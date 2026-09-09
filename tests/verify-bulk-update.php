<?php
/**
 * 一括更新（mark and sweep）の検証スクリプト.
 *
 * kintone への通信は pre_http_request で差し替え、反映先も専用の
 * カスタム投稿タイプに向けるので、実際の kintone にも既存記事にも触らない。
 *
 * 実行:
 *   php tests/verify-bulk-update.php
 *
 * wp-load.php はプラグインの位置から相対で探す。別の配置で動かしたいときは
 * 環境変数 WP_LOAD_PATH で上書きする（CI 用）.
 *
 * 特に Test C は 1.14.2 で直した「取得に失敗すると記事が全部下書きのまま残る」
 * の回帰防止。チャンク処理を触るときは必ず通すこと.
 *
 * @package import-kintone
 */

$wp_load = getenv( 'WP_LOAD_PATH' );
if ( ! $wp_load ) {
	$wp_load = __DIR__ . '/../../../../wp-load.php';
}
if ( ! file_exists( $wp_load ) ) {
	fwrite( STDERR, "wp-load.php が見つかりません: {$wp_load}\n" );
	exit( 1 );
}
require_once $wp_load;

use publish_kintone_data\Admin;

// ---------------------------------------------------------------- テスト基盤.
$failures = 0;
function ok( $condition, $label ) {
	global $failures;
	if ( $condition ) {
		echo "  \033[32mPASS\033[0m {$label}\n";
	} else {
		echo "  \033[31mFAIL\033[0m {$label}\n";
		++$failures;
	}
}

// --------------------------------------- プラグインの接続先設定（素の WP では空）.
/*
 * kintone の URL とアプリ ID は get_option() で直接読まれてリクエスト URL に使われる。
 * 未設定のままだと "https:///k/v1/..." になってスタブが解釈できないので、テスト用の
 * 値を入れる。通信自体は pre_http_request で差し替えるので、値は何でもよい。
 * 実サイトの設定を壊さないよう、終了時（致命的エラー時も含む）に必ず戻す.
 */
$pkd_saved_options = array(
	'kintone_to_wp_kintone_url'  => get_option( 'kintone_to_wp_kintone_url' ),
	'kintone_to_wp_target_appid' => get_option( 'kintone_to_wp_target_appid' ),
);
register_shutdown_function(
	function () use ( $pkd_saved_options ) {
		foreach ( $pkd_saved_options as $name => $value ) {
			if ( false === $value ) {
				delete_option( $name );
			} else {
				update_option( $name, $value );
			}
		}
	}
);
update_option( 'kintone_to_wp_kintone_url', 'example.cybozu.com' );
update_option( 'kintone_to_wp_target_appid', '1' );

// ------------------------------------------------- 実データを触らないための隔離.
register_post_type( 'pkd_verify', array( 'public' => false, 'supports' => array( 'title', 'editor', 'custom-fields' ) ) );

add_filter( 'publish_kintone_data_reflect_post_type', function () { return 'pkd_verify'; }, 99 );
add_filter( 'publish_kintone_data_kintone_field_code_for_post_title', function () { return 'title'; }, 99 );
add_filter( 'publish_kintone_data_kintone_field_code_for_post_contents', function () { return ''; }, 99 );
add_filter( 'publish_kintone_data_kintone_field_code_for_featured_image', function () { return ''; }, 99 );
add_filter( 'publish_kintone_data_kintone_field_code_for_terms', function () { return array(); }, 99 );
add_filter( 'publish_kintone_data_setting_custom_fields', function () { return array( 'title' => 'verify_title' ); }, 99 );

// 実サイトと同じく、同期した記事は公開状態にする.
$publish = function ( $data ) { $data['post_status'] = 'publish'; return $data; };
add_filter( 'import_kintone_insert_post_data', $publish, 99 );
add_filter( 'import_kintone_update_post_data', $publish, 99 );

// ページングを短くして、複数ページの挙動を見る.
add_filter( 'import_kintone_bulk_update_chunk_size', function () { return 2; }, 99 );

// ------------------------------------------------------------ kintone スタブ.
$GLOBALS['stub_records'] = array();
$GLOBALS['stub_fail_after'] = null; // この件数を返したあとのページでエラーにする.
$GLOBALS['stub_calls'] = 0;
$GLOBALS['stub_single_calls'] = 0;

function make_record( $id, $title ) {
	return array(
		'$id'   => array( 'type' => '__ID__', 'value' => (string) $id ),
		'title' => array( 'type' => 'SINGLE_LINE_TEXT', 'value' => $title ),
	);
}

add_filter( 'pre_http_request', function ( $preempt, $args, $url ) {

	// 単数形 record.json = save_post 経由の1件取り直し。走ってはいけない.
	if ( false !== strpos( $url, '/k/v1/record.json' ) ) {
		++$GLOBALS['stub_single_calls'];
		return array(
			'body'     => wp_json_encode( array( 'record' => make_record( 999, 'single' ) ) ),
			'response' => array( 'code' => 200, 'message' => 'OK' ),
			'headers'  => array(),
			'cookies'  => array(),
		);
	}

	if ( false === strpos( $url, '/k/v1/records.json' ) ) {
		return $preempt;
	}

	++$GLOBALS['stub_calls'];

	$parts = wp_parse_url( $url );
	if ( ! is_array( $parts ) || ! isset( $parts['query'] ) ) {
		return new WP_Error( 'pkd_stub', 'スタブが URL を解釈できませんでした: ' . $url );
	}
	parse_str( $parts['query'], $q );
	preg_match( '/\$id > (\d+)/', $q['query'], $m );
	$last_id = isset( $m[1] ) ? (int) $m[1] : 0;

	$page = array();
	foreach ( $GLOBALS['stub_records'] as $record ) {
		if ( (int) $record['$id']['value'] > $last_id ) {
			$page[] = $record;
		}
		if ( count( $page ) >= 2 ) {
			break;
		}
	}

	// 2ページ目以降で失敗させるシナリオ.
	if ( null !== $GLOBALS['stub_fail_after'] && $last_id >= $GLOBALS['stub_fail_after'] ) {
		return new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out' );
	}

	$body = array( 'records' => $page );
	if ( isset( $q['totalCount'] ) ) {
		$body['totalCount'] = (string) count( $GLOBALS['stub_records'] );
	}

	return array(
		'body'     => wp_json_encode( $body ),
		'response' => array( 'code' => 200, 'message' => 'OK' ),
		'headers'  => array(),
		'cookies'  => array(),
	);
}, 10, 3 );

// ------------------------------------------------------------------ ヘルパ.
function verify_posts() {
	$out = array();
	foreach ( get_posts( array( 'post_type' => 'pkd_verify', 'post_status' => 'any', 'posts_per_page' => -1 ) ) as $post ) {
		$out[ get_post_meta( $post->ID, 'kintone_record_id', true ) ] = $post->post_status;
	}
	ksort( $out );
	return $out;
}

function cleanup_verify_posts() {
	foreach ( get_posts( array( 'post_type' => 'pkd_verify', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $id ) {
		wp_delete_post( $id, true );
	}
}

cleanup_verify_posts();
$real_posts_before = wp_count_posts( 'post' )->publish;
$admin             = new Admin();

// ============================================================ Test A: 正常系.
echo "\nTest A: 3件を同期する\n";
$GLOBALS['stub_records'] = array( make_record( 1, 'レコード1' ), make_record( 2, 'レコード2' ), make_record( 3, 'レコード3' ) );
ob_start();
$admin->bulk_update();
$log_a = ob_get_clean();

$state_a = verify_posts();
ok( 3 === count( $state_a ), '3件の記事ができた（実際: ' . count( $state_a ) . '件）' );
ok( array( 'publish', 'publish', 'publish' ) === array_values( $state_a ), '3件とも公開状態' );
ok( false !== strpos( $log_a, '一括更新が完了しました' ), '完了メッセージが出た' );

// ============ Test B: kintone から1件消えた -> sweep で下書きになるか.
echo "\nTest B: kintone 側でレコード2が消える\n";
$GLOBALS['stub_records'] = array( make_record( 1, 'レコード1' ), make_record( 3, 'レコード3' ) );
ob_start();
$admin->bulk_update();
$log_b = ob_get_clean();

$state_b = verify_posts();
ok( 'publish' === $state_b[1], 'レコード1 は公開のまま' );
ok( 'draft' === $state_b[2], 'kintone から消えたレコード2 は下書きになった' );
ok( 'publish' === $state_b[3], 'レコード3 は公開のまま' );

// ==== Test C: 途中で取得に失敗 -> 記事に触らないこと（1.14.2 の回帰防止）.
echo "\nTest C: 2ページ目の取得に失敗する\n";
// レコード2を復活させ、まず全件を公開状態に戻す.
$GLOBALS['stub_records'] = array( make_record( 1, 'レコード1' ), make_record( 2, 'レコード2' ), make_record( 3, 'レコード3' ) );
ob_start();
$admin->bulk_update();
ob_get_clean();
ok( array( 'publish', 'publish', 'publish' ) === array_values( verify_posts() ), '前提: 3件とも公開に戻っている' );

$GLOBALS['stub_fail_after'] = 2; // $id > 2 のページでタイムアウト.
ob_start();
$admin->bulk_update();
$log_c = ob_get_clean();
$GLOBALS['stub_fail_after'] = null;

$state_c = verify_posts();
ok( array( 'publish', 'publish', 'publish' ) === array_values( $state_c ), '取得に失敗しても記事は公開のまま（下書きにされない）' );
ok( false !== strpos( $log_c, '記事は変更していません' ), '中止メッセージが出た' );
ok( false === strpos( $log_c, '一括更新が完了しました' ), '完了とは報告していない' );

// ================================ Test D: save_post 経由の二重同期が止まるか.
echo "\nTest D: 一括更新中に save_post 経由の再取得が走らない\n";
$GLOBALS['stub_records'] = array( make_record( 1, 'レコード1' ), make_record( 2, 'レコード2' ), make_record( 3, 'レコード3' ) );
$GLOBALS['stub_calls']        = 0;
$GLOBALS['stub_single_calls'] = 0;
ob_start();
$admin->bulk_update();
ob_get_clean();
// 3件 / chunk 2件 = 2ページ + 空ページ = 3回。1件ずつ取り直していれば増える.
ok( 3 === $GLOBALS['stub_calls'], 'records.json への問い合わせは3回だけ（実際: ' . $GLOBALS['stub_calls'] . '回）' );
ok( 0 === $GLOBALS['stub_single_calls'], 'record.json（1件取り直し）は0回（実際: ' . $GLOBALS['stub_single_calls'] . '回）' );

// ======================================= Test E: 実データに触れていないこと.
echo "\nTest E: 隔離\n";
ok( $real_posts_before === wp_count_posts( 'post' )->publish, "post タイプの公開記事数が変わっていない（{$real_posts_before}件）" );

// ========================= Test F: カーソルが進まないクエリで無限ループしない.
echo "\nTest F: カーソルが進まないクエリでも止まる\n";
add_filter( 'import_kintone_change_bulk_update_query', function () { return 'order by \ asc limit 2'; }, 99 );
$GLOBALS['stub_calls'] = 0;
ob_start();
$admin->bulk_update();
$log_f = ob_get_clean();
ok( false !== strpos( $log_f, '取得位置が進みませんでした' ), '無限ループせず中止した' );
ok( $GLOBALS['stub_calls'] <= 2, 'kintone を叩き続けていない（実際: ' . $GLOBALS['stub_calls'] . '回）' );

cleanup_verify_posts();

echo "\n" . ( 0 === $failures ? "\033[32m全て成功\033[0m\n" : "\033[31m{$failures} 件失敗\033[0m\n" );
exit( $failures > 0 ? 1 : 0 );
