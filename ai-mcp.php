<?php
/**
 * Plugin Name: AI MCP
 * Plugin URI:  https://wordpress.org/plugins/ai-mcp/
 * Description: Makes any WordPress site AI-readable. Exposes structured content via REST API and MCP. Includes semantic chunking, FAQ extraction, media indexing, author profiles, llms.txt, analytics, and error logging.
 * Version:     2.0.0
 * Author:      AI MCP
 * License:     GPL v2 or later
 * Text Domain: ai-mcp
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'AI_MCP_VERSION',    '2.0.0' );
define( 'AI_MCP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AI_MCP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

if ( ! function_exists( 'ai_mcp_get_json_dir' ) ) {
    function ai_mcp_get_json_dir() {
        $upload_dir = wp_upload_dir();
        return wp_normalize_path( $upload_dir['basedir'] . '/ai-mcp' );
    }
}

if ( ! function_exists( 'ai_mcp_get_json_url' ) ) {
    function ai_mcp_get_json_url() {
        $upload_dir = wp_upload_dir();
        return $upload_dir['baseurl'] . '/ai-mcp';
    }
}

// Core
require_once AI_MCP_PLUGIN_DIR . 'includes/class-mcp-content-reader.php';
require_once AI_MCP_PLUGIN_DIR . 'includes/class-mcp-endpoints.php';
require_once AI_MCP_PLUGIN_DIR . 'includes/class-mcp-cache.php';
require_once AI_MCP_PLUGIN_DIR . 'includes/class-mcp-discovery.php';
require_once AI_MCP_PLUGIN_DIR . 'includes/class-mcp-robots.php';
require_once AI_MCP_PLUGIN_DIR . 'includes/class-mcp-sitemap.php';
require_once AI_MCP_PLUGIN_DIR . 'includes/class-mcp-manifest.php';
require_once AI_MCP_PLUGIN_DIR . 'includes/class-mcp-rate-limiter.php';

// New modules (v2)
require_once AI_MCP_PLUGIN_DIR . 'includes/class-mcp-acf.php';
require_once AI_MCP_PLUGIN_DIR . 'includes/class-mcp-faq.php';
require_once AI_MCP_PLUGIN_DIR . 'includes/class-mcp-media.php';
require_once AI_MCP_PLUGIN_DIR . 'includes/class-mcp-authors.php';
require_once AI_MCP_PLUGIN_DIR . 'includes/class-mcp-chunker.php';
require_once AI_MCP_PLUGIN_DIR . 'includes/class-mcp-llms.php';
require_once AI_MCP_PLUGIN_DIR . 'includes/class-mcp-analytics.php';
require_once AI_MCP_PLUGIN_DIR . 'includes/class-mcp-docs-page.php';

// Admin
require_once AI_MCP_PLUGIN_DIR . 'admin/class-mcp-admin.php';

/**
 * Boot the plugin.
 */
function ai_mcp_v2_boot() {
    $reader    = new AI_MCP_Content_Reader();
    $analytics = new AI_MCP_Analytics();
    $cache     = new AI_MCP_Cache( $reader );

    new AI_MCP_Endpoints( $reader, $analytics );
    new AI_MCP_Manifest( $reader );
    new AI_MCP_Discovery();
    new AI_MCP_Robots();
    new AI_MCP_Sitemap();
    new AI_MCP_Rate_Limiter();
    new AI_MCP_LLMS();  // serves /llms.txt and /llms-full.txt
    new AI_MCP_Docs_Page(); // serves /ai-mcp-docs

    if ( is_admin() ) {
        new AI_MCP_Admin( $reader, $analytics, $cache );
    }
}
add_action( 'plugins_loaded', 'ai_mcp_v2_boot' );

/**
 * Activation: create DB, purge any stale cache, schedule fresh regen.
 */
function ai_mcp_v2_activate() {
    global $wpdb;
    
    // Create Analytics Table
    $table = $wpdb->prefix . 'ai_mcp_analytics';
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS {$table} (
        id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        ts          INT UNSIGNED NOT NULL,
        endpoint    VARCHAR(100) NOT NULL,
        agent       VARCHAR(50)  NOT NULL,
        ua_short    VARCHAR(150) NOT NULL DEFAULT '',
        ip_hash     CHAR(32)     NOT NULL,
        INDEX idx_ts       (ts),
        INDEX idx_endpoint (endpoint),
        INDEX idx_agent    (agent)
    ) {$charset_collate};";
    
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );

    $json_dir = ai_mcp_get_json_dir();
    if ( ! file_exists( $json_dir ) ) {
        wp_mkdir_p( $json_dir );
    }

    // Protect the directory by adding an index.php and perhaps .htaccess if necessary
    if ( ! file_exists( $json_dir . '/index.php' ) ) {
        file_put_contents( $json_dir . '/index.php', '<?php // Silence is golden.' );
    }

    // Purge stale/demo JSON from previous installs
    $stale = glob( $json_dir . DIRECTORY_SEPARATOR . '*.json' );
    if ( $stale ) {
        foreach ( $stale as $file ) {
            @unlink( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
        }
    }

    update_option( 'AI_MCP_needs_regen', true, false );
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'ai_mcp_v2_activate' );

/**
 * Deactivation: clean up rewrite rules.
 */
function ai_mcp_v2_deactivate() {
    wp_clear_scheduled_hook('ai_mcp_prune_analytics');
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'ai_mcp_v2_deactivate' );
