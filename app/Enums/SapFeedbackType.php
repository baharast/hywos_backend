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
        return match ($v) {
            self::ORDER_STATUS => 'Order Status',
            self::QUANTITY => 'Quantity',
            self::QUALITY => 'Quality',
            self::DOCUMENT => 'Document',
            self::COMPLETION => 'Completion',
            self::CANCELLATION => 'Cancellation',
            default => ucwords(str_replace('_', ' ', $v)),
        };
    }
}
