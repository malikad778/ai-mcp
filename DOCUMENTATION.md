# AI MCP Plugin v2.0.0 - Complete Documentation

> Makes any WordPress site AI-readable. Exposes structured content via REST API and MCP protocol.

---

## Table of Contents

1. [Overview](#overview)
2. [Installation & Activation](#installation--activation)
3. [REST API Endpoints](#rest-api-endpoints)
4. [AI Discovery Files](#ai-discovery-files)
5. [Semantic Chunking](#semantic-chunking)
6. [JSON Caching & Auto-Sync](#json-caching--auto-sync)
7. [FAQ Extraction](#faq-extraction)
8. [Media Library Indexing](#media-library-indexing)
9. [Author Profiles](#author-profiles)
10. [ACF & Custom Fields](#acf--custom-fields)
11. [Social Links Detection](#social-links-detection)
12. [Analytics Dashboard](#analytics-dashboard)
13. [Rate Limiting](#rate-limiting)
14. [Sitemap Integration](#sitemap-integration)
15. [Robots.txt Integration](#robotstxt-integration)
16. [Admin Dashboard](#admin-dashboard)
17. [Developer Hooks & Filters](#developer-hooks--filters)
18. [Architecture Overview](#architecture-overview)

---

## Quick Reference: All Available URLs

Replace `https://yoursite.com` with your actual domain.

### 📋 Main Data Endpoints

| URL | What You Get |
|---|---|
| `https://yoursite.com/wp-json/ai-mcp/v1/all` | **Everything** — full site summary in one call |
| `https://yoursite.com/wp-json/ai-mcp/v1/profile` | Site owner profile, bio, and social links |
| `https://yoursite.com/wp-json/ai-mcp/v1/pages` | **All pages** with full content |
| `https://yoursite.com/wp-json/ai-mcp/v1/posts` | **All posts** with full content |
| `https://yoursite.com/wp-json/ai-mcp/v1/social` | Social media links |
| `https://yoursite.com/wp-json/ai-mcp/v1/menus` | Navigation menu structure |
| `https://yoursite.com/wp-json/ai-mcp/v1/faqs` | All FAQ pairs |
| `https://yoursite.com/wp-json/ai-mcp/v1/authors` | All author profiles |
| `https://yoursite.com/wp-json/ai-mcp/v1/media` | Media library index |
| `https://yoursite.com/wp-json/ai-mcp/v1/chunks` | Content index (word counts + chunk links) |

### 📄 How to View a Single Page's Full Content

**Option 1 — By slug:**
```
https://yoursite.com/wp-json/ai-mcp/v1/pages/about
https://yoursite.com/wp-json/ai-mcp/v1/pages/services
https://yoursite.com/wp-json/ai-mcp/v1/pages/contact
https://yoursite.com/wp-json/ai-mcp/v1/pages/speaking
https://yoursite.com/wp-json/ai-mcp/v1/pages/podcast
https://yoursite.com/wp-json/ai-mcp/v1/pages/media
https://yoursite.com/wp-json/ai-mcp/v1/pages/work-with-me
```

**Option 2 — By page ID (from the `/all` or `/chunks` response):**
```
https://yoursite.com/wp-json/ai-mcp/v1/chunks/90    ← About (ID: 90)
https://yoursite.com/wp-json/ai-mcp/v1/chunks/91    ← Services (ID: 91)
https://yoursite.com/wp-json/ai-mcp/v1/chunks/92    ← Contact (ID: 92)
https://yoursite.com/wp-json/ai-mcp/v1/chunks/93    ← Speaking (ID: 93)
https://yoursite.com/wp-json/ai-mcp/v1/chunks/94    ← Podcast (ID: 94)
https://yoursite.com/wp-json/ai-mcp/v1/chunks/95    ← Media (ID: 95)
https://yoursite.com/wp-json/ai-mcp/v1/chunks/96    ← Work With Me (ID: 96)
```

> **Which should I use?**
> - Use `/pages/{slug}` when you know the page slug — returns full content, excerpt, word count, FAQs, custom fields, and featured image.
> - Use `/chunks/{id}` when you have the page ID — returns content split into AI-friendly chunks with overlap for context preservation.

### 👤 How to View a Single Author

```
https://yoursite.com/wp-json/ai-mcp/v1/authors/1    ← Author by user ID
```

### 🤖 AI Discovery Files (Non-API)

| URL | Format | What It Is |
|---|---|---|
| `https://yoursite.com/.well-known/mcp.json` | JSON | MCP Manifest — tells AI agents what tools are available |
| `https://yoursite.com/llms.txt` | Plain text | Compact site summary (llms.txt spec) |
| `https://yoursite.com/llms-full.txt` | Plain text | Extended summary with page descriptions |

### 🔒 Admin-Only Endpoint

```
https://yoursite.com/wp-json/ai-mcp/v1/analytics    ← Requires logged-in admin
```

### Step-by-Step: Finding a Page's Data

1. Visit `/wp-json/ai-mcp/v1/all` — find your page in the `pages` array
2. Note the `chunks_url` value (e.g., `https://yoursite.com/wp-json/ai-mcp/v1/chunks/90`)
3. Open that `chunks_url` in your browser to see the full rendered content
4. **Or** use the slug directly: `/wp-json/ai-mcp/v1/pages/about`

---

## Overview

AI MCP (Model Context Protocol) transforms any WordPress site into a structured data source that AI agents (ChatGPT, Claude, Gemini, Perplexity, etc.) can crawl and understand. It works automatically with any theme - no configuration needed.

### Key Features at a Glance

| Feature | Description |
|---|---|
| **11 REST API Endpoints** | Pages, posts, profile, social, menus, FAQs, authors, media, chunks, analytics |
| **MCP Manifest** | `/.well-known/mcp.json` for AI agent tool discovery |
| **llms.txt** | Machine-readable site summary following the llms.txt spec |
| **Semantic Chunking** | Splits long content into overlapping, context-aware chunks |
| **FAQ Extraction** | Auto-detects FAQs from Yoast, RankMath, Core blocks, JSON-LD |
| **Media Indexing** | Full media library with alt text, dimensions, captions |
| **ACF Support** | Reads Advanced Custom Fields and public post meta |
| **Analytics** | Tracks which AI agents are crawling and which endpoints are hit |
| **Rate Limiting** | 100 req/hour per IP to prevent abuse |
| **Auto-Caching** | JSON files regenerate on content changes (selective rebuild) |
| **Rendered Content Fallback** | Fetches rendered HTML for pages using custom PHP templates |

---

## Installation & Activation

1. Upload the `ai-mcp-v2` folder to `/wp-content/plugins/`
2. Activate via **Plugins → Activate**
3. Go to **Settings → AI MCP** to see the dashboard
4. Click **"Regenerate All JSON Now"** for the initial cache build
5. Visit `/wp-json/ai-mcp/v1/all` to verify

On activation, the plugin:
- Creates the `json/` cache directory
- Purges any stale JSON from previous installs
- Flags a full cache regeneration
- Flushes rewrite rules (needed for `llms.txt` and `mcp.json` routes)

On deactivation, rewrite rules are flushed. The `uninstall.php` handles full cleanup.

---

## REST API Endpoints

All endpoints are under `/wp-json/ai-mcp/v1/` and are **publicly accessible** (no authentication required).

### Core Endpoints

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/all` | **Master endpoint** - compact summary of entire site (profile, pages, posts, FAQs, authors, media stats, content index, menus) |
| `GET` | `/profile` | Site owner profile: name, tagline, bio (from About page), email, social links |
| `GET` | `/pages` | All published pages with full rendered content, word count, excerpts |
| `GET` | `/pages/{slug}` | Single page by slug (e.g., `/pages/about`) |
| `GET` | `/posts` | All published posts with content, categories, tags, author |
| `GET` | `/social` | Social media links (auto-detected from Yoast, RankMath, theme mods, or custom filters) |
| `GET` | `/menus` | All registered navigation menus with items |

### v2 Endpoints

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/faqs` | All FAQ pairs across the site, grouped by source page |
| `GET` | `/authors` | All authors with bios, avatars, social links, recent posts |
| `GET` | `/authors/{id}` | Single author by user ID |
| `GET` | `/media` | Full media library index (images with alt text, dimensions, captions) |
| `GET` | `/chunks` | Content index - shows every page/post with word count, chunk count, and `chunks_url` |
| `GET` | `/chunks/{id}` | Full chunked content for a specific post/page ID |
| `GET` | `/analytics` | *(Admin only)* API usage statistics |

### Response Headers

Every response includes:
```
Access-Control-Allow-Origin: *
X-MCP-Version: 2.0.0
Cache-Control: public, max-age=3600
```

### Example: Get a Single Page

```
GET /wp-json/ai-mcp/v1/pages/about
```

Returns:
```json
{
  "id": 90,
  "title": "About",
  "slug": "about",
  "url": "https://example.com/about/",
  "excerpt": "...",
  "content": "Full rendered text content...",
  "word_count": 619,
  "chunks_url": "https://example.com/wp-json/ai-mcp/v1/chunks/90",
  "last_updated": "2026-03-25 05:03:39",
  "type": "page"
}
```

### The `/all` Endpoint (Master Summary)

The `/all` endpoint returns a **compact index** designed to fit within AI context windows. It includes:

- `_meta` - Site info, all endpoint URLs, MCP manifest link, llms.txt link
- `profile` - Name, tagline, bio, email, social links
- `pages` - Summary of each page (id, title, slug, url, excerpt, word_count, chunks_url)
- `posts` - Summary of each post (same fields + date, author, categories)
- `faqs` - All extracted FAQ pairs
- `authors` - All author profiles
- `media_summary` - Image/video/audio/doc counts
- `content_index` - Every page/post with chunk counts and direct `chunks_url` links
- `menus` - All navigation menu structures

> **Tip**: The `/all` response intentionally contains summaries only. For full page content, use the `chunks_url` provided for each item.

---

## AI Discovery Files

### MCP Manifest (`/.well-known/mcp.json`)

Follows the [Model Context Protocol](https://modelcontextprotocol.io/) spec. Tells AI agents what tools are available:

```
GET /.well-known/mcp.json
```

Returns:
```json
{
  "mcpVersion": "1.0.0",
  "name": "Site Name MCP Server",
  "description": "AI-readable structured content for Site Name.",
  "capabilities": { "tools": true, "resources": true },
  "endpoints": { ... },
  "tools": [
    { "name": "get_all_content", "description": "..." },
    { "name": "get_profile", "description": "..." },
    { "name": "get_pages", "description": "..." },
    { "name": "get_posts", "description": "..." },
    { "name": "get_social", "description": "..." }
  ]
}
```

### llms.txt (`/llms.txt` and `/llms-full.txt`)

Implements the [llms.txt specification](https://llmstxt.org/). A plain-text, markdown-formatted file that summarizes the site:

- **`/llms.txt`** - Compact version with page links
- **`/llms-full.txt`** - Extended version with 40-word page summaries

Contains:
1. Site name and tagline
2. About page bio (80 words)
3. All pages with links
4. Recent posts with dates
5. All AI-readable API endpoint links
6. Cross-references to `llms-full.txt` and MCP manifest

### HTML Discovery Tags

Automatically injected into every page's `<head>`:

```html
<!-- AI MCP: AI Agent Discovery -->
<link rel="alternate" type="application/json" href="/wp-json/ai-mcp/v1/all" title="Full Site Content (AI-Ready JSON)" />
<link rel="mcp-manifest+json" href="/.well-known/mcp.json" />
<meta name="ai-agent-entry" content="/wp-json/ai-mcp/v1/all" />
<!-- /AI MCP -->
```

---

## Semantic Chunking

Long content is split into overlapping, context-aware chunks so AI agents never exceed their context window.

### Configuration

| Setting | Value | Purpose |
|---|---|---|
| `CHUNK_WORDS` | 800 | Target words per chunk (~1,000 tokens) |
| `OVERLAP_WORDS` | 60 | Overlap between chunks to preserve context |

### How It Works

1. Content is split at **sentence boundaries** (not mid-word)
2. Common abbreviations (Mr., Dr., e.g., etc.) are protected from false splits
3. Chunks carry metadata: `chunk_index`, `total_chunks`, `source_id`, `source_title`, `source_url`
4. Short content (≤800 words) returns as a single chunk - no overhead

### Rendered Content Fallback

If a page has empty `post_content` (e.g., content is rendered by custom PHP templates), the chunker automatically:
1. Fetches the page via `wp_remote_get()`
2. Extracts the `<main>` tag content
3. Falls back to `<body>` content (minus header/footer/nav/aside)
4. Strips HTML to plain text

### Usage

```
GET /wp-json/ai-mcp/v1/chunks/90
```

Returns:
```json
{
  "post_id": 90,
  "title": "About",
  "url": "https://example.com/about/",
  "total_chunks": 1,
  "chunks": [
    {
      "chunk_index": 0,
      "total_chunks": 1,
      "word_count": 619,
      "content": "Full text content...",
      "source_id": 90,
      "source_title": "About",
      "source_url": "https://example.com/about/",
      "chunks_url": "https://example.com/wp-json/ai-mcp/v1/chunks/90"
    }
  ]
}
```

---

## JSON Caching & Auto-Sync

All API responses are cached as JSON files in the `json/` directory for performance.

### Selective Regeneration

When a single post/page is saved, **only that item's slice** is rebuilt - not the entire cache:

| Trigger | Action |
|---|---|
| **Post/page saved** | Rebuilds only that item in `pages.json` or `posts.json`, then regenerates `all.json` |
| **Post/page deleted** | Removes the item from the cache and regenerates `all.json` |
| **WP options changed** | Full regeneration (blogname, blogdescription, admin_email, wpseo_social) |
| **Plugin activated** | Full regeneration |
| **Manual button click** | Full regeneration via admin dashboard |

### Cache Files

```
json/
├── all.json       ← /all endpoint
├── pages.json     ← /pages endpoint
├── posts.json     ← /posts endpoint
├── profile.json   ← /profile endpoint
├── social.json    ← /social endpoint
├── menus.json     ← /menus endpoint
├── faqs.json      ← /faqs endpoint
├── authors.json   ← /authors endpoint
├── media.json     ← /media endpoint
└── chunks.json    ← /chunks index endpoint
```

### Error Logging

Failed HTTP fetches (when the plugin self-requests a page URL) are logged with timestamp, URL, and reason. Viewable in **Settings → AI MCP → Error Log** tab. Maximum 200 entries retained.

---

## FAQ Extraction

Automatically finds FAQ content from 5 sources:

| Source | Detection Method |
|---|---|
| **Yoast SEO FAQ Block** | Parses `yoast/faq-block` Gutenberg block attributes |
| **RankMath FAQ Block** | Parses `rank-math/faq-block` Gutenberg block attributes |
| **Core Details Block** | Parses `core/details` (native WP accordion, WP 6.1+) |
| **JSON-LD Schema** | Extracts `FAQPage` schema from `<script type="application/ld+json">` tags |
| **FAQ Pages** | Scans pages/posts whose title/slug contains "faq" |

FAQs are deduplicated by question and grouped by source page.

---

## Media Library Indexing

Indexes up to **300 images** from the WordPress media library with:

- URL, title, alt text, caption, description
- Filename, MIME type, dimensions (width × height), file size
- Upload date
- Parent page/post (if attached)
- Available image sizes with URLs
- Featured image flag

```
GET /wp-json/ai-mcp/v1/media
```

The `/all` endpoint includes a `media_summary` with counts by type (images, videos, audio, docs).

---

## Author Profiles

Exposes all authors who have published posts with:

- Display name, slug, email, bio, avatar (Gravatar)
- Role(s), post count, registration date
- Social links (auto-detected from user meta: twitter, facebook, linkedin, instagram, youtube, tiktok, github)
- 5 most recent published posts (title, URL, date)

```
GET /wp-json/ai-mcp/v1/authors
GET /wp-json/ai-mcp/v1/authors/1
```

---

## ACF & Custom Fields

If **Advanced Custom Fields (ACF)** is installed, the plugin reads all ACF field data and normalizes it:

| ACF Type | Normalized To |
|---|---|
| `WP_Post` | `{id, title, url}` |
| `WP_Term` | `{id, name, slug}` |
| `WP_User` | `{id, name}` |
| Image array | `{url, alt, caption, width, height}` |
| Repeaters/Groups | Recursively normalized arrays |

Even without ACF, **public custom fields** (non-underscore-prefixed meta keys) are included in the `fields.custom_fields` property of each page/post.

---

## Social Links Detection

Social links are auto-detected from multiple sources (first match wins):

| Priority | Source |
|---|---|
| 1 | **Yoast SEO** - `wpseo_social` option (facebook, twitter, instagram, linkedin, youtube, pinterest) |
| 2 | **RankMath** - `social_url_*` options |
| 3 | **Theme Mods** - `social_facebook`, `social_twitter`, etc. via `get_theme_mod()` |
| 4 | **Custom Filter** - `AI_MCP_social_links` filter (themes can inject their own links) |

---

## Analytics Dashboard

Tracks API usage with **zero extra database tables** (uses WordPress options):

### What's Tracked

- **Per-request log**: timestamp, endpoint, detected AI agent, user-agent snippet, hashed IP
- **Daily aggregates**: total requests, per-endpoint counts, per-agent counts
- **Rolling window**: Max 2,000 raw entries; daily stats kept for 90 days

### Known AI Agent Detection

Recognizes 13 AI crawlers by User-Agent signature:

| Agent | Signatures |
|---|---|
| Claude | `claudebot`, `claude-web`, `anthropic` |
| ChatGPT | `chatgpt-user`, `oai-searchbot`, `openai` |
| Perplexity | `perplexitybot`, `perplexity` |
| Gemini | `google-extended`, `bard`, `gemini` |
| Copilot/Bing | `bingbot`, `msnbot`, `bingpreview` |
| GPTBot | `gptbot` |
| You.com | `youbot` |
| Cohere | `cohere-ai` |
| Meta AI | `facebookexternalhit`, `meta-ai` |
| Mistral | `mistral` |
| Grok | `grok` |
| Common Crawl | `ccbot` |
| DuckAssist | `duckassistbot` |

### Admin Dashboard Displays

- **Total API hits** (lifetime)
- **Unique AI agents** detected
- **7-day bar chart** trend
- **Top endpoints** ranked by hits
- **AI referrers** with percentage bars
- **20 most recent requests** with timestamp, endpoint, agent, UA

Analytics can be cleared via the admin dashboard button.

---

## Rate Limiting

Prevents abuse with a per-IP rate limiter:

| Setting | Default |
|---|---|
| Max requests | **100 per hour** |
| Storage | WordPress transients |
| Scope | Only `/ai-mcp/v1/*` endpoints |

When exceeded, returns:
```json
{
  "code": "ai_mcp_rate_limit_exceeded",
  "message": "Rate limit exceeded. Please try again later.",
  "status": 429
}
```

Supports proxy-aware IP detection: Cloudflare (`CF-Connecting-IP`), `X-Forwarded-For`, `X-Real-IP`, `REMOTE_ADDR`.

---

## Sitemap Integration

Automatically registers MCP endpoints in **three** sitemap systems:

| System | How |
|---|---|
| **Native WP Sitemap** (WP 5.5+) | Custom `ai-mcp` sitemap provider registered via `wp_sitemaps_add_provider` |
| **Yoast SEO** | Added to sitemap index via `wpseo_sitemap_index` filter |
| **RankMath** | Added to sitemap index via `rank_math/sitemap/index` filter |

---

## Robots.txt Integration

Appends AI-friendly directives to WordPress's virtual `robots.txt`:

```
# AI MCP - AI Agent Access
Allow: /wp-json/ai-mcp/
Allow: /.well-known/mcp.json

# MCP Structured Data Sitemap
Sitemap: https://example.com/wp-json/ai-mcp/v1/all
```

---

## Admin Dashboard

Located at **Settings → AI MCP**. Three tabs:

### Tab 1: Status
- Plugin version and active status
- Last cache generation timestamp
- Cached pages/posts counts
- Social links and menus count
- Clickable links to every API endpoint
- **"Regenerate All JSON Now"** button

### Tab 2: Analytics
- Summary cards (total hits, unique agents, 7-day total)
- 7-day bar chart
- Top endpoints table
- AI referrers table with progress bars
- Recent requests log (last 20)
- **"Clear Analytics Data"** button

### Tab 3: Error Log
- Failed URL fetch log (timestamp, URL, reason)
- **"Clear Error Log"** button

---

## Developer Hooks & Filters

### Filters

| Filter | Description | Default |
|---|---|---|
| `AI_MCP_social_links` | Modify/add social links before they're returned | Auto-detected array |
| `AI_MCP_manifest` | Modify the MCP manifest JSON before serving | Default manifest array |

### Actions

| Action | Description | Parameters |
|---|---|---|
| `ai_mcp_fetch_error` | Fired when an HTTP page fetch fails | `$url`, `$error_message` |

### Example: Adding Social Links from a Theme

```php
add_filter( 'AI_MCP_social_links', function( $social ) {
    $social['youtube']  = 'https://youtube.com/@yourhandle';
    $social['twitter']  = 'https://twitter.com/yourhandle';
    return $social;
} );
```

### Example: Adding Custom Data to the MCP Manifest

```php
add_filter( 'AI_MCP_manifest', function( $manifest ) {
    $manifest['tools'][] = array(
        'name'        => 'get_products',
        'description' => 'Fetch all WooCommerce products.',
    );
    return $manifest;
} );
```

---

## Architecture Overview

```
ai-mcp-v2/
├── ai-mcp.php                          ← Plugin bootstrap (defines constants, loads all files)
├── uninstall.php                       ← Cleanup on uninstall
├── json/                               ← JSON cache directory (auto-created)
│   ├── all.json
│   ├── pages.json
│   ├── posts.json
│   ├── profile.json
│   ├── social.json
│   ├── menus.json
│   ├── faqs.json
│   ├── authors.json
│   ├── media.json
│   └── chunks.json
├── includes/
│   ├── class-mcp-content-reader.php    ← Core brain: reads all content (pages, posts, CPTs, menus, social)
│   ├── class-mcp-endpoints.php         ← REST API route registration and callbacks
│   ├── class-mcp-cache.php             ← JSON caching, selective rebuild, error logging
│   ├── class-mcp-chunker.php           ← Semantic text chunking with overlap
│   ├── class-mcp-acf.php               ← ACF & custom fields reader
│   ├── class-mcp-faq.php               ← FAQ extraction from blocks, JSON-LD
│   ├── class-mcp-media.php             ← Media library indexer
│   ├── class-mcp-authors.php           ← Author profile builder
│   ├── class-mcp-discovery.php         ← HTML <head> AI discovery tags
│   ├── class-mcp-manifest.php          ← /.well-known/mcp.json generator
│   ├── class-mcp-llms.php              ← /llms.txt and /llms-full.txt generator
│   ├── class-mcp-robots.php            ← robots.txt AI directives
│   ├── class-mcp-sitemap.php           ← Sitemap integration (Native, Yoast, RankMath)
│   ├── class-mcp-analytics.php         ← API usage tracking & AI agent detection
│   └── class-mcp-rate-limiter.php      ← Per-IP rate limiting
└── admin/
    ├── class-mcp-admin.php             ← Admin dashboard (3 tabs: status, analytics, errors)
    ├── mcp-admin.css                   ← Admin styles
    └── mcp-admin.js                    ← Admin AJAX (regenerate, clear analytics/errors)
```

### Boot Sequence

```
plugins_loaded → AI_MCP_init()
  ├── new AI_MCP_Content_Reader()    ← Reads all content
  ├── new AI_MCP_Analytics()         ← Tracks API usage
  ├── new AI_MCP_Cache($reader)      ← Hooks into save_post, delete_post, shutdown
  ├── new AI_MCP_Endpoints($reader)  ← Registers REST routes
  ├── new AI_MCP_Manifest($reader)   ← Serves /.well-known/mcp.json
  ├── new AI_MCP_Discovery()         ← Injects <head> tags
  ├── new AI_MCP_Robots()            ← Modifies robots.txt
  ├── new AI_MCP_Sitemap()           ← Registers in sitemaps
  ├── new AI_MCP_Rate_Limiter()      ← Applies rate limits
  ├── new AI_MCP_LLMS()              ← Serves /llms.txt
  └── if (is_admin)
      └── new AI_MCP_Admin()         ← Admin dashboard
```

---

*Documentation generated from source code analysis - AI MCP v2.0.0*
