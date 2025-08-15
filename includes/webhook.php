<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action('rest_api_init', function() {
    register_rest_route('wc-upi-qr/v1', '/webhook', array(
        'methods' => 'POST',
        'callback' => function($request) {
            $headers = $request->get_headers();
            $raw = $request->get_body();
            $data = $request->get_json_params();
            $ip = $_SERVER['REMOTE_ADDR'] ?? ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');

            $gateway = new \WC_Gateway_UPI_QR_Advanced();

            // IP allowlist check
            if ( ! $gateway->is_ip_allowed( $ip ) ) {
                $gateway->log_webhook(array('headers'=>$headers,'body'=>$raw,'ip'=>$ip,'verified'=>0,'status'=>'ip_blocked'));
                return new \WP_REST_Response(array('ok'=>false,'error'=>'IP not allowed'), 403);
            }

            $verified = $gateway->verify_webhook_request( $raw, $headers );

            $payload = is_array($data) ? $data : json_decode($raw, true);

            if ( ! $gateway->is_timestamp_valid( $payload ) ) {
                $gateway->log_webhook(array('headers'=>$headers,'body'=>$raw,'ip'=>$ip,'verified'=>intval($verified),'status'=>'replay_failed'));
                return new \WP_REST_Response(array('ok'=>false,'error'=>'Invalid or missing timestamp'), 400);
            }

            $txn_key = $gateway->get_option('transaction_key', 'transaction_id');
            $txn_id = $payload[$txn_key] ?? ($payload['txn_id'] ?? null);
            if ( $txn_id && $gateway->is_transaction_processed( $txn_id ) ) {
                $gateway->log_webhook(array('headers'=>$headers,'body'=>$raw,'ip'=>$ip,'transaction_id'=>$txn_id,'verified'=>intval($verified),'status'=>'duplicate'));
                return new \WP_REST_Response(array('ok'=>false,'error'=>'Duplicate transaction'), 409);
            }

            $log_id = $gateway->log_webhook(array('headers'=>$headers,'body'=>$raw,'ip'=>$ip,'transaction_id'=>$txn_id,'order_id'=>$payload['order_id'] ?? null,'verified'=>intval($verified),'status'=>'received'));

            if ( ! $verified ) {
                return new \WP_REST_Response(array('ok'=>false,'error'=>'Invalid signature or secret'), 401);
            }

            $res = $gateway->handle_webhook_payload( $payload );
            if ( is_wp_error( $res ) ) {
                return new \WP_REST_Response(array('ok'=>false,'error'=>$res->get_error_message()), $res->get_error_data()['status'] ?? 400 );
            }

            global $wpdb;
            $table = $wpdb->prefix . 'wc_upi_webhooks';
            $wpdb->update($table, array('status'=>'processed'), array('id'=>$log_id));

            return new \WP_REST_Response(array('ok'=>true), 200);
        },
        'permission_callback' => '__return_true'
    ));
});
