<?php
/**
 * sync() のフィールド型分岐・ターム反映・アイキャッチの検証.
 *
 * update_kintone_data_to_wp_post_meta() は kintone のフィールド型で処理を分けており、
 * 過去に本番データを壊した箇所が集中している。ここは回帰防止として重要度が高い.
 *
 * @package import-kintone
 */

require_once __DIR__ . '/bootstrap.php';

use publish_kintone_data\Publish_Kintone_Data;

pkd_set_field_map(
	array(
		'post_title'     => 'title',
		'post_contents'  => 'body',
		'featured_image' => 'thumb',
		'terms'          => array( PKD_TAXONOMY => 'category' ),
		'custom_fields'  => array(
			'title'    => 'f_title',
			'text'     => 'f_text',
			'num'      => 'f_num',
			'checks'   => 'f_checks',
			'user'     => 'f_user',
			'creator'  => 'f_creator',
			'table'    => 'f_table',
			'datetime' => 'f_datetime',
			'file'     => 'f_file',
			'missing'  => 'f_missing',
		),
	)
);

// FILE の取得だけ応答すればよい.
pkd_set_http_handler(
	function ( $url ) {
		if ( false !== strpos( $url, '/k/v1/file.json' ) ) {
			return pkd_raw_response( pkd_tiny_png() );
		}
		return null;
	}
);

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

// ================================================ 投稿本体とカスタムフィールド.
pkd_section( 'フィールド型ごとのメタ保存' );

$post = pkd_sync_one(
	pkd_record(
		10,
		array(
			'title'    => pkd_field( 'SINGLE_LINE_TEXT', 'タイトル' ),
			'body'     => pkd_field( 'MULTI_LINE_TEXT', '本文です' ),
			'text'     => pkd_field( 'SINGLE_LINE_TEXT', 'ただの文字列' ),
			'num'      => pkd_field( 'NUMBER', '1234' ),
			'checks'   => pkd_field( 'CHECK_BOX', array( 'A', 'B' ) ),
			'user'     => pkd_field(
				'USER_SELECT',
				array(
					array(
						'code' => 'u1',
						'name' => '山田',
					),
				)
			),
			'creator'  => pkd_field(
				'CREATOR',
				array(
					'code' => 'admin',
					'name' => '管理者',
				)
			),
			'table'    => pkd_field(
				'SUBTABLE',
				array(
					array(
						'id'    => '1',
						'value' => array(),
					),
				)
			),
			'datetime' => pkd_field( 'DATETIME', '2026-09-09T00:30:00Z' ),
			'category' => pkd_field( 'CHECK_BOX', array( 'ニュース', 'お知らせ' ) ),
		)
	)
);

pkd_ok( $post instanceof WP_Post, '記事が作られた' );
pkd_ok( 'タイトル' === $post->post_title, 'post_title が反映された' );
pkd_ok( '本文です' === $post->post_content, 'post_content が反映された' );
pkd_ok( '10' === get_post_meta( $post->ID, 'kintone_record_id', true ), 'kintone_record_id が刻まれた' );
pkd_ok( 'ただの文字列' === get_post_meta( $post->ID, 'f_text', true ), 'SINGLE_LINE_TEXT がそのまま入る' );
pkd_ok( '1234' === get_post_meta( $post->ID, 'f_num', true ), 'NUMBER がそのまま入る' );
pkd_ok( 'A,B' === get_post_meta( $post->ID, 'f_checks', true ), 'CHECK_BOX は , 連結の文字列になる' );
pkd_ok(
	array(
		array(
			'code' => 'u1',
			'name' => '山田',
		),
	) === get_post_meta(
		$post->ID,
		'f_user',
		true
	),
	'USER_SELECT は配列のまま入る'
);
pkd_ok( 'admin' === get_post_meta( $post->ID, 'f_creator_code', true ), 'CREATOR は _code に分割される' );
pkd_ok( '管理者' === get_post_meta( $post->ID, 'f_creator_name', true ), 'CREATOR は _name に分割される' );
pkd_ok( is_array( get_post_meta( $post->ID, 'f_table', true ) ), 'SUBTABLE は配列のまま入る' );

// DATETIME は +9h して Y-m-d H:i.
pkd_ok( '2026-09-09 09:30' === get_post_meta( $post->ID, 'f_datetime', true ), 'DATETIME は +9h されて Y-m-d H:i になる（実際: ' . get_post_meta( $post->ID, 'f_datetime', true ) . '）' );

// ======================================================== 過去に壊した箇所.
pkd_section( 'データを壊さないためのガード' );

/*
 * レコードに無いフィールドはメタに触らない。以前は null を書き込んでいて
 * meta_value が SQL の NULL になり、meta_query の '=' でも 'NOT EXISTS' でも
 * 拾えず、絞り込み一覧から記事が消えていた.
 */
global $wpdb;
$missing_rows = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = 'f_missing'", $post->ID ) );
pkd_ok( '0' === (string) $missing_rows, 'レコードに無いフィールドはメタ行を作らない' );

$null_rows = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_value IS NULL", $post->ID ) );
pkd_ok( '0' === (string) $null_rows, 'meta_value が SQL NULL の行が無い' );

// 空の DATETIME に strtotime() を使うと false + 9h で 1970-01-01 09:00 になる.
$post = pkd_sync_one(
	pkd_record(
		11,
		array(
			'title'    => pkd_field( 'SINGLE_LINE_TEXT', '空の日時' ),
			'datetime' => pkd_field( 'DATETIME', '' ),
		)
	)
);
pkd_ok( '' === get_post_meta( $post->ID, 'f_datetime', true ), '空の DATETIME は空文字（1970-01-01 にならない）' );

// record が取れていないデータで既存記事を空で上書きしない.
$before = pkd_sync_one( pkd_record( 12, array( 'title' => pkd_field( 'SINGLE_LINE_TEXT', '壊されない' ) ) ) );
$sync   = new Publish_Kintone_Data();
$sync->sync(
	array(
		'type'   => 'UPDATE_RECORD',
		'record' => array(),
	)
);
$sync->sync( array( 'type' => 'UPDATE_RECORD' ) );
$after = get_post( $before->ID );
pkd_ok( '壊されない' === $after->post_title, 'record を持たないデータで既存記事が上書きされない' );
pkd_ok( 3 === count( pkd_post_statuses() ), '空記事が新規作成されていない（実際: ' . count( pkd_post_statuses() ) . '件）' );

// ==================================================================== ターム.
pkd_section( 'ターム反映' );

$post  = pkd_post_by_record_id( '10' );
$terms = wp_get_object_terms( $post->ID, PKD_TAXONOMY, array( 'fields' => 'names' ) );
sort( $terms );
pkd_ok( array( 'お知らせ', 'ニュース' ) === $terms, 'CHECK_BOX の値がタームとして付く' );

// USER_SELECT を term に割り当てると name が使われる.
pkd_set_field_map(
	array_merge(
		$GLOBALS['pkd_field_map'],
		array( 'terms' => array( PKD_TAXONOMY => 'user' ) )
	)
);
$post  = pkd_sync_one(
	pkd_record(
		13,
		array(
			'title' => pkd_field( 'SINGLE_LINE_TEXT', 'ユーザータグ' ),
			'user'  => pkd_field(
				'USER_SELECT',
				array(
					array(
						'code' => 'u1',
						'name' => '山田',
					),
				)
			),
		)
	)
);
$terms = wp_get_object_terms( $post->ID, PKD_TAXONOMY, array( 'fields' => 'names' ) );
pkd_ok( array( '山田' ) === $terms, 'USER_SELECT はユーザー名がタームになる' );

// ============================================================ FILE / アイキャッチ.
pkd_section( 'FILE 型とアイキャッチ' );

pkd_set_field_map(
	array_merge(
		$GLOBALS['pkd_field_map'],
		array( 'terms' => array() )
	)
);

$file_value = array(
	array(
		'fileKey'     => 'abc123',
		'name'        => 'sample.png',
		'contentType' => 'image/png',
		'size'        => '95',
	),
);

$post = pkd_sync_one(
	pkd_record(
		14,
		array(
			'title' => pkd_field( 'SINGLE_LINE_TEXT', '添付あり' ),
			'file'  => pkd_field( 'FILE', $file_value ),
			'thumb' => pkd_field( 'FILE', $file_value ),
		)
	)
);

$attachment_id = (int) get_post_meta( $post->ID, 'f_file', true );
pkd_ok( $attachment_id > 0, 'FILE がアタッチメント化され、メタに attachment ID が入る' );
pkd_ok( 'attachment' === get_post_type( $attachment_id ), 'attachment 投稿が実在する' );
pkd_ok( 'image/png' === get_post_mime_type( $attachment_id ), 'MIME タイプが image/png' );
pkd_ok( file_exists( get_attached_file( $attachment_id ) ), '実ファイルがアップロードディレクトリに存在する' );
pkd_ok( has_post_thumbnail( $post->ID ), 'アイキャッチが設定された' );
pkd_ok( pkd_http_count( '/k/v1/file.json' ) > 0, 'file.json からファイルを取得している' );

// 空の FILE で添付が消える.
$post = pkd_sync_one(
	pkd_record(
		14,
		array(
			'title' => pkd_field( 'SINGLE_LINE_TEXT', '添付を外す' ),
			'file'  => pkd_field( 'FILE', array() ),
			'thumb' => pkd_field( 'FILE', array() ),
		)
	)
);
pkd_ok( '' === get_post_meta( $post->ID, 'f_file', true ), 'FILE を空にするとメタが消える' );
pkd_ok( ! has_post_thumbnail( $post->ID ), 'アイキャッチも外れる' );
pkd_ok( null === get_post( $attachment_id ), '古いアタッチメントが削除されている' );

pkd_finish();
