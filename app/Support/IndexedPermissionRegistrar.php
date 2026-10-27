<?php

namespace App\Support;

use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Contracts\Permission;
use Spatie\Permission\PermissionRegistrar;
use WeakMap;

/**
 * Spatie mencari permission per nama dengan menyaring seluruh koleksi pada
 * setiap cek izin. Satu halaman tabel memanggil can() ribuan kali (aksi per
 * baris, policy, menu), jadi pencarian nama+guard dibuat lewat index array.
 * Index dibangun ulang setiap kali koleksi permission diganti (cache dihapus).
 *
 * Hasil cek izin per pengguna juga diingat selama koleksi permission dan relasi
 * roles/permissions pengguna itu masih objek yang sama, jadi assignRole(),
 * givePermissionTo(), dan sejenisnya (yang melepas relasi) langsung terlihat.
 */
class IndexedPermissionRegistrar extends PermissionRegistrar
{
    /** @var array<string, Permission> */
    private array $byNameAndGuard = [];

    private ?Collection $indexedPermissions = null;

    /** @var WeakMap<Model, array{state: list<mixed>, results: array<string, bool>}>|null */
    private ?WeakMap $checks = null;

    public function rememberCheck(Model $model, string $permission, ?string $guardName, Closure $check): bool
    {
        $this->checks ??= new WeakMap;
        $key = $permission."\0".$guardName;
        $entry = $this->checks[$model] ?? null;

        if ($entry !== null && $entry['state'] === $this->checkState($model) && array_key_exists($key, $entry['results'])) {
            return $entry['results'][$key];
        }

        $result = $check();
        $state = $this->checkState($model);

        if ($entry === null || $entry['state'] !== $state) {
            $entry = ['state' => $state, 'results' => []];
        }

        $entry['results'][$key] = $result;
        $this->checks[$model] = $entry;

        return $result;
    }

    /**
     * @return list<mixed>
     */
    private function checkState(Model $model): array
    {
        return [
            $this->permissions,
            $model->relationLoaded('roles') ? $model->getRelation('roles') : null,
            $model->relationLoaded('permissions') ? $model->getRelation('permissions') : null,
        ];
    }

    public function getPermissions(array $params = [], bool $onlyOne = false): Collection
    {
        if (! $onlyOne || count($params) !== 2 || ! isset($params['name'], $params['guard_name'])) {
            return parent::getPermissions($params, $onlyOne);
        }

        // Pemanggilan pertama lewat parent agar koleksi dimuat dari cache.
        if (! $this->permissions instanceof Collection) {
            return parent::getPermissions($params, $onlyOne);
        }

        if ($this->indexedPermissions !== $this->permissions) {
            $this->byNameAndGuard = [];

            foreach ($this->permissions as $permission) {
                $this->byNameAndGuard[$permission->getAttribute('name')."\0".$permission->getAttribute('guard_name')] ??= $permission;
            }

            $this->indexedPermissions = $this->permissions;
        }

        $permission = $this->byNameAndGuard[$params['name']."\0".$params['guard_name']] ?? null;

        return new Collection($permission ? [$permission] : []);
    }
}
