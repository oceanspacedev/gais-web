<?php

namespace App\Services\Sumo;

/**
 * Hasil satu kali `sumo:import`: jumlah baris per langkah dan peringatan
 * (data yang dilewati atau tidak terpetakan).
 */
final class SumoImportReport
{
    /** @var array<string, int> */
    private array $counts = [];

    /** @var array<string, true> pesan sebagai key, supaya cek duplikat tidak O(n) */
    private array $warnings = [];

    /** @var list<string> */
    private array $notes = [];

    /**
     * Informasi untuk diperiksa (mis. pemetaan badan usaha), bukan masalah.
     */
    public function note(string $message): void
    {
        $this->notes[] = $message;
    }

    /**
     * @return list<string>
     */
    public function notes(): array
    {
        return $this->notes;
    }

    public function add(string $key, int $amount = 1): void
    {
        $this->counts[$key] = ($this->counts[$key] ?? 0) + $amount;
    }

    public function warn(string $message): void
    {
        $this->warnings[$message] = true;
    }

    /**
     * @return array<string, int>
     */
    public function counts(): array
    {
        ksort($this->counts);

        return $this->counts;
    }

    public function count(string $key): int
    {
        return $this->counts[$key] ?? 0;
    }

    /**
     * @return list<string>
     */
    public function warnings(): array
    {
        return array_keys($this->warnings);
    }
}
