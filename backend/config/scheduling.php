<?php

return [
    'default_slot_duration' => (int) env('DEFAULT_SLOT_DURATION_MINUTES', 30),
    'min_slot_duration' => (int) env('MIN_SLOT_DURATION_MINUTES', 15),
    'max_slot_duration' => (int) env('MAX_SLOT_DURATION_MINUTES', 240),

    'booking' => [
        'max_advance_days' => (int) env('BOOKING_MAX_ADVANCE_DAYS', 60),
        'min_advance_hours' => (int) env('BOOKING_MIN_ADVANCE_HOURS', 2),
        'max_hold_time_minutes' => (int) env('BOOKING_MAX_HOLD_TIME_MINUTES', 10),
        'auto_confirm' => (bool) env('BOOKING_AUTO_CONFIRM', false),
        'auto_cancel_unpaid_after_hours' => (int) env('BOOKING_AUTO_CANCEL_UNPAID_AFTER_HOURS', 24),
    ],

    'slots' => [
        'default_capacity' => (int) env('DEFAULT_SLOT_CAPACITY', 1),
        'buffer_time_minutes' => (int) env('SLOT_BUFFER_TIME_MINUTES', 15),
        'overlap_allowed' => (bool) env('SLOT_OVERLAP_ALLOWED', false),
        'recurring_patterns' => [
            'daily' => ['enabled' => true, 'max_occurrences' => 365],
            'weekly' => ['enabled' => true, 'max_occurrences' => 52],
            'biweekly' => ['enabled' => true, 'max_occurrences' => 26],
            'monthly' => ['enabled' => true, 'max_occurrences' => 12],
        ],
    ],

    'capacity_windows' => [
        'enabled' => (bool) env('CAPACITY_WINDOWS_ENABLED', true),
        'default_window_size' => (int) env('DEFAULT_CAPACITY_WINDOW_MINUTES', 60),
        'max_window_size' => (int) env('MAX_CAPACITY_WINDOW_MINUTES', 480),
        'auto_create_from_availability' => (bool) env('AUTO_CREATE_CAPACITY_WINDOWS', false),
    ],

    'operating_hours' => [
        'default' => [
            'monday' => ['09:00', '18:00'],
            'tuesday' => ['09:00', '18:00'],
            'wednesday' => ['09:00', '18:00'],
            'thursday' => ['09:00', '18:00'],
            'friday' => ['09:00', '13:00'],
            'saturday' => ['09:00', '13:00'],
            'sunday' => [],
        ],
        'timezone' => env('SCHEDULING_TIMEZONE', 'Asia/Tehran'),
    ],

    'holidays' => [
        'enabled' => (bool) env('HOLIDAYS_ENABLED', true),
        'auto_block_slots' => (bool) env('HOLIDAYS_AUTO_BLOCK_SLOTS', true),
        'default_holidays' => [
            '01-01' => 'New Year',
            '03-20' => 'Nowruz',
            '03-21' => 'Nowruz',
            '03-22' => 'Nowruz',
            '03-23' => 'Nowruz',
            '03-24' => 'Nowruz',
        ],
    ],

    'reminders' => [
        'enabled' => (bool) env('APPOINTMENT_REMINDERS_ENABLED', true),
        'default_reminders' => [
            ['hours_before' => 24, 'channels' => ['sms', 'email']],
            ['hours_before' => 2, 'channels' => ['sms']],
        ],
        'max_reminders' => (int) env('MAX_APPOINTMENT_REMINDERS', 3),
    ],

    'cancellation' => [
        'allowed_until_hours_before' => (int) env('CANCELLATION_ALLOWED_UNTIL_HOURS_BEFORE', 24),
        'fee_after_deadline' => (int) env('CANCELLATION_FEE_AFTER_DEADLINE', 0),
        'fee_percentage' => (float) env('CANCELLATION_FEE_PERCENTAGE', 0),
        'require_reason' => (bool) env('CANCELLATION_REQUIRE_REASON', true),
    ],

    'check_in' => [
        'early_check_in_minutes' => (int) env('EARLY_CHECK_IN_MINUTES', 30),
        'late_check_in_minutes' => (int) env('LATE_CHECK_IN_MINUTES', 15),
        'auto_no_show_after_minutes' => (int) env('AUTO_NO_SHOW_AFTER_MINUTES', 30),
    ],

    'completion' => [
        'require_visit_confirmation' => (bool) env('REQUIRE_VISIT_CONFIRMATION', true),
        'auto_complete_after_hours' => (int) env('AUTO_COMPLETE_AFTER_HOURS', 0),
        'follow_up_reminder_days' => (int) env('FOLLOW_UP_REMINDER_DAYS', 7),
    ],
];
