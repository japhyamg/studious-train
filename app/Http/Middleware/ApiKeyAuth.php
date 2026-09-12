<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\BusinessDetails;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyAuth
{
    /**
     * Authenticate API consumers using client_id + HMAC-SHA256 signature.
     *
     * Required headers:
     *   X-Client-Id  : client id issued via Settings → Generate API keys
     *   X-Timestamp  : unix seconds (allowed clock skew: 5 minutes)
     *   X-Nonce      : random string, unique per request (replay protection)
     *   X-Signature  : hash_hmac('sha256', signString, client_secret)
     *
     * The signed string is:
     *   "{METHOD}\n{PATH}\n{TIMESTAMP}\n{NONCE}\n{RAW_BODY}"
     */
    public function handle(Request $request, Closure $next): Response
    {
        $clientId  = $request->header('X-Client-Id');
        $timestamp = $request->header('X-Timestamp');
        $nonce     = $request->header('X-Nonce');
        $signature = $request->header('X-Signature');

        if (!$clientId || !$timestamp || !$nonce || !$signature) {
            return $this->unauthorized('Missing authentication headers (X-Client-Id, X-Timestamp, X-Nonce, X-Signature).');
        }

        // Reject requests outside the allowed clock skew to mitigate replays.
        if (abs(time() - (int) $timestamp) > 300) {
            return $this->unauthorized('Request timestamp is invalid or expired.');
        }

        $storedClientId = BusinessDetails::where('name', 'client_id')->value('value');
        $secret         = BusinessDetails::where('name', 'client_secret')->value('value');

        if (!$secret || !$storedClientId || !hash_equals((string) $storedClientId, (string) $clientId)) {
            return $this->unauthorized('Invalid client id.');
        }

        // Replay protection: a nonce may only be used once.
        $nonceKey = 'api_nonce:' . $nonce;
        if (Cache::has($nonceKey)) {
            return $this->unauthorized('Nonce has already been used.');
        }

        $signed = implode("\n", [
            $request->method(),
            $request->path(),
            $timestamp,
            $nonce,
            $request->getContent(),
        ]);

        $expected = hash_hmac('sha256', $signed, (string) $secret);

        if (!hash_equals($expected, (string) $signature)) {
            return $this->unauthorized('Invalid signature.');
        }

        // Consume the nonce for the duration of the clock-skew window.
        Cache::put($nonceKey, true, now()->addMinutes(10));

        return $next($request);
    }

    private function unauthorized(string $message): Response
    {
        return response()->json([
            'status'  => 'error',
            'message' => 'Unauthorized. ' . $message,
        ], 401);
    }
}
