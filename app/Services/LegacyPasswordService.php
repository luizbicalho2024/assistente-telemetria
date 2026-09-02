<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class LegacyPasswordService
{
    public function verifyAndUpgrade(User $user, string $plain): bool
    {
        foreach ([
            $user->password ?? null,
            $user->hashed_password ?? null,
            $user->password_hash ?? null,
        ] as $encoded) {
            if (!$encoded) {
                continue;
            }

            if ($this->verify($plain, (string) $encoded)) {
                $this->upgrade($user, $plain);
                return true;
            }
        }

        return false;
    }

    public function verify(string $plain, string $encoded): bool
    {
        if ($encoded === '') {
            return false;
        }

        if (str_starts_with($encoded, 'pbkdf2_sha256$')) {
            return $this->verifyPbkdf2($plain, $encoded);
        }

        try {
            if (Hash::check($plain, $encoded)) {
                return true;
            }
        } catch (\Throwable) {
            // Continua para password_verify, compatível com bcrypt legado.
        }

        return password_verify($plain, $encoded);
    }

    private function verifyPbkdf2(string $plain, string $encoded): bool
    {
        $parts = explode('$', $encoded, 4);
        if (count($parts) !== 4 || $parts[0] !== 'pbkdf2_sha256') {
            return false;
        }

        $iterations = (int) $parts[1];
        $salt = base64_decode(strtr($parts[2], '-_', '+/'), true);
        $expected = base64_decode(strtr($parts[3], '-_', '+/'), true);

        if ($iterations < 1 || $salt === false || $expected === false) {
            return false;
        }

        $candidate = hash_pbkdf2('sha256', $plain, $salt, $iterations, strlen($expected), true);
        return hash_equals($expected, $candidate);
    }

    private function upgrade(User $user, string $plain): void
    {
        $user->forceFill([
            'password' => Hash::make($plain),
            'hashed_password' => null,
            'password_hash' => null,
            'password_migrated_at' => now(),
            'active' => true,
            'disabled' => false,
        ])->save();
    }
}
