<?php

namespace FilamentOtpLogin\Services;

use FilamentOtpLogin\Contracts\OtpSenderInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;

class LogOtpSender implements OtpSenderInterface
{
    public function send(Authenticatable $user, string $code): void
    {
        $mobileColumn = config('filament-otp-login.mobile_column', 'mobile');
        $mobile = $user->{$mobileColumn} ?? $user->getAuthIdentifier();

        Log::info('Filament OTP sent', [
            'user_id' => $user->getAuthIdentifier(),
            'mobile' => $mobile,
            'code' => $code,
        ]);
    }
}
