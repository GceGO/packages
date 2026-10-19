<?php

declare(strict_types=1);

namespace Gcego\Settings\Security;

use Gcego\Settings\Exceptions\InvalidSettingException;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\StringEncrypter;

/**
 * Laravel's encrypter (APP_KEY, the configured cipher, and
 * APP_PREVIOUS_KEYS for key rotation): authenticated encryption, so a
 * tampered payload fails to decrypt instead of yielding a value.
 * Sensitive payloads are encrypted before they are written and decrypted
 * only when trusted server code reads the effective value; screens,
 * exports, history, events and caches only ever see the ciphertext or nothing.
 */
final readonly class SettingEncryption
{
    public function __construct(private StringEncrypter $encrypter) {}

    public function encrypt(string $payload): string
    {
        return $this->encrypter->encryptString($payload);
    }

    /**
     * @throws InvalidSettingException when the payload was tampered with or encrypted with an unknown key
     */
    public function decrypt(string $ciphertext): string
    {
        try {
            return $this->encrypter->decryptString($ciphertext);
        } catch (DecryptException) {
            throw InvalidSettingException::cast('decrypt');
        }
    }

    /**
     * Whether encryption works with the current key (diagnostics).
     */
    public function available(): bool
    {
        try {
            return $this->decrypt($this->encrypt('gcego-settings')) === 'gcego-settings';
        } catch (\Throwable) {
            return false;
        }
    }
}
