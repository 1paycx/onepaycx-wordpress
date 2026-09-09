<?php
/**
 * REST endpoints:
 *   POST /wp-json/onepaycx/v1/checkout  — create a payment, return the checkout URL.
 *   POST /wp-json/onepaycx/v1/notify    — receive the signed paid notify.
 *
 * The button amount is authoritative server-side: the shortcode/block signs it into
 * the token (see OnePayCx_Token), so a tampered client can't change a fixed price.
 * "Open" buttons (buyer enters the amount) accept the posted value.
 */

if (!defined('ABSPATH')) {
    exit;
}

class OnePayCx_Rest {
    public function __construct() {
        add_action('rest_api_init', array($this, 'routes'));
    }

    public function routes() {
        register_rest_route('onepaycx/v1', '/checkout', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'checkout'),
            // Public by design: the caller is an anonymous site visitor pressing a
            // Pay button, so there is no user to authorise and no nonce to hold (a
            // nonce embedded in a page would also expire under full-page caching).
            // It is safe because it performs no privileged action: the price comes
            // from a signed token the visitor cannot alter, and the only effect is
            // returning a checkout URL for that price.
            'permission_callback' => '__return_true',
        ));
        register_rest_route('onepaycx/v1', '/notify', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'notify'),
            // Public by design: this is the payment service's webhook and carries
            // no WordPress session. Authentication is the HMAC-SHA256 signature
            // over the raw body, checked with hash_equals() before anything is
            // acted on — stronger than a nonce, and the only thing that can fire
            // the onepaycx_paid hook.
            'permission_callback' => '__return_true',
        ));
    }

    public function checkout(WP_REST_Request $request) {
        $key = get_option('onepaycx_api_key');
        if (!$key) {
            return new WP_REST_Response(array('error' => 'not_configured'), 500);
        }

        $token = sanitize_text_field((string) $request->get_param('token'));
        $spec  = $token ? OnePayCx_Token::verify($token) : false;
        if (!is_array($spec)) {
            // Fallback for 1.0.x tokens still embedded in pages cached before the
            // upgrade. Remove once those caches have turned over.
            $spec = $token ? get_transient('onepaycx_tok_' . $token) : false;
        }
        if (!is_array($spec)) {
            return new WP_REST_Response(array('error' => 'invalid_button'), 400);
        }

        // Fixed price wins; open buttons take the posted amount.
        $amount = $spec['amount'];
        if ($amount === null) {
            $amount = round((float) $request->get_param('amount'), 2);
        }
        if (!$amount || $amount <= 0) {
            return new WP_REST_Response(array('error' => 'invalid_amount'), 400);
        }

        // Only ever return the buyer to this site: the URL arrives from the browser,
        // so an attacker could otherwise use a Pay button as an open redirect.
        $return = esc_url_raw((string) $request->get_param('return'));
        if (!$return || wp_parse_url($return, PHP_URL_HOST) !== wp_parse_url(home_url('/'), PHP_URL_HOST)) {
            $return = home_url('/');
        }

        // Direct mode needs the buyer's email on the payment (the processor asks
        // for it); only a valid one is forwarded, and only then do we skip our page.
        $email = sanitize_email((string) $request->get_param('email'));

        $payload = array(
            'amount'      => (float) $amount,
            'currency'    => isset($spec['currency']) ? $spec['currency'] : 'USD',
            'reference'   => isset($spec['reference']) ? $spec['reference'] : '',
            'notifyUrl'   => rest_url('onepaycx/v1/notify'),
            'redirectUrl' => $return,
        );
        if ($email && is_email($email)) {
            $payload['email'] = $email;
        }

        $api = new OnePayCx_Api($key);
        $res = $api->create_payment($payload);

        if (!$res['ok'] || empty($res['data']['checkoutUrl'])) {
            return new WP_REST_Response(array('error' => $res['error'] ? $res['error'] : 'failed'), 502);
        }

        delete_transient('onepaycx_tok_' . $token); // no-op unless this was a legacy token

        $checkout = $res['data']['checkoutUrl'];
        $url = (strpos($checkout, 'http') === 0) ? $checkout : ONEPAYCX_CHECKOUT_BASE . $checkout;
        if (get_option('onepaycx_direct') && $email && is_email($email)) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . 'direct=1';
        }

        return new WP_REST_Response(array('url' => $url), 200);
    }

    public function notify(WP_REST_Request $request) {
        $secret = get_option('onepaycx_webhook_secret');
        $sig    = $request->get_header('x_1pay_signature');
        $body   = $request->get_body();

        if (!$secret || !$sig) {
            return new WP_REST_Response(array('error' => 'missing_signature'), 400);
        }
        if (!hash_equals(hash_hmac('sha256', $body, $secret), $sig)) {
            return new WP_REST_Response(array('error' => 'bad_signature'), 401);
        }

        $payload = json_decode($body, true);
        $event   = isset($payload['event']) ? $payload['event'] : '';
        $data    = isset($payload['data']) ? $payload['data'] : array();

        if ($event === 'payment.paid') {
            /**
             * Fires when a 1pay.cx payment is confirmed paid. Other plugins/CRMs can
             * hook this to fulfil orders, grant access, send receipts, etc.
             *
             * @param array $data { paymentId, reference, amount, valueForwarded, status }
             */
            do_action('onepaycx_paid', $data);
        }

        return new WP_REST_Response(array('ok' => true), 200);
    }
}
