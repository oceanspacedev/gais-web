<?php

namespace Tests\Feature;

use App\Filament\Resources\TaskResource\Pages\CreateTask;
use App\Filament\Resources\TaskResource\Pages\EditTask;
use App\Filament\Resources\TaskResource\Pages\ListTasks;
use App\Models\BusinessEntity;
use App\Models\Task;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class TaskPicTest extends TestCase
{
    use DatabaseTransactions;

    public function test_pic_is_filled_automatically_with_the_user_who_creates_the_task(): void
    {
        [$admin, $entity, $vendor] = $this->scenario();

        Livewire::test(CreateTask::class)
            ->assertFormFieldDoesNotExist('user_id')
            ->fillForm([
                'name' => 'Perbaikan AC Ruang Rapat',
                'vendor_id' => $vendor->id,
                'cost' => 250000,
                'work_timestamp' => now()->toDateTimeString(),
                'location' => 'Lantai 2',
                'business_entity_id' => $entity->id,
                'description' => 'AC tidak dingin',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $task = Task::where('name', 'Perbaikan AC Ruang Rapat')->firstOrFail();

        $this->assertSame($admin->id, $task->user_id);
    }

    public function test_pic_cannot_be_changed_from_the_edit_form(): void
    {
        [, $entity, $vendor] = $this->scenario();
        $creator = User::factory()->create();

        $task = Task::create($this->attributes($entity, $vendor) + ['user_id' => $creator->id]);

        Livewire::test(EditTask::class, ['record' => $task->getRouteKey()])
            ->assertFormFieldDoesNotExist('user_id');

        $task->update(['location' => 'Gudang Belakang']);

        $this->assertSame($creator->id, $task->fresh()->user_id);
    }

    public function test_task_table_shows_pic_and_created_date_by_default(): void
    {
        [$admin, $entity, $vendor] = $this->scenario();

        $task = Task::create($this->attributes($entity, $vendor));

        Livewire::test(ListTasks::class)
            ->assertCanSeeTableRecords([$task])
            ->assertTableColumnVisible('user.name')
            ->assertTableColumnVisible('created_at')
            ->assertTableColumnStateSet('user.name', $admin->name, $task)
            ->assertTableColumnFormattedStateSet('created_at', $task->created_at->format('d/m/Y H:i'), $task);
    }

    /**
     * @return array{User, BusinessEntity, Vendor}
     */
    private function scenario(): array
    {
        $entity = BusinessEntity::firstOrCreate(['name' => 'PT Test Entity']);
        $admin = User::factory()->create(['business_entity_id' => $entity->id]);
        $admin->assignRole('admin');
        $this->actingAs($admin);

        return [$admin, $entity, Vendor::create(['name' => 'Vendor Uji PIC', 'last_price' => 0])];
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(BusinessEntity $entity, Vendor $vendor): array
    {
        return [
            'business_entity_id' => $entity->id,
            'vendor_id' => $vendor->id,
            'work_timestamp' => now(),
            'name' => 'Pekerjaan Uji PIC',
            'description' => '-',
            'cost' => 1,
            'location' => '-',
        ];
    }
}
