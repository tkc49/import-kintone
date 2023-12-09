=== Publish kintone data ===
Contributors: tkc49
Tags: cybozu, kintone, crm, database, custom field
Requires at least: 4.9
Tested up to: 6.4.2
Stable tag: 1.13.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

The data of kintone can be reflected on WordPress.

== Description ==

The data of kintone can be reflected on WordPress.

= What is kintone? =

It is a cloud service that can make the business applications with non-programming provided by Cybozu.

Collaborate with team members and partners via apps and workspaces.

* Information in Japanese : https://kintone.cybozu.com/jp/
* Information in English: https://www.kintone.com/

Thanks
Cover banner designed by [akari_doi](https://profiles.wordpress.org/akari_doi/)


== Installation ==

1. Upload the entire `publish-kintone-data` folder to the `/ wp-content / plugins /` directory.
2. Write an equation for the confirmatory reaction of each cation and anion based on the experimental results of Operation 3. Activate the plugin through the 'Plugins' menu in WordPress

Open the post edit screen in the WordPress administration screen, and in the text editor pane, put the short code in the place where you want to display.

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
