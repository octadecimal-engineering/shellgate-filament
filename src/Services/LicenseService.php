<?php

declare(strict_types=1);

namespace OctadecimalHQ\ShellGate\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * License verification service using Anystack API.
 *
 * Validates Shell Gate license keys against Anystack's licensing platform.
 * Results are cached to minimize API calls and improve performance.
 */
class LicenseService
{
    private const ANYSTACK_API_URL = 'https://api.anystack.sh/v1';
    private const CACHE_KEY = 'shell-gate:license:status';
    private const CACHE_TTL_VALID = 86400;      // 24 hours for valid licenses
    private const CACHE_TTL_INVALID = 3600;     // 1 hour for invalid (allow retry)
    private const CACHE_TTL_ERROR = 300;        // 5 minutes for network errors

    public function __construct(
        private readonly ?string $licenseKey,
        private readonly ?string $apiKey,
        private readonly ?string $productId,
        private readonly bool $enabled = true,
    ) {}

    /**
     * Check if the license is valid.
     *
     * @return array{valid: bool, status: string, message: string, cached: bool}
     */
    public function validate(): array
    {
        // Skip validation if disabled or in local/testing environment
        if (! $this->enabled || app()->environment(['local', 'testing'])) {
            return [
                'valid' => true,
                'status' => 'skipped',
                'message' => 'License validation skipped (disabled or development environment)',
                'cached' => false,
            ];
        }

        // Check for missing configuration
        if (empty($this->licenseKey)) {
            return [
                'valid' => false,
                'status' => 'missing_key',
                'message' => 'License key not configured. Set SHELL_GATE_LICENSE_KEY in your .env file.',
                'cached' => false,
            ];
        }

        if (empty($this->apiKey)) {
            Log::warning('Shell Gate: ANYSTACK_CUSTOMER_API_KEY not configured');

            return [
                'valid' => false,
                'status' => 'missing_api_key',
                'message' => 'Anystack API key not configured. Set ANYSTACK_CUSTOMER_API_KEY in your .env file. See: https://github.com/octadecimalhq/shellgate',
                'cached' => false,
            ];
        }

        if (empty($this->productId)) {
            Log::error('Shell Gate: Product ID missing - this should not happen');

            return [
                'valid' => false,
                'status' => 'config_error',
                'message' => 'Internal configuration error. Please contact support.',
                'cached' => false,
            ];
        }

        // Check cache first
        $cacheKey = $this->getCacheKey();
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return array_merge($cached, ['cached' => true]);
        }

        // Call Anystack API
        $result = $this->callAnystackApi();

        // Cache the result
        $ttl = match ($result['status']) {
            'valid' => self::CACHE_TTL_VALID,
            'invalid', 'suspended', 'expired' => self::CACHE_TTL_INVALID,
            default => self::CACHE_TTL_ERROR,
        };

        Cache::put($cacheKey, $result, $ttl);

        return array_merge($result, ['cached' => false]);
    }

    /**
     * Check if license is valid (simple boolean).
     */
    public function isValid(): bool
    {
        return $this->validate()['valid'];
    }

    /**
     * Clear cached license status (force re-validation).
     */
    public function clearCache(): void
    {
        Cache::forget($this->getCacheKey());
    }

    /**
     * Call Anystack API to validate license.
     *
     * Uses validate-key endpoint which checks if license is valid for the fingerprint.
     * If fingerprint is not yet activated, falls back to activate-key.
     *
     * @return array{valid: bool, status: string, message: string}
     */
    private function callAnystackApi(): array
    {
        try {
            $fingerprint = $this->getFingerprint();

            // First try validate-key (for already activated licenses)
            $response = Http::withToken($this->apiKey)
                ->timeout(10)
                ->post(self::ANYSTACK_API_URL . "/products/{$this->productId}/licenses/validate-key", [
                    'key' => $this->licenseKey,
                    'scope' => [
                        'fingerprint' => $fingerprint,
                    ],
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $activation = $data['data'] ?? [];

                // activate-key returns activation object on success
                if (! empty($activation['id'])) {
                    return [
                        'valid' => true,
                        'status' => 'valid',
                        'message' => 'License is valid and activated',
                        'activation_id' => $activation['id'] ?? null,
                        'license_id' => $activation['license_id'] ?? null,
                    ];
                }

                // Check for meta.valid (validate-key format)
                $meta = $data['meta'] ?? [];
                if ($meta['valid'] ?? false) {
                    return [
                        'valid' => true,
                        'status' => 'valid',
                        'message' => 'License is valid',
                        'license_id' => $activation['id'] ?? null,
                    ];
                }

                // License exists but is not valid
                $status = $meta['status'] ?? 'invalid';

                // Handle FINGERPRINT_INVALID by trying to activate
                if ($status === 'FINGERPRINT_INVALID') {
                    return $this->tryActivateLicense($fingerprint);
                }

                return match ($status) {
                    'SUSPENDED' => [
                        'valid' => false,
                        'status' => 'suspended',
                        'message' => 'License has been suspended. Contact support@octadecimalhq.com.',
                    ],
                    'EXPIRED' => [
                        'valid' => false,
                        'status' => 'expired',
                        'message' => 'License has expired. Renew your license via Anystack.',
                    ],
                    default => [
                        'valid' => false,
                        'status' => 'invalid',
                        'message' => 'License key is invalid.',
                    ],
                };
            }

            // API returned error
            $errorData = $response->json();
            $errorMessage = $errorData['message'] ?? $response->body();

            Log::warning('Shell Gate: Anystack API error', [
                'status' => $response->status(),
                'body' => $response->body(),
                'json' => $errorData,
            ]);

            if ($response->status() === 404) {
                return [
                    'valid' => false,
                    'status' => 'not_found',
                    'message' => 'License key not found.',
                ];
            }

            if ($response->status() === 422) {
                // Validation error - extract message
                $errors = $errorData['errors'] ?? [];
                $firstError = reset($errors);
                $errorMsg = is_array($firstError) ? ($firstError[0] ?? $errorMessage) : $errorMessage;

                return [
                    'valid' => false,
                    'status' => 'validation_error',
                    'message' => $errorMsg,
                ];
            }

            return [
                'valid' => false,
                'status' => 'api_error',
                'message' => 'License verification failed. Please try again later.',
            ];
        } catch (\Exception $e) {
            Log::error('Shell Gate: License verification exception', [
                'error' => $e->getMessage(),
            ]);

            // On network errors, allow graceful degradation in production
            // (don't block users if Anystack is temporarily unavailable)
            return [
                'valid' => true, // Graceful degradation
                'status' => 'offline',
                'message' => 'License verification temporarily unavailable.',
            ];
        }
    }

    /**
     * Try to activate license for this fingerprint.
     *
     * Called when validate-key returns FINGERPRINT_INVALID (license exists but not activated for this domain).
     *
     * @return array{valid: bool, status: string, message: string}
     */
    private function tryActivateLicense(string $fingerprint): array
    {
        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(10)
                ->post(self::ANYSTACK_API_URL . "/products/{$this->productId}/licenses/activate-key", [
                    'key' => $this->licenseKey,
                    'fingerprint' => $fingerprint,
                    'hostname' => gethostname(),
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $activation = $data['data'] ?? [];

                return [
                    'valid' => true,
                    'status' => 'activated',
                    'message' => 'License activated for this domain',
                    'activation_id' => $activation['id'] ?? null,
                ];
            }

            // Check for specific errors
            $errorData = $response->json();
            $errorMessage = $errorData['message'] ?? '';

            if (str_contains($errorMessage, 'ACTIVATION_LIMIT')) {
                return [
                    'valid' => false,
                    'status' => 'activation_limit',
                    'message' => 'License activation limit reached. Upgrade your license or deactivate another domain.',
                ];
            }

            if (str_contains($errorMessage, 'FINGERPRINT_ALREADY_EXISTS')) {
                // Already activated - this is actually success
                return [
                    'valid' => true,
                    'status' => 'valid',
                    'message' => 'License is already activated for this domain',
                ];
            }

            return [
                'valid' => false,
                'status' => 'activation_failed',
                'message' => 'Could not activate license: ' . $errorMessage,
            ];
        } catch (\Exception $e) {
            Log::warning('Shell Gate: License activation failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'valid' => false,
                'status' => 'activation_error',
                'message' => 'License activation failed. Please try again later.',
            ];
        }
    }

    /**
     * Generate fingerprint for this installation.
     * Uses domain/hostname as the unique identifier.
     */
    private function getFingerprint(): string
    {
        $appUrl = config('app.url', '');
        $host = parse_url($appUrl, PHP_URL_HOST) ?: gethostname() ?: 'unknown';

        return $host;
    }

    /**
     * Get cache key for this license.
     */
    private function getCacheKey(): string
    {
        $keyHash = substr(md5($this->licenseKey ?? ''), 0, 8);

        return self::CACHE_KEY . ':' . $keyHash;
    }
}
