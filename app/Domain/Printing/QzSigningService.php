<?php

namespace App\Domain\Printing;

use RuntimeException;

class QzSigningService
{
    public function certificate(): string
    {
        $path = config('printing.qz.certificate_path');

        if (! is_string($path) || ! is_file($path)) {
            throw new RuntimeException('QZ sertifikati sozlanmagan.');
        }

        return file_get_contents($path) ?: throw new RuntimeException('QZ sertifikatini o‘qib bo‘lmadi.');
    }

    public function sign(string $data): string
    {
        $path = config('printing.qz.private_key_path');

        if (! is_string($path) || ! is_file($path)) {
            throw new RuntimeException('QZ maxfiy kaliti sozlanmagan.');
        }

        $privateKey = openssl_pkey_get_private(
            file_get_contents($path),
            config('printing.qz.private_key_passphrase') ?: '',
        );

        if (! $privateKey || ! openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA512)) {
            throw new RuntimeException('QZ ma’lumotlarini imzolab bo‘lmadi.');
        }

        return base64_encode($signature);
    }
}
