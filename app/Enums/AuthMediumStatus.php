<?php

namespace App\Enums;

class AuthMediumStatus
{
    public const ACTIVE = 'active';
    public const BLOCKED = 'blocked';
    public const EXPIRED = 'expired';
    public const USED = 'used';

    // Chip-card-specific lifecycle states (additive — do not remove the above).
    public const LOST = 'lost';
    public const DEFECTIVE = 'defective';
    public const REPLACED = 'replaced';
    public const ARCHIVED = 'archived';
}
