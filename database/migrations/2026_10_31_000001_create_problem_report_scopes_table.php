<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cakupan jenis gangguan per divisi, pengganti config/problem-report.php yang
 * menulis nama divisi di kode: pemegang "Lihat Data Semua User" yang divisinya
 * punya baris di sini hanya melihat jenis yang disebut (excluded = false) atau
 * semua jenis kecuali yang disebut (excluded = true). Aturan lama (sumo: HCM
 * hanya UMUM, MIS-IT selain UMUM) dipindahkan ke tabel bila divisi dan jenisnya
 * sudah ada; selanjutnya diatur di menu Divisi.
 */
return new class extends Migration
{
    /** @var array<string, list<string>> nama divisi => jenis; awalan "!" = dikecualikan */
    private const LEGACY_RULES = [
        'HCM' => ['UMUM'],
        'MIS-IT' => ['!UMUM'],
    ];

    public function up(): void
    {
        Schema::create('problem_report_scopes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('division_id')->constrained()->cascadeOnDelete();
            $table->foreignId('problem_report_category_id')->constrained()->cascadeOnDelete();
            $table->boolean('excluded')->default(false);
            $table->timestamps();

            $table->unique(['division_id', 'problem_report_category_id'], 'problem_report_scopes_unique');
        });

        foreach (self::LEGACY_RULES as $divisionName => $categories) {
            $divisionIds = DB::table('divisions')
                ->whereNull('deleted_at')
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($divisionName)])
                ->pluck('id');

            foreach ($categories as $category) {
                $excluded = str_starts_with($category, '!');

                // Termasuk jenis yang sudah dihapus, seperti aturan lama yang mencocokkan nama.
                $categoryIds = DB::table('problem_report_categories')
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower(ltrim($category, '!'))])
                    ->pluck('id');

                foreach ($divisionIds as $divisionId) {
                    foreach ($categoryIds as $categoryId) {
                        DB::table('problem_report_scopes')->insertOrIgnore([
                            'division_id' => $divisionId,
                            'problem_report_category_id' => $categoryId,
                            'excluded' => $excluded,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('problem_report_scopes');
    }
};
