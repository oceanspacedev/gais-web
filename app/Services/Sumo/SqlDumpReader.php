<?php

namespace App\Services\Sumo;

use Generator;
use RuntimeException;

/**
 * Membaca file dump MySQL (.sql atau .sql.gz) per pernyataan tanpa memuat
 * seluruh file ke memori. Pemisah `;` di dalam string, identifier, dan komentar
 * diabaikan; `DELIMITER` ala klien mysql didukung. Komentar biasa dibuang,
 * komentar eksekusi `/*! ... *\/` dipertahankan karena dijalankan MySQL.
 */
final class SqlDumpReader
{
    public function __construct(private readonly string $path) {}

    /**
     * @return Generator<int, string>
     */
    public function statements(): Generator
    {
        // gzopen juga membaca file yang tidak terkompresi apa adanya.
        $handle = @gzopen($this->path, 'rb');

        if ($handle === false) {
            throw new RuntimeException("File dump tidak bisa dibuka: {$this->path}");
        }

        $delimiter = ';';
        $statement = '';
        $quote = null;
        $inComment = false;

        try {
            while (($line = gzgets($handle)) !== false) {
                if ($quote === null && ! $inComment && trim($statement) === '' && preg_match('/^\s*DELIMITER\s+(\S+)\s*$/i', $line, $match)) {
                    $delimiter = $match[1];
                    $statement = '';

                    continue;
                }

                $length = strlen($line);
                $i = 0;

                while ($i < $length) {
                    if ($inComment) {
                        $end = strpos($line, '*/', $i);

                        if ($end === false) {
                            break;
                        }

                        $i = $end + 2;
                        $inComment = false;

                        continue;
                    }

                    if ($quote !== null) {
                        // Backtick tidak mengenal escape backslash; kutip ganda ('') tertangani
                        // sebagai tutup lalu buka lagi.
                        $j = $i + strcspn($line, $quote === '`' ? '`' : $quote.'\\', $i);
                        $statement .= substr($line, $i, $j - $i);

                        if ($j >= $length) {
                            break;
                        }

                        if ($line[$j] === '\\') {
                            $statement .= substr($line, $j, 2);
                            $i = $j + 2;

                            continue;
                        }

                        $statement .= $quote;
                        $quote = null;
                        $i = $j + 1;

                        continue;
                    }

                    $j = $i + strcspn($line, "'\"`/-#".$delimiter[0], $i);
                    $statement .= substr($line, $i, $j - $i);

                    if ($j >= $length) {
                        break;
                    }

                    $char = $line[$j];

                    if ($char === $delimiter[0] && substr_compare($line, $delimiter, $j, strlen($delimiter)) === 0) {
                        if (($sql = trim($statement)) !== '') {
                            yield $sql;
                        }

                        $statement = '';
                        $i = $j + strlen($delimiter);

                        continue;
                    }

                    if ($char === "'" || $char === '"' || $char === '`') {
                        $quote = $char;
                        $statement .= $char;
                        $i = $j + 1;

                        continue;
                    }

                    $next = $line[$j + 1] ?? '';

                    if ($char === '#' || ($char === '-' && $next === '-' && ($j + 2 >= $length || ctype_space($line[$j + 2])))) {
                        $statement .= "\n";

                        break;
                    }

                    if ($char === '/' && $next === '*' && ($line[$j + 2] ?? '') !== '!') {
                        $inComment = true;
                        $i = $j + 2;

                        continue;
                    }

                    $statement .= $char;
                    $i = $j + 1;
                }
            }

            if ($quote !== null) {
                throw new RuntimeException('File dump terpotong: ada string yang tidak ditutup di akhir file.');
            }

            if (($sql = trim($statement)) !== '') {
                yield $sql;
            }
        } finally {
            gzclose($handle);
        }
    }
}
