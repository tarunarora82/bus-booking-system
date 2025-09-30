<?php

namespace BusBooking\Middleware;

use BusBooking\Core\Request;
use BusBooking\Services\AuthService;

/**
 * Authentication middleware using JWT tokens
 */
class AuthMiddleware
{
    private AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    public function handle(Request $request): ?array
    {
        $token = $this->extractToken($request);
        
        if (!$token) {
            http_response_code(401);
            return [
                'success' => false,
                'message' => 'Authentication token required',
                'error_code' => 'TOKEN_MISSING'
            ];
        }

        try {
            $payload = $this->authService->verifyToken($token);
            
            // Add user info to request for use in controllers
            $request->user = $payload;
            
            return null; // Continue to next middleware
            
        } catch (\Exception $e) {
            http_response_code(401);
            return [
                'success' => false,
                'message' => 'Invalid or expired token',
                'error_code' => 'TOKEN_INVALID'
            ];
        }
    }

    public function admin(Request $request): ?array
    {
        // First check regular auth
        $authResult = $this->handle($request);
        if ($authResult !== null) {
            return $authResult;
        }

        // Check if user is admin
        if (!isset($request->user['role']) || $request->user['role'] !== 'admin') {
            http_response_code(403);
            return [
                'success' => false,
                'message' => 'Admin access required',
                'error_code' => 'INSUFFICIENT_PERMISSIONS'
            ];
        }

        return null; // Continue to next middleware
    }

    private function extractToken(Request $request): ?string
    {
        $authHeader = $request->getHeader('Authorization');
        
        if (empty($authHeader)) {
            return null;
        }

        // Check for Bearer token
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $matches[1];
        }

        return null;
    }
}