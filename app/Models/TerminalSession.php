<?php

namespace App\Models;

use App\Enums\GateTerminalSessionState;
use App\Enums\GateTerminalTouchpoint;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Driver-facing session at one of the 3 gate/terminal touchpoints.
 *
 * Read-only in MVP — see app/Http/Controllers/Api/GateTerminalMonitorController.
 *
 * All entity refs (device_id, plant_visit_id, order_id, trailer_id,
 * clarification_case_id, driver_id) are SOFT FKs: the row may exist before
 * or after its parent in other tables, and the System & Devices module
 * isn't built yet.
 */
class TerminalSession extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'session_no',
        'touchpoint',
        'touchpoint_label',
        'device_id',
        'device_label',
        'device_health',
        'driver_id',
        'driver_name',
        'driver_code',
        'plant_visit_id',
        'visit_no',
        'order_id',
        'order_no',
        'trailer_id',
        'trailer_label',
        'current_screen',
        'session_state',
        'issue_reason',
        'action_needed',
        'needs_operator',
        'support_requested',
        'clarification_case_id',
        'last_activity_at',
        'correlation_id',
        'notes',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'needs_operator' => 'boolean',
        'support_requested' => 'boolean',
        'last_activity_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (TerminalSession $model): void {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
            if (empty($model->session_state)) {
                $model->session_state = GateTerminalSessionState::IDLE;
            }
            if (empty($model->touchpoint_label) && ! empty($model->touchpoint)) {
                $model->touchpoint_label = GateTerminalTouchpoint::label($model->touchpoint);
            }
            if (empty($model->session_no)) {
                $model->session_no = static::nextSessionNo();
            }
        });
    }

    /**
     * Generate the next session_no in the form `TS-YYYY-NNNN`. Simple
     * count+1 — under high concurrency the unique index is the fallback.
     */
    public static function nextSessionNo(): string
    {
        $year = now()->format('Y');
        $countThisYear = static::query()->whereYear('created_at', now()->year)->count();
        $seq = str_pad((string) ($countThisYear + 1), 4, '0', STR_PAD_LEFT);
        return "TS-{$year}-{$seq}";
    }

    /* ----- Scopes ----- */

    public function scopeAtTouchpoint(Builder $q, string $touchpoint): Builder
    {
        return $q->where('touchpoint', $touchpoint);
    }

    /**
     * Sessions still operationally relevant (non-idle). Used by the
     * touchpoints endpoint and the summary block.
     */
    public function scopeActiveOrIssue(Builder $q): Builder
    {
        return $q->where('session_state', '!=', GateTerminalSessionState::IDLE);
    }

    public function scopeNeedsOperator(Builder $q): Builder
    {
        // V2.3 §11 — backend-set flag OR explicit needs_operator state OR
        // a linked open clarification. Frontend must NOT infer this; this
        // scope mirrors the same condition the controller uses.
        return $q->where(function (Builder $w): void {
            $w->where('needs_operator', true)
                ->orWhere('session_state', GateTerminalSessionState::NEEDS_OPERATOR);
        });
    }
}
