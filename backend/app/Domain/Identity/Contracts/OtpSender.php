<?php

namespace App\Domain\Identity\Contracts;

interface OtpSender
{
    public function send(string $mobile, string $code, string $locale): void;
}

