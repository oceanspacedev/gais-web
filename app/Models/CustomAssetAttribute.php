<?php

namespace App\Models;

use App\Access\HasVisibility;
use App\Access\Rules\EveryoneGrant;
use App\Access\Visibility;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomAssetAttribute extends Model
{
    use HasFactory, HasVisibility, SoftDeletes;

    public const TYPE_TEXT = 'text';

    public const TYPE_NUMBER = 'number';

    public const TYPE_TEXTAREA = 'textarea';

    public const TYPE_DATE = 'date';

    public const TYPE_DOCUMENT_EXPIRY = 'document_expiry';

    public const CHANNEL_WHATSAPP = 'whatsapp';

    public const CHANNEL_EMAIL = 'email';

    public const KEY_SERIAL_NUMBER = 'serial_number';

    public const KEY_IMEI1 = 'imei1';

    public const KEY_IMEI2 = 'imei2';

    public const KEY_TYPE = 'type';

    /**
     * Atribut identitas aset yang dibaca kode lewat `key` (bukan nama, yang boleh
     * diganti admin): key => [nama awal, tipe]. Dulu kolom di tabel assets.
     */
    public const IDENTITY_KEYS = [
        self::KEY_SERIAL_NUMBER => ['Serial Number', self::TYPE_TEXT],
        self::KEY_IMEI1 => ['IMEI1', self::TYPE_TEXT],
        self::KEY_IMEI2 => ['IMEI2', self::TYPE_TEXT],
        self::KEY_TYPE => ['Type', self::TYPE_TEXT],
    ];

    /** @var array<string, int>|null */
    private static ?array $keyIds = null;

    /** @var array<string, string>|null */
    private static ?array $keyLabels = null;

    /** @var list<int>|null */
    private static ?array $searchableIds = null;

    protected static function booted(): void
    {
        static::saved(fn () => self::forgetKeyIds());
        static::deleted(fn () => self::forgetKeyIds());
        // Atribut identitas dibaca kode lewat key-nya (pencarian, export, rekonsiliasi).
        static::deleting(fn (CustomAssetAttribute $attribute): bool => ! $attribute->isIdentity());
    }

    protected $fillable = [
        'key',
        'name',
        'type',
        'required',
        'is_active',
        'category_id',
        'is_notifiable',
        'notification_type',
        'notification_offset',
        'fixed_notification_date',
        'notification_channels',
        'notification_recipient_user_ids',
        'notification_recipient_emails',
        'notification_recipient_whatsapp_numbers',
    ];

    protected $casts = [
        'category_id' => 'array',
        'required' => 'boolean',
        'is_active' => 'boolean',
        'is_notifiable' => 'boolean',
        'notification_offset' => 'integer',
        'fixed_notification_date' => 'date',
        'notification_channels' => 'array',
        'notification_recipient_user_ids' => 'array',
        'notification_recipient_emails' => 'array',
        'notification_recipient_whatsapp_numbers' => 'array',
    ];

    /**
     * Id atribut untuk sebuah key identitas, dibuat bila belum ada.
     */
    public static function idForKey(string $key): int
    {
        self::$keyIds ??= static::query()->whereNotNull('key')->pluck('id', 'key')->map(fn ($id): int => (int) $id)->all();

        if (! isset(self::$keyIds[$key])) {
            [$name, $type] = self::IDENTITY_KEYS[$key] ?? throw new \InvalidArgumentException("Key atribut identitas tidak dikenal: {$key}");

            self::$keyIds[$key] = (int) static::query()->firstOrCreate(['key' => $key], ['name' => $name, 'type' => $type, 'is_active' => true, 'category_id' => []])->id;
        }

        return self::$keyIds[$key];
    }

    /**
     * Nama atribut identitas saat ini (admin boleh mengganti nama, misalnya "No. IMEI").
     */
    public static function labelForKey(string $key): string
    {
        self::$keyLabels ??= static::query()->whereNotNull('key')->pluck('name', 'key')->all();

        return self::$keyLabels[$key] ?? self::IDENTITY_KEYS[$key][0] ?? $key;
    }

    /**
     * Atribut yang ikut pencarian aset: aktif dan bukan dokumen (nilainya JSON).
     *
     * @return list<int>
     */
    public static function searchableIds(): array
    {
        return self::$searchableIds ??= static::query()
            ->where('is_active', true)
            ->where('type', '!=', self::TYPE_DOCUMENT_EXPIRY)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    public static function forgetKeyIds(): void
    {
        self::$keyIds = null;
        self::$keyLabels = null;
        self::$searchableIds = null;
    }

    public function isIdentity(): bool
    {
        return filled($this->key);
    }

    public static function typeOptions(): array
    {
        return [
            self::TYPE_TEXT => 'Text Input',
            self::TYPE_NUMBER => 'Number Input',
            self::TYPE_TEXTAREA => 'Textarea',
            self::TYPE_DATE => 'Date Picker',
            self::TYPE_DOCUMENT_EXPIRY => 'Dokumen / Masa Berlaku',
        ];
    }

    public static function notificationChannelOptions(): array
    {
        return [
            self::CHANNEL_WHATSAPP => 'WhatsApp',
            self::CHANNEL_EMAIL => 'Email',
        ];
    }

    public function notificationChannels(): array
    {
        $channels = collect($this->arrayAttribute('notification_channels'))
            ->map(fn ($channel) => is_string($channel) ? trim($channel) : $channel)
            ->filter(fn ($channel) => in_array($channel, array_keys(self::notificationChannelOptions()), true))
            ->unique()
            ->values()
            ->all();

        return $channels === [] ? [self::CHANNEL_WHATSAPP] : $channels;
    }

    public function usesNotificationChannel(string $channel): bool
    {
        return in_array($channel, $this->notificationChannels(), true);
    }

    public function notificationRecipientUserIds(): array
    {
        return collect($this->arrayAttribute('notification_recipient_user_ids'))
            ->map(fn ($id) => filter_var($id, FILTER_VALIDATE_INT))
            ->filter(fn ($id) => $id !== false && $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function notificationRecipientEmails(): array
    {
        return collect($this->arrayAttribute('notification_recipient_emails'))
            ->map(fn ($email) => is_string($email) ? trim($email) : null)
            ->filter(fn ($email) => filled($email) && filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();
    }

    public function notificationRecipientWhatsappNumbers(): array
    {
        return collect($this->arrayAttribute('notification_recipient_whatsapp_numbers'))
            ->map(function ($number) {
                if (! is_string($number) && ! is_numeric($number)) {
                    return null;
                }

                return preg_replace('/[^\d+]/', '', (string) $number);
            })
            ->filter(fn ($number) => filled($number))
            ->unique()
            ->values()
            ->all();
    }

    protected function arrayAttribute(string $key): array
    {
        $value = $this->getAttribute($key);

        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        return preg_split('/[\r\n,]+/', $value, flags: PREG_SPLIT_NO_EMPTY) ?: [];
    }

    public function setCategoryIdAttribute($value)
    {
        $this->attributes['category_id'] = json_encode(array_map('intval', $value));
    }

    // Relasi ke kategori
    public function category()
    {
        return $this->belongsTo(Category::class)->withTrashed();
    }

    // Relasi ke asset_attributes
    public function assetAttributes()
    {
        return $this->hasMany(AssetAttribute::class, 'custom_attribute_id');
    }

    /**
     * Semua record terlihat; pembatasan hanya lewat izin Shield di policy.
     */
    public static function visibility(): Visibility
    {
        return Visibility::make()->grant(new EveryoneGrant);
    }
}
