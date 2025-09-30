<?php

namespace BusBooking\Controllers;

use BusBooking\Core\Request;
use BusBooking\Core\Response;
use BusBooking\Services\AuthService;

/**
 * Authentication controller
 */
class AuthController
{
    private AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    public function login(Request $request): array
    {
        $errors = $request->validate([
            'worker_id' => 'required',
            'type' => 'required'
        ]);

        if (!empty($errors)) {
            return Response::error('Validation failed', 400, $errors);
        }

        $workerId = $request->getBody('worker_id');
        $type = $request->getBody('type', 'user');

        try {
            if ($type === 'admin') {
                $password = $request->getBody('password');
                if (!$password) {
                    return Response::error('Password required for admin login', 400);
                }
                
                $result = $this->authService->authenticateAdmin($workerId, $password);
                return Response::success($result, 'Admin login successful');
            } else {
                $result = $this->authService->authenticateUser($workerId);
                return Response::success($result, 'Login successful');
            }
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), 401);
        }
    }

    public function verify(Request $request): array
    {
        $token = $request->getBody('token');
        
        if (!$token) {
            return Response::error('Token required', 400);
        }

        try {
            $payload = $this->authService->verifyToken($token);
            return Response::success(['valid' => true, 'user' => $payload], 'Token is valid');
        } catch (\Exception $e) {
            return Response::error('Invalid token', 401);
        }
    }

    public function logout(Request $request): array
    {
        $token = $request->getHeader('Authorization');
        $token = str_replace('Bearer ', '', $token);

        if ($token && $this->authService->logout($token)) {
            return Response::success([], 'Logged out successfully');
        }

        return Response::error('Invalid token', 400);
    }

    public function refresh(Request $request): array
    {
        $token = $request->getBody('token');
        
        if (!$token) {
            return Response::error('Token required', 400);
        }

        try {
            $result = $this->authService->refreshToken($token);
            return Response::success($result, 'Token refreshed successfully');
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), 401);
        }
    }
}