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

    // Product-level freight data fields.
    add_action( 'woocommerce_product_options_shipping', 'tai_freight_render_product_shipping_fields' );
    add_action( 'woocommerce_admin_process_product_object', 'tai_freight_save_product_shipping_fields' );

    // Variation-level freight data fields.
    add_action( 'woocommerce_variation_options_shipping', 'tai_freight_render_variation_shipping_fields', 10, 3 );
    add_action( 'woocommerce_save_product_variation', 'tai_freight_save_variation_shipping_fields', 10, 2 );
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
 * Render TAI freight fields on the WooCommerce product Shipping tab.
 */
function tai_freight_render_product_shipping_fields() {
    echo '<div class="options_group">';

    woocommerce_wp_text_input( array(
        'id'          => '_tai_nmfc',
        'label'       => __( 'NMFC', 'tai-freight-shipping' ),
        'description' => __( 'National Motor Freight Classification code for this product.', 'tai-freight-shipping' ),
        'desc_tip'    => true,
        'placeholder' => '156600',
    ) );

    woocommerce_wp_text_input( array(
        'id'          => '_tai_un_number',
        'label'       => __( 'UN Number', 'tai-freight-shipping' ),
        'description' => __( 'UN hazardous material identification number (if applicable).', 'tai-freight-shipping' ),
        'desc_tip'    => true,
        'placeholder' => 'UN1993',
    ) );

    woocommerce_wp_checkbox( array(
        'id'          => '_tai_hazardous_material',
        'label'       => __( 'Hazardous Material', 'tai-freight-shipping' ),
        'description' => __( 'Mark this product as hazardous for freight rating.', 'tai-freight-shipping' ),
    ) );

    woocommerce_wp_text_input( array(
        'id'                => '_tai_packaging_type',
        'label'             => __( 'Packaging Type', 'tai-freight-shipping' ),
        'description'       => __( 'TAI PackagingType enum value for this product.', 'tai-freight-shipping' ),
        'desc_tip'          => true,
        'type'              => 'number',
        'custom_attributes' => array(
            'min'  => '0',
            'step' => '1',
        ),
    ) );

    woocommerce_wp_text_input( array(
        'id'                => '_tai_packing_group',
        'label'             => __( 'Packing Group', 'tai-freight-shipping' ),
        'description'       => __( 'TAI PackingGroup enum value for this product.', 'tai-freight-shipping' ),
        'desc_tip'          => true,
        'type'              => 'number',
        'custom_attributes' => array(
            'min'  => '0',
            'step' => '1',
        ),
    ) );

    echo '</div>';
}

/**
 * Save TAI freight product fields.
 *
 * @param WC_Product $product Product object being saved.
 */
function tai_freight_save_product_shipping_fields( $product ) {
    if ( ! $product instanceof WC_Product ) {
        return;
    }

    $nmfc = isset( $_POST['_tai_nmfc'] ) ? sanitize_text_field( wp_unslash( $_POST['_tai_nmfc'] ) ) : '';
    $product->update_meta_data( '_tai_nmfc', $nmfc );

    $un_number = isset( $_POST['_tai_un_number'] ) ? sanitize_text_field( wp_unslash( $_POST['_tai_un_number'] ) ) : '';
    $product->update_meta_data( '_tai_un_number', $un_number );

    $hazardous_material = isset( $_POST['_tai_hazardous_material'] ) ? 'yes' : 'no';
    $product->update_meta_data( '_tai_hazardous_material', $hazardous_material );

    $packaging_type = isset( $_POST['_tai_packaging_type'] ) ? absint( wp_unslash( $_POST['_tai_packaging_type'] ) ) : '';
    $product->update_meta_data( '_tai_packaging_type', ( '' === $packaging_type ) ? '' : (string) $packaging_type );

    $packing_group = isset( $_POST['_tai_packing_group'] ) ? absint( wp_unslash( $_POST['_tai_packing_group'] ) ) : '';
    $product->update_meta_data( '_tai_packing_group', ( '' === $packing_group ) ? '' : (string) $packing_group );
}

/**
 * Render TAI freight fields for each product variation.
 *
 * @param int              $loop           Variation index.
 * @param array            $variation_data Variation data.
 * @param WP_Post|WC_Product_Variation $variation Variation object.
 */
function tai_freight_render_variation_shipping_fields( $loop, $variation_data, $variation ) {
    $variation_id = is_object( $variation ) && isset( $variation->ID ) ? absint( $variation->ID ) : 0;

    if ( $variation_id <= 0 ) {
        return;
    }

    $nmfc               = get_post_meta( $variation_id, '_tai_nmfc', true );
    $un_number          = get_post_meta( $variation_id, '_tai_un_number', true );
    $hazardous_material = get_post_meta( $variation_id, '_tai_hazardous_material', true );
    $packaging_type     = get_post_meta( $variation_id, '_tai_packaging_type', true );
    $packing_group      = get_post_meta( $variation_id, '_tai_packing_group', true );

    woocommerce_wp_text_input( array(
        'id'            => "_tai_nmfc_variation_{$loop}",
        'name'          => '_tai_nmfc_variation[' . $loop . ']',
        'label'         => __( 'TAI NMFC', 'tai-freight-shipping' ),
        'value'         => $nmfc,
        'desc_tip'      => true,
        'description'   => __( 'NMFC code for this variation.', 'tai-freight-shipping' ),
        'wrapper_class' => 'form-row form-row-full',
    ) );

    woocommerce_wp_text_input( array(
        'id'            => "_tai_un_number_variation_{$loop}",
        'name'          => '_tai_un_number_variation[' . $loop . ']',
        'label'         => __( 'TAI UN Number', 'tai-freight-shipping' ),
        'value'         => $un_number,
        'desc_tip'      => true,
        'description'   => __( 'UN number for this variation (if hazardous).', 'tai-freight-shipping' ),
        'wrapper_class' => 'form-row form-row-full',
    ) );

    woocommerce_wp_text_input( array(
        'id'                => "_tai_packaging_type_variation_{$loop}",
        'name'              => '_tai_packaging_type_variation[' . $loop . ']',
        'label'             => __( 'TAI Packaging Type', 'tai-freight-shipping' ),
        'value'             => $packaging_type,
        'type'              => 'number',
        'desc_tip'          => true,
        'description'       => __( 'PackagingType enum value for this variation.', 'tai-freight-shipping' ),
        'wrapper_class'     => 'form-row form-row-first',
        'custom_attributes' => array(
            'min'  => '0',
            'step' => '1',
        ),
    ) );

    woocommerce_wp_text_input( array(
        'id'                => "_tai_packing_group_variation_{$loop}",
        'name'              => '_tai_packing_group_variation[' . $loop . ']',
        'label'             => __( 'TAI Packing Group', 'tai-freight-shipping' ),
        'value'             => $packing_group,
        'type'              => 'number',
        'desc_tip'          => true,
        'description'       => __( 'PackingGroup enum value for this variation.', 'tai-freight-shipping' ),
        'wrapper_class'     => 'form-row form-row-last',
        'custom_attributes' => array(
            'min'  => '0',
            'step' => '1',
        ),
    ) );

    woocommerce_wp_checkbox( array(
        'id'            => "_tai_hazardous_material_variation_{$loop}",
        'name'          => '_tai_hazardous_material_variation[' . $loop . ']',
        'label'         => __( 'TAI Hazardous Material', 'tai-freight-shipping' ),
        'value'         => ( 'yes' === $hazardous_material ) ? 'yes' : 'no',
        'cbvalue'       => 'yes',
        'description'   => __( 'Mark this variation as hazardous.', 'tai-freight-shipping' ),
        'wrapper_class' => 'form-row form-row-full',
        'desc_tip'      => false,
        'class'         => 'checkbox',
    ) );
}

/**
 * Save TAI freight fields for each variation.
 *
 * @param int $variation_id Variation post ID.
 * @param int $loop         Variation index.
 */
function tai_freight_save_variation_shipping_fields( $variation_id, $loop ) {
    $nmfc = isset( $_POST['_tai_nmfc_variation'][ $loop ] )
        ? sanitize_text_field( wp_unslash( $_POST['_tai_nmfc_variation'][ $loop ] ) )
        : '';
    update_post_meta( $variation_id, '_tai_nmfc', $nmfc );

    $un_number = isset( $_POST['_tai_un_number_variation'][ $loop ] )
        ? sanitize_text_field( wp_unslash( $_POST['_tai_un_number_variation'][ $loop ] ) )
        : '';
    update_post_meta( $variation_id, '_tai_un_number', $un_number );

    $packaging_type = isset( $_POST['_tai_packaging_type_variation'][ $loop ] )
        ? absint( wp_unslash( $_POST['_tai_packaging_type_variation'][ $loop ] ) )
        : '';
    update_post_meta( $variation_id, '_tai_packaging_type', ( '' === $packaging_type ) ? '' : (string) $packaging_type );

    $packing_group = isset( $_POST['_tai_packing_group_variation'][ $loop ] )
        ? absint( wp_unslash( $_POST['_tai_packing_group_variation'][ $loop ] ) )
        : '';
    update_post_meta( $variation_id, '_tai_packing_group', ( '' === $packing_group ) ? '' : (string) $packing_group );

    $hazardous_material = isset( $_POST['_tai_hazardous_material_variation'][ $loop ] ) ? 'yes' : 'no';
    update_post_meta( $variation_id, '_tai_hazardous_material', $hazardous_material );
}

/**
 * Declare compatibility with WooCommerce HPOS (High-Performance Order Storage).
 */
add_action( 'before_woocommerce_init', function () {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );
