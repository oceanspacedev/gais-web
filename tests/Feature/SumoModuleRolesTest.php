<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SumoModuleRolesTest extends TestCase
{
    use DatabaseTransactions;

    public function test_roles_from_sumo_exist_with_their_core_permissions(): void
    {
        $expected = [
            'pengajuan_admin' => ['view_all_item::request', 'delete_any_item::request', 'import_insurance', 'View:WaNotificationSettings', 'widget_SumoOverviewStats'],
            'accounting' => ['view_any_item::request', 'approve_accounting_item::request', 'export_item::request'],
            'executor' => ['process_item::request', 'approve_manager_item::request', 'process_problem::report', 'view_all_problem::report'],
            'requester' => ['create_item::request', 'create_problem::report', 'view_product'],
        ];

        foreach ($expected as $role => $permissions) {
            $model = Role::query()->where('name', $role)->where('guard_name', 'web')->first();

            $this->assertNotNull($model, "Role {$role} missing.");

            foreach ($permissions as $permission) {
                $this->assertTrue($model->hasPermissionTo($permission), "Role {$role} lacks {$permission}.");
            }
        }

        $requester = Role::findByName('requester', 'web');

        $this->assertFalse($requester->hasPermissionTo('process_item::request'));
        $this->assertFalse($requester->hasPermissionTo('view_all_item::request'));
    }
}
