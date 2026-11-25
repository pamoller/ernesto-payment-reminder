<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AuthController
{
    private const MAX_ATTEMPTS = 5;
    private const WINDOW_SECONDS = 900;

    public function session(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->ensureCsrfToken();
        return $this->json($response, [
            'authenticated' => !empty($_SESSION['authenticated']),
            'csrf_token' => $_SESSION['csrf_token'],
            'username' => $_SESSION['username'] ?? null,
        ]);
    }

    public function login(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->ensureCsrfToken();
        $csrf = $request->getHeaderLine('X-CSRF-Token');
        if (!hash_equals((string) $_SESSION['csrf_token'], $csrf)) {
            return $this->json($response, ['error' => 'Invalid CSRF token'], 419);
        }

        $now = time();
        $attempts = array_values(array_filter(
            $_SESSION['login_attempts'] ?? [],
            static fn (int $timestamp): bool => $timestamp > $now - self::WINDOW_SECONDS,
        ));
        if (count($attempts) >= self::MAX_ATTEMPTS) {
            $_SESSION['login_attempts'] = $attempts;
            return $this->json($response, ['error' => 'Too many login attempts'], 429);
        }

        $body = $request->getParsedBody();
        $body = is_array($body) ? $body : [];
        $username = is_string($body['username'] ?? null) ? (string) $body['username'] : '';
        $password = is_string($body['password'] ?? null) ? (string) $body['password'] : '';

        $configuredUsername = (string) (getenv('AUTH_USERNAME') ?: '');
        $passwordHash = (string) (getenv('AUTH_PASSWORD_HASH') ?: '');

        $valid = $configuredUsername !== ''
            && hash_equals($configuredUsername, $username)
            && $passwordHash !== ''
            && password_verify($password, $passwordHash);

        if (!$valid) {
            $attempts[] = $now;
            $_SESSION['login_attempts'] = $attempts;
            return $this->json($response, ['error' => 'Invalid credentials'], 401);
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['authenticated'] = true;
        $_SESSION['username'] = $configuredUsername;
        unset($_SESSION['login_attempts']);
        $this->ensureCsrfToken();

        return $this->json($response, [
            'authenticated' => true,
            'csrf_token' => $_SESSION['csrf_token'],
            'username' => $configuredUsername,
        ]);
    }

    public function logout(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $csrf = $request->getHeaderLine('X-CSRF-Token');
        if (!isset($_SESSION['csrf_token']) || !hash_equals((string) $_SESSION['csrf_token'], $csrf)) {
            return $this->json($response, ['error' => 'Invalid CSRF token'], 419);
        }

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                [
                    'expires' => time() - 42000,
                    'path' => $params['path'],
                    'domain' => $params['domain'],
                    'secure' => $params['secure'],
                    'httponly' => $params['httponly'],
                    'samesite' => $params['samesite'] ?? 'Lax',
                ],
            );
        }
        session_destroy();

        return $this->json($response, ['authenticated' => false]);
    }

    private function ensureCsrfToken(): void
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }

    /** @param array<string, mixed> $payload */
    private function json(
        ResponseInterface $response,
        array $payload,
        int $status = 200,
    ): ResponseInterface {
        $response->getBody()->write(json_encode($payload, JSON_THROW_ON_ERROR));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}
