<?php

namespace FilamentOtpLogin;

use FilamentOtpLogin\Contracts\OtpSenderInterface;
use Illuminate\Support\ServiceProvider;

class FilamentOtpLoginServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/filament-otp-login.php',
            'filament-otp-login'
        );

        $this->app->bind(
            OtpSenderInterface::class,
            config('filament-otp-login.sender', Services\LogOtpSender::class)
        );
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'filament-otp-login');
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'filament-otp-login');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/filament-otp-login.php' => config_path('filament-otp-login.php'),
            ], 'filament-otp-login-config');

            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'filament-otp-login-migrations');

            $this->publishes([
                __DIR__ . '/../resources/lang' => lang_path('vendor/filament-otp-login'),
            ], 'filament-otp-login-translations');
        }
    }
}
