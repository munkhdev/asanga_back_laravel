<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class UserController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        if (!$user) {
            return ApiResponse::error('Нэвтрэх эрхгүй байна', 401, 'UNAUTHORIZED');
        }

        return ApiResponse::success($this->serializeUser($user));
    }

    public function index(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $page = max(1, (int) $request->query('page', 1));
        $limit = min(200, max(1, (int) $request->query('limit', 50)));
        $q = trim((string) $request->query('q', ''));

        $query = User::query();
        if ($q !== '') {
            $query->where(function ($w) use ($q): void {
                $w->where('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%");
            });
        }

        $role = trim((string) $request->query('role', ''));
        if ($role !== '') {
            $query->where('role', $role);
        }

        $paginator = $query
            ->latest('created_at')
            ->paginate($limit, ['*'], 'page', $page);

        return ApiResponse::success(
            collect($paginator->items())
                ->map(fn (User $user) => $this->serializeUser($user))
                ->values()
                ->all(),
            200,
            ApiResponse::SUCCESS,
            null,
            [
                'page' => $paginator->currentPage(),
                'limit' => $paginator->perPage(),
                'total' => $paginator->total(),
                'pages' => $paginator->lastPage(),
            ]
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $payload = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:191'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['nullable', 'in:user,artist,admin,sub-admin'],
            'status' => ['nullable', 'boolean'],
            'isActive' => ['nullable', 'boolean'],
            'photo' => ['nullable', 'string'],
        ]);

        [$firstName, $lastName] = $this->resolveNames($payload);

        $user = User::query()->create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'phone' => $this->nullableString($payload['phone'] ?? null),
            'email' => strtolower(trim((string) ($payload['email'] ?? ''))),
            'password' => Hash::make((string) ($payload['password'] ?? '')),
            'role' => (string) ($payload['role'] ?? 'user'),
            'photo' => $this->nullableString($payload['photo'] ?? null),
            'is_verified' => (bool) (($payload['status'] ?? $payload['isActive'] ?? true)),
        ]);

        return ApiResponse::success($this->serializeUser($user), 201, ApiResponse::USER_CREATED);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        /** @var User|null $auth */
        $auth = $request->user();
        if (!$auth) {
            return ApiResponse::error('Нэвтрэх эрхгүй байна', 401, 'UNAUTHORIZED');
        }

        $isAdmin = in_array((string) $auth->role, ['admin', 'sub-admin'], true);
        if (!$isAdmin && (string) $auth->id !== $id) {
            return ApiResponse::error('Хандах эрхгүй байна', 403, 'FORBIDDEN');
        }

        $payload = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:191'],
            'password' => ['nullable', 'string', 'min:6'],
            'role' => ['nullable', 'in:user,artist,admin,sub-admin'],
            'status' => ['nullable', 'boolean'],
            'isActive' => ['nullable', 'boolean'],
            'photo' => ['nullable', 'string'],
        ]);

        /** @var User $user */
        $user = User::query()->findOrFail($id);

        [$firstName, $lastName] = $this->resolveNames($payload, $user);
        $user->first_name = $firstName;
        $user->last_name = $lastName;

        if (array_key_exists('phone', $payload)) {
            $user->phone = $this->nullableString($payload['phone']);
        }
        if (array_key_exists('email', $payload)) {
            $user->email = $this->nullableString(strtolower((string) $payload['email']));
        }
        if (!empty($payload['password'])) {
            $user->password = Hash::make((string) $payload['password']);
        }
        if ($isAdmin && array_key_exists('role', $payload) && $payload['role']) {
            $user->role = (string) $payload['role'];
        }
        if ($isAdmin && (array_key_exists('status', $payload) || array_key_exists('isActive', $payload))) {
            $user->is_verified = (bool) ($payload['status'] ?? $payload['isActive']);
        }
        if (array_key_exists('photo', $payload)) {
            $user->photo = $this->nullableString($payload['photo']);
        }

        $user->save();

        return ApiResponse::success($this->serializeUser($user));
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->ensureAdmin($request);

        $user = User::query()->findOrFail($id);
        $user->delete();

        return ApiResponse::success(['id' => $id]);
    }

    private function ensureAdmin(Request $request): void
    {
        /** @var User|null $user */
        $user = $request->user();
        if (!$user) {
            throw new RuntimeException('Нэвтрэх эрхгүй байна');
        }

        if (!in_array((string) $user->role, ['admin', 'sub-admin'], true)) {
            throw new RuntimeException('Хандах эрхгүй байна');
        }
    }

    private function resolveNames(array $payload, ?User $fallback = null): array
    {
        $firstName = trim((string) ($payload['first_name'] ?? ''));
        $lastName = trim((string) ($payload['last_name'] ?? ''));

        if ($firstName === '' && array_key_exists('name', $payload)) {
            $parts = preg_split('/\s+/', trim((string) $payload['name'])) ?: [];
            $firstName = trim((string) ($parts[0] ?? ''));
            $lastName = trim((string) implode(' ', array_slice($parts, 1)));
        }

        if ($fallback) {
            if ($firstName === '') {
                $firstName = (string) $fallback->first_name;
            }
            if ($lastName === '') {
                $lastName = (string) ($fallback->last_name ?? '');
            }
        }

        if ($firstName === '') {
            $firstName = 'User';
        }

        return [$firstName, $lastName];
    }

    private function nullableString(mixed $value): ?string
    {
        $v = trim((string) ($value ?? ''));
        return $v === '' ? null : $v;
    }

    private function serializeUser(User $user): array
    {
        $arr = $user->toArray();
        $firstName = (string) ($arr['first_name'] ?? '');
        $lastName = (string) ($arr['last_name'] ?? '');

        return [
            'id' => (string) $user->id,
            '_id' => (string) $user->id,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'name' => trim("{$firstName} {$lastName}"),
            'phone' => (string) ($arr['phone'] ?? ''),
            'email' => (string) ($arr['email'] ?? ''),
            'role' => (string) ($arr['role'] ?? 'user'),
            'photo' => (string) ($arr['photo'] ?? ''),
            'status' => (bool) ($arr['is_verified'] ?? false),
            'isActive' => (bool) ($arr['is_verified'] ?? false),
            'is_verified' => (bool) ($arr['is_verified'] ?? false),
            'createdAt' => $arr['created_at'] ?? null,
            'updatedAt' => $arr['updated_at'] ?? null,
        ];
    }
}