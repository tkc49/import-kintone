<?php
/**
 * Publish Kintone Data で連携している記事をバッチで更新する.
 *
 * @package import-kintone
 */

// 実行時間制限を解除.
set_time_limit( 0 );

// メモリ制限を設定.
ini_set( 'memory_limit', '2048M' );

// WordPressの環境をロード.
require_once __DIR__ . '/../../../../wp-load.php';

// クラスファイルを読み込む.
require_once __DIR__ . '/../admin/class-admin.php';

// 名前空間を使用してクラスを呼び出す.
use publish_kintone_data\Admin;

// スクリプトの開始を表示.
echo "一括更新します...\n";

try {
	// インスタンスを作成してから実行.
	$admin = new Admin();
	$admin->bulk_update();
	echo "更新処理が正常に完了しました。\n";
} catch ( Exception $e ) {
	echo 'エラーが発生しました: ' . esc_html( $e->getMessage() ) . "\n";
	exit( 1 );
}
