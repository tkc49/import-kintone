<?php
/**
 * 一括更新（mark and sweep）の検証.
 *
 * 特に Test C は 1.14.2 で直した「取得に失敗すると記事が全部下書きのまま残る」
 * の回帰防止。チャンク処理を触るときは必ず通すこと.
 *
 * @package import-kintone
 */

require_once __DIR__ . '/bootstrap.php';

use publish_kintone_data\Admin;

pkd_set_field_map(
	array(
		'post_title'    => 'title',
		'custom_fields' => array( 'title' => 'verify_title' ),
	)
);

// ページングを短くして、複数チャンクの挙動を見る.
add_filter(
	'import_kintone_bulk_update_chunk_size',
	function () {
		return 2;
	},
	99
);

$stub_records   = array();
$stub_fail_from = null;

/**
 * records.json を返すスタブ.
 */
pkd_set_http_handler(
	function ( $url, $query ) use ( &$stub_records, &$stub_fail_from ) {

		// 単数形 record.json = save_post 経由の1件取り直し。一括更新中に走ってはいけない.
		if ( false !== strpos( $url, '/k/v1/record.json' ) ) {
			return pkd_json_response( array( 'record' => pkd_record( 999, array( 'title' => pkd_field( 'SINGLE_LINE_TEXT', 'single' ) ) ) ) );
		}

		if ( false === strpos( $url, '/k/v1/records.json' ) ) {
			return null;
		}

		preg_match( '/\$id > (\d+)/', $query['query'], $m );
		$last_id = isset( $m[1] ) ? (int) $m[1] : 0;

		if ( null !== $stub_fail_from && $last_id >= $stub_fail_from ) {
			return new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out' );
		}

		$page = array();
		foreach ( $stub_records as $record ) {
			if ( (int) $record['$id']['value'] > $last_id ) {
				$page[] = $record;
			}
			if ( count( $page ) >= 2 ) {
				break;
			}
		}

		$body = array( 'records' => $page );
		if ( isset( $query['totalCount'] ) ) {
			$body['totalCount'] = (string) count( $stub_records );
		}

		return pkd_json_response( $body );
	}
);

/**
 * テスト用のレコードを作る.
 *
 * @param int $id .
 *
 * @return array
 */
function pkd_bulk_record( $id ) {
	return pkd_record( $id, array( 'title' => pkd_field( 'SINGLE_LINE_TEXT', 'レコード' . $id ) ) );
}

$real_posts_before = wp_count_posts( 'post' )->publish;
$admin             = new Admin();

// ============================================================ Test A: 正常系.
pkd_section( 'Test A: 3件を同期する' );
$stub_records = array( pkd_bulk_record( 1 ), pkd_bulk_record( 2 ), pkd_bulk_record( 3 ) );
ob_start();
$admin->bulk_update();
$log_a = ob_get_clean();

$state_a = pkd_post_statuses();
pkd_ok( 3 === count( $state_a ), '3件の記事ができた（実際: ' . count( $state_a ) . '件）' );
pkd_ok( array( 'publish', 'publish', 'publish' ) === array_values( $state_a ), '3件とも公開状態' );
pkd_ok( false !== strpos( $log_a, 'Bulk update finished' ), '完了メッセージが出た' );

// ============ Test B: kintone から1件消えた -> sweep で下書きになるか.
pkd_section( 'Test B: kintone 側でレコード2が消える' );
$stub_records = array( pkd_bulk_record( 1 ), pkd_bulk_record( 3 ) );
ob_start();
$admin->bulk_update();
ob_get_clean();

$state_b = pkd_post_statuses();
pkd_ok( 'publish' === $state_b[1], 'レコード1 は公開のまま' );
pkd_ok( 'draft' === $state_b[2], 'kintone から消えたレコード2 は下書きになった' );
pkd_ok( 'publish' === $state_b[3], 'レコード3 は公開のまま' );

// ==== Test C: 途中で取得に失敗 -> 記事に触らないこと（1.14.2 の回帰防止）.
pkd_section( 'Test C: 2ページ目の取得に失敗する' );
$stub_records = array( pkd_bulk_record( 1 ), pkd_bulk_record( 2 ), pkd_bulk_record( 3 ) );
ob_start();
$admin->bulk_update();
ob_get_clean();
pkd_ok( array( 'publish', 'publish', 'publish' ) === array_values( pkd_post_statuses() ), '前提: 3件とも公開に戻っている' );

$stub_fail_from = 2; // $id > 2 のページでタイムアウト.
ob_start();
$admin->bulk_update();
$log_c          = ob_get_clean();
$stub_fail_from = null;

pkd_ok( array( 'publish', 'publish', 'publish' ) === array_values( pkd_post_statuses() ), '取得に失敗しても記事は公開のまま（下書きにされない）' );
pkd_ok( false !== strpos( $log_c, 'No posts were changed' ), '中止メッセージが出た' );
pkd_ok( false === strpos( $log_c, 'Bulk update finished' ), '完了とは報告していない' );

// ================================ Test D: save_post 経由の二重同期が止まるか.
pkd_section( 'Test D: 一括更新中に save_post 経由の再取得が走らない' );
$stub_records = array( pkd_bulk_record( 1 ), pkd_bulk_record( 2 ), pkd_bulk_record( 3 ) );
pkd_set_http_handler( $GLOBALS['pkd_http_handler'] ); // ログをリセット.
ob_start();
$admin->bulk_update();
ob_get_clean();
pkd_ok( 3 === pkd_http_count( '/k/v1/records.json' ), 'records.json への問い合わせは3回だけ（実際: ' . pkd_http_count( '/k/v1/records.json' ) . '回）' );
pkd_ok( 0 === pkd_http_count( '/k/v1/record.json?' ), 'record.json（1件取り直し）は0回' );

// ======================================= Test E: 実データに触れていないこと.
pkd_section( 'Test E: 隔離' );
pkd_ok( $real_posts_before === wp_count_posts( 'post' )->publish, "post タイプの公開記事数が変わっていない（{$real_posts_before}件）" );

// ========================= Test F: カーソルが進まないクエリで無限ループしない.
pkd_section( 'Test F: カーソルが進まないクエリでも止まる' );
add_filter(
	'import_kintone_change_bulk_update_query',
	function () {
		return 'order by $id asc limit 2';
	},
	99
);
pkd_set_http_handler( $GLOBALS['pkd_http_handler'] );
ob_start();
$admin->bulk_update();
$log_f = ob_get_clean();
pkd_ok( false !== strpos( $log_f, 'cursor did not advance' ), '無限ループせず中止した' );
pkd_ok( pkd_http_count( '/k/v1/records.json' ) <= 2, 'kintone を叩き続けていない（実際: ' . pkd_http_count( '/k/v1/records.json' ) . '回）' );

pkd_finish();
