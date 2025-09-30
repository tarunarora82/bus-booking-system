<?php

namespace BusBooking\Middleware;

use BusBooking\Core\Request;

/**
 * Rate limiting middleware using Redis
 */
class RateLimitMiddleware
{
    private \Redis $redis;
    private int $maxRequests;
    private int $windowSeconds;

    public function __construct()
    {
        $this->redis = new \Redis();
        $this->redis->connect(REDIS_HOST, REDIS_PORT);
        $this->maxRequests = RATE_LIMIT_REQUESTS;
        $this->windowSeconds = RATE_LIMIT_WINDOW;
    }

    public function handle(Request $request): ?array
    {
        $clientId = $this->getClientIdentifier($request);
        $key = "rate_limit:{$clientId}";
        
        // Get current request count
        $current = $this->redis->get($key);
        
        if ($current === false) {
            // First request in window
            $this->redis->setex($key, $this->windowSeconds, 1);
            $this->addRateLimitHeaders(1);
            return null;
        }
        
        $current = (int)$current;
        
        if ($current >= $this->maxRequests) {
            // Rate limit exceeded
            $this->addRateLimitHeaders($current, true);
            http_response_code(429);
            return [
                'success' => false,
                'message' => 'Rate limit exceeded. Please try again later.',
                'error_code' => 'RATE_LIMIT_EXCEEDED',
                'retry_after' => $this->redis->ttl($key)
            ];
        }
        
        // Increment counter
        $this->redis->incr($key);
        $this->addRateLimitHeaders($current + 1);
        
        return null; // Continue to next middleware
    }

    private function getClientIdentifier(Request $request): string
    {
        $ip = $request->getIpAddress();
        $userAgent = $request->getUserAgent();
        
        // Create a unique identifier based on IP and User Agent
        return md5($ip . $userAgent);
    }

    private function addRateLimitHeaders(int $current, bool $exceeded = false): void
    {
        header('X-RateLimit-Limit: ' . $this->maxRequests);
        header('X-RateLimit-Remaining: ' . max(0, $this->maxRequests - $current));
        header('X-RateLimit-Reset: ' . (time() + $this->windowSeconds));
        
        if ($exceeded) {
            header('Retry-After: ' . $this->windowSeconds);
        }
    }
}