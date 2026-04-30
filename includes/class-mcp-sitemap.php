<?php
/**
 * AI MCP - Sitemap Integration
 *
 * Registers MCP endpoints in the WordPress XML Sitemap (Native, Yoast, or RankMath).
 * This ensures AI crawlers discover the structured JSON data.
 *
 * @package AI_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AI_MCP_Sitemap {

    /**
     * Constructor.
     */
    public function __construct() {
        // 1. Native WordPress Sitemaps (WP 5.5+)
        add_filter( 'wp_sitemaps_index_entries', array( $this, 'add_native_sitemap_index' ) );
        add_filter( 'wp_sitemaps_providers', array( $this, 'register_native_sitemap_provider' ) );

        // 2. Yoast SEO Sitemap
        add_filter( 'wpseo_sitemap_index', array( $this, 'add_to_yoast_sitemap' ) );

        // 3. RankMath Sitemap
        add_filter( 'rank_math/sitemap/index', array( $this, 'add_to_rankmath_sitemap' ) );
    }

    /**
     * Add MCP to Native WP Sitemap Index.
     */
    public function add_native_sitemap_index( $entries ) {
        $entries[] = array(
            'loc' => home_url( '/wp-sitemap-ai-mcp-1.xml' ),
        );
        return $entries;
    }

    /**
     * Register a custom provider for Native WP Sitemap.
     */
    public function register_native_sitemap_provider( $providers ) {
        $providers['ai-mcp'] = new AI_MCP_Sitemap_Provider();
        return $providers;
    }

    /**
     * Add MCP to Yoast SEO Sitemap index.
     */
    public function add_to_yoast_sitemap( $sitemap_custom ) {
        $now = gmdate( 'c' );
        $all_url = rest_url( 'ai-mcp/v1/all' );
        $sitemap_custom .= "
<sitemap>
    <loc>" . esc_url( $all_url ) . "</loc>
    <lastmod>" . esc_html( $now ) . "</lastmod>
</sitemap>";
        return $sitemap_custom;
    }

    /**
     * Add MCP to RankMath Sitemap index.
     */
    public function add_to_rankmath_sitemap( $xml ) {
        $now = gmdate( 'c' );
        $all_url = rest_url( 'ai-mcp/v1/all' );
        $xml .= "
<sitemap>
    <loc>" . esc_url( $all_url ) . "</loc>
    <lastmod>" . esc_html( $now ) . "</lastmod>
</sitemap>";
        return $xml;
    }
}

/**
 * Custom Provider for Native WP Sitemap.
 */
if ( class_exists( 'WP_Sitemaps_Provider' ) ) {
    class AI_MCP_Sitemap_Provider extends WP_Sitemaps_Provider {
        public function __construct() {
            $this->name = 'ai-mcp';
        }

        public function get_url_list( $page_num, $object_subtype = '' ) {
            $all_url = rest_url( 'ai-mcp/v1/all' );
            return array(
                array(
                    'loc' => $all_url,
                ),
            );
        }

        public function get_max_num_pages( $object_subtype = '' ) {
            return 1;
        }
    }
}
