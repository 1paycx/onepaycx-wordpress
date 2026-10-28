<?php
/**
 * Plugin Name: 1pay.cx Payments
 * Plugin URI: https://1pay.cx/docs#wordpress
 * Description: Add a "Pay by card or crypto" button to any WordPress page or post with a block or the [onepaycx_button] shortcode. Buyers pay by card, Bitcoin or Monero. For stores, use "1pay.cx for WooCommerce" instead.
 * Version: 1.3.0
 * Author: 1pay.cx
 * Author URI: https://1pay.cx
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: 1pay-cx-payments
 *
 * All traffic goes to 1pay.cx / api.1pay.cx only.
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ONEPAYCX_WP_VERSION', '1.3.0');
define('ONEPAYCX_API_BASE', 'https://api.1pay.cx');   // REST API host (calls /v1/...)
define('ONEPAYCX_CHECKOUT_BASE', 'https://1pay.cx');  // hosted checkout host (/pay/<id>)
define('ONEPAYCX_WP_FILE', __FILE__);

require_once __DIR__ . '/includes/class-onepaycx-token.php';
require_once __DIR__ . '/includes/class-onepaycx-api.php';
require_once __DIR__ . '/includes/class-onepaycx-settings.php';
require_once __DIR__ . '/includes/class-onepaycx-rest.php';
require_once __DIR__ . '/includes/class-onepaycx-button.php';

add_action('plugins_loaded', function () {
    new OnePayCx_Settings();
    new OnePayCx_Rest();
    new OnePayCx_Button();
});
