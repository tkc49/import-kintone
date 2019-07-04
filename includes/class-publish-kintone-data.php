<?php
/**
 * Publish Kintone Data の Classファイル
 *
 * @package Publish_Kintone_Data
 */

/**
 * Publish Kintone Data
 */
class Publish_Kintone_Data {

	/**
	 * Kintoneクラス.
	 *
	 * @var Kintone_API
	 */
	private $kintone_api;

	/**
	 * WordPressへ反映
	 *
	 * @var Sync
	 */
	private $sync;


	/**
	 * Constructor
	 */
	public function __construct() {
	}

	/**
	 * Register
	 */
	public function register() {
		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ), 1 );
	}

	/**
	 * Plugins Loaded
	 */
	public function plugins_loaded() {

		require_once KINTONE_TO_WP_PATH . '/includes/class-kintone-api.php';
		require_once KINTONE_TO_WP_PATH . '/includes/class-sync.php';
		$this->sync = new Sync();

		if ( is_admin() ) {
			require_once KINTONE_TO_WP_PATH . '/includes/class-admin.php';
			new Admin();

		}

		/**
		 * Hookの登録
		 */

		// kintoneからのWebHookの呼び出し.
		add_action( 'wp_ajax_kintone_to_wp_start', array( $this, 'kintone_to_wp_start' ) );
		add_action( 'wp_ajax_nopriv_kintone_to_wp_start', array( $this, 'kintone_to_wp_start' ) );

		// プラグインの設定画面を保存した時.
		add_action( 'save_post', array( $this, 'update_post_kintone_data' ) );
	}

	/**
	 * Kintoneデータを保存する Post Type の記事が保存された場合、
	 * Kintoneの情報を再度取得する。（kintoneの情報が上書きされる）
	 *
	 * @param integer $post_id WordPress POST ID.
	 */
	public function update_post_kintone_data( $post_id ) {

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( 'trash' === get_post_status( $post_id ) ) {
			return;
		}

		remove_action( 'save_post', array( $this, 'update_post_kintone_data' ) );

		$reflect_post_type = get_option( 'kintone_to_wp_reflect_post_type' );
		if ( get_post_type( $post_id ) !== $reflect_post_type ) {
			return;
		}

		$kintone_id = get_post_meta( $post_id, 'kintone_record_id', true );
		if ( empty( $kintone_id ) ) {
			return;
		}

		// WordPress側で更新処理をされた場合、再度 kintoneからデータを取得して反映する（kintoneの情報がつねに正しい）.
		$this->create_kintone_api_instance();
		$kintone_data                         = $this->kintone_api->get_by_id( $kintone_id );
		$kintone_data['kintone_to_wp_status'] = 'normal';
		$this->sync->main( $kintone_data, $this->kintone_api );

		add_action( 'save_post', array( $this, 'update_post_kintone_data' ) );

	}

	/**
	 * Webhookの受付口.
	 *
	 * @return  void
	 */
	public function kintone_to_wp_start() {

		$update_kintone_data_json = file_get_contents( 'php://input' );
		$update_kintone_data      = json_decode( $update_kintone_data_json, true );

		if ( 'DELETE_RECORD' === $update_kintone_data['type'] ) {

			$kintone_id = $update_kintone_data['recordId'];

		} else {

			$kintone_id = $update_kintone_data['record']['$id']['value'];

		}

		// kintoneのデータを再取得.
		$this->create_kintone_api_instance();
		$kintone_data = $this->kintone_api->get_by_id( $kintone_id );

		if ( is_wp_error( $kintone_data ) ) {
			// kintoneにデータが存在してなくて、webhookのステータスが'DELETE_RECORD'だったら、WPの記事を削除する.
			if ( 'DELETE_RECORD' === $update_kintone_data['type'] && 'GAIA_RE01' === $kintone_data->get_error_code() ) {
				$this->delete( $update_kintone_data['recordId'] );
			}
		} else {

			$kintone_data['kintone_to_wp_status'] = 'normal';
			$kintone_data                         = apply_filters( 'kintone_to_wp_kintone_data', $kintone_data );
			$this->sync->main( $kintone_data, $this->kintone_api );
		}

	}

	/**
	 * Kintone APIのインスタンスを作成する
	 *
	 * @return void
	 */
	private function create_kintone_api_instance() {
		if ( ! $this->kintone_api ) {

			$this->kintone_api = new Kintone_API( get_option( 'kintone_to_wp_kintone_url' ), get_option( 'kintone_to_wp_kintone_api_token' ), get_option( 'kintone_to_wp_target_appid' ) );

		}
	}

	/**
	 * 記事削除
	 *
	 * @param int $record_id KintoneのレコードID.
	 *
	 * @return void
	 */
	private function delete( $record_id ) {

		$args      = array(
			'post_type'   => get_option( 'kintone_to_wp_reflect_post_type' ),
			'meta_key'    => 'kintone_record_id',
			'meta_value'  => $record_id,
			'post_status' => array(
				'publish',
				'pending',
				'draft',
				'future',
				'private',
			),
		);
		$the_query = new WP_Query( $args );
		if ( $the_query->have_posts() ) {
			wp_delete_post( $the_query->post->ID );
		}

	}

}
