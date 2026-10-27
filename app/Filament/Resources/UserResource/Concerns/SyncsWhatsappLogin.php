<?php

namespace App\Filament\Resources\UserResource\Concerns;

use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Auth;

trait SyncsWhatsappLogin
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function syncWhatsappLoginCredential(array $data): array
    {
        $actor = Auth::user();
        $canManageLogin = $actor?->can('manageAccess', User::class) ?? false;

        // Nomor yang dikunci (user dari Talenta) tidak ikut dikirim form, jadi
        // nomor login yang sudah ada dibiarkan.
        if (! $canManageLogin || ! array_key_exists('whatsapp_number', $data)) {
            unset($data['whatsapp_login_number']);

            return $data;
        }

        $data['whatsapp_login_number'] = PhoneNumber::canonical($data['whatsapp_number'] ?? null);

        return $data;
    }
}
