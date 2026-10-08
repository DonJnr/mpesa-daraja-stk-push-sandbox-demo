<?php
class Daraja
{
    private string $base;

    public function __construct()
    {
        if (env('MPESA_ENV', 'sandbox') !== 'sandbox') {
            throw new RuntimeException('This demo supports Daraja sandbox only.');
        }
        $this->base = 'https://sandbox.safaricom.co.ke';
    }

    /** Tokens last ~1 hour; cache in a temp file to avoid fetching per request. */

    public function token(): string
    {
        $cache = sys_get_temp_dir() . '/daraja_token_' . md5(env('MPESA_CONSUMER_KEY')) . '.json';
        if (is_file($cache)) {
            $c = json_decode(file_get_contents($cache), true);
            if ($c && $c['expires'] > time() + 30) return $c['token'];
        }
        $res = $this->request('GET', '/oauth/v1/generate?grant_type=client_credentials', null,
            ['Authorization: Basic ' . base64_encode(env('MPESA_CONSUMER_KEY') . ':' . env('MPESA_CONSUMER_SECRET'))]);
        if (empty($res['access_token'])) throw new RuntimeException('Could not get Daraja token');
        file_put_contents($cache, json_encode(['token' => $res['access_token'], 'expires' => time() + 3300]));
        return $res['access_token'];
    }

    public function stkPush(string $phone, int $amount, string $accountRef, string $desc = 'Payment'): array
    {
        [$password, $timestamp] = $this->password();
        return $this->authed('POST', '/mpesa/stkpush/v1/processrequest', [
            'BusinessShortCode' => env('MPESA_SHORTCODE'),
            'Password'          => $password,
            'Timestamp'         => $timestamp,
            'TransactionType'   => 'CustomerPayBillOnline', // use CustomerBuyGoodsOnline for a Till
            'Amount'            => $amount,
            'PartyA'            => $phone,
            'PartyB'            => env('MPESA_SHORTCODE'),
            'PhoneNumber'       => $phone,
            'CallBackURL'       => env('MPESA_CALLBACK_URL'),
            'AccountReference'  => substr($accountRef, 0, 12),
            'TransactionDesc'   => substr($desc, 0, 13),
        ]);
    }

    public function stkQuery(string $checkoutRequestId): array
    {
        [$password, $timestamp] = $this->password();
        return $this->authed('POST', '/mpesa/stkpushquery/v1/query', [
            'BusinessShortCode' => env('MPESA_SHORTCODE'),
            'Password'          => $password,
            'Timestamp'         => $timestamp,
            'CheckoutRequestID' => $checkoutRequestId,
        ]);
    }

    /** 07xx / 01xx / +254 / 254 -> 2547xxxxxxxx; returns null if invalid */
    public static function normalizePhone(string $p): ?string
    {
        $p = preg_replace('/\D/', '', $p);
        if (preg_match('/^0([17]\d{8})$/', $p, $m)) return '254' . $m[1];
        if (preg_match('/^254([17]\d{8})$/', $p, $m)) return '254' . $m[1];
        if (preg_match('/^([17]\d{8})$/', $p, $m)) return '254' . $m[1];
        return null;
    }

    private function password(): array
    {
        $ts = date('YmdHis');
        return [base64_encode(env('MPESA_SHORTCODE') . env('MPESA_PASSKEY') . $ts), $ts];
    }

    private function authed(string $method, string $path, array $body): array
    {
        return $this->request($method, $path, $body, ['Authorization: Bearer ' . $this->token()]);
    }

    private function request(string $method, string $path, ?array $body, array $headers): array
    {
        $ch = curl_init($this->base . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => array_merge(['Content-Type: application/json'], $headers),
            CURLOPT_TIMEOUT        => 30,
        ]);
        if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        $out = curl_exec($ch);
        if ($out === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('Daraja transport error: ' . $error);
        }
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $decoded = json_decode($out, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Daraja returned invalid JSON (HTTP ' . $httpCode . ')');
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException('Daraja request rejected (HTTP ' . $httpCode . '): ' . ($decoded['errorMessage'] ?? $decoded['error_description'] ?? 'Unknown error'));
        }
        return $decoded;
    }
}
