<?php
/**
 * テストの共通基盤.
 *
 * WordPress を実際に読み込んで動かす。kintone への通信は pre_http_request で
 * 差し替え、反映先は専用のカスタム投稿タイプに向けるので、実際の kintone にも
 * 既存記事にも触らない。
 *
 * wp-load.php はプラグインの位置から相対で探す。別の配置で動かしたいときは
 * 環境変数 WP_LOAD_PATH で上書きする（CI 用）.
 *
 * @package import-kintone
 */

// -------------------------------------------------------------- WordPress.
$pkd_wp_load = getenv( 'WP_LOAD_PATH' );
if ( ! $pkd_wp_load ) {
	$pkd_wp_load = __DIR__ . '/../../../../wp-load.php';
}
if ( ! file_exists( $pkd_wp_load ) ) {
	fwrite( STDERR, "wp-load.php が見つかりません: {$pkd_wp_load}\n" );
	exit( 1 );
}
require_once $pkd_wp_load;

/**
 * 反映先に使うカスタム投稿タイプ.
 */
const PKD_POST_TYPE = 'pkd_verify';

/**
 * ターム反映の確認に使うタクソノミー.
 */
const PKD_TAXONOMY = 'pkd_verify_tax';

// ------------------------------------------------------------ アサーション.
$GLOBALS['pkd_failures'] = 0;
$GLOBALS['pkd_passes']   = 0;

/**
 * 条件を検証する.
 *
 * @param boolean $condition .
 * @param string  $label     .
 *
 * @return void
 */
function pkd_ok( $condition, $label ) {
	if ( $condition ) {
		++$GLOBALS['pkd_passes'];
		echo "  \033[32mPASS\033[0m {$label}\n";
	} else {
		++$GLOBALS['pkd_failures'];
		echo "  \033[31mFAIL\033[0m {$label}\n";
	}
}

/**
 * 見出しを出す.
 *
 * @param string $title .
 *
 * @return void
 */
function pkd_section( $title ) {
	echo "\n{$title}\n";
}

/**
 * 集計して終了する.
 *
 * @return void
 */
function pkd_finish() {
	pkd_cleanup_posts();

	$passes   = $GLOBALS['pkd_passes'];
	$failures = $GLOBALS['pkd_failures'];

	if ( 0 === $failures ) {
		echo "\n\033[32m{$passes} 件すべて成功\033[0m\n";
		exit( 0 );
	}

	echo "\n\033[31m{$failures} 件失敗\033[0m（成功 {$passes} 件）\n";
	exit( 1 );
}

// ------------------------------------- プラグインの接続先設定（素の WP では空）.
/*
 * kintone の URL とアプリ ID は get_option() で直接読まれてリクエスト URL に使われる。
 * 未設定のままだと "https:///k/v1/..." になってスタブが解釈できないので、テスト用の
 * 値を入れる。通信自体は差し替えるので値は何でもよい。実サイトの設定を壊さないよう、
 * 終了時（致命的エラー時も含む）に必ず戻す.
 */
$GLOBALS['pkd_saved_options'] = array(
	'kintone_to_wp_kintone_url'       => get_option( 'kintone_to_wp_kintone_url' ),
	'kintone_to_wp_target_appid'      => get_option( 'kintone_to_wp_target_appid' ),
	'kintone_to_wp_reflect_post_type' => get_option( 'kintone_to_wp_reflect_post_type' ),
);
register_shutdown_function(
	function () {
		foreach ( $GLOBALS['pkd_saved_options'] as $name => $value ) {
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
update_option( 'kintone_to_wp_reflect_post_type', PKD_POST_TYPE );

// ------------------------------------------------- 実データを触らないための隔離.
register_post_type(
	PKD_POST_TYPE,
	array(
		'public'   => false,
		'supports' => array( 'title', 'editor', 'custom-fields', 'thumbnail' ),
	)
);
register_taxonomy( PKD_TAXONOMY, PKD_POST_TYPE, array( 'public' => false ) );

add_filter(
	'publish_kintone_data_reflect_post_type',
	function () {
		return PKD_POST_TYPE;
	},
	99
);

/*
 * フィールドの対応付けはテストごとに変えるので、グローバルを見て返すフィルタにする。
 * pkd_set_field_map() で差し替える.
 */
$GLOBALS['pkd_field_map'] = array();

/**
 * フィールドの対応付けを設定する.
 *
 * @param array $map post_title / post_contents / featured_image / terms / custom_fields .
 *
 * @return void
 */
function pkd_set_field_map( $map ) {
	$GLOBALS['pkd_field_map'] = wp_parse_args(
		$map,
		array(
			'post_title'     => '',
			'post_contents'  => '',
			'featured_image' => '',
			'terms'          => array(),
			'custom_fields'  => array(),
		)
	);
}
pkd_set_field_map( array() );

add_filter(
	'publish_kintone_data_kintone_field_code_for_post_title',
	function () {
		return $GLOBALS['pkd_field_map']['post_title'];
	},
	99
);
add_filter(
	'publish_kintone_data_kintone_field_code_for_post_contents',
	function () {
		return $GLOBALS['pkd_field_map']['post_contents'];
	},
	99
);
add_filter(
	'publish_kintone_data_kintone_field_code_for_featured_image',
	function () {
		return $GLOBALS['pkd_field_map']['featured_image'];
	},
	99
);
add_filter(
	'publish_kintone_data_kintone_field_code_for_terms',
	function () {
		return $GLOBALS['pkd_field_map']['terms'];
	},
	99
);
add_filter(
	'publish_kintone_data_setting_custom_fields',
	function () {
		return $GLOBALS['pkd_field_map']['custom_fields'];
	},
	99
);

// 実サイトと同じく、同期した記事は公開状態にする（post_status はプラグインが触らない）.
$pkd_publish = function ( $data ) {
	if ( ! empty( $data ) ) {
		$data['post_status'] = 'publish';
	}
	return $data;
};
add_filter( 'import_kintone_insert_post_data', $pkd_publish, 99 );
add_filter( 'import_kintone_update_post_data', $pkd_publish, 99 );

// ------------------------------------------------------------ kintone スタブ.
/*
 * pre_http_request を1本だけ登録し、実際の応答はテストが差し替えるハンドラに委ねる.
 */
$GLOBALS['pkd_http_handler'] = null;
$GLOBALS['pkd_http_log']     = array();

/**
 * kintone の応答を差し替えるハンドラを設定する.
 *
 * ハンドラは ( $url, $query, $args ) を受け取り、レスポンス配列 / WP_Error /
 * null（未対応）を返す.
 *
 * @param callable|null $handler .
 *
 * @return void
 */
function pkd_set_http_handler( $handler ) {
	$GLOBALS['pkd_http_handler'] = $handler;
	$GLOBALS['pkd_http_log']     = array();
}

/**
 * これまでに飛んだ kintone リクエストの記録を返す.
 *
 * @return array
 */
function pkd_http_log() {
	return $GLOBALS['pkd_http_log'];
}

/**
 * 指定のエンドポイントへ飛んだ回数を返す.
 *
 * @param string $endpoint 例: '/k/v1/records.json'.
 *
 * @return int
 */
function pkd_http_count( $endpoint ) {
	$count = 0;
	foreach ( $GLOBALS['pkd_http_log'] as $entry ) {
		if ( false !== strpos( $entry['url'], $endpoint ) ) {
			++$count;
		}
	}
	return $count;
}

add_filter(
	'pre_http_request',
	function ( $preempt, $args, $url ) {

		if ( false === strpos( $url, '/k/v1/' ) ) {
			return $preempt;
		}

		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || ! isset( $parts['query'] ) ) {
			return new WP_Error( 'pkd_stub', 'スタブが URL を解釈できませんでした: ' . $url );
		}
		parse_str( $parts['query'], $query );

		$GLOBALS['pkd_http_log'][] = array(
			'url'   => $url,
			'query' => $query,
		);

		if ( ! is_callable( $GLOBALS['pkd_http_handler'] ) ) {
			return new WP_Error( 'pkd_stub', 'スタブのハンドラが設定されていません: ' . $url );
		}

		$result = call_user_func( $GLOBALS['pkd_http_handler'], $url, $query, $args );

		if ( null === $result ) {
			return new WP_Error( 'pkd_stub', 'スタブが応答を用意していないリクエストです: ' . $url );
		}

		return $result;
	},
	10,
	3
);

/**
 * JSON のレスポンスを組み立てる.
 *
 * @param array $body .
 *
 * @return array
 */
function pkd_json_response( $body ) {
	return array(
		'body'     => wp_json_encode( $body ),
		'response' => array(
			'code'    => 200,
			'message' => 'OK',
		),
		'headers'  => array(),
		'cookies'  => array(),
	);
}

/**
 * 生のボディを返すレスポンスを組み立てる（ファイル取得用）.
 *
 * @param string $body .
 *
 * @return array
 */
function pkd_raw_response( $body ) {
	return array(
		'body'     => $body,
		'response' => array(
			'code'    => 200,
			'message' => 'OK',
		),
		'headers'  => array(),
		'cookies'  => array(),
	);
}

// ------------------------------------------------------------------ ヘルパ.
/**
 * kintone のフィールド値を組み立てる.
 *
 * @param string $type  .
 * @param mixed  $value .
 *
 * @return array
 */
function pkd_field( $type, $value ) {
	return array(
		'type'  => $type,
		'value' => $value,
	);
}

/**
 * kintone のレコードを組み立てる.
 *
 * @param int   $id     .
 * @param array $fields フィールドコード => pkd_field() の戻り値.
 *
 * @return array
 */
function pkd_record( $id, $fields = array() ) {
	return array_merge(
		array( '$id' => pkd_field( '__ID__', (string) $id ) ),
		$fields
	);
}

/**
 * 記事を kintone のレコード ID で引く.
 *
 * @param string $record_id .
 *
 * @return \WP_Post|null
 */
function pkd_post_by_record_id( $record_id ) {
	$posts = get_posts(
		array(
			'post_type'      => PKD_POST_TYPE,
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'meta_key'       => 'kintone_record_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'     => $record_id,          // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		)
	);

	return $posts ? $posts[0] : null;
}

/**
 * レコード ID => 投稿ステータス の一覧を返す.
 *
 * @return array
 */
function pkd_post_statuses() {
	$out = array();
	foreach ( get_posts(
		array(
			'post_type'      => PKD_POST_TYPE,
			'post_status'    => 'any',
			'posts_per_page' => -1,
		)
	) as $post ) {
		$out[ get_post_meta( $post->ID, 'kintone_record_id', true ) ] = $post->post_status;
	}
	ksort( $out );

	return $out;
}

/**
 * テスト用の記事と添付ファイルを消す.
 *
 * @return void
 */
function pkd_cleanup_posts() {
	foreach ( get_posts(
		array(
			'post_type'      => PKD_POST_TYPE,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	) as $id ) {
		foreach ( get_attached_media( '', $id ) as $attachment ) {
			wp_delete_attachment( $attachment->ID, true );
		}
		$thumbnail_id = get_post_thumbnail_id( $id );
		if ( $thumbnail_id ) {
			wp_delete_attachment( $thumbnail_id, true );
		}
		wp_delete_post( $id, true );
	}
}

/**
 * 1x1 の PNG を返す（FILE フィールドの確認用）.
 *
 * @return string
 */
function pkd_tiny_png() {
	return base64_decode( // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
	);
}

pkd_cleanup_posts();
