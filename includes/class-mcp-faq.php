<?php
/**
 * AI MCP - FAQ & Schema Markup Extractor
 *
 * Finds FAQ content from:
 *   1. Yoast SEO FAQ blocks  (yoast/faq-block)
 *   2. RankMath FAQ blocks   (rank-math/faq-block)
 *   3. Core Details blocks   (core/details – native WP accordion)
 *   4. JSON-LD <script> tags embedded in page HTML
 *   5. Pages/posts whose title/slug contains "faq"
 *
 * @package AI_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AI_MCP_FAQ {

    /**
     * Extract FAQs from a single post/page.
     *
     * @param WP_Post $post
     * @return array  Array of {question, answer} pairs.
     */
    public function extract_from_post( $post ) {
        $faqs = array();

        // 1. Gutenberg blocks
        if ( function_exists( 'parse_blocks' ) && ! empty( $post->post_content ) ) {
            $blocks = parse_blocks( $post->post_content );
            $faqs   = array_merge( $faqs, $this->scan_blocks( $blocks ) );
        }

        // 2. JSON-LD embedded in rendered HTML (catches Elementor / Classic Editor output)
        $rendered = apply_filters( 'the_content', $post->post_content );
        if ( $rendered ) {
            $faqs = array_merge( $faqs, $this->extract_jsonld_faqs( $rendered ) );
        }

        // Deduplicate by question
        $seen  = array();
        $clean = array();
        foreach ( $faqs as $faq ) {
            $key = md5( strtolower( $faq['question'] ) );
            if ( isset( $seen[ $key ] ) ) {
                continue;
            }
            $seen[ $key ] = true;
            $clean[]      = $faq;
        }

        return $clean;
    }

    /**
     * Get all FAQs across the entire site, grouped by source page.
     *
     * @return array
     */
    public function get_all_site_faqs() {
        $posts = get_posts( array(
            'post_type'      => array( 'post', 'page' ),
            'post_status'    => 'publish',
            'posts_per_page' => apply_filters( 'ai_mcp_max_posts', 500 ),
        ) );

        $all = array();
        foreach ( $posts as $post ) {
            $faqs = $this->extract_from_post( $post );
            if ( ! empty( $faqs ) ) {
                $all[] = array(
                    'source_id'    => $post->ID,
                    'source_title' => $post->post_title,
                    'source_url'   => get_permalink( $post->ID ),
                    'faqs'         => $faqs,
                );
            }
        }

        return $all;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Recursively scan Gutenberg blocks for FAQ data.
     */
    private function scan_blocks( array $blocks ) {
        $faqs = array();

        foreach ( $blocks as $block ) {
            $name = $block['blockName'] ?? '';

            // Yoast FAQ block
            if ( 'yoast/faq-block' === $name && ! empty( $block['attrs']['questions'] ) ) {
                foreach ( $block['attrs']['questions'] as $q ) {
                    $question = wp_strip_all_tags( $q['jsonQuestion'] ?? ( $q['question'] ?? '' ) );
                    $answer   = wp_strip_all_tags( $q['jsonAnswer']   ?? ( $q['answer']   ?? '' ) );
                    if ( $question ) {
                        $faqs[] = array( 'question' => $question, 'answer' => $answer );
                    }
                }
            }

            // RankMath FAQ block
            if ( 'rank-math/faq-block' === $name && ! empty( $block['attrs']['list'] ) ) {
                foreach ( $block['attrs']['list'] as $item ) {
                    $question = wp_strip_all_tags( $item['title'] ?? '' );
                    $answer   = wp_strip_all_tags( $item['content'] ?? '' );
                    if ( $question ) {
                        $faqs[] = array( 'question' => $question, 'answer' => $answer );
                    }
                }
            }

            // Core Details / Summary (native WP accordion, WP 6.1+)
            if ( 'core/details' === $name && ! empty( $block['innerBlocks'] ) ) {
                $summary = '';
                $body    = '';
                foreach ( $block['innerBlocks'] as $inner ) {
                    if ( 'core/summary' === ( $inner['blockName'] ?? '' ) ) {
                        $summary = wp_strip_all_tags( render_block( $inner ) );
                    } else {
                        $body .= wp_strip_all_tags( render_block( $inner ) ) . ' ';
                    }
                }
                // Fallback: parse summary from innerHTML
                if ( ! $summary && ! empty( $block['innerHTML'] ) ) {
                    preg_match( '/<summary[^>]*>(.*?)<\/summary>/is', $block['innerHTML'], $m );
                    $summary = wp_strip_all_tags( $m[1] ?? '' );
                }
                if ( $summary ) {
                    $faqs[] = array( 'question' => trim( $summary ), 'answer' => trim( $body ) );
                }
            }

            // Recurse into inner blocks
            if ( ! empty( $block['innerBlocks'] ) ) {
                $faqs = array_merge( $faqs, $this->scan_blocks( $block['innerBlocks'] ) );
            }
        }

        return $faqs;
    }

    /**
     * Extract FAQPage JSON-LD schema from raw HTML.
     */
    private function extract_jsonld_faqs( $html ) {
        $faqs = array();

        preg_match_all(
            '/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is',
            $html,
            $matches
        );

        foreach ( $matches[1] as $raw_json ) {
            $data = json_decode( trim( $raw_json ), true );
            if ( ! $data ) {
                continue;
            }

            // Handle both single object and @graph array
            $schemas = isset( $data['@graph'] ) ? $data['@graph'] : array( $data );

            foreach ( $schemas as $schema ) {
                if ( ( $schema['@type'] ?? '' ) !== 'FAQPage' ) {
                    continue;
                }
                foreach ( $schema['mainEntity'] ?? array() as $entity ) {
                    $question = wp_strip_all_tags( $entity['name'] ?? '' );
                    $answer   = wp_strip_all_tags(
                        $entity['acceptedAnswer']['text'] ?? ''
                    );
                    if ( $question ) {
                        $faqs[] = array( 'question' => $question, 'answer' => $answer );
                    }
                }
            }
        }

        return $faqs;
    }

}
