<?php

namespace App\Services\Sumo\Concerns;

use App\Models\ItemRequestScope;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Akun sumo menjadi user GAIS (password bcrypt lama tetap dipakai), lalu
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
        $gaisId = $this->mapUser($sumoId);

        return $gaisId === null ? null : ($this->userEntity[$gaisId] ?? null);
    }

    protected function importUsers(): void
    {
        $entities = config('sumo-import.business_entities', []);
        $accessAllRoles = array_map('intval', config('sumo-import.access_all_business_entities_roles', []));
        $suffix = (string) config('sumo-import.inactive_username_suffix', '-nonaktif-sumo');
        $links = config('sumo-import.user_links', []);
        $approvers = [];
        $legacyUsers = $this->source('users')->orderBy('id')->get();
        $referenced = $this->referencedSumoUserIds();

        $this->noteEntityMapping($legacyUsers, $entities);

        $sumoNames = $legacyUsers
            ->filter(fn (object $legacy): bool => $this->dateOrNull($legacy->deleted_at ?? null) === null)
            ->countBy(fn (object $legacy): string => $this->personName($legacy->fullname ?? null) ?? '')
            ->all();
        $gaisByName = DB::table('users')->whereNull('legacy_sumo_user_id')->get(['id', 'name'])
            ->groupBy(fn (object $user): string => $this->personName($user->name) ?? '')
            ->map(fn ($users): array => $users->pluck('id')->map(fn ($id): int => (int) $id)->all())
            ->all();

        $this->accessTargets = [];

        foreach ($legacyUsers as $legacy) {
            $sumoId = (int) $legacy->id;

            // Sudah tertaut di impor sebelumnya: akun GAIS-nya tidak disentuh lagi.
            if (isset($this->userMap[$sumoId])) {
                $this->report->add('users.existing');

                continue;
            }

            $deleted = $this->dateOrNull($legacy->deleted_at ?? null) !== null;
            $username = $this->str($legacy->username ?? null);

            // Akun terhapus hanya dibuat demi riwayat; tanpa riwayat tidak perlu ada di GAIS.
            if ($deleted && ! isset($referenced[$sumoId]) && ! array_key_exists($sumoId, $links)) {
                $this->report->add('users.skipped_unused');

                continue;
            }

            if ($deleted && $username !== null) {
                $username .= $suffix.$sumoId;
            }

            $entityId = $this->mappedEntity($this->intOrNull($legacy->badan_usaha_id ?? null), $entities);
            $divisionId = $this->idOrNull('divisions', $legacy->division_id ?? null);
            $gaisId = $this->matchGaisUser($legacy, $sumoId, $username, $deleted, $links, $sumoNames, $gaisByName);

            if ($gaisId === null) {
                $gaisId = (int) DB::table('users')->insertGetId([
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
                $current = DB::table('users')->where('id', $gaisId)->first(['business_entity_id', 'division_id', 'username', 'password']);
                $update = [
                    'legacy_sumo_user_id' => $sumoId,
                    'business_entity_id' => $current?->business_entity_id ?? $entityId,
                    'division_id' => $current?->division_id ?? $divisionId,
                ];

                // Akun GAIS tanpa login (mis. data karyawan dari Talenta) dilengkapi login
                // sumo supaya orangnya tetap bisa masuk; login GAIS yang sudah ada tidak diubah.
                if (! $deleted && $current !== null) {
                    $usernameFree = $username !== null && ! DB::table('users')
                        ->where('id', '<>', $gaisId)
                        ->whereRaw('LOWER(username) = ?', [mb_strtolower($username)])
                        ->exists();

                    if ($current->username === null && $usernameFree) {
                        $update['username'] = $username;
                    }

                    if ($current->password === null && filled($legacy->password ?? null)) {
                        $update['password'] = (string) $legacy->password;
                    }

                    if (isset($update['username']) || isset($update['password'])) {
                        $this->report->add('users.login_filled');
                    }
                }

                DB::table('users')->where('id', $gaisId)->update($update);
                $entityId = $current?->business_entity_id === null ? $entityId : (int) $current->business_entity_id;
                $this->report->add('users.linked');
            }

            $this->userMap[$sumoId] = $gaisId;
            $this->userEntity[$gaisId] = $entityId;
            $this->accessTargets[$gaisId] = true;
            $approvers[$sumoId] = $this->intOrNull($legacy->approval_id ?? null);
        }

        foreach ($approvers as $sumoId => $approverSumoId) {
            if ($approverSumoId !== null && $approverSumoId > 0 && $approverSumoId !== $sumoId && isset($this->userMap[$approverSumoId])) {
                // Atasan yang sudah diatur di GAIS dipertahankan.
                $set = DB::table('users')->where('id', $this->userMap[$sumoId])->whereNull('approver_user_id')
                    ->update(['approver_user_id' => $this->userMap[$approverSumoId]]);
                $this->report->add('users.approver_set', $set);
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

            // Cakupan hanya diisi pada impor pertama; sesudahnya diatur (atau dihapus) di menu Divisi.
            if (! $this->firstImport || ItemRequestScope::query()->where('division_id', (int) $divisionId)->exists()) {
                $this->report->add('scopes.existing', count($entries));

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
        $divisionPermissionRoles = array_map('intval', config('sumo-import.division_permission_roles', [3]));
        $legacyPermissions = config('sumo-import.legacy_user_permissions', []);
        $accessAllRoles = array_map('intval', config('sumo-import.access_all_business_entities_roles', []));
        $divisionNames = $this->source('divisions')->pluck('division', 'id')->all();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->source('users')->whereNull('deleted_at')->orderBy('id')->get() as $legacy) {
            $user = User::query()->find($this->mapUser($legacy->id));

            if (! $user instanceof User) {
                continue;
            }

            // Role/izin akun yang sudah tertaut di impor sebelumnya bisa sudah diubah admin di GAIS.
            if ($this->accessTargets !== null && ! isset($this->accessTargets[$user->id])) {
                $this->report->add('access.existing');

                continue;
            }

            $roleName = $roles[(int) ($legacy->role_id ?? 0)] ?? null;

            if ($roleName !== null) {
                $user->assignRole(Role::findOrCreate($roleName, 'web'));
                $this->report->add("access.role.{$roleName}");
            }

            $byDivision = in_array((int) ($legacy->role_id ?? 0), $divisionPermissionRoles, true)
                ? ($divisionPermissions[(string) ($divisionNames[$legacy->division_id ?? 0] ?? '')] ?? [])
                : [];

            $permissions = [
                ...$byDivision,
                ...($legacyPermissions[(int) $legacy->id] ?? []),
            ];

            if ($permissions !== []) {
                $user->givePermissionTo(array_map(fn (string $name): Permission => Permission::findOrCreate($name, 'web'), $permissions));
                $this->report->add('access.extra_permissions', count($permissions));
            }

            if (in_array((int) ($legacy->role_id ?? 0), $accessAllRoles, true) && ! $user->access_all_business_entities) {
                // Akses semua badan usaha berlaku di seluruh GAIS (aset, BA, dst.), jadi akun
                // yang sudah memakai GAIS dengan role lain tidak diperluas diam-diam.
                if ($user->roles()->whereNotIn('name', array_values($roles))->exists()) {
                    $this->report->warn("User GAIS #{$user->id} ({$user->name}) tidak otomatis diberi akses semua badan usaha karena sudah memakai GAIS dengan role lain; berikan manual bila perlu.");
                } else {
                    $user->forceFill(['access_all_business_entities' => true])->saveQuietly();
                }
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Akun GAIS milik orang yang sama, supaya tidak ada dua akun untuk satu orang:
     * pemetaan manual di config, lalu username, lalu nama lengkap yang unik di kedua
     * sisi. Akun sumo yang sudah dihapus hanya digabung lewat config.
     *
     * @param  array<int, int|false|null>  $links  id user sumo => id user GAIS (false = selalu akun baru)
     * @param  array<string, int>  $sumoNames  nama => jumlah user sumo aktif
     * @param  array<string, list<int>>  $gaisByName  nama => id user GAIS yang belum tertaut ke sumo
     */
    private function matchGaisUser(object $legacy, int $sumoId, ?string $username, bool $deleted, array $links, array $sumoNames, array &$gaisByName): ?int
    {
        if (array_key_exists($sumoId, $links)) {
            $target = $links[$sumoId];

            if ($target === false || $target === null) {
                return null;
            }

            if (! DB::table('users')->where('id', (int) $target)->exists()) {
                $this->report->warn("sumo-import.user_links: user GAIS #{$target} untuk user sumo #{$sumoId} tidak ada; dibuat akun baru.");

                return null;
            }

            $this->report->add('users.linked_by_config');

            return (int) $target;
        }

        if ($deleted) {
            return null;
        }

        if ($username !== null) {
            $existing = DB::table('users')->whereRaw('LOWER(username) = ?', [mb_strtolower($username)])->value('id');

            if ($existing !== null) {
                $this->report->warn("User sumo #{$sumoId} ({$username}) digabung ke user GAIS #{$existing} karena username-nya sama; nama dan login GAIS dipertahankan.");
                $this->report->add('users.linked_by_username');

                return (int) $existing;
            }
        }

        $name = $this->personName($legacy->fullname ?? null);

        // Satu kata ("ADMIN", "MEGA") terlalu umum untuk dianggap orang yang sama.
        if ($name === null || ! str_contains($name, ' ') || ! isset($gaisByName[$name])) {
            return null;
        }

        $candidates = $gaisByName[$name];

        if (count($candidates) > 1 || ($sumoNames[$name] ?? 0) > 1) {
            $this->report->warn("User sumo #{$sumoId} ({$username}) tidak digabung otomatis: nama \"{$name}\" dipakai ".count($candidates).' user GAIS dan '.($sumoNames[$name] ?? 0).' user sumo aktif. Isi sumo-import.user_links bila orangnya sama.');

            return null;
        }

        $this->report->warn("User sumo #{$sumoId} ({$username}) digabung ke user GAIS #{$candidates[0]} karena nama lengkapnya sama: {$name}.");
        $this->report->add('users.linked_by_name');
        unset($gaisByName[$name]);

        return $candidates[0];
    }

    /**
     * Id user sumo yang dirujuk data yang ikut diimpor (pemohon, approver, penutup,
     * pencatat log/kontrak, atasan).
     *
     * @return array<int, true>
     */
    private function referencedSumoUserIds(): array
    {
        $columns = [
            'requests' => ['user_id', 'closed_by'],
            'request_approvals' => ['approved_by'],
            'request_logs' => ['user_id'],
            'problem_report' => ['user_id', 'closed_by'],
            'insurances' => ['user_id'],
            'insurance_updates' => ['user_id'],
            'rents' => ['user_id'],
            'rent_updates' => ['user_id'],
            'users' => ['approval_id'],
        ];
        $schema = DB::connection($this->connection)->getSchemaBuilder();
        $ids = [];

        foreach ($columns as $table => $tableColumns) {
            foreach ($tableColumns as $column) {
                if (! $schema->hasColumn($table, $column)) {
                    continue;
                }

                foreach ($this->source($table)->whereNotNull($column)->distinct()->pluck($column) as $id) {
                    $ids[(int) $id] = true;
                }
            }
        }

        return $ids;
    }

    private function personName(mixed $value): ?string
    {
        $value = $this->str($value);

        return $value === null ? null : mb_strtoupper((string) preg_replace('/\s+/u', ' ', $value));
    }

    /**
     * Pemetaan badan usaha yang dipakai user sumo, ditampilkan di laporan supaya
     * bisa diperiksa sebelum impor sungguhan (config sumo-import.business_entities).
     *
     * @param  Collection<int, object>  $legacyUsers
     * @param  array<int, int|null>  $entities
     */
    private function noteEntityMapping($legacyUsers, array $entities): void
    {
        $used = $legacyUsers->whereNull('deleted_at')->countBy(fn (object $legacy): int => (int) ($legacy->badan_usaha_id ?? 0));
        $sumoNames = $this->source('badan_usahas')->pluck('badan_usaha', 'id')->all();
        $gaisNames = DB::table('business_entities')->pluck('name', 'id')->all();

        foreach ($used->sortKeys() as $sumoEntityId => $users) {
            $target = $entities[$sumoEntityId] ?? null;
            $label = $target === null ? '(tanpa badan usaha)' : "GAIS #{$target} ".($gaisNames[$target] ?? '(TIDAK ADA)');

            $this->report->note("Badan usaha sumo #{$sumoEntityId} ".($sumoNames[$sumoEntityId] ?? '?')." ({$users} user aktif) -> {$label}");
        }
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

        $gaisEntityId = $entities[$sumoEntityId];

        if ($gaisEntityId !== null && ! $this->has('business_entities', $gaisEntityId)) {
            $this->report->warn("Badan usaha GAIS #{$gaisEntityId} (peta badan usaha sumo #{$sumoEntityId}) tidak ditemukan.");

            return null;
        }

        return $gaisEntityId === null ? null : (int) $gaisEntityId;
    }
}
