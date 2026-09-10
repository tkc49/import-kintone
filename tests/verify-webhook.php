<?php
/**
 * Webhook 入口・削除・save_post 入口の検証.
 *
 * @package import-kintone
 */

require_once __DIR__ . '/bootstrap.php';

use publish_kintone_data\Kintone_Utility;
use publish_kintone_data\Publish_Kintone_Data;

pkd_set_field_map(
	array(
		'post_title'    => 'title',
		'custom_fields' => array( 'title' => 'f_title' ),
	)
);

$app_code = 'APPCODE';

pkd_set_http_handler(
	function ( $url, $query ) use ( &$app_code ) {

		if ( false !== strpos( $url, '/k/v1/app.json' ) ) {
			return pkd_json_response(
				array(
					'appId' => '1',
					'code'  => $app_code,
				)
			);
		}

		if ( false !== strpos( $url, '/k/v1/record.json' ) ) {
			$id = isset( $query['id'] ) ? $query['id'] : '0';

			if ( 'fail' === $GLOBALS['pkd_record_mode'] ) {
				return new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out' );
			}
			if ( 'garbage' === $GLOBALS['pkd_record_mode'] ) {
				return pkd_raw_response( 'not json at all' );
			}

			return pkd_json_response(
				array(
					'record' => pkd_record( $id, array( 'title' => pkd_field( 'SINGLE_LINE_TEXT', 'kintone の値 ' . $id ) ) ),
				)
			);
		}

		return null;
	}
);
$GLOBALS['pkd_record_mode'] = 'ok';

/**
 * 1件だけ同期する.
 *
 * @param array $record .
 *
 * @return \WP_Post|null
 */
function pkd_sync_one( $record ) {
	$sync = new Publish_Kintone_Data();
	$sync->sync(
		array(
			'record'               => $record,
			'kintone_to_wp_status' => 'normal',
			'type'                 => 'UPDATE_RECORD',
			'app'                  => array( 'id' => '1' ),
		)
	);

	return pkd_post_by_record_id( $record['$id']['value'] );
}

// ================================================ Webhook の送信元 IP チェック.
pkd_section( 'Webhook のアクセス制御' );

pkd_ok( true === Kintone_Utility::check_kintone_ip_adress( '103.79.14.1' ), 'kintone の 103.79.14.0/24 を許可' );
pkd_ok( true === Kintone_Utility::check_kintone_ip_adress( '103.79.14.255' ), '同レンジの末尾も許可' );
pkd_ok( true === Kintone_Utility::check_kintone_ip_adress( '52.32.46.167' ), 'kintone の AWS IP を許可' );
pkd_ok( true === Kintone_Utility::check_kintone_ip_adress( '35.155.158.164' ), 'もう一方の AWS IP を許可' );
pkd_ok( true === Kintone_Utility::check_kintone_ip_adress( '192.168.1.10' ), 'プライベートアドレスを許可（ローカル検証用）' );
pkd_ok( true === Kintone_Utility::check_kintone_ip_adress( '10.1.2.3' ), '10.0.0.0/8 を許可' );
pkd_ok( true === Kintone_Utility::check_kintone_ip_adress( '172.16.0.1' ), '172.16.0.0/12 を許可' );
pkd_ok( false === Kintone_Utility::check_kintone_ip_adress( '8.8.8.8' ), '無関係なグローバル IP は拒否' );
pkd_ok( false === Kintone_Utility::check_kintone_ip_adress( '103.79.15.1' ), '隣接レンジは拒否' );

// ================================================================ レコード削除.
pkd_section( 'DELETE_RECORD' );

pkd_sync_one( pkd_record( 20, array( 'title' => pkd_field( 'SINGLE_LINE_TEXT', '消される記事' ) ) ) );
pkd_ok( null !== pkd_post_by_record_id( '20' ), '前提: 記事がある' );

$sync = new Publish_Kintone_Data();
$sync->sync(
	array(
		'type'     => 'DELETE_RECORD',
		'recordId' => '20',
	)
);
pkd_ok( null === pkd_post_by_record_id( '20' ), 'DELETE_RECORD で記事が消える' );

// アプリコード付きの ID で保存されている記事は、app.json を引いて探し直す.
$post_id = wp_insert_post(
	array(
		'post_type'   => PKD_POST_TYPE,
		'post_title'  => 'アプリコード付き',
		'post_status' => 'publish',
	)
);
update_post_meta( $post_id, 'kintone_record_id', $app_code . '-21' );

$sync->sync(
	array(
		'type'     => 'DELETE_RECORD',
		'recordId' => '21',
	)
);
pkd_ok( null === pkd_post_by_record_id( $app_code . '-21' ), 'アプリコード付き ID の記事も削除できる' );
pkd_ok( pkd_http_count( '/k/v1/app.json' ) > 0, 'app.json を引いてアプリコードを取得している' );

// 存在しないレコードの削除で落ちないこと.
$before = count( pkd_post_statuses() );
$sync->sync(
	array(
		'type'     => 'DELETE_RECORD',
		'recordId' => '9999',
	)
);
pkd_ok( $before === count( pkd_post_statuses() ), '存在しないレコードの削除で他の記事に影響しない' );

// =========================================== save_post 入口（kintone が正）.
pkd_section( 'save_post 経由の同期' );

$post = pkd_sync_one( pkd_record( 30, array( 'title' => pkd_field( 'SINGLE_LINE_TEXT', 'kintone の値 30' ) ) ) );

// WordPress 側で書き換えて保存すると、kintone から取り直して上書きされる.
wp_update_post(
	array(
		'ID'         => $post->ID,
		'post_title' => 'WordPress で書き換えた',
	)
);
pkd_ok( 'kintone の値 30' === get_post( $post->ID )->post_title, 'WP 側の編集は kintone の値で上書きされる' );
pkd_ok( pkd_http_count( '/k/v1/record.json' ) > 0, 'save_post で record.json を取り直している' );

// 取得に失敗したときは何も書き換えない（1.14.2 のガード）.
$GLOBALS['pkd_record_mode'] = 'fail';
wp_update_post(
	array(
		'ID'         => $post->ID,
		'post_title' => '通信失敗時の値',
	)
);
pkd_ok( '通信失敗時の値' === get_post( $post->ID )->post_title, '取得に失敗しても致命的エラーにならず、保存済みの内容が残る' );

// JSON として読めない応答でも同様.
$GLOBALS['pkd_record_mode'] = 'garbage';
wp_update_post(
	array(
		'ID'         => $post->ID,
		'post_title' => '壊れた応答時の値',
	)
);
pkd_ok( '壊れた応答時の値' === get_post( $post->ID )->post_title, '応答を解釈できなくてもメタを空で上書きしない' );
pkd_ok( 'f_title' !== get_post_meta( $post->ID, 'f_title', true ), 'カスタムフィールドが消えていない' );
pkd_ok( '' !== get_post_meta( $post->ID, 'f_title', true ), 'カスタムフィールドが空にされていない' );
$GLOBALS['pkd_record_mode'] = 'ok';

// kintone と紐づいていない記事は対象外.
$plain_id     = wp_insert_post(
	array(
		'post_type'   => PKD_POST_TYPE,
		'post_title'  => '紐づいていない記事',
		'post_status' => 'publish',
	)
);
$count_before = pkd_http_count( '/k/v1/record.json' );
wp_update_post(
	array(
		'ID'         => $plain_id,
		'post_title' => '編集しても同期されない',
	)
);
pkd_ok( '編集しても同期されない' === get_post( $plain_id )->post_title, 'kintone_record_id が無い記事は上書きされない' );
pkd_ok( $count_before === pkd_http_count( '/k/v1/record.json' ), 'kintone にも問い合わせない' );

pkd_finish();
