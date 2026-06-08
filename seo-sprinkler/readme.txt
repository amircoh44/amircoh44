=== SEO Sprinkler ===
Contributors: amircoh44
Tags: seo, internal links, images, schema, structured data, yoast
Requires at least: 5.6
Tested up to: 6.8
Requires PHP: 7.2
Stable tag: 1.21.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

The SEO toolkit that works ALONGSIDE Yoast/Rank Math (not instead of them) and does what they can't — images, internal links, schema, cleanup, migration.

== Description ==

**SEO Sprinkler is not a replacement for your SEO plugin — it's the layer on top of it.** Keep Yoast, Rank Math or All in One SEO for your titles, meta and sitemaps. SEO Sprinkler sits alongside them and does the hands-on, page-by-page work no other SEO plugin does for you: making sure every article is full of images, properly interlinked, schema-complete, free of AI "junk" markup, and ready to migrate.

It's built from **30 years of real SEO and website-building work by Amir Khan** — every check and fix he has done for clients by hand, packed into one plugin so you can do it in a click.

It combines several on-page SEO tools in one place:

1. **Image minimum** – Counts the images inside each article (inline images, gallery blocks/shortcodes and, optionally, the featured image) and flags any article that falls below a minimum you choose. The count appears in the post list and in the editor's Publish box.

2. **Schema (structured data) check** – Fetches each article's rendered output and detects the Schema.org JSON-LD (and microdata) types present — for example `Article` or `BlogPosting`. Flags any article that has no structured data, or that is missing a required type you specify.

3. **Internal linking from the Yoast sitemap** – Builds an index of linkable phrases from your published, sitemap-listed posts and pages (using each post's title and/or its Yoast focus keyword) and automatically links those phrases where they appear in other content. Linking is **non-destructive by default** (added on the fly when a page is viewed). You can optionally **bake the links permanently** into your content, and **revert** them again at any time.

4. **Content distribution ("Sprinkler")** – Create rules that inject a shortcode (such as an Elementor template), an image, or custom HTML into the articles you choose — matched by tag, category or keyword — placed exactly where you want: around sub-headings, around paragraphs, in the empty gap between paragraphs, after every N paragraphs, and more. Includes device targeting, scheduling, de-duplication and per-rule limits. Your stored content is never modified.

5. **Link audit** – See how many internal vs external links each post has (admin column + a dedicated screen), browse the full list of links inside any article, and edit a link's text and URL inline with a small WYSIWYG editor that saves straight to the post.

6. **Content cleaner** – Remove "generative content trash" from your HTML — markdown code fences, zero-width characters, empty paragraphs, Word/Office cruft, stray script/style, runs of <br>, empty inline tags, and (optionally) inline styles, class attributes and HTML comments. The result is clean markup with no CSS and nothing added to your links. Every change is logged with a per-post backup and a one-click Revert; optional auto-clean on save.

7. **Duplicate-H1 warning** – Warns you in the editor when the content contains an <h1> (which usually duplicates the theme's title H1). Dismissible per post for layouts that hide the theme H1 on purpose.

8. **Business profile → full schema** – A "tell us about your business" questionnaire (name, type/LocalBusiness subtype, logo, address, geo, hours, price range, social profiles, founder, areas served…) that powers a complete JSON-LD `@graph` output in `<head>`: Organization/LocalBusiness, WebSite (with search action), WebPage, Article, **Service** (mapped to your service post type), CollectionPage and BreadcrumbList — so your home page, pages, services, posts and tag/category archives all get accurate structured data. (Disable Yoast's schema to avoid duplication.)

9. **Multiple sitemaps + sitemap-synced audit** – Add as many sitemaps as you like (each an index or a flat urlset); they're merged and de-duplicated. The Schema Audit can then scan **every URL in your sitemaps** for structured data, keeping your schema coverage in sync with what you actually publish.

10. **SEO Sprinkler meta box (fill with images)** – On every post, page and custom-post editor: a one-click button that fills the content with **related, randomised** images from your media library — choose alignment (middle/left/right) and size (thumbnail → full) — distributed through the article so it's full of visuals. The same box shows a live SEO checklist (images vs the minimum, internal/external link counts, schema status, H1). Every fill is backed up and can be undone.

11. **Export / Migrate** – Download your whole site as structured JSON for import into Python or another platform: all post types and custom post types, every taxonomy/term, the full media library with alt/caption/description/sizes/URLs, general settings, and SEO metadata (Yoast, Rank Math, AIOSEO, SEOPress, The SEO Framework are auto-detected). Optionally bundle the actual media files as a ZIP. Anything containing personal data (user logins/emails) requires an explicit authorisation checkbox before it is exported.

12. **Per-post SEO score + AI assist** – A 0–100 SEO score on every editor, plus optional AI actions (generate meta description / SEO title) that use *your own* OpenAI-compatible endpoint and key (OpenAI, OpenRouter, Azure, local LLM) — nothing is proxied through us.

13. **Syndication** – Automatically push each newly published post to outbound webhooks; connect them to Zapier / Make / n8n / IFTTT to share to Google Business Profile, Facebook, LinkedIn, X and more.

= New post awareness =

When you publish a new post or page, the link index is rebuilt automatically so older, related articles start linking to the new URL right away. You can also have the plugin permanently insert inbound links to the new post into a limited number of older related articles.

= Why pair it with Yoast? =

The linkable URL set is taken from the Yoast SEO sitemap, so only public, indexed pages are linked. If Yoast (or a sitemap) isn't detected, the plugin gracefully falls back to all published posts and tells you so on the dashboard.

== Installation ==

1. Upload the `seo-sprinkler` folder to `/wp-content/plugins/`, or install the ZIP via **Plugins → Add New → Upload Plugin**.
2. Activate the plugin through the **Plugins** screen.
3. Go to **SEO Sprinkler → Settings** to set your image minimum, schema requirements and linking options.
4. Visit **SEO Sprinkler → Image Audit** and **Schema Audit** to scan existing content, and **Internal Links** to rebuild the link index.

== Frequently Asked Questions ==

= Does it modify my post content? =

Not unless you ask it to. By default internal links are added at display time via the `the_content` filter and nothing is written to the database. The **Internal Links** screen offers an optional "Apply links to all content" tool that writes the links permanently — and a "Revert applied links" tool that removes every link the plugin added.

= Do I need Yoast SEO? =

It's recommended, because the linkable URL set and the focus-keyword phrases come from Yoast. Without it, the plugin falls back to all published posts and uses post titles as anchor phrases.

= How does the schema check work? =

Because SEO plugins output JSON-LD in the page head at render time, the plugin requests each article over HTTP and inspects the rendered HTML for `application/ld+json` blocks (and `schema.org` microdata), collecting the `@type` values it finds. Results are cached per post.

= Will it create duplicate or nested links? =

No. The linker never links inside existing links, code, scripts or (optionally) headings, matches whole words only, and caps links per phrase and per article. Re-applying links first strips any it previously added, so it is safe to run repeatedly.

== Free vs Pro vs Expert ==

**Everything is free up to 25 pages.** On sites with 25 or fewer published items, every feature — including all the premium ones — is unlocked. Beyond 25 pages you choose a plan:

* **Free** – all audits and manual tools at any size (image-minimum audit, schema audit incl. sitemap coverage, link audit + inline editor, content-cleaner scan + revert, duplicate-H1 warning, business profile, "fill with images" tool, settings), plus *every* premium feature while your site is within 25 pages. Support: community forum & documentation.
* **Pro** – removes the 25-page limit for the automation/output features: automatic internal linking, permanent bulk apply/revert, content distribution (Sprinkler), bulk + auto content cleaning, new-post auto inbound links, AI actions, and JSON-LD schema output in <head>. Support: email support.
* **Expert** – everything in Pro plus the Export / Migrate tool (JSON + media ZIP) and multisite/agency use. Support: priority email support with a 24-hour response.

== Changelog ==

= 1.21.1 =
* New Content Cleaner rule: strip bloated image srcset/sizes attributes. Trims the long responsive-image markup from your stored content (WordPress re-adds srcset at render from the wp-image-ID class), leaving a clean <img src alt width height>.

= 1.21.0 =
* Fix: centred images no longer revert to left after saving in the Classic editor. On Classic-editor posts the plugin now inserts a classic image (which TinyMCE keeps aligned on save) instead of a Gutenberg block whose alignment was being rewritten.
* New: "Image format" choice on Image Distribution — Auto (match the editor), force Gutenberg block, or force Classic image.
* New: per-image alignment in "Preview & edit all" — set each image to Middle/Left/Right before inserting.

= 1.20.3 =
* Cleaner output: inserted images are now lean — a simple <img class="aligncenter size-large wp-image-ID"> with no giant srcset baked into your content (WordPress still adds responsive srcset automatically at render).
* The Content Cleaner no longer strips functional image classes: alignment (aligncenter/left/right), size-*, wp-image-ID and the plugin markers are kept even with "strip classes" on — so cleaning never un-centres or orphans distributed images.

= 1.20.2 =
* Fix: distributed images now carry the alignment class on the <img> itself (e.g. class="aligncenter size-large") — the classic WordPress markup every theme styles — so wide images centre (or float left/right) reliably even when the block-editor CSS isn't loaded. Clear your page cache (WP Rocket) after updating.

= 1.20.1 =
* Improved: the Search Console connect screen now walks you through Google Cloud step by step — enable the APIs, set up the consent screen and add yourself as a test user, then create the OAuth client choosing "User data" → "Web application" with the exact redirect URI — plus a "Common fixes" panel for access-blocked/redirect-mismatch errors.

= 1.20.0 =
* New: Link Audit table is sortable — click any column header; by default the weakest articles (fewest internal links) sit at the top so you fix them first.
* Improved: Search Console connect is now a guided, near-one-click setup — direct buttons to enable the two Google APIs and create the OAuth client, plus a one-click "Copy" for the redirect URI. After the two-minute setup, connecting is a single click.

= 1.19.0 =
* Fix: bulk image fill no longer dumps all images at the bottom — it now finds paragraph/heading/list seams in classic content too and scatters images evenly through the article.
* Fix: centred images render centred even on themes/page builders that don't load WordPress's block CSS (stronger, !important rules on our own image class). If a page is cached, clear your cache (e.g. WP Rocket) so the updated CSS takes effect.
* New: Image Distribution uses the 2026 design (gradient header, panel icons).
* New: "Sprinkle icons" now lets you choose placement (before each heading, start of each paragraph, or top of the article), scatters a different icon per spot, and matches each icon to the section by its alt text / title / file name.

= 1.18.0 =
* New: "Download content files (ZIP)" — the export is now split into many small, openable files (manifest.json + site.json + one JSON and clean .html per post + chunked media metadata + separate sections) instead of one giant manifest.json that editors and parsers choke on. The media ZIP uses the same split layout, with the actual files under media/files/.

= 1.17.0 =
* New: Image Distribution scatters images evenly across each article (no more clumping).
* New: small images (≤150px) are treated as icons — placed left-aligned at the start of a paragraph, never with a caption — so your service icons sit inline with the text.
* New: "Sprinkle icons" button on Image Distribution fills the selected articles with your icon-sized images only.

= 1.16.0 =
* New: "Fill from Google" now also imports your opening hours (when a Google Places API key is set) — Google's structured hours are converted straight into the plugin's schema hours format (e.g. "Mo 08:00-20:00"), including 24/7 and split shifts; closed days are simply left out.

= 1.15.0 =
* New: Search Console & Indexing (Expert) — one-click connect to Google Search Console (OAuth), check which articles are indexed, queue every unindexed URL, and submit a safe number to Google's Indexing API each day (with a daily schedule). You're told which URLs were newly indexed, dropped, or still not indexed (on-screen, by email and in the activity log).
* Fix: "Fill from Google" on the Business Profile now reads the actual place pin (not the map's viewport, which could be miles off) and fills the street address for free by reverse-geocoding the coordinates via OpenStreetMap. Coordinates now overwrite stale values. Phone still needs the optional Google Places API key.

= 1.14.0 =
* New: "Run all scans" on the Dashboard — runs the image, internal-link (and optionally schema) audits in one pass and shows a summary with one-click links to fix each issue.
* New: edit "Minimum images per article" right on the Dashboard.
* New: "Test schema on Google" button on the Dashboard — opens Google's Rich Results Test for your site.

= 1.13.0 =
* New: Export / Migrate now produces a complete, portable mirror — it renders the *real* content of every page (including Elementor and other page builders, whose layout lives outside the post content), adds a clean semantic-HTML version (no scripts, styles, classes or builder/WordPress markup), and extracts each page's content images and internal/external link connections. Navigation menus are exported too.
* New: Content Cleaner custom rules — define your own removal rules in Settings (one per line; a line wrapped in slashes is a regular expression, anything else is literal text) and run them with the other cleanups.
* New: Business Profile logo defaults to your WordPress site logo (or site icon) automatically.
* New: "Connect your Google listing" on the Business Profile — paste your Google Maps / Business link to fill the name and map coordinates (free); add a Google Places API key to also pull the phone, full address and website.

= 1.12.0 =
* New: "Preview & edit all" in Image Distribution — see every proposed image for the selected articles in one editable list, tweak alt text / caption / image title / description (or tick Skip), then insert them all at once.
* Fix: centered images now actually render centered. We set alignment on our own figure class in the front-end stylesheet, so center/left/right work even on themes or page builders that don't load WordPress's block-library CSS (which previously left "center" images stuck on the left).

= 1.11.0 =
* New: redesigned Dashboard — a colourful 2026 look with a branded header, icon stat cards, a clean status list, quick-action tiles and an activity timeline. The same design language (brand accent, soft cards) now carries across every SEO Sprinkler screen.
* New: Image Distribution can now remove the images it inserted, in bulk, from the selected articles ("Remove inserted images").
* New: Content Cleaner can clean articles one by one, and exclude any you don't want touched — scan, untick the ones to skip, then "Clean selected" (or use the per-row Clean button). "Clean all flagged" still cleans everything.
* Fix: the reviewer's "Approve & insert" button no longer shows a literal "&amp;".

= 1.10.0 =
* New: "Review each image first" in Image Distribution — step through every proposed image and edit its alt text, caption, image title and description before it is inserted. Approve one by one, skip, or "approve all remaining"; each run spreads across your whole media library.
* New: Image Distribution remembers your last "below the minimum" scan, so the list survives reloads until you re-scan.
* New: after an Image Audit scan, jump straight into Image Distribution to fill the flagged articles; Link Audit and Schema Audit now link on to Internal Links and the Business Profile.
* New: Business Profile is now also reachable as a tab inside Settings.
* New: lightweight activity log on the Dashboard (recent scans, fills, cleans and link audits).
* Fix: the Content Cleaner's "inline styles" cleanup now keeps functional table-layout styles (borders, padding, widths), so cleaning a page with styled tables no longer breaks them. It still scans only your article content (post_content), never the surrounding theme/page.

= 1.9.0 =
* New: detects any active SEO plugin (Rank Math, AIOSEO, SEOPress, The SEO Framework, Yoast), not just Yoast — shown on the Dashboard; internal linking now also reads Rank Math focus keywords.
* New: Schema Audit shows a "Test on Google" (Rich Results Test) link for each page; plus a warning when another SEO plugin is active and could clash with the plugin's own JSON-LD output (with how to disable it).
* New: Pro / Expert pills next to the options that need them in Settings.
* Tweak: menu reorganised into pipelines — Image Audit by Image Distribution, Link Audit by Internal Links, Schema Audit; Settings moved to the end.

= 1.8.0 =
* New: Image Distribution screen — find every article below your image minimum, select them, and bulk-insert related media-library images into the content with alt text (AI-written when AI is connected; a clean fallback otherwise). Choose "images per article" or "one image per N words"; each run spreads across the whole library so images vary; every change is backed up and revertable.
* Tweak: refreshed, animated progress bars (rounded pill + gradient) across all scans.

= 1.7.5 =
* Tweak: clearer Content Cleaner labels — "Inline style=… attributes" instead of the misleading style="" (it strips real, non-empty style attributes, e.g. on tables), same for class attributes.

= 1.7.4 =
* New: Link Audit remembers the last scan — results are stored and shown on every visit (with a "Last scanned …" time), and only refresh when you click "Re-scan all posts".

= 1.7.3 =
* New: Settings page is now organised into tabs (License, Internal links, Images & schema, Content tools, AI, Syndication) with expanded, plain-language explanations for every section. One "Save Changes" button still saves every tab at once.
* New: Business Profile — "Auto-fill from this site" button (pulls Name, Website URL, description and logo from WordPress) and "Look up coordinates from address" to fill latitude/longitude via OpenStreetMap (free, no API key; cached; override with the spr_geocode filter).

= 1.7.2 =
* New: Export / Migrate can download the JSON as a compressed **.gz** ("Compress (.gz)") — useful when antivirus/Defender mis-flags a plain export (your own post HTML/JS triggers a content heuristic). Added `X-Content-Type-Options: nosniff` to the export downloads and an in-page note on how to allow a blocked file.

= 1.7.1 =
* Fix: Settings page — field titles that contain tag names (e.g. the content cleaner's "Stray <script> and <style> blocks", Word/Office cruft, and the schema "<head>" option) are now HTML-escaped. Previously the raw `<script>` in a label was parsed as live markup and swallowed the rest of the page, hiding later options and the **Save Changes** button.

= 1.7.0 =
* New: Per-post SEO score (free) in the SEO Sprinkler meta box.
* New: AI assist (Pro) — generate meta description / SEO title via your own OpenAI-compatible endpoint (Settings → AI).
* New: Syndication (Expert) — push newly published posts to outbound webhooks (Zapier/Make/n8n/IFTTT → GMB, Facebook, LinkedIn, X).

= 1.6.0 =
* New: Free / Pro / Expert editions. Everything is free up to 25 pages (a configurable grace via the `spr_free_page_limit` filter); beyond that, premium features need Pro (automation, bulk tools, AI, schema output) or Expert (export/migration, multisite). License-key field unlocks premium; integrate any provider (Freemius, Lemon Squeezy, …) via the `spr_validate_license` filter. Dashboard shows edition + free usage.
* New: AI (bring-your-own API) — connect any OpenAI-compatible endpoint (OpenAI/OpenRouter/Azure/local) in Settings → AI; powers Pro AI actions.

= 1.5.0 =
* New: Export / Migrate — download the whole site as structured JSON (all post types/CPTs, taxonomies, full media library with alt/caption/description, settings, and SEO metadata from auto-detected SEO plugins) for import into Python or another platform; optional media-files ZIP. Personal data (logins/emails) requires explicit authorisation.

= 1.4.0 =
* New: "SEO Sprinkler" meta box on every post/page/custom-post editor — a one-click "fill with images" tool that inserts related, randomised library images (choose middle/left/right alignment and thumbnail→full size) distributed through the content, plus a live SEO checklist (images, internal/external links, schema, H1). Fills are backed up and reversible.

= 1.3.0 =
* New: Business Profile questionnaire that generates a full JSON-LD schema graph (Organization/LocalBusiness, WebSite, WebPage, Article, Service, CollectionPage, BreadcrumbList) output in <head> for every page, service, post and archive.
* New: Multiple sitemaps — add any number of sitemap URLs (index or urlset), merged and de-duplicated.
* New: Sitemap-synced schema audit — scan every URL in your sitemap(s) for structured data.
* Settings: schema-output toggle and configurable Service post type.

= 1.2.0 =
* New: Link Audit — per-post internal/external link counts (admin column + screen) and a full link list with an inline mini-WYSIWYG editor to change a link's text/URL and save it from the back end.
* New: Content Cleaner — remove "generative content trash" (markdown code fences, zero-width characters, empty paragraphs, Word/Office cruft, stray script/style, runs of <br>, empty inline tags, optional HTML comments / inline styles / class attributes). Leaves clean markup, adds nothing to links, and every change is logged with a per-post backup and one-click Revert. Optional auto-clean on save.
* New: Duplicate-H1 warning in the editor when content contains an <h1>, dismissible per post.

= 1.1.0 =
* New: Content Distribution ("Sprinkler") — rule-based injection of shortcodes, images or HTML into targeted articles (by tag/category/keyword) at precise positions around headings and paragraphs, with device targeting, scheduling, de-duplication and limits.

= 1.0.0 =
* Initial release: image-minimum auditing, Schema.org structured-data auditing, Yoast-sitemap-driven internal linking (display-time + permanent apply/revert), and new-post inbound-link awareness.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
