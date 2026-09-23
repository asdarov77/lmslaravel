<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiResponseEnvelope
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if ($response instanceof JsonResponse) {
            $original = $response->getData(true);
            $status = $response->getStatusCode();

            $enveloped = [
                'success' => $status >= 200 && $status < 300,
                'data' => $original['data'] ?? $original,
                'error' => $status >= 400 ? [
                    'code' => $original['code'] ?? (string)$status,
                    'message' => $original['message'] ?? ($original['error'] ?? 'Error'),
                    'details' => $original['errors'] ?? null,
                ] : null,
                'meta' => $original['meta'] ?? null,
            ];

            return response()->json($enveloped, $status, $response->headers->all(), JSON_UNESCAPED_UNICODE);
        }

        return $response;
    }
}


