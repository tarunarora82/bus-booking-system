<?php

namespace BusBooking\Core;

/**
 * HTTP Response handling
 */
class Response
{
    private int $statusCode = 200;
    private array $headers = [];
    private string $body = '';

    public function setStatusCode(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function getHeader(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function setBody(string $body): self
    {
        $this->body = $body;
        return $this;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function json(array $data, int $statusCode = null): void
    {
        if ($statusCode !== null) {
            $this->setStatusCode($statusCode);
        }

        $this->setHeader('Content-Type', 'application/json; charset=utf-8');
        $this->setBody(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->send();
    }

    public function html(string $content, int $statusCode = null): void
    {
        if ($statusCode !== null) {
            $this->setStatusCode($statusCode);
        }

        $this->setHeader('Content-Type', 'text/html; charset=utf-8');
        $this->setBody($content);
        $this->send();
    }

    public function text(string $content, int $statusCode = null): void
    {
        if ($statusCode !== null) {
            $this->setStatusCode($statusCode);
        }

        $this->setHeader('Content-Type', 'text/plain; charset=utf-8');
        $this->setBody($content);
        $this->send();
    }

    public function redirect(string $url, int $statusCode = 302): void
    {
        $this->setStatusCode($statusCode);
        $this->setHeader('Location', $url);
        $this->send();
        exit;
    }

    public function download(string $filePath, string $filename = null): void
    {
        if (!file_exists($filePath)) {
            $this->setStatusCode(404);
            $this->json(['error' => 'File not found']);
            return;
        }

        $filename = $filename ?? basename($filePath);
        $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';

        $this->setHeader('Content-Type', $mimeType);
        $this->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $this->setHeader('Content-Length', (string)filesize($filePath));
        $this->setHeader('Cache-Control', 'must-revalidate');
        $this->setHeader('Pragma', 'public');

        $this->sendHeaders();
        readfile($filePath);
        exit;
    }

    public function cache(int $seconds): self
    {
        $this->setHeader('Cache-Control', 'public, max-age=' . $seconds);
        $this->setHeader('Expires', gmdate('D, d M Y H:i:s', time() + $seconds) . ' GMT');
        return $this;
    }

    public function noCache(): self
    {
        $this->setHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
        $this->setHeader('Pragma', 'no-cache');
        $this->setHeader('Expires', '0');
        return $this;
    }

    public function cors(array $origins = ['*'], array $methods = ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS']): self
    {
        $this->setHeader('Access-Control-Allow-Origin', implode(', ', $origins));
        $this->setHeader('Access-Control-Allow-Methods', implode(', ', $methods));
        $this->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        $this->setHeader('Access-Control-Allow-Credentials', 'true');
        return $this;
    }

    public function security(): self
    {
        $this->setHeader('X-Content-Type-Options', 'nosniff');
        $this->setHeader('X-Frame-Options', 'DENY');
        $this->setHeader('X-XSS-Protection', '1; mode=block');
        $this->setHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->setHeader('Content-Security-Policy', "default-src 'self'");
        return $this;
    }

    public function sendHeaders(): void
    {
        if (headers_sent()) {
            return;
        }

        http_response_code($this->statusCode);

        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
    }

    public function send(): void
    {
        $this->sendHeaders();
        echo $this->body;
    }

    // Static helper methods for common responses
    public static function success(array $data = [], string $message = 'Success'): array
    {
        return [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    public static function error(string $message, int $code = 400, array $errors = []): array
    {
        return [
            'success' => false,
            'message' => $message,
            'error_code' => $code,
            'errors' => $errors,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    public static function paginated(array $data, int $total, int $page, int $perPage): array
    {
        return [
            'success' => true,
            'data' => $data,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => (int)ceil($total / $perPage),
                'has_next' => ($page * $perPage) < $total,
                'has_prev' => $page > 1
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
}