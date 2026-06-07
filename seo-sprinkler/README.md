# SEO Sprinkler

[![Smoke test](https://github.com/amircoh44/amircoh44/actions/workflows/smoke-test.yml/badge.svg)](https://github.com/amircoh44/amircoh44/actions/workflows/smoke-test.yml)

> **Not a replacement for your SEO plugin — the layer on top of it.** Keep Yoast / Rank Math / AIOSEO for titles, meta and sitemaps; SEO Sprinkler does the hands‑on, page‑by‑page work they don't. Built from **30 years of SEO & website building by Amir Khan**, packed into one plugin.

A WordPress plugin that boosts on-page SEO for your articles in several ways:

1. **Image minimum** — counts images inside each article and flags posts below a configurable minimum.
2. **Schema check** — audits articles for Schema.org structured data (JSON-LD / microdata).
3. **Internal linking** — builds a linking index from your **Yoast SEO sitemap** and automatically links related content; new posts are picked up automatically.
4. **Content distribution ("Sprinkler")** — injects a shortcode (e.g. an Elementor template), an image, or HTML into articles matched by tag/category/keyword, placed precisely around your headings and paragraphs.
5. **Link audit** — internal vs external link counts per post + a full link list with an inline mini‑WYSIWYG editor to change a link's text/URL and save it.
6. **Content cleaner** — strips "generative" HTML junk to clean markup (no CSS/inline styles, nothing added to links); every change is logged with a per‑post backup and one‑click revert.
7. **Duplicate‑H1 warning** — flags a content `<h1>` in the editor; dismissible per post.
8. **Business profile → full schema** — a questionnaire that generates a complete JSON‑LD `@graph` (Organization/LocalBusiness, WebSite, WebPage, Article, **Service**, CollectionPage, BreadcrumbList) in `<head>` for every page, service, post and archive. (Disable Yoast's schema to avoid duplication.)
9. **Multiple sitemaps + sitemap‑synced audit** — add any number of sitemaps (merged/de‑duplicated) and scan every sitemap URL for structured data.
10. **SEO Sprinkler meta box** — on every post/page/CPT editor: one‑click "fill with related (randomised) images" (middle/left/right, thumbnail→full, reversible) + a live SEO checklist (images, internal/external links, schema, H1).
11. **Export / Migrate** — download the whole site as JSON (all post types/CPTs, taxonomies, media + alt/caption/description, settings, SEO metadata) for Python/other platforms; optional media ZIP; SEO‑plugin detection (Yoast, Rank Math, AIOSEO, SEOPress, The SEO Framework); PII export gated behind an authorization checkbox.
12. **Per‑post SEO score + AI assist** — a 0–100 on‑page SEO score on every editor, plus optional AI generation of a meta description / SEO title using *your own* OpenAI‑compatible endpoint and key (OpenAI, OpenRouter, Azure, local LLM — nothing is proxied through us).
13. **Syndication** — auto‑push every newly published post to outbound webhooks (Zapier / Make / n8n / IFTTT → Google Business Profile, Facebook, LinkedIn, X).

> Requires PHP 7.2+ and WordPress 5.6+. Works best alongside [Yoast SEO](https://wordpress.org/plugins/wordpress-seo/).

---

## Editions — Free vs Pro vs Expert

> **Everything is free up to 25 published items.** On a site with **25 or fewer** published posts/pages/CPTs, *every* Pro **and** Expert feature is unlocked automatically — no licence key required. Past 25 items the **Free** column applies unless a Pro/Expert licence is active. (The 25‑item grace is filterable via `spr_free_page_limit`.)

| Capability | Free | Pro | Expert |
| --- | :---: | :---: | :---: |
| Image‑minimum audit — count, sortable posts‑list column, editor notice | ✅ | ✅ | ✅ |
| Schema audit — detect Schema.org types, flag missing / required types | ✅ | ✅ | ✅ |
| Multiple sitemaps + sitemap‑synced schema audit | ✅ | ✅ | ✅ |
| Link audit — internal/external counts + inline link editor | ✅ | ✅ | ✅ |
| Content cleaner — scan, preview & per‑post revert | ✅ | ✅ | ✅ |
| Duplicate‑H1 warning (dismissible per post) | ✅ | ✅ | ✅ |
| Business‑profile questionnaire | ✅ | ✅ | ✅ |
| "Fill with images" meta box + per‑post **SEO score (0–100)** | ✅ | ✅ | ✅ |
| All settings | ✅ | ✅ | ✅ |
| **Automatic internal linking** (display‑time, non‑destructive) | — | ✅ | ✅ |
| **Permanent bulk apply / revert** of internal links | — | ✅ | ✅ |
| **Auto inbound links** into older posts when you publish | — | ✅ | ✅ |
| **Content distribution ("Sprinkler")** rules engine | — | ✅ | ✅ |
| **Bulk clean** all content + **auto‑clean on save** | — | ✅ | ✅ |
| **JSON‑LD `@graph` schema output** in `<head>` | — | ✅ | ✅ |
| **AI assist** — generate meta description / SEO title (your own key) | — | ✅ | ✅ |
| **Export / migrate** — full‑site JSON + optional media ZIP | — | — | ✅ |
| **Syndication** — publish → webhooks → GMB / Facebook / LinkedIn / X | — | — | ✅ |
| **Multisite / white‑label** | — | — | 🔜 |

**In short:** **Free** gives you all the *audits*, the inline editors, image fill and the SEO score — the hands‑on checking tools. **Pro** adds *automation* (auto‑linking, distribution, bulk cleaning, schema output, AI). **Expert** is Pro **plus** full‑site export/migration and syndication.

Licensing is provider‑agnostic — Freemius, Lemon Squeezy, Gumroad or direct sale (see [`MONETIZATION.md`](MONETIZATION.md)). A site activates a tier by entering a key, by defining the `SPR_EDITION` constant, or via the `spr_edition` / `spr_validate_license` filters.

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
wp-content/plugins/seo-sprinkler/
```

Copy the `seo-sprinkler` folder into your plugins directory (or zip it and upload via **Plugins → Add New → Upload Plugin**), then activate it. Configure under **SEO Sprinkler → Settings**.

---

## Architecture

The plugin is wired together by a single orchestrator (`SPR_Plugin`) which constructs the services and registers their hooks:

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
  class-link-scanner.php    Per-post internal/external link analysis + inline edit
  class-content-cleaner.php Generative-junk cleanup + log + per-post backup/revert
  class-heading-checker.php Duplicate-H1 warning (dismissible per post)
  class-business-profile.php Business questionnaire storage + schema helpers
  class-schema-generator.php JSON-LD @graph output (org, website, page, article, service, breadcrumbs)
  class-image-filler.php    "Fill with images" engine + SEO Sprinkler meta box
  class-seo-detector.php    Detects Yoast/Rank Math/AIOSEO/SEOPress/TSF + their meta
  class-exporter.php        Builds the full migration manifest (JSON) + media file list
  class-ajax.php            Nonce/capability-guarded batched AJAX endpoints
  class-plugin.php          Service container + hook wiring
admin/                      Menu pages, settings fields, views, CSS/JS
  class-distribution-admin.php   Rules list/editor + CRUD handlers
  class-link-audit-admin.php     Link counts/list + inline link editor
  class-cleaner-admin.php        Scan/clean + reversible change log
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
| `spr_link_index` (filter) | Modify the raw link index array before it is cached. |
| `spr_post_phrases` (filter) | Modify the anchor phrases derived for a single post. `($phrases, $post_id)` |
| `spr_should_inject_links` (filter) | Return `false` to skip display-time link injection for the current request. |
| `spr_should_distribute` (filter) | Return `false` to skip content distribution for the current request. |
| `spr_inbound_links_applied` (action) | Fires after inbound links to a new post are permanently applied. `($post_id, $result)` |
| `spr_sitemap_sslverify` (filter) | Toggle SSL verification when fetching the sitemap. |
| `spr_schema_sslverify` (filter) | Toggle SSL verification when fetching pages for schema detection. |

Injected links carry the CSS class `spr-internal-link` (and `data-spr-target="<post_id>"`), so they can be styled or detected.

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

## Development & testing

The plugin ships with a self-contained smoke test that stands up a throwaway
WordPress on **SQLite** (no MySQL, no wordpress.org — core and the SQLite
drop-in are pulled from GitHub) and exercises the engines, the `the_content`
filters, auto-clean-on-save, and every admin screen.

Run it locally:

```bash
# 1. Bootstrap a throwaway WP install (clones core + SQLite drop-in)
WP_CORE=/tmp/wp bash seo-sprinkler/tests/bootstrap-wp.sh

# 2. Run the suites (each exits non-zero on failure)
WP_CORE=/tmp/wp php seo-sprinkler/tests/test-engines.php
WP_CORE=/tmp/wp php seo-sprinkler/tests/test-frontend.php
WP_CORE=/tmp/wp php seo-sprinkler/tests/test-admin.php
```

The same suites run automatically in **GitHub Actions** on every push that
touches the plugin, across PHP 7.4 / 8.0 / 8.2 / 8.3
(`.github/workflows/smoke-test.yml`).

| Suite | Covers |
| --- | --- |
| `test-engines.php` | image/H1 counters, link enumerate/classify/inline-edit, every cleaner cleanup, backup→log→revert, link replacer, settings defaults |
| `test-edition.php` | Free/Pro/Expert gating, the 25-item free grace, licence activation |
| `test-frontend.php` | internal-link injection + content distribution on a real singular loop, auto-clean-on-save |
| `test-schema.php` | schema `@graph` for articles + services, business-profile helpers, multi-sitemap merge |
| `test-filler.php` | image relevance/randomisation, block markup, distribution, fill + revert |
| `test-syndication.php` | publish payload, webhook parsing, edition gating |
| `test-export.php` | export manifest sections, post meta/terms/SEO, media metadata, PII gating |
| `test-admin.php` | every admin screen renders with no fatals |

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
