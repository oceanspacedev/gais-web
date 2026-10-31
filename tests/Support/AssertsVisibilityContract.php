<?php

namespace Tests\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Kontrak App\Access: query visibleTo() dan keputusan isVisibleTo() harus sepakat
 * untuk setiap viewer dan record, baik ketika relasinya sudah dimuat (tabel)
 * maupun belum (halaman view/edit, aksi per baris), termasuk record terhapus.
 */
trait AssertsVisibilityContract
{
    /**
     * @param  class-string<Model>  $model
     * @param  array<string, User>  $viewers  label => viewer
     * @param  list<int>  $ids  record yang dibandingkan, termasuk yang terhapus
     * @param  array<string, list<string>>  $loadings  label => relasi yang dimuat sebelum isVisibleTo()
     */
    protected function assertQueryAndPolicyAgree(string $model, array $viewers, array $ids, array $loadings = ['lazy' => []]): void
    {
        $softDeletes = in_array(SoftDeletes::class, class_uses_recursive($model), true);

        foreach ($viewers as $label => $viewer) {
            // Termasuk record terhapus: status soft delete tidak mengubah aturan lihat.
            $visible = ($softDeletes ? $model::withTrashed() : $model::query())->whereKey($ids)->visibleTo($viewer)->pluck('id')->all();

            foreach ($loadings as $loading => $relations) {
                $records = ($softDeletes ? $model::withTrashed() : $model::query())->whereKey($ids)->with($relations)->get();

                foreach ($records as $record) {
                    $this->assertSame(
                        in_array($record->getKey(), $visible, true),
                        $record->isVisibleTo($viewer),
                        "{$label} / {$loading} / {$model} #{$record->getKey()}",
                    );
                }
            }
        }
    }
}
