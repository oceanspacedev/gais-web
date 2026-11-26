<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Access\HasVisibility;
use App\Access\Rules\SelfGrant;
use App\Access\Users\BusinessEntityMemberGrant;
use App\Access\Users\HidesSuperAdminsRule;
use App\Access\Visibility;
use App\Models\Concerns\HasBusinessEntityAccess;
use App\Support\IndexedPermissionRegistrar;
use App\Support\PhoneNumber;
use BezhanSalleh\FilamentShield\Traits\HasPanelShield;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Traits\HasRoles;

use function Illuminate\Support\enum_value;

class User extends Authenticatable implements FilamentUser, HasName
{
    use HasApiTokens, HasBusinessEntityAccess, HasFactory, HasPanelShield, HasVisibility, Notifiable;
    use HasRoles {
        checkPermissionTo as private checkPermissionToUncached;
    }

    /**
     * Role operasional yang boleh mengeluarkan aset dari stok (BA Serah Terima)
     * dan menerima pengembalian aset ke stok (BA Pengembalian).
     */
    public const GENERAL_AFFAIR_ROLE = 'general_affair';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'employee_id',
        'whatsapp_number',
        'whatsapp_login_number',
        'password',
        'email_verified_at',
        'business_entity_id',
        'access_all_business_entities',
        'job_title_id',
        'division_id',
        'approver_user_id',
        'legacy_sumo_user_id',
        'login_disabled',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'whatsapp_login_number',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'access_all_business_entities' => 'boolean',
        'login_disabled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Kode dan magic link login email hanya berlaku untuk alamat saat kode diminta.
        static::updated(function (User $user): void {
            if (! $user->wasChanged('email') || ! Schema::hasTable('email_otps')) {
                return;
            }

            EmailOtp::query()
                ->where('user_id', $user->getKey())
                ->whereNull('verified_at')
                ->update(['expires_at' => now()]);
        });
    }

    public function setEmailAttribute($value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['email'] = null;

            return;
        }

        $email = trim((string) $value);

        if (preg_match('/[\r\n]/', $email) === 1) {
            throw new InvalidArgumentException('Email must not contain line breaks.');
        }

        $this->attributes['email'] = $email;
    }

    public function setWhatsappLoginNumberAttribute($value): void
    {
        $phone = PhoneNumber::canonical($value);
        if (filled($value) && $phone === null) {
            throw new InvalidArgumentException('Nomor WhatsApp untuk login tidak valid.');
        }

        $this->attributes['whatsapp_login_number'] = $phone;
    }

    /**
     * Dipanggil Gate untuk setiap can(); hasilnya diingat per request
     * (lihat IndexedPermissionRegistrar::rememberCheck()).
     */
    public function checkPermissionTo($permission, ?string $guardName = null): bool
    {
        $registrar = app(PermissionRegistrar::class);
        $name = enum_value($permission);

        if (! $registrar instanceof IndexedPermissionRegistrar || ! is_string($name)) {
            return $this->checkPermissionToUncached($permission, $guardName);
        }

        return $registrar->rememberCheck($this, $name, $guardName, fn (): bool => $this->checkPermissionToUncached($permission, $guardName));
    }

    /**
     * Semua karyawan boleh masuk, termasuk yang belum diberi role: tanpa role
     * mereka hanya mendapat akses dasar (Lapor Gangguan miliknya dan Panduan);
     * menu dan widget lain tetap dijaga izin Shield. Akun yang loginnya
     * dinonaktifkan (akun toko yang sudah digantikan akun pribadi) ditolak di
     * semua cara login dan di sesi yang masih terbuka.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return ! $this->isLoginDisabled();
    }

    public function isLoginDisabled(): bool
    {
        return $this->login_disabled_at !== null;
    }

    /**
     * Saklar di form User; waktu penonaktifan pertama dipertahankan.
     */
    public function setLoginDisabledAttribute(mixed $disabled): void
    {
        $this->attributes['login_disabled_at'] = $disabled
            ? ($this->attributes['login_disabled_at'] ?? $this->freshTimestampString())
            : null;
    }

    /**
     * Filament Impersonate: controlled by the Shield `Impersonate:User` permission.
     */
    public function canImpersonate(): bool
    {
        return $this->can('impersonate', self::class);
    }

    /**
     * Filament Impersonate: only users with a role, only within the
     * impersonator's business entities, and never a super admin.
     */
    public function canBeImpersonated(): bool
    {
        if ($this->roles->isEmpty() || $this->isSuperAdmin()) {
            return false;
        }

        $impersonator = Filament::auth()->user();

        if (! $impersonator instanceof self) {
            return false;
        }

        return $impersonator->canAccessBusinessEntity($this->business_entity_id);
    }

    public function isGeneralAffair(): bool
    {
        return $this->hasRole(self::GENERAL_AFFAIR_ROLE);
    }

    /**
     * Akun bersama yang mewakili departemen (mis. akun GA "adminga"), bukan
     * orang; lihat config/asset-transfer.php.
     */
    public function isSharedAccount(): bool
    {
        $username = strtolower(trim((string) $this->username));
        $sharedUsernames = array_map('strtolower', (array) config('asset-transfer.shared_general_affair_usernames', []));

        return $username !== '' && in_array($username, $sharedUsernames, true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(config('filament-shield.super_admin.name', 'super_admin'));
    }

    /**
     * Karyawan biasa (pemohon/pelapor) yang belum punya divisi: wajib memilih
     * divisinya dulu sebelum membuat pengajuan barang atau laporan gangguan,
     * supaya record masuk ke area dan kuota divisi yang benar dan terlihat oleh
     * rekan sedivisi. Pemroses (GA, executor, manager, accounting, audit, super
     * admin) tidak diminta, walau tanpa divisi.
     */
    public function needsToChooseDivision(): bool
    {
        if ($this->division_id !== null || $this->isSuperAdmin()) {
            return false;
        }

        foreach (['ViewAll:ItemRequest', 'Process:ItemRequest', 'ApproveManager:ItemRequest', 'ApproveAccounting:ItemRequest', 'Audit:ItemRequest', 'ChangeApplicant:ItemRequest'] as $permission) {
            if ($this->can($permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Karyawan mengisi divisinya sendiri, sekali saja (setelah itu hanya admin
     * lewat menu Pengguna). Pengajuan dan laporannya yang belum tercatat
     * divisinya ikut diisi supaya terlihat oleh rekan sedivisi; yang sudah
     * punya divisi tidak diubah. False bila divisinya ternyata sudah terisi.
     */
    public function chooseOwnDivision(Division $division): bool
    {
        return DB::transaction(function () use ($division): bool {
            // Dikunci: hanya mengisi bila masih kosong (cegah ganti lewat request ulang).
            $updated = DB::table('users')
                ->where('id', $this->getKey())
                ->whereNull('division_id')
                ->update(['division_id' => $division->id, 'updated_at' => now()]);

            if ($updated === 0) {
                return false;
            }

            foreach (['item_requests', 'problem_reports'] as $table) {
                DB::table($table)
                    ->where('user_id', $this->getKey())
                    ->whereNull('division_id')
                    ->update(['division_id' => $division->id]);
            }

            $this->setAttribute('division_id', $division->id);
            $this->syncOriginalAttribute('division_id');

            return true;
        });
    }

    /**
     * Boleh membuat BA stok (Serah Terima, Pengembalian) atas nama staf GA mana
     * pun: pemegang izin Shield "Kelola BA Stok".
     */
    public function canManageStockTransfers(): bool
    {
        return $this->can('manageStock', AssetTransfer::class);
    }

    /**
     * Boleh membuat BA stok sama sekali. Staf GA tanpa izin "Kelola BA Stok"
     * hanya bisa menjadi pihak GA di BA-nya sendiri.
     */
    public function canCreateStockTransfers(): bool
    {
        return $this->isGeneralAffair() || $this->canManageStockTransfers();
    }

    /**
     * Users holding the general_affair role.
     */
    public function scopeGeneralAffair(Builder $query): Builder
    {
        return $query->whereHas('roles', fn (Builder $roles) => $roles->where('name', self::GENERAL_AFFAIR_ROLE));
    }

    public function businessEntity(): BelongsTo
    {
        return $this->belongsTo(BusinessEntity::class)->withTrashed();
    }

    public function jobTitle(): BelongsTo
    {
        return $this->belongsTo(JobTitle::class)->withTrashed();
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class)->withTrashed();
    }

    /**
     * Atasan yang menyetujui pengajuan user ini (sumo: approval_id).
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }

    public function subordinates(): HasMany
    {
        return $this->hasMany(User::class, 'approver_user_id');
    }

    public function itemRequests(): HasMany
    {
        return $this->hasMany(ItemRequest::class);
    }

    public function problemReports(): HasMany
    {
        return $this->hasMany(ProblemReport::class);
    }

    /**
     * Tahap approval pengajuan yang disetujui user ini.
     */
    public function itemRequestApprovals(): HasMany
    {
        return $this->hasMany(ItemRequestApproval::class, 'approved_by_user_id');
    }

    /**
     * Aset yang saat ini dipegang user ini (penerima BA terakhir).
     */
    public function heldAssets(): HasMany
    {
        return $this->hasMany(Asset::class, 'recipient_id');
    }

    public function getFilamentName(): string
    {
        return (string) ($this->name ?: $this->fullname ?: $this->username);
    }

    public function nameWithJobTitle(): string
    {
        $this->loadMissing('jobTitle');

        $title = $this->jobTitle?->title;

        return filled($title) ? "{$this->name} ({$title})" : $this->name;
    }

    public function assetTransfers()
    {
        return $this->hasMany(AssetTransfer::class);
    }

    public function assetTransfersFrom()
    {
        return $this->hasMany(AssetTransfer::class, 'from_user_id');
    }

    public function assetTransfersTo()
    {
        return $this->hasMany(AssetTransfer::class, 'to_user_id');
    }

    public function assetTransferDetails()
    {
        return $this->hasManyThrough(
            AssetTransferDetail::class, // Model tujuan akhir (AssetTransferDetail)
            AssetTransfer::class, // Model perantara (AssetTransfer)
            'from_user_id', // Foreign key di AssetTransfer (relasi ke User)
            'asset_transfer_id', // Foreign key di AssetTransferDetail (relasi ke AssetTransfer)
            'id', // Local key di User
            'id'  // Local key di AssetTransfer
        );
    }

    public function isDuplicate(): bool
    {
        return User::where('name', $this->name)
            ->where('id', '!=', $this->id)  // Hindari perbandingan dengan dirinya sendiri
            ->exists();
    }

    /**
     * Akun super admin hanya terlihat oleh super admin. Selebihnya user melihat
     * akunnya sendiri dan user di badan usaha yang bisa ia akses (semua user
     * bila berakses semua badan usaha). Sama dengan UserPolicy.
     */
    public static function visibility(): Visibility
    {
        return Visibility::make()
            ->require(new HidesSuperAdminsRule)
            ->grant(new BusinessEntityMemberGrant, new SelfGrant);
    }
}
