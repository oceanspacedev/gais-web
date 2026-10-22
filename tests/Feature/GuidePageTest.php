<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GuidePageTest extends TestCase
{
    use DatabaseTransactions;

    public function test_any_panel_user_can_open_the_guide(): void
    {
        $role = Role::firstOrCreate(['name' => 'requester', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user)
            ->get('/admin/panduan')
            ->assertOk()
            ->assertSee('Panduan Shelf')
            ->assertSee('Pengajuan Barang')
            ->assertSee('pg-flow', false)
            ->assertSee('@keyframes pg-dot-6-5', false)
            ->assertSee('id="bab-13"', false)
            ->assertSee('Konfirmasi Penerimaan');
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/admin/panduan')->assertRedirect();
    }
}
