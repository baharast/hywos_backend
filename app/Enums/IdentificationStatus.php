<?php

namespace App\Enums;

class IdentificationStatus
{
    public const CHIP_ASSIGNED = 'chip_assigned';
    public const TAN_AVAILABLE = 'tan_available';
    public const MISSING = 'missing';
    public const BLOCKED = 'blocked';
    public const EXPIRED = 'expired';
}
