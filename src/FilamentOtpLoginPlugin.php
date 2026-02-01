<?php

namespace FilamentOtpLogin;

use Filament\Contracts\Plugin;
use Filament\Panel;
use FilamentOtpLogin\Pages\Login;
use FilamentOtpLogin\Pages\PhoneVerificationPrompt;
use Illuminate\Support\Facades\Route;

class FilamentOtpLoginPlugin implements Plugin
{
    public function getId(): string
    {
        return 'filament-otp-login';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->login(Login::class)
            ->registration(false);

        $panel->routes(function (Panel $panel): void {
            Route::name('auth.')->group(function () use ($panel): void {
                Route::get('phone-verification-prompt', PhoneVerificationPrompt::class)
                    ->name('phone-verification-prompt');
            });
        });
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public static function make(): static
    {
        return app(static::class);
    }
}
