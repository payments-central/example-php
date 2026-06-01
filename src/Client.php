<?php

declare(strict_types=1);

namespace PaymentsCentral;

class Client
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $merchantId,
        private readonly string $baseUrl = 'https://api.uat.payments-central.com',
    ) {}

    /**
     * Execute a cURL request against the Payments Central API.
     *
     * @param array<string, mixed> $body
     * @param array<string, string> $extraHeaders
     * @return array<string, mixed>
     * @throws \RuntimeException
     */
    private function request(
        string $method,
        string $path,
        array $body = [],
        array $extraHeaders = [],
    ): array {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');

        $headers = array_merge([
            'Authorization: Bearer ' . $this->apiKey,
            'x-merchant-id: ' . $this->merchantId,
            'Accept: application/json',
            'Content-Type: application/json',
        ], $extraHeaders);

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        ]);

        if (!empty($body)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_THROW_ON_ERROR));
        } elseif (strtoupper($method) === 'POST') {
            // POST with empty body still needs Content-Length: 0
            curl_setopt($ch, CURLOPT_POSTFIELDS, '{}');
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException('cURL error: ' . $curlError);
        }

        /** @var array<string, mixed>|null $data */
        $data = json_decode((string) $response, associative: true);

        if (!is_array($data)) {
            throw new \RuntimeException(
                sprintf('Unexpected API response (HTTP %d): %s', $httpCode, $response)
            );
        }

        if ($httpCode >= 400) {
            $message = $data['message'] ?? $data['error'] ?? ('HTTP ' . $httpCode);
            throw new \RuntimeException((string) $message, $httpCode);
        }

        return $data;
    }

    /**
     * Charge a payment.
     *
     * Required keys: amount, currency, gateway, merchant_ref, description
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function charge(array $params): array
    {
        return $this->request('POST', '/api/v1/transactions/charge', $params);
    }

    /**
     * Retrieve a single transaction by ID.
     *
     * @return array<string, mixed>
     */
    public function getTransaction(string $id): array
    {
        return $this->request('GET', '/api/v1/transactions/' . rawurlencode($id));
    }

    /**
     * List transactions.
     *
     * The core API paginates with `page` (1-based) and `limit`; an `offset`
     * parameter is silently ignored. Response shape: { data, total, page, limit }.
     *
     * @return array<string, mixed>
     */
    public function listTransactions(int $page = 1, int $limit = 10): array
    {
        $query = http_build_query(compact('page', 'limit'));
        return $this->request('GET', '/api/v1/transactions?' . $query);
    }

    /**
     * Refund a transaction (fully or partially).
     *
     * The core API requires an `amount` (in minor units / cents); there is no
     * "omit amount for full refund" behaviour — pass the original transaction
     * amount for a full refund. Any `reason` is accepted but ignored by core.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function refund(string $id, array $params): array
    {
        return $this->request('POST', '/api/v1/transactions/' . rawurlencode($id) . '/refund', $params);
    }

    /**
     * Create a hosted checkout session.
     *
     * Required keys: amount, currency, description, success_url, cancel_url, type
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function createCheckoutSession(array $params): array
    {
        return $this->request('POST', '/api/v1/checkout/sessions', $params);
    }
}
