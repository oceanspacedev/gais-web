<?php

namespace App\Services\Sumo\Concerns;

use App\Enums\ItemRequestApprovalType;
use Illuminate\Support\Facades\DB;

/**
 * Pengajuan barang beserta detail, approval, dan log. Id sumo dipertahankan
 * supaya kode REQ{id} dan QR yang sudah dicetak tetap berlaku.
 */
trait ImportsItemRequests
{
    protected function importItemRequests(): void
    {
        $this->source('requests')->chunkById(500, function ($requests): void {
            $rows = [];

            foreach ($requests as $request) {
                if (! $this->has('item_request_types', $request->request_type_id ?? null)) {
                    $this->report->warn("Pengajuan sumo #{$request->id} dilewati: tipe #{$request->request_type_id} tidak ada.");

                    continue;
                }

                $userId = $this->mapUser($request->user_id ?? null);

                if ($userId === null) {
                    $this->report->add('item_requests.without_user');
                }

                $rows[] = [
                    'id' => (int) $request->id,
                    'request_code' => $this->str($request->request_code ?? null) ?? config('item-request.code_prefix', 'REQ').$request->id,
                    'user_id' => $userId,
                    'item_request_type_id' => (int) $request->request_type_id,
                    'business_entity_id' => $this->entityOfSumoUser($request->user_id ?? null),
                    'requested_at' => $this->dateOrNull($request->date ?? null) ?? $this->dateOrNull($request->created_at ?? null) ?? now()->format('Y-m-d H:i:s'),
                    'total_cost' => (float) ($request->total_cost ?? 0),
                    'po_status' => (int) ($request->status_po ?? 0) === 1,
                    'po_number' => $this->str($request->po_number ?? null),
                    'client_status' => (int) ($request->status_client ?? 0),
                    'closed_by_user_id' => $this->mapUser($request->closed_by ?? null),
                    'closed_at' => $this->dateOrNull($request->closed_at ?? null),
                    'request_file' => $this->str($request->request_file ?? null),
                    'request_file_2' => $this->str($request->request_file_2 ?? null),
                    'approved_file' => $this->str($request->approved_file ?? null),
                    'notes' => $this->str($request->notes ?? null),
                    'user_notes' => $this->str($request->user_notes ?? null),
                    'audit_notes' => $this->str($request->audit_notes ?? null),
                ] + $this->timestamps($request);
            }

            $this->upsert('item_requests', $rows, [
                'request_code', 'user_id', 'item_request_type_id', 'business_entity_id', 'requested_at', 'total_cost', 'po_status',
                'po_number', 'client_status', 'closed_by_user_id', 'closed_at', 'request_file', 'request_file_2', 'approved_file',
                'notes', 'user_notes', 'audit_notes', 'updated_at', 'deleted_at',
            ], 'item_requests');
        });

        $this->importItemRequestDetails();
        $this->importItemRequestApprovals();
        $this->importItemRequestLogs();
    }

    private function importItemRequestDetails(): void
    {
        $this->source('request_details')->chunkById(1000, function ($details): void {
            $rows = [];

            foreach ($details as $detail) {
                if (! $this->has('item_requests', $detail->request_id ?? null)) {
                    $this->report->add('item_request_details.orphan_skipped');

                    continue;
                }

                $rows[] = [
                    'id' => (int) $detail->id,
                    'item_request_id' => (int) $detail->request_id,
                    'product_id' => $this->idOrNull('products', $detail->product_id ?? null),
                    'qty_request' => (float) ($detail->qty_request ?? 0),
                    'qty_remaining' => $detail->qty_remaining ?? null,
                    'qty_approved' => $detail->qty_approved ?? null,
                    'description' => $this->str($detail->description ?? null),
                ] + $this->timestamps($detail);
            }

            $this->upsert('item_request_details', $rows, ['item_request_id', 'product_id', 'qty_request', 'qty_remaining', 'qty_approved', 'description', 'updated_at', 'deleted_at'], 'item_request_details');
        });
    }

    private function importItemRequestApprovals(): void
    {
        $types = array_map(fn (ItemRequestApprovalType $type): string => $type->value, ItemRequestApprovalType::cases());
        // Tahap yang sudah ada di GAIS (bisa sudah diisi di GAIS) tidak ditimpa duplikat sumo.
        $existing = DB::table('item_request_approvals')->get(['item_request_id', 'approval_type'])
            ->mapWithKeys(fn (object $row): array => [$row->item_request_id.'|'.$row->approval_type => true])->all();

        $this->source('request_approvals')->chunkById(1000, function ($approvals) use ($types, $existing): void {
            $rows = [];

            foreach ($approvals as $approval) {
                $type = strtoupper((string) ($approval->approval_type ?? ''));

                if (! $this->has('item_requests', $approval->request_id ?? null) || ! in_array($type, $types, true)) {
                    $this->report->add('item_request_approvals.orphan_skipped');

                    continue;
                }

                $key = (int) $approval->request_id.'|'.$type;

                if (isset($existing[$key])) {
                    $this->report->add('item_request_approvals.existing');

                    continue;
                }

                // Satu baris per tahap; bila sumo punya duplikat, baris terakhir yang menang.
                $rows[$key] = [
                    'id' => (int) $approval->id,
                    'item_request_id' => (int) $approval->request_id,
                    'approval_type' => $type,
                    'approved_by_user_id' => $this->mapUser($approval->approved_by ?? null),
                    'approved_at' => $this->dateOrNull($approval->approved_at ?? null),
                ] + $this->timestamps($approval);
            }

            $this->upsert('item_request_approvals', array_values($rows), ['item_request_id', 'approval_type', 'approved_by_user_id', 'approved_at', 'updated_at', 'deleted_at'], 'item_request_approvals');
        });
    }

    private function importItemRequestLogs(): void
    {
        $rows = [];

        foreach ($this->source('request_logs')->orderBy('id')->get() as $log) {
            if (! $this->has('item_requests', $log->request_id ?? null)) {
                $this->report->add('item_request_logs.orphan_skipped');

                continue;
            }

            $rows[] = [
                'id' => (int) $log->id,
                'item_request_id' => (int) $log->request_id,
                'user_id' => $this->mapUser($log->user_id ?? null),
                'activity' => $this->str($log->activity ?? null) ?? '-',
            ] + $this->timestamps($log);
        }

        $this->upsert('item_request_logs', $rows, ['item_request_id', 'user_id', 'activity', 'updated_at', 'deleted_at'], 'item_request_logs');
    }
}
