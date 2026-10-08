<?php
require __DIR__ . '/../src/config.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_response(['ResultCode' => 1, 'ResultDesc' => 'POST only'], 405);
}

$raw = file_get_contents('php://input');
if ($raw === false || strlen($raw) > 65536) {
    json_response(['ResultCode' => 1, 'ResultDesc' => 'Invalid payload size'], 413);
}
$data = json_decode($raw ?: '', true);
$cb = $data['Body']['stkCallback'] ?? null;
if (!is_array($cb) || !isset($cb['CheckoutRequestID'], $cb['ResultCode']) ||
    !is_scalar($cb['CheckoutRequestID']) || !is_numeric($cb['ResultCode'])) {
    json_response(['ResultCode' => 1, 'ResultDesc' => 'Invalid callback'], 400);
}

$checkout = trim((string)$cb['CheckoutRequestID']);
if ($checkout === '') json_response(['ResultCode' => 1, 'ResultDesc' => 'Missing checkout ID'], 400);
$code = (int)$cb['ResultCode'];
$status = $code === 0 ? 'success' : ($code === 1032 ? 'cancelled' : 'failed');
$description = isset($cb['ResultDesc']) ? (string)$cb['ResultDesc'] : null;
$receipt = null;
if ($code === 0) {
    foreach (($cb['CallbackMetadata']['Item'] ?? []) as $item) {
        if (is_array($item) && ($item['Name'] ?? '') === 'MpesaReceiptNumber' &&
            isset($item['Value']) && is_scalar($item['Value'])) {
            $receipt = trim((string)$item['Value']);
            if ($receipt === '') $receipt = null;
            break;
        }
    }
}

$pdo = null;
try {
    $pdo = db();
    $pdo->beginTransaction();
    $pdo->prepare('INSERT INTO mpesa_callbacks (payload) VALUES (?)')->execute([$raw]);
    $stmt = $pdo->prepare('SELECT id, status, mpesa_receipt FROM payments WHERE checkout_request_id = ? FOR UPDATE');
    $stmt->execute([$checkout]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($payment) {
        if ($payment['status'] === 'pending') {
            $pdo->prepare('UPDATE payments SET status=?, result_code=?, result_desc=?, mpesa_receipt=? WHERE id=?')
                ->execute([$status, $code, $description, $receipt, $payment['id']]);
        } elseif ($payment['status'] === 'success' && $code === 0 &&
                  ($payment['mpesa_receipt'] === null || $payment['mpesa_receipt'] === '') && $receipt !== null) {
            // Status query may have confirmed success before the callback delivered the receipt.
            $pdo->prepare('UPDATE payments SET mpesa_receipt=?, result_code=?, result_desc=? WHERE id=? AND status=? AND mpesa_receipt IS NULL')
                ->execute([$receipt, $code, $description, $payment['id'], 'success']);
        } elseif ($payment['status'] !== $status ||
                  ($receipt !== null && $payment['mpesa_receipt'] !== null && $payment['mpesa_receipt'] !== $receipt)) {
            error_log('M-Pesa conflicting callback for checkout ' . $checkout . ': existing=' . $payment['status'] . ', incoming=' . $status);
        }
    } else {
        error_log('M-Pesa callback has no matching payment: ' . $checkout);
    }
    $pdo->commit();
    json_response(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    error_log('M-Pesa callback processing error: ' . $e->getMessage());
    json_response(['ResultCode' => 1, 'ResultDesc' => 'Processing failed'], 500);
}
