<?php
/**
 * AI MCP - JSON Cache & Auto-Sync (v2 - Selective Regeneration)
 *
 * NEW: Selective regeneration - when a single post/page is updated only
 * that item's slice of the cache is rebuilt, not the entire site.
 *
 * NEW: Error log - failed HTTP fetches recorded in WP option.
 *
 * @package AI_MCP
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class AI_MCP_Cache {

    /** @var AI_MCP_Content_Reader */
    private $reader;

    /** @var string */
    private $cache_dir;

    const ERROR_LOG_OPTION = 'ai_mcp_error_log';
    const MAX_ERRORS       = 200;

    public function __construct( AI_MCP_Content_Reader $reader ) {
        $this->reader    = $reader;
        $this->cache_dir = ai_mcp_get_json_dir() . '/';

        add_action( 'save_post',      array( $this, 'on_post_saved' ),   20 );
        add_action( 'delete_post',    array( $this, 'on_post_deleted' ), 20 );
        add_action( 'updated_option', array( $this, 'on_option_update' ), 20, 1 );
        add_action( 'shutdown',       array( $this, 'maybe_flush_all' ) );

        add_action( 'wp_ajax_AI_MCP_regenerate',   array( $this, 'ajax_regenerate' ) );
        add_action( 'wp_ajax_AI_MCP_clear_errors', array( $this, 'ajax_clear_errors' ) );
        add_action( 'ai_mcp_fetch_error',          array( $this, 'log_error' ), 10, 2 );
    }

    // --- Selective regeneration ---

    public function on_post_saved( $post_id ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( wp_is_post_revision( $post_id ) )               return;

        $post = get_post( $post_id );
        if ( ! $post || ! in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
            update_option( 'AI_MCP_needs_regen', true, false );
            return;
        }

        $this->rebuild_post_slice( $post );
        update_option( 'AI_MCP_needs_all_rebuild', true, false );
    }

    public function on_post_deleted( $post_id ) {
        $post     = get_post( $post_id );
        $type     = $post ? $post->post_type : '';
        $filename = ( 'page' === $type ) ? 'pages.json' : 'posts.json';
        $data     = $this->read_json( $filename );

        if ( is_array( $data ) ) {
            $data = array_values( array_filter( $data, function( $item ) use ( $post_id ) {
                return ( $item['id'] ?? null ) !== $post_id;
            } ) );
            $this->write_json( $filename, $data );
        }
        update_option( 'AI_MCP_needs_all_rebuild', true, false );
    }

    private function rebuild_post_slice( $post ) {
        $filename = ( 'page' === $post->post_type ) ? 'pages.json' : 'posts.json';
        $data     = $this->read_json( $filename );
        if ( ! is_array( $data ) ) $data = array();

        // Remove stale entry
        $data = array_values( array_filter( $data, function( $item ) use ( $post ) {
            return ( $item['id'] ?? null ) !== $post->ID;
        } ) );

        if ( 'publish' === $post->post_status ) {
            $new_entry = ( 'page' === $post->post_type )
                ? $this->reader->get_page_as_array( $post )
                : $this->reader->get_post_as_array( $post );

            if ( $new_entry ) {
                $data[] = $new_entry;
                if ( 'page' === $post->post_type ) {
                    usort( $data, function( $a, $b ) { return strcmp( $a['title'] ?? '', $b['title'] ?? '' ); } );
                } else {
                    usort( $data, function( $a, $b ) { return strcmp( $b['date'] ?? '', $a['date'] ?? '' ); } );
                }
            }
        }

        $this->write_json( $filename, $data );
    }

    public function maybe_flush_all() {
        if ( get_option( 'AI_MCP_needs_regen' ) ) {
            delete_option( 'AI_MCP_needs_regen' );
            delete_option( 'AI_MCP_needs_all_rebuild' ); // Also clear this so it doesn't double-fire
            $this->regenerate_all();
            return;
        }
        if ( get_option( 'AI_MCP_needs_all_rebuild' ) ) {
            delete_option( 'AI_MCP_needs_all_rebuild' );
            $this->write_json( 'all.json', $this->reader->get_all() );
            update_option( 'AI_MCP_last_generated', current_time( 'mysql' ), false );
        }
    }

    // --- Full regeneration ---

    public function regenerate_all() {
        if ( ! file_exists( $this->cache_dir ) ) wp_mkdir_p( $this->cache_dir );

        $this->write_json( 'profile.json', $this->reader->get_profile() );
        $this->write_json( 'pages.json',   $this->reader->get_pages() );
        $this->write_json( 'posts.json',   $this->reader->get_posts_content() );
        $this->write_json( 'social.json',  $this->reader->get_social_links() );
        $this->write_json( 'menus.json',   $this->reader->get_menus() );
        $this->write_json( 'faqs.json',    $this->reader->get_faqs() );
        $this->write_json( 'authors.json', $this->reader->get_authors() );
        $this->write_json( 'media.json',   $this->reader->get_media() );
        $this->write_json( 'chunks.json',  $this->reader->get_content_index() );
        $this->write_json( 'all.json',     $this->reader->get_all() );

        update_option( 'AI_MCP_last_generated', current_time( 'mysql' ), false );
        flush_rewrite_rules();
    }

    // --- Option watcher ---

    public function on_option_update( $option ) {
        $watched = array( 'blogname', 'blogdescription', 'admin_email', 'wpseo_social' );
        if ( in_array( $option, $watched, true ) ) {
            update_option( 'AI_MCP_needs_regen', true, false );
        }
    }

    // --- Error logging ---

    public function log_error( $url, $reason = '' ) {
        $log = get_option( self::ERROR_LOG_OPTION, array() );
        array_unshift( $log, array(
            'ts'     => current_time( 'mysql' ),
            'url'    => esc_url_raw( $url ),
            'reason' => sanitize_text_field( $reason ),
        ) );
        if ( count( $log ) > self::MAX_ERRORS ) {
            $log = array_slice( $log, 0, self::MAX_ERRORS );
        }
        update_option( self::ERROR_LOG_OPTION, $log, false );
    }

    public function get_error_log() {
        return get_option( self::ERROR_LOG_OPTION, array() );
    }

    // --- AJAX ---

    public function ajax_regenerate() {
        check_ajax_referer( 'AI_MCP_regen_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
        $this->regenerate_all();
        wp_send_json_success( array(
            'message'   => 'All JSON files regenerated successfully.',
            'timestamp' => get_option( 'AI_MCP_last_generated' ),
        ) );
    }

    public function ajax_clear_errors() {
        check_ajax_referer( 'AI_MCP_regen_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
        delete_option( self::ERROR_LOG_OPTION );
        wp_send_json_success( array( 'message' => 'Error log cleared.' ) );
    }

    // --- Filesystem ---

    public function write_json( $filename, $data ) {
        $filepath = $this->cache_dir . $filename;
        $json     = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
        file_put_contents( $filepath, $json ); // phpcs:ignore WordPress.WP.AlternativeFunctions
    }

    private function read_json( $filename ) {
        $filepath = $this->cache_dir . $filename;
        if ( ! file_exists( $filepath ) ) return null;
        $content = file_get_contents( $filepath ); // phpcs:ignore WordPress.WP.AlternativeFunctions
        return $content ? json_decode( $content, true ) : null;
    }
}
