<?php
/**
 * Settings screen (Settings -> 1pay.cx): the API key. Validates the key on save
 * and caches the webhook secret used to verify notify signatures.
 */

if (!defined('ABSPATH')) {
    exit;
}

class OnePayCx_Settings {
    public function __construct() {
        add_action('admin_menu', array($this, 'menu'));
        add_action('admin_init', array($this, 'register'));
        add_filter('plugin_action_links_' . plugin_basename(ONEPAYCX_WP_FILE), array($this, 'action_link'));
    }

    public function menu() {
        add_options_page('1pay.cx', '1pay.cx', 'manage_options', 'onepaycx', array($this, 'render'));
    }

    public function register() {
        register_setting('onepaycx', 'onepaycx_api_key', array(
            'type'              => 'string',
            'sanitize_callback' => array($this, 'sanitize_key'),
            'default'           => '',
        ));
        register_setting('onepaycx', 'onepaycx_direct', array(
            'type'              => 'string',
            'sanitize_callback' => array($this, 'sanitize_direct'),
            'default'           => '',
        ));
    }

    /** On save, verify the key and stash the webhook secret. */
    public function sanitize_key($value) {
        $key = trim((string) $value);
        if ($key === '') {
            delete_option('onepaycx_webhook_secret');
            return '';
        }
        $api = new OnePayCx_Api($key);
        $res = $api->me();
        if ($res['ok'] && !empty($res['data']['webhookSecret'])) {
            update_option('onepaycx_webhook_secret', $res['data']['webhookSecret']);
            add_settings_error('onepaycx', 'onepaycx_ok', __('API key verified — your Pay buttons are live.', '1pay-cx-payments'), 'updated');
        } else {
            add_settings_error('onepaycx', 'onepaycx_bad', sprintf(
                /* translators: %s: upstream error */
                __('Could not verify the API key (%s). Check it in your 1pay.cx dashboard.', '1pay-cx-payments'),
                $res['error'] ? $res['error'] : __('unknown error', '1pay-cx-payments')
            ));
        }
        return $key;
    }

    /** Checkbox: store '1' or ''. */
    public function sanitize_direct($value) {
        return $value ? '1' : '';
    }

    public function action_link($links) {
        $url = admin_url('options-general.php?page=onepaycx');
        array_unshift($links, '<a href="' . esc_url($url) . '">' . esc_html__('Settings', '1pay-cx-payments') . '</a>');
        return $links;
    }

    public function render() {
        if (!current_user_can('manage_options')) {
            return;
        }
        $configured = (bool) get_option('onepaycx_webhook_secret');
        ?>
        <div class="wrap">
            <h1>1pay.cx Payments</h1>
            <p><?php esc_html_e('Add a "Pay by card or crypto" button to any page. Buyers pay by card, Bitcoin or Monero; you settle in crypto.', '1pay-cx-payments'); ?></p>
            <?php settings_errors('onepaycx'); ?>
            <form method="post" action="options.php">
                <?php settings_fields('onepaycx'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="onepaycx_api_key"><?php esc_html_e('API key', '1pay-cx-payments'); ?></label></th>
                        <td>
                            <input name="onepaycx_api_key" id="onepaycx_api_key" type="password" autocomplete="off"
                                   value="<?php echo esc_attr(get_option('onepaycx_api_key', '')); ?>" class="regular-text" placeholder="pk_xxx.yyy" />
                            <p class="description">
                                <?php esc_html_e('From your 1pay.cx dashboard: Settings -> API keys.', '1pay-cx-payments'); ?>
                                <?php echo $configured ? '<strong style="color:#1a7f37">' . esc_html__('Connected.', '1pay-cx-payments') . '</strong>' : ''; ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Direct to card processor', '1pay-cx-payments'); ?></th>
                        <td>
                            <label>
                                <input name="onepaycx_direct" type="checkbox" value="1" <?php checked((bool) get_option('onepaycx_direct')); ?> />
                                <?php esc_html_e('Skip the 1pay.cx payment page — buyers go straight to the card processor', '1pay-cx-payments'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('Direct mode requires the buyer\'s email, so the Pay button shows an email field. Without a valid email the buyer sees the standard payment page instead.', '1pay-cx-payments'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
            <h2><?php esc_html_e('Add a Pay button', '1pay-cx-payments'); ?></h2>
            <p><?php esc_html_e('Use the "1pay.cx Pay Button" block, or paste a shortcode:', '1pay-cx-payments'); ?></p>
            <p><code>[onepaycx_button amount="25.00" label="Pay $25"]</code> &mdash; <?php esc_html_e('fixed amount', '1pay-cx-payments'); ?></p>
            <p><code>[onepaycx_button label="Donate" reference="donation"]</code> &mdash; <?php esc_html_e('buyer enters the amount', '1pay-cx-payments'); ?></p>
        </div>
        <?php
    }
}
