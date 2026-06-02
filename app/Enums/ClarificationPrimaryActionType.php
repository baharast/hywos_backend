<?php

namespace App\Enums;

/**
 * The single primary resolution action a selected clarification case exposes.
 *
 * Per FillTrack Clarification Cases UX Frontend Spec-EN-V1.3 §8 (Primary
 * Action Model) and §14 (TypeScript shapes).
 *
 * V1.3 §3 ("Resolution Workbench Principle") and §7 ("One primary action")
 * insist that each selected case exposes exactly one primary action — the
 * action picked is driven by case source, status and permissions. That
 * derivation lives in the lifecycle controller (TSK-012), not in this enum.
 *
 * `none` is used when the case is in a calm state (resolved / closed /
 * waiting_for_owner under a role the user cannot act on) and only reference
 * links should be shown.
 *
 * No `tone()` here — primary action is an instruction, not a status badge.
 */
class ClarificationPrimaryActionType
{
    public const RESOLVE_TRAILER_IDENTIFICATION = 'resolve_trailer_identification';
    public const FIX_ORDER_ASSIGNMENT = 'fix_order_assignment';
    public const RESOLVE_PARKING_CONFIRMATION = 'resolve_parking_confirmation';
    public const ROUTE_TO_OWNER = 'route_to_owner';
    public const ROUTE_TO_ANALYSIS = 'route_to_analysis';
    public const ROUTE_TO_DOCUMENTS = 'route_to_documents';
    public const RESOLVE_EXIT_BLOCKER = 'resolve_exit_blocker';
    public const RESOLVE_CASE = 'resolve_case';
    public const NONE = 'none';

    public static function all(): array
    {
        return [
            self::RESOLVE_TRAILER_IDENTIFICATION,
            self::FIX_ORDER_ASSIGNMENT,
            self::RESOLVE_PARKING_CONFIRMATION,
            self::ROUTE_TO_OWNER,
            self::ROUTE_TO_ANALYSIS,
            self::ROUTE_TO_DOCUMENTS,
            self::RESOLVE_EXIT_BLOCKER,
            self::RESOLVE_CASE,
            self::NONE,
        ];
    }

    public static function label(string $v): string
    {
        $translated = __('clarification.primary_action.' . $v);
        return $translated !== 'clarification.primary_action.' . $v
            ? $translated
            : ucfirst(str_replace('_', ' ', $v));
    }
}
