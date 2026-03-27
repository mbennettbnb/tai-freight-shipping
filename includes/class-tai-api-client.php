<?php
/**
 * TAI Public API Client.
 *
 * Handles all HTTP communication with the TAI Software Shipping Service API.
 *
 * @package TAI_Freight_Shipping
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TAI_API_Client {

    /**
     * Base URL (no trailing slash).
     *
     * @var string
     */
    private $base_url;

    /**
     * Authentication Key (GUID).
     *
     * @var string
     */
    private $auth_key;

    /**
     * Logger instance.
     *
     * @var TAI_Logger
     */
    private $logger;

    /**
     * API path prefix.
     *
     * @var string
     */
    private const API_PATH = '/PublicApi/Shipping';

    /**
     * @param string     $base_url Base URL of the TAI TMS server.
     * @param string     $auth_key Authentication GUID.
     * @param TAI_Logger $logger   Logger instance.
     */
    public function __construct( string $base_url, string $auth_key, TAI_Logger $logger ) {
        $this->base_url = untrailingslashit( $base_url );
        $this->auth_key = $auth_key;
        $this->logger   = $logger;
    }

    // ------------------------------------------------------------------
    //  Public methods matching TAI API endpoints
    // ------------------------------------------------------------------

    /**
     * Hello World – simple connectivity test (HTTP GET).
     *
     * @return string|WP_Error
     */
    public function hello_world() {
        return $this->get( '/helloWorld' );
    }

    /**
     * Verify authentication credentials (HTTP GET).
     *
     * @return array|WP_Error Decoded JSON or WP_Error.
     */
    public function verify_authentication() {
        if ( empty( $this->auth_key ) ) {
            return new \WP_Error( 'tai_missing_key', __( 'Authentication Key is not configured.', 'tai-freight-shipping' ) );
        }
        return $this->get( '/verifyauthentication/' . $this->auth_key );
    }

    /**
     * Get the list of accessorial codes (HTTP GET).
     *
     * @return array|WP_Error
     */
    public function get_accessorial_list() {
        if ( empty( $this->auth_key ) ) {
            return new \WP_Error( 'tai_missing_key', __( 'Authentication Key is not configured.', 'tai-freight-shipping' ) );
        }
        return $this->get( '/accessorialList/' . $this->auth_key );
    }

    /**
     * Get a rate quote (HTTP PUT).
     *
     * @param array $body Request body – see TAI_Freight_Shipping_Method::build_rate_request().
     * @return array|WP_Error Decoded JSON response or WP_Error.
     */
    public function get_rate_quote( array $body ) {
        return $this->put( '/getRateQuote', $body );
    }

    /**
     * Book a domestic shipment (HTTP PUT).
     *
     * @param array $body Booking request body.
     * @return array|WP_Error
     */
    public function book_domestic_shipment( array $body ) {
        return $this->put( '/bookDomesticShipment', $body );
    }

    /**
     * Get shipment status (HTTP PUT or GET – adjust once TAI confirms method).
     *
     * @param array $body Status request body.
     * @return array|WP_Error
     */
    public function get_shipment_status( array $body ) {
        return $this->put( '/ShipmentStatus', $body );
    }

    // ------------------------------------------------------------------
    //  Admin AJAX helpers – "Test Connection" button
    // ------------------------------------------------------------------

    /**
     * Register the AJAX actions for the admin test-connection button.
     */
    public static function register_ajax_hooks() {
        add_action( 'wp_ajax_tai_freight_test_connection', array( __CLASS__, 'ajax_test_connection' ) );
    }

    /**
     * AJAX handler: test the TAI API connection.
     */
    public static function ajax_test_connection() {
        check_ajax_referer( 'tai_freight_test_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'tai-freight-shipping' ) );
        }

        $api_url = isset( $_POST['api_url'] ) ? sanitize_text_field( wp_unslash( $_POST['api_url'] ) ) : '';
        $api_key = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';

        $logger = new TAI_Logger();
        $client = new self( $api_url, $api_key, $logger );

        // Step 1 – Hello World.
        $hello = $client->hello_world();
        if ( is_wp_error( $hello ) ) {
            wp_send_json_error( __( 'Could not reach the TAI server: ', 'tai-freight-shipping' ) . $hello->get_error_message() );
        }

        // Step 2 – Verify Authentication.
        if ( ! empty( $api_key ) ) {
            $auth = $client->verify_authentication();
            if ( is_wp_error( $auth ) ) {
                wp_send_json_error( __( 'Server reachable but authentication failed: ', 'tai-freight-shipping' ) . $auth->get_error_message() );
            }
        }

        wp_send_json_success( __( 'Connection successful!', 'tai-freight-shipping' ) );
    }

    // ------------------------------------------------------------------
    //  Internal HTTP helpers
    // ------------------------------------------------------------------

    /**
     * Perform an HTTP GET request.
     *
     * @param string $endpoint Path appended to base + API_PATH.
     * @return mixed|WP_Error
     */
    private function get( string $endpoint ) {
        $url = $this->build_url( $endpoint );

        $this->logger->log( "GET {$url}" );

        $response = wp_remote_get( $url, array(
            'timeout' => 30,
            'headers' => array( 'Accept' => 'application/json' ),
        ) );

        return $this->handle_response( $response );
    }

    /**
     * Perform an HTTP PUT request with a JSON body.
     *
     * @param string $endpoint Path appended to base + API_PATH.
     * @param array  $body     Data to JSON-encode.
     * @return mixed|WP_Error
     */
    private function put( string $endpoint, array $body ) {
        $url = $this->build_url( $endpoint );

        $this->logger->log( "PUT {$url} – body: " . wp_json_encode( $body ) );

        $response = wp_remote_request( $url, array(
            'method'  => 'PUT',
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ),
            'body'    => wp_json_encode( $body ),
        ) );

        return $this->handle_response( $response );
    }

    /**
     * Build the full URL for an API endpoint.
     *
     * @param string $endpoint Relative endpoint path.
     * @return string
     */
    private function build_url( string $endpoint ): string {
        $base = $this->resolve_base_url();
        return $base . self::API_PATH . '/' . ltrim( $endpoint, '/' );
    }

    /**
     * Determine the effective base URL, respecting test mode.
     *
     * @return string
     */
    private function resolve_base_url(): string {
        $settings = get_option( 'woocommerce_tai_freight_settings', array() );
        if ( isset( $settings['test_mode'] ) && 'yes' === $settings['test_mode'] ) {
            return 'http://www.taibeta.com';
        }
        return $this->base_url;
    }

    /**
     * Parse an HTTP response and return decoded JSON or WP_Error.
     *
     * @param array|WP_Error $response wp_remote_* response.
     * @return mixed|WP_Error
     */
    private function handle_response( $response ) {
        if ( is_wp_error( $response ) ) {
            $this->logger->log( 'HTTP Error: ' . $response->get_error_message(), 'error' );
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        $this->logger->log( "Response ({$code}): {$body}" );

        if ( $code < 200 || $code >= 300 ) {
            return new \WP_Error(
                'tai_http_error',
                sprintf(
                    /* translators: 1: HTTP status code 2: response body */
                    __( 'TAI API returned HTTP %1$d: %2$s', 'tai-freight-shipping' ),
                    $code,
                    $body
                )
            );
        }

        $decoded = json_decode( $body, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            // Some endpoints (like HelloWorld) return plain text.
            return $body;
        }

        return $decoded;
    }
}

// Register AJAX hooks.
TAI_API_Client::register_ajax_hooks();
