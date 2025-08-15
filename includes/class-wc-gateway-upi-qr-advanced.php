<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WC_Gateway_UPI_QR_Advanced extends WC_Payment_Gateway {

    public function __construct() {
        $this->id = 'upi_qr_advanced';
        $this->has_fields = false;
        $this->method_title = __('UPI QR (Advanced)', 'wc-upi-qr-advanced');
        $this->method_description = __('UPI QR with webhook automation and security features.', 'wc-upi-qr-advanced');

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title', 'UPI / QR Payment');
        $this->description = $this->get_option('description', 'Scan QR to pay via UPI app');
        $this->vpa = $this->get_option('vpa', '');
        $this->merchant_name = $this->get_option('merchant_name', '');
        $this->upi_note = $this->get_option('upi_note', '');
        $this->display_qr_size = intval($this->get_option('qr_size', 300));
        $this->webhook_secret = $this->get_option('webhook_secret', '');
        $this->ip_allowlist = $this->get_option('ip_allowlist', '');
        $this->replay_ttl = intval($this->get_option('replay_ttl', 300));
        $this->transaction_key = $this->get_option('transaction_key', 'transaction_id');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    }

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'wc-upi-qr-advanced'),
                'type' => 'checkbox',
                'label' => __('Enable UPI QR Payment', 'wc-upi-qr-advanced'),
                'default' => 'yes'
            ),
            'title' => array('title'=>__('Title','wc-upi-qr-advanced'),'type'=>'text','default'=>'UPI / QR Payment'),
            'description' => array('title'=>__('Description','wc-upi-qr-advanced'),'type'=>'textarea','default'=>'Scan the QR code with any UPI app to pay.'),
            'vpa' => array('title'=>__('Merchant UPI ID (VPA)','wc-upi-qr-advanced'),'type'=>'text','description'=>'Example: yourid@upi','default'=>''),
            'merchant_name' => array('title'=>__('Merchant / Payee Name','wc-upi-qr-advanced'),'type'=>'text','default'=>''),
            'upi_note' => array('title'=>__('Payment Note (optional)','wc-upi-qr-advanced'),'type'=>'text','default'=>''),
            'qr_size' => array('title'=>__('QR Size (px)','wc-upi-qr-advanced'),'type'=>'number','default'=>'300'),
            'webhook_secret' => array('title'=>__('Webhook Secret','wc-upi-qr-advanced'),'type'=>'text','description'=>'Secret token used to verify incoming webhook requests from your payment aggregator.','default'=>''),
            'ip_allowlist' => array('title'=>__('IP Allowlist (comma separated)','wc-upi-qr-advanced'),'type'=>'text','description'=>'Optional: Only accept webhooks from these IP addresses. Leave empty to accept all.','default'=>''),
            'replay_ttl' => array('title'=>__('Replay protection TTL (seconds)','wc-upi-qr-advanced'),'type'=>'number','description'=>'Time window to accept webhooks with timestamp in payload. Set 0 to disable.','default'=>'300'),
            'transaction_key' => array('title'=>__('Transaction ID key in payload','wc-upi-qr-advanced'),'type'=>'text','description'=>'JSON key name that contains aggregator transaction id (e.g., txn_id). Leave empty to skip dedupe.','default'=>'transaction_id'),
            'instructions' => array('title'=>__('Instructions on checkout','wc-upi-qr-advanced'),'type'=>'textarea','default'=>"Scan the QR code with your UPI app and complete payment. After payment, return to this page and click 'I've Paid'."),
        );
    }

    public function payment_fields() {
        echo '<p>' . wp_kses_post(wpautop($this->description)) . '</p>';
        if (empty($this->vpa)) {
            echo '<p style="color:red;">Please configure Merchant UPI ID in plugin settings.</p>';
            return;
        }
        $order = $this->get_order_from_request();
        if (!$order) {
            echo '<p>' . esc_html__('Please proceed to place order to see the UPI QR code.', 'wc-upi-qr-advanced') . '</p>';
            return;
        }
        echo $this->generate_qr_html($order);
        echo '<p><button type="button" class="button" id="wc-upi-paid-button">' . esc_html__("I've Paid", 'wc-upi-qr-advanced') . '</button></p>';
        ?>
        <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function(){
            var btn = document.getElementById('wc-upi-paid-button');
            if (btn) {
                btn.addEventListener('click', function(){
                    alert('Thank you — merchant will verify payment.');
                });
            }
        });
        </script>
        <?php
    }

    protected function get_order_from_request() {
        if ( isset( $_GET['order_id'] ) ) {
            return wc_get_order( intval( $_GET['order_id'] ) );
        }
        $order_id = WC()->session ? WC()->session->get('order_awaiting_payment') : null;
        if ($order_id) return wc_get_order( $order_id );
        return null;
    }

    public function generate_upi_uri( $order ) {
        $amount = number_format((float)$order->get_total(), 2, '.', '');
        $vpa = $this->vpa;
        $pn = $this->merchant_name ?: get_bloginfo('name');
        $tn = $this->upi_note ?: 'Order ' . $order->get_order_number();
        $params = array('pa'=>$vpa,'pn'=>$pn,'am'=>$amount,'tn'=>$tn,'cu'=>get_woocommerce_currency());
        $pairs = array();
        foreach ($params as $k=>$v) $pairs[] = $k . '=' . rawurlencode($v);
        return 'upi://pay?' . implode('&',$pairs);
    }

    public function generate_qr_html( $order ) {
        $upi_uri = $this->generate_upi_uri($order);
        $encoded = rawurlencode($upi_uri);
        $size = intval($this->display_qr_size);
        $qr_url = "https://chart.googleapis.com/chart?chs={$size}x{$size}&cht=qr&chl={$encoded}";
        ob_start();
        ?>
        <div class="wc-upi-qr-box">
            <p><strong><?php echo esc_html($this->title); ?></strong></p>
            <p><?php echo wp_kses_post(wpautop($this->get_option('instructions'))); ?></p>
            <img src="<?php echo esc_url($qr_url); ?>" alt="<?php esc_attr_e('UPI QR Code','wc-upi-qr-advanced'); ?>" width="<?php echo esc_attr($size); ?>" height="<?php echo esc_attr($size); ?>" />
            <p><small><?php esc_html_e('Scan with any UPI app and complete payment.', 'wc-upi-qr-advanced'); ?></small></p>
            <p>UPI URI: <code><?php echo esc_html($this->generate_upi_uri($order)); ?></code></p>
        </div>
        <?php
        return ob_get_clean();
    }

    public function process_payment( $order_id ) {
        $order = wc_get_order($order_id);
        $order->update_status('on-hold', __('Awaiting UPI payment via QR code', 'wc-upi-qr-advanced'));
        wc_reduce_stock_levels($order_id);
        WC()->cart->empty_cart();
        return array('result'=>'success','redirect'=>$this->get_return_url($order));
    }

    // Webhook and security helper methods
    public function log_webhook( $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wc_upi_webhooks';
        $wpdb->insert($table, array(
            'headers' => maybe_serialize($data['headers'] ?? array()),
            'body' => $data['body'] ?? '',
            'ip' => $data['ip'] ?? '',
            'transaction_id' => $data['transaction_id'] ?? null,
            'order_id' => $data['order_id'] ?? null,
            'status' => $data['status'] ?? null,
            'verified' => isset($data['verified']) ? intval($data['verified']) : 0,
            'received_at' => current_time('mysql', 1)
        ), array('%s','%s','%s','%s','%d','%s','%d','%s'));
        return $wpdb->insert_id;
    }

    public function is_transaction_processed( $transaction_id ) {
        if ( empty($transaction_id) ) return false;
        global $wpdb;
        $table = $wpdb->prefix . 'wc_upi_webhooks';
        $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE transaction_id=%s", $transaction_id));
        return $exists ? true : false;
    }

    public function is_ip_allowed( $ip ) {
        $list = $this->get_option('ip_allowlist');
        if ( empty($list) ) return true;
        $items = array_map('trim', explode(',', $list));
        return in_array($ip, $items);
    }

    public function is_timestamp_valid( $payload ) {
        $ttl = intval($this->get_option('replay_ttl', 300));
        if ( $ttl <= 0 ) return true;
        $ts = $payload['timestamp'] ?? $payload['ts'] ?? null;
        if ( ! $ts ) return false;
        $now = time();
        $diff = abs($now - intval($ts));
        return ($diff <= $ttl);
    }

    public function verify_webhook_request( $raw_body, $headers ) {
        $secret = $this->get_option('webhook_secret');
        if ( empty($secret) ) return false;
        // header names are lowercase in WP
        if ( isset($headers['x-wc-upi-secret']) && is_array($headers['x-wc-upi-secret']) && $headers['x-wc-upi-secret'][0] === $secret ) {
            return true;
        }
        if ( isset($headers['x-wc-upi-sign']) && is_array($headers['x-wc-upi-sign']) ) {
            $sig = $headers['x-wc-upi-sign'][0];
            $expected = hash_hmac('sha256', $raw_body, $secret);
            if ( hash_equals($expected, $sig) ) return true;
        }
        return false;
    }

    public function handle_webhook_payload( $payload ) {
        if ( empty($payload['order_id']) ) return new WP_Error('missing_order','Missing order_id', array('status'=>400));
        $order = wc_get_order( intval($payload['order_id']) );
        if ( ! $order ) return new WP_Error('invalid_order','Order not found', array('status'=>404));

        $status = strtolower( $payload['status'] ?? '' );
        if ( $status === 'paid' || $status === 'success' ) {
            $order->payment_complete();
            $order->add_order_note( 'UPI webhook: payment confirmed by aggregator.' );
        } elseif ( $status === 'refunded' ) {
            $order->update_status('refunded', 'UPI webhook: refund processed.');
        } elseif ( $status === 'failed' ) {
            $order->update_status('failed', 'UPI webhook: payment failed.');
        } else {
            $order->add_order_note( 'UPI webhook: unknown status: ' . maybe_serialize($payload) );
        }
        return true;
    }
}
