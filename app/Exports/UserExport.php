<?php

namespace App\Exports;

use App\Exports\Concerns\ExportsTemplateRows;
use App\Filament\Resources\UserResource;
use App\Models\User;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Ekspor user yang boleh dilihat pembaca dengan kolom ekspor sumo
 * (/user/export): username, fullname, badan_usaha, divisi, role, approval.
 * Tidak ada impor Excel pasangannya: data user masuk lewat JSON Talenta.
 */
class UserExport implements FromCollection, WithHeadings, WithMapping
{
    use ExportsTemplateRows;

    public function fileName(): string
    {
        return 'user.xlsx';
    }

    /**
     * @return Collection<int, User>
     */
    public function collection(): Collection
    {
        return UserResource::getEloquentQuery()
            ->with(['businessEntity', 'division', 'approver', 'roles'])
            ->orderBy('users.name')
            ->orderBy('users.id')
            ->get();
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return ['username', 'fullname', 'badan_usaha', 'divisi', 'role', 'approval'];
    }

    /**
     * @param  User  $user
     * @return list<mixed>
     */
    public function map($user): array
    {
        return $this->orderedByHeadings([
            'username' => $user->username,
            'fullname' => $user->name,
            'badan_usaha' => $user->businessEntity?->name,
            'divisi' => $user->division?->name,
            'role' => $user->roles->pluck('name')->implode(', '),
            'approval' => $user->approver?->name,
        ]);
    }
}
