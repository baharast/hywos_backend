<?php

namespace App\Services\Sap;

use App\Exceptions\SapFieldProtectionException;
use Illuminate\Database\Eloquent\Model;

/**
 * Enforces that SAP-owned fields on records flagged `is_sap_owned = true`
 * cannot be silently overwritten by a local update.
 *
 * A field is considered "changed" when:
 *   - it appears in $payload, AND
 *   - its proposed value differs from the current model value.
 *
 * Re-submitting an identical value is allowed (idempotent update) so the
 * frontend can safely PUT the full form without forcing the user to strip
 * SAP-owned fields.
 */
class SapFieldGuard
{
    /**
     * Throws SapFieldProtectionException if any SAP-owned field would be modified.
     *
     * @param Model     $model           Current record (must have `is_sap_owned`).
     * @param array     $payload         New attribute values keyed by column name.
     * @param string[]  $sapOwnedFields  Column names that SAP owns.
     */
    public function assertCanUpdate(Model $model, array $payload, array $sapOwnedFields): void
    {
        if (! ($model->getAttribute('is_sap_owned') ?? false)) {
            return;
        }

        $violations = [];
        foreach ($sapOwnedFields as $field) {
            if (! array_key_exists($field, $payload)) {
                continue;
            }
            $proposed = $payload[$field];
            $current = $model->getAttribute($field);
            if ($this->valuesDiffer($current, $proposed)) {
                $violations[] = $field;
            }
        }

        if (! empty($violations)) {
            throw new SapFieldProtectionException(
                $violations,
                $model->getMorphClass(),
                (string) $model->getKey()
            );
        }
    }

    /**
     * Strip SAP-owned fields from a payload without throwing — useful for
     * partial-update flows that want a silent best-effort behavior. Not used
     * by default; provided for future endpoints.
     *
     * @return array  cleaned payload
     */
    public function strip(Model $model, array $payload, array $sapOwnedFields): array
    {
        if (! ($model->getAttribute('is_sap_owned') ?? false)) {
            return $payload;
        }
        foreach ($sapOwnedFields as $field) {
            unset($payload[$field]);
        }
        return $payload;
    }

    protected function valuesDiffer(mixed $current, mixed $proposed): bool
    {
        if (is_null($current) && is_null($proposed)) {
            return false;
        }
        if (is_null($current) xor is_null($proposed)) {
            return true;
        }
        return (string) $current !== (string) $proposed;
    }
}
