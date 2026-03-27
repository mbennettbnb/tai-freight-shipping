<?php
/**
 * Simple logger that writes to WooCommerce's log system.
 *
 * @package TAI_Freight_Shipping
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TAI_Logger {

    /**
     * WooCommerce logger source identifier.
     */
    private const SOURCE = 'tai-freight-shipping';

    /**
     * Whether debug logging is enabled in plugin settings.
     *
     * @var bool|null Lazy-loaded.
     */
    private $enabled;

    /**
     * Write a log entry (only when debug mode is on).
     *
     * @param string $message Message to log.
     * @param string $level   One of: emergency, alert, critical, error, warning, notice, info, debug.
     */
    public function log( string $message, string $level = 'debug' ): void {
        if ( ! $this->is_enabled() ) {
            return;
        }

        if ( ! function_exists( 'wc_get_logger' ) ) {
            return;
        }

        $logger = wc_get_logger();
        $logger->log( $level, $message, array( 'source' => self::SOURCE ) );
    }

    /**
     * Check whether debug mode is turned on in the plugin settings.
     *
     * @return bool
     */
    private function is_enabled(): bool {
        if ( null === $this->enabled ) {
            $settings      = get_option( 'woocommerce_tai_freight_settings', array() );
            $this->enabled = isset( $settings['debug_mode'] ) && 'yes' === $settings['debug_mode'];
        }
        return $this->enabled;
    }
}
