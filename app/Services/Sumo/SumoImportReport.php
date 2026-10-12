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

    /** @var list<string> */
    private array $warnings = [];

    public function add(string $key, int $amount = 1): void
    {
        $this->counts[$key] = ($this->counts[$key] ?? 0) + $amount;
    }

    public function warn(string $message): void
    {
        if (! in_array($message, $this->warnings, true)) {
            $this->warnings[] = $message;
        }
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
        return $this->warnings;
    }
}
