<?php

namespace BusBooking\Core;

/**
 * HTTP Request handling
 */
class Request
{
    private string $method;
    private string $uri;
    private array $headers;
    private array $query;
    private array $body;
    private array $files;
    private array $server;

    public function __construct()
    {
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->uri = $this->parseUri();
        $this->headers = $this->parseHeaders();
        $this->query = $_GET ?? [];
        $this->body = $this->parseBody();
        $this->files = $_FILES ?? [];
        $this->server = $_SERVER ?? [];
    }

    private function parseUri(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $uri = parse_url($uri, PHP_URL_PATH);
        return rtrim($uri, '/') ?: '/';
    }

    private function parseHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $header = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
                $headers[$header] = $value;
            }
        }
        
        // Add content type if present
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['Content-Type'] = $_SERVER['CONTENT_TYPE'];
        }
        
        return $headers;
    }

    private function parseBody(): array
    {
        $body = [];
        
        if ($this->method === 'POST' || $this->method === 'PUT' || $this->method === 'PATCH') {
            $contentType = $this->getHeader('Content-Type', '');
            
            if (strpos($contentType, 'application/json') !== false) {
                $input = file_get_contents('php://input');
                $decoded = json_decode($input, true);
                $body = $decoded ?? [];
            } elseif (strpos($contentType, 'application/x-www-form-urlencoded') !== false) {
                $body = $_POST ?? [];
            } elseif (strpos($contentType, 'multipart/form-data') !== false) {
                $body = $_POST ?? [];
            }
        }
        
        return $body;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getHeader(string $name, string $default = ''): string
    {
        return $this->headers[$name] ?? $default;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getQuery(string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->query;
        }
        return $this->query[$key] ?? $default;
    }

    public function getBody(string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->body;
        }
        return $this->body[$key] ?? $default;
    }

    public function getFile(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    public function getServer(string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->server;
        }
        return $this->server[$key] ?? $default;
    }

    public function getIpAddress(): string
    {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                return trim($ips[0]);
            }
        }
        
        return '127.0.0.1';
    }

    public function getUserAgent(): string
    {
        return $this->getServer('HTTP_USER_AGENT', '');
    }

    public function isSecure(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
               (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    }

    public function isAjax(): bool
    {
        return $this->getHeader('X-Requested-With') === 'XMLHttpRequest';
    }

    public function expectsJson(): bool
    {
        $accept = $this->getHeader('Accept', '');
        return strpos($accept, 'application/json') !== false;
    }

    public function validate(array $rules): array
    {
        $errors = [];
        $data = array_merge($this->query, $this->body);
        
        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;
            $fieldRules = is_string($rule) ? explode('|', $rule) : $rule;
            
            foreach ($fieldRules as $fieldRule) {
                if ($fieldRule === 'required' && empty($value)) {
                    $errors[$field][] = "The {$field} field is required";
                } elseif ($fieldRule === 'email' && !empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$field][] = "The {$field} must be a valid email address";
                } elseif (strpos($fieldRule, 'min:') === 0 && !empty($value)) {
                    $min = (int)substr($fieldRule, 4);
                    if (strlen($value) < $min) {
                        $errors[$field][] = "The {$field} must be at least {$min} characters";
                    }
                } elseif (strpos($fieldRule, 'max:') === 0 && !empty($value)) {
                    $max = (int)substr($fieldRule, 4);
                    if (strlen($value) > $max) {
                        $errors[$field][] = "The {$field} may not be greater than {$max} characters";
                    }
                } elseif ($fieldRule === 'numeric' && !empty($value) && !is_numeric($value)) {
                    $errors[$field][] = "The {$field} must be a number";
                }
            }
        }
        
        return $errors;
    }
}