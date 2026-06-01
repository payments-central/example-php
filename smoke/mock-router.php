<?php

declare(strict_types=1);

/**
 * Recorded mock of the Payments Central core API for the CI smoke test.
 *
 * It asserts that every request the demo app sends matches core's current
 * contract and appends any mismatch to SMOKE_FAIL_FILE. Returns canned
 * responses so the demo flow can complete end to end. No secrets required.
 *
 * Run via: php -S 127.0.0.1:<port> smoke/mock-router.php
 */

$failFile = getenv('SMOKE_FAIL_FILE') ?: sys_get_temp_dir() . '/php-smoke-failures.txt';
$mockPort = getenv('MOCK_PORT') ?: '4021';

function fail(string $message): void
{
    global $failFile;
    file_put_contents($failFile, $message . "\n", FILE_APPEND);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri    = strtok($_SERVER['REQUEST_URI'] ?? '/', '?') ?: '/';
$raw    = file_get_contents('php://input');
$body   = json_decode($raw ?: '{}', true);
if (!is_array($body)) {
    $body = [];
}

// Auth headers must be present on every call.
$auth       = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$merchantId = $_SERVER['HTTP_X_MERCHANT_ID'] ?? '';
if (!str_starts_with($auth, 'Bearer ')) {
    fail("$method $uri: missing \"Authorization: Bearer\" header");
}
if ($merchantId === '') {
    fail("$method $uri: missing x-merchant-id header");
}

header('Content-Type: application/json');

$tx = [
    'id'           => 'txn_smoke_1',
    'amount'       => 1000,
    'currency'     => 'USD',
    'status'       => 'completed',
    'gateway'      => 'stripe',
    'merchant_ref' => 'demo-smoke',
    'description'  => 'smoke',
    'created_at'   => '2026-01-01T00:00:00Z',
    'updated_at'   => '2026-01-01T00:00:00Z',
];

if ($method === 'POST' && $uri === '/api/v1/transactions/charge') {
    if (!isset($body['amount']) || !is_numeric($body['amount']) || $body['amount'] <= 0) {
        fail('charge: amount must be a positive number (minor units)');
    }
    if (empty($body['currency'])) {
        fail('charge: currency required');
    }
    if (empty($body['gateway'])) {
        fail('charge: gateway required');
    }
    http_response_code(201);
    echo json_encode(array_merge($tx, ['amount' => $body['amount'] ?? 1000]));
    exit;
}

if ($method === 'GET' && $uri === '/api/v1/transactions') {
    parse_str($_SERVER['QUERY_STRING'] ?? '', $q);
    if (!array_key_exists('page', $q)) {
        fail('list: missing `page` query param (core paginates with page/limit)');
    }
    if (!array_key_exists('limit', $q)) {
        fail('list: missing `limit` query param');
    }
    if (array_key_exists('offset', $q)) {
        fail('list: `offset` is not a core param and must not be sent');
    }
    echo json_encode([
        'data'  => [$tx],
        'total' => 1,
        'page'  => (int) ($q['page'] ?? 1),
        'limit' => (int) ($q['limit'] ?? 10),
    ]);
    exit;
}

if ($method === 'GET' && preg_match('#^/api/v1/transactions/[^/]+$#', $uri)) {
    echo json_encode($tx);
    exit;
}

if ($method === 'POST' && preg_match('#^/api/v1/transactions/[^/]+/refund$#', $uri)) {
    if (!isset($body['amount']) || !is_numeric($body['amount'])) {
        fail('refund: core requires a numeric `amount`');
    }
    echo json_encode(array_merge($tx, ['status' => 'refunded']));
    exit;
}

if ($method === 'POST' && $uri === '/api/v1/checkout/sessions') {
    foreach (['amount', 'currency', 'gateway', 'success_url', 'cancel_url'] as $key) {
        if (!isset($body[$key]) || $body[$key] === '') {
            fail("checkout: $key required");
        }
    }
    echo json_encode([
        'session_id'   => 'cs_smoke',
        'checkout_url' => "http://127.0.0.1:$mockPort/pay?s=cs_smoke",
        'type'         => $body['type'] ?? 'redirect',
        'expires_at'   => '2026-01-01T01:00:00Z',
    ]);
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'not found']);
