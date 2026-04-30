<?php
/**
 * AI MCP - AI Discovery Tags
 *
 * Injects AI discovery <link> and <meta> tags into the <head> of every page.
 * This is the WordPress equivalent of the Next.js layout.tsx head tags.
 *
 * @package AI_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AI_MCP_Discovery {

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'wp_head', array( $this, 'inject_head_tags' ), 1 );
    }

    /**
     * Inject AI discovery tags into the <head>.
     */
    public function inject_head_tags() {
        $all_url      = rest_url( 'ai-mcp/v1/all' );
        $manifest_url = home_url( '/.well-known/mcp.json' );

        echo "\n<!-- AI MCP: AI Agent Discovery -->\n";
        echo '<link rel="alternate" type="application/json" href="' . esc_url( $all_url ) . '" title="Full Site Content (AI-Ready JSON)" />' . "\n";
        echo '<link rel="mcp-manifest+json" href="' . esc_url( $manifest_url ) . '" />' . "\n";
        echo '<meta name="ai-agent-entry" content="' . esc_url( $all_url ) . '" />' . "\n";
        echo "<!-- /AI MCP -->\n\n";
    }
}
