<?php
/**
 * AI MCP - Media Library Indexer
 *
 * Indexes uploaded images with alt text, captions, descriptions, dimensions,
 * and the page/post they are attached to.  Gives AI a full picture of the
 * site's visual assets without needing to crawl individual pages.
 *
 * @package AI_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AI_MCP_Media {

    /** Maximum images returned by get_library(). Keeps response size sane. */
    const MAX_IMAGES = 300;

    /**
     * Return an indexed list of all images in the media library.
     *
     * @return array
     */
    public function get_library() {
        $attachments = get_posts( array(
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'post_mime_type' => 'image',
            'posts_per_page' => self::MAX_IMAGES,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ) );

        $result = array();
        foreach ( $attachments as $att ) {
            $result[] = $this->format_attachment( $att );
        }
        return $result;
    }

    /**
     * Return images attached to a specific post ID.
     *
     * @param int $post_id
     * @return array
     */
    public function get_for_post( $post_id ) {
        $attachments = get_attached_media( 'image', $post_id );
        $result      = array();
        foreach ( $attachments as $att ) {
            $result[] = $this->format_attachment( $att );
        }

        // Also grab the featured image if not already included
        $thumb_id = get_post_thumbnail_id( $post_id );
        if ( $thumb_id ) {
            $ids = array_column( $result, 'id' );
            if ( ! in_array( (int) $thumb_id, $ids, true ) ) {
                $att = get_post( $thumb_id );
                if ( $att ) {
                    $item              = $this->format_attachment( $att );
                    $item['featured']  = true;
                    array_unshift( $result, $item );
                }
            }
        }

        return $result;
    }

    /**
     * Return media stats summary for the /all endpoint.
     *
     * @return array
     */
    public function get_summary() {
        global $wpdb;
        $counts = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            "SELECT post_mime_type, COUNT(*) as total
             FROM {$wpdb->posts}
             WHERE post_type = 'attachment' AND post_status = 'inherit'
             GROUP BY post_mime_type",
            OBJECT_K
        );

        $images = 0;
        $docs   = 0;
        $videos = 0;
        $audio  = 0;

        foreach ( $counts as $mime => $row ) {
            if ( strpos( $mime, 'image/' ) === 0 )  $images += (int) $row->total;
            if ( strpos( $mime, 'video/' ) === 0 )  $videos += (int) $row->total;
            if ( strpos( $mime, 'audio/' ) === 0 )  $audio  += (int) $row->total;
            if ( in_array( $mime, array( 'application/pdf', 'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document' ), true ) ) {
                $docs++;
            }
        }

        return array(
            'total_images' => $images,
            'total_videos' => $videos,
            'total_audio'  => $audio,
            'total_docs'   => $docs,
            'index_url'    => rest_url( 'ai-mcp/v1/media' ),
        );
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Format a single attachment post into an AI-friendly array.
     *
     * @param WP_Post $att
     * @return array
     */
    private function format_attachment( $att ) {
        $meta = wp_get_attachment_metadata( $att->ID );

        $item = array(
            'id'          => $att->ID,
            'url'         => wp_get_attachment_url( $att->ID ),
            'title'       => $att->post_title,
            'alt'         => get_post_meta( $att->ID, '_wp_attachment_image_alt', true ),
            'caption'     => wp_strip_all_tags( $att->post_excerpt ),
            'description' => wp_strip_all_tags( $att->post_content ),
            'filename'    => basename( get_attached_file( $att->ID ) ),
            'mime_type'   => get_post_mime_type( $att->ID ),
            'width'       => $meta['width']  ?? null,
            'height'      => $meta['height'] ?? null,
            'file_size'   => isset( $meta['filesize'] ) ? $this->format_size( $meta['filesize'] ) : null,
            'uploaded'    => $att->post_date,
            'featured'    => false,
        );

        // Parent page/post context
        if ( $att->post_parent ) {
            $parent = get_post( $att->post_parent );
            if ( $parent ) {
                $item['attached_to'] = array(
                    'id'    => $parent->ID,
                    'title' => $parent->post_title,
                    'url'   => get_permalink( $parent->ID ),
                    'type'  => $parent->post_type,
                );
            }
        }

        // Available sizes
        if ( ! empty( $meta['sizes'] ) ) {
            $sizes = array();
            foreach ( $meta['sizes'] as $size_name => $size_data ) {
                $sizes[ $size_name ] = array(
                    'width'  => $size_data['width'],
                    'height' => $size_data['height'],
                    'url'    => wp_get_attachment_image_url( $att->ID, $size_name ),
                );
            }
            $item['sizes'] = $sizes;
        }

        return $item;
    }

    /**
     * Format bytes into human-readable string.
     */
    private function format_size( $bytes ) {
        if ( $bytes >= 1048576 ) return round( $bytes / 1048576, 2 ) . ' MB';
        if ( $bytes >= 1024 )    return round( $bytes / 1024, 2 )    . ' KB';
        return $bytes . ' B';
    }
}
