<?php
/**
 * AI MCP - llms.txt Generator
 *
 * Implements the llms.txt specification (https://llmstxt.org/).
 * Serves a machine-readable plain-text file at /llms.txt that tells AI
 * crawlers what the site is about and where to find its content.
 *
 * Also serves /llms-full.txt - an extended version with full page summaries.
 *
 * @package AI_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AI_MCP_LLMS {

    /**
     * Register rewrite rules and serve hooks.
     */
    public function __construct() {
        add_action( 'init',          array( $this, 'add_rewrite_rules' ) );
        add_action( 'template_redirect', array( $this, 'maybe_serve' ), 1 );
    }

    /**
     * Register /llms.txt and /llms-full.txt rewrite rules.
     */
    public function add_rewrite_rules() {
        add_rewrite_rule( '^llms\.txt$',      'index.php?ai_mcp_llms=1',      'top' );
        add_rewrite_rule( '^llms-full\.txt$', 'index.php?ai_mcp_llms_full=1', 'top' );
        add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
    }

    /**
     * Register custom query vars.
     */
    public function add_query_vars( $vars ) {
        $vars[] = 'ai_mcp_llms';
        $vars[] = 'ai_mcp_llms_full';
        return $vars;
    }

    /**
     * Intercept the request and serve llms.txt if the query var is set.
     */
    public function maybe_serve() {
        if ( get_query_var( 'ai_mcp_llms' ) ) {
            $this->serve_llms_txt( false );
        }
        if ( get_query_var( 'ai_mcp_llms_full' ) ) {
            $this->serve_llms_txt( true );
        }
    }

    /**
     * Output the llms.txt (or llms-full.txt) file.
     *
     * @param bool $full  Include full page summaries.
     */
    public function serve_llms_txt( $full = false ) {
        header( 'Content-Type: text/plain; charset=utf-8' );
        header( 'Cache-Control: public, max-age=3600' );
        // phpcs:ignore WordPress.Security.EscapeOutput
        echo $this->generate( $full );
        exit;
    }

    /**
     * Generate the llms.txt content string.
     *
     * @param bool $full
     * @return string
     */
    public function generate( $full = false ) {
        $site_name = get_bloginfo( 'name' );
        $tagline   = get_bloginfo( 'description' );

        $out  = "# {$site_name}\n\n";

        if ( $tagline ) {
            $out .= "> {$tagline}\n\n";
        }

        // About / profile blurb from the About page
        $about_slug = apply_filters( 'ai_mcp_profile_page_slug', 'about' );
        $about = get_page_by_path( $about_slug );
        if ( $about ) {
            $bio = wp_trim_words(
                wp_strip_all_tags( apply_filters( 'the_content', $about->post_content ) ),
                80
            );
            if ( $bio ) {
                $out .= $bio . "\n\n";
            }
        }

        // --- Pages section ---
        $pages = get_pages( array(
            'post_status' => 'publish',
            'sort_column' => 'menu_order,post_title',
        ) );

        if ( $pages ) {
            $out .= "## Pages\n\n";
            foreach ( $pages as $page ) {
                $url     = get_permalink( $page->ID );
                $excerpt = '';
                if ( $full ) {
                    $excerpt = ': ' . wp_trim_words(
                        wp_strip_all_tags( apply_filters( 'the_content', $page->post_content ) ),
                        40
                    );
                } elseif ( $page->post_excerpt ) {
                    $excerpt = ': ' . wp_strip_all_tags( $page->post_excerpt );
                }
                $out .= "- [{$page->post_title}]({$url}){$excerpt}\n";
            }
            $out .= "\n";
        }

        // --- Posts section ---
        $posts = get_posts( array(
            'post_status'    => 'publish',
            'posts_per_page' => $full ? 50 : 20,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ) );

        if ( $posts ) {
            $out .= "## Posts\n\n";
            foreach ( $posts as $post ) {
                $url     = get_permalink( $post->ID );
                $date    = wp_date( 'Y-m-d', strtotime( $post->post_date ) );
                $excerpt = '';
                if ( $full && $post->post_excerpt ) {
                    $excerpt = ': ' . wp_strip_all_tags( $post->post_excerpt );
                }
                $out .= "- [{$post->post_title}]({$url}) ({$date}){$excerpt}\n";
            }
            $out .= "\n";
        }

        // --- AI API endpoints ---
        $out .= "## AI-Readable Data\n\n";
        $out .= "- [Full JSON Export]("    . rest_url( 'ai-mcp/v1/all' )     . "): Complete structured site export (profile, pages, posts, FAQs, media, authors)\n";
        $out .= "- [Content Index]("       . rest_url( 'ai-mcp/v1/chunks' )  . "): Chunked content index - ideal for large-context retrieval\n";
        $out .= "- [Pages]("               . rest_url( 'ai-mcp/v1/pages' )   . ")\n";
        $out .= "- [Posts]("               . rest_url( 'ai-mcp/v1/posts' )   . ")\n";
        $out .= "- [FAQs]("                . rest_url( 'ai-mcp/v1/faqs' )    . ")\n";
        $out .= "- [Authors]("             . rest_url( 'ai-mcp/v1/authors' ) . ")\n";
        $out .= "- [Media]("               . rest_url( 'ai-mcp/v1/media' )   . ")\n";
        $out .= "- [MCP Manifest]("        . home_url( '/.well-known/mcp.json' ) . ")\n";
        $out .= "\n";

        // --- Variant links ---
        $out .= "## Related\n\n";
        $out .= "- [llms.txt]("      . home_url( '/llms.txt' )      . "): This file (compact)\n";
        $out .= "- [llms-full.txt](" . home_url( '/llms-full.txt' ) . "): Extended version with summaries\n";

        return $out;
    }

    /**
     * Add llms.txt and llms-full.txt links to robots.txt.
     * Called from class-mcp-robots.php via a filter.
     *
     * @param string $output
     * @return string
     */
    public function append_to_robots( $output ) {
        $output .= "\n# AI Content Files\n";
        $output .= 'Allow: /llms.txt' . "\n";
        $output .= 'Allow: /llms-full.txt' . "\n";
        return $output;
    }
}
