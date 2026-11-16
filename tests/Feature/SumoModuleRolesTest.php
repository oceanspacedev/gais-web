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
            'general_affair' => ['ViewAll:ItemRequest', 'DeleteAny:ItemRequest', 'Import:Insurance', 'View:WaNotificationSettings', 'View:SumoOverviewStats'],
            'accounting' => ['ViewAny:ItemRequest', 'ApproveAccounting:ItemRequest', 'Export:ItemRequest'],
            'executor' => ['Process:ItemRequest', 'ApproveManager:ItemRequest', 'Process:ProblemReport', 'ViewAll:ProblemReport'],
            'requester' => ['Create:ItemRequest', 'Create:ProblemReport', 'View:Product'],
        ];

        foreach ($expected as $role => $permissions) {
            $model = Role::query()->where('name', $role)->where('guard_name', 'web')->first();

            $this->assertNotNull($model, "Role {$role} missing.");

            foreach ($permissions as $permission) {
                $this->assertTrue($model->hasPermissionTo($permission), "Role {$role} lacks {$permission}.");
            }
        }

        $requester = Role::findByName('requester', 'web');

        $this->assertFalse($requester->hasPermissionTo('Process:ItemRequest'));
        $this->assertFalse($requester->hasPermissionTo('ViewAll:ItemRequest'));
    }
}
