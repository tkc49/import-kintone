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

/*
 * 一括更新（mark and sweep）が残すもの。
 * 実行 ID のポストメタと、中断した実行状態のトランジェント.
 */
delete_post_meta_by_key( '_kintone_to_wp_bulk_update_run' );

global $wpdb;

// プレフィックスでトランジェントをまとめて消す API がコアに無いため直接消す.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_kintone_to_wp_bulk_update_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_kintone_to_wp_bulk_update_' ) . '%'
	)
);
