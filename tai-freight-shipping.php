<?php
/**
 * Plugin Name: TAI Freight Shipping
 * Plugin URI:  https://example.com/tai-freight-shipping
 * Description: WooCommerce shipping method that fetches real-time freight rates from the TAI Software Public API.
 * Version:     1.0.0
 * Author:      Michael Bennett
 * Author URI:  https://github.com/mbennettbnb
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: tai-freight-shipping
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants.
define( 'TAI_FREIGHT_VERSION', '1.0.0' );
define( 'TAI_FREIGHT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TAI_FREIGHT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'TAI_FREIGHT_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Check if WooCommerce is active before initialising.
 */
function tai_freight_check_woocommerce() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', 'tai_freight_wc_missing_notice' );
        return;
    }
    tai_freight_init();
}
add_action( 'plugins_loaded', 'tai_freight_check_woocommerce', 20 );

/**
 * Admin notice when WooCommerce is not active.
 */
function tai_freight_wc_missing_notice() {
    ?>
    <div class="notice notice-error">
        <p>
            <strong><?php esc_html_e( 'TAI Freight Shipping', 'tai-freight-shipping' ); ?></strong>
            <?php esc_html_e( 'requires WooCommerce to be installed and active.', 'tai-freight-shipping' ); ?>
        </p>
    </div>
    <?php
}

/**
 * Initialise the plugin – load classes and register the shipping method.
 */
function tai_freight_init() {
    // Include required files.
    require_once TAI_FREIGHT_PLUGIN_DIR . 'includes/class-tai-logger.php';
    require_once TAI_FREIGHT_PLUGIN_DIR . 'includes/class-tai-api-client.php';
    require_once TAI_FREIGHT_PLUGIN_DIR . 'includes/class-tai-shipping-method.php';

    // Register the shipping method with WooCommerce.
    add_filter( 'woocommerce_shipping_methods', 'tai_freight_add_shipping_method' );

    // Add a settings link on the Plugins page.
    add_filter( 'plugin_action_links_' . TAI_FREIGHT_PLUGIN_BASENAME, 'tai_freight_plugin_action_links' );

    // Enqueue admin styles.
    add_action( 'admin_enqueue_scripts', 'tai_freight_admin_styles' );
}

/**
 * Register our shipping method.
 *
 * @param array $methods Registered shipping methods.
 * @return array
 */
function tai_freight_add_shipping_method( $methods ) {
    $methods['tai_freight'] = 'TAI_Freight_Shipping_Method';
    return $methods;
}

/**
 * Add a "Settings" link on the Plugins list page.
 *
 * @param array $links Existing action links.
 * @return array
 */
function tai_freight_plugin_action_links( $links ) {
    $settings_url  = admin_url( 'admin.php?page=wc-settings&tab=shipping&section=tai_freight' );
    $settings_link = '<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', 'tai-freight-shipping' ) . '</a>';
    array_unshift( $links, $settings_link );
    return $links;
}

/**
 * Enqueue admin-only CSS.
 *
 * @param string $hook Current admin page hook.
 */
function tai_freight_admin_styles( $hook ) {
    if ( 'woocommerce_page_wc-settings' !== $hook ) {
        return;
    }
    wp_enqueue_style(
        'tai-freight-admin',
        TAI_FREIGHT_PLUGIN_URL . 'assets/css/admin.css',
        array(),
        TAI_FREIGHT_VERSION
    );
}

/**
 * Declare compatibility with WooCommerce HPOS (High-Performance Order Storage).
 */
add_action( 'before_woocommerce_init', function () {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );
