<?php

declare(strict_types=1);

namespace OctadecimalHQ\ShellGate\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Str;

/**
 * Service for generating and validating JWT tokens for terminal sessions.
 */
class JwtService
{
    private const ALGORITHM = 'HS256';

    public function __construct(
        private readonly string $secret,
        private readonly int $ttl = 300,
        private readonly bool $bindIp = true,
        private readonly bool $bindUserAgent = true,
    ) {}

    /**
     * Generate a JWT token for terminal session.
     *
     * @param array<string, mixed> $payload Custom payload data
     * @param string|null $ip Client IP address
     * @param string|null $userAgent Client User-Agent
     * @return array{token: string, expires_at: int, session_id: string}
     */
    public function generate(array $payload = [], ?string $ip = null, ?string $userAgent = null): array
    {
        $now = time();
        $expiresAt = $now + $this->ttl;
        $sessionId = $payload['session_id'] ?? (string) Str::uuid();

        $tokenPayload = [
            'iat' => $now,
            'exp' => $expiresAt,
            'jti' => $sessionId,
            'sub' => $payload['user_id'] ?? null,
            ...$payload,
        ];

        // Bind token to IP if enabled
        if ($this->bindIp && $ip) {
            $tokenPayload['ip'] = $ip;
        }

        // Bind token to User-Agent if enabled
        if ($this->bindUserAgent && $userAgent) {
            $tokenPayload['ua_hash'] = $this->hashUserAgent($userAgent);
        }

        $token = JWT::encode($tokenPayload, $this->secret, self::ALGORITHM);

        return [
            'token' => $token,
            'expires_at' => $expiresAt,
            'session_id' => $sessionId,
        ];
    }

    /**
     * Validate and decode a JWT token.
     *
     * @param string $token JWT token
     * @param string|null $ip Client IP to validate against
     * @param string|null $userAgent Client User-Agent to validate against
     * @return object Decoded payload
     * @throws \Firebase\JWT\ExpiredException If token is expired
     * @throws \Firebase\JWT\SignatureInvalidException If signature is invalid
     * @throws \InvalidArgumentException If IP or User-Agent mismatch
     */
    public function validate(string $token, ?string $ip = null, ?string $userAgent = null): object
    {
        $payload = JWT::decode($token, new Key($this->secret, self::ALGORITHM));

        // Validate IP binding
        if ($this->bindIp && isset($payload->ip) && $ip !== null) {
            if ($payload->ip !== $ip) {
                throw new \InvalidArgumentException('IP address mismatch');
            }
        }

        // Validate User-Agent binding
        if ($this->bindUserAgent && isset($payload->ua_hash) && $userAgent !== null) {
            if ($payload->ua_hash !== $this->hashUserAgent($userAgent)) {
                throw new \InvalidArgumentException('User-Agent mismatch');
            }
        }

        return $payload;
    }

    /**
     * Hash User-Agent string for comparison.
     */
    private function hashUserAgent(string $userAgent): string
    {
        return hash('sha256', $userAgent);
    }

    /**
     * Get token TTL in seconds.
     */
    public function getTtl(): int
    {
        return $this->ttl;
    }
}
