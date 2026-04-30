<?php
/**
 * AI MCP - ACF & Custom Fields Reader
 *
 * Reads Advanced Custom Fields (ACF) data and generic public post meta,
 * normalising complex field types (relationships, images, repeaters) into
 * plain, AI-readable arrays.
 *
 * @package AI_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AI_MCP_ACF {

    /**
     * Get all ACF fields + public meta for a given post ID.
     *
     * @param int $post_id
     * @return array  Keys: 'acf' (if ACF active) and/or 'custom_fields'
     */
    public function get_fields_for_post( $post_id ) {
        $result = array();

        // --- ACF ---
        if ( function_exists( 'get_fields' ) ) {
            $fields = get_fields( $post_id );
            if ( ! empty( $fields ) ) {
                $result['acf'] = $this->normalise_acf( $fields );
            }
        }

        // --- Generic public meta (non-underscore keys, excluding ACF internals) ---
        $all_meta = get_post_meta( $post_id );
        $public   = array();
        foreach ( $all_meta as $key => $values ) {
            if ( strpos( $key, '_' ) === 0 ) {
                continue; // skip private / internal
            }
            // Skip keys already returned by ACF to avoid duplication
            if ( isset( $result['acf'][ $key ] ) ) {
                continue;
            }
            $public[ $key ] = ( count( $values ) === 1 ) ? $values[0] : $values;
        }
        if ( ! empty( $public ) ) {
            $result['custom_fields'] = $public;
        }

        return $result;
    }

    /**
     * Recursively normalise ACF field values into AI-friendly primitives.
     *
     * @param mixed $value
     * @return mixed
     */
    private function normalise_acf( $value ) {
        // Image array (ACF image field returns an array with 'url', 'alt', etc.)
        if ( is_array( $value ) && isset( $value['url'], $value['alt'] ) ) {
            return array(
                'url'     => $value['url'],
                'alt'     => $value['alt'],
                'caption' => $value['caption'] ?? '',
                'width'   => $value['width'] ?? null,
                'height'  => $value['height'] ?? null,
            );
        }

        if ( is_array( $value ) ) {
            $out = array();
            foreach ( $value as $k => $v ) {
                $out[ $k ] = $this->normalise_acf( $v );
            }
            return $out;
        }

        // WP_Post - return id/title/url tuple
        if ( $value instanceof WP_Post ) {
            return array(
                'id'    => $value->ID,
                'title' => get_the_title( $value->ID ),
                'url'   => get_permalink( $value->ID ),
            );
        }

        // WP_Term
        if ( $value instanceof WP_Term ) {
            return array(
                'id'   => $value->term_id,
                'name' => $value->name,
                'slug' => $value->slug,
            );
        }

        // WP_User
        if ( $value instanceof WP_User ) {
            return array(
                'id'   => $value->ID,
                'name' => $value->display_name,
            );
        }

        return $value;
    }

    /**
     * Get ACF field group labels for a given post (useful for AI context).
     *
     * @param int $post_id
     * @return array  e.g. ['Hero Section', 'SEO Settings']
     */
    public function get_field_group_labels( $post_id ) {
        if ( ! function_exists( 'acf_get_field_groups' ) ) {
            return array();
        }
        $post  = get_post( $post_id );
        $groups = acf_get_field_groups(
            array(
                'post_id'   => $post_id,
                'post_type' => $post ? $post->post_type : '',
            )
        );
        return array_column( $groups, 'title' );
    }
}
