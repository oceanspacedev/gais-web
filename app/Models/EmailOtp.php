<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu permintaan login email: kode 6 digit dan token magic link disimpan dalam satu record, jadi memakai salah
 * satunya ikut menghanguskan yang lain.
 */
class EmailOtp extends Model
{
    public const PURPOSE_LOGIN = 'login';

    protected $fillable = [
        'user_id',
        'email',
        'purpose',
        'otp_hash',
        'token_hash',
        'expires_at',
        'verified_at',
        'attempt_count',
    ];

    protected $hidden = [
        'otp_hash',
        'token_hash',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'attempt_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
