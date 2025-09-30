<?php

namespace BusBooking\Middleware;

use BusBooking\Core\Request;

/**
 * CORS (Cross-Origin Resource Sharing) middleware
 */
class CorsMiddleware
{
    public function handle(Request $request): ?array
    {
        $origin = $request->getHeader('Origin');
        $allowedOrigins = CORS_ALLOWED_ORIGINS;

        // Check if origin is allowed
        if ($origin && (in_array($origin, $allowedOrigins) || in_array('*', $allowedOrigins))) {
            header('Access-Control-Allow-Origin: ' . $origin);
        } elseif (in_array('*', $allowedOrigins)) {
            header('Access-Control-Allow-Origin: *');
        }

        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');

        // Handle preflight OPTIONS request
        if ($request->getMethod() === 'OPTIONS') {
            http_response_code(200);
            return ['message' => 'CORS preflight handled'];
        }

        return null; // Continue to next middleware
    }
}