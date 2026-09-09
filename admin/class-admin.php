<?php
/**
 * Admin class
 *
 * @package import-kintone
 */

namespace publish_kintone_data;

/**
 * Admin class
 *
 * @package import-kintone
 */
class Admin {

	/**
	 * Nonce
	 *
	 * @var string
	 */
	private $nonce = 'kintone_to_wp_';

	/**
	 * 一括更新の AJAX で使う nonce のアクション名.
	 *
	 * @var string
	 */
	const BULK_UPDATE_NONCE_ACTION = 'kintone_to_wp_bulk_update';

	/**
	 * 一括更新の実行状態を保存するトランジェントのキー接頭辞.
	 *
	 * @var string
	 */
	const BULK_UPDATE_STATE_PREFIX = 'kintone_to_wp_bulk_update_';

	/**
	 * 一括更新で同期できた記事に刻む実行 ID のメタキー.
	 *
	 * @var string
	 */
	const BULK_UPDATE_RUN_META_KEY = '_kintone_to_wp_bulk_update_run';

	/**
	 * 一括更新中など、save_post 経由の同期を止めるためのフラグ.
	 *
	 * @var boolean
	 */
	private static $suspend_post_sync = false;

	/**
	 * 設定画面の hook suffix.
	 *
	 * @var string
	 */
	private $hook_suffix = '';

	/**
	 * 実行中の一括更新の ID. 同期できた記事に刻むために使う.
	 *
	 * @var string
	 */
	private $bulk_update_run_id = '';

	/**
	 * Constructor
	 *
	 * @return void
	 */
	public function __construct() {
		// Create Admin Menu.
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'save_post', array( $this, 'update_post_kintone_data' ), 10, 3 );

		// 一括更新のチャンク実行.
		add_action( 'wp_ajax_kintone_to_wp_bulk_update_chunk', array( $this, 'bulk_update_chunk' ) );
	}

	/**
	 * 同期を止める / 再開する（save_post 経由の再帰を防ぐ）.
	 *
	 * フックを remove_action() で外さないのは、CLI のように Admin を別インスタンスで new した場合に
	 * コールバックの比較が一致せずフックが外れない。インスタンスに依存しない
	 * 静的フラグで止める。入れ子で呼ばれても戻せるよう、直前の値を返す.
	 *
	 * @param boolean $suspend 止めるなら true.
	 *
	 * @return boolean 直前の値.
	 */
	public static function suspend_post_sync( $suspend ) {

		$previous                = self::$suspend_post_sync;
		self::$suspend_post_sync = (bool) $suspend;

		return $previous;
	}
	/**
	 * Admin menu
	 *
	 * @return void
	 */
	public function admin_menu() {
		$this->hook_suffix = add_submenu_page(
			'options-general.php',
			'Publish kintone data',
			'Publish kintone data',
			'manage_options',
			'publish-kintone-data-setting',
			array(
				$this,
				'kintone_to_wp_setting',
			)
		);
	}
	/**
	 * Kintone to WP setting
	 *
	 * @return void
	 */
	public function kintone_to_wp_setting() {

		if ( ! empty( $_POST ) && check_admin_referer( $this->nonce ) ) {

			if ( isset( $_POST['get_kintone_fields'] ) ) {

				$kintone_basci_information = array();

				$kintone_basci_information['domain']    = isset( $_POST['kintone_to_wp_kintone_url'] ) ? sanitize_text_field( wp_unslash( $_POST['kintone_to_wp_kintone_url'] ) ) : '';
				$kintone_basci_information['app_id']    = isset( $_POST['kintone_to_wp_target_appid'] ) ? sanitize_text_field( wp_unslash( $_POST['kintone_to_wp_target_appid'] ) ) : '';
				$kintone_basci_information['url']       = 'https://' . $kintone_basci_information['domain'] . '/k/v1/form.json?app=' . $kintone_basci_information['app_id'];
				$kintone_basci_information['token']     = isset( $_POST['kintone_to_wp_kintone_api_token'] ) ? sanitize_text_field( wp_unslash( $_POST['kintone_to_wp_kintone_api_token'] ) ) : '';
				$kintone_basci_information['post_type'] = isset( $_POST['kintone_to_wp_reflect_post_type'] ) ? sanitize_text_field( wp_unslash( $_POST['kintone_to_wp_reflect_post_type'] ) ) : '';

				$error_flg = false;
				if ( ! $kintone_basci_information['domain'] ) {
					echo '<div class="error notice is-dismissible"><p><strong>Domain is required</strong></p></div>';
					$error_flg = true;
				} elseif ( ! $kintone_basci_information['post_type'] ) {
					echo '<div class="error notice is-dismissible"><p><strong>Post type is required</strong></p></div>';
					$error_flg = true;
				}

				if ( ! $error_flg ) {
					$kintone_form_data = Kintone_Utility::kintone_api( $kintone_basci_information['url'], $kintone_basci_information['token'] );
					if ( ! is_wp_error( $kintone_form_data ) ) {
						$this->update_kintone_basci_information( $kintone_basci_information, $kintone_form_data );
					} else {
						echo '<div class="error fade"><p><strong>setting information is incorrect</strong></p></div>';
					}
				}
			} elseif ( isset( $_POST['save'] ) ) {

				$kintone_app_fields_code_for_wp = array();
				if ( isset( $_POST['kintone_to_wp_kintone_field_code_for_post_title'] ) ) {
					$kintone_app_fields_code_for_wp['kintone_to_wp_kintone_field_code_for_post_title'] = sanitize_text_field( wp_unslash( $_POST['kintone_to_wp_kintone_field_code_for_post_title'] ) );
				}
				if ( isset( $_POST['kintone_to_wp_kintone_field_code_for_post_contents'] ) ) {
					$kintone_app_fields_code_for_wp['kintone_to_wp_kintone_field_code_for_post_contents'] = sanitize_text_field( wp_unslash( $_POST['kintone_to_wp_kintone_field_code_for_post_contents'] ) );
				}
				if ( isset( $_POST['kintone_to_wp_kintone_field_code_for_terms'] ) && is_array( $_POST['kintone_to_wp_kintone_field_code_for_terms'] ) ) {
					$kintone_app_fields_code_for_wp['kintone_to_wp_kintone_field_code_for_terms'] = array_map(
						'sanitize_text_field',
						array_map( 'wp_unslash', $_POST['kintone_to_wp_kintone_field_code_for_terms'] )
					);
				}
				if ( isset( $_POST['kintone_to_wp_kintone_field_code_for_featured_image'] ) ) {
					$kintone_app_fields_code_for_wp['kintone_to_wp_kintone_field_code_for_featured_image'] = sanitize_text_field( wp_unslash( $_POST['kintone_to_wp_kintone_field_code_for_featured_image'] ) );
				}

				if ( isset( $_POST['kintone_to_wp_setting_custom_fields'] ) && is_array( $_POST['kintone_to_wp_setting_custom_fields'] ) ) {
					$kintone_app_fields_code_for_wp['kintone_to_wp_setting_custom_fields'] = array_map(
						'sanitize_text_field',
						array_map( 'wp_unslash', $_POST['kintone_to_wp_setting_custom_fields'] )
					);
				}

				$this->update_kintone_app_fields_code_for_wp( $kintone_app_fields_code_for_wp );

			} elseif ( isset( $_POST['bulk_update'] ) ) {

				$this->render_bulk_update_panel();

			}
		}

		$wp_n = wp_nonce_field( $this->nonce );

		$kintone_url           = get_option( 'kintone_to_wp_kintone_url' );
		$api_token             = get_option( 'kintone_to_wp_kintone_api_token' );
		$target_appid          = get_option( 'kintone_to_wp_target_appid' );
		$reflect_post_type     = get_option( 'kintone_to_wp_reflect_post_type' );
		$kintone_app_form_data = get_option( 'kintone_to_wp_kintone_app_form_data' );

		echo '<div class="wrap">';

		echo '<h2>Setting Publish kintone data</h2>';
		echo '<form method="post" action="">';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $wp_n;

		echo '<table class="form-table">';
		echo '	<tr valign="top">';
		echo '		<th scope="row"><label for="add_text">kintone domain</label></th>';
		echo '		<td><input name="kintone_to_wp_kintone_url" type="text" id="kintone_to_wp_kintone_url" value="' . ( '' === $kintone_url ? '' : esc_textarea( $kintone_url ) ) . '" class="regular-text" /></td>';
		echo '	</tr>';
		echo '	<tr valign="top">';
		echo '		<th scope="row"><label for="add_text">API Token</label><br><span style="font-size:10px;">Permission: show record</span></th>';
		echo '		<td><input name="kintone_to_wp_kintone_api_token" type="text" id="kintone_to_wp_kintone_api_token" value="' . ( '' === $api_token ? '' : esc_textarea( $api_token ) ) . '" class="regular-text" /></td>';
		echo '	</tr>';
		echo '	<tr valign="top">';
		echo '		<th scope="row"><label for="add_text">Reflect kintone to post_type</label></th>';
		echo '		<td>';
		echo '			kintone APP ID:<input name="kintone_to_wp_target_appid" type="text" id="kintone_to_wp_target_appid" value="' . ( '' === $target_appid ? '' : esc_textarea( $target_appid ) ) . '" class="small-text" /> ->';
		echo '			WordPress Post Type:<select name="kintone_to_wp_reflect_post_type">';
		echo '				<option value=""></option>';
		echo '				<option ' . selected( $reflect_post_type, 'post', false ) . ' value="post">post</option>';
		echo '				<option ' . selected( $reflect_post_type, 'page', false ) . ' value="page">page</option>';
		echo $this->get_html_post_type_form_slect_option( $reflect_post_type ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '			</select>';
		echo '		</td>';
		echo '	</tr>';
		echo '	</table>';

		echo '<p class="submit"><input type="submit" name="get_kintone_fields" class="button-primary" value="Save the above settings and retrieve the field information in kintone" /></p>';

		echo '</form>';

		$disp_data        = '';
		$old_kintone_data = get_option( 'kintone_to_wp_kintone_app_form_data' );
		if ( isset( $kintone_form_data ) ) {

			if ( is_wp_error( $kintone_form_data ) ) {
				// Error.
				if ( ! empty( $old_kintone_data ) ) {
					$disp_data = $old_kintone_data;
				}
			} else {
				// Success.
				$disp_data = $kintone_form_data;
			}
		} elseif ( ! empty( $old_kintone_data ) ) {
			// Nothing.
			$disp_data = $old_kintone_data;
		}

		if ( ! empty( $disp_data ) ) {

			echo '<form method="post" action="">';

			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $wp_n;

			echo 'Please set this URL to kintone\'s WEBHOOK-><strong>' . esc_url( site_url( '/wp-admin/admin-ajax.php?action=kintone_to_wp_start' ) ) . '</strong><br><span style="font-size:10px;">Permission: post record, update record, delete record</span>';
			echo '<br/>';
			echo '<br/>';
			echo '	<table>';
			echo '	<tr valign="top">';
			echo '		<th scope="row"><label for="add_text">Select Post title</label></th>';
			echo '		<td>';
			echo '			<select name="kintone_to_wp_kintone_field_code_for_post_title">';
			echo $this->get_html_post_title_form_select_option( $disp_data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '			</select>';
			echo '		</td>';
			echo '	</tr>';
			echo '	<tr valign="top">';
			echo '		<th scope="row"><label for="add_text">Select Post contents</label></th>';
			echo '		<td>';
			echo '			<select name="kintone_to_wp_kintone_field_code_for_post_contents">';
			echo $this->get_html_post_contents_form_select_option( $disp_data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '			</select>';
			echo '		</td>';
			echo '	</tr>';
			echo '	<tr valign="top">';
			echo '		<th scope="row"><label for="add_text">Select Term</label></th>';
			echo '		<td>';
			echo $this->get_html_taxonomy_form_select( $disp_data, $reflect_post_type ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '		</td>';
			echo '	</tr>';
			echo '	<tr valign="top">';
			echo '		<th scope="row"><label for="add_text">Select Featured image</label></th>';
			echo '		<td>';
			echo '			<select name="kintone_to_wp_kintone_field_code_for_featured_image">';
			echo $this->get_html_featured_image_form_select_option( $disp_data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '			</select>';
			echo '		</td>';
			echo '	</tr>';
			echo '	<tr valign="top">';
			echo '		<th scope="row"><label for="add_text">Setting Custom Field</label></th>';
			echo '		<td>';
			echo $this->get_html_custom_field_form_input( $disp_data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '		</td>';
			echo '	</tr>';
			echo '</table>';

			echo '<p class="submit"><input type="submit" name="save" class="button-primary" value="Save the association between the Custom field and the kintone field" /></p>';
			echo '<p class="submit"><input type="submit" name="bulk_update" class="button-primary" value="Bulk Update" /></p>';
			echo '</form>';

		}

		echo '</div>';
	}
	/**
	 * 管理画面から記事を保存した時に実行する.
	 *
	 * @param int     $post_id .
	 * @param WP_Post $post .
	 * @param boolean $update .
	 */
	public function update_post_kintone_data( $post_id, $post, $update ) {

		// 一括更新など、呼び出し側がまとめて同期している最中は何もしない.
		if ( self::$suspend_post_sync ) {
			return;
		}

		// Autosave, do nothing.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		// AJAX? Not used here.
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( 'trash' === get_post_status( $post_id ) ) {
			return;
		}

		if ( ! $update ) {
			return;
		}

		$reflect_post_type = get_option( 'kintone_to_wp_reflect_post_type' );
		if ( get_post_type( $post_id ) !== $reflect_post_type ) {
			return;
		}

		$kintone_id = $this->get_kintone_id_without_appcode( get_post_meta( $post_id, 'kintone_record_id', true ) );

		if ( empty( $kintone_id ) ) {
			return;
		}

		// WordPress側で更新処理をされた場合、再度 kintoneからデータを取得して反映する（kintoneの情報が常に正しい）.
		$url        = 'https://' . get_option( 'kintone_to_wp_kintone_url' ) . '/k/v1/record.json?app=' . get_option( 'kintone_to_wp_target_appid' ) . '&id=' . $kintone_id;
		$retun_data = Kintone_Utility::kintone_api( $url, get_option( 'kintone_to_wp_kintone_api_token' ) );

		/*
		 * 取得できなかったときは何も書き換えずに終わる。
		 *
		 * kintone_api() は通信エラーと kintone のエラー応答で WP_Error を返し、
		 * 応答が JSON として読めなかったときは null を返す。以前はどちらも
		 * そのまま扱っていたため、WP_Error だと配列添字の代入で致命的エラーになり、
		 * null だと record を持たないデータが同期に渡っていた。
		 * 投稿の保存自体はこの時点で終わっているので、ここで止めれば実害はない.
		 */
		if ( is_wp_error( $retun_data ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( 'import-kintone: レコード %s の取得に失敗したため同期を中止しました。%s', $kintone_id, $retun_data->get_error_message() ) );
			return;
		}

		if ( ! is_array( $retun_data ) || empty( $retun_data['record'] ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( 'import-kintone: レコード %s の応答を解釈できなかったため同期を中止しました。', $kintone_id ) );
			return;
		}

		$retun_data['kintone_to_wp_status'] = 'normal';
		$publish_kintone_data               = new Publish_Kintone_Data();

		/*
		 * sync() の中で wp_update_post() が走るため、自分自身を止めて再帰を防ぐ。
		 * 以前はこの関数の冒頭で remove_action() したまま戻していなかったので、
		 * 同じリクエストで別の記事を保存しても同期されなくなっていた。終わったら必ず戻す.
		 */
		$suspended = self::suspend_post_sync( true );
		$publish_kintone_data->sync( $retun_data );
		self::suspend_post_sync( $suspended );
	}
	/**
	 * Bulk update.
	 *
	 * 最後まで通しで実行する版。CLI（batch/run-bulk-update.php）と、
	 * 外部から直接この関数を呼んでいる利用者のために残してある。
	 * 管理画面からは render_bulk_update_panel() 経由で AJAX のチャンク実行を使う.
	 *
	 * @return void
	 */
	public function bulk_update() {

		$state = $this->bulk_update_initial_state();

		while ( true ) {

			$state = $this->bulk_update_run_chunk( $state );

			if ( is_wp_error( $state ) ) {
				$this->bulk_update_output( $state->get_error_message(), 'error' );
				return;
			}

			$this->bulk_update_output( $state['message'] );

			if ( $state['completed'] ) {
				return;
			}
		}
	}

	/**
	 * 一括更新の初期状態を作る.
	 *
	 * @return array
	 */
	private function bulk_update_initial_state() {

		return array(
			'run_id'    => uniqid( '', true ),
			'phase'     => 'sync',
			'last_id'   => 0,
			'total'     => null,
			'processed' => 0,
			'swept'     => 0,
			'completed' => false,
			'message'   => '',
		);
	}

	/**
	 * 一括更新を1チャンクだけ進める.
	 *
	 * 「先に全記事を下書きにする」方式はやめて、mark and sweep にしてある。
	 *
	 * 以前は全記事を下書きにしてから kintone を取得していた。取得に失敗すると
	 * 記事が下書きのまま残ってサイトから消えるので、1.14.2 で「取得してから
	 * 下書きにする」順に直した。ところが一括更新をチャンクに割ると、全件を
	 * 取得し終える前に記事へ触らざるを得なくなり、同じ壊れ方が戻ってくる。
	 *
	 * そこで、同期できた記事にこの実行の ID を刻んでおき（mark）、
	 * 全件を取得しきったあとで、刻まれなかった公開記事だけを下書きにする（sweep）。
	 * 途中で失敗しても記事は公開のまま残り、同じ run_id で再開できる。
	 * kintone 側で削除されたレコードの記事が下書きになるのも sweep の効果.
	 *
	 * @param array $state 実行状態.
	 *
	 * @return array|\WP_Error 更新後の実行状態.
	 */
	public function bulk_update_run_chunk( $state ) {

		$state = wp_parse_args( (array) $state, $this->bulk_update_initial_state() );

		/*
		 * チャンク内の wp_update_post() が save_post を発火させるため、
		 * update_post_kintone_data() を止めておく。止めないとレコードを
		 * 1件ずつ取り直しにいって API 呼び出しが倍になる。
		 *
		 * remove_action() ではなく静的フラグを使うのは、CLI のように
		 * Admin を別インスタンスで new した場合に remove_action() の
		 * コールバック比較が一致せず、外れないため.
		 */
		$suspended = self::suspend_post_sync( true );

		try {
			if ( 'sync' === $state['phase'] ) {
				$state = $this->bulk_update_sync_chunk( $state );
			} elseif ( 'sweep' === $state['phase'] ) {
				$state = $this->bulk_update_sweep_chunk( $state );
			} else {
				$state = new \WP_Error( 'kintone_to_wp_bulk_update', '不明な処理段階です: ' . $state['phase'] );
			}
		} catch ( \Throwable $e ) {
			$state = new \WP_Error( 'kintone_to_wp_bulk_update', 'エラーが発生しました: ' . $e->getMessage() );
		} finally {
			self::suspend_post_sync( $suspended );
		}

		if ( is_wp_error( $state ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'import-kintone: 一括更新を中止しました。' . $state->get_error_message() );
		}

		return $state;
	}

	/**
	 * 【mark】kintone のレコードを1チャンク分取得して同期する.
	 *
	 * @param array $state 実行状態.
	 *
	 * @return array|\WP_Error
	 */
	private function bulk_update_sync_chunk( $state ) {

		/**
		 * Filters 1リクエストで取得・同期する kintone レコード数.
		 *
		 * @param int $chunk_size .
		 *
		 * @since 1.15.0
		 */
		$chunk_size = (int) apply_filters( 'import_kintone_bulk_update_chunk_size', 100 );
		$chunk_size = max( 1, min( 500, $chunk_size ) );

		$query = apply_filters(
			'import_kintone_change_bulk_update_query',
			'$id > ' . $state['last_id'] . ' order by $id asc limit ' . $chunk_size
		);

		$args = array(
			'app'   => get_option( 'kintone_to_wp_target_appid' ),
			'query' => $query,
		);

		// 進捗表示のための総件数は最初の1回だけ取る.
		if ( null === $state['total'] ) {
			$args['totalCount'] = 'true';
		}

		$url = add_query_arg( $args, 'https://' . get_option( 'kintone_to_wp_kintone_url' ) . '/k/v1/records.json' );

		$response = Kintone_Utility::kintone_api( $url, get_option( 'kintone_to_wp_kintone_api_token' ) );

		/*
		 * 取得に失敗したら記事に触らずに終わる。sweep へ進まないので、
		 * 記事は公開のまま残る.
		 */
		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'kintone_to_wp_bulk_update', 'kintone からレコードを取得できませんでした。記事は変更していません。' . $response->get_error_message() );
		}

		if ( ! is_array( $response ) || ! isset( $response['records'] ) || ! is_array( $response['records'] ) ) {
			return new \WP_Error( 'kintone_to_wp_bulk_update', 'kintone の応答を解釈できませんでした。記事は変更していません。' );
		}

		if ( null === $state['total'] && isset( $response['totalCount'] ) ) {
			$state['total'] = (int) $response['totalCount'];
		}

		if ( empty( $response['records'] ) ) {

			// 全件を取得しきった。ここで初めて記事を下書きにしてよい.
			$state['phase']   = 'sweep';
			$state['message'] = sprintf( 'kintone の %d 件を反映しました。kintone に無くなった記事を下書きにします。', $state['processed'] );

			return $state;
		}

		$cursor_before = $state['last_id'];

		/*
		 * 同期した記事に実行 ID を刻むためのフックを、このチャンクの間だけ付ける。
		 * 例外で抜けても外れるように finally で外す。付けっぱなしにすると、
		 * 同じリクエスト内の以降の同期にまで古い実行 ID が刻まれる.
		 */
		$this->bulk_update_run_id = $state['run_id'];
		add_action( 'after_insert_or_update_to_post', array( $this, 'mark_bulk_update_run' ), 10, 1 );

		try {
			foreach ( $response['records'] as $record ) {

				if ( ! isset( $record['$id']['value'] ) || '' === $record['$id']['value'] ) {
					continue;
				}

				$data = array(
					'record'               => $record,
					'kintone_to_wp_status' => 'normal',
					'type'                 => 'UPDATE_RECORD',
					'app'                  => array(
						'id' => get_option( 'kintone_to_wp_target_appid' ),
					),
				);
				$data = apply_filters( 'kintone_to_wp_kintone_data', $data );

				$publish_kintone_data = new Publish_Kintone_Data();
				$publish_kintone_data->sync( $data );

				++$state['processed'];
				$state['last_id'] = $record['$id']['value'];
			}
		} finally {
			remove_action( 'after_insert_or_update_to_post', array( $this, 'mark_bulk_update_run' ), 10 );
			$this->bulk_update_run_id = '';
		}

		/*
		 * レコードが返ってきたのにカーソルが進まなかった場合、次も同じページが
		 * 返ってきて永久に終わらない。$id を持たない応答や、
		 * import_kintone_change_bulk_update_query で $id > の条件を
		 * 消してしまった場合に起きる.
		 */
		if ( $cursor_before === $state['last_id'] ) {
			return new \WP_Error( 'kintone_to_wp_bulk_update', '取得位置が進みませんでした。処理を中止します。import_kintone_change_bulk_update_query で $id の条件を消していないか確認してください。' );
		}

		$state['message'] = null === $state['total']
			? sprintf( '%d 件を反映しました。', $state['processed'] )
			: sprintf( '%1$d / %2$d 件を反映しました。', $state['processed'], $state['total'] );

		return $state;
	}

	/**
	 * 【sweep】この実行で同期されなかった公開記事を下書きにする.
	 *
	 * 呼ばれる時点で kintone の全レコードを取得しきっているので、
	 * ここに残っている公開記事は kintone 側に無いものだけになる.
	 *
	 * @param array $state 実行状態.
	 *
	 * @return array|\WP_Error
	 */
	private function bulk_update_sweep_chunk( $state ) {

		$post_type = apply_filters( 'publish_kintone_data_reflect_post_type', get_option( 'kintone_to_wp_reflect_post_type' ), 'bulk_update' );

		/**
		 * Filters 1リクエストで下書きにする記事数.
		 *
		 * @param int $chunk_size .
		 *
		 * @since 1.15.0
		 */
		$chunk_size = (int) apply_filters( 'import_kintone_bulk_update_sweep_chunk_size', 100 );
		$chunk_size = max( 1, $chunk_size );

		$the_query = new \WP_Query(
			array(
				'post_type'              => $post_type,
				'post_status'            => 'publish',
				'posts_per_page'         => $chunk_size,
				'fields'                 => 'ids',
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_term_cache' => false,
				'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'OR',
					array(
						'key'     => self::BULK_UPDATE_RUN_META_KEY,
						'value'   => $state['run_id'],
						'compare' => '!=',
					),
					array(
						'key'     => self::BULK_UPDATE_RUN_META_KEY,
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);

		$post_ids = $the_query->posts;

		if ( empty( $post_ids ) ) {

			$state['phase']     = 'done';
			$state['completed'] = true;
			$state['message']   = sprintf( '一括更新が完了しました。%1$d 件を反映し、%2$d 件を下書きにしました。', $state['processed'], $state['swept'] );

			return $state;
		}

		$drafted = 0;
		foreach ( $post_ids as $post_id ) {
			$result = wp_update_post(
				array(
					'ID'          => $post_id,
					'post_status' => 'draft',
				),
				true
			);

			if ( is_wp_error( $result ) ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( sprintf( 'import-kintone: 記事 %1$d を下書きにできませんでした。%2$s', $post_id, $result->get_error_message() ) );
				continue;
			}

			++$drafted;
		}

		/*
		 * 1件も下書きにできなかった場合、次の問い合わせでも同じ記事が返ってくる。
		 * そのまま返すと呼び出し側が永久に同じチャンクを繰り返すので、ここで止める.
		 */
		if ( 0 === $drafted ) {
			return new \WP_Error( 'kintone_to_wp_bulk_update', '記事を下書きにできませんでした。処理を中止します。' );
		}

		$state['swept']  += $drafted;
		$state['message'] = sprintf( '%d 件を下書きにしました。', $state['swept'] );

		return $state;
	}

	/**
	 * 【mark】同期できた記事に実行 ID を刻む.
	 *
	 * Publish_Kintone_Data::sync() の after_insert_or_update_to_post から呼ばれる.
	 *
	 * @param int $post_id .
	 *
	 * @return void
	 */
	public function mark_bulk_update_run( $post_id ) {

		if ( empty( $post_id ) || is_wp_error( $post_id ) || empty( $this->bulk_update_run_id ) ) {
			return;
		}

		update_post_meta( $post_id, self::BULK_UPDATE_RUN_META_KEY, $this->bulk_update_run_id );
	}

	/**
	 * 一括更新の経過を出力する.
	 *
	 * CLI からも呼ばれるので、その場合は素のテキストで出す.
	 *
	 * @param string $message .
	 * @param string $type    info または error.
	 *
	 * @return void
	 */
	private function bulk_update_output( $message, $type = 'info' ) {

		if ( '' === $message ) {
			return;
		}

		if ( ( defined( 'WP_CLI' ) && WP_CLI ) || 'cli' === php_sapi_name() ) {
			echo esc_html( $message ) . "\n";
			return;
		}

		$class = ( 'error' === $type ) ? 'notice notice-error' : 'notice notice-success';
		echo '<div class="' . esc_attr( $class ) . '"><p><strong>' . esc_html( $message ) . '</strong></p></div>';
	}

	/**
	 * 一括更新の進行状況パネルを出力する.
	 *
	 * 実際の処理は bulk_update_chunk() へ AJAX で投げる.
	 *
	 * @return void
	 */
	private function render_bulk_update_panel() {

		wp_enqueue_style( 'kintone-to-wp-bulk-update' );
		wp_enqueue_script( 'kintone-to-wp-bulk-update' );

		?>
		<div class="kintone-to-wp-bulk-update" id="kintone-to-wp-bulk-update">
			<h3><?php esc_html_e( 'Bulk Update', 'kintone-to-wp' ); ?></h3>
			<p class="description">
				<?php esc_html_e( '処理が終わるまでこの画面を開いたままにしてください。途中で閉じても記事は壊れません。', 'kintone-to-wp' ); ?>
			</p>
			<div class="kintone-to-wp-bulk-update__track">
				<div class="kintone-to-wp-bulk-update__bar" data-role="bar"></div>
			</div>
			<p class="kintone-to-wp-bulk-update__status" data-role="status"></p>
			<p>
				<button type="button" class="button" data-role="stop"><?php esc_html_e( '中止', 'kintone-to-wp' ); ?></button>
				<button type="button" class="button button-primary" data-role="retry" hidden><?php esc_html_e( '再開', 'kintone-to-wp' ); ?></button>
			</p>
		</div>
		<?php
	}

	/**
	 * 管理画面のアセットを登録する.
	 *
	 * @param string $hook_suffix .
	 *
	 * @return void
	 */
	public function admin_enqueue_scripts( $hook_suffix ) {

		if ( $hook_suffix !== $this->hook_suffix ) {
			return;
		}

		wp_register_style(
			'kintone-to-wp-bulk-update',
			KINTONE_TO_WP_URL . '/assets/css/bulk-update.css',
			array(),
			KINTONE_TO_WP_VERSION
		);

		wp_register_script(
			'kintone-to-wp-bulk-update',
			KINTONE_TO_WP_URL . '/assets/js/bulk-update.js',
			array( 'jquery' ),
			KINTONE_TO_WP_VERSION,
			true
		);

		wp_localize_script(
			'kintone-to-wp-bulk-update',
			'kintoneToWpBulkUpdate',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::BULK_UPDATE_NONCE_ACTION ),
				'i18n'    => array(
					'starting'     => __( '処理を開始しています...', 'kintone-to-wp' ),
					'stopped'      => __( '中止しました。「再開」で続きから実行できます。', 'kintone-to-wp' ),
					'networkError' => __( '通信エラーが発生しました。「再開」で続きから実行できます。', 'kintone-to-wp' ),
					'unknownError' => __( '不明なエラーが発生しました。', 'kintone-to-wp' ),
					'sweeping'     => __( 'kintone に無くなった記事を下書きにしています...', 'kintone-to-wp' ),
				),
			)
		);
	}

	/**
	 * AJAX: 一括更新を1チャンク進める.
	 *
	 * @return void
	 */
	public function bulk_update_chunk() {

		if ( ! check_ajax_referer( self::BULK_UPDATE_NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'セキュリティチェックに失敗しました。画面を再読み込みしてください。', 'kintone-to-wp' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( '権限がありません。', 'kintone-to-wp' ) ) );
		}

		$run_id = isset( $_POST['run_id'] ) ? sanitize_text_field( wp_unslash( $_POST['run_id'] ) ) : '';

		if ( '' === $run_id ) {
			$state = $this->bulk_update_initial_state();
		} else {

			if ( ! preg_match( '/\A[0-9a-f.]{1,32}\z/', $run_id ) ) {
				wp_send_json_error( array( 'message' => __( '実行 ID が不正です。', 'kintone-to-wp' ) ) );
			}

			$state = get_transient( self::BULK_UPDATE_STATE_PREFIX . $run_id );

			if ( ! is_array( $state ) ) {
				wp_send_json_error(
					array(
						'message' => __( '実行状態が見つかりませんでした（時間切れの可能性があります）。記事は変更していません。最初からやり直してください。', 'kintone-to-wp' ),
					)
				);
			}
		}

		/*
		 * Kintone_Utility::kintone_api() はエラー時に HTML を echo する。
		 * そのまま流すと JSON の前にゴミが混ざって応答が壊れるので捨てる。
		 * 内容は WP_Error 側に入っているため、失われる情報はない.
		 */
		$before = $state;
		ob_start();
		$state        = $this->bulk_update_run_chunk( $state );
		$stray_output = ob_get_clean();

		if ( '' !== trim( (string) $stray_output ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'import-kintone: 一括更新中の想定外の出力を破棄しました。' . wp_strip_all_tags( $stray_output ) );
		}

		if ( is_wp_error( $state ) ) {

			// 途中まで進んだ状態は残しておき、「再開」で続きから実行できるようにする.
			set_transient( self::BULK_UPDATE_STATE_PREFIX . $before['run_id'], $before, DAY_IN_SECONDS );

			wp_send_json_error(
				array(
					'message' => $state->get_error_message(),
					'run_id'  => $before['run_id'],
				)
			);
		}

		if ( $state['completed'] ) {
			delete_transient( self::BULK_UPDATE_STATE_PREFIX . $state['run_id'] );
		} else {
			set_transient( self::BULK_UPDATE_STATE_PREFIX . $state['run_id'], $state, DAY_IN_SECONDS );
		}

		wp_send_json_success(
			array(
				'run_id'    => $state['run_id'],
				'phase'     => $state['phase'],
				'processed' => $state['processed'],
				'swept'     => $state['swept'],
				'total'     => $state['total'],
				'completed' => $state['completed'],
				'message'   => $state['message'],
			)
		);
	}
	/**
	 * Update kintone app fields code for wp
	 *
	 * @param array $kintone_app_fields_code_for_wp .
	 *
	 * @return void
	 */
	private function update_kintone_app_fields_code_for_wp( $kintone_app_fields_code_for_wp ) {

		if ( empty( $kintone_app_fields_code_for_wp['kintone_to_wp_kintone_field_code_for_post_title'] ) ) {
			delete_option( 'kintone_to_wp_kintone_field_code_for_post_title' );
		} else {
			update_option( 'kintone_to_wp_kintone_field_code_for_post_title', $kintone_app_fields_code_for_wp['kintone_to_wp_kintone_field_code_for_post_title'] );
		}

		if ( empty( $kintone_app_fields_code_for_wp['kintone_to_wp_kintone_field_code_for_post_contents'] ) ) {
			delete_option( 'kintone_to_wp_kintone_field_code_for_post_contents' );
		} else {
			update_option( 'kintone_to_wp_kintone_field_code_for_post_contents', $kintone_app_fields_code_for_wp['kintone_to_wp_kintone_field_code_for_post_contents'] );
		}

		if ( empty( $kintone_app_fields_code_for_wp['kintone_to_wp_kintone_field_code_for_terms'] ) && empty( $kintone_app_fields_code_for_wp['kintone_to_wp_kintone_field_code_for_terms'] ) ) {
			delete_option( 'kintone_to_wp_kintone_field_code_for_terms' );
		} else {
			update_option( 'kintone_to_wp_kintone_field_code_for_terms', $kintone_app_fields_code_for_wp['kintone_to_wp_kintone_field_code_for_terms'] );
		}

		if ( empty( $kintone_app_fields_code_for_wp['kintone_to_wp_setting_custom_fields'] ) ) {
			delete_option( 'kintone_to_wp_setting_custom_fields' );
		} else {
			update_option( 'kintone_to_wp_setting_custom_fields', $kintone_app_fields_code_for_wp['kintone_to_wp_setting_custom_fields'] );
		}

		if ( empty( $kintone_app_fields_code_for_wp['kintone_to_wp_kintone_field_code_for_featured_image'] ) ) {
			delete_option( 'kintone_to_wp_kintone_field_code_for_featured_image' );
		} else {
			update_option( 'kintone_to_wp_kintone_field_code_for_featured_image', $kintone_app_fields_code_for_wp['kintone_to_wp_kintone_field_code_for_featured_image'] );
		}
	}

	/**
	 * Update kintone basci information
	 *
	 * @param array $kintone_basci_information .
	 * @param array $kintone_form_data .
	 *
	 * @return void
	 */
	private function update_kintone_basci_information( $kintone_basci_information, $kintone_form_data ) {

		if ( empty( $kintone_basci_information['url'] ) ) {
			delete_option( 'kintone_to_wp_kintone_url' );
		} else {
			update_option( 'kintone_to_wp_kintone_url', $kintone_basci_information['domain'] );
		}

		if ( empty( $kintone_basci_information['token'] ) ) {
			delete_option( 'kintone_to_wp_kintone_api_token' );
		} else {
			update_option( 'kintone_to_wp_kintone_api_token', $kintone_basci_information['token'] );
		}

		if ( empty( $kintone_basci_information['app_id'] ) ) {
			delete_option( 'kintone_to_wp_target_appid' );
		} else {
			update_option( 'kintone_to_wp_target_appid', $kintone_basci_information['app_id'] );
		}

		if ( empty( $kintone_basci_information['post_type'] ) ) {
			delete_option( 'kintone_to_wp_reflect_post_type' );
		} else {
			update_option( 'kintone_to_wp_reflect_post_type', $kintone_basci_information['post_type'] );
		}

		if ( ! is_wp_error( $kintone_form_data ) ) {

			if ( empty( $kintone_form_data ) ) {
				delete_option( 'kintone_to_wp_kintone_app_form_data' );
			} else {
				update_option( 'kintone_to_wp_kintone_app_form_data', $kintone_form_data );
			}

			echo '<div class="updated notice is-dismissible"><p><strong>Success</strong></p></div>';
		}
	}

	/**
	 * Get html custom field form input
	 *
	 * @param array $kintone_app_form_data .
	 *
	 * @return string
	 */
	private function get_html_custom_field_form_input( $kintone_app_form_data ) {

		$html_setting_custom_fields  = '';
		$html_setting_custom_fields .= '<table>';
		$setting_custom_fields       = get_option( 'kintone_to_wp_setting_custom_fields' );

		foreach ( $kintone_app_form_data['properties'] as $kintone_form_value ) {
			$input_val = '';

			if ( is_array( $setting_custom_fields ) ) {
				foreach ( $setting_custom_fields as $key => $custom_form_value ) {
					if ( array_key_exists( 'code', $kintone_form_value ) ) {
						if ( $kintone_form_value['code'] == $key ) {
							$input_val = $custom_form_value;
						}
					}
				}
			}

			if ( array_key_exists( 'code', $kintone_form_value ) ) {
				$html_setting_custom_fields .= '<tr>';
				if ( 'RECORD_NUMBER' === $kintone_form_value['type'] ) {

					$html_setting_custom_fields .= '<th>' . esc_html( $kintone_form_value['label'] ) . '(' . esc_html( $kintone_form_value['code'] ) . ')</th><td><input readonly="readonly" type="text" name="kintone_to_wp_setting_custom_fields[' . esc_attr( $kintone_form_value['code'] ) . ']" value="kintone_record_id" class="regular-text" /></td>';

				} else {

					$label = '';
					if ( isset( $kintone_form_value['label'] ) ) {
						$label = $kintone_form_value['label'];
					}

					$html_setting_custom_fields .= '<th>' . esc_html( $label ) . '(' . esc_html( $kintone_form_value['code'] ) . ')</th><td><input type="text" name="kintone_to_wp_setting_custom_fields[' . esc_attr( $kintone_form_value['code'] ) . ']" value="' . esc_attr( $input_val ) . '" class="regular-text" /></td>';

				}
				$html_setting_custom_fields .= '</tr>';
			}
		}
		$html_setting_custom_fields .= '</table>';

		return $html_setting_custom_fields;
	}

	/**
	 * Get html post type form slect option
	 *
	 * @param string $reflect_post_type .
	 *
	 * @return string
	 */
	private function get_html_post_type_form_slect_option( $reflect_post_type ) {

		$args       = array(
			'public'   => true,
			'_builtin' => false,
		);
		$post_types = get_post_types( $args, 'names' );

		$html_option = '';
		foreach ( $post_types as $value ) {
			$html_option .= '<option ' . selected( $reflect_post_type, $value, false ) . ' value="' . esc_attr( $value ) . '">' . esc_html( $value ) . '</option>';
		}

		return $html_option;
	}

	/**
	 * Get html featured image form select option
	 *
	 * @param array $kintone_app_form_data .
	 *
	 * @return string
	 */
	private function get_html_featured_image_form_select_option( $kintone_app_form_data ) {

		$html_select_featured_image            = '';
		$kintone_field_code_for_featured_image = get_option( 'kintone_to_wp_kintone_field_code_for_featured_image' );

		$html_select_featured_image .= '<option ' . selected( '', $kintone_field_code_for_featured_image, false ) . ' value=""></option>';

		foreach ( $kintone_app_form_data['properties'] as $kintone_form_value ) {

			if ( array_key_exists( 'code', $kintone_form_value ) ) {

				$label = '';
				if ( isset( $kintone_form_value['label'] ) ) {
					$label = $kintone_form_value['label'];
				}

				$html_select_featured_image .= '<option ' . selected( $kintone_form_value['code'], $kintone_field_code_for_featured_image, false ) . ' value="' . esc_attr( $kintone_form_value['code'] ) . '">' . esc_html( $label ) . '(' . esc_html( $kintone_form_value['code'] ) . ')</option>';
			}
		}

		return $html_select_featured_image;
	}

	/**
	 * Get html post title form select option
	 *
	 * @param array $kintone_app_form_data .
	 *
	 * @return string
	 */
	private function get_html_post_title_form_select_option( $kintone_app_form_data ) {

		$html_select_post_title            = '';
		$kintone_field_code_for_post_title = get_option( 'kintone_to_wp_kintone_field_code_for_post_title' );

		$html_select_post_title .= '<option ' . selected( '', $kintone_field_code_for_post_title, false ) . ' value=""></option>';

		foreach ( $kintone_app_form_data['properties'] as $kintone_form_value ) {

			if ( array_key_exists( 'code', $kintone_form_value ) ) {

				$label = '';
				if ( isset( $kintone_form_value['label'] ) ) {
					$label = $kintone_form_value['label'];
				}

				$html_select_post_title .= '<option ' . selected( $kintone_form_value['code'], $kintone_field_code_for_post_title, false ) . ' value="' . esc_attr( $kintone_form_value['code'] ) . '">' . esc_html( $label ) . '(' . esc_html( $kintone_form_value['code'] ) . ')</option>';
			}
		}

		return $html_select_post_title;
	}

	/**
	 * Get html post contents form select option
	 *
	 * @param array $kintone_app_form_data .
	 *
	 * @return string
	 */
	private function get_html_post_contents_form_select_option( $kintone_app_form_data ) {
		$html_select_post_contents            = '';
		$kintone_field_code_for_post_contents = get_option( 'kintone_to_wp_kintone_field_code_for_post_contents' );

		$html_select_post_contents .= '<option ' . selected( '', $kintone_field_code_for_post_contents, false ) . ' value=""></option>';

		foreach ( $kintone_app_form_data['properties'] as $kintone_form_value ) {

			if ( array_key_exists( 'code', $kintone_form_value ) ) {

				$label = '';
				if ( isset( $kintone_form_value['label'] ) ) {
					$label = $kintone_form_value['label'];
				}

				$html_select_post_contents .= '<option ' . selected( $kintone_form_value['code'], $kintone_field_code_for_post_contents, false ) . ' value="' . esc_attr( $kintone_form_value['code'] ) . '">' . esc_html( $label ) . '(' . esc_html( $kintone_form_value['code'] ) . ')</option>';
			}
		}

		return $html_select_post_contents;
	}


	/**
	 * Get html taxonomy form select
	 *
	 * @param array  $kintone_app_form_data .
	 * @param string $reflect_post_type .
	 *
	 * @return string
	 */
	private function get_html_taxonomy_form_select( $kintone_app_form_data, $reflect_post_type ) {

		// Category.
		$terms = get_taxonomies(
			array(),
			'objects'
		);

		$html_select_term = '';

		foreach ( $terms as $key => $term ) {

			if ( in_array( $reflect_post_type, $term->object_type ) ) {

				$html_select_term            .= $term->label . '-><select name="kintone_to_wp_kintone_field_code_for_terms[' . $term->name . ']">';
				$kintone_field_code_for_terms = get_option( 'kintone_to_wp_kintone_field_code_for_terms' );

				$html_select_term .= '<option value=""></option>';

				foreach ( $kintone_app_form_data['properties'] as $kintone_form_value ) {

					$input_val = '';

					if ( is_array( $kintone_field_code_for_terms ) ) {
						foreach ( $kintone_field_code_for_terms as $key => $kintone_field_code_for_term ) {
							if ( $term->name == $key ) {
								$input_val = $kintone_field_code_for_term;
							}
						}
					}

					if ( array_key_exists( 'code', $kintone_form_value ) ) {
						$html_select_term .= '<option ' . selected( $kintone_form_value['code'], $input_val, false ) . ' value="' . esc_attr( $kintone_form_value['code'] ) . '">' . esc_html( $kintone_form_value['label'] ) . '(' . esc_html( $kintone_form_value['code'] ) . ')</option>';
					}
				}

				$html_select_term .= '</select><br/>';
			}
		}

		return $html_select_term;
	}

	/**
	 * Get kintone id without appcode
	 *
	 * @param string $id .
	 *
	 * @return string
	 */
	private function get_kintone_id_without_appcode( $id ) {

		if ( empty( $id ) ) {
			return $id;
		}

		$id = explode( '-', $id );
		if ( 1 !== count( $id ) ) {
			$id = $id[1];
		} else {
			$id = $id[0];
		}

		return $id;
	}
}
