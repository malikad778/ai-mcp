<?php
/**
 * AI MCP - Admin Dashboard (v2)
 *
 * Tabbed admin UI:
 *   1. Status & Endpoints
 *   2. Analytics (endpoint hits, AI referrers, 7-day trend)
 *   3. Error Log
 *
 * @package AI_MCP
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class AI_MCP_Admin {

    /** @var AI_MCP_Content_Reader */
    private $reader;
    /** @var AI_MCP_Analytics */
    private $analytics;
    /** @var AI_MCP_Cache */
    private $cache;

    public function __construct( AI_MCP_Content_Reader $reader, AI_MCP_Analytics $analytics, AI_MCP_Cache $cache ) {
        $this->reader    = $reader;
        $this->analytics = $analytics;
        $this->cache     = $cache;
        add_action( 'admin_menu',            array( $this, 'add_menu_page' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_ajax_AI_MCP_clear_analytics', array( $this, 'ajax_clear_analytics' ) );
    }

    public function add_menu_page() {
        add_options_page( 'AI MCP', 'AI MCP', 'manage_options', 'ai-mcp', array( $this, 'render' ) );
    }

    public function enqueue_assets( $hook ) {
        if ( 'settings_page_ai-mcp' !== $hook ) return;
        wp_enqueue_style(  'ai-mcp-admin', AI_MCP_PLUGIN_URL . 'admin/mcp-admin.css', array(), AI_MCP_VERSION );
        wp_enqueue_script( 'ai-mcp-admin', AI_MCP_PLUGIN_URL . 'admin/mcp-admin.js',  array( 'jquery' ), AI_MCP_VERSION, true );
        wp_localize_script( 'ai-mcp-admin', 'aiMCP', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'AI_MCP_regen_nonce' ),
        ) );
    }

    public function ajax_clear_analytics() {
        check_ajax_referer( 'AI_MCP_regen_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
        $this->analytics->clear();
        wp_send_json_success( array( 'message' => 'Analytics cleared.' ) );
    }

    public function render() {
        $tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'status'; // phpcs:ignore WordPress.Security.NonceVerification
        ?>
        <div class="wrap ai-mcp-admin">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
                <h1 style="margin: 0;">⚡ AI MCP - AI Content Server</h1>
                <a href="<?php echo esc_url( home_url( '/ai-mcp-docs/' ) ); ?>" target="_blank" class="button">📖 View Documentation</a>
            </div>
            <nav class="nav-tab-wrapper">
                <?php foreach ( array( 'status' => '📊 Status', 'analytics' => '📈 Analytics', 'errors' => '🚨 Error Log', 'settings' => '⚙️ Settings' ) as $slug => $label ) : ?>
                <a href="?page=ai-mcp&tab=<?php echo esc_attr( $slug ); ?>"
                   class="nav-tab <?php echo $tab === $slug ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html( $label ); ?>
                </a>
                <?php endforeach; ?>
            </nav>

            <?php
            if ( 'analytics' === $tab )      $this->render_analytics();
            elseif ( 'errors' === $tab )     $this->render_errors();
            elseif ( 'settings' === $tab )   $this->render_settings();
            else                             $this->render_status();
            ?>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Tab: Status
    // -------------------------------------------------------------------------

    private function render_status() {
        $last   = get_option( 'AI_MCP_last_generated', 'Never' );
        $social = $this->reader->get_social_links();
        $menus  = $this->reader->get_menus();

        // Counts from cache files (fast)
        $pages_json   = ai_mcp_get_json_dir() . '/pages.json';
        $posts_json   = ai_mcp_get_json_dir() . '/posts.json';
        $pages_count  = file_exists( $pages_json )  ? count( json_decode( file_get_contents( $pages_json ), true ) ?? [] )  : '-';
        $posts_count  = file_exists( $posts_json )  ? count( json_decode( file_get_contents( $posts_json ), true ) ?? [] )  : '-';

        $endpoints = array(
            '/all'         => rest_url( 'ai-mcp/v1/all' ),
            '/profile'     => rest_url( 'ai-mcp/v1/profile' ),
            '/pages'       => rest_url( 'ai-mcp/v1/pages' ),
            '/posts'       => rest_url( 'ai-mcp/v1/posts' ),
            '/faqs'        => rest_url( 'ai-mcp/v1/faqs' ),
            '/authors'     => rest_url( 'ai-mcp/v1/authors' ),
            '/media'       => rest_url( 'ai-mcp/v1/media' ),
            '/chunks'      => rest_url( 'ai-mcp/v1/chunks' ),
            '/social'      => rest_url( 'ai-mcp/v1/social' ),
            '/menus'       => rest_url( 'ai-mcp/v1/menus' ),
            '/analytics'   => rest_url( 'ai-mcp/v1/analytics' ),
            'MCP Manifest' => home_url( '/.well-known/mcp.json' ),
            'llms.txt'     => home_url( '/llms.txt' ),
            'llms-full.txt'=> home_url( '/llms-full.txt' ),
        );
        ?>
        <div class="ai-mcp-grid" style="margin-top:20px">
            <div class="ai-mcp-card">
                <h2>📊 Status</h2>
                <table class="widefat striped">
                    <tr><th>Plugin Version</th><td><?php echo esc_html( AI_MCP_VERSION ); ?></td></tr>
                    <tr><th>Status</th><td><span class="ai-mcp-badge ai-mcp-badge-active">Active</span></td></tr>
                    <tr><th>Last Generated</th><td id="ai-mcp-last-gen"><?php echo esc_html( $last ); ?></td></tr>
                    <tr><th>Pages Cached</th><td><?php echo esc_html( $pages_count ); ?></td></tr>
                    <tr><th>Posts Cached</th><td><?php echo esc_html( $posts_count ); ?></td></tr>
                    <tr><th>Social Links</th><td><?php echo esc_html( count( $social ) ); ?></td></tr>
                    <tr><th>Menus</th><td><?php echo esc_html( count( $menus ) ); ?></td></tr>
                </table>
            </div>
            <div class="ai-mcp-card">
                <h2>🔗 API Endpoints</h2>
                <table class="widefat striped">
                    <?php foreach ( $endpoints as $label => $url ) : ?>
                    <tr><th><?php echo esc_html( $label ); ?></th><td><a href="<?php echo esc_url( $url ); ?>" target="_blank">Open →</a></td></tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>

        <div class="ai-mcp-card" style="margin-top:20px">
            <h2>⚙️ Actions</h2>
            <p>
                <button id="ai-mcp-regen-btn" class="button button-primary button-large">🔄 Regenerate All JSON Now</button>
                <span id="ai-mcp-regen-status" style="margin-left:12px"></span>
            </p>
            <p class="description">JSON rebuilds automatically when you save pages/posts (selective - only changed content). Use this button for a full manual refresh.</p>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Tab: Analytics
    // -------------------------------------------------------------------------

    private function render_analytics() {
        $stats = $this->analytics->get_stats();
        $trend = $stats['last_7_days'];
        $max   = max( array_values( $trend ) ?: array(1) );
        ?>
        <div style="margin-top:20px">

            <!-- Summary cards -->
            <div class="ai-mcp-grid">
                <div class="ai-mcp-card ai-mcp-stat-card">
                    <div class="ai-mcp-stat-num"><?php echo esc_html( $stats['total_requests'] ); ?></div>
                    <div class="ai-mcp-stat-label">Total API Hits</div>
                </div>
                <div class="ai-mcp-card ai-mcp-stat-card">
                    <div class="ai-mcp-stat-num"><?php echo esc_html( count( $stats['top_agents'] ) ); ?></div>
                    <div class="ai-mcp-stat-label">Unique AI Agents</div>
                </div>
                <div class="ai-mcp-card ai-mcp-stat-card">
                    <div class="ai-mcp-stat-num"><?php echo esc_html( array_sum( array_values( $trend ) ) ); ?></div>
                    <div class="ai-mcp-stat-label">Requests Last 7 Days</div>
                </div>
            </div>

            <!-- 7-day bar chart -->
            <div class="ai-mcp-card" style="margin-top:20px">
                <h2>📅 Last 7 Days</h2>
                <div class="ai-mcp-bar-chart">
                    <?php foreach ( $trend as $day => $count ) : $pct = $max > 0 ? round( ( $count / $max ) * 100 ) : 0; ?>
                    <div class="ai-mcp-bar-col">
                        <div class="ai-mcp-bar-wrap">
                            <div class="ai-mcp-bar" style="height:<?php echo esc_attr( $pct ); ?>%" title="<?php echo esc_attr( $count ); ?> requests"></div>
                        </div>
                        <div class="ai-mcp-bar-count"><?php echo esc_html( $count ); ?></div>
                        <div class="ai-mcp-bar-label"><?php echo esc_html( wp_date( 'M j', strtotime( $day ) ) ); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="ai-mcp-grid" style="margin-top:20px">

                <!-- Top endpoints -->
                <div class="ai-mcp-card">
                    <h2>🏆 Most Queried Endpoints</h2>
                    <?php if ( empty( $stats['top_endpoints'] ) ) : ?>
                        <p>No data yet. Endpoints will appear here once AI agents start crawling.</p>
                    <?php else : ?>
                    <table class="widefat striped">
                        <thead><tr><th>Endpoint</th><th>Hits</th></tr></thead>
                        <tbody>
                        <?php foreach ( $stats['top_endpoints'] as $ep => $hits ) : ?>
                            <tr><td><code>/<?php echo esc_html( $ep ); ?></code></td><td><?php echo esc_html( $hits ); ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>

                <!-- AI Referrers -->
                <div class="ai-mcp-card">
                    <h2>🤖 AI Referrers</h2>
                    <?php if ( empty( $stats['top_agents'] ) ) : ?>
                        <p>No AI traffic detected yet.</p>
                    <?php else : ?>
                    <table class="widefat striped">
                        <thead><tr><th>Agent</th><th>Hits</th><th></th></tr></thead>
                        <tbody>
                        <?php
                        $total = max( 1, array_sum( $stats['top_agents'] ) );
                        foreach ( $stats['top_agents'] as $agent => $hits ) :
                            $pct = round( ( $hits / $total ) * 100 );
                        ?>
                            <tr>
                                <td><?php echo esc_html( $agent ); ?></td>
                                <td><?php echo esc_html( $hits ); ?></td>
                                <td>
                                    <div style="background:#e0e0e0;border-radius:3px;height:8px;width:100px">
                                        <div style="background:#0073aa;height:8px;border-radius:3px;width:<?php echo esc_attr( $pct ); ?>%"></div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>

            </div>

            <!-- Recent hits -->
            <?php if ( ! empty( $stats['recent_hits'] ) ) : ?>
            <div class="ai-mcp-card" style="margin-top:20px">
                <h2>🕐 Recent Requests</h2>
                <table class="widefat striped">
                    <thead><tr><th>Time</th><th>Endpoint</th><th>Agent</th><th>UA</th></tr></thead>
                    <tbody>
                    <?php foreach ( $stats['recent_hits'] as $hit ) : ?>
                        <tr>
                            <td><?php echo esc_html( wp_date( 'M j H:i', $hit['ts'] ) ); ?></td>
                            <td><code><?php echo esc_html( $hit['endpoint'] ); ?></code></td>
                            <td><?php echo esc_html( $hit['agent'] ); ?></td>
                            <td style="font-size:11px;color:#666"><?php echo esc_html( $hit['ua_short'] ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <p style="margin-top:10px">
                    <button id="ai-mcp-clear-analytics" class="button">🗑 Clear Analytics Data</button>
                    <span id="ai-mcp-analytics-status" style="margin-left:10px"></span>
                </p>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Tab: Error Log
    // -------------------------------------------------------------------------

    private function render_errors() {
        $errors = $this->cache->get_error_log();
        ?>
        <div style="margin-top:20px">
            <div class="ai-mcp-card">
                <h2>🚨 Failed Fetch Log</h2>
                <p class="description">These URLs failed when the plugin tried to read rendered HTML content. Usually caused by pages behind auth or slow responses.</p>

                <?php if ( empty( $errors ) ) : ?>
                    <p>✅ No errors recorded.</p>
                <?php else : ?>
                <table class="widefat striped">
                    <thead><tr><th>Time</th><th>URL</th><th>Reason</th></tr></thead>
                    <tbody>
                    <?php foreach ( $errors as $err ) : ?>
                        <tr>
                            <td><?php echo esc_html( $err['ts'] ); ?></td>
                            <td><?php echo esc_html( $err['url'] ); ?></td>
                            <td><?php echo esc_html( $err['reason'] ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <p style="margin-top:10px">
                    <button id="ai-mcp-clear-errors" class="button">🗑 Clear Error Log</button>
                    <span id="ai-mcp-error-status" style="margin-left:10px"></span>
                </p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    private function render_settings() {
        if ( isset( $_POST['ai_mcp_settings_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ai_mcp_settings_nonce'] ) ), 'ai_mcp_save_settings' ) ) {
            $expose = isset( $_POST['ai_mcp_expose_author_emails'] ) ? '1' : '0';
            update_option( 'ai_mcp_expose_author_emails', $expose );
            echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
        }
        
        $expose_emails = get_option( 'ai_mcp_expose_author_emails', '0' );
        ?>
        <div style="margin-top:20px">
            <div class="ai-mcp-card">
                <h2>⚙️ Privacy Settings</h2>
                <form method="post" action="">
                    <?php wp_nonce_field( 'ai_mcp_save_settings', 'ai_mcp_settings_nonce' ); ?>
                    
                    <p>
                        <label>
                            <input type="checkbox" name="ai_mcp_expose_author_emails" value="1" <?php checked( $expose_emails, '1' ); ?> />
                            <strong>Expose Admin/Author email addresses in API responses</strong>
                        </label>
                    </p>
                    <p class="description">If enabled, the <code>/ai-mcp/v1/profile</code> and <code>/ai-mcp/v1/authors</code> endpoints will include the real email addresses. <br>⚠️ Only enable this if your authors have consented or if it's a corporate site. May conflict with GDPR obligations.</p>
                    
                    <p style="margin-top: 20px;">
                        <button type="submit" class="button button-primary">Save Settings</button>
                    </p>
                </form>
            </div>
        </div>
        <?php
    }
}
