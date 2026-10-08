<?php
require __DIR__ . '/../src/config.php';
require __DIR__ . '/../src/Daraja.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') json_response(['error' => 'GET only'], 405);
if (!is_string($_GET['checkout_request_id'] ?? null)) json_response(['error' => 'Invalid checkout_request_id'], 422);
$id = trim($_GET['checkout_request_id']);
if ($id === '') json_response(['error' => 'Missing checkout_request_id'], 422);

try {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT status, result_code, result_desc, mpesa_receipt, amount, created_at FROM payments WHERE checkout_request_id=?');
    $stmt->execute([$id]);
    $p = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$p) json_response(['error' => 'Payment not found'], 404);
    $queryError = null;

    if ($p['status'] === 'pending' && strtotime($p['created_at']) !== false && time() - strtotime($p['created_at']) >= 60) {
        try {
            $q = (new Daraja())->stkQuery($id);
            if (isset($q['ResultCode']) && is_numeric($q['ResultCode'])) {
                $code = (int)$q['ResultCode'];
                $status = $code === 0 ? 'success' : ($code === 1032 ? 'cancelled' : 'failed');
                $pdo->prepare("UPDATE payments SET status=?, result_code=?, result_desc=? WHERE checkout_request_id=? AND status='pending'")
                    ->execute([$status, $code, $q['ResultDesc'] ?? null, $id]);
            } else {
                $queryError = 'No final payment result available from Safaricom';
                error_log('M-Pesa query inconclusive for ' . $id . ': ' . json_encode($q));
            }
        } catch (Throwable $e) {
            $queryError = 'Unable to query Safaricom';
            error_log('M-Pesa query exception: ' . $e->getMessage());
        }
        $stmt->execute([$id]);
        $p = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    header('Cache-Control: no-store');
    json_response([
        'status' => $p['status'],
        'message' => $p['result_desc'],
        'receipt' => $p['mpesa_receipt'],
        'amount' => (int)$p['amount'],
        'result_code' => $p['result_code'] === null ? null : (int)$p['result_code'],
        'query_error' => $queryError,
    ]);
} catch (Throwable $e) {
    error_log('Payment status error: ' . $e->getMessage());
    json_response(['error' => 'Unable to retrieve payment status'], 500);
}
