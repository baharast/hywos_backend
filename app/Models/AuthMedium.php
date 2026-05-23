<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AuthMedium extends Model
{
    use HasFactory;

    protected $table = 'auth_media';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'medium_type',
        'identifier_value',
        'identifier_hash',
        'display_identifier',
        'driver_id',
        'status',
        'is_single_use',
        'issued_at',
        'expires_at',
        'used_at',
        'revoked_at',
        'revoked_by_user_id',
        'revocation_reason',
        'order_id',
        'created_by_user_id',
    ];

    protected $hidden = [
        'identifier_value',
        'identifier_hash',
    ];

    protected $casts = [
        'is_single_use' => 'boolean',
        'issued_at' => 'datetime',
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
            if (empty($model->status)) {
                $model->status = 'active';
            }
        });
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id', 'id');
    }
}
