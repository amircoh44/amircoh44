=== Image Bulk Downloader ===
Contributors: yourname
Tags: media, images, export, download, zip, backup
Requires at least: 5.6
Tested up to: 6.5
Requires PHP: 7.2
Stable tag: 1.0.0
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

= 1.0.0 =
* Initial release.
