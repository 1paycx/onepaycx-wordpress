<?php
/**
 * REST endpoints:
 *   POST /wp-json/onepaycx/v1/checkout  — create a payment, return the checkout URL.
 *   POST /wp-json/onepaycx/v1/notify    — receive the signed paid notify. Acts only on
 *     payments this site created (recorded as option onepaycx_pay_<id>), re-reads
 *     each from GET /v1/payments/{id}, and fires onepaycx_paid at most once per payment.
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
            // (timestamped V2 header when present, else the legacy body-only one),
            // checked with hash_equals() before anything is acted on; the payment
            // is then re-read from the API before the onepaycx_paid hook fires.
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

        // Remember what we asked for: the notify handler only acts on payments this
        // site created, and checks the paid amount/currency against this record.
        $pid = isset($res['data']['id']) ? (string) $res['data']['id'] : '';
        if (self::valid_payment_id($pid)) {
            add_option(self::record_key($pid), array(
                'amount'    => round((float) $payload['amount'], 2),
                'currency'  => strtoupper((string) $payload['currency']),
                'reference' => (string) $payload['reference'],
                'status'    => 'pending',
                'created'   => time(),
            ), '', 'no');
        }

        delete_transient('onepaycx_tok_' . $token); // no-op unless this was a legacy token

        $checkout = $res['data']['checkoutUrl'];
        $url = (strpos($checkout, 'http') === 0) ? $checkout : ONEPAYCX_CHECKOUT_BASE . $checkout;
        if (get_option('onepaycx_direct') && $email && is_email($email)) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . 'direct=1';
        }

        return new WP_REST_Response(array('url' => $url), 200);
    }

    /** Max age/skew, in seconds, of an X-1pay-Signature-V2 timestamp. */
    const SIG_TOLERANCE = 300;

    public static function valid_payment_id($id) {
        return is_string($id) && preg_match('/^[A-Za-z0-9_-]{1,64}$/', $id) === 1;
    }

    /** Option name of the local record for a payment this site created. */
    public static function record_key($id) {
        return 'onepaycx_pay_' . $id;
    }

    /**
     * Verify the notify signature. When X-1pay-Signature-V2 ("t=<unix>,v1=<hex>")
     * is present it is the only one consulted: HMAC-SHA256 over "<t>.<body>" and a
     * timestamp within SIG_TOLERANCE of now, so a captured delivery cannot be
     * replayed later. Otherwise the legacy X-1pay-Signature (HMAC over the body).
     *
     * @return string '' when valid, else an error code.
     */
    private function verify_signature(WP_REST_Request $request, $body, $secret) {
        $v2 = trim((string) $request->get_header('x_1pay_signature_v2'));
        if ($v2 !== '') {
            $t = null;
            $v1 = '';
            foreach (explode(',', $v2) as $part) {
                $kv = explode('=', trim($part), 2);
                if (count($kv) !== 2) {
                    continue;
                }
                if ($kv[0] === 't' && preg_match('/^\d{1,12}$/', $kv[1])) {
                    $t = (int) $kv[1];
                } elseif ($kv[0] === 'v1' && ctype_xdigit($kv[1])) {
                    $v1 = strtolower($kv[1]);
                }
            }
            if ($t === null || $v1 === '') {
                return 'missing_signature';
            }
            if (abs(time() - $t) > self::SIG_TOLERANCE) {
                return 'stale_signature';
            }
            return hash_equals(hash_hmac('sha256', $t . '.' . $body, $secret), $v1) ? '' : 'bad_signature';
        }

        $sig = strtolower(trim((string) $request->get_header('x_1pay_signature')));
        if ($sig === '') {
            return 'missing_signature';
        }
        return hash_equals(hash_hmac('sha256', $body, $secret), $sig) ? '' : 'bad_signature';
    }

    public function notify(WP_REST_Request $request) {
        $secret = (string) get_option('onepaycx_webhook_secret');
        $key    = (string) get_option('onepaycx_api_key');
        $body   = $request->get_body();

        if ($secret === '' || $key === '') {
            return new WP_REST_Response(array('error' => 'not_configured'), 503);
        }
        $err = $this->verify_signature($request, $body, $secret);
        if ($err !== '') {
            return new WP_REST_Response(array('error' => $err), $err === 'missing_signature' ? 400 : 401);
        }

        $payload = json_decode($body, true);
        $event   = is_array($payload) && isset($payload['event']) ? $payload['event'] : '';
        $data    = is_array($payload) && isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : array();

        if ($event !== 'payment.paid') {
            return new WP_REST_Response(array('ok' => true, 'ignored' => 'event'), 200);
        }

        $pid = isset($data['paymentId']) ? (string) $data['paymentId'] : '';
        if (!self::valid_payment_id($pid)) {
            return new WP_REST_Response(array('error' => 'missing_payment_id'), 400);
        }

        $api = new OnePayCx_Api($key);
        // Refusals below are acknowledged (200): redelivering would not change the answer.
        if ($api->is_live() && array_key_exists('livemode', $data) && $data['livemode'] === false) {
            return new WP_REST_Response(array('ok' => false, 'ignored' => 'test_payment'), 200);
        }

        // Only a payment this site created (recorded at checkout) is acted on.
        $record = get_option(self::record_key($pid));
        if (!is_array($record)) {
            return new WP_REST_Response(array('ok' => false, 'ignored' => 'unknown_payment'), 200);
        }
        if (isset($record['status']) && $record['status'] === 'paid') {
            return new WP_REST_Response(array('ok' => true, 'duplicate' => true), 200);
        }

        // Authoritative re-read with our own key; the notify is only a hint.
        $res = $api->get_payment($pid);
        if (!$res['ok'] || !is_array($res['data'])) {
            return new WP_REST_Response(array('error' => 'could_not_verify'), 502);
        }
        $row = $res['data'];
        if (!isset($row['status']) || $row['status'] !== 'paid') {
            return new WP_REST_Response(array('error' => 'not_paid'), 409); // retried later
        }
        if (isset($row['id']) && (string) $row['id'] !== $pid) {
            return new WP_REST_Response(array('ok' => false, 'ignored' => 'payment_mismatch'), 200);
        }
        if ($api->is_live() && array_key_exists('livemode', $row) && $row['livemode'] === false) {
            return new WP_REST_Response(array('ok' => false, 'ignored' => 'test_payment'), 200);
        }
        $amount   = isset($row['amount']) && is_numeric($row['amount']) ? (float) $row['amount'] : 0.0;
        $currency = isset($row['currency']) ? strtoupper((string) $row['currency']) : '';
        $ref      = isset($row['reference']) ? (string) $row['reference'] : '';
        if ($currency === '' || $currency !== $record['currency']) {
            return new WP_REST_Response(array('ok' => false, 'ignored' => 'currency_mismatch'), 200);
        }
        // Higher is allowed (a customer surcharge configured on 1pay.cx); short is not.
        if ($amount + 0.01 < (float) $record['amount']) {
            return new WP_REST_Response(array('ok' => false, 'ignored' => 'amount_mismatch'), 200);
        }
        if ($ref !== (string) $record['reference']) {
            return new WP_REST_Response(array('ok' => false, 'ignored' => 'reference_mismatch'), 200);
        }

        $record['status'] = 'paid';
        $record['paid']   = time();
        update_option(self::record_key($pid), $record, 'no');

        /**
         * Fires once when a 1pay.cx payment created by this site is confirmed paid
         * (signature verified, then re-read from the API). Other plugins/CRMs can
         * hook this to fulfil orders, grant access, send receipts, etc.
         *
         * @param array $data { paymentId, reference, amount, currency, valueForwarded, status, livemode }
         */
        do_action('onepaycx_paid', array_merge($data, array(
            'paymentId' => $pid,
            'reference' => $ref,
            'amount'    => $amount,
            'currency'  => $currency,
            'status'    => 'paid',
            'livemode'  => isset($row['livemode']) ? (bool) $row['livemode'] : $api->is_live(),
        )));

        return new WP_REST_Response(array('ok' => true), 200);
    }
}
