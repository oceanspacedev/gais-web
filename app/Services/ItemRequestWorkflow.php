<?php

namespace App\Services;

use App\Enums\ItemRequestApprovalType;
use App\Enums\ItemRequestClientStatus;
use App\Enums\ItemRequestExecutorDecision;
use App\Exceptions\ItemRequestException;
use App\Models\Division;
use App\Models\ItemRequest;
use App\Models\ItemRequestApproval;
use App\Models\ItemRequestDetail;
use App\Models\ItemRequestScope;
use App\Models\ItemRequestType;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Aturan alur pengajuan barang (sumo: RequestController), dipakai resource
 * Filament dan test: kuota pengajuan terbuka, syarat per tipe, dan tahap
 * Accounting -> Manager -> Executor -> konfirmasi pemohon.
 */
class ItemRequestWorkflow
{
    public function maxOpenPerType(): int
    {
        return max(1, (int) config('item-request.max_open_per_type', 2));
    }

    public function openCountByType(User $requester, int $typeId): int
    {
        return ItemRequest::query()
            ->where('user_id', $requester->id)
            ->where('item_request_type_id', $typeId)
            ->open()
            ->count();
    }

    /**
     * Tipe aktif yang kuota pengajuan terbukanya belum habis untuk pemohon ini.
     *
     * @return Collection<int, ItemRequestType>
     */
    public function availableTypes(User $requester): Collection
    {
        return ItemRequestType::query()
            ->active()
            ->orderBy('id')
            ->get()
            ->filter(fn (ItemRequestType $type): bool => $this->openCountByType($requester, $type->id) < $this->maxOpenPerType())
            ->values();
    }

    public function assertCanCreate(User $requester, ItemRequestType $type): void
    {
        if (! $type->is_active) {
            throw new ItemRequestException("Tipe pengajuan {$type->name} sudah tidak aktif.");
        }

        if ($this->openCountByType($requester, $type->id) >= $this->maxOpenPerType()) {
            throw new ItemRequestException(sprintf(
                'Harap menunggu hingga pengajuan %s sebelumnya diproses dan status akhirnya diselesaikan (maksimal %d pengajuan terbuka per tipe).',
                $type->name,
                $this->maxOpenPerType(),
            ));
        }
    }

    /**
     * @param  array{user_id?: int|string|null, item_request_type_id: int|string, request_file?: string|null, request_file_2?: string|null, items?: list<array{product_id?: int|string|null, qty_request?: float|int|string|null, description?: string|null}>}  $data
     */
    public function create(User $actor, array $data): ItemRequest
    {
        $requester = filled($data['user_id'] ?? null) ? User::query()->findOrFail($data['user_id']) : $actor;
        $type = ItemRequestType::query()->findOrFail($data['item_request_type_id']);

        $this->assertCanCreate($requester, $type);

        $items = collect($data['items'] ?? [])
            ->filter(fn (array $item): bool => filled($item['product_id'] ?? null) && (float) ($item['qty_request'] ?? 0) > 0)
            ->values();

        if ($type->requires_attachment && blank($data['request_file'] ?? null)) {
            throw new ItemRequestException('Harap unggah file persetujuan atasan.');
        }

        if ($type->requires_products && $items->isEmpty()) {
            throw new ItemRequestException('Harap pilih barang yang akan diajukan.');
        }

        return DB::transaction(function () use ($actor, $requester, $type, $data, $items): ItemRequest {
            $request = ItemRequest::create([
                'user_id' => $requester->id,
                'item_request_type_id' => $type->id,
                'business_entity_id' => $requester->business_entity_id,
                'requested_at' => now(),
                'request_file' => $data['request_file'] ?? null,
                'request_file_2' => $data['request_file_2'] ?? null,
            ]);

            foreach ($items as $item) {
                $request->details()->create([
                    'product_id' => (int) $item['product_id'],
                    'qty_request' => (float) $item['qty_request'],
                    'description' => $item['description'] ?? null,
                ]);
            }

            if ($requester->isNot($actor)) {
                $this->log($request, $actor, "{$actor->name} membuat pengajuan atas nama {$requester->name}");
            }

            return $request->fresh(['details', 'approvals']);
        });
    }

    /**
     * Tahap Accounting: status dan nomor PO.
     */
    public function approveAccounting(ItemRequest $request, User $actor, bool $poStatus, ?string $poNumber = null): void
    {
        $this->assertNotFinal($request);

        DB::transaction(function () use ($request, $actor, $poStatus, $poNumber): void {
            $request->forceFill(['po_status' => $poStatus, 'po_number' => $poNumber])->save();
            $this->approval($request, ItemRequestApprovalType::Accounting)->markApproved($actor);
        });
    }

    /**
     * Tahap Manager: jumlah yang belum diisi disetujui sesuai jumlah diminta (sumo: fixRequest).
     * Bila pengajuan masuk cakupan divisi yang menyetujui jumlah, hanya divisi itu yang boleh.
     */
    public function approveManager(ItemRequest $request, User $actor): void
    {
        $this->assertNotFinal($request);

        if (! $this->canApproveQuantity($actor, $request)) {
            throw new ItemRequestException('Jumlah pengajuan ini disetujui oleh divisi yang ditunjuk pada cakupan pengajuan (menyetujui jumlah).');
        }

        DB::transaction(function () use ($request, $actor): void {
            $request->details()->whereNull('qty_approved')->update(['qty_approved' => DB::raw('qty_request')]);
            $this->approval($request, ItemRequestApprovalType::Manager)->markApproved($actor);
        });
    }

    public function updateApprovedQty(ItemRequestDetail $detail, float $qtyApproved): void
    {
        $this->assertNotFinal($detail->request);

        $detail->forceFill(['qty_approved' => max(0, $qtyApproved)])->save();
    }

    /**
     * Tahap Executor: menunggu, diproses, selesai, atau dibatalkan.
     */
    public function process(ItemRequest $request, User $actor, ItemRequestExecutorDecision $decision, ?string $notes = null): void
    {
        if ($request->client_status === ItemRequestClientStatus::Accepted) {
            throw new ItemRequestException('Pengajuan sudah dikonfirmasi diterima oleh pemohon.');
        }

        if (! $this->executorMayProcess($request)) {
            throw new ItemRequestException(sprintf(
                'Pengajuan belum lewat tahap %s; executor belum bisa memproses.',
                $this->pendingStageBeforeExecutor($request)?->label() ?? '-',
            ));
        }

        DB::transaction(function () use ($request, $actor, $decision, $notes): void {
            $approval = $this->approval($request, ItemRequestApprovalType::Executor);

            if ($decision === ItemRequestExecutorDecision::Pending) {
                $approval->reset();
            } else {
                $approval->markApproved($actor);
            }

            $closes = in_array($decision, [ItemRequestExecutorDecision::Closed, ItemRequestExecutorDecision::Cancelled], true);

            $request->forceFill([
                'client_status' => $decision->clientStatus(),
                'notes' => $notes,
                'closed_by_user_id' => $closes ? $actor->id : null,
                'closed_at' => $closes ? now() : null,
            ])->save();
        });
    }

    /**
     * Konfirmasi pemohon setelah executor menyelesaikan pengajuan.
     */
    public function confirm(ItemRequest $request, User $actor, bool $accepted, ?string $userNotes = null): void
    {
        if (! $request->isApproved(ItemRequestApprovalType::Executor)) {
            throw new ItemRequestException('Pengajuan belum diselesaikan oleh executor.');
        }

        if ($request->client_status === ItemRequestClientStatus::Accepted) {
            throw new ItemRequestException('Pengajuan sudah dikonfirmasi sebelumnya.');
        }

        DB::transaction(function () use ($request, $actor, $accepted, $userNotes): void {
            $request->forceFill([
                'user_notes' => $userNotes,
                'client_status' => $accepted ? ItemRequestClientStatus::Accepted : $request->client_status,
            ])->save();

            if ($accepted) {
                $this->approval($request, ItemRequestApprovalType::EndUser)->markApproved($actor);
            }
        });
    }

    public function cancel(ItemRequest $request, User $actor, ?string $reason = null): void
    {
        $this->assertNotFinal($request);

        DB::transaction(function () use ($request, $actor, $reason): void {
            $request->forceFill([
                'client_status' => ItemRequestClientStatus::Cancelled,
                'closed_by_user_id' => $actor->id,
                'closed_at' => now(),
                'notes' => $reason ?? $request->notes,
            ])->save();

            $this->log($request, $actor, "{$actor->name} membatalkan pengajuan".(filled($reason) ? ": {$reason}" : ''));
        });
    }

    public function changeApplicant(ItemRequest $request, User $actor, User $newApplicant): void
    {
        DB::transaction(function () use ($request, $actor, $newApplicant): void {
            $previous = $request->user?->name ?? '-';

            $request->forceFill([
                'user_id' => $newApplicant->id,
                'business_entity_id' => $newApplicant->business_entity_id,
            ])->save();

            $this->log($request, $actor, "{$actor->name} merubah pemohon {$previous} menjadi {$newApplicant->name}");
        });
    }

    public function updateAuditNotes(ItemRequest $request, ?string $notes): void
    {
        $request->forceFill(['audit_notes' => $notes])->save();
    }

    /**
     * Pengajuan yang menunggu tindakan executor (sumo: filter "belum diproses"):
     * tahap Accounting dan Manager yang wajib sudah lewat, executor belum mengisi.
     */
    public function scopeAwaitingExecutor(Builder $query): Builder
    {
        $query
            ->where('client_status', ItemRequestClientStatus::Waiting->value)
            ->whereHas('approvals', fn (Builder $approvals) => $approvals
                ->where('approval_type', ItemRequestApprovalType::Executor->value)
                ->whereNull('approved_by_user_id'))
            ->where(fn (Builder $ready) => $ready
                ->whereHas('type', fn (Builder $type) => $type->where('requires_accounting_approval', false))
                ->orWhereHas('approvals', fn (Builder $approvals) => $approvals
                    ->where('approval_type', ItemRequestApprovalType::Accounting->value)
                    ->whereNotNull('approved_by_user_id')));

        $scopes = $this->quantityApproverScopes();

        if ($scopes->isEmpty()) {
            return $query;
        }

        // Subquery tanpa korelasi (whereIn) seperti ItemRequestVisibility, supaya
        // MySQL cukup menghitungnya sekali.
        return $query->where(function (Builder $ready) use ($scopes): void {
            $ready->whereHas('approvals', fn (Builder $approvals) => $approvals
                ->where('approval_type', ItemRequestApprovalType::Manager->value)
                ->whereNotNull('approved_by_user_id'));

            $ready->orWhere(function (Builder $outside) use ($scopes): void {
                foreach ($scopes as $scope) {
                    $outside->whereNot(function (Builder $inside) use ($scope): void {
                        if ($scope->coversEverything()) {
                            $inside->whereNotNull('item_requests.id');

                            return;
                        }

                        if ($scope->item_request_type_id !== null) {
                            $inside->where('item_requests.item_request_type_id', $scope->item_request_type_id);
                        }

                        if ($scope->area_id !== null) {
                            $inside->whereIn('item_requests.user_id', User::query()->select('users.id')->whereIn(
                                'division_id',
                                Division::query()->select('divisions.id')->where('area_id', $scope->area_id),
                            ));
                        }
                    });
                }
            });
        });
    }

    /**
     * Tahap Manager wajib bila pengajuan masuk cakupan divisi yang menyetujui jumlah
     * (item_request_scopes.approves_quantity; sumo: ATK dari area TOP/MKLI menunggu
     * WHM/MKLI-HO, NOTA menunggu AUDIT).
     */
    public function requiresManagerApproval(ItemRequest $request): bool
    {
        return $this->quantityApproverScopesFor($request)->isNotEmpty();
    }

    /**
     * Siapa boleh menyetujui jumlah: siapa saja bila tahap Manager tidak wajib untuk
     * pengajuan ini; bila wajib, anggota divisi yang cakupannya menyetujui jumlah
     * (super admin selalu). Izin Shield-nya dicek terpisah oleh resource.
     */
    public function canApproveQuantity(?User $user, ItemRequest $request): bool
    {
        if ($user === null) {
            return false;
        }

        $scopes = $this->quantityApproverScopesFor($request);

        if ($scopes->isEmpty() || $user->isSuperAdmin()) {
            return true;
        }

        return $user->division_id !== null
            && $scopes->contains(fn (ItemRequestScope $scope): bool => (int) $scope->division_id === (int) $user->division_id);
    }

    /**
     * Tahap yang masih harus lewat sebelum executor boleh memproses: Accounting
     * (tipe yang mensyaratkannya) lalu Manager (bila wajib). null = siap diproses.
     */
    public function pendingStageBeforeExecutor(ItemRequest $request): ?ItemRequestApprovalType
    {
        if ($request->type?->requires_accounting_approval && ! $request->isApproved(ItemRequestApprovalType::Accounting)) {
            return ItemRequestApprovalType::Accounting;
        }

        if ($this->requiresManagerApproval($request) && ! $request->isApproved(ItemRequestApprovalType::Manager)) {
            return ItemRequestApprovalType::Manager;
        }

        return null;
    }

    /**
     * Executor boleh memproses bila sudah pernah mengisi tahapnya (mis. status
     * Diproses) atau semua tahap sebelumnya sudah lewat (sumo: tombol ubah status).
     */
    public function executorMayProcess(ItemRequest $request): bool
    {
        return $request->isApproved(ItemRequestApprovalType::Executor) || $this->pendingStageBeforeExecutor($request) === null;
    }

    /**
     * Cakupan "menyetujui jumlah" yang mengenai pengajuan ini.
     *
     * @return Collection<int, ItemRequestScope>
     */
    private function quantityApproverScopesFor(ItemRequest $request): Collection
    {
        $scopes = $this->quantityApproverScopes();

        if ($scopes->isEmpty()) {
            return $scopes;
        }

        $request->loadMissing('user.division');
        $areaId = $request->user?->division?->area_id;

        return $scopes
            ->filter(fn (ItemRequestScope $scope): bool => $scope->matches($request->item_request_type_id, $areaId))
            ->values();
    }

    /**
     * Dimemo selama satu request/job: dibaca untuk tiap baris tabel dan tiap aksi.
     *
     * @return Collection<int, ItemRequestScope>
     */
    private function quantityApproverScopes(): Collection
    {
        return once(fn (): Collection => ItemRequestScope::query()->where('approves_quantity', true)->get());
    }

    private function approval(ItemRequest $request, ItemRequestApprovalType $type): ItemRequestApproval
    {
        // Baris approval diubah lewat instance terpisah; relasi yang sudah dimuat
        // dibuang supaya isApproved() membaca data terbaru.
        $request->unsetRelation('approvals');

        return $request->approvals()->firstOrCreate(['approval_type' => $type->value]);
    }

    private function assertNotFinal(ItemRequest $request): void
    {
        if (in_array($request->client_status, [ItemRequestClientStatus::Accepted, ItemRequestClientStatus::Cancelled], true)) {
            throw new ItemRequestException('Pengajuan sudah selesai dikonfirmasi atau dibatalkan.');
        }
    }

    private function log(ItemRequest $request, User $actor, string $activity): void
    {
        $request->logs()->create(['user_id' => $actor->id, 'activity' => $activity]);
    }
}
