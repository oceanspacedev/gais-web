<?php

namespace App\Access;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

/**
 * Rangkaian aturan lihat sebuah model: record terlihat bila lolos SEMUA pembatas
 * (require, mis. badan usaha) DAN dikenai SALAH SATU pemberi akses (grant, mis.
 * pemilik, atasan, cakupan divisi). Dipakai lewat trait HasVisibility:
 * scopeVisibleTo() untuk daftar dan isVisibleTo() untuk satu record, dan
 * keduanya harus selalu sepakat.
 */
final class Visibility
{
    /** @var list<Rule> */
    private array $restrictions = [];

    /** @var list<Grant> */
    private array $grants = [];

    public static function make(): self
    {
        return new self;
    }

    /**
     * Pembatas (AND): berlaku untuk semua record, apa pun grant-nya.
     */
    public function require(Rule ...$rules): self
    {
        $this->restrictions = [...$this->restrictions, ...array_values($rules)];

        return $this;
    }

    /**
     * Pemberi akses (OR): cukup satu yang mengenai record.
     */
    public function grant(Grant ...$grants): self
    {
        $this->grants = [...$this->grants, ...array_values($grants)];

        return $this;
    }

    /**
     * Subquery di dalam grant memakai whereIn tanpa korelasi (bukan whereHas per
     * baris) supaya MySQL cukup menghitungnya sekali dan bisa memakai index FK.
     */
    public function apply(Builder $query, User $user): Builder
    {
        foreach ($this->activeRestrictions($user) as $restriction) {
            $restriction->apply($query, $user);
        }

        $grants = $this->activeGrants($user);

        if ($grants === []) {
            return $query->whereRaw('1 = 0');
        }

        foreach ($grants as $grant) {
            if ($grant->grantsEverything($user)) {
                return $query;
            }
        }

        return $query->where(function (Builder $any) use ($grants, $user): void {
            foreach ($grants as $grant) {
                $any->orWhere(function (Builder $granted) use ($grant, $user): void {
                    $grant->apply($granted, $user);

                    // Grup kosong dibuang Laravel diam-diam, yang berarti "tidak ada
                    // akses" padahal grant ini bermaksud memberi akses.
                    if ($granted->getQuery()->wheres === []) {
                        throw new LogicException($grant::class.' tidak menambah klausa; kembalikan true dari grantsEverything() bila memberi semua record.');
                    }
                });
            }
        });
    }

    /**
     * Dipanggil policy untuk setiap record dan setiap aksi di tabel, jadi aturan
     * dievaluasi di PHP dari relasi yang sudah dimuat bila bisa; selain itu
     * jatuh ke query yang sama dengan daftar.
     */
    public function allows(User $user, Model $record): bool
    {
        $restrictionUndecided = false;

        foreach ($this->activeRestrictions($user) as $restriction) {
            $decision = $restriction->allows($user, $record);

            if ($decision === false) {
                return false;
            }

            if ($decision === null) {
                $restrictionUndecided = true;
            }
        }

        $grantUndecided = false;

        foreach ($this->activeGrants($user) as $grant) {
            $decision = $grant->grantsEverything($user) ? true : $grant->allows($user, $record);

            if ($decision === true) {
                return $restrictionUndecided ? $this->existsInQuery($user, $record) : true;
            }

            if ($decision === null) {
                $grantUndecided = true;
            }
        }

        return $grantUndecided ? $this->existsInQuery($user, $record) : false;
    }

    /**
     * Status soft delete bukan urusan aturan lihat: record terhapus dinilai dengan
     * aturan yang sama, sehingga filter Sampah dan aksi Pulihkan bekerja untuk
     * siapa pun yang berhak melihatnya.
     */
    private function existsInQuery(User $user, Model $record): bool
    {
        $query = $record->newQuery()->whereKey($record->getKey());

        if (in_array(SoftDeletes::class, class_uses_recursive($record), true)) {
            $query->withTrashed();
        }

        return $this->apply($query, $user)->exists();
    }

    /**
     * @return list<Rule>
     */
    private function activeRestrictions(User $user): array
    {
        return array_values(array_filter($this->restrictions, fn (Rule $rule): bool => $rule->appliesTo($user)));
    }

    /**
     * @return list<Grant>
     */
    private function activeGrants(User $user): array
    {
        return array_values(array_filter($this->grants, fn (Grant $grant): bool => $grant->appliesTo($user)));
    }
}
