<?php
/**
 * AI MCP - Robots.txt Integration
 *
 * Appends AI-friendly directives to the WordPress robots.txt output.
 * Equivalent to the Next.js robots.ts file.
 *
 * @package AI_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AI_MCP_Robots {

    /**
     * Constructor.
     */
    public function __construct() {
        add_filter( 'robots_txt', array( $this, 'modify_robots_txt' ), 100, 2 );
    }

    /**
     * Append MCP directives to robots.txt.
     *
     * @param string $output  Current robots.txt content.
     * @param bool   $public  Whether the site is public.
     * @return string
     */
    public function modify_robots_txt( $output, $public ) {
        if ( ! $public ) {
            return $output;
        }

        $mcp_rules  = "\n# AI MCP - AI Agent Access\n";
        $mcp_rules .= "Allow: /wp-json/ai-mcp/\n";
        $mcp_rules .= "Allow: /.well-known/mcp.json\n";
        $mcp_rules .= "\n# MCP Structured Data Sitemap\n";
        $mcp_rules .= "Sitemap: " . rest_url( 'ai-mcp/v1/all' ) . "\n";

        return $output . $mcp_rules;
    }
}
