<?php
/**
 * Fired when the plugin is uninstalled via the WordPress Plugins screen.
 *
 * @package TAI_Freight_Shipping
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Remove plugin options.
delete_option( 'woocommerce_tai_freight_settings' );

// Remove any cached rate transients.
global $wpdb;
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
        $wpdb->esc_like( '_transient_tai_freight_' ) . '%',
        $wpdb->esc_like( '_transient_timeout_tai_freight_' ) . '%'
    )
);
