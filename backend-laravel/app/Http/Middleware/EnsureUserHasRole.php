<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

final class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'No autenticado.'], Response::HTTP_UNAUTHORIZED);
        }

        $allowed = Collection::make($roles)
            ->flatMap(fn (string $role): array => explode('|', $role))
            ->map(fn (string $role): string => strtoupper(trim($role)))
            ->filter()
            ->all();

        if ($allowed !== [] && ! collect($allowed)->contains(fn (string $role): bool => $user->hasRole($role))) {
            return response()->json(['message' => 'No tiene permisos para esta acción.'], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
