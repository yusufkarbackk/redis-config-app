<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ApiRateLimit
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $appKey = $request->header('X-App-Key');
        $ip = $request->ip();

        // Create unique keys for rate limiting
        $appKeyKey = 'api_app_key:' . ($appKey ? hash('sha256', $appKey) : 'anonymous');
        $ipKey = 'api_ip:' . $ip;

        // Different rate limits based on authentication
        if ($appKey) {
            // Authenticated requests: Higher limit
            $maxAttempts = 60; // 60 requests per minute
            $decayMinutes = 1;
        } else {
            // Unauthenticated requests: Lower limit
            $maxAttempts = 10; // 10 requests per minute
            $decayMinutes = 1;
        }

        // Check rate limiting by app key
        if (RateLimiter::tooManyAttempts($appKeyKey, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($appKeyKey);

            \Log::warning('API rate limit exceeded by app key', [
                'app_key_hash' => $appKey ? hash('sha256', $appKey) : 'none',
                'ip' => $ip,
                'retry_after' => $seconds,
                'endpoint' => $request->path()
            ]);

            return response()->json([
                'message' => 'Too many requests. Please try again later.',
                'error_code' => 'RATE_LIMIT_EXCEEDED',
                'retry_after' => $seconds
            ], 429)->header('Retry-After', $seconds);
        }

        // Check rate limiting by IP (additional protection)
        $ipMaxAttempts = $maxAttempts * 2; // Allow double the limit per IP
        if (RateLimiter::tooManyAttempts($ipKey, $ipMaxAttempts)) {
            $seconds = RateLimiter::availableIn($ipKey);

            \Log::warning('API rate limit exceeded by IP', [
                'ip' => $ip,
                'retry_after' => $seconds,
                'endpoint' => $request->path()
            ]);

            return response()->json([
                'message' => 'Too many requests from this IP. Please try again later.',
                'error_code' => 'IP_RATE_LIMIT_EXCEEDED',
                'retry_after' => $seconds
            ], 429)->header('Retry-After', $seconds);
        }

        // Increment the rate limit counters
        RateLimiter::hit($appKeyKey, $decayMinutes * 60);
        RateLimiter::hit($ipKey, $decayMinutes * 60);

        // Add rate limit headers to response
        $response = $next($request);

        $response->headers->set('X-RateLimit-Limit', $maxAttempts);
        $response->headers->set('X-RateLimit-Remaining',
            max(0, $maxAttempts - RateLimiter::attempts($appKeyKey))
        );

        return $response;
    }
}