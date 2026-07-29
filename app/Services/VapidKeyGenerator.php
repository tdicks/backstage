<?php

namespace App\Services;

use Minishlink\WebPush\VAPID;
use RuntimeException;
use Throwable;

class VapidKeyGenerator
{
    /**
     * @return array{publicKey: string, privateKey: string}
     */
    public function generate(): array
    {
        $this->ensureOpenSslConfigPath();

        try {
            return VAPID::createVapidKeys();
        } catch (Throwable $exception) {
            return $this->generateWithOpenSsl($exception);
        }
    }

    /**
     * @return array{publicKey: string, privateKey: string}
     */
    private function generateWithOpenSsl(Throwable $firstFailure): array
    {
        $key = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
        ]);

        if ($key === false) {
            throw new RuntimeException(
                'Unable to create VAPID keys. Library error: '.$firstFailure->getMessage().'. OpenSSL error: '.$this->latestOpenSslError()
            );
        }

        $details = openssl_pkey_get_details($key);

        if (! is_array($details) || ! isset($details['ec']['x'], $details['ec']['y'], $details['ec']['d'])) {
            throw new RuntimeException(
                'Unable to derive VAPID key details from OpenSSL key resource.'
            );
        }

        $x = $this->normalizeCoordinate($details['ec']['x']);
        $y = $this->normalizeCoordinate($details['ec']['y']);
        $d = $this->normalizeCoordinate($details['ec']['d']);

        $publicKey = $this->base64UrlEncode(chr(4).$x.$y);
        $privateKey = $this->base64UrlEncode($d);

        return [
            'publicKey' => $publicKey,
            'privateKey' => $privateKey,
        ];
    }

    private function normalizeCoordinate(string $coordinate): string
    {
        if (strlen($coordinate) > 32) {
            $coordinate = substr($coordinate, -32);
        }

        return str_pad($coordinate, 32, chr(0), STR_PAD_LEFT);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function latestOpenSslError(): string
    {
        $lastError = null;

        while ($error = openssl_error_string()) {
            $lastError = $error;
        }

        return $lastError ?? 'Unknown OpenSSL error.';
    }

    private function ensureOpenSslConfigPath(): void
    {
        $currentConfig = getenv('OPENSSL_CONF');

        if (is_string($currentConfig) && $currentConfig !== '' && is_file($currentConfig) && $this->canCreateEcKey()) {
            return;
        }

        $phpBinaryDirectory = dirname(PHP_BINARY);

        $candidates = [
            $phpBinaryDirectory.DIRECTORY_SEPARATOR.'extras'.DIRECTORY_SEPARATOR.'ssl'.DIRECTORY_SEPARATOR.'openssl.cnf',
            $phpBinaryDirectory.DIRECTORY_SEPARATOR.'openssl.cnf',
            PHP_BINDIR.DIRECTORY_SEPARATOR.'extras'.DIRECTORY_SEPARATOR.'ssl'.DIRECTORY_SEPARATOR.'openssl.cnf',
            PHP_BINDIR.DIRECTORY_SEPARATOR.'openssl.cnf',
        ];

        foreach ($candidates as $candidate) {
            if (! is_file($candidate)) {
                continue;
            }

            $this->applyOpenSslConfig($candidate);

            if ($this->canCreateEcKey()) {
                return;
            }
        }

        $temporaryConfigPath = storage_path('framework/cache/openssl-vapid.cnf');

        if (! is_file($temporaryConfigPath)) {
            file_put_contents($temporaryConfigPath, <<<'CNF'
openssl_conf = openssl_init

[openssl_init]
providers = provider_sect

[provider_sect]
default = default_sect

[default_sect]
activate = 1
CNF
            );
        }

        $this->applyOpenSslConfig($temporaryConfigPath);

        if ($this->canCreateEcKey()) {
            return;
        }

        throw new RuntimeException('OpenSSL configuration file is invalid or unavailable. Set OPENSSL_CONF to a working openssl.cnf path and try again. Last OpenSSL error: '.$this->latestOpenSslError());
    }

    private function applyOpenSslConfig(string $path): void
    {
        putenv('OPENSSL_CONF='.$path);
        $_ENV['OPENSSL_CONF'] = $path;
        $_SERVER['OPENSSL_CONF'] = $path;
    }

    private function canCreateEcKey(): bool
    {
        $key = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
        ]);

        return $key !== false;
    }
}
