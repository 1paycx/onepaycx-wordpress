<?php
/**
 * Remove every option this plugin created when it is deleted (not merely
 * deactivated). Required so an uninstall leaves no rows behind.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('onepaycx_api_key');
delete_option('onepaycx_webhook_secret');
delete_option('onepaycx_direct');
delete_option('onepaycx_token_secret');
