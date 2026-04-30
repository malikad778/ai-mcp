<?php
/**
 * AI MCP - MCP Manifest
 *
 * Dynamically generates and serves /.well-known/mcp.json.
 * This tells AI agents what tools/endpoints are available on this site.
 *
 * @package AI_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AI_MCP_Manifest {

    /**
     * @var AI_MCP_Content_Reader
     */
    private $reader;

    /**
     * Constructor.
     *
     * @param AI_MCP_Content_Reader $reader Content reader instance.
     */
    public function __construct( AI_MCP_Content_Reader $reader ) {
        $this->reader = $reader;
        add_action( 'init', array( $this, 'add_rewrite_rules' ) );
        add_action( 'template_redirect', array( $this, 'serve_manifest' ) );
        add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
    }

    /**
     * Add rewrite rule for /.well-known/mcp.json.
     */
    public function add_rewrite_rules() {
        add_rewrite_rule(
            '^\.well-known/mcp\.json$',
            'index.php?AI_MCP_manifest=1',
            'top'
        );
    }

    /**
     * Register the custom query var.
     */
    public function add_query_vars( $vars ) {
        $vars[] = 'AI_MCP_manifest';
        return $vars;
    }

    /**
     * Serve the manifest JSON when the rewrite rule matches.
     */
    public function serve_manifest() {
        if ( ! get_query_var( 'AI_MCP_manifest' ) ) {
            return;
        }

        $site_name = get_bloginfo( 'name' );

        $manifest = array(
            'mcpVersion'   => '1.0.0',
            'name'         => $site_name . ' MCP Server',
            'description'  => 'AI-readable structured content for ' . $site_name . '.',
            'capabilities' => array(
                'tools'     => true,
                'resources' => true,
            ),
            'endpoints'    => array(
                'all'     => rest_url( 'ai-mcp/v1/all' ),
                'profile' => rest_url( 'ai-mcp/v1/profile' ),
                'pages'   => rest_url( 'ai-mcp/v1/pages' ),
                'posts'   => rest_url( 'ai-mcp/v1/posts' ),
                'social'  => rest_url( 'ai-mcp/v1/social' ),
                'menus'   => rest_url( 'ai-mcp/v1/menus' ),
            ),
            'tools'        => array(
                array(
                    'name'        => 'get_all_content',
                    'description' => 'Fetch the complete site content in one structured JSON response.',
                ),
                array(
                    'name'        => 'get_profile',
                    'description' => 'Fetch the site owner\'s profile, bio, and contact info.',
                ),
                array(
                    'name'        => 'get_pages',
                    'description' => 'Fetch all published pages with titles, slugs, and content.',
                ),
                array(
                    'name'        => 'get_posts',
                    'description' => 'Fetch all published posts with categories and tags.',
                ),
                array(
                    'name'        => 'get_social',
                    'description' => 'Fetch all social media links associated with the site.',
                ),
            ),
            'contact'      => array(
                'name' => $site_name,
                'url'  => home_url(),
            ),
        );

        // Allow other plugins to filter the manifest
        $manifest = apply_filters( 'AI_MCP_manifest', $manifest );

        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Access-Control-Allow-Origin: *' );
        header( 'X-MCP-Version: ' . AI_MCP_VERSION );
        echo wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
        exit;
    }
}
