<?php

return [
    'user_model' => env('FILAMENT_OTP_LOGIN_USER_MODEL', \App\Models\AdminUser::class),

    'otp_log' => [
        'table' => env('FILAMENT_OTP_LOGIN_OTP_LOG_TABLE', 'admin_otp_logs'),
        'user_foreign_key' => env('FILAMENT_OTP_LOGIN_USER_FOREIGN_KEY', 'admin_user_id'),
    ],

    'mobile_column' => env('FILAMENT_OTP_LOGIN_MOBILE_COLUMN', 'mobile'),

    'sender' => env('FILAMENT_OTP_LOGIN_SENDER', \FilamentOtpLogin\Services\LogOtpSender::class),

    'otp_length' => (int) env('FILAMENT_OTP_LOGIN_OTP_LENGTH', 6),

    'otp_expires_seconds' => (int) env('FILAMENT_OTP_LOGIN_OTP_EXPIRES_SECONDS', 120),

    'request_block_seconds' => (int) env('FILAMENT_OTP_LOGIN_REQUEST_BLOCK_SECONDS', 60),

    'same_otp_max_sends' => (int) env('FILAMENT_OTP_LOGIN_SAME_OTP_MAX_SENDS', 3),

    'cache_prefix' => env('FILAMENT_OTP_LOGIN_CACHE_PREFIX', 'filament_otp_send:'),
];
