<?php
/**
 * Removes the plugin's settings.
 *
 * @package Dernekyazilimi
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'dernekyazilimi_settings' );
delete_transient( 'dernekyazilimi_config' );
