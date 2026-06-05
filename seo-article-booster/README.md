# SEO Article Booster

A WordPress plugin that boosts on-page SEO for your articles in three ways:

1. **Image minimum** — counts images inside each article and flags posts below a configurable minimum.
2. **Schema check** — audits articles for Schema.org structured data (JSON-LD / microdata).
3. **Internal linking** — builds a linking index from your **Yoast SEO sitemap** and automatically links related content; new posts are picked up automatically.
4. **Content distribution ("Sprinkler")** — injects a shortcode (e.g. an Elementor template), an image, or HTML into articles matched by tag/category/keyword, placed precisely around your headings and paragraphs.

> Requires PHP 7.2+ and WordPress 5.6+. Works best alongside [Yoast SEO](https://wordpress.org/plugins/wordpress-seo/).

---

## Features

### 🖼️ Image minimum auditing
- Counts inline `<img>` tags, Gutenberg image/gallery blocks, classic `[gallery]` shortcodes, and (optionally) the featured image.
- Set a **minimum images per article** in the settings.
- Articles below the minimum are flagged:
  - in the **Image Audit** screen (batched AJAX scan),
  - as an **"Images" column** on the posts list (sortable),
  - with a **warning notice** in the editor and a count in the **Publish** box.

### 🧩 Schema (structured data) check
- Fetches each article's rendered HTML and extracts every Schema.org `@type` from `application/ld+json` blocks (including `@graph`) and `schema.org` microdata.
- Pass condition: *any* structured data, or *specific required types* (e.g. `Article, BlogPosting`).
- Results cached per post; shown on the **Schema Audit** screen and in the Publish box.

### 🔗 Internal linking from the Yoast sitemap
- Parses the Yoast sitemap index and its child sitemaps to determine which URLs are linkable.
- Builds anchor phrases from each post's **title** and/or **Yoast focus keyword(s)**.
- **Display-time injection (default, non-destructive):** links are added via the `the_content` filter — nothing is written to the database.
- **Permanent apply / revert (optional):** bake links into `post_content` and strip them again, both batched via AJAX.
- Safe matching: whole-word + Unicode-aware, never links inside existing links / code / scripts / (optionally) headings, with per-phrase and per-article caps.

### 💧 Content distribution ("Sprinkler")
Create **rules** that sprinkle content into your articles without touching the stored post:
- **Targeting:** post types + tags + categories + keywords (match ANY/ALL) + explicit include/exclude IDs.
- **Payload:** an Elementor (or any) **shortcode**, an **image** from the media library, or **custom HTML**.
- **Placement:** top/bottom, before/after the Nth paragraph, before/after the first/last/Nth sub-heading, **between two paragraphs** (uses an empty gap when present), after every N paragraphs, the middle, or after N words.
- **Options:** max insertions, min paragraphs before, device targeting (desktop/mobile), wrapper CSS class, start/end **schedule**, de-duplication, and priority.
- Injection is at display time via `the_content`, so your content "stays as is."

### 🆕 New post awareness
- On publish, the link index is rebuilt so older related articles immediately link to the new URL.
- Optionally insert **permanent inbound links** to the new post into a limited number of older related articles.

---

## Installation

```
wp-content/plugins/seo-article-booster/
```

Copy the `seo-article-booster` folder into your plugins directory (or zip it and upload via **Plugins → Add New → Upload Plugin**), then activate it. Configure under **SEO Booster → Settings**.

---

## Architecture

The plugin is wired together by a single orchestrator (`SAB_Plugin`) which constructs the services and registers their hooks:

```
includes/
  class-settings.php        Settings storage, defaults, sanitisation
  class-image-scanner.php   Counts images, enforces the minimum, admin column/notices
  class-schema-scanner.php  Fetches pages and detects Schema.org types
  class-sitemap-parser.php  Fetches + parses the Yoast sitemap (cached)
  class-link-index.php      Builds phrase→URL index from sitemap + titles/keywords
  class-link-replacer.php   DOM text-node engine that wraps phrases in <a> (shared)
  class-link-injector.php   the_content filter (display-time, non-destructive)
  class-link-applier.php    Permanent apply / revert + inbound links for new posts
  class-new-post-linker.php Reacts to publish transitions
  class-injection-rules.php Distribution rule storage, sanitisation + targeting
  class-content-distributor.php  DOM engine that sprinkles payloads into content
  class-ajax.php            Nonce/capability-guarded batched AJAX endpoints
  class-plugin.php          Service container + hook wiring
admin/                      Menu pages, settings fields, views, CSS/JS
  class-distribution-admin.php   Rules list/editor + CRUD handlers
public/css/                 Optional styling for injected links
```

Data flow:

```
sitemap ─▶ index ─▶ replacer ─┬▶ injector (display-time the_content)
                              └▶ applier (permanent apply / revert / inbound)
scanner  ─▶ image audit + minimum enforcement
schema   ─▶ schema audit
new-post ─▶ rebuild index / apply inbound links on publish
```

---

## Hooks & filters

| Filter / Action | Description |
| --- | --- |
| `sab_link_index` (filter) | Modify the raw link index array before it is cached. |
| `sab_post_phrases` (filter) | Modify the anchor phrases derived for a single post. `($phrases, $post_id)` |
| `sab_should_inject_links` (filter) | Return `false` to skip display-time link injection for the current request. |
| `sab_should_distribute` (filter) | Return `false` to skip content distribution for the current request. |
| `sab_inbound_links_applied` (action) | Fires after inbound links to a new post are permanently applied. `($post_id, $result)` |
| `sab_sitemap_sslverify` (filter) | Toggle SSL verification when fetching the sitemap. |
| `sab_schema_sslverify` (filter) | Toggle SSL verification when fetching pages for schema detection. |

Injected links carry the CSS class `sab-internal-link` (and `data-sab-target="<post_id>"`), so they can be styled or detected.

---

## Settings reference

| Setting | Default | Notes |
| --- | --- | --- |
| Minimum images per article | `3` | Articles below this are flagged. |
| Count the featured image | on | Include the featured image in the count. |
| Audit post types | `post` | Used by both the image and schema audits. |
| Audit articles for structured data | on | Enables the schema check. |
| Required schema types | *(blank)* | Comma list, e.g. `Article, BlogPosting`. Blank = any. |
| Enable automatic internal linking | on | Display-time `the_content` linking. |
| Link post types | `post, page` | Where links may be injected. |
| Max links per article / per phrase | `5` / `1` | Over-linking guards. |
| Ignore phrases shorter than | `4` | In characters. |
| Use titles / Yoast focus keywords | on / on | Sources of anchor phrases. |
| Skip headings, case-sensitive, new tab, nofollow | off* | Link behaviour. (*skip headings is on) |
| Sitemap index URL | *(auto)* | Defaults to `/sitemap_index.xml`. |
| Only link to URLs in the sitemap | on | Falls back to all posts if the sitemap is unreadable. |
| React when new content is published | on | Rebuilds the index on publish. |
| Permanently add inbound links on publish | off | Bakes inbound links into older posts. |
| Max older posts to edit per new post | `5` | Cap for the above. |

---

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
