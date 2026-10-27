<?php

namespace Tests\Feature;

use App\Filament\Exports\AssetExporter;
use App\Models\User;
use App\Queue\RestoresQueuedModels;
use Filament\Actions\Exports\Jobs\CreateXlsxFile;
use Filament\Actions\Exports\Jobs\ExportCompletion;
use Filament\Actions\Exports\Jobs\ExportCsv;
use Filament\Actions\Exports\Jobs\PrepareCsvExport;
use Filament\Actions\Exports\Models\Export;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Jobs\ImportCsv;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Bus\ChainedBatch;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use Tests\TestCase;

class ExportQueueModelRestorationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Lewat config, bukan env: Dotenv menimpa DB_CONNECTION dari .env pada
        // setiap boot setelah yang pertama, jadi env hanya berlaku bila tes ini jalan paling awal.
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');

        $this->createQueueTables();
    }

    public function test_queue_connections_wait_until_database_transactions_commit(): void
    {
        $this->assertTrue(config('queue.connections.redis.after_commit'));
        $this->assertTrue(config('queue.connections.database.after_commit'));
        $this->assertTrue(config('queue.connections.sqs.after_commit'));
        $this->assertTrue(config('queue.connections.beanstalkd.after_commit'));
    }

    public function test_filament_queue_jobs_that_restore_models_are_rebound(): void
    {
        $export = $this->makeExport();
        $import = $this->makeImport();

        $jobs = [
            $this->prepareJob($export),
            app(ExportCsv::class, [
                'export' => $export,
                'query' => 'not-unserialized-until-handle',
                'records' => [1],
                'page' => 1,
                'columnMap' => ['name' => 'Nama'],
                'options' => [],
            ]),
            app(CreateXlsxFile::class, [
                'export' => $export,
                'columnMap' => ['name' => 'Nama'],
                'options' => [],
            ]),
            app(ExportCompletion::class, [
                'export' => $export,
                'columnMap' => ['name' => 'Nama'],
                'formats' => [],
                'options' => [],
                'authGuard' => 'web',
            ]),
            app(ImportCsv::class, [
                'import' => $import,
                'rows' => [['name' => 'Laptop']],
                'columnMap' => ['name' => 'Nama'],
                'options' => [],
            ]),
        ];

        foreach ($jobs as $job) {
            $this->assertContains(RestoresQueuedModels::class, class_uses_recursive($job));
            $this->assertTrue($job->afterCommit);
        }
    }

    public function test_chained_export_batch_retries_until_the_export_row_is_visible(): void
    {
        config([
            'queue.missing_model_retry.attempts' => 4,
            'queue.missing_model_retry.delay_ms' => 0,
        ]);

        $export = $this->makeExport();
        $chain = $this->chainedBatch($this->prepareJob($export));
        $payload = serialize($chain);
        $row = DB::table('exports')->where('id', $export->getKey())->first();
        $export->delete();

        $selects = 0;
        DB::listen(function ($query) use (&$selects, $row): void {
            if (! $this->isExportSelect($query->sql)) {
                return;
            }

            $selects++;

            if ($selects === 2) {
                DB::table('exports')->insert((array) $row);
            }
        });

        /** @var ChainedBatch $restored */
        $restored = unserialize($payload);
        $restoredExport = (new ReflectionClass($restored->jobs->first()))
            ->getProperty('export')
            ->getValue($restored->jobs->first());

        $this->assertGreaterThan(1, $selects);
        $this->assertInstanceOf(ChainedBatch::class, $restored);
        $this->assertSame($export->getKey(), $restoredExport->getKey());
    }

    public function test_missing_export_still_fails_after_the_retry_window(): void
    {
        config([
            'queue.missing_model_retry.attempts' => 2,
            'queue.missing_model_retry.delay_ms' => 0,
        ]);

        $export = $this->makeExport();
        $payload = serialize($this->chainedBatch($this->prepareJob($export)));
        $export->delete();

        $selects = 0;
        DB::listen(function ($query) use (&$selects): void {
            if ($this->isExportSelect($query->sql)) {
                $selects++;
            }
        });

        try {
            unserialize($payload);
            $this->fail('A missing export should still fail the chained batch after retries.');
        } catch (ModelNotFoundException $exception) {
            $this->assertSame(Export::class, $exception->getModel());
            $this->assertSame(2, $selects);
        }
    }

    private function prepareJob(Export $export): PrepareCsvExport
    {
        return app(PrepareCsvExport::class, [
            'export' => $export,
            'query' => 'not-unserialized-until-handle',
            'columnMap' => ['name' => 'Nama'],
            'options' => [],
            'chunkSize' => 100,
            'records' => null,
        ]);
    }

    private function makeExport(): Export
    {
        return Export::query()->create([
            'file_disk' => 'local',
            'file_name' => 'export_asset.xlsx',
            'exporter' => AssetExporter::class,
            'processed_rows' => 0,
            'total_rows' => 1,
            'successful_rows' => 0,
            'user_id' => 1,
        ]);
    }

    private function chainedBatch(PrepareCsvExport $job): ChainedBatch
    {
        $chain = (new ReflectionClass(ChainedBatch::class))->newInstanceWithoutConstructor();
        $chain->jobs = collect([$job]);
        $chain->name = 'Export';
        $chain->options = [];

        return $chain;
    }

    private function isExportSelect(string $sql): bool
    {
        $sql = strtolower($sql);

        return str_starts_with(ltrim($sql), 'select') && str_contains($sql, 'exports');
    }

    private function createQueueTables(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
        });

        DB::table('users')->insert([
            'id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::create('exports', function (Blueprint $table): void {
            $table->id();
            $table->timestamp('completed_at')->nullable();
            $table->string('file_disk');
            $table->string('file_name')->nullable();
            $table->string('exporter');
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('total_rows');
            $table->unsignedInteger('successful_rows')->default(0);
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('imports', function (Blueprint $table): void {
            $table->id();
            $table->timestamp('completed_at')->nullable();
            $table->string('file_name');
            $table->string('file_path');
            $table->string('importer');
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('total_rows');
            $table->unsignedInteger('successful_rows')->default(0);
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    private function makeImport(): Import
    {
        return Import::query()->create([
            'file_name' => 'users.csv',
            'file_path' => 'imports/users.csv',
            'importer' => QueueRestorationImporter::class,
            'processed_rows' => 0,
            'total_rows' => 1,
            'successful_rows' => 0,
            'user_id' => 1,
        ]);
    }
}

class QueueRestorationImporter extends Importer
{
    protected static ?string $model = User::class;

    public static function getColumns(): array
    {
        return [];
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        return 'ok';
    }
}
