<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Terminal Support Phone
    |--------------------------------------------------------------------------
    |
    | Shown on the driver login page's "Terminal Support" section per
    | FillTrack Driver Login Page UX Spec V3 §10.1. Value is TBC by Tyczka
    | / site support — leave null to render the helper text without a
    | clickable number.
    |
    */
    'support_phone' => env('TERMINAL_SUPPORT_PHONE', null),

    /*
    |--------------------------------------------------------------------------
    | Demo chip-scan via Tab key
    |--------------------------------------------------------------------------
    |
    | V3 §9.3 — when true, the chip login endpoint accepts `is_simulated:
    | true` from FE Tab-key simulations and tags audit/event entries with
    | source=demo. Must remain false in production.
    |
    */
    'demo_chip_scan' => env('APP_TERMINAL_DEMO_CHIP_SCAN', false),
];
