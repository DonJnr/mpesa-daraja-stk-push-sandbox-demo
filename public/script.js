const form = document.getElementById('form');
const msg = document.getElementById('msg');
const btn = document.getElementById('btn');
let active = false;
const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));
async function getJSON(url, options) {
  const response = await fetch(url, { ...options, cache: 'no-store' });
  const data = await response.json();
  if (!response.ok) throw new Error(data.error || 'Request failed');
  return data;
}
form.addEventListener('submit', async event => {
  event.preventDefault();
  if (active) return;
  active = true; btn.disabled = true;
  msg.textContent = 'Sending STK Push request…';
  try {
    const result = await getJSON('stk_push.php', { method: 'POST', body: new FormData(form) });
    msg.textContent = (result.message || 'Request sent. Awaiting payment result…') + '\nCheckoutRequestID: ' + result.checkout_request_id + '\n' + (result.daraja_description || '');
    await poll(result.checkout_request_id);
  } catch (error) {
    msg.textContent = 'Error: ' + error.message;
  } finally {
    active = false; btn.disabled = false;
  }
});
async function poll(id) {
  // Continue briefly after success without a receipt to allow a late callback.
  const started = Date.now();
  let confirmedAt = null;
  while (Date.now() - started < 180000) {
    await sleep(3000);
    let payment;
    try {
      payment = await getJSON('status.php?checkout_request_id=' + encodeURIComponent(id));
    } catch (error) {
      msg.textContent = 'Status check unavailable: ' + error.message + '\nDo not pay again until you verify this transaction.';
      return;
    }
    if (payment.status === 'success') {
      if (payment.receipt) {
        msg.textContent = 'Payment confirmed.\nAmount: KES ' + payment.amount + '\nM-Pesa transaction code: ' + payment.receipt;
        return;
      }
      msg.textContent = 'Payment reported successful.\nAmount: KES ' + payment.amount + '\nM-Pesa transaction code: awaiting callback.';
      if (confirmedAt === null) confirmedAt = Date.now();
      if (Date.now() - confirmedAt >= 30000) return;
    } else if (payment.status === 'failed' || payment.status === 'cancelled') {
      msg.textContent = 'Payment ' + payment.status + '. ' + (payment.message || '');
      return;
    } else {
      msg.textContent = 'Awaiting payment confirmation…' + (payment.query_error ? '\n' + payment.query_error : '');
    }
  }
  msg.textContent = 'Payment status is still pending. Check your M-Pesa messages and transaction history before trying again.';
}
