<?php
/**
 * AI MCP - Analytics
 *
 * Tracks which AI agents are hitting the MCP endpoints, which endpoints
 * are most queried, and logs referrer bot signatures.
 *
 * Storage: Custom database table (wp_ai_mcp_analytics) to prevent
 * wp_options bloat and timeouts.
 *
 * @package AI_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AI_MCP_Analytics {

    /**
     * Known AI crawler / assistant signatures.
     * Format: 'Display Name' => ['substring', ...]
     */
    const AI_SIGNATURES = array(
        'Claude'          => array( 'claudebot', 'claude-web', 'anthropic' ),
        'ChatGPT'         => array( 'chatgpt-user', 'oai-searchbot', 'openai' ),
        'Perplexity'      => array( 'perplexitybot', 'perplexity' ),
        'Gemini'          => array( 'google-extended', 'bard', 'gemini' ),
        'Copilot/Bing'    => array( 'bingbot', 'msnbot', 'bingpreview' ),
        'You.com'         => array( 'youbot' ),
        'Cohere'          => array( 'cohere-ai' ),
        'Meta AI'         => array( 'facebookexternalhit', 'meta-ai' ),
        'Mistral'         => array( 'mistral' ),
        'Grok'            => array( 'grok' ),
        'Common Crawl'    => array( 'ccbot' ),
        'GPTBot'          => array( 'gptbot' ),
        'DuckAssist'      => array( 'duckassistbot' ),
    );

    public function __construct() {
        add_action('ai_mcp_prune_analytics', array($this, 'prune_old_entries'));
        if (!wp_next_scheduled('ai_mcp_prune_analytics')) {
            wp_schedule_event(time(), 'daily', 'ai_mcp_prune_analytics');
        }
    }

    /**
     * Record a single API hit directly to DB.
     *
     * @param string $endpoint  e.g. 'all', 'pages', 'chunks/12'
     */
    public function track( $endpoint ) {
        global $wpdb;
        $ua       = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
        $referrer = $this->detect_referrer( $ua );
        $ip_hash  = md5( $this->get_client_ip() ); // hash for privacy

        $wpdb->insert(
            $wpdb->prefix . 'ai_mcp_analytics',
            array(
                'ts'       => time(),
                'endpoint' => sanitize_text_field( $endpoint ),
                'agent'    => $referrer,
                'ua_short' => substr( $ua, 0, 150 ),
                'ip_hash'  => $ip_hash,
            ),
            array('%d', '%s', '%s', '%s', '%s')
        );
    }

    /**
     * Return aggregated stats for the admin dashboard.
     *
     * @return array
     */
    public function get_stats() {
        global $wpdb;
        $table   = $wpdb->prefix . 'ai_mcp_analytics';

        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");

        $top_endpoints = $wpdb->get_results(
            "SELECT endpoint, COUNT(*) as hits FROM {$table}
             GROUP BY endpoint ORDER BY hits DESC LIMIT 10",
            OBJECT_K
        );

        $top_agents = $wpdb->get_results(
            "SELECT agent, COUNT(*) as hits FROM {$table}
             GROUP BY agent ORDER BY hits DESC",
            OBJECT_K
        );

        // Last 7 days trend
        $trend = array();
        for ($i = 6; $i >= 0; $i--) {
            $day        = wp_date('Y-m-d', strtotime("-{$i} days"));
            $day_start  = strtotime($day . ' 00:00:00');
            $day_end    = $day_start + DAY_IN_SECONDS;
            $trend[$day] = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE ts >= %d AND ts < %d",
                $day_start, $day_end
            ));
        }

        $recent = $wpdb->get_results(
            "SELECT * FROM {$table} ORDER BY ts DESC LIMIT 20",
            ARRAY_A
        );

        return array(
            'total_requests' => $total,
            'top_endpoints'  => array_map(function($r) { return (int)$r->hits; }, (array)$top_endpoints),
            'top_agents'     => array_map(function($r) { return (int)$r->hits; }, (array)$top_agents),
            'last_7_days'    => $trend,
            'recent_hits'    => $recent,
        );
    }

    /**
     * Clear all analytics data.
     */
    public function clear() {
        global $wpdb;
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}ai_mcp_analytics");
    }

    /**
     * Prune log entries older than 90 days.
     */
    public function prune_old_entries() {
        global $wpdb;
        $cutoff = time() - (90 * DAY_IN_SECONDS);
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}ai_mcp_analytics WHERE ts < %d LIMIT 5000",
            $cutoff
        ));
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Detect the AI agent/referrer from User-Agent string.
     *
     * @param string $ua
     * @return string  Human-readable agent name or 'Other'.
     */
    private function detect_referrer( $ua ) {
        if ( empty( $ua ) ) {
            return 'Unknown';
        }
        $ua_lower = strtolower( $ua );
        foreach ( self::AI_SIGNATURES as $name => $patterns ) {
            foreach ( $patterns as $pattern ) {
                if ( strpos( $ua_lower, $pattern ) !== false ) {
                    return $name;
                }
            }
        }
        // Generic browser / unknown tool
        return 'Other';
    }

    /**
     * Get client IP, respecting common proxy headers.
     *
     * @return string
     */
    private function get_client_ip() {
        $headers = array(
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        );
        foreach ( $headers as $header ) {
            if ( ! empty( $_SERVER[ $header ] ) ) {
                // X-Forwarded-For can be a comma-separated list
                $ip = trim( explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) ) )[0] );
                if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }
}
