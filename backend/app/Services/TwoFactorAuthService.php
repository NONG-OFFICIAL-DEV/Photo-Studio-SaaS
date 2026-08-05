<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * TOTP (Google Authenticator / Authy-compatible) two-factor authentication.
 * Scoped to is_super_admin accounts only — see TwoFactorAuthController and
 * AuthService::login() for where that's enforced. Recovery codes are
 * stored hashed (like passwords), never in plaintext after issuance.
 */
class TwoFactorAuthService
{
    protected const RECOVERY_CODE_COUNT = 8;

    public function __construct(protected Google2FA $google2fa)
    {
    }

    /**
     * Starts (or restarts) enrollment: generates a fresh secret and stores
     * it unconfirmed. Two-factor only takes effect once confirm() verifies
     * the user actually scanned it and can produce a valid code — this
     * step alone never enables anything.
     */
    public function generateSecret(User $user): array
    {
        $secret = $this->google2fa->generateSecretKey();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
        ])->save();

        $otpauthUrl = $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret,
        );

        return [
            'secret' => $secret,
            'otpauth_url' => $otpauthUrl,
            'qr_code_svg' => $this->renderQrCodeSvg($otpauthUrl),
        ];
    }

    /**
     * Confirms enrollment with a code from the authenticator app. Only
     * past this point does hasTwoFactorEnabled() become true. Recovery
     * codes are generated here and returned once in plaintext — the
     * caller must surface them to the user immediately, since only the
     * hashed form is kept afterward.
     */
    public function confirm(User $user, string $code): array
    {
        if (! $user->two_factor_secret) {
            throw new ApiException(422, 'Start two-factor setup before confirming a code.', 'TWO_FACTOR_NOT_STARTED');
        }

        if (! $this->google2fa->verifyKey($user->two_factor_secret, $code)) {
            throw new ApiException(422, 'The code you entered is incorrect.', 'INVALID_TWO_FACTOR_CODE');
        }

        $recoveryCodes = $this->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => array_map(fn (string $c) => Hash::make($c), $recoveryCodes),
        ])->save();

        return $recoveryCodes;
    }

    public function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
        ])->save();
    }

    public function verifyCode(User $user, string $code): bool
    {
        if (! $user->two_factor_secret) {
            return false;
        }

        if ($this->google2fa->verifyKey($user->two_factor_secret, $code)) {
            return true;
        }

        return $this->verifyRecoveryCode($user, $code);
    }

    /**
     * Recovery codes are single-use — a match consumes it immediately so
     * the same leaked code can't be replayed.
     */
    protected function verifyRecoveryCode(User $user, string $code): bool
    {
        $codes = $user->two_factor_recovery_codes ?? [];

        foreach ($codes as $index => $hashedCode) {
            if (Hash::check($code, $hashedCode)) {
                unset($codes[$index]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

                return true;
            }
        }

        return false;
    }

    protected function generateRecoveryCodes(): array
    {
        return array_map(
            fn () => Str::upper(Str::random(4)).'-'.Str::upper(Str::random(4)),
            range(1, self::RECOVERY_CODE_COUNT),
        );
    }

    protected function renderQrCodeSvg(string $otpauthUrl): string
    {
        $renderer = new ImageRenderer(new RendererStyle(200), new SvgImageBackEnd);

        return (new Writer($renderer))->writeString($otpauthUrl);
    }
}
