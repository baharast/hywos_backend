<?php

namespace App\Enums;

/**
 * Type of FillTrack -> SAP feedback message (V1.5 §3.4 / §11.2).
 *
 * Only meaningful for `direction = export` rows. Imports leave this null.
 */
class SapFeedbackType
{
    public const ORDER_STATUS = 'order_status';
    public const QUANTITY = 'quantity';
    public const QUALITY = 'quality';
    public const DOCUMENT = 'document';
    public const COMPLETION = 'completion';
    public const CANCELLATION = 'cancellation';

    public static function all(): array
    {
        return [
            self::ORDER_STATUS,
            self::QUANTITY,
            self::QUALITY,
            self::DOCUMENT,
            self::COMPLETION,
            self::CANCELLATION,
        ];
    }

    public static function label(string $v): string
    {
        $translated = __('masterdata.sap_feedback_type.' . $v);
        return $translated !== 'masterdata.sap_feedback_type.' . $v ? $translated : ucfirst(str_replace('_', ' ', $v));
    }
}
