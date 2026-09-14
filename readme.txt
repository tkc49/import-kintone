=== Publish kintone data ===
Contributors: tkc49
Tags: cybozu, kintone, crm, database, custom field
Requires at least: 4.9
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.16.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

The data of kintone can be reflected on WordPress.

== Description ==

Publish kintone data turns a kintone app into WordPress content. Each record in
the app becomes a post, and the field values become the post title, the post
content, taxonomy terms, the featured image and custom fields, following the
mapping you set up on the settings screen.

It is aimed at the case where the business data already lives in kintone and the
website needs to show it: product catalogues, member directories, event listings,
property listings and the like. Staff keep editing records in kintone, which they
already know how to use, and the site follows.

= One-way, with kintone as the source of truth =

The sync only ever runs from kintone to WordPress. Nothing is written back.

If someone edits a synced post in the WordPress admin and saves it, the plugin
fetches that record from kintone again and overwrites the post with the kintone
values. This is deliberate: it keeps the two sides from drifting apart, and it
means kintone is always the place to make a correction.

= Three ways data reaches WordPress =

* **Webhook** — kintone notifies WordPress the moment a record is added, updated
  or deleted, and only that record is synced. This is the normal way to run it.
  The settings screen shows the URL to register in your kintone app.
* **Saving a post** — saving a synced post in the WordPress admin re-fetches that
  record, as described above.
* **Bulk update** — a button on the settings screen walks the whole app and
  reflects every record. Use it for the first import, or after changing the field
  mapping. It runs in small batches with a progress bar, and can be stopped and
  resumed. `batch/run-bulk-update.php` does the same thing from the command line,
  for cron.

A record deleted in kintone deletes its post if the webhook is in place. If the
webhook was not running at the time, the next bulk update moves the orphaned post
to draft instead of leaving it published.

= What the field values become =

* **FILE** — the file is downloaded and added to the media library. The custom
  field holds the attachment ID. Point the featured image setting at a file field
  to use it as the post thumbnail.
* **USER_SELECT** and **SUBTABLE** — stored as arrays. When a user field is mapped
  to a taxonomy, the user names become the terms.
* **CREATOR** and **MODIFIER** — split into two custom fields, `<key>_code` and
  `<key>_name`.
* **DATETIME** — stored as `Y-m-d H:i`, shifted by nine hours from UTC.
* **Anything else** — stored as text. Fields that hold several values, such as
  checkboxes and multi-select, are joined with commas.

Custom Field Suite is used to store the value when that plugin is active.

= Showing a value in a template =

Mapped custom fields can be read with `get_post_meta()` as usual, or placed in
post content with the shortcode:

`[publish_kintone_data custom_field_key="your_meta_key"]`

`format="number_format"` adds thousands separators. Any other value is treated as
a date format and passed to `date_i18n()`, so the stored value has to be a Unix
timestamp:

`[publish_kintone_data custom_field_key="price" format="number_format"]`
`[publish_kintone_data custom_field_key="opened_at" format="Y-m-d"]`

= Before you start =

* A kintone API token for the app, with permission to view records. Add permission
  to add, update and delete records as well if you want the webhook to fire.
* A post type to reflect the app into. The built-in Posts and Pages both work, and
  so does any public custom post type.
* Posts are created as drafts. Publishing them is left to the site, so that a new
  record does not appear on the front page before anyone has looked at it. See
  below for how to change that.

= For developers =

The post data is passed through `import_kintone_insert_post_data` and
`import_kintone_update_post_data` before a post is created or updated. This is
where to set `post_status` if you want records published automatically, and
returning an empty array skips the write entirely.

Every setting is read through a filter named `publish_kintone_data_*`, with the
kintone payload as the second argument, so the connected app, the target post type
and the field mapping can all be swapped per record. That is how one WordPress
site can serve several kintone apps.

= What is kintone? =

It is a cloud service that can make the business applications with non-programming provided by Cybozu.

Collaborate with team members and partners via apps and workspaces.

* Information in Japanese : https://kintone.cybozu.com/jp/
* Information in English: https://www.kintone.com/

Thanks
Cover banner designed by [akari_doi](https://profiles.wordpress.org/akari_doi/)


== Installation ==

1. Install the plugin through the Plugins screen in WordPress, or upload the
   `import-kintone` folder to `/wp-content/plugins/`.
2. Activate it through the Plugins screen.
3. Go to Settings > Publish kintone data. Enter your kintone subdomain, an API
   token for the app and the app ID, choose the post type to reflect the app into,
   and save. The field list is fetched from kintone at this point.
4. Map the kintone fields to the post title, post content, taxonomies, the featured
   image and your custom field keys, then save again.
5. Copy the webhook URL shown on the settings screen into the webhook settings of
   your kintone app, so that changes are reflected as they happen.
6. Press Bulk Update to bring the records that already exist in the app across.

To print a value inside post content, put the shortcode where you want it to
appear:

`[publish_kintone_data custom_field_key="your_meta_key"]`

== Frequently asked questions ==

= Any field it can not be associated? =

"The fields it can not be associated." in the below web page can not be associated to WordPress even if put in kinote.

https://cybozudev.zendesk.com/hc/ja/articles/201941834
(Sorry, only in Japanese)

If you have questions about WordPress to kintone, please contact the following e-mail address.

support@ht79.info

For the operation of kintone, the above contact can't handle.
Please contact Cybozu.

https://www.cybozu.com/jp/inquiry/



== Screenshots ==

1. screenshot-1.png
2. screenshot-2.png

== Changelog ==

= 1.16.1 (2026-09-10) =
* [Changed] Rewrote the plugin description. It was a single sentence and did not say which way the sync runs, what the field values turn into, or that records arrive as drafts
* [Fixed] Removed an unrelated paragraph that had been pasted into the installation steps, and replaced them with the actual setup: API token, field mapping, webhook URL and the first bulk update. The folder name given there was also the old one

= 1.16.0 (2026-09-10) =
* [Fixed] The text domain did not match the plugin slug, so translations were never loaded. WordPress.org ships language packs named after the slug (`import-kintone`), but the code declared `kintone-to-wp`. Nothing was translated before this release, so no existing translation breaks
* [Fixed] Added the direct file access check to the main plugin file and to the shortcode file. Both run code at the top level
* [Changed] The bulk update panel is now written in English, like the rest of the admin screens. Eleven of its messages were not run through a translation function at all
* [Changed] `Tested up to` is now 7.1, backed by a CI matrix that installs WordPress 7.1, 7.0.4, 6.9.7, 6.8.8 and nightly on PHP 7.4 through 8.4 and runs the test suite on each
* [Added] `Requires PHP: 7.4`. The header was missing, so WordPress.org could not warn users on an unsupported PHP version
* [Removed] `load_plugin_textdomain()` and the `Domain Path` header. WordPress loads translations for WordPress.org plugins automatically since 4.6, and the folder the header pointed to did not exist

= 1.15.1 (2026-09-09) =
* [Security] `batch/run-bulk-update.php` now refuses to run outside the CLI. The file sits under `plugins/` and was reachable over HTTP, so anyone could trigger a full kintone sync with no authentication and no nonce
* [Changed] Renamed `batch/run-update-books.php` to `batch/run-bulk-update.php`. The "books" in the old name referred to a post type this plugin does not have; the sync target is chosen with the `kintone_to_wp_reflect_post_type` setting. **If you call the old path from cron or a shell script, update it**
* [Changed] Documented why the CLI script exists. Since 1.15.0 the admin screen no longer times out, so the script is now for running a bulk update without a browser (cron and the like)

= 1.15.0 (2026-09-09) =
* [Changed] Bulk update now runs in chunks over AJAX, so it no longer times out on apps with many records. The settings screen shows the progress and lets you stop and resume
* [Changed] Bulk update no longer drafts every post before it starts. It marks each post as it syncs, and only after every kintone record has been fetched does it draft the posts that were not marked. An interrupted run now leaves the posts published instead of hiding the whole site
* [Changed] **If your site does not set `post_status` through the `import_kintone_update_post_data` filter, published posts now stay published after a bulk update.** They used to all become drafts
* [Fixed] Bulk update now drafts the posts whose kintone record was deleted. This was the long-standing @todo in the code
* [Fixed] Suspend the save_post sync with a flag instead of remove_action(). The callback comparison never matched when Admin was instantiated separately, as batch/run-update-books.php does, so bulk update re-fetched every record one by one
* [Fixed] URL-encode the query string sent to kintone
* [Added] Filters `import_kintone_bulk_update_chunk_size` (default 100) and `import_kintone_bulk_update_sweep_chunk_size` (default 100). Note that `import_kintone_change_bulk_update_query` now receives `limit 100` instead of `limit 500`

= 1.14.2 (2026-08-31) =
* [Fixed] Stop the sync instead of crashing when the kintone request fails. A WP_Error was used as an array, which caused a fatal error after the post had already been saved
* [Fixed] Do not overwrite post meta when the response cannot be parsed as a record. It used to wipe every mapped custom field, or create an empty post
* [Fixed] Leave post meta untouched for mapped fields that are missing from the record. It used to store SQL NULL, which matches neither `=` nor `NOT EXISTS` in a meta_query, so the post silently disappeared from filtered lists
* [Fixed] Store an empty string for an empty DATETIME field. It used to store 1970-01-01 09:00
* [Fixed] Fetch the kintone records before drafting the posts in bulk update. A failed request used to leave every post as a draft
* [Fixed] Re-add the save_post callback after removing it, and skip the per-post sync while bulk update drafts the posts

= 1.14.1 (2025-01-20) =
* Fixed a bug where the original title would disappear when not linked with kintone

= 1.14.0 (2024-12-26) =
* [Added] Support executing bulk update from PHP command line
* Code forma

= 1.13.1 (2023-12-09) =
* [Fixed] Fixed bulk update issue.

= 1.13.0 (2023-09-28) =
* [Changed] Change the name of the button on the admin page.
* [Added] Add page to selectable post_type.
* [Changed] Changed to draft all target articles when batch updating.

= 1.12.2 (2023-02-26) =
Fix a bug that can't update when using app code.

= 1.12.1 =
Release Date: October 16th, 2022

* [Fixed] Modified to return if there is an error when a record in kintone is deleted.


= 1.12.0 =
Release Date: October 10th, 2022

* [Added] Fillter hook 'import_kintone_update_post_data'
* [Fixed] Fixed some

= 1.11.0 =
Release Date: October 10th, 2022

* [Added] Fillter hook 'import_kintone_insert_post_data'
* [Deprecated] Fillter hook 'import_kintone_insert_post_status'
* [Deprecated] Fillter hook 'import_kintone_insert_post_author'
* [Fixed] Typo

= 1.10.0 =
Release Date: August 1th, 2022

* [Add] Support for using shorts to display data
* [Add] Support for passing data via kintone.proxy

= 1.9.7 =
Release Date: January 30th, 2022

* [Fixed] If the size of the attachment is 0 bytes, don't process the registration to WordPress.

= 1.9.6 =
Release Date: January 29th, 2022

* [Added] Added app.id to filter hook parameter of kintone_to_wp_kintone_data when clicked bulk update button.

= 1.9.5 =
Release Date: January 29th, 2022

* [Changed] Changed directory structure
* [Add] Add the following filter hooks
・publish_kintone_data_kintone_api_token
・publish_kintone_data_reflect_post_type
・publish_kintone_data_kintone_field_code_for_featured_image
・publish_kintone_data_kintone_field_code_for_post_title
・publish_kintone_data_kintone_field_code_for_post_contents
・publish_kintone_data_kintone_field_code_for_terms
・publish_kintone_data_setting_custom_fields

= 1.9.4 =
Release Date: November 25th, 2020

* [Fixed] Fixed a bug that when you set a thumbnail, saving it from draft to public increases the number of images

= 1.9.3 =
Release Date: November 25th, 2020

* [Fixed] Fixed a bug that caused tags to be removed when updating with Webhook from kintone

= 1.9.2 =
Release Date: October 9th, 2020

* [Fixed] Typo

= 1.9.1 =
Release Date: October 9th, 2020

* [Fixed] Fixed the name of import_kintone_insert_post_auhor hook

= 1.9.0 =
Release Date: October 9th, 2020

* [Added] Add hook import_kintone_insert_post_status
* [Updated] Refactoring

* 1.8.3 - Change the display logic of the taxonomy configuration field.
* 1.8.2 - Add parameter of $kintone_data to after_insert_or_update_to_post of action hook.
* 1.8.1 - Change version of Tested up.
* 1.8.0 - New action hooks: 'after_insert_or_update_to_post'.
* 1.7.4 - FIx doesn't post to wordpress when $kintone_data is empty.
* 1.7.3 - Fix can not delete post when use kintone appcode.
* 1.7.2 - Fix can not publish post when use kintone appcode.
* 1.7.1 - Fix can not save taxonomy when selected custom post type.
* 1.7.0 - Add function to Post content.
* 1.6.1 - Fixed notice & warnign error.
* 1.6.0 - Add function to Featured image.
* 1.5.1 - Fix Can not import subtable data.
* 1.5.0 - Fix Custom Taxonomy can't sync if not set category or tags.
* 1.4.0 - Add 'import_kintone_insert_post_status' filter hooks
* 1.3.0 - Addition of CFS's corresponding processing
* 1.2.9 - Changed to delete WordPress posts
* 1.2.8 - Fix
* 1.2.7 - Add processing to fetch kintone data again when saving WordPress and fix some bug
* 1.2.6 - change plugin's icon
* 1.2.5 - change plugin's icon
* 1.2.4 - add contributer
* 1.2.3 - add contributer
* 1.2.2 - fix bug
* 1.2.1 - fix typo
* 1.2.0 - New filter hooks: kintone_to_wp_kintone_data
* 1.1.0 - add tempfile
* 1.0.5 - add esc_*
* 1.0.4 - fix not working post_type
* 1.0.3 - change plugin's url
* 1.0.2 - change menu name
* 1.0.1 - add uninstall.php
* 1.0.0 - First Release

== Upgrade Notice ==

= 1.16.0 =
Translations were never loaded because the text domain did not match the plugin slug. This release fixes that, so translations from translate.wordpress.org now apply. The bulk update panel is now in English rather than Japanese.

= 1.15.1 =
The CLI script `batch/run-update-books.php` was renamed to `batch/run-bulk-update.php` and now refuses to run outside the CLI. If you invoke the old path from cron or a shell script, update it. The old path was reachable over HTTP and would start a full kintone sync without authentication.

= 1.15.0 =
Bulk update now runs in chunks and no longer drafts every post before it starts. If your site does not set `post_status` via the `import_kintone_update_post_data` filter, published posts stay published after a bulk update instead of all becoming drafts.
