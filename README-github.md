# Image Bulk Downloader for WordPress

> One-click bulk download of every image in your WordPress media library as a single ZIP — images only, with the original upload folder structure, or with full metadata (title, alt text, caption, description).

[![License: GPL v2+](https://img.shields.io/badge/license-GPLv2%2B-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
![WordPress](https://img.shields.io/badge/WordPress-5.6%2B-21759b)
![PHP](https://img.shields.io/badge/PHP-7.2%2B-777bb4)

---

## What it does

Adds a single page under **Media → Bulk Download** in the WordPress admin. Pick one of three export modes, click **Download all images**, and the plugin builds a ZIP of your entire media library and streams it to your browser.

| Mode | What goes in the ZIP |
| --- | --- |
| **Images only** | Flat list of every image. Duplicate filenames are renamed automatically. |
| **Images with upload folder paths** | Same files inside the original `YYYY/MM/` folder structure of `wp-content/uploads/`. |
| **Images + folder paths + metadata** | Everything above **plus** `image-metadata.csv` and `image-metadata.json` containing each image's title, alt text, caption, description, MIME type, and upload date. |

## Why you might want it

- **Site migration / backup** — grab every uploaded image in one click before moving hosts.
- **Content audit** — open the CSV in Excel/Sheets and see which images are missing alt text.
- **Handover** — give a client every asset they own, organised as it sits on the server.
- **Reuse** — pull all images from an old project to use somewhere new.

## Installation

1. Download the latest release: **[wp-image-bulk-downloader.zip](./wp-image-bulk-downloader.zip)**
2. In WordPress admin, go to **Plugins → Add New → Upload Plugin**.
3. Choose the ZIP, click **Install Now**, then **Activate**.
4. Open **Media → Bulk Download**, pick a mode, click the button.

## Usage

1. Go to **Media → Bulk Download** in your WordPress admin.
2. Pick an export mode (Images only / With folder paths / With metadata).
3. Click **Download all images**.
4. A progress bar shows live status. Large libraries are processed 20 images per AJAX request so PHP timeouts are not a concern.
5. When it finishes, your browser downloads `wp-images-YYYY-MM-DD-HHMMSS.zip`.

You can cancel a running export at any time — the partial ZIP is discarded on the server.

## Requirements

- WordPress **5.6+**
- PHP **7.2+**
- The PHP **`ZipArchive`** extension (bundled with virtually all hosts)
- Capability: **`upload_files`** — Author role and above by default

## How it works under the hood

- AJAX-driven, chunked export so multi-gigabyte libraries don't trip `max_execution_time` or memory limits.
- The working ZIP is built inside `wp-content/uploads/wpibd-exports/`, protected with `.htaccess` (`Require all denied`) and an empty `index.html`.
- After the browser finishes the download, the file is deleted from the server. If the user closes the tab early, the job transient expires after 1 hour.
- The plugin's `uninstall.php` removes the temp directory and any leftover transients.

## FAQ

**Where is the ZIP stored on the server?**
`wp-content/uploads/wpibd-exports/`. It is wiped from the server as soon as your browser finishes downloading it.

**Does it include non-image media (PDF, video, audio)?**
No — only attachments with an `image/*` MIME type.

**Does it work on multisite?**
It runs per-site (each site exports its own media library).

**Does it work on WP Engine, Kinsta, Cloudways, etc.?**
Yes — tested on WP Engine. It writes to the standard uploads directory and uses transients, so it works on managed hosts.

## Changelog

### 1.0.0
- Initial release.
- Three export modes, chunked AJAX, progress bar, cancel button, CSV + JSON metadata sidecars, automatic temp cleanup.

## Author

**Amir Cohen**
[adamchimneysweep.com](https://www.adamchimneysweep.com/)

## License

[GPL-2.0-or-later](./LICENSE) — same license as WordPress itself.
