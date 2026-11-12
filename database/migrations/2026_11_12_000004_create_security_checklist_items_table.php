<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Pertanyaan Security Checksheet pindah dari konstanta di model ke tabel yang
 * diatur admin (ala Google Form). Butir lama dijadikan pertanyaan pilihan ganda
 * Baik/Bermasalah, dan jawaban teks laporan lama diubah ke format snapshot.
 */
return new class extends Migration
{
    /** Salinan `SecurityChecksheet::CHECKLIST` sebelum pertanyaan diatur dari database. */
    private const LEGACY_CHECKLIST = [
        'keamanan' => [
            'title' => 'Keamanan',
            'items' => [
                'pintu_utama' => 'Pintu utama / gerbang',
                'ruangan_kantor' => 'Ruangan kantor',
                'gudang_ruangan' => 'Gudang & ruangan',
            ],
        ],
        'kelistrikan' => [
            'title' => 'Kelistrikan',
            'items' => [
                'lampu_parkir' => 'Lampu area parkir',
                'lampu_gudang' => 'Lampu gudang',
                'ac_ruangan' => 'AC ruangan',
            ],
        ],
        'kondisi_aset' => [
            'title' => 'Kondisi Aset',
            'items' => [
                'mobil' => 'Mobil',
                'motor' => 'Motor',
                'apar' => 'APAR',
            ],
        ],
        'kebersihan' => [
            'title' => 'Kebersihan',
            'items' => [
                'halaman_parkir' => 'Halaman parkir',
                'area_toko' => 'Area toko / ruangan',
            ],
        ],
    ];

    public function up(): void
    {
        Schema::create('security_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->string('section');
            $table->string('question');
            // choice | yes_no | checkboxes | text (App\Enums\SecurityAnswerType).
            $table->string('answer_type', 20);
            $table->json('options')->nullable();
            // Jawaban yang dianggap sesuai; kosong berarti butir tidak dinilai.
            $table->json('answer_key')->nullable();
            $table->unsignedInteger('sort')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('security_checksheets', function (Blueprint $table) {
            $table->unsignedInteger('mismatch_count')->default(0)->after('checklist')->index();
        });

        $now = now();
        $sort = 0;
        $itemIds = [];

        foreach (self::LEGACY_CHECKLIST as $sectionKey => $section) {
            foreach ($section['items'] as $itemKey => $label) {
                $itemIds[$sectionKey][$itemKey] = DB::table('security_checklist_items')->insertGetId([
                    'section' => $section['title'],
                    'question' => $label,
                    'answer_type' => 'choice',
                    'options' => json_encode(['Baik', 'Bermasalah']),
                    'answer_key' => json_encode('Baik'),
                    'sort' => ++$sort,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        DB::table('security_checksheets')->orderBy('id')->select(['id', 'checklist'])->chunk(200, function ($rows) use ($itemIds): void {
            foreach ($rows as $row) {
                $answers = json_decode((string) $row->checklist, true);

                if (! is_array($answers) || array_is_list($answers)) {
                    continue;
                }

                DB::table('security_checksheets')->where('id', $row->id)->update([
                    'checklist' => json_encode($this->toSnapshot($answers, $itemIds)),
                    'mismatch_count' => 0,
                ]);
            }
        });
    }

    public function down(): void
    {
        DB::table('security_checksheets')->orderBy('id')->select(['id', 'checklist'])->chunk(200, function ($rows): void {
            foreach ($rows as $row) {
                $entries = json_decode((string) $row->checklist, true);

                if (! is_array($entries) || ! array_is_list($entries)) {
                    continue;
                }

                DB::table('security_checksheets')->where('id', $row->id)->update([
                    'checklist' => json_encode($this->toLegacy($entries)),
                ]);
            }
        });

        Schema::table('security_checksheets', function (Blueprint $table) {
            $table->dropIndex(['mismatch_count']);
            $table->dropColumn('mismatch_count');
        });

        Schema::dropIfExists('security_checklist_items');
    }

    /**
     * Jawaban teks lama tetap tampil apa adanya dan tidak dinilai.
     *
     * @param  array<string, mixed>  $answers
     * @param  array<string, array<string, int>>  $itemIds
     * @return list<array<string, mixed>>
     */
    private function toSnapshot(array $answers, array $itemIds): array
    {
        $entries = [];

        foreach ($answers as $sectionKey => $items) {
            foreach (is_array($items) ? $items : [] as $itemKey => $value) {
                $entries[] = [
                    'item_id' => $itemIds[$sectionKey][$itemKey] ?? null,
                    'section' => self::LEGACY_CHECKLIST[$sectionKey]['title'] ?? Str::headline((string) $sectionKey),
                    'question' => self::LEGACY_CHECKLIST[$sectionKey]['items'][$itemKey] ?? Str::headline((string) $itemKey),
                    'type' => 'text',
                    'options' => null,
                    'answer_key' => null,
                    'answer' => is_scalar($value) ? (string) $value : null,
                    'matches' => null,
                ];
            }
        }

        return $entries;
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     * @return array<string, array<string, string>>
     */
    private function toLegacy(array $entries): array
    {
        $answers = [];

        foreach ($entries as $entry) {
            $section = (string) ($entry['section'] ?? '');
            $question = (string) ($entry['question'] ?? '');
            $sectionKey = Str::snake($section);
            $itemKey = Str::snake($question);

            foreach (self::LEGACY_CHECKLIST as $legacySectionKey => $legacySection) {
                if ($legacySection['title'] === $section && ($legacyItemKey = array_search($question, $legacySection['items'], true)) !== false) {
                    [$sectionKey, $itemKey] = [$legacySectionKey, $legacyItemKey];
                }
            }

            $answer = $entry['answer'] ?? null;
            $answers[$sectionKey][$itemKey] = is_array($answer) ? implode(', ', $answer) : (string) $answer;
        }

        return $answers;
    }
};
