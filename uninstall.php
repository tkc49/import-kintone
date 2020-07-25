<?php
if ( ! defined( 'ABSPATH' ) && ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit();
}
delete_option( 'kintone_to_wp_kintone_field_code_for_post_title' );
delete_option( 'kintone_to_wp_kintone_field_code_for_terms' );
delete_option( 'kintone_to_wp_kintone_field_code_for_featured_image' );
delete_option( 'kintone_to_wp_setting_custom_fields' );
delete_option( 'kintone_to_wp_kintone_url' );
delete_option( 'kintone_to_wp_kintone_api_token' );
delete_option( 'kintone_to_wp_target_appid' );
delete_option( 'kintone_to_wp_reflect_post_type' );
delete_option( 'kintone_to_wp_kintone_app_form_data' );
