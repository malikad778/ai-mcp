<?php
/**
 * AI MCP - Frontend Documentation Page
 *
 * Serves a beautifully styled HTML documentation page at /ai-mcp-docs
 * so non-developers can browse all features and available URLs.
 *
 * @package AI_MCP
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class AI_MCP_Docs_Page {

    public function __construct() {
        add_action( 'init',              array( $this, 'add_rewrite_rules' ) );
        add_action( 'template_redirect', array( $this, 'maybe_serve' ) );
    }

    public function add_rewrite_rules() {
        add_rewrite_rule( '^ai-mcp-docs/?$', 'index.php?ai_mcp_docs=1', 'top' );
        add_filter( 'query_vars', function( $vars ) {
            $vars[] = 'ai_mcp_docs';
            return $vars;
        } );
    }

    public function maybe_serve() {
        if ( ! get_query_var( 'ai_mcp_docs' ) ) return;

        $site_url  = home_url();
        $site_name = esc_html( get_bloginfo( 'name' ) );
        $version   = AI_MCP_VERSION;

        // Build all the dynamic URLs
        $api = rest_url( 'ai-mcp/v1' );

        // Get pages for the individual page links
        $pages = get_pages( array( 'post_status' => 'publish', 'sort_column' => 'post_title' ) );

        header( 'Content-Type: text/html; charset=utf-8' );
        header( 'Cache-Control: public, max-age=3600' );
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI MCP Documentation — <?php echo $site_name; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            line-height: 1.7;
            -webkit-font-smoothing: antialiased;
        }
        .hero {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
            border-bottom: 1px solid #334155;
            padding: 60px 20px;
            text-align: center;
        }
        .hero h1 {
            font-size: 2.5rem;
            font-weight: 900;
            background: linear-gradient(135deg, #f59e0b, #fbbf24, #f59e0b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 8px;
        }
        .hero .version {
            display: inline-block;
            background: #f59e0b;
            color: #0f172a;
            font-size: 0.75rem;
            font-weight: 800;
            padding: 3px 10px;
            border-radius: 50px;
            margin-bottom: 16px;
        }
        .hero p { color: #94a3b8; font-size: 1.1rem; max-width: 600px; margin: 0 auto; }
        .container { max-width: 900px; margin: 0 auto; padding: 40px 20px 80px; }
        .nav-links {
            display: flex; flex-wrap: wrap; gap: 8px;
            background: #1e293b; border: 1px solid #334155; border-radius: 12px;
            padding: 20px; margin-bottom: 40px;
        }
        .nav-links a {
            color: #f59e0b; text-decoration: none; font-size: 0.85rem; font-weight: 600;
            padding: 5px 12px; border-radius: 6px; transition: all 0.2s;
        }
        .nav-links a:hover { background: #f59e0b22; }
        section { margin-bottom: 48px; }
        h2 {
            font-size: 1.5rem; font-weight: 800; color: #f1f5f9;
            border-bottom: 2px solid #334155; padding-bottom: 12px; margin-bottom: 20px;
        }
        h2 .emoji { margin-right: 8px; }
        h3 { font-size: 1.1rem; font-weight: 700; color: #cbd5e1; margin: 24px 0 12px; }
        p, li { color: #94a3b8; }
        p { margin-bottom: 12px; }
        table {
            width: 100%; border-collapse: collapse; margin: 16px 0;
            background: #1e293b; border-radius: 12px; overflow: hidden;
            border: 1px solid #334155;
        }
        th {
            text-align: left; padding: 12px 16px; background: #0f172a;
            font-size: 0.75rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.05em; color: #64748b; border-bottom: 1px solid #334155;
        }
        td {
            padding: 10px 16px; border-bottom: 1px solid #1e293b;
            font-size: 0.9rem; color: #cbd5e1;
        }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #334155; }
        .url-link {
            color: #f59e0b; text-decoration: none; font-family: 'SF Mono', 'Fira Code', monospace;
            font-size: 0.82rem; word-break: break-all;
        }
        .url-link:hover { text-decoration: underline; }
        code {
            background: #1e293b; color: #fbbf24; padding: 2px 8px; border-radius: 4px;
            font-size: 0.85rem; font-family: 'SF Mono', 'Fira Code', monospace;
        }
        .card {
            background: #1e293b; border: 1px solid #334155; border-radius: 12px;
            padding: 20px; margin: 16px 0;
        }
        .card h4 { color: #f1f5f9; font-size: 1rem; margin-bottom: 8px; }
        .card p { color: #94a3b8; font-size: 0.9rem; }
        .badge {
            display: inline-block; background: #f59e0b22; color: #f59e0b;
            font-size: 0.7rem; font-weight: 700; padding: 2px 8px;
            border-radius: 4px; text-transform: uppercase; letter-spacing: 0.05em;
        }
        .badge-green { background: #22c55e22; color: #4ade80; }
        .badge-blue { background: #3b82f622; color: #60a5fa; }
        .badge-red { background: #ef444422; color: #f87171; }
        .tip {
            background: #1e293b; border-left: 4px solid #f59e0b;
            padding: 16px 20px; border-radius: 0 8px 8px 0; margin: 16px 0;
        }
        .tip strong { color: #fbbf24; }
        ul { padding-left: 20px; margin: 8px 0; }
        li { margin: 4px 0; font-size: 0.9rem; }
        .steps { counter-reset: step; list-style: none; padding: 0; }
        .steps li {
            counter-increment: step; padding: 12px 16px 12px 52px;
            background: #1e293b; border: 1px solid #334155; border-radius: 8px;
            margin: 8px 0; position: relative;
        }
        .steps li::before {
            content: counter(step);
            position: absolute; left: 16px; top: 12px;
            width: 26px; height: 26px; background: #f59e0b; color: #0f172a;
            border-radius: 50%; font-size: 0.8rem; font-weight: 800;
            display: flex; align-items: center; justify-content: center;
        }
        .footer {
            text-align: center; padding: 40px 20px; border-top: 1px solid #334155;
            color: #475569; font-size: 0.85rem;
        }
        @media (max-width: 640px) {
            .hero h1 { font-size: 1.8rem; }
            td, th { padding: 8px 10px; font-size: 0.8rem; }
        }
    </style>
</head>
<body>

<div class="hero">
    <span class="version">v<?php echo esc_html( $version ); ?></span>
    <h1>AI MCP Documentation</h1>
    <p>Everything you need to know about how AI agents read and understand this website's content.</p>
</div>

<div class="container">

    <nav class="nav-links">
        <a href="#endpoints">📋 Endpoints</a>
        <a href="#page-data">📄 Page Data</a>
        <a href="#discovery">🤖 AI Discovery</a>
        <a href="#chunking">🧩 Chunking</a>
        <a href="#features">⚡ All Features</a>
        <a href="#analytics">📈 Analytics</a>
        <a href="#developers">🔧 Developers</a>
    </nav>

    <!-- ====== ENDPOINTS ====== -->
    <section id="endpoints">
        <h2><span class="emoji">📋</span> All Available Endpoints</h2>
        <p>These are all the URLs where your site's data is available. Click any link to see it live.</p>

        <table>
            <thead><tr><th>Endpoint</th><th>What You Get</th><th>Link</th></tr></thead>
            <tbody>
                <tr>
                    <td><code>/all</code></td>
                    <td>Complete site summary in one call</td>
                    <td><a class="url-link" href="<?php echo esc_url( $api . '/all' ); ?>" target="_blank">Open →</a></td>
                </tr>
                <tr>
                    <td><code>/profile</code></td>
                    <td>Site owner profile, bio, social links</td>
                    <td><a class="url-link" href="<?php echo esc_url( $api . '/profile' ); ?>" target="_blank">Open →</a></td>
                </tr>
                <tr>
                    <td><code>/pages</code></td>
                    <td>All pages with full content</td>
                    <td><a class="url-link" href="<?php echo esc_url( $api . '/pages' ); ?>" target="_blank">Open →</a></td>
                </tr>
                <tr>
                    <td><code>/posts</code></td>
                    <td>All blog posts with full content</td>
                    <td><a class="url-link" href="<?php echo esc_url( $api . '/posts' ); ?>" target="_blank">Open →</a></td>
                </tr>
                <tr>
                    <td><code>/social</code></td>
                    <td>Social media links</td>
                    <td><a class="url-link" href="<?php echo esc_url( $api . '/social' ); ?>" target="_blank">Open →</a></td>
                </tr>
                <tr>
                    <td><code>/menus</code></td>
                    <td>Navigation menu structure</td>
                    <td><a class="url-link" href="<?php echo esc_url( $api . '/menus' ); ?>" target="_blank">Open →</a></td>
                </tr>
                <tr>
                    <td><code>/faqs</code></td>
                    <td>All FAQ question & answer pairs</td>
                    <td><a class="url-link" href="<?php echo esc_url( $api . '/faqs' ); ?>" target="_blank">Open →</a></td>
                </tr>
                <tr>
                    <td><code>/authors</code></td>
                    <td>Author profiles with bios & avatars</td>
                    <td><a class="url-link" href="<?php echo esc_url( $api . '/authors' ); ?>" target="_blank">Open →</a></td>
                </tr>
                <tr>
                    <td><code>/media</code></td>
                    <td>Media library index</td>
                    <td><a class="url-link" href="<?php echo esc_url( $api . '/media' ); ?>" target="_blank">Open →</a></td>
                </tr>
                <tr>
                    <td><code>/chunks</code></td>
                    <td>Content index with chunk links</td>
                    <td><a class="url-link" href="<?php echo esc_url( $api . '/chunks' ); ?>" target="_blank">Open →</a></td>
                </tr>
            </tbody>
        </table>
    </section>

    <!-- ====== PAGE DATA ====== -->
    <section id="page-data">
        <h2><span class="emoji">📄</span> How to View Each Page's Data</h2>
        <p>Every page on this site has its own data endpoint. You can access it two ways:</p>

        <h3>Option 1: By Page Name (Slug)</h3>
        <p>Simply add the page name to the URL:</p>
        <table>
            <thead><tr><th>Page</th><th>URL</th><th>Link</th></tr></thead>
            <tbody>
                <?php foreach ( $pages as $page ) : ?>
                <tr>
                    <td><strong><?php echo esc_html( $page->post_title ); ?></strong></td>
                    <td><code>/pages/<?php echo esc_html( $page->post_name ); ?></code></td>
                    <td><a class="url-link" href="<?php echo esc_url( $api . '/pages/' . $page->post_name ); ?>" target="_blank">Open →</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h3>Option 2: By Page ID (Chunked Content)</h3>
        <p>Use the page's ID number to get the content in AI-friendly chunks:</p>
        <table>
            <thead><tr><th>Page</th><th>ID</th><th>URL</th><th>Link</th></tr></thead>
            <tbody>
                <?php foreach ( $pages as $page ) : ?>
                <tr>
                    <td><strong><?php echo esc_html( $page->post_title ); ?></strong></td>
                    <td><?php echo esc_html( $page->ID ); ?></td>
                    <td><code>/chunks/<?php echo esc_html( $page->ID ); ?></code></td>
                    <td><a class="url-link" href="<?php echo esc_url( $api . '/chunks/' . $page->ID ); ?>" target="_blank">Open →</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="tip">
            <strong>💡 Tip:</strong> Use <code>/pages/{slug}</code> for full content with metadata. Use <code>/chunks/{id}</code> for AI-optimized chunked content.
        </div>

        <h3>Step-by-Step Guide</h3>
        <ol class="steps">
            <li>Open the <a class="url-link" href="<?php echo esc_url( $api . '/all' ); ?>" target="_blank">/all endpoint</a> to see a summary of everything</li>
            <li>Find your page in the <code>pages</code> array — note its <code>slug</code> or <code>chunks_url</code></li>
            <li>Open that <code>chunks_url</code> in your browser to see the full rendered content</li>
            <li>Or go directly to <code>/pages/about</code> (replace "about" with any page slug)</li>
        </ol>
    </section>

    <!-- ====== DISCOVERY ====== -->
    <section id="discovery">
        <h2><span class="emoji">🤖</span> AI Discovery Files</h2>
        <p>These special files help AI crawlers find and understand this site:</p>

        <table>
            <thead><tr><th>File</th><th>Format</th><th>Purpose</th><th>Link</th></tr></thead>
            <tbody>
                <tr>
                    <td><strong>MCP Manifest</strong></td>
                    <td><span class="badge">JSON</span></td>
                    <td>Tells AI agents what tools & data are available</td>
                    <td><a class="url-link" href="<?php echo esc_url( $site_url . '/.well-known/mcp.json' ); ?>" target="_blank">Open →</a></td>
                </tr>
                <tr>
                    <td><strong>llms.txt</strong></td>
                    <td><span class="badge-green badge">Text</span></td>
                    <td>Compact site summary for LLMs</td>
                    <td><a class="url-link" href="<?php echo esc_url( $site_url . '/llms.txt' ); ?>" target="_blank">Open →</a></td>
                </tr>
                <tr>
                    <td><strong>llms-full.txt</strong></td>
                    <td><span class="badge-green badge">Text</span></td>
                    <td>Extended summary with page descriptions</td>
                    <td><a class="url-link" href="<?php echo esc_url( $site_url . '/llms-full.txt' ); ?>" target="_blank">Open →</a></td>
                </tr>
            </tbody>
        </table>

        <div class="card">
            <h4>Auto-Injected HTML Tags</h4>
            <p>The plugin automatically adds discovery tags to every page's <code>&lt;head&gt;</code> so AI crawlers can find the data without any manual setup.</p>
        </div>
    </section>

    <!-- ====== CHUNKING ====== -->
    <section id="chunking">
        <h2><span class="emoji">🧩</span> How Chunking Works</h2>
        <p>Long content is automatically split into smaller, overlapping pieces so AI assistants can process it without running out of memory.</p>

        <div class="card">
            <h4>Configuration</h4>
            <table style="margin: 0;">
                <tr><td><strong>Chunk Size</strong></td><td>~800 words per chunk (~1,000 tokens)</td></tr>
                <tr><td><strong>Overlap</strong></td><td>60 words overlap between chunks</td></tr>
                <tr><td><strong>Split Method</strong></td><td>At sentence boundaries (never mid-word)</td></tr>
            </table>
        </div>

        <p>Short pages (under 800 words) are returned as a single chunk — no unnecessary splitting.</p>
    </section>

    <!-- ====== FEATURES ====== -->
    <section id="features">
        <h2><span class="emoji">⚡</span> All Features</h2>

        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 12px;">
            <div class="card">
                <h4>📊 11 REST Endpoints</h4>
                <p>Pages, posts, profile, social, menus, FAQs, authors, media, chunks, analytics.</p>
            </div>
            <div class="card">
                <h4>🧩 Semantic Chunking</h4>
                <p>Context-aware splitting with overlap. Respects sentence boundaries.</p>
            </div>
            <div class="card">
                <h4>❓ FAQ Extraction</h4>
                <p>Auto-detects FAQs from Yoast, RankMath, Core blocks, and JSON-LD schema.</p>
            </div>
            <div class="card">
                <h4>🖼️ Media Indexing</h4>
                <p>Up to 300 images with alt text, dimensions, captions, and sizes.</p>
            </div>
            <div class="card">
                <h4>👤 Author Profiles</h4>
                <p>Bios, avatars, social links, and recent posts for each author.</p>
            </div>
            <div class="card">
                <h4>🔗 ACF Support</h4>
                <p>Reads Advanced Custom Fields and public post meta automatically.</p>
            </div>
            <div class="card">
                <h4>📈 AI Analytics</h4>
                <p>See which AI agents are crawling your site and which endpoints are popular.</p>
            </div>
            <div class="card">
                <h4>🛡️ Rate Limiting</h4>
                <p>100 requests per hour per IP to prevent abuse.</p>
            </div>
            <div class="card">
                <h4>🗺️ Sitemap Integration</h4>
                <p>Registers in Native WP, Yoast, and RankMath sitemaps.</p>
            </div>
            <div class="card">
                <h4>🤖 robots.txt</h4>
                <p>Auto-adds AI-friendly directives to robots.txt.</p>
            </div>
            <div class="card">
                <h4>💾 Smart Caching</h4>
                <p>Selective rebuild on save — only changed content is regenerated.</p>
            </div>
            <div class="card">
                <h4>🔄 Rendered Fallback</h4>
                <p>Captures content from custom PHP templates, not just the editor.</p>
            </div>
        </div>
    </section>

    <!-- ====== ANALYTICS ====== -->
    <section id="analytics">
        <h2><span class="emoji">📈</span> Analytics & Tracking</h2>
        <p>The plugin tracks which AI agents are crawling your site. View stats at <strong>Settings → AI MCP → Analytics</strong> in the WordPress admin.</p>

        <h3>Detected AI Agents</h3>
        <table>
            <thead><tr><th>AI Agent</th><th>Status</th></tr></thead>
            <tbody>
                <tr><td>ChatGPT / GPTBot</td><td><span class="badge-green badge">Detected</span></td></tr>
                <tr><td>Claude (Anthropic)</td><td><span class="badge-green badge">Detected</span></td></tr>
                <tr><td>Gemini (Google)</td><td><span class="badge-green badge">Detected</span></td></tr>
                <tr><td>Perplexity</td><td><span class="badge-green badge">Detected</span></td></tr>
                <tr><td>Copilot / Bing</td><td><span class="badge-green badge">Detected</span></td></tr>
                <tr><td>Meta AI</td><td><span class="badge-green badge">Detected</span></td></tr>
                <tr><td>Grok / Mistral / Cohere</td><td><span class="badge-green badge">Detected</span></td></tr>
                <tr><td>You.com / DuckAssist</td><td><span class="badge-green badge">Detected</span></td></tr>
            </tbody>
        </table>
    </section>

    <!-- ====== DEVELOPERS ====== -->
    <section id="developers">
        <h2><span class="emoji">🔧</span> For Developers</h2>
        <p>The plugin provides WordPress filters for customization:</p>

        <div class="card">
            <h4><code>AI_MCP_social_links</code></h4>
            <p>Add or modify social links before they're returned by the API. Your theme can inject social URLs from custom meta fields.</p>
        </div>

        <div class="card">
            <h4><code>AI_MCP_manifest</code></h4>
            <p>Modify the MCP manifest JSON before it's served. Add custom tools or endpoints for AI agents.</p>
        </div>

        <p>Full technical documentation is available in <code>DOCUMENTATION.md</code> inside the plugin directory.</p>
    </section>

</div>

<div class="footer">
    AI MCP v<?php echo esc_html( $version ); ?> — <?php echo $site_name; ?> — <a href="<?php echo esc_url( admin_url( 'options-general.php?page=ai-mcp' ) ); ?>" style="color: #f59e0b;">Admin Dashboard →</a>
</div>

</body>
</html>
        <?php
        exit;
    }
}
