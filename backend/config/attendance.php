<?php

/**
 * Employee attendance settings. `expected_start_time` (24h "HH:MM") is
 * compared against each clock-in's time-of-day to decide Present vs Late —
 * a single tenant-wide default rather than a full per-tenant/per-role
 * schedule configuration UI.
 */
return [
    'expected_start_time' => env('ATTENDANCE_EXPECTED_START_TIME', '09:00'),
];
