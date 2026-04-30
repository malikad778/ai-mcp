<?php
/**
 * AI MCP - Semantic Chunker
 *
 * Splits long page/post content into overlapping, context-aware chunks so
 * AI agents never run out of context window on a single piece of content.
 *
 * Design goals
 * ------------
 * • Chunks break at sentence boundaries (not mid-word or mid-sentence).
 * • Each chunk carries enough metadata that an AI knows exactly where it came
 *   from and how to request the next/previous chunk.
 * • Short content (<= CHUNK_WORDS) is returned as a single chunk - no overhead.
 * • The /all endpoint stores a lightweight index; full chunks live at
 *   /wp-json/ai-mcp/v1/chunks/{post_id}
 *
 * @package AI_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AI_MCP_Chunker {

    /**
     * Target words per chunk.  ~800 words ≈ ~1 000 tokens - comfortable for
     * any current frontier model while leaving headroom for system prompts.
     */
    const CHUNK_WORDS = 800;

    /**
     * Overlap words carried over from the previous chunk so context is not
     * lost at chunk boundaries.
     */
    const OVERLAP_WORDS = 60;

    /**
     * Chunk a single string of plain text.
     *
     * @param string $text        Plain text (HTML already stripped).
     * @param int    $source_id   Post/page ID.
     * @param string $source_title Post/page title.
     * @param string $source_url  Permalink.
     * @return array  Array of chunk objects.
     */
    public function chunk_text( $text, $source_id = 0, $source_title = '', $source_url = '' ) {
        $text = trim( $text );
        if ( empty( $text ) ) {
            return array();
        }

        // Split into sentences first, then assemble into chunks
        $sentences   = $this->split_sentences( $text );
        $chunks_raw  = $this->assemble_chunks( $sentences );
        $total        = count( $chunks_raw );

        $result = array();
        foreach ( $chunks_raw as $idx => $chunk_text ) {
            $result[] = array(
                'chunk_index'   => $idx,
                'total_chunks'  => $total,
                'word_count'    => str_word_count( $chunk_text ),
                'content'       => $chunk_text,
                'source_id'     => $source_id,
                'source_title'  => $source_title,
                'source_url'    => $source_url,
                'chunks_url'    => $source_id
                    ? rest_url( 'ai-mcp/v1/chunks/' . $source_id )
                    : null,
            );
        }

        return $result;
    }

    /**
     * Chunk all published pages and posts.
     * Returns a flat array of chunks across all content.
     *
     * @param string $post_type  'page' | 'post' | 'any'
     * @return array
     */
    public function chunk_all( $post_type = 'any' ) {
        $args = array(
            'post_status'    => 'publish',
            'posts_per_page' => apply_filters( 'ai_mcp_max_posts', 500 ),
            'orderby'        => 'title',
            'order'          => 'ASC',
        );

        if ( 'any' !== $post_type ) {
            $args['post_type'] = $post_type;
        } else {
            $args['post_type'] = array( 'page', 'post' );
        }

        $posts  = get_posts( $args );
        $result = array();

        foreach ( $posts as $post ) {
            $plain = $this->get_plain_content( $post );
            $chunks  = $this->chunk_text(
                $plain,
                $post->ID,
                $post->post_title,
                get_permalink( $post->ID )
            );
            $result  = array_merge( $result, $chunks );
        }

        return $result;
    }

    /**
     * Chunk a single post by ID (used by the /chunks/{id} endpoint).
     *
     * @param int $post_id
     * @return array
     */
    public function chunk_post( $post_id ) {
        $post = get_post( (int) $post_id );
        if ( ! $post || 'publish' !== $post->post_status ) {
            return array();
        }

        $plain = $this->get_plain_content( $post );

        return $this->chunk_text(
            $plain,
            $post->ID,
            $post->post_title,
            get_permalink( $post->ID )
        );
    }

    /**
     * Return a content index - one entry per post with chunk count and a link
     * to fetch the actual chunks.  Suitable for inclusion in /all without
     * blowing up the response size.
     *
     * @return array
     */
    public function get_content_index() {
        $posts = get_posts( array(
            'post_type'      => array( 'page', 'post' ),
            'post_status'    => 'publish',
            'posts_per_page' => apply_filters( 'ai_mcp_max_posts', 500 ),
        ) );

        $index = array();
        foreach ( $posts as $post ) {
            $plain      = $this->get_plain_content( $post );
            $word_count = str_word_count( $plain );
            $chunks     = (int) ceil( max( 1, $word_count ) / self::CHUNK_WORDS );

            $index[] = array(
                'id'          => $post->ID,
                'title'       => $post->post_title,
                'type'        => $post->post_type,
                'url'         => get_permalink( $post->ID ),
                'word_count'  => $word_count,
                'chunk_count' => $chunks,
                'chunks_url'  => rest_url( 'ai-mcp/v1/chunks/' . $post->ID ),
                'summary'     => wp_trim_words( $plain, 30 ),
            );
        }

        return $index;
    }

    /**
     * Get plain text content for a post.
     * If post_content is empty (custom PHP templates), fetches the rendered page via HTTP.
     *
     * @param WP_Post $post
     * @return string Plain text content.
     */
    private function get_plain_content( $post ) {
        $raw   = apply_filters( 'the_content', $post->post_content );
        $plain = wp_strip_all_tags( $raw );
        $plain = html_entity_decode( trim( preg_replace( '/\s+/', ' ', $plain ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );

        return $plain;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Split text into an array of sentences.
     * Uses a regex that handles common abbreviations and decimal numbers.
     *
     * @param string $text
     * @return string[]
     */
    private function split_sentences( $text ) {
        // Protect common abbreviations from splitting
        $protected = preg_replace(
            '/\b(Mr|Mrs|Ms|Dr|Prof|Sr|Jr|vs|etc|al|e\.g|i\.e|approx|dept|est|inc|ltd|no|vol|fig)\./',
            '$1<DOT>',
            $text
        );

        // Split on sentence-ending punctuation followed by whitespace + capital
        $parts = preg_split( '/(?<=[.!?])\s+(?=[A-Z"\'])/', $protected );

        // Restore protected dots
        $sentences = array();
        foreach ( $parts as $part ) {
            $sentences[] = str_replace( '<DOT>', '.', trim( $part ) );
        }

        return array_filter( $sentences );
    }

    /**
     * Assemble sentences into chunks, respecting CHUNK_WORDS and OVERLAP_WORDS.
     *
     * @param string[] $sentences
     * @return string[]  Each element is one chunk's full text.
     */
    private function assemble_chunks( array $sentences ) {
        $chunks        = array();
        $current_words = array();
        $overlap_buf   = array();    // words carried into next chunk
        $current_count = 0;

        foreach ( $sentences as $sentence ) {
            $s_words = preg_split( '/\s+/', trim( $sentence ) );
            $s_count = count( $s_words );

            // If adding this sentence exceeds the limit, finalise current chunk
            if ( $current_count > 0 && ( $current_count + $s_count ) > self::CHUNK_WORDS ) {
                $chunks[]      = implode( ' ', $current_words );

                // Build overlap: last OVERLAP_WORDS words of the finished chunk
                $all_words     = $current_words;
                $overlap_buf   = array_slice( $all_words, -self::OVERLAP_WORDS );
                $current_words = array_merge( $overlap_buf, $s_words );
                $current_count = count( $current_words );
            } else {
                $current_words = array_merge( $current_words, $s_words );
                $current_count += $s_count;
            }
        }

        // Flush remaining words
        if ( ! empty( $current_words ) ) {
            $chunks[] = implode( ' ', $current_words );
        }

        return $chunks;
    }
}
