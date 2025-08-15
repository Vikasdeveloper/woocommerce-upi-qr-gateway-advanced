# WooCommerce UPI QR Gateway - Advanced

UPI QR Gateway with webhook verification, transaction deduplication, webhook logging, IP allowlist and replay protection.

## Installation

1. Upload the plugin folder into `wp-content/plugins/`.
2. Activate the plugin.
3. Go to WooCommerce > Settings > Payments and enable "UPI / QR (Advanced)".
4. Click "Manage" and set Merchant UPI ID (VPA), Merchant Name, Webhook Secret, IP Allowlist (comma separated), Transaction ID key (e.g., txn_id), and Replay TTL (seconds).

## Webhook Integration

- **Endpoint:** `POST /wp-json/wc-upi-qr/v1/webhook`
- **Payload:** JSON object. Example:
```json
{ "order_id": 123, "transaction_id": "txn_abc123", "status": "paid", "timestamp": 1690000000 }
```
- **Authentication:** Use either header `X-WC-UPI-SECRET: <secret>` or `X-WC-UPI-SIGN: <hmac_sha256(raw_body, secret)>`

## Security Features

- **IP Allowlist:** Only accepts webhooks from configured IP addresses (optional).
- **Replay Protection:** Validates timestamp inside payload against configured TTL.
- **Transaction Deduplication:** Prevents processing the same aggregator transaction twice.
- **Webhook Logs:** All incoming webhooks are logged to `wp_wc_upi_webhooks` table for audit and debugging.

## Notes

- Use HTTPS for webhook endpoints.
- Configure your aggregator to send the transaction id key matching the plugin's setting (default: `transaction_id`).
- If you want integration with a specific aggregator (example: Cashfree, Razorpay Payouts, etc.), provide their webhook payload spec and we can map fields accordingly.
