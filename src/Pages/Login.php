<?php

namespace FilamentOtpLogin\Pages;

use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use FilamentOtpLogin\Contracts\OtpSenderInterface;
use FilamentOtpLogin\Models\OtpLog;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

class Login extends \Filament\Auth\Pages\Login
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getMobileFormComponent(),
            ]);
    }

    protected function getMobileFormComponent(): Component
    {
        return TextInput::make('mobile')
            ->label(__('filament-otp-login::filament-otp-login.mobile_label'))
            ->numeric()
            ->required()
            ->minLength(10)
            ->maxLength(15)
            ->autocomplete('tel')
            ->autofocus();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        return [
            'mobile' => $data['mobile'],
        ];
    }

    public function authenticate(): ?LoginResponse
    {
        $data = $this->form->getState();
        $mobileColumn = config('filament-otp-login.mobile_column', 'mobile');
        $mobile = (string) ($data['mobile'] ?? '');
        $mobile = preg_replace('/\D/', '', $mobile) ?: $mobile;

        if (blank($mobile)) {
            throw ValidationException::withMessages([
                'data.mobile' => __('filament-otp-login::filament-otp-login.mobile_required'),
            ]);
        }

        $blockSeconds = config('filament-otp-login.request_block_seconds', 60);
        $cachePrefix = config('filament-otp-login.cache_prefix', 'filament_otp_send:');
        $cacheKey = $cachePrefix . $mobile;
        if (Cache::has($cacheKey)) {
            $secondsLeft = (int) Cache::get($cacheKey . '_until', 0) - time();
            if ($secondsLeft > 0) {
                Notification::make()
                    ->title(__('filament-otp-login::filament-otp-login.wait_seconds', ['seconds' => $secondsLeft]))
                    ->danger()
                    ->send();

                return null;
            }
        }

        $userModel = config('filament-otp-login.user_model');
        $user = $userModel::firstOrCreate(
            [$mobileColumn => $mobile],
            ['name' => 'User ' . substr($mobile, -4)]
        );

        $maxSends = config('filament-otp-login.same_otp_max_sends', 3);
        $length = config('filament-otp-login.otp_length', 6);
        $expiresSeconds = config('filament-otp-login.otp_expires_seconds', 120);
        $expiresAt = now()->addSeconds($expiresSeconds);

        $resendSameOtp = $user->otp_code !== null
            && $user->otp_expires_at
            && $user->otp_expires_at->isFuture()
            && (int) ($user->otp_sent_count ?? 0) < $maxSends;

        if ($resendSameOtp) {
            $user->otp_sent_count = (int) ($user->otp_sent_count ?? 0) + 1;
            $user->save();
            $code = (string) $user->otp_code;
        } else {
            $code = str_pad((string) random_int(0, (int) str_repeat('9', $length)), $length, '0', STR_PAD_LEFT);
            $user->otp_code = (int) $code;
            $user->otp_expires_at = $expiresAt;
            $user->otp_sent_count = 1;
            $user->save();
        }

        $fk = config('filament-otp-login.otp_log.user_foreign_key', 'admin_user_id');
        OtpLog::create([
            $fk => $user->getKey(),
            'otp_code' => (int) $code,
            'ip' => request()->ip() ?? '0.0.0.0',
            'is_used' => false,
        ]);

        Cache::put($cacheKey, true, $blockSeconds);
        Cache::put($cacheKey . '_until', time() + $blockSeconds, $blockSeconds + 10);

        app(OtpSenderInterface::class)->send($user, $code);

        Notification::make()
            ->title(__('filament-otp-login::filament-otp-login.code_sent'))
            ->success()
            ->send();

        $routeName = 'filament.' . Filament::getCurrentPanel()->getId() . '.auth.phone-verification-prompt';
        $this->redirect(route($routeName, ['mobile' => $mobile]), navigate: false);

        return null;
    }

    public function getSubheading(): \Illuminate\Contracts\Support\Htmlable|string|null
    {
        return null;
    }
}
