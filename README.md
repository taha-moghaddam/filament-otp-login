# Filament OTP Login

[![Latest Version on Packagist](https://img.shields.io/packagist/v/taha-moghaddam/filament-otp-login.svg?style=flat-square)](https://packagist.org/packages/taha-moghaddam/filament-otp-login)
[![Total Downloads](https://img.shields.io/packagist/dt/taha-moghaddam/filament-otp-login.svg?style=flat-square)](https://packagist.org/packages/taha-moghaddam/filament-otp-login)

OTP (One-Time Password) login for **Filament v5**: mobile/phone number login with OTP code, no password, no session (mobile passed in URL). Users can “register” by logging in with OTP for the first time.

## Features

- **Mobile + OTP only** – No password, no email. User enters mobile → receives OTP → enters code on next page.
- **No session for pending state** – Mobile number is passed in the URL (e.g. `?mobile=...`) to the OTP verification page.
- **Configurable request block** – `request_block_seconds`: time (in seconds) before the same mobile can request another OTP.
- **Same OTP resend** – Up to N times (configurable, default 3) the same code is resent; after that a new OTP is generated.
- **Pluggable OTP delivery** – Implement `OtpSenderInterface` (SMS, email, log, etc.) and set it in config.
- **Filament 5** – Built for Filament v5 panels.

## Requirements

- PHP 8.2+
- Laravel 11 or 12
- Filament 4 or 5
- Livewire 3 or 4

## Installation

```bash
composer require taha-moghaddam/filament-otp-login
```

### 1. User model and table

Your user model (e.g. `App\Models\AdminUser`) must have at least:

- A **mobile column** (name configurable, default: `mobile`) – unique, stored as string or bigInteger.
- **OTP fields**: `otp_code` (nullable int), `otp_expires_at` (nullable timestamp), `otp_sent_count` (int, default 0), `mobile_verified_at` (nullable timestamp).

Example migration for `admin_users`:

```php
Schema::create('admin_users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->unsignedBigInteger('mobile')->unique();
    $table->timestamp('mobile_verified_at')->nullable();
    $table->unsignedSmallInteger('otp_code')->nullable();
    $table->timestamp('otp_expires_at')->nullable();
    $table->unsignedTinyInteger('otp_sent_count')->default(0);
    $table->rememberToken();
    $table->timestamps();
});
```

### 2. Publish and run migrations (OTP logs table)

```bash
php artisan vendor:publish --tag="filament-otp-login-migrations"
php artisan migrate
```

### 3. Publish config (optional)

```bash
php artisan vendor:publish --tag="filament-otp-login-config"
```

Then edit `config/filament-otp-login.php`:

- **user_model** – Your user model class (e.g. `App\Models\AdminUser`).
- **otp_log.table** – Table name for OTP logs (default: `admin_otp_logs`).
- **otp_log.user_foreign_key** – Foreign key to users (default: `admin_user_id`).
- **mobile_column** – Column name for mobile number (default: `mobile`).
- **sender** – Class implementing `FilamentOtpLogin\Contracts\OtpSenderInterface` (default: `LogOtpSender` – logs to Laravel log).
- **otp_length**, **otp_expires_seconds**, **request_block_seconds**, **same_otp_max_sends** – Behaviour and limits.

### 4. Register the plugin on your panel

In your Filament panel provider (e.g. `AdminPanelProvider`):

```php
use FilamentOtpLogin\FilamentOtpLoginPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->id('admin')
        ->path('admin')
        ->authGuard('admin')  // use the guard that uses your user model
        ->plugins([
            FilamentOtpLoginPlugin::make(),
        ])
        // ... rest of panel config
        ;
}
```

Do **not** call `->login()` or `->registration()` on the panel; the plugin sets the login page and disables registration.

### 5. Auth guard (optional)

Ensure `config/auth.php` has a guard that uses your user model, e.g.:

```php
'guards' => [
    'admin' => [
        'driver' => 'session',
        'provider' => 'admin_users',
    ],
],
'providers' => [
    'admin_users' => [
        'driver' => 'eloquent',
        'model' => App\Models\AdminUser::class,
    ],
],
```

## Configuration (env)

| Key | Description | Default |
|-----|-------------|---------|
| `FILAMENT_OTP_LOGIN_USER_MODEL` | User model class | `App\Models\AdminUser` |
| `FILAMENT_OTP_LOGIN_OTP_LOG_TABLE` | OTP logs table name | `admin_otp_logs` |
| `FILAMENT_OTP_LOGIN_USER_FOREIGN_KEY` | FK column in OTP logs | `admin_user_id` |
| `FILAMENT_OTP_LOGIN_MOBILE_COLUMN` | Mobile column on user | `mobile` |
| `FILAMENT_OTP_LOGIN_SENDER` | OTP sender class | `FilamentOtpLogin\Services\LogOtpSender` |
| `FILAMENT_OTP_LOGIN_OTP_LENGTH` | OTP code length | `6` |
| `FILAMENT_OTP_LOGIN_OTP_EXPIRES_SECONDS` | OTP validity (seconds) | `120` |
| `FILAMENT_OTP_LOGIN_REQUEST_BLOCK_SECONDS` | Block before next request (seconds) | `60` |
| `FILAMENT_OTP_LOGIN_SAME_OTP_MAX_SENDS` | Same code resend limit | `3` |

## Custom OTP sender

Implement `FilamentOtpLogin\Contracts\OtpSenderInterface`:

```php
use FilamentOtpLogin\Contracts\OtpSenderInterface;
use Illuminate\Contracts\Auth\Authenticatable;

class MySmsOtpSender implements OtpSenderInterface
{
    public function send(Authenticatable $user, string $code): void
    {
        $mobile = $user->mobile; // or $user->{config('filament-otp-login.mobile_column')}
        // Send SMS to $mobile with $code
    }
}
```

Register it in config:

```php
'sender' => \App\Services\MySmsOtpSender::class,
```

## Translations

Publish and edit translations:

```bash
php artisan vendor:publish --tag="filament-otp-login-translations"
```

Keys are under `resources/lang/vendor/filament-otp-login/` (e.g. `en/filament-otp-login.php`). English and Persian (fa) are included in the package.

## Flow

1. User opens panel login → sees **mobile** field only.
2. Submits mobile → rate limit checked (`request_block_seconds`), user found or created, OTP generated or same code resent (up to `same_otp_max_sends`), OTP sent via `OtpSenderInterface`, redirect to **phone-verification-prompt?mobile=...**.
3. User enters OTP on verification page → code validated, user logged in, redirect to panel.

No session is used for the “pending” user; the mobile in the URL identifies the user on the OTP page.

## Security

- Use HTTPS in production.
- Rate limiting is applied per mobile (`request_block_seconds`).
- OTP logs table can be pruned (e.g. scheduled job) to avoid bloat.

## Changelog

See [Releases](https://github.com/taha-moghaddam/filament-otp-login/releases).

## License

MIT. See [LICENSE.md](LICENSE.md).

## Publish to Packagist and GitHub

1. Create a new repository on GitHub (e.g. `taha-moghaddam/filament-otp-login`).
2. Push the `filament-otp-login` folder to GitHub.
3. Register the package on [Packagist](https://packagist.org) and link your GitHub repo.
4. Submit to the [Filament plugin directory](https://filamentphp.com/plugins) so it appears on the Filament site.

## Filament

This plugin is built for [Filament](https://filamentphp.com) v5.
