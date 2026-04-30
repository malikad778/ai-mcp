<?php
/**
 * AI MCP - Rate Limiter
 *
 * Simple rate limiting for public API endpoints to prevent abuse.
 * Uses WordPress transients for temporary storage.
 *
 * @package AI_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AI_MCP_Rate_Limiter {

    /**
     * Maximum requests per time window
     *
     * @var int
     */
    private $max_requests = 100;

    /**
     * Time window in seconds
     *
     * @var int
     */
    private $time_window = 3600; // 1 hour

    /**
     * Constructor.
     */
    public function __construct() {
        add_filter( 'rest_pre_dispatch', array( $this, 'check_rate_limit' ), 10, 3 );
    }

    /**
     * Check if the current request should be rate limited.
     *
     * @param mixed           $result  Response to replace the requested version with.
     * @param WP_REST_Server  $server  Server instance.
     * @param WP_REST_Request $request Request used to generate the response.
     * @return mixed
     */
    public function check_rate_limit( $result, $server, $request ) {
        // Only apply to our endpoints
        if ( strpos( $request->get_route(), '/ai-mcp/v1/' ) !== 0 ) {
            return $result;
        }

        // Bypass for admins
        if ( current_user_can( 'manage_options' ) ) {
            return $result;
        }

        // Get client identifier (IP address)
        $client_ip = $this->get_client_ip();

        // Check if rate limit is exceeded
        if ( $this->is_rate_limited( $client_ip ) ) {
            return new WP_Error(
                'ai_mcp_rate_limit_exceeded',
                __( 'Rate limit exceeded. Please try again later.', 'ai-mcp' ),
                array( 'status' => 429 )
            );
        }

        // Increment request count
        $this->increment_request_count( $client_ip );

        return $result;
    }

    /**
     * Get the client's IP address.
     *
     * @return string
     */
    private function get_client_ip() {
        $ip_keys = array(
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',  // Proxy
            'HTTP_X_REAL_IP',        // Nginx proxy
            'REMOTE_ADDR',           // Direct connection
        );

        foreach ( $ip_keys as $key ) {
            if ( ! empty( $_SERVER[ $key ] ) ) {
                $ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
                
                // Handle comma-separated IPs (X-Forwarded-For can have multiple)
                if ( strpos( $ip, ',' ) !== false ) {
                    $ip_array = explode( ',', $ip );
                    $ip       = trim( $ip_array[0] );
                }
                
                // Validate IP
                if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0'; // Fallback
    }

    private function get_transient_option_name( $client_ip ) {
        return '_transient_ai_mcp_rate_' . md5( $client_ip );
    }

    /**
     * Check if a client IP is rate limited.
     *
     * @param string $client_ip Client IP address.
     * @return bool
     */
    private function is_rate_limited( $client_ip ) {
        global $wpdb;

        $option_name = $this->get_transient_option_name( $client_ip );
        $timeout_key = '_transient_timeout_ai_mcp_rate_' . md5( $client_ip );

        // Check expiry first
        $expires_at = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
            $timeout_key
        ) );

        if ( ! $expires_at || time() > $expires_at ) {
            // Window expired — clean up and allow
            $wpdb->query( $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name IN (%s, %s)",
                $option_name, $timeout_key
            ) );
            return false;
        }

        $count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
            $option_name
        ) );

        return $count >= $this->max_requests;
    }

    /**
     * Increment the request count for a client IP.
     *
     * @param string $client_ip Client IP address.
     */
    private function increment_request_count( $client_ip ) {
        global $wpdb;

        $option_name = $this->get_transient_option_name( $client_ip );
        $timeout_key = '_transient_timeout_ai_mcp_rate_' . md5( $client_ip );
        $expiry      = time() + $this->time_window;

        // Try atomic increment first (row already exists)
        $updated = $wpdb->query( $wpdb->prepare(
            "UPDATE {$wpdb->options}
             SET option_value = option_value + 1
             WHERE option_name = %s",
            $option_name
        ) );

        if ( ! $updated ) {
            // First request in this window — insert both value + timeout rows
            // Use INSERT IGNORE to handle race on very first request
            $wpdb->query( $wpdb->prepare(
                "INSERT IGNORE INTO {$wpdb->options}
                 (option_name, option_value, autoload)
                 VALUES (%s, 1, 'no')",
                $option_name
            ) );
            $wpdb->query( $wpdb->prepare(
                "INSERT IGNORE INTO {$wpdb->options}
                 (option_name, option_value, autoload)
                 VALUES (%s, %d, 'no')",
                $timeout_key,
                $expiry
            ) );
        }
    }

    /**
     * Get current request count for an IP (for debugging/admin display).
     *
     * @param string $client_ip Client IP address.
     * @return int
     */
    public function get_request_count( $client_ip ) {
        global $wpdb;
        $val = $wpdb->get_var( $wpdb->prepare(
            "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
            $this->get_transient_option_name( $client_ip )
        ) );
        return $val ? (int) $val : 0;
    }

    /**
     * Clear rate limit for a specific IP (admin function).
     *
     * @param string $client_ip Client IP address.
     */
    public function clear_rate_limit( $client_ip ) {
        global $wpdb;
        $key     = '_transient_ai_mcp_rate_' . md5( $client_ip );
        $timeout = '_transient_timeout_ai_mcp_rate_' . md5( $client_ip );
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name IN (%s, %s)",
            $key, $timeout
        ) );
    }

    /**
     * Update rate limit settings (for future admin controls).
     *
     * @param int $max_requests Maximum requests per window.
     * @param int $time_window  Time window in seconds.
     */
    public function update_settings( $max_requests, $time_window ) {
        $this->max_requests = (int) $max_requests;
        $this->time_window  = (int) $time_window;
    }
}
