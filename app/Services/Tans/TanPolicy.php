<?php

namespace App\Services\Tans;

/**
 * Static policy constants for the TANs module — MVP defaults.
 * Centralized here so FormRequests, controllers, and tests reference one source.
 */
class TanPolicy
{
    /**
     * Maximum number of days into the future a TAN may be set to expire,
     * measured from "now" at request validation time.
     */
    public const MAX_VALIDITY_DAYS = 7;

    /**
     * Threshold (in hours) below which a TAN's `expires_at` is considered
     * "expiring soon" for UI badges and list filters.
     */
    public const EXPIRING_SOON_HOURS = 24;

    /**
     * Length of the generated numeric TAN value.
     *
     * Per V6 §16.1 the TAN is 7 digits and is presented to the driver
     * in the human-readable form `XXX-XXXX` (3+4). Backend stores the
     * digits-only form on `auth_media.identifier_value` and the masked
     * form `***-XXXX` on `auth_media.tan_masked`. The display form
     * (`XXX-XXXX`) lives on `auth_media.display_identifier`.
     */
    public const VALUE_DIGITS = 7;
}
