<?php
namespace publish_kintone_data;

class Admin{

	private $nonce   = 'kintone_to_wp_';

	public function __construct(){
		// Create Admin Menu.
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'save_post', array( $this, 'update_post_kintone_data' ), 10, 3 );
	}
	public function admin_menu() {
		add_submenu_page( 'options-general.php', 'Publish kintone data', 'Publish kintone data', 'manage_options', 'publish-kintone-data-setting', array(
			$this,
			'kintone_to_wp_setting'
		) );
	}
	public function kintone_to_wp_setting() {

		if ( ! empty( $_POST ) && check_admin_referer( $this->nonce ) ) {

			if ( isset( $_POST['get_kintone_fields'] ) ) {

				$kintone_basci_information = array();

				$kintone_basci_information['domain']    = sanitize_text_field( trim( $_POST['kintone_to_wp_kintone_url'] ) );
				$kintone_basci_information['app_id']    = sanitize_text_field( trim( $_POST['kintone_to_wp_target_appid'] ) );
				$kintone_basci_information['url']       = 'https://' . $kintone_basci_information['domain'] . '/k/v1/form.json?app=' . $kintone_basci_information['app_id'];
				$kintone_basci_information['token']     = sanitize_text_field( trim( $_POST['kintone_to_wp_kintone_api_token'] ) );
				$kintone_basci_information['post_type'] = sanitize_text_field( trim( $_POST['kintone_to_wp_reflect_post_type'] ) );

				$error_flg = false;
				if ( ! $kintone_basci_information['domain'] ) {
					echo '<div class="error notice is-dismissible"><p><strong>Domain is required</strong></p></div>';
					$error_flg = true;
				} else if ( ! $kintone_basci_information['post_type'] ) {
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
					$kintone_app_fields_code_for_wp['kintone_to_wp_kintone_field_code_for_post_title'] = sanitize_text_field( $_POST['kintone_to_wp_kintone_field_code_for_post_title'] );
				}
				if ( isset( $_POST['kintone_to_wp_kintone_field_code_for_post_contents'] ) ) {
					$kintone_app_fields_code_for_wp['kintone_to_wp_kintone_field_code_for_post_contents'] = sanitize_text_field( $_POST['kintone_to_wp_kintone_field_code_for_post_contents'] );
				}
				if ( isset( $_POST['kintone_to_wp_kintone_field_code_for_terms'] ) && is_array( $_POST['kintone_to_wp_kintone_field_code_for_terms'] ) ) {
					$kintone_app_fields_code_for_wp['kintone_to_wp_kintone_field_code_for_terms'] = $_POST['kintone_to_wp_kintone_field_code_for_terms'];
				}
				if ( isset( $_POST['kintone_to_wp_kintone_field_code_for_featured_image'] ) ) {
					$kintone_app_fields_code_for_wp['kintone_to_wp_kintone_field_code_for_featured_image'] = sanitize_text_field( $_POST['kintone_to_wp_kintone_field_code_for_featured_image'] );
				}

				if ( isset( $_POST['kintone_to_wp_setting_custom_fields'] ) && is_array( $_POST['kintone_to_wp_setting_custom_fields'] ) ) {
					$kintone_app_fields_code_for_wp['kintone_to_wp_setting_custom_fields'] = $_POST['kintone_to_wp_setting_custom_fields'];
				}


				$this->update_kintone_app_fields_code_for_wp( $kintone_app_fields_code_for_wp );

			} elseif ( isset( $_POST['bulk_update'] ) ) {

				$this->bulk_update();

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
		echo $wp_n;

		echo '<table class="form-table">';
		echo '	<tr valign="top">';
		echo '		<th scope="row"><label for="add_text">kintone domain</label></th>';
		echo '		<td><input name="kintone_to_wp_kintone_url" type="text" id="kintone_to_wp_kintone_url" value="' . ( $kintone_url == "" ? "" : esc_textarea( $kintone_url ) ) . '" class="regular-text" /></td>';
		echo '	</tr>';
		echo '	<tr valign="top">';
		echo '		<th scope="row"><label for="add_text">API Token</label><br><span style="font-size:10px;">Permission: show record</span></th>';
		echo '		<td><input name="kintone_to_wp_kintone_api_token" type="text" id="kintone_to_wp_kintone_api_token" value="' . ( $api_token == "" ? "" : esc_textarea( $api_token ) ) . '" class="regular-text" /></td>';
		echo '	</tr>';
		echo '	<tr valign="top">';
		echo '		<th scope="row"><label for="add_text">Reflect kintone to post_type</label></th>';
		echo '		<td>';
		echo '			kintone APP ID:<input name="kintone_to_wp_target_appid" type="text" id="kintone_to_wp_target_appid" value="' . ( $target_appid == "" ? "" : esc_textarea( $target_appid ) ) . '" class="small-text" /> ->';
		echo '			WordPress Post Type:<select name="kintone_to_wp_reflect_post_type">';
		echo '				<option value=""></option>';
		echo '				<option ' . selected( $reflect_post_type, "post", false ) . ' value="post">post</option>';
		echo '				<option ' . selected( $reflect_post_type, "page", false ) . ' value="page">page</option>';
		echo $this->get_html_post_type_form_slect_option( $reflect_post_type );
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
				// Error
				if ( ! empty( $old_kintone_data ) ) {
					$disp_data = $old_kintone_data;
				}
			} else {
				// Success
				$disp_data = $kintone_form_data;
			}
		} else {
			// Nothing
			if ( ! empty( $old_kintone_data ) ) {
				$disp_data = $old_kintone_data;
			}
		}

		if ( ! empty( $disp_data ) ) {


			echo '<form method="post" action="">';

			echo $wp_n;

			echo 'Please set this URL to kintone\'s WEBHOOK-><strong>' . site_url( '/wp-admin/admin-ajax.php?action=kintone_to_wp_start' ) . '</strong><br><span style="font-size:10px;">Permission: post record, update record, delete record</span>';
			echo '<br/>';
			echo '<br/>';
			echo '	<table>';
			echo '	<tr valign="top">';
			echo '		<th scope="row"><label for="add_text">Select Post title</label></th>';
			echo '		<td>';
			echo '			<select name="kintone_to_wp_kintone_field_code_for_post_title">';
			echo $this->get_html_post_title_form_select_option( $disp_data );
			echo '			</select>';
			echo '		</td>';
			echo '	</tr>';
			echo '	<tr valign="top">';
			echo '		<th scope="row"><label for="add_text">Select Post contents</label></th>';
			echo '		<td>';
			echo '			<select name="kintone_to_wp_kintone_field_code_for_post_contents">';
			echo $this->get_html_post_contents_form_select_option( $disp_data );
			echo '			</select>';
			echo '		</td>';
			echo '	</tr>';
			echo '	<tr valign="top">';
			echo '		<th scope="row"><label for="add_text">Select Term</label></th>';
			echo '		<td>';
			echo $this->get_html_taxonomy_form_select( $disp_data, $reflect_post_type );
			echo '		</td>';
			echo '	</tr>';
			echo '	<tr valign="top">';
			echo '		<th scope="row"><label for="add_text">Select Featured image</label></th>';
			echo '		<td>';
			echo '			<select name="kintone_to_wp_kintone_field_code_for_featured_image">';
			echo $this->get_html_featured_image_form_select_option( $disp_data );
			echo '			</select>';
			echo '		</td>';
			echo '	</tr>';
			echo '	<tr valign="top">';
			echo '		<th scope="row"><label for="add_text">Setting Custom Field</label></th>';
			echo '		<td>';
			echo $this->get_html_custom_field_form_input( $disp_data );
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

		remove_action( 'save_post', array( $this, 'update_post_kintone_data' ), 10 );

		$reflect_post_type = get_option( 'kintone_to_wp_reflect_post_type' );
		if ( get_post_type( $post_id ) !== $reflect_post_type ) {
			return;
		}

		$kintone_id = $this->get_kintone_id_without_appcode( get_post_meta( $post_id, 'kintone_record_id', true ) );

		if ( empty( $kintone_id ) ) {
			return;
		}

		// WordPress側で更新処理をされた場合、再度 kintoneからデータを取得して反映する（kintoneの情報が常に正しい）.
		$url                                = 'https://' . get_option( 'kintone_to_wp_kintone_url' ) . '/k/v1/record.json?app=' . get_option( 'kintone_to_wp_target_appid' ) . '&id=' . $kintone_id;
		$retun_data                         = Kintone_Utility::kintone_api( $url, get_option( 'kintone_to_wp_kintone_api_token' ) );
		$retun_data['kintone_to_wp_status'] = 'normal';
		$publish_kintone_data = new Publish_Kintone_Data();
		$publish_kintone_data->sync( $retun_data );

	}
	private function bulk_update() {

		// 一旦全記事を下書きにする
		$post_type = apply_filters( 'publish_kintone_data_reflect_post_type', get_option( 'kintone_to_wp_reflect_post_type' ), 'bulk_update' );
		$args      = array(
			'post_type'      => $post_type,
			'posts_per_page' => -1,
			'post_status'    => 'publish',
		);
		// The Query
		$the_query = new \WP_Query( $args );
		if ( $the_query->have_posts() ) {
			while ( $the_query->have_posts() ) {
				$the_query->the_post();
				// 下書きに更新する
				wp_update_post(
					array(
						'ID'          => get_the_ID(),
						'post_status' => 'draft',
					)
				);
			}
			/* Restore original Post Data */
			wp_reset_postdata();
		}

		$kintone_data['records'] = array();
		$last_id = 0;
		$reacquisition_flag = true;

		while ( $reacquisition_flag ) {

			$query = apply_filters( 'import_kintone_change_bulk_update_query', '$id > ' . $last_id . ' order by $id asc limit 500');

			$url        = 'https://' . get_option( 'kintone_to_wp_kintone_url' ) . '/k/v1/records.json?app=' . get_option( 'kintone_to_wp_target_appid' ) . '&query=' . $query;
			$retun_data = Kintone_Utility::kintone_api( $url, get_option( 'kintone_to_wp_kintone_api_token' ) );

			$kintone_data['records'] = array_merge( $kintone_data['records'], $retun_data['records'] );

			if ( count( $retun_data['records'] ) < 500 ) {
				$reacquisition_flag = false;
			} else {
				$last_id = end($retun_data['records'])['$id']['value'];
			}
		}

		// @todo 一括更新は kintone でデータを削除したものは、WordPress側では削除されない。(Webhookを最初から使用しているとWebhookで削除はされるけど)
		foreach ( $kintone_data['records'] as $key => $value ) {

			$data                         = array();
			$data['record']               = $value;
			$data['kintone_to_wp_status'] = 'normal';
			$data['type'] = 'UPDATE_RECORD';
			$data['app'] = array(
				'id' => get_option( 'kintone_to_wp_target_appid' )
			);
			$data                         = apply_filters( 'kintone_to_wp_kintone_data', $data );
			$publish_kintone_data = new Publish_Kintone_Data();
			$publish_kintone_data->sync( $data );
		}

		echo '<div class="updated fade"><p><strong>Updated</strong></p></div>';

	}
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

	private function get_html_custom_field_form_input( $kintone_app_form_data ) {

		$html_setting_custom_fields = "";
		$html_setting_custom_fields .= '<table>';
		$setting_custom_fields      = get_option( 'kintone_to_wp_setting_custom_fields' );

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
				if ( $kintone_form_value['type'] == 'RECORD_NUMBER' ) {

					$html_setting_custom_fields .= '<th>' . esc_html( $kintone_form_value['label'] ) . '(' . esc_html( $kintone_form_value['code'] ) . ')' . '</th><td><input readonly="readonly" type="text" name="kintone_to_wp_setting_custom_fields[' . esc_attr( $kintone_form_value['code'] ) . ']" value="kintone_record_id" class="regular-text" /></td>';

				} else {

					$label = '';
					if ( isset( $kintone_form_value['label'] ) ) {
						$label = $kintone_form_value['label'];
					}

					$html_setting_custom_fields .= '<th>' . esc_html( $label ) . '(' . esc_html( $kintone_form_value['code'] ) . ')' . '</th><td><input type="text" name="kintone_to_wp_setting_custom_fields[' . esc_attr( $kintone_form_value['code'] ) . ']" value="' . esc_attr( $input_val ) . '" class="regular-text" /></td>';

				}
				$html_setting_custom_fields .= '</tr>';
			}

		}
		$html_setting_custom_fields .= '</table>';

		return $html_setting_custom_fields;

	}

	private function get_html_post_type_form_slect_option( $reflect_post_type ) {

		$args       = array(
			'public'   => true,
			'_builtin' => false
		);
		$post_types = get_post_types( $args, 'names' );

		$html_option = '';
		foreach ( $post_types as $value ) {
			$html_option .= '<option ' . selected( $reflect_post_type, $value, false ) . ' value="' . esc_attr( $value ) . '">' . esc_html( $value ) . '</option>';
		}

		return $html_option;

	}

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

				$html_select_featured_image .= '<option ' . selected( $kintone_form_value['code'], $kintone_field_code_for_featured_image, false ) . ' value="' . esc_attr( $kintone_form_value['code'] ) . '">' . esc_html( $label ) . '(' . esc_html( $kintone_form_value['code'] ) . ')' . '</option>';
			}

		}

		return $html_select_featured_image;
	}

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

				$html_select_post_title .= '<option ' . selected( $kintone_form_value['code'], $kintone_field_code_for_post_title, false ) . ' value="' . esc_attr( $kintone_form_value['code'] ) . '">' . esc_html( $label ) . '(' . esc_html( $kintone_form_value['code'] ) . ')' . '</option>';
			}

		}

		return $html_select_post_title;

	}

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

				$html_select_post_contents .= '<option ' . selected( $kintone_form_value['code'], $kintone_field_code_for_post_contents, false ) . ' value="' . esc_attr( $kintone_form_value['code'] ) . '">' . esc_html( $label ) . '(' . esc_html( $kintone_form_value['code'] ) . ')' . '</option>';
			}

		}

		return $html_select_post_contents;
	}


	private function get_html_taxonomy_form_select( $kintone_app_form_data, $reflect_post_type ) {

		// Category
		$terms = get_taxonomies(
			array(),
			'objects'
		);

		$html_select_term = '';

		foreach ( $terms as $key => $term ) {

			if ( in_array( $reflect_post_type, $term->object_type ) ) {

				$html_select_term             .= $term->label . '-><select name="kintone_to_wp_kintone_field_code_for_terms[' . $term->name . ']">';
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
						$html_select_term .= '<option ' . selected( $kintone_form_value['code'], $input_val, false ) . ' value="' . esc_attr( $kintone_form_value['code'] ) . '">' . esc_html( $kintone_form_value['label'] ) . '(' . esc_html( $kintone_form_value['code'] ) . ')' . '</option>';
					}
				}

				$html_select_term .= '</select><br/>';
			}
		}

		return $html_select_term;

	}
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
