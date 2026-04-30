<?php
/**
 * Uninstall AI MCP Plugin
 *
 * Fired when the plugin is uninstalled.
 * Cleans up all plugin data, options, and cached files.
 *
 * @package AI_MCP
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

/**
 * Delete all plugin options
 */
delete_option( 'AI_MCP_last_generated' );
delete_option( 'AI_MCP_needs_regen' );
delete_option( 'AI_MCP_needs_all_rebuild' );
delete_option( 'ai_mcp_access_log' );
delete_option( 'ai_mcp_daily_stats' );
delete_option( 'ai_mcp_error_log' );
delete_option( 'ai_mcp_expose_author_emails' );

/**
 * Remove rate-limit tracking transients
 */
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ai_mcp_rate_%' OR option_name LIKE '_transient_timeout_ai_mcp_rate_%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'ai_mcp_rate_%'" );

/**
 * Drop Analytics custom database table
 */
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}ai_mcp_analytics" );
wp_clear_scheduled_hook('ai_mcp_prune_analytics');

/**
 * Delete all cached JSON files
 */
$upload_dir = wp_upload_dir();
$json_dir   = wp_normalize_path( $upload_dir['basedir'] . '/ai-mcp/' );

if ( file_exists( $json_dir ) && is_dir( $json_dir ) ) {
    $files = glob( $json_dir . '*.json' );
    
    if ( $files ) {
        foreach ( $files as $file ) {
            if ( is_file( $file ) ) {
                unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
            }
        }
    }
    
    if ( file_exists( $json_dir . 'index.php' ) ) {
        unlink( $json_dir . 'index.php' );
    }

    // Remove the directory
    rmdir( $json_dir );
}

/**
 * Flush rewrite rules to clean up /.well-known/mcp.json
 */
flush_rewrite_rules();
