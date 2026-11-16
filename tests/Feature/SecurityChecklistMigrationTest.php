<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SecurityChecklistMigrationTest extends TestCase
{
    private const LEGACY = [
        'keamanan' => ['pintu_utama' => 'aman terkunci', 'ruangan_kantor' => 'aman'],
        'kelistrikan' => ['lampu_parkir' => 'Nyala sampai pagi 1 lampu'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('security_checksheets', function (Blueprint $table): void {
            $table->id();
            $table->json('checklist');
        });
    }

    public function test_old_text_answers_become_unscored_snapshots_and_roll_back(): void
    {
        DB::table('security_checksheets')->insert(['checklist' => json_encode(self::LEGACY)]);

        $migration = require database_path('migrations/2026_11_12_000004_create_security_checklist_items_table.php');
        $migration->up();

        $this->assertSame(11, DB::table('security_checklist_items')->count());

        $row = DB::table('security_checksheets')->first();
        $entries = json_decode($row->checklist, true);
        $gateId = DB::table('security_checklist_items')->where('question', 'Pintu utama / gerbang')->value('id');

        $this->assertSame(0, (int) $row->mismatch_count);
        $this->assertCount(3, $entries);
        $this->assertSame([
            'item_id' => $gateId,
            'section' => 'Keamanan',
            'question' => 'Pintu utama / gerbang',
            'type' => 'text',
            'options' => null,
            'answer_key' => null,
            'answer' => 'aman terkunci',
            'matches' => null,
        ], $entries[0]);

        $migration->down();

        $this->assertFalse(Schema::hasTable('security_checklist_items'));
        $this->assertFalse(Schema::hasColumn('security_checksheets', 'mismatch_count'));
        $this->assertSame(self::LEGACY, json_decode(DB::table('security_checksheets')->value('checklist'), true));
    }
}
