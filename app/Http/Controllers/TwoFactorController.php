<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PragmaRX\Google2FA\Google2FA;
use App\Models\User;

class TwoFactorController extends Controller
{
    protected $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    /**
     * Show 2FA setup page (QR code + secret).
     *
     * GET /api/user/two-factor
     */
    public function show(Request $request)
    {
        $user = $request->user();

        if (! $user->two_factor_secret) {
            $secret = $this->google2fa->generateSecretKey();
            $user->two_factor_secret = $secret;
            $user->save();
        }

        $qrCodeUrl = $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $user->two_factor_secret
        );

        return response()->json([
            'data' => [
                'secret' => $user->two_factor_secret,
                'qr_code_url' => $qrCodeUrl,
                'enabled' => $user->two_factor_enabled,
                'recovery_codes' => $user->two_factor_recovery_codes
                    ? json_decode($user->two_factor_recovery_codes, true)
                    : null,
            ],
        ]);
    }

    /**
     * Enable 2FA after verifying the first code.
     *
     * POST /api/user/two-factor/enable
     */
    public function enable(Request $request)
    {
        $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $user = $request->user();

        if (! $this->google2fa->verifyKey($user->two_factor_secret, $request->code)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => '422', 'message' => 'Неверный код подтверждения'],
            ], 422);
        }

        $recoveryCodes = collect(range(1, 10))->map(fn () => $this->generateRecoveryCode())->toArray();

        $user->update([
            'two_factor_enabled' => true,
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => json_encode($recoveryCodes),
        ]);

        return response()->json([
            'data' => [
                'enabled' => true,
                'recovery_codes' => $recoveryCodes,
            ],
        ]);
    }

    /**
     * Disable 2FA.
     *
     * POST /api/user/two-factor/disable
     */
    public function disable(Request $request)
    {
        $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $user = $request->user();

        if (! $this->google2fa->verifyKey($user->two_factor_secret, $request->code)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => '422', 'message' => 'Неверный код подтверждения'],
            ], 422);
        }

        $user->update([
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
        ]);

        return response()->json([
            'data' => ['enabled' => false],
        ]);
    }

    /**
     * Verify 2FA code during login.
     *
     * POST /api/two-factor/verify
     */
    public function verify(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! $user->two_factor_enabled) {
            return response()->json([
                'success' => false,
                'error' => ['code' => '404', 'message' => '2FA не включено'],
            ], 404);
        }

        if ($this->google2fa->verifyKey($user->two_factor_secret, $request->code)) {
            return response()->json([
                'data' => ['verified' => true],
            ]);
        }

        // Check recovery codes
        if ($user->two_factor_recovery_codes) {
            $codes = json_decode($user->two_factor_recovery_codes, true);
            if (in_array($request->code, $codes)) {
                $codes = array_diff($codes, [$request->code]);
                $user->two_factor_recovery_codes = json_encode(array_values($codes));
                $user->save();

                return response()->json([
                    'data' => ['verified' => true, 'used_recovery_code' => true],
                ]);
            }
        }

        return response()->json([
            'success' => false,
            'error' => ['code' => '422', 'message' => 'Неверный код'],
        ], 422);
    }

    /**
     * Regenerate recovery codes.
     *
     * POST /api/user/two-factor/regenerate-codes
     */
    public function regenerateCodes(Request $request)
    {
        $user = $request->user();

        if (! $user->two_factor_enabled) {
            return response()->json([
                'success' => false,
                'error' => ['code' => '400', 'message' => '2FA не включено'],
            ], 400);
        }

        $recoveryCodes = collect(range(1, 10))->map(fn () => $this->generateRecoveryCode())->toArray();

        $user->update([
            'two_factor_recovery_codes' => json_encode($recoveryCodes),
        ]);

        return response()->json([
            'data' => ['recovery_codes' => $recoveryCodes],
        ]);
    }

    private function generateRecoveryCode(): string
    {
        return strtoupper(substr(md5(random_bytes(16)), 0, 8));
    }
}
