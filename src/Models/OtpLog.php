<?php

namespace FilamentOtpLogin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OtpLog extends Model
{
    protected $guarded = [];

    public function getTable(): string
    {
        return config('filament-otp-login.otp_log.table', 'admin_otp_logs');
    }

    protected function casts(): array
    {
        return [
            'is_used' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        $userModel = config('filament-otp-login.user_model');
        $foreignKey = config('filament-otp-login.otp_log.user_foreign_key', 'admin_user_id');

        return $this->belongsTo($userModel, $foreignKey);
    }
}
