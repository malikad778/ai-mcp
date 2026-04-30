<?php
/**
 * AI MCP - REST API Endpoints (v2)
 *
 * All routes under /wp-json/ai-mcp/v1/
 * New in v2: /faqs  /authors  /media  /chunks  /chunks/{id}  /analytics
 * Every request is tracked by AI_MCP_Analytics.
 *
 * @package AI_MCP
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class AI_MCP_Endpoints {

    /** @var AI_MCP_Content_Reader */
    private $reader;

    /** @var AI_MCP_Analytics */
    private $analytics;

    public function __construct( AI_MCP_Content_Reader $reader, AI_MCP_Analytics $analytics ) {
        $this->reader    = $reader;
        $this->analytics = $analytics;
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        $ns = 'ai-mcp/v1';

        $routes = array(
            array( '/all',                                      'get_all' ),
            array( '/profile',                                  'get_profile' ),
            array( '/pages',                                    'get_pages' ),
            array( '/pages/(?P<slug>[a-zA-Z0-9-]+)',            'get_page_by_slug' ),
            array( '/posts',                                    'get_posts' ),
            array( '/social',                                   'get_social' ),
            array( '/menus',                                    'get_menus' ),
            array( '/faqs',                                     'get_faqs' ),
            array( '/authors',                                  'get_authors' ),
            array( '/authors/(?P<id>[0-9]+)',                   'get_author_by_id' ),
            array( '/media',                                    'get_media' ),
            array( '/chunks',                                   'get_chunks_index' ),
            array( '/chunks/(?P<id>[0-9]+)',                    'get_chunks_for_post' ),
            array( '/analytics',                                'get_analytics' ),
        );

        foreach ( $routes as $route ) {
            register_rest_route( $ns, $route[0], array(
                'methods'             => 'GET',
                'callback'            => array( $this, $route[1] ),
                'permission_callback' => '__return_true',
            ) );
        }
    }

    // -------------------------------------------------------------------------
    // Endpoint callbacks
    // -------------------------------------------------------------------------

    public function get_all( $req ) {
        $this->track( 'all' );
        return $this->respond( $this->from_cache_or_live( 'all.json', 'get_all' ) );
    }

    public function get_profile( $req ) {
        $this->track( 'profile' );
        return $this->respond( $this->from_cache_or_live( 'profile.json', 'get_profile' ) );
    }

    public function get_pages( $req ) {
        $this->track( 'pages' );
        $data = $this->from_cache_or_live( 'pages.json', 'get_pages' );
        return $this->respond( array( 'pages' => $data ) );
    }

    public function get_page_by_slug( $req ) {
        $slug = $req->get_param( 'slug' );
        $this->track( 'pages/' . $slug );
        $data = $this->from_cache_or_live( 'pages.json', 'get_pages' );

        if ( isset( $data['error'] ) ) return $this->respond( $data );

        foreach ( $data as $page ) {
            if ( isset( $page['slug'] ) && $page['slug'] === $slug ) {
                return $this->respond( $page );
            }
        }
        return new WP_REST_Response( array( 'error' => 'Page not found', 'slug' => $slug ), 404 );
    }

    public function get_posts( $req ) {
        $this->track( 'posts' );
        $data = $this->from_cache_or_live( 'posts.json', 'get_posts_content' );
        return $this->respond( array( 'posts' => $data ) );
    }

    public function get_social( $req ) {
        $this->track( 'social' );
        $data = $this->from_cache_or_live( 'social.json', 'get_social_links' );
        return $this->respond( array( 'social' => $data ) );
    }

    public function get_menus( $req ) {
        $this->track( 'menus' );
        $data = $this->from_cache_or_live( 'menus.json', 'get_menus' );
        return $this->respond( array( 'menus' => $data ) );
    }

    public function get_faqs( $req ) {
        $this->track( 'faqs' );
        $data = $this->from_cache_or_live( 'faqs.json', 'get_faqs' );
        return $this->respond( array( 'faqs' => $data ) );
    }

    public function get_authors( $req ) {
        $this->track( 'authors' );
        $data = $this->from_cache_or_live( 'authors.json', 'get_authors' );
        return $this->respond( array( 'authors' => $data ) );
    }

    public function get_author_by_id( $req ) {
        $id = (int) $req->get_param( 'id' );
        $this->track( 'authors/' . $id );
        $user = get_user_by( 'id', $id );
        if ( ! $user ) {
            return new WP_REST_Response( array( 'error' => 'Author not found' ), 404 );
        }
        // Re-use Authors class directly for single author
        $authors = new AI_MCP_Authors();
        return $this->respond( $authors->get_by_id( $id ) );
    }

    public function get_media( $req ) {
        $this->track( 'media' );
        $data = $this->from_cache_or_live( 'media.json', 'get_media' );
        return $this->respond( array( 'media' => $data, 'total' => is_array( $data ) ? count( $data ) : 0 ) );
    }

    public function get_chunks_index( $req ) {
        $this->track( 'chunks' );
        $data = $this->from_cache_or_live( 'chunks.json', 'get_content_index' );
        return $this->respond( array(
            'description' => 'Content index. Use chunks_url per item to fetch chunked content of long pages without exceeding AI context window.',
            'items'       => $data,
            'total'       => is_array( $data ) ? count( $data ) : 0,
        ) );
    }

    public function get_chunks_for_post( $req ) {
        $post_id = (int) $req->get_param( 'id' );
        $this->track( 'chunks/' . $post_id );

        $post = get_post( $post_id );
        if ( ! $post || 'publish' !== $post->post_status ) {
            return new WP_REST_Response( array( 'error' => 'Post not found or not published', 'id' => $post_id ), 404 );
        }

        $chunks = $this->reader->get_chunks_for_post( $post_id );
        return $this->respond( array(
            'post_id'      => $post_id,
            'title'        => $post->post_title,
            'url'          => get_permalink( $post_id ),
            'total_chunks' => count( $chunks ),
            'chunks'       => $chunks,
        ) );
    }

    public function get_analytics( $req ) {
        // Analytics endpoint requires admin - not public
        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_REST_Response( array( 'error' => 'Unauthorized' ), 403 );
        }
        
        $response = new WP_REST_Response( $this->analytics->get_stats(), 200 );
        $response->set_headers( array(
            'Access-Control-Allow-Origin' => '*',
            'X-MCP-Version'               => AI_MCP_VERSION,
            'Cache-Control'               => 'private, no-store',
        ) );
        return $response;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Read from JSON cache; fall back to live reader method if cache is empty.
     *
     * @param string $filename  e.g. 'all.json'
     * @param string $method    AI_MCP_Content_Reader method name
     * @return array
     */
    private function from_cache_or_live( $filename, $method ) {
        $filepath = ai_mcp_get_json_dir() . '/' . $filename;

        if ( file_exists( $filepath ) && filesize( $filepath ) > 2 ) {
            $content = file_get_contents( $filepath ); // phpcs:ignore
            if ( $content ) {
                $decoded = json_decode( $content, true );
                if ( is_array( $decoded ) && ! empty( $decoded ) ) {
                    return $decoded;
                }
            }
        }

        // Cache miss - go live and queue a background regen
        update_option( 'AI_MCP_needs_regen', true, false );

        if ( method_exists( $this->reader, $method ) ) {
            return call_user_func( array( $this->reader, $method ) );
        }
        return array( 'error' => "Cache empty. Trigger regeneration in AI MCP admin." );
    }

    private function track( $endpoint ) {
        $this->analytics->track( $endpoint );
    }

    private function respond( $data ) {
        $response = new WP_REST_Response( $data, 200 );
        $response->set_headers( array(
            'Access-Control-Allow-Origin' => '*',
            'X-MCP-Version'              => AI_MCP_VERSION,
            'Cache-Control'              => 'public, max-age=3600',
        ) );
        return $response;
    }
}
