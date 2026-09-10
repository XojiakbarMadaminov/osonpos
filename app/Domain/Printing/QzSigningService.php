<?php

namespace App\Domain\Printing;

use RuntimeException;

class QzSigningService
{
    public function certificate(): string
    {
        $path = config('printing.qz.certificate_path');

        if (! is_string($path) || ! is_file($path)) {
            throw new RuntimeException('QZ certificate is not configured.');
        }

        return file_get_contents($path) ?: throw new RuntimeException('QZ certificate could not be read.');
    }

    public function sign(string $data): string
    {
        $path = config('printing.qz.private_key_path');

        if (! is_string($path) || ! is_file($path)) {
            throw new RuntimeException('QZ private key is not configured.');
        }

        $privateKey = openssl_pkey_get_private(
            file_get_contents($path),
            config('printing.qz.private_key_passphrase') ?: '',
        );

        if (! $privateKey || ! openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA512)) {
            throw new RuntimeException('QZ payload could not be signed.');
        }

        return base64_encode($signature);
    }
}
