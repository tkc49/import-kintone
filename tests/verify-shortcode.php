<?php
/**
 * [publish_kintone_data] ショートコードの検証.
 *
 * @package import-kintone
 */

require_once __DIR__ . '/bootstrap.php';

use publish_kintone_data\Publish_Kintone_Data;

pkd_set_field_map(
	array(
		'post_title'    => 'title',
		'custom_fields' => array(
			'title' => 'f_title',
			'price' => 'f_price',
			'stamp' => 'f_stamp',
		),
	)
);

pkd_set_http_handler(
	function () {
		return null;
	}
);

$sync = new Publish_Kintone_Data();
$sync->sync(
	array(
		'record'               => pkd_record(
			40,
			array(
				'title' => pkd_field( 'SINGLE_LINE_TEXT', 'ショートコードの記事' ),
				'price' => pkd_field( 'NUMBER', '1234567' ),
				'stamp' => pkd_field( 'NUMBER', '1757376000' ),
			)
		),
		'kintone_to_wp_status' => 'normal',
		'type'                 => 'UPDATE_RECORD',
		'app'                  => array( 'id' => '1' ),
	)
);

$target = pkd_post_by_record_id( '40' );

pkd_section( 'ショートコード' );

// ショートコードは get_the_ID() を見るので、グローバルの $post を立てておく.
global $post;
$post = get_post( $target->ID );
setup_postdata( $post );

pkd_ok( 'ショートコードの記事' === do_shortcode( '[publish_kintone_data custom_field_key="f_title"]' ), 'カスタムフィールドの値を出力する' );
pkd_ok( '1,234,567' === do_shortcode( '[publish_kintone_data custom_field_key="f_price" format="number_format"]' ), 'format="number_format" で桁区切りになる' );
pkd_ok( '2025-09-09' === do_shortcode( '[publish_kintone_data custom_field_key="f_stamp" format="Y-m-d"]' ), 'format に日付書式を渡すと整形される（実際: ' . do_shortcode( '[publish_kintone_data custom_field_key="f_stamp" format="Y-m-d"]' ) . '）' );
pkd_ok( '' === do_shortcode( '[publish_kintone_data custom_field_key="f_missing"]' ), '存在しないキーは空を返す' );
pkd_ok( false !== strpos( do_shortcode( '[publish_kintone_data]' ), 'custom_field_key' ), 'custom_field_key 未指定なら使い方を返す' );

wp_reset_postdata();

// 対象外の post_type では案内を返す.
$other_id = wp_insert_post(
	array(
		'post_type'   => 'post',
		'post_title'  => '対象外',
		'post_status' => 'draft',
	)
);
$post     = get_post( $other_id );
setup_postdata( $post );
pkd_ok( false !== strpos( do_shortcode( '[publish_kintone_data custom_field_key="f_title"]' ), 'not the post type' ), '設定外の post_type では案内文を返す' );
wp_reset_postdata();
wp_delete_post( $other_id, true );

pkd_finish();
