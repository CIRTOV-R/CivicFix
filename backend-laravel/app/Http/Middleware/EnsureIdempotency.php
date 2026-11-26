<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\IdempotencyResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class EnsureIdempotency
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('POST')) {
            return $next($request);
        }

        $key = $request->header('X-Idempotency-Key');

        if ($key === null || ! Str::isUuid($key)) {
            return response()->json([
                'message' => 'La cabecera X-Idempotency-Key debe ser un UUID válido.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $fingerprint = hash('sha256', ($request->user()?->id ?? 'guest').'|'.$key);
        $existing = IdempotencyResponse::query()->where('idempotency_key', $fingerprint)->first();

        if ($existing !== null) {
            return response()->json($existing->response_body, $existing->status_code);
        }

        $response = $next($request);

        if ($response->getStatusCode() < Response::HTTP_INTERNAL_SERVER_ERROR) {
            $content = $response->getContent();
            $body = is_string($content) && $content !== '' ? json_decode($content, true) : null;

            IdempotencyResponse::firstOrCreate(
                ['idempotency_key' => $fingerprint],
                [
                    'user_id' => $request->user()?->id,
                    'method' => $request->method(),
                    'path' => Str::limit($request->path(), 255, ''),
                    'status_code' => $response->getStatusCode(),
                    'response_body' => is_array($body) ? $body : ['data' => null],
                ],
            );
        }

        return $response;
    }
}
