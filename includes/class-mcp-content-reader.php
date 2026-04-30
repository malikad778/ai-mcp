<?php
/**
 * AI MCP - Content Reader (v2)
 *
 * The brain of the plugin.  Dynamically reads ALL content from any WordPress
 * site.  In v2 it also:
 *   - Integrates ACF, FAQ, Media, Authors, and Chunker modules
 *   - Fires ai_mcp_fetch_error action on HTTP failures so the cache can log them
 *   - Exposes get_page_as_array() / get_post_as_array() for selective regen
 *   - Returns a compact /all response with summaries + index links (not full
 *     content dumps) so AI agents get everything without running out of context
 *
 * @package AI_MCP
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class AI_MCP_Content_Reader {

    /** @var AI_MCP_ACF */
    private $acf;
    /** @var AI_MCP_FAQ */
    private $faq;
    /** @var AI_MCP_Media */
    private $media;
    /** @var AI_MCP_Authors */
    private $authors;
    /** @var AI_MCP_Chunker */
    private $chunker;

    public function __construct() {
        $this->acf     = new AI_MCP_ACF();
        $this->faq     = new AI_MCP_FAQ();
        $this->media   = new AI_MCP_Media();
        $this->authors = new AI_MCP_Authors();
        $this->chunker = new AI_MCP_Chunker();
    }

    // =========================================================================
    // Profile
    // =========================================================================

    public function get_profile() {
        $about_slug    = apply_filters( 'ai_mcp_profile_page_slug', 'about' );
        $about_page    = get_page_by_path( $about_slug );
        $about_content = '';
        if ( $about_page ) {
            $raw = apply_filters( 'the_content', $about_page->post_content );
            $about_content = $this->clean( $raw );
        }

        $profile = array(
            'name'      => $this->clean( get_bloginfo( 'name' ) ),
            'tagline'   => $this->clean( get_bloginfo( 'description' ) ),
            'url'       => home_url(),
            'language'  => get_bloginfo( 'language' ),
            'bio'       => $about_content,
            'social'    => $this->get_social_links(),
        );
        
        if ( get_option( 'ai_mcp_expose_author_emails', false ) ) {
            $profile['email'] = get_bloginfo( 'admin_email' );
        }
        
        return $profile;
    }

    // =========================================================================
    // Pages
    // =========================================================================

    public function get_pages() {
        $pages  = get_pages( array(
            'post_status' => 'publish',
            'sort_column' => 'menu_order,post_title',
            'number'      => apply_filters( 'ai_mcp_max_posts', 500 ),
        ) );

        $result         = array();
        $processed_urls = array();

        foreach ( $pages as $page ) {
            $url             = get_permalink( $page->ID );
            $processed_urls[] = trailingslashit( $url );
            $result[]         = $this->get_page_as_array( $page );
        }

        // Discover non-WP pages linked from menus
        foreach ( $this->discover_menu_links() as $link ) {
            $url = trailingslashit( $link['url'] );
            if ( in_array( $url, $processed_urls, true ) ) continue;

            $rendered = $this->fetch_rendered( $link['url'] );
            if ( empty( $rendered ) ) continue;

            $result[]         = array(
                'id'           => null,
                'title'        => $this->clean( $link['title'] ),
                'slug'         => basename( parse_url( $link['url'], PHP_URL_PATH ) ),
                'url'          => $link['url'],
                'excerpt'      => wp_trim_words( $this->clean( $rendered ), 40 ),
                'content'      => $this->clean( $rendered ),
                'last_updated' => current_time( 'mysql' ),
                'parent'       => null,
                'type'         => 'external_link',
            );
            $processed_urls[] = $url;
        }

        return $result;
    }

    /**
     * Build a single page array - used by both get_pages() and selective regen.
     *
     * @param WP_Post $page
     * @return array
     */
    public function get_page_as_array( $page ) {
        $url      = get_permalink( $page->ID );
        $content  = apply_filters( 'the_content', $page->post_content );

        $entry = array(
            'id'           => $page->ID,
            'title'        => $this->clean( $page->post_title ),
            'slug'         => $page->post_name,
            'url'          => $url,
            'excerpt'      => $this->excerpt_for( $page ),
            'content'      => $this->clean( $content ),
            'last_updated' => $page->post_modified,
            'parent'       => $page->post_parent ? get_the_title( $page->post_parent ) : null,
            'type'         => 'page',
            'word_count'   => str_word_count( $this->clean( $content ) ),
        );

        // ACF / custom fields
        $fields = $this->acf->get_fields_for_post( $page->ID );
        if ( ! empty( $fields ) ) $entry['fields'] = $fields;

        // FAQs embedded in this page
        $faqs = $this->faq->extract_from_post( $page );
        if ( ! empty( $faqs ) ) $entry['faqs'] = $faqs;

        // Featured image
        $thumb = get_the_post_thumbnail_url( $page->ID, 'large' );
        if ( $thumb ) $entry['featured_image'] = $thumb;

        // Chunk info (does NOT embed chunks - just tells AI where to get them)
        $entry['chunks_url'] = rest_url( 'ai-mcp/v1/chunks/' . $page->ID );
        if ( $entry['word_count'] > AI_MCP_Chunker::CHUNK_WORDS ) {
            $entry['chunks_available'] = (int) ceil( $entry['word_count'] / AI_MCP_Chunker::CHUNK_WORDS );
        }

        return $entry;
    }

    public function get_page_by_slug( $slug ) {
        $page = get_page_by_path( $slug );
        return $page ? $this->get_page_as_array( $page ) : null;
    }

    // =========================================================================
    // Posts
    // =========================================================================

    public function get_posts_content() {
        $posts  = get_posts( array(
            'post_status'    => 'publish',
            'posts_per_page' => apply_filters( 'ai_mcp_max_posts', 500 ),
            'orderby'        => 'date',
            'order'          => 'DESC',
        ) );

        $result = array();
        foreach ( $posts as $post ) {
            $result[] = $this->get_post_as_array( $post );
        }
        return $result;
    }

    /**
     * Build a single post array - used by get_posts_content() and selective regen.
     *
     * @param WP_Post $post
     * @return array
     */
    public function get_post_as_array( $post ) {
        $content = $this->clean( apply_filters( 'the_content', $post->post_content ) );

        $entry = array(
            'id'             => $post->ID,
            'title'          => $this->clean( $post->post_title ),
            'slug'           => $post->post_name,
            'url'            => get_permalink( $post->ID ),
            'excerpt'        => $this->excerpt_for( $post ),
            'content'        => $content,
            'date'           => $post->post_date,
            'last_updated'   => $post->post_modified,
            'categories'     => wp_get_post_categories( $post->ID, array( 'fields' => 'names' ) ),
            'tags'           => wp_get_post_tags( $post->ID, array( 'fields' => 'names' ) ),
            'author'         => get_the_author_meta( 'display_name', $post->post_author ),
            'featured_image' => get_the_post_thumbnail_url( $post->ID, 'large' ),
            'word_count'     => str_word_count( $content ),
        );

        $fields = $this->acf->get_fields_for_post( $post->ID );
        if ( ! empty( $fields ) ) $entry['fields'] = $fields;

        $faqs = $this->faq->extract_from_post( $post );
        if ( ! empty( $faqs ) ) $entry['faqs'] = $faqs;

        if ( $entry['word_count'] > AI_MCP_Chunker::CHUNK_WORDS ) {
            $entry['chunks_available'] = (int) ceil( $entry['word_count'] / AI_MCP_Chunker::CHUNK_WORDS );
            $entry['chunks_url']       = rest_url( 'ai-mcp/v1/chunks/' . $post->ID );
        }

        return $entry;
    }

    // =========================================================================
    // New modules - exposed for cache & endpoints
    // =========================================================================

    public function get_faqs() {
        return $this->faq->get_all_site_faqs();
    }

    public function get_authors() {
        return $this->authors->get_all();
    }

    public function get_media() {
        return $this->media->get_library();
    }

    public function get_content_index() {
        return $this->chunker->get_content_index();
    }

    public function get_chunks_for_post( $post_id ) {
        return $this->chunker->chunk_post( $post_id );
    }

    // =========================================================================
    // Custom Post Types
    // =========================================================================

    public function get_custom_post_types() {
        $cpts   = get_post_types( array( 'public' => true, '_builtin' => false ), 'objects' );
        $ignored = array( 'elementor_library', 'e-landing-page', 'wp_navigation', 'wp_block', 'sureforms_form' );
        $result  = array();

        foreach ( $cpts as $slug => $obj ) {
            if ( in_array( $slug, $ignored, true ) ) continue;

            $items = get_posts( array(
                'post_type'      => $slug,
                'post_status'    => 'publish',
                'posts_per_page' => apply_filters( 'ai_mcp_max_posts', 500 ),
                'orderby'        => 'date',
                'order'          => 'DESC',
            ) );

            $cpt_items = array();
            foreach ( $items as $item ) {
                $content     = $this->clean( apply_filters( 'the_content', $item->post_content ) );
                $cpt_entry   = array(
                    'id'             => $item->ID,
                    'title'          => $this->clean( $item->post_title ),
                    'slug'           => $item->post_name,
                    'url'            => get_permalink( $item->ID ),
                    'excerpt'        => $this->excerpt_for( $item ),
                    'content'        => $content,
                    'date'           => $item->post_date,
                    'last_updated'   => $item->post_modified,
                    'featured_image' => get_the_post_thumbnail_url( $item->ID, 'large' ),
                    'word_count'     => str_word_count( $content ),
                );
                $fields = $this->acf->get_fields_for_post( $item->ID );
                if ( ! empty( $fields ) ) $cpt_entry['fields'] = $fields;
                $cpt_items[] = $cpt_entry;
            }

            if ( ! empty( $cpt_items ) ) {
                $result[ $slug ] = array(
                    'label' => $obj->label,
                    'count' => count( $cpt_items ),
                    'items' => $cpt_items,
                );
            }
        }
        return $result;
    }

    // =========================================================================
    // Menus & Social
    // =========================================================================

    public function get_menus() {
        $menus  = wp_get_nav_menus();
        $result = array();
        foreach ( $menus as $menu ) {
            $items      = wp_get_nav_menu_items( $menu->term_id );
            $menu_items = array();
            if ( $items ) {
                foreach ( $items as $item ) {
                    $menu_items[] = array(
                        'title'  => $this->clean( $item->title ),
                        'url'    => $item->url,
                        'parent' => (int) $item->menu_item_parent,
                    );
                }
            }
            $result[ $menu->slug ] = array(
                'name'  => $menu->name,
                'items' => $menu_items,
            );
        }
        return $result;
    }

    public function get_social_links() {
        $social = array();

        $yoast = get_option( 'wpseo_social' );
        if ( $yoast ) {
            $map = array(
                'facebook_site' => 'facebook', 'twitter_site' => 'twitter',
                'instagram_url' => 'instagram', 'linkedin_url' => 'linkedin',
                'youtube_url'   => 'youtube',   'pinterest_url' => 'pinterest',
            );
            foreach ( $map as $k => $v ) {
                if ( ! empty( $yoast[$k] ) ) $social[$v] = $yoast[$k];
            }
        }

        $rm_map = array(
            'social_url_facebook' => 'facebook', 'twitter_author_names' => 'twitter',
            'social_url_linkedin' => 'linkedin', 'social_url_instagram' => 'instagram',
            'social_url_youtube'  => 'youtube',  'social_url_pinterest' => 'pinterest',
        );
        foreach ( $rm_map as $k => $v ) {
            $val = get_option( $k );
            if ( $val && ! isset( $social[$v] ) ) $social[$v] = $val;
        }

        foreach ( array( 'social_facebook', 'social_twitter', 'social_instagram',
                         'social_linkedin', 'social_youtube', 'social_tiktok' ) as $key ) {
            $val      = get_theme_mod( $key );
            $short    = str_replace( 'social_', '', $key );
            if ( $val && ! isset( $social[$short] ) ) $social[$short] = $val;
        }

        return apply_filters( 'AI_MCP_social_links', $social );
    }

    // =========================================================================
    // /all - compact index (AI-friendly, context-safe)
    // =========================================================================

    public function get_all() {
        $pages_index  = array_map( function( $p ) {
            return array(
                'id'          => $p['id'],
                'title'       => $p['title'],
                'slug'        => $p['slug'],
                'url'         => $p['url'],
                'excerpt'     => $p['excerpt'],
                'word_count'  => $p['word_count'] ?? 0,
                'chunks_url'  => $p['chunks_url'] ?? null,
                'last_updated'=> $p['last_updated'],
            );
        }, $this->get_pages() );

        $posts_index  = array_map( function( $p ) {
            return array(
                'id'         => $p['id'],
                'title'      => $p['title'],
                'slug'       => $p['slug'],
                'url'        => $p['url'],
                'excerpt'    => $p['excerpt'],
                'date'       => $p['date'],
                'author'     => $p['author'],
                'categories' => $p['categories'],
                'word_count' => $p['word_count'] ?? 0,
                'chunks_url' => $p['chunks_url'] ?? null,
            );
        }, $this->get_posts_content() );

        return array(
            '_meta' => array(
                'site'        => $this->clean( get_bloginfo( 'name' ) ),
                'url'         => home_url(),
                'crawled_at'  => gmdate( 'c' ),
                'description' => 'AI-ready structured export. Use chunks_url to fetch full content of long pages without exceeding context.',
                'version'     => AI_MCP_VERSION,
                'endpoints'   => array(
                    'all'     => rest_url( 'ai-mcp/v1/all' ),
                    'profile' => rest_url( 'ai-mcp/v1/profile' ),
                    'pages'   => rest_url( 'ai-mcp/v1/pages' ),
                    'posts'   => rest_url( 'ai-mcp/v1/posts' ),
                    'chunks'  => rest_url( 'ai-mcp/v1/chunks' ),
                    'faqs'    => rest_url( 'ai-mcp/v1/faqs' ),
                    'authors' => rest_url( 'ai-mcp/v1/authors' ),
                    'media'   => rest_url( 'ai-mcp/v1/media' ),
                    'social'  => rest_url( 'ai-mcp/v1/social' ),
                    'menus'   => rest_url( 'ai-mcp/v1/menus' ),
                ),
                'mcp_server'  => array(
                    'manifest' => home_url( '/.well-known/mcp.json' ),
                    'llms_txt' => home_url( '/llms.txt' ),
                ),
                'context_tip' => 'This /all response contains summaries only. For full page content use pages[] chunks_url. For chunked content of a specific post use /chunks/{id}.',
            ),
            'profile'           => $this->get_profile(),
            'pages'             => $pages_index,
            'posts'             => $posts_index,
            'faqs'              => $this->get_faqs(),
            'authors'           => $this->get_authors(),
            'media_summary'     => $this->media->get_summary(),
            'content_index'     => $this->get_content_index(),
            'menus'             => $this->get_menus(),
        );
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    private function clean( $content ) {
        if ( empty( $content ) ) return '';
        $clean = wp_strip_all_tags( $content );
        $clean = preg_replace( '/\s+/', ' ', $clean );
        $clean = html_entity_decode( $clean, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        return trim( $clean );
    }

    private function excerpt_for( $post ) {
        if ( ! empty( $post->post_excerpt ) ) {
            return $this->clean( $post->post_excerpt );
        }
        return wp_trim_words( $this->clean( $post->post_content ), 40 );
    }

    private function fetch_rendered( $url, $extract_main = true ) {
        $response = wp_remote_get( $url, array( 'timeout' => 10 ) );

        if ( is_wp_error( $response ) ) {
            do_action( 'ai_mcp_fetch_error', $url, $response->get_error_message() );
            return '';
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            do_action( 'ai_mcp_fetch_error', $url, "HTTP {$code}" );
            return '';
        }

        $html = wp_remote_retrieve_body( $response );
        if ( empty( $html ) ) return '';

        if ( ! class_exists( 'DOMDocument' ) ) {
            return wp_strip_all_tags( $html );
        }

        libxml_use_internal_errors( true );
        $dom = new DOMDocument();
        $dom->loadHTML( mb_convert_encoding( $html, 'HTML-ENTITIES', 'UTF-8' ), LIBXML_NOERROR | LIBXML_NOWARNING );
        libxml_clear_errors();

        if ( $extract_main ) {
            $main = $dom->getElementsByTagName( 'main' );
            if ( $main->length > 0 ) {
                return $dom->saveHTML( $main->item( 0 ) );
            }
        }
        
        $body = $dom->getElementsByTagName( 'body' );
        if ( $body->length > 0 ) {
            $body_node = $body->item( 0 );
            $tags_to_remove = array( 'header', 'footer', 'nav', 'aside', 'script', 'style' );
            foreach ( $tags_to_remove as $tag ) {
                $elements = $body_node->getElementsByTagName( $tag );
                for ( $i = $elements->length - 1; $i >= 0; $i -- ) {
                    $el = $elements->item( $i );
                    if ( $el && $el->parentNode ) {
                        $el->parentNode->removeChild( $el );
                    }
                }
            }
            return $dom->saveHTML( $body_node );
        }

        return $html;
    }

    private function discover_menu_links() {
        $links    = array();
        $home_url = home_url();

        foreach ( wp_get_nav_menus() as $menu ) {
            $items = wp_get_nav_menu_items( $menu->term_id );
            if ( $items ) {
                foreach ( $items as $item ) {
                    if ( strpos( $item->url, $home_url ) === 0 ) {
                        $links[] = array( 'title' => $item->title, 'url' => $item->url );
                    }
                }
            }
        }

        $nav_posts = get_posts( array( 'post_type' => 'wp_navigation', 'post_status' => 'publish', 'numberposts' => -1 ) );
        foreach ( $nav_posts as $nav ) {
            $blocks = parse_blocks( $nav->post_content );
            $links  = array_merge( $links, $this->links_from_blocks( $blocks ) );
        }

        // Deduplicate
        $unique = array();
        $seen   = array();
        foreach ( $links as $link ) {
            $url = trailingslashit( $link['url'] );
            if ( in_array( $url, $seen, true ) ) continue;
            if ( strpos( $url, '/wp-admin' ) !== false )   continue;
            if ( strpos( $url, '/wp-content' ) !== false ) continue;
            if ( strpos( $url, '/wp-json' ) !== false )    continue;
            if ( strlen( parse_url( $url, PHP_URL_PATH ) ) <= 1 ) continue;
            $unique[] = $link;
            $seen[]   = $url;
        }

        return $unique;
    }

    private function links_from_blocks( $blocks ) {
        $links    = array();
        $home_url = home_url();
        foreach ( $blocks as $block ) {
            if ( ( $block['blockName'] ?? '' ) === 'core/navigation-link' ) {
                if ( ! empty( $block['attrs']['url'] ) && strpos( $block['attrs']['url'], $home_url ) === 0 ) {
                    $links[] = array(
                        'title' => $block['attrs']['label'] ?? '',
                        'url'   => $block['attrs']['url'],
                    );
                }
            }
            if ( ! empty( $block['innerBlocks'] ) ) {
                $links = array_merge( $links, $this->links_from_blocks( $block['innerBlocks'] ) );
            }
        }
        return $links;
    }
}
