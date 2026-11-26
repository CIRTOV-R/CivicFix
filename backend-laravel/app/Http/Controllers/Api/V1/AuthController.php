<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\AuditAction;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

final class AuthController extends Controller
{
    public function __construct(private readonly AuditService $auditService) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'role_id' => Role::where('name', RoleName::CIUDADANO->value)->value('id'),
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
            'password_hash' => Hash::make($request->string('password')->toString()),
            'phone' => $request->input('phone'),
        ]);

        $this->auditService->log(AuditAction::USER_REGISTERED, $user, [], [
            'email' => $user->email,
            'role' => RoleName::CIUDADANO->value,
        ], $user);

        $token = $this->issueToken($user);
        $payload = ['user' => new UserResource($user->load('role')), 'token' => $token];

        return response()->json(['data' => $payload, 'token' => $token], JsonResponse::HTTP_CREATED);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        if (! Auth::attempt($request->only('email', 'password'))) {
            return response()->json(['message' => 'Credenciales inválidas.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        /** @var User $user */
        $user = Auth::user();

        if (! $user->is_active) {
            Auth::logout();

            return response()->json(['message' => 'La cuenta está inactiva.'], JsonResponse::HTTP_FORBIDDEN);
        }

        $token = $this->issueToken($user);
        $payload = ['user' => new UserResource($user->load('role')), 'token' => $token];

        return response()->json(['data' => $payload, 'token' => $token]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['message' => 'Sesión cerrada.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'data' => new UserResource($request->user()->load('role')),
        ]);
    }

    private function issueToken(User $user): string
    {
        return $user->createToken(
            'civicfix-pwa',
            ['*'],
            now()->addMinutes((int) (config('sanctum.expiration') ?? 43200)),
        )->plainTextToken;
    }
}
