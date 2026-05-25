<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PendingRegistration;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthService
{
    private const VERIFY_BASE_URL = 'https://api.verify.mn';

    public function requestRegisterOtp(array $payload): array
    {
        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        $phone = trim((string) ($payload['phone'] ?? ''));

        if ($email !== '' && User::query()->where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => ['Email already exists.'],
            ]);
        }

        if ($phone !== '' && User::query()->where('phone', $phone)->exists()) {
            throw ValidationException::withMessages([
                'phone' => ['Phone already exists.'],
            ]);
        }

        $ttl = max(60, (int) env('OTP_TTL_SECONDS', 180));
        $otp = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $verifySession = $this->createVerifySession($phone, $otp);
        $verifySessionId = (string) ($verifySession['sessionId'] ?? '');
        $expiresAt = $this->resolveVerifyExpiry($verifySession, $ttl);
        $displayInstruction = trim((string) ($verifySession['displayInstruction'] ?? ''));

        $pending = PendingRegistration::query()->create([
            'first_name' => trim((string) ($payload['first_name'] ?? $payload['firstName'] ?? 'User')),
            'last_name' => trim((string) ($payload['last_name'] ?? $payload['lastName'] ?? '')),
            'phone' => $phone !== '' ? $phone : null,
            'email' => $email !== '' ? $email : null,
            'password_hash' => Hash::make((string) ($payload['password'] ?? '')),
            'role' => (string) ($payload['role'] ?? 'user'),
            'otp' => $otp,
            'otp_expires_at' => $expiresAt,
            'verify_session_id' => $verifySessionId,
            'verify_callback_status' => 'pending',
            'verify_instruction' => $displayInstruction !== '' ? $displayInstruction : 'verify-mn-pending',
            'status' => 'pending',
        ]);

        return [
            'registrationId' => (string) $pending->id,
            'sessionId' => $verifySessionId,
            'expiresAt' => optional($pending->otp_expires_at)->toISOString(),
            'displayInstruction' => $pending->verify_instruction,
            'smsUri' => $verifySession['smsUri'] ?? null,
        ];
    }

    public function registerWithEmail(array $payload): array
    {
        $name = trim((string) ($payload['name'] ?? 'User'));
        $parts = preg_split('/\s+/', $name) ?: [];
        $first = trim((string) ($parts[0] ?? 'User'));
        $last = trim((string) implode(' ', array_slice($parts, 1)));

        return $this->register([
            'first_name' => $first,
            'last_name' => $last,
            'email' => strtolower(trim((string) ($payload['email'] ?? ''))),
            'password' => (string) ($payload['password'] ?? ''),
            'role' => 'user',
        ]);
    }

    public function verifyRegisterOtp(array $payload): array
    {
        $registrationId = (string) ($payload['registrationId'] ?? '');
        $otp = trim((string) ($payload['otp'] ?? ''));

        /** @var PendingRegistration|null $pending */
        $pending = PendingRegistration::query()->find($registrationId);
        if (!$pending) {
            throw ValidationException::withMessages([
                'registrationId' => ['Registration not found.'],
            ]);
        }

        if ($pending->status === 'completed') {
            $user = User::query()
                ->where('email', $pending->email)
                ->orWhere('phone', $pending->phone)
                ->first();

            if ($user) {
                $token = $user->createToken('api')->plainTextToken;
                return [
                    'token' => $token,
                    'user' => $user->toArray(),
                ];
            }
        }

        if ($pending->otp_expires_at instanceof Carbon && $pending->otp_expires_at->isPast()) {
            $pending->status = 'expired';
            $pending->save();

            throw ValidationException::withMessages([
                'otp' => ['OTP хугацаа дууссан байна'],
            ]);
        }

        if ($otp === '' && $pending->verify_callback_status !== 'completed') {
            throw ValidationException::withMessages([
                'otp' => ['Баталгаажуулалтын callback ирээгүй байна.'],
            ]);
        }

        if ($otp !== '' && $pending->otp !== $otp) {
            throw ValidationException::withMessages([
                'otp' => ['OTP буруу байна'],
            ]);
        }

        if ($pending->email && User::query()->where('email', $pending->email)->exists()) {
            throw ValidationException::withMessages([
                'email' => ['Email already exists.'],
            ]);
        }

        if ($pending->phone && User::query()->where('phone', $pending->phone)->exists()) {
            throw ValidationException::withMessages([
                'phone' => ['Phone already exists.'],
            ]);
        }

        $user = User::query()->create([
            'first_name' => (string) $pending->first_name,
            'last_name' => (string) ($pending->last_name ?? ''),
            'phone' => $pending->phone,
            'email' => $pending->email,
            'password' => (string) $pending->password_hash,
            'role' => (string) ($pending->role ?: 'user'),
            'is_verified' => true,
        ]);

        $pending->status = 'completed';
        $pending->verify_callback_status = 'completed';
        $pending->save();

        $token = $user->createToken('api')->plainTextToken;

        return [
            'token' => $token,
            'user' => $user->toArray(),
        ];
    }

    public function registerStatus(string $registrationId): array
    {
        /** @var PendingRegistration|null $pending */
        $pending = PendingRegistration::query()->find($registrationId);
        if (!$pending) {
            throw ValidationException::withMessages([
                'registrationId' => ['Registration not found.'],
            ]);
        }

        if ($pending->otp_expires_at instanceof Carbon && $pending->otp_expires_at->isPast() && $pending->status === 'pending') {
            $pending->status = 'expired';
            $pending->save();
        }

        return [
            'registrationId' => (string) $pending->id,
            'status' => strtoupper((string) $pending->status),
            'callbackStatus' => strtoupper((string) ($pending->verify_callback_status ?? 'pending')),
            'displayInstruction' => (string) ($pending->verify_instruction ?? ''),
            'expiresAt' => optional($pending->otp_expires_at)->toISOString(),
        ];
    }

    public function handleVerifyMnCallback(array $payload): array
    {
        $sessionId = trim((string) ($payload['sessionId'] ?? $payload['session_id'] ?? $payload['sessionid'] ?? ''));
        if ($sessionId === '') {
            return ['ok' => true];
        }

        /** @var PendingRegistration|null $pending */
        $pending = PendingRegistration::query()
            ->where('verify_session_id', $sessionId)
            ->first();

        if ($pending) {
            $pending->verify_callback_status = 'completed';
            $pending->save();
        }

        return ['ok' => true];
    }

    private function createVerifySession(string $phone, string $otp): array
    {
        $apiKey = trim((string) env('VERIFY_MN_API_KEY', ''));
        if ($apiKey === '') {
            throw ValidationException::withMessages([
                'phone' => ['VERIFY_MN_API_KEY тохиргоо дутуу байна.'],
            ]);
        }

        if (!preg_match('/^\d{8,16}$/', $phone)) {
            throw ValidationException::withMessages([
                'phone' => ['Утасны дугаар буруу байна.'],
            ]);
        }

        $payload = [
            'phone' => $phone,
            'text' => $otp,
        ];

        $responseSms = trim((string) env('VERIFY_MN_RESPONSE_SMS', 'Verification successful.'));
        if ($responseSms !== '') {
            // Verify.mn accepts ASCII-only response text.
            $payload['responseSms'] = preg_replace('/[^\x20-\x7E]/', '', $responseSms) ?: 'Amjilttai batalgaajlaa.';
        }

        $callbackUrl = trim((string) env('VERIFY_MN_CALLBACK_URL', ''));
        if ($callbackUrl !== '') {
            $payload['callback'] = $callbackUrl;
        }

        try {
            $response = Http::timeout(20)
                ->acceptJson()
                ->withToken($apiKey)
                ->post(self::VERIFY_BASE_URL . '/sessions', $payload);
        } catch (\Throwable $e) {
            Log::warning('Verify.mn request failed', [
                'message' => $e->getMessage(),
                'phone' => $phone,
            ]);

            throw ValidationException::withMessages([
                'phone' => ['Verify.mn холболт амжилтгүй боллоо.'],
            ]);
        }

        if ($response->failed()) {
            Log::warning('Verify.mn session create failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'phone' => $phone,
            ]);

            $message = (string) (
                $response->json('message')
                ?? $response->json('error')
                ?? 'Verify.mn session үүсгэхэд алдаа гарлаа.'
            );

            throw ValidationException::withMessages([
                'phone' => [$message],
            ]);
        }

        $data = (array) $response->json();
        $sessionId = trim((string) ($data['sessionId'] ?? ''));
        if ($sessionId === '') {
            throw ValidationException::withMessages([
                'phone' => ['Verify.mn sessionId ирсэнгүй.'],
            ]);
        }

        return [
            'sessionId' => $sessionId,
            'expiresAt' => $data['expiresAt'] ?? null,
            'displayInstruction' => $data['displayInstruction'] ?? null,
            'smsUri' => $data['smsUri'] ?? null,
        ];
    }

    private function resolveVerifyExpiry(array $verifySession, int $fallbackTtl): Carbon
    {
        $raw = trim((string) ($verifySession['expiresAt'] ?? ''));

        if ($raw !== '') {
            try {
                $parsed = Carbon::parse($raw);
                if ($parsed->isFuture()) {
                    return $parsed;
                }
            } catch (\Throwable $e) {
                // fallback to local TTL
            }
        }

        return now()->addSeconds($fallbackTtl);
    }

    public function register(array $payload): array
    {
        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        $phone = trim((string) ($payload['phone'] ?? ''));

        if ($email !== '' && User::query()->where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => ['Email already exists.'],
            ]);
        }

        if ($phone !== '' && User::query()->where('phone', $phone)->exists()) {
            throw ValidationException::withMessages([
                'phone' => ['Phone already exists.'],
            ]);
        }

        $user = User::query()->create([
            'first_name' => trim((string) ($payload['first_name'] ?? $payload['firstName'] ?? 'User')),
            'last_name' => trim((string) ($payload['last_name'] ?? $payload['lastName'] ?? '')),
            'phone' => $phone !== '' ? $phone : null,
            'email' => $email !== '' ? $email : null,
            'password' => Hash::make((string) ($payload['password'] ?? '')),
            'role' => (string) ($payload['role'] ?? 'user'),
            'is_verified' => true,
        ]);

        $token = $user->createToken('api')->plainTextToken;

        return [
            'data' => [
                'token' => $token,
                'user' => $user->toArray(),
            ],
        ];
    }

    public function login(array $payload): array
    {
        $identity = trim((string) ($payload['phone'] ?? $payload['email'] ?? ''));
        $password = (string) ($payload['password'] ?? '');

        $query = User::query();
        if (str_contains($identity, '@')) {
            $query->where('email', strtolower($identity));
        } else {
            $query->where('phone', $identity);
        }

        $user = $query->first();

        if (!$user || !Hash::check($password, (string) $user->password)) {
            throw ValidationException::withMessages([
                'credentials' => ['Invalid credentials.'],
            ]);
        }

        $token = $user->createToken('api')->plainTextToken;

        return [
            'data' => [
                'token' => $token,
                'user' => $user->toArray(),
            ],
        ];
    }

    public function loginWithGoogle(string $idToken): array
    {
        $clientId = trim((string) env('GOOGLE_CLIENT_ID', ''));
        if ($clientId === '') {
            throw ValidationException::withMessages([
                'google' => ['Google config missing.'],
            ]);
        }

        $res = Http::acceptJson()->get('https://oauth2.googleapis.com/tokeninfo', [
            'id_token' => $idToken,
        ]);

        if ($res->failed()) {
            throw ValidationException::withMessages([
                'google' => ['Invalid Google token.'],
            ]);
        }

        $profile = (array) $res->json();
        $aud = trim((string) ($profile['aud'] ?? ''));
        if ($aud !== $clientId) {
            throw ValidationException::withMessages([
                'google' => ['Google token audience mismatch.'],
            ]);
        }

        $email = strtolower(trim((string) ($profile['email'] ?? '')));
        if ($email === '') {
            throw ValidationException::withMessages([
                'google' => ['Google email is required.'],
            ]);
        }

        $providerId = trim((string) ($profile['sub'] ?? $email));
        $firstName = trim((string) ($profile['given_name'] ?? 'User'));
        $lastName = trim((string) ($profile['family_name'] ?? ''));
        $photo = trim((string) ($profile['picture'] ?? ''));

        return $this->findOrCreateSocialUser(
            provider: 'google',
            providerId: $providerId,
            email: $email,
            firstName: $firstName,
            lastName: $lastName,
            photo: $photo !== '' ? $photo : null,
        );
    }

    public function loginWithFacebook(string $accessToken): array
    {
        $appId = trim((string) env('FACEBOOK_APP_ID', ''));
        if ($appId === '') {
            throw ValidationException::withMessages([
                'facebook' => ['Facebook config missing.'],
            ]);
        }

        $res = Http::acceptJson()->get('https://graph.facebook.com/me', [
            'fields' => 'id,name,first_name,last_name,email,picture.type(large)',
            'access_token' => $accessToken,
        ]);

        if ($res->failed()) {
            throw ValidationException::withMessages([
                'facebook' => ['Invalid Facebook token.'],
            ]);
        }

        $profile = (array) $res->json();
        $providerId = trim((string) ($profile['id'] ?? ''));
        if ($providerId === '') {
            throw ValidationException::withMessages([
                'facebook' => ['Facebook id missing.'],
            ]);
        }

        $email = strtolower(trim((string) ($profile['email'] ?? '')));
        $firstName = trim((string) ($profile['first_name'] ?? 'User'));
        $lastName = trim((string) ($profile['last_name'] ?? ''));
        $photo = trim((string) data_get($profile, 'picture.data.url', ''));

        return $this->findOrCreateSocialUser(
            provider: 'facebook',
            providerId: $providerId,
            email: $email !== '' ? $email : null,
            firstName: $firstName,
            lastName: $lastName,
            photo: $photo !== '' ? $photo : null,
        );
    }

    public function me(User $user): array
    {
        return ['data' => $user->toArray()];
    }

    public function logout(User $user): array
    {
        $user->currentAccessToken()?->delete();
        return ['data' => ['ok' => true]];
    }

    private function findOrCreateSocialUser(
        string $provider,
        string $providerId,
        ?string $email,
        string $firstName,
        string $lastName,
        ?string $photo
    ): array {
        $user = User::query()
            ->where('social_provider', $provider)
            ->where('social_provider_id', $providerId)
            ->first();

        if (!$user && $email) {
            $user = User::query()->where('email', $email)->first();
        }

        if (!$user) {
            $user = User::query()->create([
                'first_name' => $firstName !== '' ? $firstName : 'User',
                'last_name' => $lastName,
                'email' => $email,
                'role' => 'user',
                'photo' => $photo,
                'social_provider' => $provider,
                'social_provider_id' => $providerId,
                'is_verified' => true,
            ]);
        } else {
            $user->social_provider = $provider;
            $user->social_provider_id = $providerId;
            if ($photo) {
                $user->photo = $photo;
            }
            if (!$user->email && $email) {
                $user->email = $email;
            }
            $user->is_verified = true;
            $user->save();
        }

        $token = $user->createToken('api')->plainTextToken;

        return [
            'data' => [
                'token' => $token,
                'user' => $user->toArray(),
            ],
        ];
    }
}
