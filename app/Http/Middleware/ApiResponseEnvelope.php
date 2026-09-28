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

        // Already enveloped (e.g. by a controller using the same contract) — skip
        if ($response instanceof JsonResponse
            && is_array($response->getData(true))
            && array_key_exists('success', $response->getData(true))
            && array_key_exists('data', $response->getData(true))) {
            return $response;
        }

        // 204 No Content must stay untouched (REST contract for empty deletes)
        if ($response instanceof JsonResponse && $response->getStatusCode() === 204) {
            return $response;
        }

        if ($response instanceof JsonResponse) {
            $original = $response->getData(true);
            // Laravel validation errors returned as ->errors() bag via parent Handler:
            // {"message": "...", "errors": {...}} — keep shape, add success flag.
            if (isset($original['message']) && isset($original['errors'])) {
                $enveloped = [
                    'success' => false,
                    'data' => null,
                    'error' => [
                        'code' => (string)$status,
                        'message' => $original['message'],
                        'details' => $original['errors'],
                    ],
                    'meta' => null,
                ];
                return response()->json($enveloped, $status, $response->headers->all(), JSON_UNESCAPED_UNICODE);
            }
            $status = $response->getStatusCode();
            // Laravel validation errors returned as ->errors() bag via parent Handler:
            // {"message": "...", "errors": {...}} — keep shape, add success flag.
            if (isset($original['message']) && isset($original['errors'])) {
                $enveloped = [
                    'success' => false,
                    'data' => null,
                    'error' => [
                        'code' => (string)$status,
                        'message' => $original['message'],
                        'details' => $original['errors'],
                    ],
                    'meta' => null,
                ];
                return response()->json($enveloped, $status, $response->headers->all(), JSON_UNESCAPED_UNICODE);
            }

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


