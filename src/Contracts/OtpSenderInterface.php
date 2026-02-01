<?php

namespace FilamentOtpLogin\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface OtpSenderInterface
{
    public function send(Authenticatable $user, string $code): void;
}
