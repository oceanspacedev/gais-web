<?php

namespace App\Services\Sumo\Concerns;

use App\Models\ItemRequestScope;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Akun sumo menjadi user shelf (password bcrypt lama tetap dipakai), lalu
 * role, izin per divisi, cakupan pengajuan, dan akses badan usaha.
 */
trait ImportsUsers
{
    protected function loadUserMap(): void
    {
        $this->userMap = [];
        $this->userEntity = [];

        foreach (DB::table('users')->whereNotNull('legacy_sumo_user_id')->get(['id', 'legacy_sumo_user_id', 'business_entity_id']) as $user) {
            $this->userMap[(int) $user->legacy_sumo_user_id] = (int) $user->id;
            $this->userEntity[(int) $user->id] = $user->business_entity_id === null ? null : (int) $user->business_entity_id;
        }
    }

    protected function mapUser(mixed $sumoId): ?int
    {
        $sumoId = $this->intOrNull($sumoId);

        return $sumoId === null ? null : ($this->userMap[$sumoId] ?? null);
    }

    protected function entityOfSumoUser(mixed $sumoId): ?int
    {
        $shelfId = $this->mapUser($sumoId);

        return $shelfId === null ? null : ($this->userEntity[$shelfId] ?? null);
    }

    protected function importUsers(): void
    {
        $entities = config('sumo-import.business_entities', []);
        $accessAllRoles = array_map('intval', config('sumo-import.access_all_business_entities_roles', []));
        $suffix = (string) config('sumo-import.inactive_username_suffix', '-nonaktif-sumo');
        $approvers = [];

        foreach ($this->source('users')->orderBy('id')->get() as $legacy) {
            $sumoId = (int) $legacy->id;
            $deleted = $this->dateOrNull($legacy->deleted_at ?? null) !== null;
            $username = $this->str($legacy->username ?? null);

            if ($deleted && $username !== null) {
                $username .= $suffix.$sumoId;
            }

            $entityId = $this->mappedEntity($this->intOrNull($legacy->badan_usaha_id ?? null), $entities);
            $divisionId = $this->idOrNull('divisions', $legacy->division_id ?? null);
            $shelfId = $this->userMap[$sumoId] ?? null;

            if ($shelfId === null && $username !== null && ! $deleted) {
                $existing = DB::table('users')->whereRaw('LOWER(username) = ?', [mb_strtolower($username)])->value('id');

                if ($existing !== null) {
                    $shelfId = (int) $existing;
                    $this->report->warn("User sumo #{$sumoId} ({$username}) dipetakan ke user shelf #{$shelfId} yang sudah ada; nama dan password shelf dipertahankan.");
                }
            }

            if ($shelfId === null) {
                $shelfId = (int) DB::table('users')->insertGetId([
                    'name' => $this->str($legacy->fullname ?? null) ?? $username ?? "Akun sumo {$sumoId}",
                    'username' => $username,
                    'email' => null,
                    'password' => (string) $legacy->password,
                    'business_entity_id' => $entityId,
                    'access_all_business_entities' => ! $deleted && in_array((int) ($legacy->role_id ?? 0), $accessAllRoles, true),
                    'division_id' => $divisionId,
                    'legacy_sumo_user_id' => $sumoId,
                ] + $this->timestamps($legacy, false));
                $this->report->add('users.created');
            } else {
                $current = DB::table('users')->where('id', $shelfId)->first(['business_entity_id', 'division_id']);

                DB::table('users')->where('id', $shelfId)->update([
                    'legacy_sumo_user_id' => $sumoId,
                    'business_entity_id' => $current?->business_entity_id ?? $entityId,
                    'division_id' => $current?->division_id ?? $divisionId,
                ]);
                $entityId = $current?->business_entity_id === null ? $entityId : (int) $current->business_entity_id;
                $this->report->add('users.linked');
            }

            $this->userMap[$sumoId] = $shelfId;
            $this->userEntity[$shelfId] = $entityId;
            $approvers[$sumoId] = $this->intOrNull($legacy->approval_id ?? null);
        }

        foreach ($approvers as $sumoId => $approverSumoId) {
            if ($approverSumoId !== null && $approverSumoId > 0 && $approverSumoId !== $sumoId && isset($this->userMap[$approverSumoId])) {
                DB::table('users')->where('id', $this->userMap[$sumoId])->update(['approver_user_id' => $this->userMap[$approverSumoId]]);
                $this->report->add('users.approver_set');
            }
        }

        $this->forgetIds('users');
    }

    protected function seedScopes(): void
    {
        foreach (config('sumo-import.scopes', []) as $divisionId => $entries) {
            if (! $this->has('divisions', $divisionId)) {
                $this->report->warn("Cakupan untuk divisi #{$divisionId} dilewati: divisi tidak ada.");

                continue;
            }

            foreach ($entries as [$typeId, $areaId]) {
                $scope = ItemRequestScope::query()->firstOrCreate([
                    'division_id' => (int) $divisionId,
                    'item_request_type_id' => $this->idOrNull('item_request_types', $typeId),
                    'area_id' => $this->idOrNull('areas', $areaId),
                ]);

                $this->report->add($scope->wasRecentlyCreated ? 'scopes.created' : 'scopes.existing');
            }
        }
    }

    protected function assignAccess(): void
    {
        $roles = config('sumo-import.roles', []);
        $divisionPermissions = config('sumo-import.division_permissions', []);
        $legacyPermissions = config('sumo-import.legacy_user_permissions', []);
        $accessAllRoles = array_map('intval', config('sumo-import.access_all_business_entities_roles', []));
        $divisionNames = $this->source('divisions')->pluck('division', 'id')->all();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->source('users')->whereNull('deleted_at')->orderBy('id')->get() as $legacy) {
            $user = User::query()->find($this->mapUser($legacy->id));

            if (! $user instanceof User) {
                continue;
            }

            $roleName = $roles[(int) ($legacy->role_id ?? 0)] ?? null;

            if ($roleName !== null) {
                $user->assignRole(Role::findOrCreate($roleName, 'web'));
                $this->report->add("access.role.{$roleName}");
            }

            $permissions = [
                ...($divisionPermissions[(string) ($divisionNames[$legacy->division_id ?? 0] ?? '')] ?? []),
                ...($legacyPermissions[(int) $legacy->id] ?? []),
            ];

            if ($permissions !== []) {
                $user->givePermissionTo(array_map(fn (string $name): Permission => Permission::findOrCreate($name, 'web'), $permissions));
                $this->report->add('access.extra_permissions', count($permissions));
            }

            if (in_array((int) ($legacy->role_id ?? 0), $accessAllRoles, true) && ! $user->access_all_business_entities) {
                $user->forceFill(['access_all_business_entities' => true])->saveQuietly();
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @param  array<int, int|null>  $entities
     */
    private function mappedEntity(?int $sumoEntityId, array $entities): ?int
    {
        if ($sumoEntityId === null) {
            return null;
        }

        if (! array_key_exists($sumoEntityId, $entities)) {
            $this->report->warn("Badan usaha sumo #{$sumoEntityId} tidak ada di config sumo-import.business_entities.");

            return null;
        }

        $shelfEntityId = $entities[$sumoEntityId];

        if ($shelfEntityId !== null && ! $this->has('business_entities', $shelfEntityId)) {
            $this->report->warn("Badan usaha shelf #{$shelfEntityId} (peta badan usaha sumo #{$sumoEntityId}) tidak ditemukan.");

            return null;
        }

        return $shelfEntityId === null ? null : (int) $shelfEntityId;
    }
}
