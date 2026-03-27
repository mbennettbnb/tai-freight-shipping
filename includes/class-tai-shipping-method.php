<?php
/**
 * TAI Freight Shipping Method for WooCommerce.
 *
 * @package TAI_Freight_Shipping
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WooCommerce shipping method that calls the TAI Software getRateQuote API.
 */
class TAI_Freight_Shipping_Method extends WC_Shipping_Method {

    /**
     * API client instance.
     *
     * @var TAI_API_Client
     */
    private $api_client;

    /**
     * Logger instance.
     *
     * @var TAI_Logger
     */
    private $logger;

    /**
     * Constructor.
     *
     * @param int $instance_id Shipping zone instance ID.
     */
    public function __construct( $instance_id = 0 ) {
        $this->id                 = 'tai_freight';
        $this->instance_id        = absint( $instance_id );
        $this->method_title       = __( 'TAI Freight Shipping', 'tai-freight-shipping' );
        $this->method_description = __( 'Real-time freight shipping rates from the TAI Software API.', 'tai-freight-shipping' );
        $this->supports           = array(
            'shipping-zones',
            'instance-settings',
            'instance-settings-modal',
        );

        $this->init();
    }

    /**
     * Initialise settings, form fields, and dependencies.
     */
    private function init() {
        $this->init_form_fields();
        $this->init_settings();

        $this->title   = $this->get_option( 'title', $this->method_title );
        $this->enabled = $this->get_option( 'enabled', 'yes' );

        $this->logger     = new TAI_Logger();
        $this->api_client = new TAI_API_Client(
            $this->get_option( 'api_url', '' ),
            $this->get_option( 'api_key', '' ),
            $this->logger
        );

        // Save settings.
        add_action(
            'woocommerce_update_options_shipping_' . $this->id,
            array( $this, 'process_admin_options' )
        );
    }

    /**
     * Define the settings fields shown in WooCommerce > Settings > Shipping.
     */
    public function init_form_fields() {
        $this->instance_form_fields = array(
            'title'          => array(
                'title'       => __( 'Method Title', 'tai-freight-shipping' ),
                'type'        => 'text',
                'description' => __( 'Title shown to the customer at checkout.', 'tai-freight-shipping' ),
                'default'     => __( 'Freight Shipping', 'tai-freight-shipping' ),
                'desc_tip'    => true,
            ),
            'tax_status'     => array(
                'title'   => __( 'Tax Status', 'tai-freight-shipping' ),
                'type'    => 'select',
                'default' => 'taxable',
                'options' => array(
                    'taxable' => __( 'Taxable', 'tai-freight-shipping' ),
                    'none'    => __( 'None', 'tai-freight-shipping' ),
                ),
            ),
        );

        $this->form_fields = array(
            'enabled'              => array(
                'title'   => __( 'Enable/Disable', 'tai-freight-shipping' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable TAI Freight Shipping', 'tai-freight-shipping' ),
                'default' => 'yes',
            ),
            'api_url'              => array(
                'title'       => __( 'API Base URL', 'tai-freight-shipping' ),
                'type'        => 'text',
                'description' => __( 'The base URL for the TAI Public API (e.g. https://atl.taicloud.net). Do NOT include /PublicApi/Shipping/.', 'tai-freight-shipping' ),
                'default'     => '',
                'placeholder' => 'https://atl.taicloud.net',
                'desc_tip'    => true,
            ),
            'api_key'              => array(
                'title'       => __( 'Authentication Key', 'tai-freight-shipping' ),
                'type'        => 'password',
                'description' => __( 'Your TAI API Authentication Key (GUID).', 'tai-freight-shipping' ),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'test_mode'            => array(
                'title'       => __( 'Test Mode', 'tai-freight-shipping' ),
                'type'        => 'checkbox',
                'label'       => __( 'Use the TAI Beta/Test server instead of your production URL.', 'tai-freight-shipping' ),
                'description' => __( 'When enabled the plugin calls http://www.taibeta.com instead of the API Base URL above.', 'tai-freight-shipping' ),
                'default'     => 'no',
                'desc_tip'    => true,
            ),
            'origin_zip'           => array(
                'title'       => __( 'Origin Zip Code', 'tai-freight-shipping' ),
                'type'        => 'text',
                'description' => __( 'Default origin / warehouse zip code used for rate quotes.', 'tai-freight-shipping' ),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'origin_city'          => array(
                'title'       => __( 'Origin City', 'tai-freight-shipping' ),
                'type'        => 'text',
                'description' => __( 'Default origin city.', 'tai-freight-shipping' ),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'origin_state'         => array(
                'title'       => __( 'Origin State', 'tai-freight-shipping' ),
                'type'        => 'text',
                'description' => __( 'Default origin state abbreviation (e.g. GA).', 'tai-freight-shipping' ),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'default_freight_class' => array(
                'title'       => __( 'Default Freight Class', 'tai-freight-shipping' ),
                'type'        => 'select',
                'description' => __( 'Freight class to use when a product does not specify one.', 'tai-freight-shipping' ),
                'default'     => '70',
                'desc_tip'    => true,
                'options'     => $this->get_freight_class_options(),
            ),
            'fallback_rate'        => array(
                'title'       => __( 'Fallback Rate ($)', 'tai-freight-shipping' ),
                'type'        => 'text',
                'description' => __( 'A flat rate to show when the API is unreachable. Leave blank to hide shipping when the API fails.', 'tai-freight-shipping' ),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'rate_adjustment'      => array(
                'title'       => __( 'Rate Adjustment (%)', 'tai-freight-shipping' ),
                'type'        => 'text',
                'description' => __( 'Add a percentage markup or markdown to the returned rates. E.g. 10 for +10%, -5 for -5%.', 'tai-freight-shipping' ),
                'default'     => '0',
                'desc_tip'    => true,
            ),
            'cache_duration'       => array(
                'title'       => __( 'Cache Duration (minutes)', 'tai-freight-shipping' ),
                'type'        => 'number',
                'description' => __( 'How long to cache rate quotes. Set to 0 to disable caching.', 'tai-freight-shipping' ),
                'default'     => '30',
                'desc_tip'    => true,
                'custom_attributes' => array( 'min' => 0, 'step' => 1 ),
            ),
            'debug_mode'           => array(
                'title'   => __( 'Debug Logging', 'tai-freight-shipping' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable debug logging (WooCommerce > Status > Logs).', 'tai-freight-shipping' ),
                'default' => 'no',
            ),
        );
    }

    /**
     * Return NMFC freight class options.
     *
     * @return array
     */
    private function get_freight_class_options() {
        return array(
            '50'    => '50',
            '55'    => '55',
            '60'    => '60',
            '65'    => '65',
            '70'    => '70',
            '77.5'  => '77.5',
            '85'    => '85',
            '92.5'  => '92.5',
            '100'   => '100',
            '110'   => '110',
            '125'   => '125',
            '150'   => '150',
            '175'   => '175',
            '200'   => '200',
            '250'   => '250',
            '300'   => '300',
            '400'   => '400',
            '500'   => '500',
        );
    }

    /**
     * Calculate shipping rates when the cart/checkout page is loaded.
     *
     * @param array $package Cart package with items and destination.
     */
    public function calculate_shipping( $package = array() ) {
        if ( 'yes' !== $this->enabled ) {
            return;
        }

        $destination = $package['destination'];
        if ( empty( $destination['postcode'] ) ) {
            return;
        }

        // Build commodity list from cart items.
        $commodities = $this->build_commodities( $package );
        if ( empty( $commodities ) ) {
            return;
        }

        // Build the request payload.
        $request_body = $this->build_rate_request( $destination, $commodities );

        // Check transient cache.
        $cache_key      = 'tai_freight_' . md5( wp_json_encode( $request_body ) );
        $cache_duration = absint( $this->get_option( 'cache_duration', 30 ) );
        if ( $cache_duration > 0 ) {
            $cached = get_transient( $cache_key );
            if ( false !== $cached ) {
                $this->add_rates_from_response( $cached );
                return;
            }
        }

        // Call the TAI API.
        $response = $this->api_client->get_rate_quote( $request_body );

        if ( is_wp_error( $response ) ) {
            $this->logger->log( 'Rate quote API error: ' . $response->get_error_message(), 'error' );
            $this->maybe_add_fallback_rate();
            return;
        }

        // Cache the successful response.
        if ( $cache_duration > 0 ) {
            set_transient( $cache_key, $response, $cache_duration * MINUTE_IN_SECONDS );
        }

        $this->add_rates_from_response( $response );
    }

    /**
     * Build the rate quote request body.
     *
     * @param array $destination WooCommerce destination array.
     * @param array $commodities Prepared commodity list.
     * @return array
     */
    private function build_rate_request( $destination, $commodities ) {
        $origin_zip   = sanitize_text_field( $this->get_option( 'origin_zip', '' ) );
        $origin_city  = sanitize_text_field( $this->get_option( 'origin_city', '' ) );
        $origin_state = sanitize_text_field( $this->get_option( 'origin_state', '' ) );

        $dest_zip   = sanitize_text_field( $destination['postcode'] );
        $dest_city  = sanitize_text_field( $destination['city'] );
        $dest_state = sanitize_text_field( $destination['state'] );

        /**
         * Filter the rate quote request body before sending to the TAI API.
         *
         * This is the primary hook for adjusting the request payload to match
         * the exact schema expected by the TAI API once you have sample JSON.
         *
         * @param array $body        The request body array.
         * @param array $destination WooCommerce destination.
         * @param array $commodities Commodity list.
         */
        return apply_filters( 'tai_freight_rate_request_body', array(
            'AuthenticationKey' => sanitize_text_field( $this->get_option( 'api_key', '' ) ),
            'Origin'            => array(
                'City'    => $origin_city,
                'State'   => $origin_state,
                'ZipCode' => $origin_zip,
            ),
            'Destination'       => array(
                'City'    => $dest_city,
                'State'   => $dest_state,
                'ZipCode' => $dest_zip,
            ),
            'Commodities'       => $commodities,
            'Accessorials'      => array(),
        ), $destination, $commodities );
    }

    /**
     * Build a commodities array from the package items.
     *
     * @param array $package WooCommerce shipping package.
     * @return array
     */
    private function build_commodities( $package ) {
        $commodities         = array();
        $default_class       = sanitize_text_field( $this->get_option( 'default_freight_class', '70' ) );

        foreach ( $package['contents'] as $item_id => $item ) {
            $product = $item['data'];
            if ( ! $product || ! $product->needs_shipping() ) {
                continue;
            }

            $qty    = $item['quantity'];
            $weight = (float) $product->get_weight();
            $length = (float) $product->get_length();
            $width  = (float) $product->get_width();
            $height = (float) $product->get_height();

            // Weight is required – skip items with none.
            if ( $weight <= 0 ) {
                $weight = 1; // Default to 1 lb to avoid API rejection.
            }

            // Allow a custom freight class to be stored as product meta.
            $freight_class = get_post_meta( $product->get_id(), '_tai_freight_class', true );
            if ( empty( $freight_class ) ) {
                $freight_class = $default_class;
            }

            $commodities[] = apply_filters( 'tai_freight_commodity_item', array(
                'Description' => sanitize_text_field( $product->get_name() ),
                'Weight'      => $weight * $qty,
                'Class'       => $freight_class,
                'Pieces'      => $qty,
                'Length'      => $length,
                'Width'       => $width,
                'Height'      => $height,
            ), $product, $qty );
        }

        return $commodities;
    }

    /**
     * Parse the API response and register shipping rates with WooCommerce.
     *
     * @param array $response Decoded JSON response from the TAI API.
     */
    private function add_rates_from_response( $response ) {
        /**
         * Filter the parsed API response before rates are added.
         *
         * @param array $response Decoded API response.
         */
        $response = apply_filters( 'tai_freight_rate_response', $response );

        // -----------------------------------------------------------
        // Map the response to rates.
        //
        // The TAI API returns a list of carrier quotes. Adjust the
        // array keys below once you have the actual sample JSON from
        // TAI support. The current mapping represents a best-guess
        // based on the documentation.
        // -----------------------------------------------------------
        $quotes = array();

        if ( isset( $response['Quotes'] ) && is_array( $response['Quotes'] ) ) {
            $quotes = $response['Quotes'];
        } elseif ( isset( $response['RateQuotes'] ) && is_array( $response['RateQuotes'] ) ) {
            $quotes = $response['RateQuotes'];
        } elseif ( isset( $response['CarrierQuotes'] ) && is_array( $response['CarrierQuotes'] ) ) {
            $quotes = $response['CarrierQuotes'];
        } elseif ( is_array( $response ) && ! empty( $response ) && isset( reset( $response )['TotalPrice'] ) ) {
            // Response might be a flat list of quote objects.
            $quotes = $response;
        }

        if ( empty( $quotes ) ) {
            $this->logger->log( 'No quotes found in API response.', 'warning' );
            $this->maybe_add_fallback_rate();
            return;
        }

        $adjustment_pct = (float) $this->get_option( 'rate_adjustment', 0 );
        $tax_status     = $this->get_option( 'tax_status', 'taxable' );

        foreach ( $quotes as $index => $quote ) {
            // Extract price – try common key names.
            $cost = 0;
            foreach ( array( 'TotalPrice', 'Total', 'Price', 'Cost', 'Rate', 'TotalCost', 'TotalCharges' ) as $key ) {
                if ( isset( $quote[ $key ] ) && is_numeric( $quote[ $key ] ) ) {
                    $cost = (float) $quote[ $key ];
                    break;
                }
            }

            if ( $cost <= 0 ) {
                continue;
            }

            // Apply adjustment.
            if ( 0 !== $adjustment_pct ) {
                $cost = $cost * ( 1 + ( $adjustment_pct / 100 ) );
            }

            // Build a label from carrier info.
            $carrier_name = '';
            foreach ( array( 'CarrierName', 'Carrier', 'SCAC', 'ServiceLevel', 'CarrierSCAC' ) as $key ) {
                if ( ! empty( $quote[ $key ] ) ) {
                    $carrier_name = sanitize_text_field( $quote[ $key ] );
                    break;
                }
            }

            $service_desc = '';
            foreach ( array( 'ServiceDescription', 'ServiceLevel', 'Service', 'TariffDescription' ) as $key ) {
                if ( ! empty( $quote[ $key ] ) && $quote[ $key ] !== $carrier_name ) {
                    $service_desc = sanitize_text_field( $quote[ $key ] );
                    break;
                }
            }

            $transit = '';
            foreach ( array( 'TransitDays', 'EstimatedTransitDays', 'TransitTime' ) as $key ) {
                if ( ! empty( $quote[ $key ] ) ) {
                    /* translators: %s: number of transit days */
                    $transit = sprintf( __( ' (%s days)', 'tai-freight-shipping' ), sanitize_text_field( $quote[ $key ] ) );
                    break;
                }
            }

            $label = trim( $carrier_name . ( $service_desc ? ' – ' . $service_desc : '' ) . $transit );
            if ( empty( $label ) ) {
                $label = $this->title . ' #' . ( $index + 1 );
            }

            $rate = array(
                'id'        => $this->get_rate_id( 'tai_' . $index ),
                'label'     => $label,
                'cost'      => round( $cost, 2 ),
                'taxes'     => ( 'taxable' === $tax_status ) ? '' : false,
                'package'   => false,
                'meta_data' => array(
                    'tai_raw_quote' => $quote,
                ),
            );

            $this->add_rate( $rate );
        }
    }

    /**
     * Add a fallback flat rate when the API call fails.
     */
    private function maybe_add_fallback_rate() {
        $fallback = $this->get_option( 'fallback_rate', '' );
        if ( '' === $fallback || ! is_numeric( $fallback ) ) {
            return;
        }

        $this->add_rate( array(
            'id'    => $this->get_rate_id( 'fallback' ),
            'label' => $this->title . ' ' . __( '(estimated)', 'tai-freight-shipping' ),
            'cost'  => round( (float) $fallback, 2 ),
        ) );
    }
}
