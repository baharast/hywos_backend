<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditAction;
use App\Enums\AuthMediumStatus;
use App\Enums\AuthMediumType;
use App\Enums\ChipCardAssignmentState;
use App\Enums\ChipCardLifecycleStatus;
use App\Enums\ChipCardType;
use App\Enums\ChipUsageResult;
use App\Enums\EventCategory;
use App\Enums\EventSeverity;
use App\Http\Requests\ChipCard\ArchiveChipCardRequest;
use App\Http\Requests\ChipCard\AssignChipCardRequest;
use App\Http\Requests\ChipCard\BlockChipCardRequest;
use App\Http\Requests\ChipCard\MarkDefectiveChipCardRequest;
use App\Http\Requests\ChipCard\MarkLostChipCardRequest;
use App\Http\Requests\ChipCard\ReplaceChipCardRequest;
use App\Http\Requests\ChipCard\StoreChipCardRequest;
use App\Http\Requests\ChipCard\UnblockChipCardRequest;
use App\Http\Requests\ChipCard\UpdateChipCardRequest;
use App\Http\Resources\ChipCardAssignmentHistoryItemResource;
use App\Http\Resources\ChipCardResource;
use App\Models\AuditLog;
use App\Models\AuthMedium;
use App\Models\ChipCardAssignment;
use App\Models\Driver;
use App\Models\EventLog;
use App\Models\Trailer;
use App\Services\ApiResponse;
use App\Services\Audit\AuditLogger;
use App\Services\Events\EventLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChipCardController extends ApiController
{
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 25);

        $query = AuthMedium::query()->chipCards()->with(['driver', 'trailer']);
        $this->applyFilters($query, $request->all());

        $sort = (string) $request->query('sort', '-updated_at');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');
        $allowed = ['card_code', 'created_at', 'updated_at', 'expires_at', 'last_used_at', 'status'];
        if (! in_array($column, $allowed, true)) {
            $column = 'updated_at';
            $direction = 'desc';
        }

        $paginator = $query->orderBy($column, $direction)->paginate($perPage);
        $rows = ChipCardResource::collection($paginator->items());

        $summary = $this->summary();
        $lastUpdated = AuthMedium::query()->chipCards()->max('updated_at');

        return ApiResponse::list($rows, $paginator, $summary, $lastUpdated, __('vehicles.messages.cards_retrieved'));
    }

    public function store(StoreChipCardRequest $request, AuditLogger $audit, EventLogger $events)
    {
        $data = $request->validated();
        $cardType = $data['card_type'];
        $assignmentPayload = $data['assignment'] ?? null;

        $mediumType = $this->mediumTypeForCard($cardType);

        return DB::transaction(function () use ($data, $cardType, $assignmentPayload, $mediumType, $audit, $events) {
            $card = AuthMedium::create([
                'medium_type' => $mediumType,
                'card_code' => $data['card_code'],
                'card_type' => $cardType,
                'serial_number' => $data['serial_number'] ?? null,
                'masked_uid' => $data['masked_uid'] ?? null,
                'expires_at' => $data['expires_at'] ?? null,
                'status' => AuthMediumStatus::ACTIVE,
                'assignment_state' => ChipCardAssignmentState::UNASSIGNED,
                'is_single_use' => false,
                'issued_at' => now(),
            ]);

            $audit->record(
                $card,
                AuditAction::CHIP_CARD_REGISTERED,
                null,
                $audit->snapshotModel($card),
                null,
                null
            );

            $events->record(
                'chip_card.registered',
                $card,
                "Chip card {$card->card_code} registered",
                ['cardType' => $cardType],
                EventCategory::OPERATIONS,
                EventSeverity::INFO
            );

            // Optional combined register+assign.
            if ($assignmentPayload) {
                $this->performAssign(
                    $card,
                    $assignmentPayload['entity_type'],
                    $assignmentPayload['entity_id'],
                    $assignmentPayload['reason'] ?? 'Registered with assignment',
                    $assignmentPayload['reason_code'] ?? null,
                    $audit,
                    $events
                );
            }

            return $this->created(
                new ChipCardResource($card->fresh()->load(['driver', 'trailer'])),
                __('vehicles.messages.card_registered')
            );
        });
    }

    public function show(Request $request, $id)
    {
        $card = AuthMedium::query()->chipCards()->with(['driver', 'trailer'])->find($id);
        if (! $card) {
            return $this->error(__('vehicles.messages.card_not_found'), 'CHIP_CARD_NOT_FOUND', 404);
        }

        $includes = array_filter(array_map('trim', explode(',', (string) $request->query('include', ''))));
        $payload = ['card' => new ChipCardResource($card)];

        if (in_array('history', $includes, true)) {
            $payload['history'] = ChipCardAssignmentHistoryItemResource::collection(
                ChipCardAssignment::query()
                    ->where('auth_medium_id', $card->id)
                    ->orderByDesc('created_at')
                    ->limit(50)
                    ->get()
            );
        }
        if (in_array('usage', $includes, true)) {
            $payload['usage'] = EventLog::query()
                ->where('entity_type', $card->getMorphClass())
                ->where('entity_id', (string) $card->id)
                ->where('event_type', 'like', 'chip_card.usage%')
                ->orderByDesc('occurred_at')
                ->limit(20)
                ->get();
        }

        return $this->success($payload, __('vehicles.messages.card_retrieved'));
    }

    public function update(UpdateChipCardRequest $request, $id, AuditLogger $audit, EventLogger $events)
    {
        $card = AuthMedium::query()->chipCards()->find($id);
        if (! $card) {
            return $this->error(__('vehicles.messages.card_not_found'), 'CHIP_CARD_NOT_FOUND', 404);
        }
        if ($card->status === AuthMediumStatus::ARCHIVED) {
            return $this->error(__('vehicles.messages.card_archived_locked'), 'CARD_ARCHIVED', 409);
        }

        $data = $request->validated();

        return DB::transaction(function () use ($card, $data, $audit, $events) {
            $old = $audit->snapshotModel($card);

            $card->update($data);

            $audit->record(
                $card,
                AuditAction::CHIP_CARD_REGISTERED, // generic "metadata updated" reuse; no dedicated UPDATED action specified
                $old,
                $audit->snapshotModel($card->fresh()),
                null,
                null
            );

            $events->record(
                'chip_card.updated',
                $card,
                "Chip card {$card->card_code} metadata updated",
                null,
                EventCategory::OPERATIONS,
                EventSeverity::INFO
            );

            return $this->success(
                new ChipCardResource($card->fresh()->load(['driver', 'trailer'])),
                __('vehicles.messages.card_updated')
            );
        });
    }

    public function assign(AssignChipCardRequest $request, $id, AuditLogger $audit, EventLogger $events)
    {
        $card = AuthMedium::query()->chipCards()->find($id);
        if (! $card) {
            return $this->error(__('vehicles.messages.card_not_found'), 'CHIP_CARD_NOT_FOUND', 404);
        }
        if ($card->status !== AuthMediumStatus::ACTIVE) {
            return $this->error(__('vehicles.messages.card_not_active'), 'CARD_NOT_ASSIGNABLE', 409);
        }
        if ($card->assignment_state === ChipCardAssignmentState::ASSIGNED) {
            return $this->error(__('vehicles.messages.card_already_assigned'), 'CARD_NOT_ASSIGNABLE', 409);
        }

        $entityType = $request->input('entity_type');
        $entityId   = $request->input('entity_id');
        $reason     = $request->input('reason');
        $reasonCode = $request->input('reason_code');

        if ($card->card_type === ChipCardType::DRIVER_CARD && $entityType !== 'driver') {
            return $this->error(__('vehicles.messages.card_type_mismatch'), 'CARD_TYPE_MISMATCH', 409);
        }
        if ($card->card_type === ChipCardType::TRAILER_CHIP_TAG && $entityType !== 'trailer') {
            return $this->error(__('vehicles.messages.card_type_mismatch'), 'CARD_TYPE_MISMATCH', 409);
        }

        $existing = AuthMedium::query()->chipCards()
            ->where('card_type', $card->card_type)
            ->where('assignment_state', ChipCardAssignmentState::ASSIGNED)
            ->where('status', AuthMediumStatus::ACTIVE)
            ->where($entityType === 'driver' ? 'driver_id' : 'trailer_id', $entityId)
            ->where('id', '!=', $card->id)
            ->first();
        if ($existing) {
            return $this->error(
                __('vehicles.messages.entity_has_card'),
                'ASSIGNMENT_CONFLICT',
                409,
                ['existingChipCardId' => $existing->id]
            );
        }

        return DB::transaction(function () use ($card, $entityType, $entityId, $reason, $reasonCode, $audit, $events) {
            $this->performAssign($card, $entityType, $entityId, $reason, $reasonCode, $audit, $events);
            return $this->success(
                new ChipCardResource($card->fresh()->load(['driver', 'trailer'])),
                __('vehicles.messages.card_assigned')
            );
        });
    }

    public function unassign(Request $request, $id, AuditLogger $audit, EventLogger $events)
    {
        $card = AuthMedium::query()->chipCards()->find($id);
        if (! $card) {
            return $this->error(__('vehicles.messages.card_not_found'), 'CHIP_CARD_NOT_FOUND', 404);
        }
        if ($card->assignment_state !== ChipCardAssignmentState::ASSIGNED) {
            return $this->error(__('vehicles.messages.card_not_assigned'), 'NOT_ASSIGNED', 409);
        }

        $reason = (string) ($request->input('reason') ?? 'Unassigned');
        $reasonCode = $request->input('reason_code');

        return DB::transaction(function () use ($card, $reason, $reasonCode, $audit, $events) {
            $entityType = $card->driver_id ? 'driver' : ($card->trailer_id ? 'trailer' : null);
            $entityId   = $card->driver_id ?? $card->trailer_id;
            $label      = $this->resolveEntityLabel($entityType, $entityId);
            $old        = $audit->snapshotModel($card);

            $card->update([
                'assignment_state' => ChipCardAssignmentState::UNASSIGNED,
                'driver_id' => null,
                'trailer_id' => null,
            ]);

            ChipCardAssignment::create([
                'auth_medium_id' => $card->id,
                'action' => 'unassigned',
                'entity_type' => $entityType ?? 'unknown',
                'entity_id' => $entityId,
                'entity_label' => $label,
                'reason' => $reason,
                'reason_code' => $reasonCode,
            ]);

            $audit->record(
                $card,
                AuditAction::CHIP_CARD_UNASSIGNED,
                $old,
                $audit->snapshotModel($card->fresh()),
                $reason,
                $reasonCode
            );

            $events->record(
                'chip_card.unassigned',
                $card,
                "Chip card {$card->card_code} unassigned from {$label}",
                ['previousEntityType' => $entityType, 'previousEntityId' => $entityId, 'reason' => $reason],
                EventCategory::OPERATIONS,
                EventSeverity::INFO
            );

            return $this->success(
                new ChipCardResource($card->fresh()->load(['driver', 'trailer'])),
                __('vehicles.messages.card_unassigned')
            );
        });
    }

    public function block(BlockChipCardRequest $request, $id, AuditLogger $audit, EventLogger $events)
    {
        $card = AuthMedium::query()->chipCards()->find($id);
        if (! $card) {
            return $this->error(__('vehicles.messages.card_not_found'), 'CHIP_CARD_NOT_FOUND', 404);
        }
        if ($card->status === AuthMediumStatus::BLOCKED) {
            return $this->error(__('vehicles.messages.card_already_blocked'), 'ALREADY_BLOCKED', 409);
        }

        $reason = $request->input('reason');
        $reasonCode = $request->input('reason_code');

        return DB::transaction(function () use ($card, $reason, $reasonCode, $audit, $events) {
            $old = $audit->snapshotModel($card);
            $card->update(['status' => AuthMediumStatus::BLOCKED]);

            $audit->record($card, AuditAction::CHIP_CARD_BLOCKED, $old, $audit->snapshotModel($card->fresh()), $reason, $reasonCode);
            $events->record(
                'chip_card.blocked', $card,
                "Chip card {$card->card_code} blocked",
                ['reason' => $reason, 'reason_code' => $reasonCode],
                EventCategory::OPERATIONS, EventSeverity::WARNING
            );

            return $this->success(new ChipCardResource($card->fresh()->load(['driver', 'trailer'])), __('vehicles.messages.card_blocked'));
        });
    }

    public function unblock(UnblockChipCardRequest $request, $id, AuditLogger $audit, EventLogger $events)
    {
        $card = AuthMedium::query()->chipCards()->find($id);
        if (! $card) {
            return $this->error(__('vehicles.messages.card_not_found'), 'CHIP_CARD_NOT_FOUND', 404);
        }
        if ($card->status !== AuthMediumStatus::BLOCKED) {
            return $this->error(__('vehicles.messages.card_not_blocked'), 'NOT_BLOCKED', 409);
        }

        $reason = $request->input('reason');
        $reasonCode = $request->input('reason_code');

        return DB::transaction(function () use ($card, $reason, $reasonCode, $audit, $events) {
            $old = $audit->snapshotModel($card);
            $card->update(['status' => AuthMediumStatus::ACTIVE]);

            $audit->record($card, AuditAction::CHIP_CARD_UNBLOCKED, $old, $audit->snapshotModel($card->fresh()), $reason, $reasonCode);
            $events->record(
                'chip_card.unblocked', $card,
                "Chip card {$card->card_code} unblocked",
                ['reason' => $reason, 'reason_code' => $reasonCode],
                EventCategory::OPERATIONS, EventSeverity::INFO
            );

            return $this->success(new ChipCardResource($card->fresh()->load(['driver', 'trailer'])), __('vehicles.messages.card_unblocked'));
        });
    }

    public function markLost(MarkLostChipCardRequest $request, $id, AuditLogger $audit, EventLogger $events)
    {
        $card = AuthMedium::query()->chipCards()->find($id);
        if (! $card) {
            return $this->error(__('vehicles.messages.card_not_found'), 'CHIP_CARD_NOT_FOUND', 404);
        }

        $reason = $request->input('reason');
        $reasonCode = $request->input('reason_code');
        $assignmentAction = $request->input('assignment_action', 'keep');

        return DB::transaction(function () use ($card, $reason, $reasonCode, $assignmentAction, $audit, $events) {
            $old = $audit->snapshotModel($card);
            $card->update([
                'status' => AuthMediumStatus::LOST,
                'lost_at' => now(),
            ]);

            if ($assignmentAction === 'unassign' && $card->assignment_state === ChipCardAssignmentState::ASSIGNED) {
                $entityType = $card->driver_id ? 'driver' : ($card->trailer_id ? 'trailer' : null);
                $entityId   = $card->driver_id ?? $card->trailer_id;
                $label      = $this->resolveEntityLabel($entityType, $entityId);

                $card->update([
                    'assignment_state' => ChipCardAssignmentState::UNASSIGNED,
                    'driver_id' => null,
                    'trailer_id' => null,
                ]);

                ChipCardAssignment::create([
                    'auth_medium_id' => $card->id,
                    'action' => 'unassigned',
                    'entity_type' => $entityType ?? 'unknown',
                    'entity_id' => $entityId,
                    'entity_label' => $label,
                    'reason' => 'Auto-unassigned due to lost report: ' . $reason,
                    'reason_code' => $reasonCode,
                ]);
            }

            $audit->record($card, AuditAction::CHIP_CARD_MARKED_LOST, $old, $audit->snapshotModel($card->fresh()), $reason, $reasonCode);
            $events->record(
                'chip_card.marked_lost', $card,
                "Chip card {$card->card_code} marked lost",
                ['reason' => $reason, 'assignmentAction' => $assignmentAction],
                EventCategory::OPERATIONS, EventSeverity::WARNING
            );

            return $this->success(new ChipCardResource($card->fresh()->load(['driver', 'trailer'])), __('vehicles.messages.card_lost'));
        });
    }

    public function markDefective(MarkDefectiveChipCardRequest $request, $id, AuditLogger $audit, EventLogger $events)
    {
        $card = AuthMedium::query()->chipCards()->find($id);
        if (! $card) {
            return $this->error(__('vehicles.messages.card_not_found'), 'CHIP_CARD_NOT_FOUND', 404);
        }

        $reason = $request->input('reason');
        $reasonCode = $request->input('reason_code');

        return DB::transaction(function () use ($card, $reason, $reasonCode, $audit, $events) {
            $old = $audit->snapshotModel($card);
            $card->update([
                'status' => AuthMediumStatus::DEFECTIVE,
                'defective_at' => now(),
            ]);

            $audit->record($card, AuditAction::CHIP_CARD_MARKED_DEFECTIVE, $old, $audit->snapshotModel($card->fresh()), $reason, $reasonCode);
            $events->record(
                'chip_card.marked_defective', $card,
                "Chip card {$card->card_code} marked defective",
                ['reason' => $reason],
                EventCategory::OPERATIONS, EventSeverity::WARNING
            );

            return $this->success(new ChipCardResource($card->fresh()->load(['driver', 'trailer'])), __('vehicles.messages.card_defective'));
        });
    }

    public function replace(ReplaceChipCardRequest $request, $id, AuditLogger $audit, EventLogger $events)
    {
        $oldCard = AuthMedium::query()->chipCards()->find($id);
        if (! $oldCard) {
            return $this->error(__('vehicles.messages.card_not_found'), 'CHIP_CARD_NOT_FOUND', 404);
        }

        $newCard = AuthMedium::query()->chipCards()->find($request->input('replacement_card_id'));
        if (! $newCard) {
            return $this->error(__('vehicles.messages.replacement_not_found'), 'CHIP_CARD_NOT_FOUND', 404);
        }
        if ($newCard->id === $oldCard->id) {
            return $this->error(__('vehicles.messages.replacement_must_differ'), 'CARD_NOT_ASSIGNABLE', 409);
        }
        if ($newCard->status !== AuthMediumStatus::ACTIVE) {
            return $this->error(__('vehicles.messages.replacement_not_active'), 'CARD_NOT_ASSIGNABLE', 409);
        }
        if ($newCard->assignment_state !== ChipCardAssignmentState::UNASSIGNED) {
            return $this->error(__('vehicles.messages.replacement_not_unassigned'), 'CARD_NOT_ASSIGNABLE', 409);
        }
        if ($newCard->card_type !== $oldCard->card_type) {
            return $this->error(__('vehicles.messages.replacement_type_mismatch'), 'CARD_TYPE_MISMATCH', 409);
        }

        $reason = $request->input('reason');
        $reasonCode = $request->input('reason_code');
        $carry = (bool) $request->input('carry_assignment', true);
        $oldFinalStatus = $request->input('old_card_final_status', AuthMediumStatus::REPLACED);

        return DB::transaction(function () use ($oldCard, $newCard, $reason, $reasonCode, $carry, $oldFinalStatus, $audit, $events) {
            $entityType = $oldCard->driver_id ? 'driver' : ($oldCard->trailer_id ? 'trailer' : null);
            $entityId   = $oldCard->driver_id ?? $oldCard->trailer_id;
            $label      = $this->resolveEntityLabel($entityType, $entityId);

            $oldSnapshotBefore = $audit->snapshotModel($oldCard);
            $newSnapshotBefore = $audit->snapshotModel($newCard);

            // Link the two cards.
            $oldCard->update([
                'replaced_by_card_id' => $newCard->id,
                'replacement_reason' => $reason,
            ]);
            $newCard->update([
                'replacement_of_card_id' => $oldCard->id,
                'replacement_reason' => $reason,
            ]);

            if ($carry && $oldCard->assignment_state === ChipCardAssignmentState::ASSIGNED && $entityType && $entityId) {
                // Move assignment.
                $oldCard->update([
                    'assignment_state' => ChipCardAssignmentState::REPLACED,
                    'driver_id' => null,
                    'trailer_id' => null,
                ]);
                ChipCardAssignment::create([
                    'auth_medium_id' => $oldCard->id,
                    'action' => 'replaced',
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                    'entity_label' => $label,
                    'reason' => $reason,
                    'reason_code' => $reasonCode,
                ]);

                $newCard->update([
                    'assignment_state' => ChipCardAssignmentState::ASSIGNED,
                    'driver_id' => $entityType === 'driver' ? $entityId : null,
                    'trailer_id' => $entityType === 'trailer' ? $entityId : null,
                ]);
                ChipCardAssignment::create([
                    'auth_medium_id' => $newCard->id,
                    'action' => 'assigned',
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                    'entity_label' => $label,
                    'reason' => 'Carried over from card ' . $oldCard->card_code . ': ' . $reason,
                    'reason_code' => $reasonCode,
                ]);
            }

            $oldCard->update(['status' => $oldFinalStatus]);

            $audit->record($oldCard, AuditAction::CHIP_CARD_REPLACED, $oldSnapshotBefore, $audit->snapshotModel($oldCard->fresh()), $reason, $reasonCode);
            $audit->record($newCard, AuditAction::CHIP_CARD_REPLACED, $newSnapshotBefore, $audit->snapshotModel($newCard->fresh()), $reason, $reasonCode);

            $events->record(
                'chip_card.replaced', $oldCard,
                "Chip card {$oldCard->card_code} replaced by {$newCard->card_code}",
                [
                    'oldCardId' => $oldCard->id,
                    'newCardId' => $newCard->id,
                    'carryAssignment' => $carry,
                    'oldFinalStatus' => $oldFinalStatus,
                    'reason' => $reason,
                ],
                EventCategory::OPERATIONS, EventSeverity::WARNING
            );

            return $this->success([
                'oldCard' => new ChipCardResource($oldCard->fresh()->load(['driver', 'trailer'])),
                'newCard' => new ChipCardResource($newCard->fresh()->load(['driver', 'trailer'])),
            ], __('vehicles.messages.card_replaced'));
        });
    }

    public function archive(ArchiveChipCardRequest $request, $id, AuditLogger $audit, EventLogger $events)
    {
        $card = AuthMedium::query()->chipCards()->find($id);
        if (! $card) {
            return $this->error(__('vehicles.messages.card_not_found'), 'CHIP_CARD_NOT_FOUND', 404);
        }
        if ($card->assignment_state === ChipCardAssignmentState::ASSIGNED) {
            return $this->error(__('vehicles.messages.card_cannot_archive'), 'CARD_NOT_ASSIGNABLE', 409);
        }

        $reason = $request->input('reason');
        $reasonCode = $request->input('reason_code');

        return DB::transaction(function () use ($card, $reason, $reasonCode, $audit, $events) {
            $old = $audit->snapshotModel($card);
            $card->update([
                'status' => AuthMediumStatus::ARCHIVED,
                'assignment_state' => ChipCardAssignmentState::ARCHIVED,
                'archived_at' => now(),
            ]);

            $audit->record($card, AuditAction::CHIP_CARD_ARCHIVED, $old, $audit->snapshotModel($card->fresh()), $reason, $reasonCode);
            $events->record(
                'chip_card.archived', $card,
                "Chip card {$card->card_code} archived",
                ['reason' => $reason],
                EventCategory::OPERATIONS, EventSeverity::INFO
            );

            return $this->success(new ChipCardResource($card->fresh()->load(['driver', 'trailer'])), __('vehicles.messages.card_archived'));
        });
    }

    public function assignmentHistory(Request $request, $id)
    {
        $card = AuthMedium::query()->chipCards()->find($id);
        if (! $card) {
            return $this->error(__('vehicles.messages.card_not_found'), 'CHIP_CARD_NOT_FOUND', 404);
        }

        $perPage = (int) $request->query('per_page', 25);
        $paginator = ChipCardAssignment::query()
            ->where('auth_medium_id', $card->id)
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return ApiResponse::list(
            ChipCardAssignmentHistoryItemResource::collection($paginator->items()),
            $paginator,
            null,
            $paginator->items() ? data_get($paginator->items()[0], 'created_at') : null,
            __('vehicles.messages.assignment_history')
        );
    }

    public function usageHistory(Request $request, $id)
    {
        $card = AuthMedium::query()->chipCards()->find($id);
        if (! $card) {
            return $this->error(__('vehicles.messages.card_not_found'), 'CHIP_CARD_NOT_FOUND', 404);
        }

        $perPage = (int) $request->query('per_page', 25);
        $paginator = EventLog::query()
            ->where('entity_type', $card->getMorphClass())
            ->where('entity_id', (string) $card->id)
            ->where('event_type', 'like', 'chip_card.usage%')
            ->orderByDesc('occurred_at')
            ->paginate($perPage);

        return ApiResponse::list(
            $paginator->items(),
            $paginator,
            null,
            null,
            __('vehicles.messages.usage_history')
        );
    }

    public function eventsAudit(Request $request, $id)
    {
        $card = AuthMedium::query()->chipCards()->find($id);
        if (! $card) {
            return $this->error(__('vehicles.messages.card_not_found'), 'CHIP_CARD_NOT_FOUND', 404);
        }

        $perPage = (int) $request->query('per_page', 25);
        $entityType = $card->getMorphClass();

        $audits = AuditLog::query()
            ->where('entity_type', $entityType)
            ->where('entity_id', (string) $card->id)
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        $events = EventLog::query()
            ->where('entity_type', $entityType)
            ->where('entity_id', (string) $card->id)
            ->orderByDesc('occurred_at')
            ->limit(200)
            ->get();

        return $this->success([
            'audits' => $audits,
            'events' => $events,
            'meta' => [
                'per_page' => $perPage,
                'audits_count' => $audits->count(),
                'events_count' => $events->count(),
            ],
        ], __('vehicles.messages.card_events_audit'));
    }

    public function export(Request $request)
    {
        $visibleColumns = (array) $request->input('visible_columns', []);
        $filters = (array) $request->input('filters', []);
        $search = $request->input('search');

        $query = AuthMedium::query()->chipCards()->with(['driver', 'trailer']);
        $this->applyFilters($query, array_merge(['search' => $search], $filters));

        $rows = ChipCardResource::collection($query->orderByDesc('updated_at')->get());

        return $this->success([
            'visibleColumns' => $visibleColumns,
            'rows' => $rows,
            'note' => 'Binary export (CSV/XLSX) not yet implemented; returning JSON payload.',
        ], __('vehicles.messages.card_export'));
    }

    // ---------- helpers ----------

    protected function performAssign(
        AuthMedium $card,
        string $entityType,
        string $entityId,
        ?string $reason,
        ?string $reasonCode,
        AuditLogger $audit,
        EventLogger $events
    ): void {
        $label = $this->resolveEntityLabel($entityType, $entityId);
        $old = $audit->snapshotModel($card);

        $card->update([
            'assignment_state' => ChipCardAssignmentState::ASSIGNED,
            'driver_id'  => $entityType === 'driver'  ? $entityId : null,
            'trailer_id' => $entityType === 'trailer' ? $entityId : null,
        ]);

        ChipCardAssignment::create([
            'auth_medium_id' => $card->id,
            'action' => 'assigned',
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'entity_label' => $label,
            'reason' => $reason,
            'reason_code' => $reasonCode,
        ]);

        $audit->record(
            $card,
            AuditAction::CHIP_CARD_ASSIGNED,
            $old,
            $audit->snapshotModel($card->fresh()),
            $reason,
            $reasonCode
        );

        $events->record(
            'chip_card.assigned',
            $card,
            "Chip {$card->card_code} assigned to {$label}",
            ['entityType' => $entityType, 'entityId' => $entityId, 'reason' => $reason],
            EventCategory::OPERATIONS,
            EventSeverity::INFO
        );
    }

    protected function resolveEntityLabel(?string $entityType, ?string $entityId): ?string
    {
        if (! $entityType || ! $entityId) {
            return null;
        }
        if ($entityType === 'driver') {
            $d = Driver::find($entityId);
            if (! $d) {
                return $entityId;
            }
            $name = trim(($d->first_name ?? '') . ' ' . ($d->last_name ?? ''));
            return $d->driver_code ? "{$name} ({$d->driver_code})" : ($name ?: $entityId);
        }
        if ($entityType === 'trailer') {
            $t = Trailer::find($entityId);
            if (! $t) {
                return $entityId;
            }
            return $t->trailer_label ?: $t->trailer_code ?: $entityId;
        }
        return $entityId;
    }

    protected function mediumTypeForCard(string $cardType): string
    {
        return match ($cardType) {
            ChipCardType::DRIVER_CARD => AuthMediumType::CHIP_CARD,
            ChipCardType::TRAILER_CHIP_TAG => AuthMediumType::TRAILER_CHIP,
            default => AuthMediumType::OTHER,
        };
    }

    protected function applyFilters($query, array $filters): void
    {
        if (! empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('card_code', 'like', $search)
                    ->orWhere('serial_number', 'like', $search)
                    ->orWhere('masked_uid', 'like', $search)
                    ->orWhere('last_used_context', 'like', $search)
                    ->orWhereHas('driver', function ($d) use ($search) {
                        $d->where('first_name', 'like', $search)
                            ->orWhere('last_name', 'like', $search)
                            ->orWhere('driver_code', 'like', $search);
                    })
                    ->orWhereHas('trailer', function ($t) use ($search) {
                        $t->where('trailer_code', 'like', $search)
                            ->orWhere('trailer_label', 'like', $search);
                    });
            });
        }

        if (! empty($filters['card_type'])) {
            $query->where('card_type', $filters['card_type']);
        }
        if (! empty($filters['assignment_state'])) {
            $query->where('assignment_state', $filters['assignment_state']);
        }

        if (! empty($filters['status'])) {
            $status = $filters['status'];
            // EXPIRED is a derived view — map to expires_at predicate even when status='active'.
            if ($status === ChipCardLifecycleStatus::EXPIRED) {
                $query->whereNotNull('expires_at')->where('expires_at', '<', now());
            } else {
                $query->where('status', $status);
            }
        }

        if (! empty($filters['assigned_entity_type'])) {
            $type = $filters['assigned_entity_type'];
            if ($type === 'driver') {
                $query->whereNotNull('driver_id')->where('assignment_state', ChipCardAssignmentState::ASSIGNED);
            } elseif ($type === 'trailer') {
                $query->whereNotNull('trailer_id')->where('assignment_state', ChipCardAssignmentState::ASSIGNED);
            } elseif ($type === 'none') {
                $query->where('assignment_state', ChipCardAssignmentState::UNASSIGNED);
            }
        }

        if (! empty($filters['expiry_state'])) {
            $today = now();
            $in30 = now()->addDays(30);
            switch ($filters['expiry_state']) {
                case 'expired':
                    $query->whereNotNull('expires_at')->where('expires_at', '<', $today);
                    break;
                case 'expiring_soon':
                    $query->whereNotNull('expires_at')
                        ->where('expires_at', '>=', $today)
                        ->where('expires_at', '<=', $in30);
                    break;
                case 'valid':
                    $query->where(function ($q) use ($in30) {
                        $q->whereNull('expires_at')->orWhere('expires_at', '>', $in30);
                    });
                    break;
            }
        }

        if (array_key_exists('has_failed_use', $filters)) {
            $hasFailed = filter_var($filters['has_failed_use'], FILTER_VALIDATE_BOOLEAN);
            if ($hasFailed) {
                $query->whereIn('last_usage_result', ChipUsageResult::failureValues());
            }
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== '') {
            $val = filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN);
            if ($val) {
                $query->where('status', AuthMediumStatus::ACTIVE);
            } else {
                $query->where('status', '!=', AuthMediumStatus::ACTIVE);
            }
        }
    }

    protected function summary(): array
    {
        $base = AuthMedium::query()->chipCards();

        $total = (clone $base)->count();
        $activeAssigned = (clone $base)
            ->where('status', AuthMediumStatus::ACTIVE)
            ->where('assignment_state', ChipCardAssignmentState::ASSIGNED)
            ->count();
        $unassigned = (clone $base)
            ->where('status', AuthMediumStatus::ACTIVE)
            ->where('assignment_state', ChipCardAssignmentState::UNASSIGNED)
            ->count();
        $blockedOrLost = (clone $base)
            ->whereIn('status', [AuthMediumStatus::BLOCKED, AuthMediumStatus::LOST])
            ->count();
        $expiringSoon = (clone $base)
            ->whereNotNull('expires_at')
            ->where('expires_at', '>=', now())
            ->where('expires_at', '<=', now()->addDays(30))
            ->count();

        return [
            'totalCards' => $total,
            'activeAssigned' => $activeAssigned,
            'unassigned' => $unassigned,
            'blockedOrLost' => $blockedOrLost,
            'expiringSoon' => $expiringSoon,
        ];
    }
}
