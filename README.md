# AI MCP for WordPress

The AI Machine Context Protocol (MCP) plugin for WordPress automatically transforms any WordPress site into a structured, machine-readable format. It exposes endpoints specifically designed for Large Language Models (LLMs) and tools like Cursor, GitHub Copilot, completely bridging the gap between your dynamic WordPress content (posts, pages, authors, FAQs, ACF) and AI agents.

## Features

*   **Semantic Chunking & RAG-ready Content:** Breaks down massive WordPress pages/posts into manageable, NLP-friendly chunks (`/wp-json/ai-mcp/v1/chunks`).
*   **LLMs.txt Support:** Automatically generates `/llms.txt` and `/llms-full.txt` (following the [llms.txt standard](https://llmstxt.org/)) for rapid AI onboarding.
*   **Dynamic FAQ Extraction:** Scrapes content to detect potential Q&A pairs.
*   **Headless JSON Endpoints:** Provides fast, structured access to `pages.json`, `posts.json`, `media.json`, and `authors.json`.
*   **Caching & Optimization:** Utilizes background, batched JSON generation placed securely in your `wp-content/uploads/ai-mcp` folder to avoid performance hits during endpoint requests.
*   **Secure & Extendable:** Proper usage of nonces, sanitization, scalable pagination algorithms, and WordPress hooks (`apply_filters( 'ai_mcp_max_posts', 500 )`).

## Installation

1.  Download the ZIP file of this repository.
2.  Go to **Plugins > Add New** in your WordPress admin dashboard.
3.  Click **Upload Plugin** and select the ZIP file.
4.  Activate the **AI MCP** plugin.
5.  Go to **AI MCP** in the WordPress admin sidebar to trigger the initial JSON generation.

## How It Works / Usage

Once activated, your site immediately exposes:

*   `https://yoursite.com/llms.txt`
*   `https://yoursite.com/llms-full.txt`
*   `https://yoursite.com/.well-known/mcp.json`

Check the AI MCP setting panel to configure analytics and monitor the rate limiter.

## Customization (Filters)

You can extend the default behavior via your child theme's `functions.php`:

```php
// Adjust pagination for giant sites (Default: 500 posts per background generation)
add_filter( 'ai_mcp_max_posts', function( $max ) {
    return 1000;
});

// Target a different page for 'about' bio generation
add_filter( 'ai_mcp_profile_page_slug', function( $slug ) {
    return 'our-team';
} );
```

## Contributing

Pull requests are welcome. For major changes, please open an issue first to discuss what you would like to change.

## License

GPLv2 or later
