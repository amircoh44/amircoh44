=== SEO Article Booster ===
Contributors: amircoh44
Tags: seo, internal links, images, schema, structured data, yoast
Requires at least: 5.6
Tested up to: 6.8
Requires PHP: 7.2
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Enforce a minimum number of images per article, audit articles for Schema.org structured data, and auto-interlink content from your Yoast sitemap.

== Description ==

**SEO Article Booster** helps you ship better-optimised articles by combining three on-page SEO checks and tools in one place:

1. **Image minimum** – Counts the images inside each article (inline images, gallery blocks/shortcodes and, optionally, the featured image) and flags any article that falls below a minimum you choose. The count appears in the post list and in the editor's Publish box.

2. **Schema (structured data) check** – Fetches each article's rendered output and detects the Schema.org JSON-LD (and microdata) types present — for example `Article` or `BlogPosting`. Flags any article that has no structured data, or that is missing a required type you specify.

3. **Internal linking from the Yoast sitemap** – Builds an index of linkable phrases from your published, sitemap-listed posts and pages (using each post's title and/or its Yoast focus keyword) and automatically links those phrases where they appear in other content. Linking is **non-destructive by default** (added on the fly when a page is viewed). You can optionally **bake the links permanently** into your content, and **revert** them again at any time.

4. **Content distribution ("Sprinkler")** – Create rules that inject a shortcode (such as an Elementor template), an image, or custom HTML into the articles you choose — matched by tag, category or keyword — placed exactly where you want: around sub-headings, around paragraphs, in the empty gap between paragraphs, after every N paragraphs, and more. Includes device targeting, scheduling, de-duplication and per-rule limits. Your stored content is never modified.

= New post awareness =

When you publish a new post or page, the link index is rebuilt automatically so older, related articles start linking to the new URL right away. You can also have the plugin permanently insert inbound links to the new post into a limited number of older related articles.

= Why pair it with Yoast? =

The linkable URL set is taken from the Yoast SEO sitemap, so only public, indexed pages are linked. If Yoast (or a sitemap) isn't detected, the plugin gracefully falls back to all published posts and tells you so on the dashboard.

== Installation ==

1. Upload the `seo-article-booster` folder to `/wp-content/plugins/`, or install the ZIP via **Plugins → Add New → Upload Plugin**.
2. Activate the plugin through the **Plugins** screen.
3. Go to **SEO Booster → Settings** to set your image minimum, schema requirements and linking options.
4. Visit **SEO Booster → Image Audit** and **Schema Audit** to scan existing content, and **Internal Links** to rebuild the link index.

== Frequently Asked Questions ==

= Does it modify my post content? =

Not unless you ask it to. By default internal links are added at display time via the `the_content` filter and nothing is written to the database. The **Internal Links** screen offers an optional "Apply links to all content" tool that writes the links permanently — and a "Revert applied links" tool that removes every link the plugin added.

= Do I need Yoast SEO? =

It's recommended, because the linkable URL set and the focus-keyword phrases come from Yoast. Without it, the plugin falls back to all published posts and uses post titles as anchor phrases.

= How does the schema check work? =

Because SEO plugins output JSON-LD in the page head at render time, the plugin requests each article over HTTP and inspects the rendered HTML for `application/ld+json` blocks (and `schema.org` microdata), collecting the `@type` values it finds. Results are cached per post.

= Will it create duplicate or nested links? =

No. The linker never links inside existing links, code, scripts or (optionally) headings, matches whole words only, and caps links per phrase and per article. Re-applying links first strips any it previously added, so it is safe to run repeatedly.

== Changelog ==

= 1.1.0 =
* New: Content Distribution ("Sprinkler") — rule-based injection of shortcodes, images or HTML into targeted articles (by tag/category/keyword) at precise positions around headings and paragraphs, with device targeting, scheduling, de-duplication and limits.

= 1.0.0 =
* Initial release: image-minimum auditing, Schema.org structured-data auditing, Yoast-sitemap-driven internal linking (display-time + permanent apply/revert), and new-post inbound-link awareness.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
