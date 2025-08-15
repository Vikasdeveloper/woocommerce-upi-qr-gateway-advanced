# WooCommerce UPI QR Code Gateway

Simple WooCommerce payment gateway that displays a UPI QR code for customers to scan and pay using any UPI app.

## Installation

1. Upload the plugin folder into `wp-content/plugins/`.
2. Activate the plugin.
3. Go to WooCommerce > Settings > Payments and enable "UPI / QR Payment".
4. Click "Manage" and set Merchant UPI ID (VPA), Merchant Name and instructions.
5. Optionally set a **Webhook Secret** in the gateway settings and configure your payment aggregator to call the webhook endpoint described below.

## Usage

- On checkout, select "UPI / QR Payment" as the payment method. A QR code will be displayed on the checkout page (if order available).
- After placing the order, the order will be set to "On hold". Merchant should verify payment manually and mark order complete.
- With webhook support enabled and a secret set, a payment aggregator can notify the store automatically to mark orders as paid.

## Webhook Integration

- **Endpoint:** `POST /wp-json/wc-upi-qr/v1/webhook`
- **Payload:** JSON object. Example:
```json
{ "order_id": 123, "status": "paid" }
```
- **Authentication:** Set a secret in the gateway settings as `Webhook Secret`. The aggregator should either:
  - Send header `X-WC-UPI-SECRET: <secret>` OR
  - Send header `X-WC-UPI-SIGN: <hmac_sha256(raw_body, secret)>`

When the webhook is verified, the plugin will call `payment_complete()` on the order (marks order paid and reduces stock).

## Notes

- Ensure TLS (HTTPS) on your site for secure webhook delivery.
- Test with a sample aggregator or ngrok during development.
- If you want the plugin to fetch receipts or verify transaction IDs, provide the aggregator's documentation and we can add transaction verification logic.
