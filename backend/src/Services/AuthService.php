<?php

namespace BusBooking\Services;

use BusBooking\Core\Database;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Authentication service for handling JWT tokens and user authentication
 */
class AuthService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function authenticateUser(string $workerId): array
    {
        // Validate worker ID format
        if (!$this->isValidWorkerId($workerId)) {
            throw new \Exception('Invalid worker ID format. Must be 7-10 digits.');
        }

        // Get or create user
        $user = $this->getOrCreateUser($workerId);
        
        // Generate JWT token
        $token = $this->generateToken([
            'user_id' => $user['id'],
            'worker_id' => $user['worker_id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => 'user'
        ]);

        return [
            'token' => $token,
            'user' => $user,
            'expires_at' => time() + JWT_EXPIRY
        ];
    }

    public function authenticateAdmin(string $username, string $password): array
    {
        $admin = $this->db->fetchOne(
            "SELECT * FROM admin_users WHERE username = ? AND status = 'active'",
            [$username]
        );

        if (!$admin || !password_verify($password, $admin['password_hash'])) {
            throw new \Exception('Invalid credentials');
        }

        // Update last login
        $this->db->update('admin_users', 
            ['last_login' => date('Y-m-d H:i:s')],
            'id = ?',
            [$admin['id']]
        );

        // Generate JWT token
        $token = $this->generateToken([
            'admin_id' => $admin['id'],
            'username' => $admin['username'],
            'full_name' => $admin['full_name'],
            'email' => $admin['email'],
            'role' => 'admin'
        ]);

        unset($admin['password_hash']); // Don't return password hash

        return [
            'token' => $token,
            'admin' => $admin,
            'expires_at' => time() + JWT_EXPIRY
        ];
    }

    public function verifyToken(string $token): array
    {
        try {
            $decoded = JWT::decode($token, new Key(JWT_SECRET, JWT_ALGORITHM));
            return (array)$decoded;
        } catch (\Exception $e) {
            throw new \Exception('Invalid token: ' . $e->getMessage());
        }
    }

    public function generateToken(array $payload): string
    {
        $payload['iat'] = time();
        $payload['exp'] = time() + JWT_EXPIRY;
        $payload['iss'] = APP_URL;

        return JWT::encode($payload, JWT_SECRET, JWT_ALGORITHM);
    }

    private function isValidWorkerId(string $workerId): bool
    {
        return preg_match('/^[0-9]{7,10}$/', $workerId);
    }

    private function getOrCreateUser(string $workerId): array
    {
        // Try to find existing user
        $user = $this->db->fetchOne(
            "SELECT * FROM users WHERE worker_id = ? AND status = 'active'",
            [$workerId]
        );

        if ($user) {
            return $user;
        }

        // Create new user with minimal information
        $userId = $this->db->insert('users', [
            'worker_id' => $workerId,
            'name' => null, // Will be updated when user provides information
            'email' => null,
            'department' => null,
            'phone' => null,
            'status' => 'active'
        ]);

        return $this->db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
    }

    public function refreshToken(string $token): array
    {
        $payload = $this->verifyToken($token);
        
        // Remove JWT specific claims
        unset($payload['iat'], $payload['exp'], $payload['iss']);
        
        // Generate new token
        $newToken = $this->generateToken($payload);
        
        return [
            'token' => $newToken,
            'expires_at' => time() + JWT_EXPIRY
        ];
    }

    public function logout(string $token): bool
    {
        // In a more complex implementation, we would blacklist the token
        // For now, we'll just verify it's valid and return success
        try {
            $this->verifyToken($token);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}