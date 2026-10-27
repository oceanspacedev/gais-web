<?php

namespace Tests\Feature;

use App\Models\AssetTransfer;
use App\Models\BusinessEntity;
use App\Models\Task;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\Support\AssetIdentitySchema;
use Tests\TestCase;

class PdfAuthorizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'activitylog.enabled' => false,
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'permission.cache.store' => 'array',
        ]);

        DB::purge('sqlite');

        $this->createSchema();
    }

    public function test_asset_transfer_pdf_requires_view_permission(): void
    {
        $actor = User::create(['name' => 'Limited User']);
        $entity = BusinessEntity::create(['name' => 'CV Complete']);
        $fromUser = User::create(['name' => 'Pemegang Lama']);
        $toUser = User::create(['name' => 'Pemegang Baru']);
        $transfer = AssetTransfer::create([
            'business_entity_id' => $entity->id,
            'letter_number' => 'BA-SEC-001',
            'from_user_id' => $fromUser->id,
            'to_user_id' => $toUser->id,
            'transfer_date' => now(),
        ]);

        $this->actingAs($actor)
            ->get(route('asset-transfer.download', $transfer))
            ->assertForbidden();
    }

    public function test_task_completion_pdf_requires_view_permission(): void
    {
        $actor = User::create(['name' => 'Limited User']);
        $entity = BusinessEntity::create(['name' => 'CV Complete']);
        $vendor = Vendor::create(['name' => 'Vendor A', 'last_price' => 100000]);
        $task = Task::create([
            'business_entity_id' => $entity->id,
            'vendor_id' => $vendor->id,
            'work_timestamp' => now(),
            'name' => 'Perbaikan AC',
            'description' => 'Service AC kantor',
            'cost' => 100000,
            'location' => 'Kantor',
            'status' => 'completed',
            'attachment' => json_encode([]),
        ]);

        $this->actingAs($actor)
            ->get(route('task-completion.download', $task))
            ->assertForbidden();
    }

    public function test_task_completion_preview_requires_view_permission(): void
    {
        $actor = User::create(['name' => 'Limited User']);
        $entity = BusinessEntity::create(['name' => 'CV Complete']);
        $vendor = Vendor::create(['name' => 'Vendor A', 'last_price' => 100000]);
        $task = Task::create([
            'business_entity_id' => $entity->id,
            'vendor_id' => $vendor->id,
            'work_timestamp' => now(),
            'name' => 'Perbaikan AC',
            'description' => 'Service AC kantor',
            'cost' => 100000,
            'location' => 'Kantor',
            'status' => 'completed',
            'attachment' => json_encode([]),
        ]);

        $this->actingAs($actor)
            ->get(route('task-completion.preview', $task))
            ->assertForbidden();
    }

    private function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });

        Schema::create('model_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
        });

        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
        });

        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
        });

        foreach (['view_asset::transfer', 'view_task', 'view_asset::request'] as $permission) {
            Permission::create(['name' => $permission, 'guard_name' => 'web']);
        }

        Schema::create('business_entities', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('format')->nullable();
            $table->string('letterhead')->nullable();
            $table->timestamps();
        });

        Schema::create('vendors', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('contact_person')->nullable();
            $table->string('location')->nullable();
            $table->decimal('last_price', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('divisions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('division_approvers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('division_id');
            $table->unsignedBigInteger('user_id');
            $table->integer('level');
            $table->timestamps();
        });

        Schema::create('asset_transfers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('business_entity_id')->nullable();
            $table->string('document_type', 32)->nullable();
            $table->string('letter_number')->nullable();
            $table->unsignedBigInteger('from_user_id');
            $table->unsignedBigInteger('to_user_id');
            $table->timestamp('transfer_date')->nullable();
            $table->string('document')->nullable();
            $table->timestamps();
        });

        Schema::create('asset_transfer_details', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('asset_transfer_id');
            $table->unsignedBigInteger('asset_id');
            $table->string('equipment')->nullable();
            $table->timestamps();
        });

        Schema::create('tasks', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->nullable();
            $table->unsignedBigInteger('business_entity_id');
            $table->unsignedBigInteger('vendor_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('work_timestamp')->nullable();
            $table->string('name');
            $table->text('description');
            $table->decimal('cost', 12, 2)->default(0);
            $table->string('location');
            $table->string('status')->default('open');
            $table->text('attachment')->nullable();
            $table->string('document_upload')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('assets', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('business_entity_id')->nullable();
            $table->string('name');
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('brand_id')->nullable();
            $table->unsignedBigInteger('asset_location_id')->nullable();
            $table->integer('qty')->default(1);
            $table->timestamps();
        });

        AssetIdentitySchema::ensure();
    }
}
