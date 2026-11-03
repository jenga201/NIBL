<?php

namespace App\Domain;

use Illuminate\Support\Facades\Cookie;

enum FuzzySearch: string
{
    case Off = 'off';
    case On = 'on';

    /** Shared with the React client (resources/js/features/packlist/cookies.ts). */
    public const COOKIE = 'nibl_fuzzy';

    public function isOn(): bool
    {
        return $this === self::On;
    }

    public static function fromCookie(?string $value): self
    {
        return $value === '0' ? self::Off : self::On;
    }

    public static function fromBool(bool $fuzzy): self
    {
        return $fuzzy ? self::On : self::Off;
    }

    public function cookieValue(): string
    {
        return $this->isOn() ? '1' : '0';
    }

    /**
     * Persist the preference. httpOnly is disabled so the React client can also
     * read and write the same cookie via document.cookie.
     */
    public function cookie(): \Symfony\Component\HttpFoundation\Cookie
    {
        return Cookie::forever(self::COOKIE, $this->cookieValue(), '/', null, null, false, false, 'lax');
    }
}
