<?php

namespace App\Support;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

/**
 * Pencarian kolom tabel milik relasi belongsTo (mis. nama pemohon) lewat
 * `foreign_key IN (SELECT id ... LIKE)`. Bawaan Filament memakai whereHas, yaitu
 * EXISTS yang dicek per baris; subquery IN dihitung MySQL sekali lalu dicocokkan
 * lewat index FK. Di 4 ribu pengajuan pencarian pemohon turun dari ±25 ms ke ±4 ms.
 *
 * Pakai: TextColumn::make('user.name')->searchable(query: RelationSearch::on('user', 'name'))
 */
final class RelationSearch
{
    public static function on(string $relation, string $column): Closure
    {
        return function (Builder $query, string $search) use ($relation, $column): Builder {
            $belongsTo = $query->getModel()->{$relation}();

            if (! $belongsTo instanceof BelongsTo) {
                throw new InvalidArgumentException("Relasi [{$relation}] harus belongsTo.");
            }

            return $query->whereIn(
                $belongsTo->getQualifiedForeignKeyName(),
                $belongsTo->getRelated()->newQuery()
                    ->select($belongsTo->getQualifiedOwnerKeyName())
                    ->where($belongsTo->getRelated()->qualifyColumn($column), 'like', '%'.$search.'%'),
            );
        };
    }
}
