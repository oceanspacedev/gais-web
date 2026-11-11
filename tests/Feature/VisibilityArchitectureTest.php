<?php

namespace Tests\Feature;

use App\Access\AuthorizesVisibleRecords;
use App\Access\HasVisibility;
use App\Access\ScopesVisibleRecords;
use App\Access\Visibility;
use App\Filament\Resources\AssetReconciliationResource;
use App\Filament\Resources\ExceptionResource;
use App\Filament\Resources\SumoImportRunResource;
use App\Models\AssetQrLabelHistory;
use App\Models\User;
use BezhanSalleh\FilamentShield\Resources\Roles\RoleResource;
use Filament\Facades\Filament;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Struktur akses yang sama untuk semua fitur: model memakai HasVisibility dan
 * SoftDeletes, resource memakai ScopesVisibleRecords tanpa getEloquentQuery()
 * sendiri dan punya filter Sampah plus aksi Pulihkan, policy memakai
 * AuthorizesVisibleRecords dengan restore/forceDelete. Pengecualian ditulis
 * eksplisit di sini beserta alasannya.
 */
class VisibilityArchitectureTest extends TestCase
{
    use DatabaseTransactions;

    /** @var array<class-string<resource>, string> resource tanpa aturan lihat */
    private const WITHOUT_VISIBILITY = [
        RoleResource::class => 'model Spatie (vendor)',
        ExceptionResource::class => 'model paket filament-exceptions (vendor)',
        SumoImportRunResource::class => 'alat impor, dijaga izin Manage:SumoImport di resource',
        AssetReconciliationResource::class => 'alat impor, dijaga izin import_asset di resource',
    ];

    /** @var array<class-string, string> model fitur tanpa soft delete */
    private const WITHOUT_SOFT_DELETE = [
        User::class => 'identitas login dan sinkron Talenta/sumo memakai kolom unik; butuh desain pulihkan/gabung',
        AssetQrLabelHistory::class => 'log cetak label, hanya-baca',
    ];

    public function test_every_feature_model_is_wired_the_same_way(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $resourcesByModel = [];

        foreach (Filament::getPanel('admin')->getResources() as $resource) {
            /** @var class-string<resource> $resource */
            if (array_key_exists($resource, self::WITHOUT_VISIBILITY)) {
                continue;
            }

            $model = $resource::getModel();
            $this->assertContains(HasVisibility::class, class_uses_recursive($model), "{$model} ({$resource}) harus memakai HasVisibility atau dikecualikan di sini.");
            $resourcesByModel[$model][] = $resource;
        }

        $this->assertGreaterThan(20, count($resourcesByModel));

        foreach ($resourcesByModel as $model => $resources) {
            $this->assertInstanceOf(Visibility::class, $model::visibility(), $model);

            $policy = Gate::getPolicyFor($model);
            $this->assertNotNull($policy, "{$model} tidak punya policy.");
            $this->assertContains(AuthorizesVisibleRecords::class, class_uses_recursive($policy), "Policy {$model} harus memakai AuthorizesVisibleRecords.");

            $softDeletes = in_array(SoftDeletes::class, class_uses_recursive($model), true);

            if (! array_key_exists($model, self::WITHOUT_SOFT_DELETE)) {
                $this->assertTrue($softDeletes, "{$model} harus memakai SoftDeletes atau dikecualikan di sini.");
            }

            $this->assertPolicyFollowsTheStandard($policy, $model, $softDeletes);

            foreach ($resources as $resource) {
                $this->assertContains(ScopesVisibleRecords::class, class_uses_recursive($resource), "{$resource} harus memakai ScopesVisibleRecords.");

                $method = new ReflectionMethod($resource, 'getEloquentQuery');
                $this->assertSame(
                    realpath(app_path('Access/ScopesVisibleRecords.php')),
                    realpath((string) $method->getFileName()),
                    "{$resource} menimpa getEloquentQuery(); ganti baseVisibleQuery() supaya aturan lihat tetap terpasang.",
                );
            }
        }
    }

    public function test_every_soft_deleting_resource_offers_trash_filter_and_restore(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        // super_admin di proyek ini tidak mem-bypass Gate (define_via_gate = false):
        // haknya dari permission, jadi viewer test memegang semua permission yang ada.
        $role = Role::findOrCreate('__visibility_architecture__', 'web');
        $role->syncPermissions(Permission::all());
        $viewer = User::factory()->create(['access_all_business_entities' => true]);
        $viewer->assignRole($role);
        $this->actingAs($viewer->fresh());

        $checked = 0;

        foreach (Filament::getPanel('admin')->getResources() as $resource) {
            /** @var class-string<resource> $resource */
            $model = $resource::getModel();

            if (array_key_exists($resource, self::WITHOUT_VISIBILITY) || ! in_array(SoftDeletes::class, class_uses_recursive($model), true)) {
                continue;
            }

            $page = $resource::getPages()['index']->getPage();

            try {
                Livewire::test($page)
                    ->assertOk()
                    ->assertTableFilterExists('trashed')
                    ->assertTableActionExists('restore')
                    ->assertTableBulkActionExists('restore')
                    ->assertTableBulkActionExists('forceDelete');
            } catch (\Throwable $exception) {
                $this->fail("{$resource}: {$exception->getMessage()}");
            }

            $checked++;
        }

        $this->assertGreaterThan(20, $checked);
    }

    /**
     * Record anak yang soft delete (pembaharuan kontrak, barang pengajuan) tidak
     * punya resource sendiri, tetapi policy-nya tetap harus bisa memulihkan dan
     * relation manager yang bisa mengubahnya harus punya filter Sampah.
     */
    public function test_soft_deleting_children_follow_the_same_standard(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $checkedPolicies = 0;

        foreach (glob(app_path('Models/*.php')) ?: [] as $file) {
            $model = 'App\\Models\\'.basename($file, '.php');

            if (! class_exists($model) || in_array(HasVisibility::class, class_uses_recursive($model), true) || ! in_array(SoftDeletes::class, class_uses_recursive($model), true)) {
                continue;
            }

            $policy = Gate::getPolicyFor($model);

            if ($policy === null) {
                continue;
            }

            foreach (['restore', 'restoreAny', 'forceDelete', 'forceDeleteAny'] as $ability) {
                $this->assertTrue(method_exists($policy, $ability), "Policy {$model} butuh {$ability}() untuk filter Sampah di relation manager.");
            }

            $checkedPolicies++;
        }

        $this->assertGreaterThan(0, $checkedPolicies);

        $checkedManagers = 0;

        foreach (Filament::getPanel('admin')->getResources() as $resource) {
            /** @var class-string<resource> $resource */
            if (array_key_exists($resource, self::WITHOUT_VISIBILITY)) {
                continue;
            }

            foreach ($resource::getRelations() as $manager) {
                if (! is_string($manager) || ! is_subclass_of($manager, RelationManager::class)) {
                    continue;
                }

                $related = app($resource::getModel())->{$manager::getRelationshipName()}()->getRelated();
                $source = (string) file_get_contents((new ReflectionClass($manager))->getFileName());
                $editable = (bool) preg_match('/\\b(CreateAction|EditAction|DeleteAction)::make\\(/', $source);

                if (! $editable || ! in_array(SoftDeletes::class, class_uses_recursive($related), true)) {
                    continue;
                }

                $this->assertStringContainsString('TrashedFilter', $source, "{$manager} mengubah ".$related::class.' yang soft delete; butuh filter Sampah.');
                $this->assertStringContainsString('RestoreAction', $source, "{$manager} butuh aksi Pulihkan.");
                $checkedManagers++;
            }
        }

        $this->assertGreaterThan(0, $checkedManagers);
    }

    /**
     * Satu titik masuk: daftar untuk viewer selalu lewat Resource::getEloquentQuery()
     * atau Resource::scopeToViewer(); visibleTo() tidak dipanggil langsung di luar
     * lapisan akses dan model.
     */
    public function test_viewer_queries_go_through_the_resource_entry_point(): void
    {
        $offenders = [];

        foreach ($this->phpFiles(app_path()) as $file) {
            $relative = str_replace(app_path().DIRECTORY_SEPARATOR, '', $file);

            if (str_starts_with($relative, 'Access'.DIRECTORY_SEPARATOR) || str_starts_with($relative, 'Models'.DIRECTORY_SEPARATOR)) {
                continue;
            }

            if (str_contains((string) file_get_contents($file), '->visibleTo(')) {
                $offenders[] = $relative;
            }
        }

        $this->assertSame([], $offenders, 'Pakai Resource::scopeToViewer() atau getEloquentQuery(), bukan visibleTo() langsung.');
    }

    /**
     * Policy model fitur: dua belas metode standar Shield (tanpa metode Sampah bila
     * modelnya tidak soft delete), dan setiap metode per record memeriksa cakupan
     * lihat lewat visible() (atau pembungkusnya).
     */
    private function assertPolicyFollowsTheStandard(object $policy, string $model, bool $softDeletes): void
    {
        $standard = ['viewAny', 'view', 'create', 'update', 'delete', 'deleteAny', 'replicate', 'reorder'];

        if ($softDeletes) {
            $standard = [...$standard, 'restore', 'restoreAny', 'forceDelete', 'forceDeleteAny'];
        }

        foreach ($standard as $ability) {
            $this->assertTrue(method_exists($policy, $ability), "Policy {$model} butuh {$ability}().");
        }

        $reflection = new ReflectionClass($policy);
        $lines = file($reflection->getFileName()) ?: [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $type = $method->getNumberOfParameters() >= 2 ? $method->getParameters()[1]->getType() : null;

            if (! $type instanceof ReflectionNamedType || ltrim($type->getName(), '?') !== $model) {
                continue;
            }

            $body = implode('', array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));
            $this->assertMatchesRegularExpression('/visible|editable/', $body, "{$reflection->getName()}::{$method->getName()}() memeriksa record tanpa visible()/editable().");
        }
    }

    /**
     * @return list<string>
     */
    private function phpFiles(string $directory): array
    {
        $files = [];

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory)) as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
