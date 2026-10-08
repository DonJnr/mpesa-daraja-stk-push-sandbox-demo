<?php
require __DIR__ . '/../src/config.php';
require __DIR__ . '/../src/Daraja.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') json_response(['error' => 'POST only'], 405);
if (!is_string($_POST['phone'] ?? null) || !is_string($_POST['amount'] ?? null) ||
    (isset($_POST['account_ref']) && !is_string($_POST['account_ref']))) {
    json_response(['error' => 'Invalid form fields'], 422);
}
$phone = Daraja::normalizePhone($_POST['phone']);
$amountRaw = (string)($_POST['amount'] ?? '');
$amount = filter_var($amountRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$ref = trim((string)($_POST['account_ref'] ?? ''));
if ($ref === '') $ref = 'ORDER' . time();
if (!$phone) json_response(['error' => 'Invalid phone number'], 422);
if ($amount === false || $amount > 150000) json_response(['error' => 'Amount must be a whole number between 1 and 150000'], 422);
if (strlen($ref) > 40) json_response(['error' => 'Reference must be 40 characters or fewer'], 422);

$pdo = null;
$paymentId = null;
try {
    $pdo = db();
    $pdo->prepare('INSERT INTO payments (account_ref, phone, amount) VALUES (?,?,?)')->execute([$ref, $phone, $amount]);
    $paymentId = (int)$pdo->lastInsertId();
    $res = (new Daraja())->stkPush($phone, $amount, $ref);
    if ((string)($res['ResponseCode'] ?? '') !== '0' || empty($res['CheckoutRequestID'])) {
        $pdo->prepare("UPDATE payments SET status='failed', result_desc=? WHERE id=?")
            ->execute([$res['errorMessage'] ?? $res['ResponseDescription'] ?? 'STK Push not accepted', $paymentId]);
        json_response(['error' => 'Daraja did not accept the STK Push: ' . (string)($res['errorMessage'] ?? $res['ResponseDescription'] ?? 'Unknown reason')], 502);
    }
    $pdo->prepare('UPDATE payments SET merchant_request_id=?, checkout_request_id=? WHERE id=?')
        ->execute([$res['MerchantRequestID'] ?? null, $res['CheckoutRequestID'], $paymentId]);
    json_response(['ok' => true, 'checkout_request_id' => $res['CheckoutRequestID'], 'message' => 'Daraja accepted the STK request (ResponseCode 0). Check your phone; acceptance does not guarantee delivery.', 'daraja_response_code' => (string)$res['ResponseCode'], 'daraja_description' => (string)($res['ResponseDescription'] ?? ''), 'environment' => 'sandbox']);
} catch (Throwable $e) {
    error_log('STK Push error: ' . $e->getMessage());
    // A transport timeout does not prove the request failed. Keep the record pending.
    json_response(['error' => 'Unable to complete request. Check transaction history before retrying.'], 500);
}
