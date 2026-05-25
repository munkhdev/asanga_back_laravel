<?php
// hidden file, only for reference
declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class QpayClient
{
    public function issueToken(): array
    {
        $base = $this->baseUrl();
        $username = (string) config('qpay.username', '');
        $password = (string) config('qpay.password', '');

        if ($username === '' || $password === '') {
            throw new RuntimeException('QPay credentials missing');
        }

        $res = Http::withBasicAuth($username, $password)
            ->acceptJson()
            ->post($base . 'auth/token', []);

        if ($res->failed()) {
            throw new RuntimeException('QPay token request failed: ' . $res->body());
        }

        $json = (array) $res->json();
        $accessToken = (string) ($json['access_token'] ?? '');
        if ($accessToken === '') {
            throw new RuntimeException('QPay token missing in response');
        }

        return [
            'access_token' => $accessToken,
            'token_type' => (string) ($json['token_type'] ?? 'Bearer'),
            'expires_in' => $json['expires_in'] ?? null,
            'raw' => $json,
        ];
    }

    public function createInvoice(array $payload): array
    {
        $base = $this->baseUrl();
        $token = $this->requestToken();

        $invoice = [
            'invoice_code' => config('qpay.invoice_code'),
            'sender_invoice_no' => $payload['senderInvoiceNo'],
            'sender_branch_code' => config('qpay.sender_branch_code', 'branch'),
            'invoice_receiver_code' => config('qpay.receiver_code', 'terminal'),
            'invoice_receiver_data' => [
                'phone' => config('qpay.receiver_phone', '88200314'),
            ],
            'invoice_description' => $payload['description'] ?? config('qpay.invoice_description', 'Төлбөр'),
            'callback_url' => $payload['callbackUrl'],
            'lines' => [[
                'tax_product_code' => (string) random_int(1000, 99999),
                'line_description' => $payload['description'] ?? 'Төлбөр',
                'line_quantity' => 1,
                'line_unit_price' => (float) ($payload['amount'] ?? 0),
            ]],
        ];

        $res = Http::withToken($token)
            ->acceptJson()
            ->post($base . 'invoice', $invoice);

        if ($res->failed()) {
            throw new RuntimeException('QPay invoice creation failed: ' . $res->body());
        }

        return (array) $res->json();
    }

    public function checkInvoicePayment(string $invoiceId): array
    {
        $base = $this->baseUrl();
        $token = $this->requestToken();

        $res = Http::withToken($token)
            ->acceptJson()
            ->post($base . 'payment/check', [
                'object_type' => 'INVOICE',
                'object_id' => $invoiceId,
                'offset' => [
                    'page_number' => 1,
                    'page_limit' => 100,
                ],
            ]);

        if ($res->failed()) {
            throw new RuntimeException('QPay status check failed: ' . $res->body());
        }

        $json = (array) $res->json();
        $status = data_get($json, 'rows.0.payment_status');

        return [
            'paymentStatus' => $status,
            'raw' => $json,
        ];
    }

    private function requestToken(): string
    {
        return (string) ($this->issueToken()['access_token'] ?? '');
    }

    private function baseUrl(): string
    {
        $raw = trim((string) config('qpay.base_url', ''));
        if ($raw === '') {
            throw new RuntimeException('QPAY_URL is missing');
        }

        $normalized = preg_replace('#/(auth/token|invoice|payment/check)/?$#i', '/', $raw) ?: $raw;
        if (!Str::endsWith($normalized, '/')) {
            $normalized .= '/';
        }

        return $normalized;
    }
}
