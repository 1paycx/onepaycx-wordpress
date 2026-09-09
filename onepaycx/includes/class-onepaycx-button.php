<?php
/**
 * The "Pay by card or crypto" button: a [onepaycx_button] shortcode and a matching
 * Gutenberg block (dynamic — both render through the same code path).
 */

if (!defined('ABSPATH')) {
    exit;
}

class OnePayCx_Button {
    public function __construct() {
        add_action('init', array($this, 'register'));
        add_shortcode('onepaycx_button', array($this, 'render'));
    }

    public function register() {
        wp_register_style('onepaycx', plugins_url('../assets/onepaycx.css', __FILE__), array(), ONEPAYCX_WP_VERSION);
        wp_register_script('onepaycx', plugins_url('../assets/onepaycx.js', __FILE__), array(), ONEPAYCX_WP_VERSION, true);
        wp_localize_script('onepaycx', 'OnePayCx', array(
            'checkoutUrl' => rest_url('onepaycx/v1/checkout'),
            'i18n'        => array(
                'amount'  => __('Enter an amount.', '1pay-cx-payments'),
                'email'   => __('Enter your email for the receipt.', '1pay-cx-payments'),
                'failed'  => __('Payment could not be started.', '1pay-cx-payments'),
                'network' => __('Network error — please try again.', '1pay-cx-payments'),
            ),
        ));

        // Dynamic block sharing the shortcode renderer.
        if (function_exists('register_block_type')) {
            wp_register_script(
                'onepaycx-block',
                plugins_url('../assets/block.js', __FILE__),
                array('wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n'),
                ONEPAYCX_WP_VERSION,
                true
            );
            register_block_type('onepaycx/button', array(
                'editor_script'   => 'onepaycx-block',
                'render_callback' => array($this, 'render_block'),
                'attributes'      => array(
                    'amount'    => array('type' => 'string', 'default' => ''),
                    'label'     => array('type' => 'string', 'default' => 'Pay by card or crypto'),
                    'reference' => array('type' => 'string', 'default' => ''),
                ),
            ));
        }
    }

    public function render_block($attrs) {
        return $this->render(array(
            'amount'    => isset($attrs['amount']) ? $attrs['amount'] : '',
            'label'     => isset($attrs['label']) ? $attrs['label'] : 'Pay by card or crypto',
            'reference' => isset($attrs['reference']) ? $attrs['reference'] : '',
        ));
    }

    public function render($atts) {
        $a = shortcode_atts(array(
            'amount'    => '',
            'label'     => __('Pay by card or crypto', '1pay-cx-payments'),
            'reference' => '',
            'currency'  => 'USD',
        ), $atts, 'onepaycx_button');

        if (!get_option('onepaycx_webhook_secret')) {
            // Not connected yet — show nothing to visitors, a hint to editors.
            return current_user_can('manage_options')
                ? '<em>' . esc_html__('1pay.cx: add your API key under Settings -> 1pay.cx to enable this button.', '1pay-cx-payments') . '</em>'
                : '';
        }

        $direct = (bool) get_option('onepaycx_direct');
        $open   = trim((string) $a['amount']) === '';
        $amount = $open ? null : round((float) $a['amount'], 2);

        // Server-authoritative price, carried in a signed token rather than stored.
        // A tampered client still can't change a fixed amount (the HMAC won't
        // verify), but because nothing is persisted the button keeps working on
        // full-page-cached sites and renders cost no database writes.
        $token = OnePayCx_Token::sign(array(
            'amount'    => $amount,
            'reference' => sanitize_text_field((string) $a['reference']),
            'currency'  => sanitize_text_field((string) $a['currency']),
        ));

        wp_enqueue_style('onepaycx');
        wp_enqueue_script('onepaycx');

        /* translators: %s: currency code, e.g. USD */
        $amount_placeholder = sprintf(__('Amount (%s)', '1pay-cx-payments'), $a['currency']);

        ob_start();
        ?>
        <div class="onepaycx-pay" data-token="<?php echo esc_attr($token); ?>" data-open="<?php echo $open ? '1' : '0'; ?>" data-direct="<?php echo $direct ? '1' : '0'; ?>">
            <?php if ($open) : ?>
                <input type="number" step="0.01" min="0.50" class="onepaycx-amount" placeholder="<?php echo esc_attr($amount_placeholder); ?>" />
            <?php endif; ?>
            <?php if ($direct) : ?>
                <input type="email" class="onepaycx-email" placeholder="<?php echo esc_attr(__('Email for your receipt', '1pay-cx-payments')); ?>" />
            <?php endif; ?>
            <button type="button" class="onepaycx-btn"><?php echo esc_html($a['label']); ?></button>
            <span class="onepaycx-msg" role="alert"></span>
        </div>
        <?php
        return ob_get_clean();
    }
}
