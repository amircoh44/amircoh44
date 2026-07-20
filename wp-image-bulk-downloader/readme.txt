=== Image Bulk Downloader ===
Contributors: amircoh44
Author: Amir Cohen
Author URI: https://www.adamchimneysweep.com/
Tags: media, images, export, download, zip, backup
Requires at least: 5.6
Tested up to: 6.5
Requires PHP: 7.2
Stable tag: 1.2.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

One-click export of every image in your media library as a ZIP — images only, with original folder paths, or with full metadata.

== Description ==

Image Bulk Downloader adds a single page under **Media → Bulk Download** where any user with the `upload_files` capability can package every image attachment in the media library into a single ZIP archive.

Three export modes:

* **Images only** — flat ZIP of every image, with automatically deduplicated filenames.
* **Images with upload folder paths** — preserves the original `wp-content/uploads/YYYY/MM/` structure inside the ZIP.
* **Images + folder paths + metadata** — same as above, plus `image-metadata.csv` and `image-metadata.json` describing each image's title, alt text, caption, description, MIME type, and upload date.

Large libraries are processed in small chunks via AJAX so the request never times out. A progress bar and live counter keep you informed, and you can cancel a running export at any time.

== Features ==

* One-click export of all media library images.
* Three export modes (flat, with paths, with metadata).
* Chunked AJAX processing for large media libraries.
* Progress bar with live status, plus cancel button.
* CSV + JSON metadata sidecar files.
* Automatic deduplication of filename collisions.
* Cleans up the temporary ZIP from the server after download.
* No database tables, no settings to configure.

== Installation ==

1. Upload the `wp-image-bulk-downloader` folder to `/wp-content/plugins/`.
2. Activate the plugin via **Plugins** in WordPress.
3. Go to **Media → Bulk Download**, pick a mode, and click **Download all images**.

Requires the PHP `ZipArchive` extension (bundled with most hosts).

== Frequently Asked Questions ==

= Where is the ZIP stored? =

In `wp-content/uploads/wpibd-exports/`, protected by `.htaccess`. It is deleted from the server as soon as your download completes.

= Does it include non-image media (PDF, video, etc.)? =

No — only attachments with an `image/*` MIME type.

= Will it slow down my site? =

The export is AJAX-driven and runs only while you are on the page. Default chunk size is 20 images per request.

== Changelog ==

= 1.2.2 =
* Fixed: download never started on some managed hosts (e.g. WP Engine). The stream now disables server-side output compression and only sends Content-Length when the byte count is guaranteed to match, preventing the browser from stalling on a length mismatch.
* Download is now streamed in 1 MB chunks with set_time_limit(0) so large full-site exports do not exhaust memory or time out mid-transfer.
* Added a visible "Download the ZIP" link in the success message as a fallback when the automatic download is blocked by the browser.
* Abandoned export archives (tab closed before download, blocked auto-download) are now swept from the uploads folder on the next export instead of lingering.

= 1.2.1 =
* New checkbox: "Deliver download as .zip.gz". Wraps the export in gzip before streaming it, so networks / antivirus / download managers that block .zip files let it through. Extract the .gz once with any tool and you get the normal .zip back.

= 1.2.0 =
* New export mode: Full site export (Astro-ready). Ships every post / page / custom post type as a markdown file with YAML frontmatter (categories, tags, author, featured image, Rank Math / Yoast SEO fields, Elementor flag) under src/content/. Adds src/data/ JSON files for site config, authors, taxonomies, menus, comments, and Rank Math redirects. Images land under public/images/ with the original YYYY/MM structure, and post-body image URLs are rewritten so they line up.
* Adds a project-handover/ folder with spreadsheets (project overview, content inventory, plugin inventory, integration inventory, menu, taxonomy, redirect, and user inventories), a redacted connections dossier (database, WP constants, SMTP, payment gateways, analytics), and a database inventory (schema, options, table row counts).
* Includes README.md files inside the ZIP explaining the layout and a suggested Astro content-collection schema.

= 1.1.0 =
* Adds optional site-info file (on by default) containing verification codes for Google Search Console, Bing, Yandex, Baidu, Pinterest, Facebook, Norton, Ahrefs, Semrush, Alexa; tracking IDs for Google Analytics (UA + GA4), Google Tag Manager, Google Ads, Facebook Pixel, TikTok Pixel, LinkedIn Insight, Pinterest Tag, Hotjar, Microsoft Clarity, Microsoft UET; SEO plugin settings from Yoast, Rank Math, All in One SEO, SEOPress; active theme; and active plugins list. Delivered as site-info.json + site-info.txt inside the ZIP.
* Fixed: "Export job not found or expired" caused by mixed-case job IDs.
* Fixed: "Export archive is missing" on hosts that do not flush empty ZipArchives.

= 1.0.0 =
* Initial release.
