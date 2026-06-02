<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use PaymentsCentral\Client;

// ---------------------------------------------------------------------------
// Load .env (simple key=value parser — no library required)
// ---------------------------------------------------------------------------

function loadEnv(string $path): void
{
    if (!file_exists($path)) {
        return;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2)) + ['', ''];
        // Strip optional surrounding quotes
        $value = trim($value, '"\'');
        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

loadEnv(dirname(__DIR__) . '/.env');

$apiKey     = $_ENV['PAYMENTS_CENTRAL_API_KEY']    ?? '';
$merchantId = $_ENV['PAYMENTS_CENTRAL_MERCHANT_ID'] ?? '';
$baseUrl    = $_ENV['PAYMENTS_CENTRAL_BASE_URL']    ?? 'https://api.uat.payments-central.com';

// ---------------------------------------------------------------------------
// Routing helpers
// ---------------------------------------------------------------------------

$requestUri    = strtok($_SERVER['REQUEST_URI'] ?? '/', '?') ?: '/';
$requestMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

function route(string $method, string $path): bool
{
    global $requestMethod, $requestUri;
    return $requestMethod === $method && $requestUri === $path;
}

// ---------------------------------------------------------------------------
// HTML helpers
// ---------------------------------------------------------------------------

function htmlPage(string $title, string $body): string
{
    return <<<HTML
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{$title} — Payments Central Demo</title>
        <style>
            *, *::before, *::after { box-sizing: border-box; }
            body { font-family: system-ui, sans-serif; background: #f5f6fa; color: #1a1a2e; margin: 0; padding: 2rem; }
            h1 { color: #2d6cdf; margin-top: 0; }
            h2 { color: #444; }
            a { color: #2d6cdf; }
            .card { background: #fff; border-radius: 8px; padding: 1.5rem 2rem; max-width: 860px; margin: 0 auto 1.5rem; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
            .btn { display: inline-block; padding: .55rem 1.2rem; border-radius: 6px; border: none; background: #2d6cdf; color: #fff; font-size: .95rem; cursor: pointer; text-decoration: none; }
            .btn:hover { background: #1a52b8; }
            .btn-danger { background: #d63031; }
            .btn-danger:hover { background: #a82929; }
            pre { background: #1e1e2e; color: #cdd6f4; border-radius: 6px; padding: 1rem; overflow-x: auto; font-size: .88rem; }
            table { width: 100%; border-collapse: collapse; font-size: .9rem; }
            th { text-align: left; padding: .5rem .75rem; background: #eef1fb; border-bottom: 2px solid #d0d9f0; }
            td { padding: .5rem .75rem; border-bottom: 1px solid #e8ebf5; }
            tr:last-child td { border-bottom: none; }
            .badge { display: inline-block; padding: .2rem .55rem; border-radius: 4px; font-size: .78rem; font-weight: 600; }
            .badge-green { background: #d4f0dc; color: #1a6b30; }
            .badge-red   { background: #fde8e8; color: #9b1c1c; }
            .badge-grey  { background: #e5e7eb; color: #374151; }
            .notice { padding: .75rem 1rem; border-radius: 6px; margin-bottom: 1rem; }
            .notice-error { background: #fde8e8; border-left: 4px solid #d63031; }
            .notice-success { background: #d4f0dc; border-left: 4px solid #27ae60; }
            nav { max-width: 860px; margin: 0 auto 1rem; }
            nav a { margin-right: .75rem; }
        </style>
    </head>
    <body>
        <div class="card">
            <h1>Payments Central PHP Demo</h1>
            <nav>
                <a href="/">Home</a>
                <a href="/demo/transactions">List Transactions</a>
            </nav>
        </div>
        {$body}
    </body>
    </html>
    HTML;
}

function jsonBlock(mixed $data): string
{
    return '<pre>' . htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . '</pre>';
}

function statusBadge(string $status): string
{
    $class = match (strtolower($status)) {
        'completed', 'paid', 'success' => 'badge-green',
        'failed', 'cancelled', 'canceled', 'refunded' => 'badge-red',
        default => 'badge-grey',
    };
    return "<span class=\"badge {$class}\">" . htmlspecialchars($status) . '</span>';
}

// ---------------------------------------------------------------------------
// Guard: credentials must be present
// ---------------------------------------------------------------------------

if ($requestUri !== '/' && (empty($apiKey) || empty($merchantId))) {
    echo htmlPage('Configuration Error', <<<HTML
    <div class="card">
        <div class="notice notice-error">
            <strong>Missing credentials.</strong> Copy <code>.env.example</code> to <code>.env</code>
            and fill in your API key and merchant ID.
        </div>
        <a href="/" class="btn">Back to Home</a>
    </div>
    HTML);
    exit;
}

$client = new Client($apiKey, $merchantId, $baseUrl);

// ---------------------------------------------------------------------------
// Routes
// ---------------------------------------------------------------------------

// GET /
if (route('GET', '/')) {
    $configWarning = (empty($apiKey) || empty($merchantId))
        ? '<div class="notice notice-error"><strong>⚠ No credentials found.</strong> Copy <code>.env.example</code> to <code>.env</code> and fill in your API key and merchant ID, then restart the server.</div>'
        : '<div class="notice notice-success">Credentials loaded. Ready to run demos.</div>';

    echo htmlPage('Home', <<<HTML
    <div class="card">
        {$configWarning}
        <h2>Available Demo Actions</h2>
        <table>
            <tr><th>Action</th><th>Endpoint</th><th>Trigger</th></tr>
            <tr><td>Charge \$10.00 USD</td><td>POST /api/v1/transactions/charge</td>
                <td><form method="POST" action="/demo/charge" style="display:inline"><button class="btn">Run Charge</button></form></td></tr>
            <tr><td>List Transactions</td><td>GET /api/v1/transactions</td>
                <td><a href="/demo/transactions" class="btn">View List</a></td></tr>
            <tr><td>Get Transaction</td><td>GET /api/v1/transactions/:id</td>
                <td>
                    <form method="GET" action="/demo/transaction" style="display:inline;display:flex;gap:.5rem;align-items:center">
                        <input name="id" placeholder="Transaction ID" style="padding:.45rem .7rem;border:1px solid #ccd;border-radius:5px;font-size:.9rem;width:220px">
                        <button class="btn">Fetch</button>
                    </form>
                </td></tr>
            <tr><td>Refund Transaction</td><td>POST /api/v1/transactions/:id/refund</td>
                <td>
                    <form method="POST" action="/demo/refund" style="display:inline;display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
                        <input name="id" placeholder="Transaction ID" style="padding:.45rem .7rem;border:1px solid #ccd;border-radius:5px;font-size:.9rem;width:220px">
                        <button class="btn btn-danger">Refund</button>
                    </form>
                </td></tr>
            <tr><td>Hosted Checkout</td><td>POST /api/v1/checkout/sessions</td>
                <td><form method="POST" action="/demo/checkout" style="display:inline"><button class="btn">Start Checkout</button></form></td></tr>
        </table>
    </div>
    HTML);
    exit;
}

// POST /demo/charge
if (route('POST', '/demo/charge')) {
    try {
        $result = $client->charge([
            'amount'       => 1000,           // amount in cents
            'currency'     => 'USD',
            'gateway'      => 'stripe',
            'merchant_ref' => 'demo-' . time(),
            'description'  => 'PHP demo charge',
        ]);
        $body = '<div class="card"><h2>Charge Result</h2>'
              . '<div class="notice notice-success">Transaction created successfully.</div>'
              . jsonBlock($result)
              . '<p><a href="/" class="btn">Back to Home</a></p></div>';
    } catch (\RuntimeException $e) {
        $body = '<div class="card"><h2>Charge Failed</h2>'
              . '<div class="notice notice-error">' . htmlspecialchars($e->getMessage()) . '</div>'
              . '<p><a href="/" class="btn">Back to Home</a></p></div>';
    }
    echo htmlPage('Charge', $body);
    exit;
}

// GET /demo/transactions
if (route('GET', '/demo/transactions')) {
    try {
        $result       = $client->listTransactions(page: 1, limit: 10);
        $transactions = $result['data'] ?? $result['transactions'] ?? $result;

        $rows = '';
        if (is_array($transactions) && count($transactions) > 0) {
            foreach ($transactions as $tx) {
                $id       = htmlspecialchars((string) ($tx['id'] ?? '—'));
                $ref      = htmlspecialchars((string) ($tx['merchant_ref'] ?? '—'));
                $amount   = isset($tx['amount']) ? number_format((float) $tx['amount'] / 100, 2) : '—';
                $currency = htmlspecialchars((string) ($tx['currency'] ?? ''));
                $status   = statusBadge((string) ($tx['status'] ?? 'unknown'));
                $created  = htmlspecialchars((string) ($tx['created_at'] ?? '—'));
                $rows    .= "<tr>
                    <td><a href=\"/demo/transaction?id={$id}\">{$id}</a></td>
                    <td>{$ref}</td>
                    <td>{$amount} {$currency}</td>
                    <td>{$status}</td>
                    <td>{$created}</td>
                </tr>";
            }
        } else {
            $rows = '<tr><td colspan="5" style="text-align:center;color:#888">No transactions found.</td></tr>';
        }

        $body = <<<HTML
        <div class="card">
            <h2>Recent Transactions (last 10)</h2>
            <table>
                <tr><th>ID</th><th>Merchant Ref</th><th>Amount</th><th>Status</th><th>Created</th></tr>
                {$rows}
            </table>
            <p style="margin-top:1rem"><a href="/" class="btn">Back to Home</a></p>
        </div>
        HTML;
    } catch (\RuntimeException $e) {
        $body = '<div class="card"><h2>Error</h2>'
              . '<div class="notice notice-error">' . htmlspecialchars($e->getMessage()) . '</div>'
              . '<p><a href="/" class="btn">Back to Home</a></p></div>';
    }
    echo htmlPage('Transactions', $body);
    exit;
}

// GET /demo/transaction?id=xxx
if (route('GET', '/demo/transaction')) {
    $id = trim($_GET['id'] ?? '');
    if ($id === '') {
        header('Location: /');
        exit;
    }
    try {
        $result = $client->getTransaction($id);
        $body   = '<div class="card"><h2>Transaction ' . htmlspecialchars($id) . '</h2>'
                . jsonBlock($result)
                . '<p><a href="/demo/transactions" class="btn">Back to List</a></p></div>';
    } catch (\RuntimeException $e) {
        $body = '<div class="card"><h2>Error</h2>'
              . '<div class="notice notice-error">' . htmlspecialchars($e->getMessage()) . '</div>'
              . '<p><a href="/demo/transactions" class="btn">Back to List</a></p></div>';
    }
    echo htmlPage('Transaction', $body);
    exit;
}

// POST /demo/refund  (id from form body or query string)
if (route('POST', '/demo/refund')) {
    $id = trim($_POST['id'] ?? $_GET['id'] ?? '');
    if ($id === '') {
        header('Location: /');
        exit;
    }
    try {
        // A fresh charge is `pending`, and core only refunds captured/settled
        // transactions ("Can only refund captured or settled transactions").
        // Drive the full lifecycle: charge -> authorize -> capture -> refund.
        $client->authorize($id);
        $captured = $client->capture($id);
        // core requires an explicit refund `amount` (minor units); refund the
        // full captured amount.
        $amount = (int) ($captured['amount'] ?? 0);
        $result = $client->refund($id, [
            'amount' => $amount,
            'reason' => 'requested_by_customer',
        ]);
        $body   = '<div class="card"><h2>Refund Result</h2>'
                . '<div class="notice notice-success">Refund submitted for transaction ' . htmlspecialchars($id) . '.</div>'
                . jsonBlock($result)
                . '<p><a href="/demo/transactions" class="btn">Back to List</a></p></div>';
    } catch (\RuntimeException $e) {
        $body = '<div class="card"><h2>Refund Failed</h2>'
              . '<div class="notice notice-error">' . htmlspecialchars($e->getMessage()) . '</div>'
              . '<p><a href="/" class="btn">Back to Home</a></p></div>';
    }
    echo htmlPage('Refund', $body);
    exit;
}

// POST /demo/checkout — create hosted checkout session and redirect
if (route('POST', '/demo/checkout')) {
    try {
        $baseAppUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
                    . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost:8000');

        $result = $client->createCheckoutSession([
            'amount'      => 2500,                        // $25.00 in cents
            'currency'    => 'USD',
            'gateway'     => 'stripe',                    // core requires a gateway
            'description' => 'PHP demo checkout',
            'success_url' => $baseAppUrl . '/demo/success',
            'cancel_url'  => $baseAppUrl . '/demo/cancel',
            'type'        => 'one_time',
        ]);

        $checkoutUrl = $result['checkout_url'] ?? null;
        if ($checkoutUrl) {
            header('Location: ' . $checkoutUrl);
            exit;
        }

        // Fallback: show the full response if no redirect URL
        $body = '<div class="card"><h2>Checkout Session Created</h2>'
              . '<div class="notice notice-success">Session created — no redirect URL returned.</div>'
              . jsonBlock($result)
              . '<p><a href="/" class="btn">Back to Home</a></p></div>';
    } catch (\RuntimeException $e) {
        $body = '<div class="card"><h2>Checkout Failed</h2>'
              . '<div class="notice notice-error">' . htmlspecialchars($e->getMessage()) . '</div>'
              . '<p><a href="/" class="btn">Back to Home</a></p></div>';
    }
    echo htmlPage('Checkout', $body);
    exit;
}

// GET /demo/success
if (route('GET', '/demo/success')) {
    echo htmlPage('Payment Completed', <<<HTML
    <div class="card" style="text-align:center;padding:3rem 2rem">
        <div style="font-size:3rem">✅</div>
        <h2>Payment completed!</h2>
        <p>Thank you — your payment was processed successfully.</p>
        <a href="/" class="btn">Back to Home</a>
    </div>
    HTML);
    exit;
}

// GET /demo/cancel
if (route('GET', '/demo/cancel')) {
    echo htmlPage('Payment Cancelled', <<<HTML
    <div class="card" style="text-align:center;padding:3rem 2rem">
        <div style="font-size:3rem">❌</div>
        <h2>Payment cancelled.</h2>
        <p>You cancelled the checkout session. No charge was made.</p>
        <a href="/" class="btn">Back to Home</a>
    </div>
    HTML);
    exit;
}

// 404
http_response_code(404);
echo htmlPage('Not Found', <<<HTML
<div class="card">
    <h2>404 — Page Not Found</h2>
    <p>The route <code>{$requestUri}</code> does not exist in this demo.</p>
    <a href="/" class="btn">Back to Home</a>
</div>
HTML);
