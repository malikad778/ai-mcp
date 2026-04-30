<?php
/**
 * AI MCP - Author Profiles
 *
 * Exposes author bios, roles, post counts, and social links so AI agents
 * can attribute content correctly and describe the people behind the site.
 *
 * @package AI_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AI_MCP_Authors {

    /**
     * Return all authors who have at least one published post.
     *
     * @return array
     */
    public function get_all() {
        $users = get_users( array(
            'role__in'            => array( 'administrator', 'editor', 'author', 'contributor' ),
            'has_published_posts' => array( 'post', 'page' ),
            'orderby'             => 'display_name',
            'order'               => 'ASC',
        ) );

        $result = array();
        foreach ( $users as $user ) {
            $result[] = $this->build_profile( $user );
        }
        return $result;
    }

    /**
     * Return a single author profile by user ID.
     *
     * @param int $user_id
     * @return array|null
     */
    public function get_by_id( $user_id ) {
        $user = get_user_by( 'id', (int) $user_id );
        return $user ? $this->build_profile( $user ) : null;
    }

    /**
     * Return a single author profile by slug (user_nicename).
     *
     * @param string $slug
     * @return array|null
     */
    public function get_by_slug( $slug ) {
        $user = get_user_by( 'slug', sanitize_title( $slug ) );
        return $user ? $this->build_profile( $user ) : null;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Build a full author profile array from a WP_User object.
     *
     * @param WP_User $user
     * @return array
     */
    private function build_profile( $user ) {
        $post_count = (int) count_user_posts( $user->ID, 'post', true );

        $profile = array(
            'id'          => $user->ID,
            'name'        => $user->display_name,
            'slug'        => $user->user_nicename,
            'url'         => get_author_posts_url( $user->ID ),
            'bio'         => get_user_meta( $user->ID, 'description', true ),
            'avatar'      => get_avatar_url( $user->ID, array( 'size' => 200 ) ),
            'role'        => implode( ', ', $user->roles ),
            'post_count'  => $post_count,
            'registered'  => $user->user_registered,
            'social'      => $this->get_social( $user->ID ),
            'recent_posts'=> $this->get_recent_posts( $user->ID ),
        );
        
        if ( get_option( 'ai_mcp_expose_author_emails', false ) ) {
            $profile['email'] = $user->user_email;
        }

        return $profile;
    }

    /**
     * Collect social/website links from user meta (multiple plugin conventions).
     */
    private function get_social( $user_id ) {
        $social = array();

        // WordPress built-in
        $website = get_user_meta( $user_id, 'user_url', true );
        if ( ! $website ) {
            $u = get_user_by( 'id', $user_id );
            $website = $u ? $u->user_url : '';
        }
        if ( $website ) $social['website'] = $website;

        // Common meta keys used by themes & plugins
        $meta_map = array(
            'twitter'         => 'twitter',
            'twitter_url'     => 'twitter',
            'facebook'        => 'facebook',
            'facebook_url'    => 'facebook',
            'linkedin'        => 'linkedin',
            'linkedin_url'    => 'linkedin',
            'instagram'       => 'instagram',
            'instagram_url'   => 'instagram',
            'youtube'         => 'youtube',
            'youtube_url'     => 'youtube',
            'tiktok'          => 'tiktok',
            'github'          => 'github',
        );

        foreach ( $meta_map as $meta_key => $platform ) {
            if ( isset( $social[ $platform ] ) ) continue; // already found
            $val = get_user_meta( $user_id, $meta_key, true );
            if ( $val ) {
                $social[ $platform ] = $val;
            }
        }

        return $social;
    }

    /**
     * Get the 5 most recent published posts by this author.
     */
    private function get_recent_posts( $user_id ) {
        $posts = get_posts( array(
            'author'         => $user_id,
            'post_status'    => 'publish',
            'posts_per_page' => 5,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ) );

        $result = array();
        foreach ( $posts as $post ) {
            $result[] = array(
                'id'    => $post->ID,
                'title' => $post->post_title,
                'url'   => get_permalink( $post->ID ),
                'date'  => $post->post_date,
            );
        }
        return $result;
    }
}
