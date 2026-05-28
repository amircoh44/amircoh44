# Image Bulk Downloader

A WordPress plugin that lets users download every image in the media library as a single ZIP file with one click.

## Export modes

| Mode | What's in the ZIP |
| --- | --- |
| **Images only** | Flat list of all image files, filenames deduplicated automatically |
| **Images with upload folder paths** | Same files inside the original `YYYY/MM/` upload structure |
| **Images + folder paths + metadata** | Above, plus `image-metadata.csv` and `image-metadata.json` with title, alt, caption, description, MIME type, and upload date for every image |

## Features

- Adds **Media → Bulk Download** in the WordPress admin.
- One radio-group + one button — no settings page to configure.
- Chunked AJAX export (20 images per request) so multi-gigabyte libraries don't trip PHP `max_execution_time` or memory limits.
- Live progress bar, live count, and a cancel button.
- ZIP is built in `wp-content/uploads/wpibd-exports/`, protected by `.htaccess`, and deleted after the browser finishes downloading it.
- Cleans up temp files and transients on plugin uninstall.

## Install

1. Copy the `wp-image-bulk-downloader` folder into `wp-content/plugins/`.
2. Activate **Image Bulk Downloader** in the Plugins screen.
3. Visit **Media → Bulk Download**, pick a mode, click **Download all images**.

## Requirements

- WordPress 5.6+
- PHP 7.2+
- PHP `ZipArchive` extension (the plugin refuses to activate without it)
- Capability: `upload_files` (Author and above by default)

## Layout

```
wp-image-bulk-downloader/
├── wp-image-bulk-downloader.php   Plugin bootstrap, constants, activation guard
├── readme.txt                     WordPress.org-style readme
├── uninstall.php                  Removes temp ZIPs and transients
├── includes/
│   ├── class-wpibd-plugin.php         Hooks + AJAX endpoints
│   ├── class-wpibd-admin.php          Admin menu, settings page, assets
│   ├── class-wpibd-image-collector.php Queries attachments + metadata
│   └── class-wpibd-zip-builder.php    Chunked ZIP build + streaming download
├── admin/
│   ├── css/admin.css
│   └── js/admin.js
└── languages/
```

## AJAX flow

1. `wpibd_start_export` — collects attachment IDs, creates a transient-backed job and an empty ZIP.
2. `wpibd_export_chunk` — adds the next 20 images to the ZIP; repeats until done. Metadata CSV/JSON are appended on the final chunk if the metadata mode is selected.
3. `wpibd_download_zip` — streams the ZIP back with the correct headers, then deletes it from the server.
4. `wpibd_cancel_export` — discards the in-progress ZIP and transient.

Every AJAX call is gated on `current_user_can( 'upload_files' )` plus a nonce.
