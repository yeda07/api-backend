<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        protected TwoFactorService $twoFactorService
    ) {
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
            'two_factor_code' => 'nullable|string',
            'recovery_code' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation error', 422, $validator->errors()->toArray());
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return $this->invalidCredentialsResponse();
        }

        if ($user->isLocked()) {
            return $this->errorResponse('Cuenta bloqueada temporalmente', 423, [
                'auth' => ['Demasiados intentos fallidos. Intenta mas tarde.'],
            ], [
                'locked_until' => $user->locked_until?->toISOString(),
            ]);
        }

        if (!Hash::check($request->password, $user->password)) {
            $this->registerFailedAttempt($user);
            return $this->invalidCredentialsResponse();
        }

        if (!$user->hasTwoFactorEnabled()) {
            $setupToken = $this->createSetupToken($user);

            return $this->successResponse([
                'requires_two_factor_setup' => true,
                'token' => $setupToken,
                'user' => $this->serializeUser($user),
            ], 200, 'Debes configurar 2FA antes de acceder');
        }

        $code = $request->input('two_factor_code');
        $recoveryCode = $request->input('recovery_code');

        if (!$code && !$recoveryCode) {
            return $this->errorResponse('Validation error', 422, [
                'two_factor_code' => ['El codigo 2FA es obligatorio'],
            ]);
        }

        $twoFactorValidated = $code
            ? $this->twoFactorService->verifyCode($user->two_factor_secret, $code)
            : $this->twoFactorService->consumeRecoveryCode($user, (string) $recoveryCode);

        if (!$twoFactorValidated) {
            $this->registerFailedAttempt($user);

            return $this->errorResponse('Codigo 2FA invalido', 422, [
                'two_factor_code' => ['El codigo 2FA o recovery code es invalido'],
            ]);
        }

        $this->clearSecurityCounters($user, $request->ip());
        $token = $this->createFullAccessToken($user);

        return $this->successResponse([
            'token' => $token,
            'user' => $this->serializeUser($user),
        ]);
    }

    public function me(Request $request)
    {
        return $this->successResponse($this->serializeUser($request->user()));
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return $this->successResponse(null, 200, 'Sesion cerrada correctamente');
    }

    public function forgotPassword(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        $status = Password::sendResetLink([
            'email' => $validated['email'],
        ]);

        if ($status !== Password::RESET_LINK_SENT) {
            return $this->errorResponse('No fue posible iniciar la recuperacion de contraseña', 422, [
                'email' => [__($status)],
            ]);
        }

        return $this->successResponse(null, 200, 'Enlace de recuperacion enviado');
    }

    public function resetPassword(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $status = Password::reset(
            $validated,
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'failed_login_attempts' => 0,
                    'locked_until' => null,
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return $this->errorResponse('No fue posible restablecer la contraseña', 422, [
                'token' => [__($status)],
            ]);
        }

        return $this->successResponse(null, 200, 'Contraseña restablecida correctamente');
    }

    public function twoFactorSetup(Request $request)
    {
        $user = $request->user();
        $token = $user->currentAccessToken();

        if (!$token || (!$token->can('*') && !$token->can('2fa:setup'))) {
            return $this->errorResponse('No autorizado para configurar 2FA', 403, [
                'two_factor' => ['El token actual no permite configurar 2FA'],
            ]);
        }

        if ($user->hasTwoFactorEnabled()) {
            return $this->successResponse([
                'already_enabled' => true,
                'user' => $this->serializeUser($user),
            ], 200, '2FA ya esta configurado');
        }

        $secret = $user->two_factor_secret ?: $this->twoFactorService->generateSecret();

        $user->forceFill([
            'two_factor_secret' => $secret,
        ])->save();

        return $this->successResponse([
            'secret' => $secret,
            'otpauth_url' => $this->twoFactorService->otpAuthUrl($user, $secret),
            'user' => $this->serializeUser($user),
        ], 200, 'Escanea el codigo con tu autenticador');
    }

    public function confirmTwoFactor(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string',
        ]);

        $user = $request->user();
        $token = $user->currentAccessToken();

        if (!$token || (!$token->can('*') && !$token->can('2fa:setup'))) {
            return $this->errorResponse('No autorizado para confirmar 2FA', 403, [
                'two_factor' => ['El token actual no permite confirmar 2FA'],
            ]);
        }

        if (empty($user->two_factor_secret)) {
            return $this->errorResponse('Primero debes generar un secreto 2FA', 422, [
                'two_factor' => ['No existe un secreto 2FA pendiente de confirmacion'],
            ]);
        }

        if (!$this->twoFactorService->verifyCode($user->two_factor_secret, $validated['code'])) {
            return $this->errorResponse('Validation error', 422, [
                'code' => ['Codigo 2FA invalido'],
            ]);
        }

        $recoveryCodes = $this->twoFactorService->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $recoveryCodes['hashed'],
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        $token->delete();
        $fullAccessToken = $this->createFullAccessToken($user);

        return $this->successResponse([
            'token' => $fullAccessToken,
            'recovery_codes' => $recoveryCodes['plain'],
            'user' => $this->serializeUser($user),
        ], 200, '2FA configurado correctamente');
    }

    public function regenerateRecoveryCodes(Request $request)
    {
        $user = $request->user();

        if (!$user->hasTwoFactorEnabled()) {
            return $this->errorResponse('2FA no esta habilitado', 422, [
                'two_factor' => ['Debes tener 2FA habilitado para regenerar recovery codes'],
            ]);
        }

        $recoveryCodes = $this->twoFactorService->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_recovery_codes' => $recoveryCodes['hashed'],
        ])->save();

        return $this->successResponse([
            'recovery_codes' => $recoveryCodes['plain'],
        ], 200, 'Recovery codes regenerados');
    }

    private function serializeUser(User $user): array
    {
        return [
            'uid' => $user->uid,
            'name' => $user->name,
            'email' => $user->email,
            'tenant_uid' => $user->tenant?->uid,
            'two_factor_enabled' => $user->hasTwoFactorEnabled(),
            'locked_until' => $user->locked_until?->toISOString(),
        ];
    }

    private function createSetupToken(User $user): string
    {
        return $user->createToken(
            '2fa-setup',
            $this->tenantAbilities($user, ['2fa:setup']),
            now()->addMinutes(10)
        )->plainTextToken;
    }

    private function createFullAccessToken(User $user): string
    {
        return $user->createToken(
            'api-token',
            $this->tenantAbilities($user, ['access:full']),
            now()->addHours(12)
        )->plainTextToken;
    }

    private function tenantAbilities(User $user, array $abilities): array
    {
        return array_merge($abilities, [
            'tenant:' . $user->tenant?->uid,
        ]);
    }

    private function invalidCredentialsResponse()
    {
        return $this->errorResponse('Credenciales incorrectas', 401, [
            'credentials' => ['Credenciales incorrectas'],
        ]);
    }

    private function registerFailedAttempt(User $user): void
    {
        $attempts = (int) $user->failed_login_attempts + 1;
        $payload = [
            'failed_login_attempts' => $attempts,
        ];

        if ($attempts >= 5) {
            $payload['locked_until'] = now()->addMinutes(15);
            $payload['failed_login_attempts'] = 0;
        }

        $user->forceFill($payload)->save();
    }

    private function clearSecurityCounters(User $user, ?string $ip): void
    {
        $user->forceFill([
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'last_login_at' => now(),
            'last_login_ip' => $ip,
        ])->save();
    }
}
