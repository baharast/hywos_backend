<?php

namespace App\Models;

use App\Http\Middleware\CorrelationIdMiddleware;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ChipCardAssignment extends Model
{
    use HasFactory;

    protected $table = 'chip_card_assignments';

    public $incrementing = false;
    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'auth_medium_id',
        'action',
        'entity_type',
        'entity_id',
        'entity_label',
        'actor_user_id',
        'reason',
        'reason_code',
        'correlation_id',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
            if (empty($model->created_at)) {
                $model->created_at = now();
            }
            if (empty($model->correlation_id)) {
                $request = request();
                if ($request) {
                    $cid = $request->attributes->get(CorrelationIdMiddleware::ATTRIBUTE);
                    if ($cid) {
                        $model->correlation_id = (string) $cid;
                    }
                }
            }
        });
    }

    public function chipCard(): BelongsTo
    {
        return $this->belongsTo(AuthMedium::class, 'auth_medium_id', 'id');
    }
}
