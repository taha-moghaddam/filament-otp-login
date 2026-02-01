<?php

namespace FilamentOtpLogin\Pages;

use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\SimplePage;
use FilamentOtpLogin\Contracts\OtpSenderInterface;
use FilamentOtpLogin\Models\OtpLog;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

/**
 * @property-read Schema $form
 */
class PhoneVerificationPrompt extends SimplePage
{
    protected string $view = 'filament-otp-login::phone-verification-prompt';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    #[Locked]
    public ?string $mobile = null;

    public function mount(): void
    {
        if (Filament::auth()->check()) {
            redirect()->intended(Filament::getUrl());

            return;
        }

        $this->mobile = request()->query('mobile');
        if (blank($this->mobile)) {
            $this->redirect(Filament::getCurrentPanel()->getLoginUrl(), navigate: false);

            return;
        }

        $this->mobile = (string) preg_replace('/\D/', '', $this->mobile) ?: $this->mobile;
        if (blank($this->mobile)) {
            $this->redirect(Filament::getCurrentPanel()->getLoginUrl(), navigate: false);

            return;
        }

        $userModel = config('filament-otp-login.user_model');
        $mobileColumn = config('filament-otp-login.mobile_column', 'mobile');
        $user = $userModel::where($mobileColumn, $this->mobile)->first();
        if (! $user || ! $user->otp_code || ! $user->otp_expires_at || $user->otp_expires_at->isPast()) {
            $this->redirect(Filament::getCurrentPanel()->getLoginUrl(), navigate: false);

            return;
        }

        $this->form->fill();
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getOtpFormComponent(),
            ]);
    }

    protected function getOtpFormComponent(): Component
    {
        $length = config('filament-otp-login.otp_length', 6);

        return TextInput::make('otp')
            ->label(__('filament-otp-login::filament-otp-login.otp_label'))
            ->numeric()
            ->required()
            ->length($length)
            ->maxLength($length)
            ->autocomplete('one-time-code')
            ->autofocus();
    }

    public function verifyOtp(): \Illuminate\Http\RedirectResponse|LoginResponse|null
    {
        $data = $this->form->getState();
        $code = (string) ($data['otp'] ?? '');
        $userModel = config('filament-otp-login.user_model');
        $mobileColumn = config('filament-otp-login.mobile_column', 'mobile');
        $user = $userModel::where($mobileColumn, $this->mobile)->first();

        if (! $user) {
            $this->redirect(Filament::getCurrentPanel()->getLoginUrl(), navigate: false);

            return null;
        }

        if ((string) $user->otp_code !== $code || ! $user->otp_expires_at || $user->otp_expires_at->isPast()) {
            throw ValidationException::withMessages([
                'data.otp' => __('filament-otp-login::filament-otp-login.invalid_code'),
            ]);
        }

        $fk = config('filament-otp-login.otp_log.user_foreign_key', 'admin_user_id');
        OtpLog::where($fk, $user->getKey())
            ->where('otp_code', (int) $code)
            ->where('is_used', false)
            ->update(['is_used' => true]);

        $user->otp_code = null;
        $user->otp_expires_at = null;
        $user->otp_sent_count = 0;
        $user->mobile_verified_at = $user->mobile_verified_at ?? now();
        $user->save();

        Filament::auth()->login($user);
        session()->regenerate();

        return app(LoginResponse::class);
    }

    public function resendOtp(): void
    {
        $blockSeconds = config('filament-otp-login.request_block_seconds', 60);
        $cachePrefix = config('filament-otp-login.cache_prefix', 'filament_otp_send:');
        $cacheKey = $cachePrefix . $this->mobile;
        if (Cache::has($cacheKey)) {
            $secondsLeft = (int) Cache::get($cacheKey . '_until', 0) - time();
            if ($secondsLeft > 0) {
                Notification::make()
                    ->title(__('filament-otp-login::filament-otp-login.wait_seconds', ['seconds' => $secondsLeft]))
                    ->danger()
                    ->send();

                return;
            }
        }

        $userModel = config('filament-otp-login.user_model');
        $mobileColumn = config('filament-otp-login.mobile_column', 'mobile');
        $user = $userModel::where($mobileColumn, $this->mobile)->first();
        if (! $user) {
            $this->redirect(Filament::getCurrentPanel()->getLoginUrl(), navigate: false);

            return;
        }

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
            ->title(__('filament-otp-login::filament-otp-login.code_resent'))
            ->success()
            ->send();

        $this->redirect(route('filament.' . Filament::getCurrentPanel()->getId() . '.auth.phone-verification-prompt', ['mobile' => $this->mobile]), navigate: false);
    }

    /**
     * @return array<\Filament\Actions\Action|\Filament\Actions\ActionGroup>
     */
    protected function getFormActions(): array
    {
        return [
            $this->getVerifyFormAction(),
            $this->getResendAction(),
        ];
    }

    protected function getVerifyFormAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('verifyOtp')
            ->label(__('filament-otp-login::filament-otp-login.verify'))
            ->submit('verifyOtp');
    }

    protected function getResendAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('resendOtp')
            ->link()
            ->label(__('filament-otp-login::filament-otp-login.resend_code'))
            ->action('resendOtp');
    }

    protected function hasFullWidthFormActions(): bool
    {
        return true;
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getFormContentComponent(),
            ]);
    }

    public function getFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler('verifyOtp')
            ->footer([
                Actions::make($this->getFormActions())
                    ->alignment(\Filament\Support\Enums\Alignment::Start)
                    ->fullWidth($this->hasFullWidthFormActions())
                    ->key('form-actions'),
            ]);
    }

    public function getTitle(): string|Htmlable
    {
        return __('filament-otp-login::filament-otp-login.verify_title');
    }

    public function getHeading(): string|Htmlable|null
    {
        return __('filament-otp-login::filament-otp-login.verify_heading');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return $this->mobile
            ? __('filament-otp-login::filament-otp-login.sent_to', ['mobile' => $this->mobile])
            : null;
    }
}
