#!/usr/bin/env php
<?php

/**
 * Verify Shell Gate license configuration using .env in package root.
 * Reads anystack-api-key / shellgate-customer-runtime-api-key and optional license key,
 * then calls Anystack API to validate.
 *
 * Usage: php scripts/verify-license-config.php [path-to-.env]
 * Default .env path: directory above script (package root).
 */

$envPath = $argv[1] ?? dirname(__DIR__) . '/.env';
if (!is_readable($envPath)) {
    fwrite(STDERR, "Error: .env not found or not readable: {$envPath}\n");
    exit(1);
}

$env = parseEnvFile($envPath);
$apiKey = $env['ANYSTACK_CUSTOMER_API_KEY']
    ?? $env['shellgate-customer-runtime-api-key']
    ?? $env['anystack-api-key']
    ?? null;
$licenseKey = $env['SHELL_GATE_LICENSE_KEY']
    ?? $env['shellgate-license-key']
    ?? null;

$productId = 'a108fd3f-8389-40f5-beac-a1767ba70724';
$apiUrl = "https://api.anystack.sh/v1/products/{$productId}/licenses/validate-key";

echo "Shell Gate – license config check\n";
echo "  .env: {$envPath}\n";
echo "  API key: " . ($apiKey ? '***' . substr($apiKey, -6) : '<not set>') . "\n";
echo "  License key: " . ($licenseKey ? '***' . substr($licenseKey, -8) : '<not set>') . "\n\n";

if (empty($apiKey)) {
    fwrite(STDERR, "Error: No Anystack API key in .env. Set one of:\n");
    fwrite(STDERR, "  ANYSTACK_CUSTOMER_API_KEY=...\n");
    fwrite(STDERR, "  shellgate-customer-runtime-api-key=...\n");
    fwrite(STDERR, "  anystack-api-key=...\n");
    exit(1);
}

if (empty($licenseKey)) {
    echo "License key not set – checking only that Anystack API key is accepted...\n\n";
    $licenseKey = 'placeholder-to-test-api-key';
}

// Simple fingerprint for validation
$fingerprint = gethostname() . ':verify-script';

$payload = json_encode([
    'key' => $licenseKey,
    'scope' => ['fingerprint' => $fingerprint],
]);

$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Bearer ' . $apiKey,
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
]);
$response = curl_exec($ch);
$status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
if (PHP_VERSION_ID < 80500) {
    curl_close($ch);
}

$data = $response ? json_decode($response, true) : [];

if ($status === 0) {
    echo "Error: Could not reach Anystack API (network error).\n";
    exit(1);
}

if ($status === 401) {
    echo "Result: API key rejected (401). Check that anystack-api-key / shellgate-customer-runtime-api-key is correct.\n";
    exit(1);
}

if ($status >= 200 && $status < 300) {
    $valid = $data['meta']['valid'] ?? !empty($data['data']['id']);
    echo "Result: Configuration is valid. Anystack API accepted the request.\n";
    echo "  License status: " . ($valid ? 'valid' : 'check response') . "\n";
    if (!empty($data['data'])) {
        echo "  Data: " . json_encode($data['data'], JSON_PRETTY_PRINT) . "\n";
    }
    exit(0);
}

// 404/422 with valid API key = license key invalid or not found (API key is OK)
if (in_array($status, [404, 422], true)) {
    echo "Result: Anystack API key is valid (request was accepted).\n";
    echo "  License key is missing or invalid (HTTP {$status}).\n";
    echo "  Add SHELL_GATE_LICENSE_KEY or shellgate-license-key to .env with your purchase key to complete setup.\n";
    exit(0);
}

echo "Result: API returned HTTP {$status}\n";
echo "  Body: " . (strlen($response ?? '') > 200 ? substr($response, 0, 200) . '...' : ($response ?: '(empty)')) . "\n";
$message = $data['message'] ?? ($data['errors'][0][0] ?? 'Unknown error');
echo "  Message: {$message}\n";
exit(1);

function parseEnvFile(string $path): array
{
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $env = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if (preg_match('/^["\'](.*)["\']$/s', $value, $m)) {
            $value = $m[1];
        }
        $env[$key] = $value;
    }
    return $env;
}
