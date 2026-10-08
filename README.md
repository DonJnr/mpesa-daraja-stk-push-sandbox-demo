# M-Pesa Daraja STK Push — Sandbox Demo

A small educational **Safaricom Daraja sandbox-only** project built with PHP, MySQL, HTML, CSS, and JavaScript. It demonstrates OAuth, initiating an STK Push, receiving a callback, storing a transaction, and polling transaction status.

> **Sandbox only. Not production-ready.** Do not use this code to accept or fulfill real payments. Safaricom does not sponsor or endorse this repository.

## Requirements

- PHP **8.0+** with `curl`, `pdo_mysql`, and `json` enabled
- MySQL 5.7.8+ / MySQL 8.x (JSON column support)
- **Laragon or XAMPP** (Windows local development), or another PHP web server
- Daraja developer account and **sandbox** credentials
- Public **HTTPS** callback URL (for example, an HTTPS tunnel to your local server)

## Local setup: Laragon or XAMPP (Windows)

Choose **either Laragon or XAMPP**. You do not need both. The PHP source code is identical for both environments.

### Option A — Laragon

1. Install [Laragon](https://laragon.org/) and start **Apache** and **MySQL**. Use PHP 8.0+ with `curl` and `pdo_mysql` enabled.
2. Extract the project into `C:\laragon\www\mpesa-daraja-stk-push-sandbox-demo`. Ensure `schema.sql`, `public/`, and `src/` are directly inside that directory.
3. Import `schema.sql` using your MySQL database tool or phpMyAdmin if installed.
4. Follow **Shared configuration** below.
5. Open `http://mpesa-daraja-stk-push-sandbox-demo.test/public/` if Laragon automatic virtual hosts are enabled, or `http://localhost/mpesa-daraja-stk-push-sandbox-demo/public/`. Reload Laragon if the `.test` host does not resolve.

### Option B — XAMPP

1. Install [XAMPP](https://www.apachefriends.org/) with a PHP version of 8.0+ and start **Apache** and **MySQL** in the XAMPP Control Panel. Ensure `curl` and `pdo_mysql` are enabled.
2. Extract the project into `C:\xampp\htdocs\mpesa-daraja-stk-push-sandbox-demo`. Ensure `schema.sql`, `public/`, and `src/` are directly inside that directory.
3. Open `http://localhost/phpmyadmin/`, create/import the database using `schema.sql` (the SQL script creates `mpesa_app`), or import using the MySQL CLI.
4. Follow **Shared configuration** below.
5. Open `http://localhost/mpesa-daraja-stk-push-sandbox-demo/public/`.

### Shared configuration (both options)

1. Copy `.env.example` to `.env` in the **project root**, never inside `public/`.
2. Add your own **Daraja sandbox** Consumer Key, Consumer Secret, shortcode, and passkey from the [Daraja developer portal](https://developer.safaricom.co.ke/). Keep `MPESA_ENV=sandbox`.
3. Set `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASS` to match your local MySQL installation. Common local defaults are `DB_HOST=127.0.0.1`, `DB_PORT=3306`, `DB_NAME=mpesa_app`; confirm the user, password, and port for your installation.
4. To receive callbacks, use a public **HTTPS** URL that forwards to `public/callback.php`. For example, when Apache listens on port 80, run `ngrok http 80` and set `MPESA_CALLBACK_URL=https://YOUR-TUNNEL-HOST/mpesa-daraja-stk-push-sandbox-demo/public/callback.php`. The URL path must match your project folder. For Laragon's `.test` virtual host, the tunnel may need a matching Host header; using the localhost-style URL avoids this complication.
5. Enter a phone number and test amount in the form and click **Pay**. The backend uses credentials from `.env`; users only enter payment details.
6. Inspect `payments` and `mpesa_callbacks` for transaction status. A Daraja `ResponseCode` of `0` means the request was **accepted for processing**, not that payment succeeded or a handset prompt is guaranteed.

**Security note:** These setups serve the project directory under the web root. Do **not** expose the entire local site or its `.env`/`src/` directories to the public internet. If using a tunnel, configure the web server to restrict access to sensitive files/directories, preferably with a virtual host whose document root is `public/`, before exposing it. This demo is intended for local sandbox testing, not public deployment.

## Files

```text
mpesa-daraja-stk-push-sandbox-demo/
├── .env.example        # Sandbox configuration template
├── .gitignore          # Excludes local secrets and generated files
├── LICENSE             # MIT license
├── README.md
├── schema.sql          # MySQL database and tables
├── src/
│   ├── config.php      # Environment loader, PDO, JSON helper
│   └── Daraja.php      # OAuth, STK Push, status query
└── public/
    ├── index.html      # Demo UI
    ├── styles.css
    ├── script.js
    ├── stk_push.php    # Initiate STK Push
    ├── callback.php    # Receive Daraja callback
    └── status.php      # Poll stored status; may query Daraja
```

## How it works

1. Browser sends phone, amount, and optional reference to `stk_push.php`.
2. PHP requests a sandbox OAuth token and sends an STK Push request.
3. A pending payment is stored in MySQL. **Accepted** means only that the request was accepted for processing, not that payment succeeded.
4. Daraja may POST the result to `callback.php`; the result is stored in MySQL.
5. Browser polls `status.php` for a result. After 60 seconds, the status endpoint may also ask Daraja for a final result.
6. Sandbox behavior varies; a callback or receipt may be delayed or absent. Do not interpret an inconclusive status as a successful payment.

## Troubleshooting

| Symptom | What to check |
| --- | --- |
| `.env file not found` | Copy `.env.example` to `.env` at project root |
| `Invalid DB_PORT` / database failure | Set `DB_PORT=3306` (or your actual MySQL port), start MySQL, import `schema.sql` |
| Daraja authentication failure | Verify sandbox credentials and `MPESA_ENV=sandbox`; do not use production credentials |
| STK Push rejected | Check sandbox shortcode/passkey, supported simulator test values, phone format, and Daraja response logs |
| Callback not received | Callback must be publicly reachable over HTTPS and include the correct path; localhost URLs are not reachable from Safaricom |
| Pending status / no receipt | Check `mpesa_callbacks`, PHP error logs, and sandbox query results; don't repeatedly resubmit |

## Security and limitations

- Never commit `.env` or actual API credentials. `.gitignore` protects only untracked files; if a secret was previously committed, remove it from Git history and rotate it.
- Database access uses prepared statements; input is validated; generic errors are returned to browsers.
- The demo has **no user authentication, CSRF protection, request rate limiting, or robust duplicate-request/idempotency controls**. `status.php` exposes transaction details to anyone with the checkout ID. Do not host the interactive form openly on the internet.
- Sandbox callbacks are **not cryptographically authenticated** in this example; a forged callback with a known checkout ID could alter a record. A successful callback alone is not sufficient to fulfill a real order.
- Real payment applications require authentication, access controls, idempotency, rate limiting, trusted reconciliation, monitoring, and a production security review.
- `public/test_db.php` was intentionally removed: do not publish diagnostic endpoints that disclose deployment state.

## License

MIT — see [LICENSE](LICENSE). Replace the placeholder copyright holder in `LICENSE` with your name or organization before publishing.


## Testing STK Push with your own sandbox credentials

1. Use your private `.env` file to configure sandbox API credentials and MySQL connection settings. Keep `.env` out of Git.
2. Start Apache and MySQL in **Laragon or XAMPP**, import `schema.sql`, and open the matching local URL from the setup instructions above.
3. Enter the phone number intended to receive the prompt and an amount, then select **Pay**. The PHP backend uses your Daraja credentials; the user enters only payment details.
4. Check the response and `CheckoutRequestID`. Accepted initiation is not proof of payment. The app checks the stored status and may query Daraja for pending payments after one minute.
5. If no prompt appears, inspect browser Developer Tools → Network → `stk_push.php`, PHP/Apache error logs, matching sandbox shortcode/passkey, phone number formatting, and any sandbox simulator restrictions. Avoid repeatedly initiating while a request is pending.
6. For callback updates, ensure `MPESA_CALLBACK_URL` is a reachable HTTPS endpoint and the tunnel forwards requests to the correct local callback path.

**Sandbox only:** This demo is not production-ready and should not be used to fulfill real payments. Public deployment requires additional access controls, rate limiting, duplicate protection, and trusted payment verification.


By Omar Juma..