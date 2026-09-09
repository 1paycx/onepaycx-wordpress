<?php
/**
 * Signed button tokens.
 *
 * A Pay button has to carry its price to the checkout endpoint without letting a
 * tampered page change it. Storing the price server-side (the 1.0.x approach) met
 * that bar but broke on the many WordPress sites that serve full-page caches: the
 * stored spec was one-shot, so once any visitor paid, every later visitor served
 * the same cached HTML got "invalid_button". It also wrote a row per render.
 *
 * So the price travels inside the token, signed with a site-specific key. Nothing
 * is persisted, cached pages keep working indefinitely, and altering the payload
 * invalidates the signature.
 */

if (!defined('ABSPATH')) {
    exit;
}

class OnePayCx_Token {
    /** Site-specific signing key, generated once and kept in options. */
    private static function secret() {
        $secret = get_option('onepaycx_token_secret');
        if (!$secret) {
            $secret = wp_generate_password(64, true, true);
            update_option('onepaycx_token_secret', $secret, false);
        }
        return $secret;
    }

    private static function b64url_encode($raw) {
        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- URL-safe transport of a JSON payload; the token is HMAC-verified on receipt and nothing decoded here is ever executed.
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private static function b64url_decode($enc) {
        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- reverses the URL-safe encoding above; output is json_decode'd into an array, never eval'd or included.
        return base64_decode(strtr($enc, '-_', '+/'));
    }

    /** @param array $spec { amount:float|null, reference:string, currency:string } */
    public static function sign(array $spec) {
        $payload = self::b64url_encode((string) wp_json_encode($spec));
        $sig     = self::b64url_encode(hash_hmac('sha256', $payload, self::secret(), true));
        return $payload . '.' . $sig;
    }

    /**
     * Verify and decode. Returns the spec array, or false if the token is missing,
     * malformed, or its signature doesn't match.
     *
     * @return array|false
     */
    public static function verify($token) {
        $token = (string) $token;
        if (strpos($token, '.') === false) {
            return false;
        }
        list($payload, $sig) = explode('.', $token, 2);
        if ($payload === '' || $sig === '') {
            return false;
        }
        $expected = self::b64url_encode(hash_hmac('sha256', $payload, self::secret(), true));
        if (!hash_equals($expected, $sig)) {
            return false;
        }
        $spec = json_decode(self::b64url_decode($payload), true);
        if (!is_array($spec) || !array_key_exists('amount', $spec)) {
            return false;
        }
        return $spec;
    }
}
