<?php
/**
 * 管理ページ
 *
 * @package Publish_Kintone_Data
 */

/**
 * Admin
 */
class Admin {

	/**
	 * Nonce
	 *
	 * @var string
	 */
	private $nonce = 'kintone_to_wp_';

	/**
	 * WordPressへ反映
	 *
	 * @var Sync
	 */
	private $sync;

	/**
	 * Kintoneに接続
	 *
	 * @var Kintone_API
	 */
	private $kintone_api;

	/**
	 * Admin constructor.
	 */
	public function __construct() {

		require_once KINTONE_TO_WP_PATH . '/includes/class-kintone-api.php';
		require_once KINTONE_TO_WP_PATH . '/includes/class-sync.php';
		$this->sync = new Sync();

		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'import_kintone_admin_init' ) );
	}

	/**
	 * 管理画面表示時に発火する
	 *
	 * @return void
	 */
	public function import_kintone_admin_init() {
		/* スタイルシートを登録 */
		wp_register_style(
			'import-kintone-admin-style',
			KINTONE_TO_WP_URL . '/css/style.css',
			array(),
			date( 'YmdGis', filemtime( KINTONE_TO_WP_PATH . '/css/style.css' ) )
		);

		// jQuery.
		wp_register_script( 'jQuery' );

		// Vue.js.
		wp_register_script(
			'vue-js',
			KINTONE_TO_WP_URL . '/vendor/vue/vue.min.js',
			array(),
			date( 'YmdGis', filemtime( KINTONE_TO_WP_PATH . '/vendor/vue/vue.min.js' ) ),
			true
		);
		// import-kintone-admin.js.
		wp_register_script(
			'import-kintone-admin-js',
			KINTONE_TO_WP_URL . '/js/import-kintone-admin.js',
			array( 'jQuery', 'vue-js' ),
			date( 'YmdGis', filemtime( KINTONE_TO_WP_PATH . '/js/import-kintone-admin.js' ) ),
			true
		);

	}

	/**
	 * 管理画面にメニュー作成
	 */
	public function admin_menu() {
		$page = add_submenu_page(
			'options-general.php',
			'Publish kintone data',
			'Publish kintone data',
			'manage_options',
			'publish-kintone-data-setting',
			array( $this, 'kintone_to_wp_setting' )
		);
		/* 登録した $page ハンドルをを使ってスタイルシートの読み込みをフック */
		add_action( 'admin_print_styles-' . $page, array( $this, 'import_kintone_admin_styles' ) );
		add_action( 'admin_print_scripts-' . $page, array( $this, 'import_kintone_admin_js' ) );
	}

	/**
	 * プラグイン管理画面のCSSを読み込み
	 *
	 * @return void
	 */
	public function import_kintone_admin_styles() {
		/*
		 * プラグイン管理画面のみで呼び出される。スタイルシートをここでエンキュー
		 */
		wp_enqueue_style(
			'import-kintone-admin-style'
		);
	}

	/**
	 * プラグイン管理画面のJSを読み込み
	 *
	 * @return void
	 */
	public function import_kintone_admin_js() {
		/*
		 * プラグイン管理画面のみで呼び出される。
		 */
		wp_enqueue_script(
			'vue-js'
		);
		wp_enqueue_script(
			'import-kintone-admin-js'
		);
		echo 'hoge';
	}


	/**
	 * プラグインの設定ページ
	 */
	public function kintone_to_wp_setting() {

		if ( ! empty( $_POST ) && check_admin_referer( $this->nonce ) ) {

			if ( isset( $_POST['get_kintone_fields'] ) ) {

				/**
				 * Get kintone fields ボタンを押したときの処理
				 * kintoneに接続してフィールドコード取得し保存する
				 */
				$kintone_basci_information = array();

				if ( isset( $_POST['kintone_to_wp_kintone_url'] ) ) {
					$kintone_basci_information['domain'] = trim( sanitize_text_field( wp_unslash( $_POST['kintone_to_wp_kintone_url'] ) ) );
				}
				if ( isset( $_POST['kintone_to_wp_target_appid'] ) ) {
					$kintone_basci_information['app_id'] = trim( sanitize_text_field( wp_unslash( $_POST['kintone_to_wp_target_appid'] ) ) );
				}
				if ( isset( $_POST['kintone_to_wp_kintone_api_token'] ) ) {
					$kintone_basci_information['token'] = trim( sanitize_text_field( wp_unslash( $_POST['kintone_to_wp_kintone_api_token'] ) ) );
				}
				if ( isset( $_POST['kintone_to_wp_reflect_post_type'] ) ) {
					$kintone_basci_information['post_type'] = trim( sanitize_text_field( wp_unslash( $_POST['kintone_to_wp_reflect_post_type'] ) ) );
				}

				$this->kintone_api = new Kintone_API( $kintone_basci_information['domain'], $kintone_basci_information['token'], $kintone_basci_information['app_id'] );

				$error_flg = false;
				if ( ! $kintone_basci_information['domain'] ) {
					echo '<div class="error notice is-dismissible"><p><strong>Domain is required</strong></p></div>';
					$error_flg = true;
				} elseif ( ! $kintone_basci_information['post_type'] ) {
					echo '<div class="error notice is-dismissible"><p><strong>Post type is required</strong></p></div>';
					$error_flg = true;
				}

				if ( ! $error_flg ) {
					$kintone_form_data = $this->kintone_api->get_kintone_form();
					if ( ! is_wp_error( $kintone_form_data ) ) {
						$this->update_kintone_basci_information( $kintone_basci_information, $kintone_form_data );
					} else {
						echo '<div class="error fade"><p><strong>setting information is incorrect</strong></p></div>';
					}
				}
			} elseif ( isset( $_POST['save'] ) ) {

				/**
				 * 保存
				 */
				$kintone_app_fields_code_for_wp = array();
				if ( isset( $_POST['kintone_to_wp_kintone_field_code_for_post_title'] ) ) {
					$kintone_app_fields_code_for_wp['kintone_to_wp_kintone_field_code_for_post_title'] = sanitize_text_field( wp_unslash( $_POST['kintone_to_wp_kintone_field_code_for_post_title'] ) );
				}
				if ( isset( $_POST['kintone_to_wp_kintone_field_code_for_terms'] ) && is_array( $_POST['kintone_to_wp_kintone_field_code_for_terms'] ) ) {
					$kintone_app_fields_code_for_wp['kintone_to_wp_kintone_field_code_for_terms'] = array_map( 'sanitize_text_field', wp_unslash( $_POST['kintone_to_wp_kintone_field_code_for_terms'] ) );
				}
				if ( isset( $_POST['kintone_to_wp_setting_custom_fields'] ) && is_array( $_POST['kintone_to_wp_setting_custom_fields'] ) ) {
					$kintone_app_fields_code_for_wp['kintone_to_wp_setting_custom_fields'] = array_map( 'sanitize_text_field', wp_unslash( $_POST['kintone_to_wp_setting_custom_fields'] ) );
				}

				$this->update_kintone_app_fields_code_for_wp( $kintone_app_fields_code_for_wp );

			} elseif ( isset( $_POST['bulk_update'] ) ) {

				/**
				 * 一括アップデート
				 */
				$this->bulk_update();
			}
		}

		$kintone_url       = get_option( 'kintone_to_wp_kintone_url' );
		$api_token         = get_option( 'kintone_to_wp_kintone_api_token' );
		$target_appid      = get_option( 'kintone_to_wp_target_appid' );
		$reflect_post_type = get_option( 'kintone_to_wp_reflect_post_type' );

		echo '<div class="wrap">';

		echo '<h2>Setting Publish kintone data</h2>';
		echo '<form method="post" action="">';
		wp_nonce_field( $this->nonce );

		echo '<table class="form-table">';
		echo '	<tr valign="top">';
		echo '		<th scope="row"><label for="add_text">kintone domain</label></th>';
		echo '		<td><input name="kintone_to_wp_kintone_url" type="text" id="kintone_to_wp_kintone_url" value="' . ( esc_attr( $kintone_url ) === '' ? '' : esc_attr( $kintone_url ) ) . '" class="regular-text" /></td>';
		echo '	</tr>';
		echo '	<tr valign="top">';
		echo '		<th scope="row"><label for="add_text">API Token</label><br><span style="font-size:10px;">Permission: show record</span></th>';
		echo '		<td><input name="kintone_to_wp_kintone_api_token" type="text" id="kintone_to_wp_kintone_api_token" value="' . ( esc_textarea( $api_token ) === '' ) ? '' : esc_textarea( $api_token ) . '" class="regular-text" /></td>';
		echo '	</tr>';
		echo '	<tr valign="top">';
		echo '		<th scope="row"><label for="add_text">Reflect kintone to post_type</label></th>';
		echo '		<td>';
		echo '			kintone APP ID:<input name="kintone_to_wp_target_appid" type="text" id="kintone_to_wp_target_appid" value="' . ( esc_textarea( $target_appid ) === '' ? '' : esc_textarea( $target_appid ) ) . '" class="small-text" /> ->';
		echo '			WordPress Post Type:<select name="kintone_to_wp_reflect_post_type">';
		echo '				<option value=""></option>';
		echo '				<option ' . selected( $reflect_post_type, 'post', false ) . ' value="post">post</option>';
		$this->output_html_select_option_for_post_type( $reflect_post_type );
		echo '			</select>';
		echo '		</td>';
		echo '	</tr>';
		echo '	</table>';

		echo '<p class="submit"><input type="submit" name="get_kintone_fields" class="button-primary" value="Get kintone fields" /></p>';

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
		} else {
			// Nothing.
			if ( ! empty( $old_kintone_data ) ) {
				$disp_data = $old_kintone_data;
			}
		}

		if ( ! empty( $disp_data ) ) {

			echo '<form method="post" action="">';

			wp_nonce_field( $this->nonce, '_wpnonce' );

			echo 'Please set this URL to kintone\'s WEBHOOK-><strong>' . esc_url( site_url( '/wp-admin/admin-ajax.php?action=kintone_to_wp_start' ) ) . '</strong><br><span style="font-size:10px;">Permission: post record, update record, delete record</span>';
			echo '<br/>';
			echo '<br/>';
			echo '<div id="block-relate-kintone-and-wp">';
			echo '	<table>';
			echo '	<tr valign="top">';
			echo '		<th scope="row"><label for="add_text">Select Post title</label></th>';
			echo '		<td>';
			echo '			<select name="kintone_to_wp_kintone_field_code_for_post_title">';
			$this->output_html_select_option_for_post_title( $disp_data );
			echo '			</select>';
			echo '		</td>';
			echo '	</tr>';
			echo '	<tr valign="top">';
			echo '		<th scope="row"><label for="add_text">Select Term</label></th>';
			echo '		<td>';
			$this->output_html_selectbox_for_taxonomy( $disp_data, $reflect_post_type );
			echo '		</td>';
			echo '	</tr>';
			echo '	<tr valign="top">';
			echo '		<th scope="row"><label for="add_text">Setting Custom Field</label></th>';
			echo '		<td>';
			$this->output_html_input_text_for_custom_field( $disp_data );
			echo '		</td>';
			echo '	</tr>';
			echo '</table>';
			echo '</div>';

			echo '<p class="submit"><input type="submit" name="save" class="button-primary" value="save" /></p>';
			echo '<p class="submit"><input type="submit" name="bulk_update" class="button-primary" value="Bulk Update" /></p>';
			echo '</form>';

		}

		echo '</div>';

	}

	/**********************************************************
	 * 一括アップデート処理
	 * kintoneから該当データを全件取得してWPへ反映する
	 *
	 * @return void
	 **********************************************************/
	private function bulk_update() {

		$this->kintone_api = new Kintone_API( get_option( 'kintone_to_wp_kintone_url' ), get_option( 'kintone_to_wp_kintone_api_token' ), get_option( 'kintone_to_wp_target_appid' ) );

		$kintone_data['records'] = $this->kintone_api->get_all();

		// @todo 一括更新は kintone でデータを削除したものは、WordPress側では削除されない。(Webhookを最初から使用しているとWebhookで削除はされるけど)
		foreach ( $kintone_data['records'] as $key => $value ) {

			$data                         = array();
			$data['record']               = $value;
			$data['kintone_to_wp_status'] = 'normal';
			$data                         = apply_filters( 'kintone_to_wp_kintone_data', $data );
			$this->sync->main( $data, $this->kintone_api );
		}

		echo '<div class="updated fade"><p><strong>Updated</strong></p></div>';

	}

	/**
	 * Kintone接続する基本情報とkintoneのフィールドデータを保存する.
	 *
	 * @param array  $kintone_basci_information (domain.token, app_id,post_type) kintoneの設定に必要な基本情報.
	 * @param object $kintone_form_data .
	 *
	 * @return void
	 */
	private function update_kintone_basci_information( $kintone_basci_information, $kintone_form_data ) {

		if ( empty( $kintone_basci_information['domain'] ) ) {
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
	 * Kintoneの設定情報を保存する.
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

		if ( empty( $kintone_app_fields_code_for_wp['kintone_to_wp_kintone_field_code_for_terms']['category'] ) && empty( $kintone_app_fields_code_for_wp['kintone_to_wp_kintone_field_code_for_terms']['post_tag'] ) ) {
			delete_option( 'kintone_to_wp_kintone_field_code_for_terms' );
		} else {
			update_option( 'kintone_to_wp_kintone_field_code_for_terms', $kintone_app_fields_code_for_wp['kintone_to_wp_kintone_field_code_for_terms'] );
		}

		if ( empty( $kintone_app_fields_code_for_wp['kintone_to_wp_setting_custom_fields'] ) ) {
			delete_option( 'kintone_to_wp_setting_custom_fields' );
		} else {
			update_option( 'kintone_to_wp_setting_custom_fields', $kintone_app_fields_code_for_wp['kintone_to_wp_setting_custom_fields'] );
		}

	}

	/**
	 * カスタムフィールドを設定するHTMLを出力する.
	 *
	 * @param array $kintone_app_form_data Kintoneの設定データ.
	 */
	private function output_html_input_text_for_custom_field( $kintone_app_form_data ) {

		echo '<table>';

		$setting_custom_fields = get_option( 'kintone_to_wp_setting_custom_fields' );

		foreach ( $kintone_app_form_data['properties'] as $kintone_form_value ) {
			$input_val = '';

			if ( is_array( $setting_custom_fields ) ) {
				foreach ( $setting_custom_fields as $key => $custom_form_value ) {
					if ( array_key_exists( 'code', $kintone_form_value ) ) {
						if ( $kintone_form_value['code'] === $key ) {
							$input_val = $custom_form_value;
						}
					}
				}
			}

			if ( array_key_exists( 'code', $kintone_form_value ) ) {
				echo '<tr>';
				if ( 'RECORD_NUMBER' === $kintone_form_value['type'] ) {
					echo '<th>' . esc_html( $kintone_form_value['label'] ) . '(' . esc_html( $kintone_form_value['code'] ) . ')</th><td><input readonly="readonly" type="text" name="kintone_to_wp_setting_custom_fields[' . esc_attr( $kintone_form_value['code'] ) . ']" value="kintone_record_id" class="regular-text" /></td>';
				} else {
					echo '<th>' . esc_html( $kintone_form_value['label'] ) . '(' . esc_html( $kintone_form_value['code'] ) . ')</th><td><input type="text" name="kintone_to_wp_setting_custom_fields[' . esc_attr( $kintone_form_value['code'] ) . ']" value="' . esc_attr( $input_val ) . '" class="regular-text" /></td>';
				}
				echo '</tr>';
			}
		}
		echo '</table>';

	}

	/**
	 * Post type を設定するセレクトボックスを出力する.
	 *
	 * @param string $reflect_post_type 設定したPostType.
	 *
	 * @return void
	 */
	private function output_html_select_option_for_post_type( $reflect_post_type ) {

		$args       = array(
			'public'   => true,
			'_builtin' => false,
		);
		$post_types = get_post_types( $args, 'names' );

		foreach ( $post_types as $value ) {
			echo '<option ' . selected( $reflect_post_type, $value, false ) . ' value="' . esc_attr( $value ) . '">' . esc_html( $value ) . '</option>';
		}
	}

	/**
	 * Post titleを設定するセレクトボックスを出力する.
	 *
	 * @param array $kintone_app_form_data .
	 *
	 * @return void
	 */
	private function output_html_select_option_for_post_title( $kintone_app_form_data ) {

		$kintone_field_code_for_post_title = get_option( 'kintone_to_wp_kintone_field_code_for_post_title' );

		echo '<option ' . selected( '', $kintone_field_code_for_post_title, false ) . ' value=""></option>';

		foreach ( $kintone_app_form_data['properties'] as $kintone_form_value ) {

			if ( array_key_exists( 'code', $kintone_form_value ) ) {
				echo '<option ' . selected( $kintone_form_value['code'], $kintone_field_code_for_post_title, false ) . ' value="' . esc_attr( $kintone_form_value['code'] ) . '">' . esc_html( $kintone_form_value['label'] ) . '(' . esc_html( $kintone_form_value['code'] ) . ')</option>';
			}
		}

	}

	/**
	 * タクソノミーを設定するセレクトボックスhtmlを出力する.
	 *
	 * @param array  $kintone_app_form_data Kintoneのデータ.
	 * @param string $reflect_post_type Post type.
	 */
	private function output_html_selectbox_for_taxonomy( $kintone_app_form_data, $reflect_post_type ) {

		// Category.
		$terms = get_taxonomies(
			array(
				'object_type' => array( $reflect_post_type ),
				'show_ui'     => true,
			),
			'objects'
		);

		foreach ( $terms as $key => $term ) {

			echo esc_html( $term->label ) . '-><select name="kintone_to_wp_kintone_field_code_for_terms[' . esc_attr( $term->name ) . ']">';
			$kintone_field_code_for_terms = get_option( 'kintone_to_wp_kintone_field_code_for_terms' );

			echo '<option value=""></option>';

			foreach ( $kintone_app_form_data['properties'] as $kintone_form_value ) {

				$input_val = '';

				if ( is_array( $kintone_field_code_for_terms ) ) {
					foreach ( $kintone_field_code_for_terms as $key => $kintone_field_code_for_term ) {
						if ( $term->name === $key ) {
							$input_val = $kintone_field_code_for_term;
						}
					}
				}

				if ( array_key_exists( 'code', $kintone_form_value ) ) {
					echo '<option ' . selected( $kintone_form_value['code'], $input_val, false ) . ' value="' . esc_attr( $kintone_form_value['code'] ) . '">' . esc_html( $kintone_form_value['label'] ) . '(' . esc_html( $kintone_form_value['code'] ) . ')</option>';
				}
			}

			echo '</select><br/>';
		}

	}
}
