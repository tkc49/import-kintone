<?php
namespace publish_kintone_data;

use WP_Query;

class Publish_Kintone_Data {

	private $kintone_to_wp_kintone_api_token;
	private $kintone_to_wp_reflect_post_type;
	private $kintone_to_wp_kintone_field_code_for_featured_image;
	private $kintone_to_wp_kintone_field_code_for_post_title;
	private $kintone_to_wp_kintone_field_code_for_post_contents;
	private $kintone_to_wp_setting_custom_fields;
	private $kintone_to_wp_kintone_field_code_for_terms;


	public function __construct() {
		// Webhook.
		add_action( 'wp_ajax_kintone_to_wp_start', array( $this, 'kintone_to_wp_start' ) );
		add_action( 'wp_ajax_nopriv_kintone_to_wp_start', array( $this, 'kintone_to_wp_start' ) );

	}

	/**
	 * Webhookの処理スタート
	 *
	 * @return void
	 */
	public function kintone_to_wp_start() {

		if ( ! Kintone_Utility::check_kintone_ip_adress( $_SERVER['REMOTE_ADDR'] ) ) {
			return;
		}

		$kintone_data_json_by_webhook = file_get_contents( 'php://input' );
		$kintone_data_by_webhook      = json_decode( $kintone_data_json_by_webhook, true );
		$this->sync( $kintone_data_by_webhook );

	}

	public function sync( $kintoen_data ) {

		$this->kintone_to_wp_kintone_api_token                     = apply_filters( 'publish_kintone_data_kintone_api_token', get_option( 'kintone_to_wp_kintone_api_token' ), $kintoen_data );
		$this->kintone_to_wp_reflect_post_type                     = apply_filters( 'publish_kintone_data_reflect_post_type', get_option( 'kintone_to_wp_reflect_post_type' ), $kintoen_data );
		$this->kintone_to_wp_kintone_field_code_for_featured_image = apply_filters( 'publish_kintone_data_kintone_field_code_for_featured_image', get_option( 'kintone_to_wp_kintone_field_code_for_featured_image' ), $kintoen_data );
		$this->kintone_to_wp_kintone_field_code_for_post_title     = apply_filters( 'publish_kintone_data_kintone_field_code_for_post_title', get_option( 'kintone_to_wp_kintone_field_code_for_post_title' ), $kintoen_data );
		$this->kintone_to_wp_kintone_field_code_for_post_contents  = apply_filters( 'publish_kintone_data_kintone_field_code_for_post_contents', get_option( 'kintone_to_wp_kintone_field_code_for_post_contents' ), $kintoen_data );
		$this->kintone_to_wp_kintone_field_code_for_terms          = apply_filters( 'publish_kintone_data_kintone_field_code_for_terms', get_option( 'kintone_to_wp_kintone_field_code_for_terms' ), $kintoen_data );
		$this->kintone_to_wp_setting_custom_fields                 = apply_filters( 'publish_kintone_data_setting_custom_fields', get_option( 'kintone_to_wp_setting_custom_fields' ), $kintoen_data );

		// WordPressの投稿を削除.
		if ( isset( $kintoen_data['type'] ) && $kintoen_data['type'] === 'DELETE_RECORD' ) {
			$kintone_id = $kintoen_data['recordId'];
			$this->delete( $kintone_id, $kintoen_data );
			return;
		}

		$kintoen_data['kintone_to_wp_status'] = 'normal';
		$kintoen_data                         = apply_filters( 'kintone_to_wp_kintone_data', $kintoen_data );

		remove_action( 'save_post', array( $this, 'update_post_kintone_data' ), 10 );
		remove_filter( 'content_save_pre', 'wp_filter_post_kses' );

		$status  = '';
		$post_id = '';

		if ( ! empty( $kintoen_data ) ) {
			$kintone_record_number = $this->get_kintone_record_number( $kintoen_data );

			$args      = array(
				'post_type'   => $this->kintone_to_wp_reflect_post_type,
				'meta_key'    => 'kintone_record_id',
				'meta_value'  => $kintone_record_number,
				'post_status' => array( 'publish', 'pending', 'draft', 'future', 'private' ),
			);
			$the_query = new WP_Query( $args );
			if ( $the_query->have_posts() ) {

				// WordPressにデータが存在するので、UPDATE or DELETE の処理をする.
				if ( $kintoen_data['kintone_to_wp_status'] == 'normal' ) {
					$this->update_kintone_data_to_wp_post( $the_query->post->ID, $kintoen_data );
					$this->update_kintone_data_to_wp_post_meta( $the_query->post->ID, $kintoen_data );
					$this->update_kintone_data_to_wp_terms( $the_query->post->ID, $kintoen_data );
					$this->update_kintone_data_to_wp_post_featured_image( $the_query->post->ID, $kintoen_data );

					$status  = 'update';
					$post_id = $the_query->post->ID;

				} elseif ( $kintoen_data['kintone_to_wp_status'] == 'delete' ) {
					wp_delete_post( $the_query->post->ID );

					$status  = 'delete';
					$post_id = $the_query->post->ID;

				}
			} else {

				// WordPressにデータが存在しないので、INSERT 処理をする.
				if ( $kintoen_data['kintone_to_wp_status'] == 'normal' ) {
					$post_id = $this->insert_kintone_data_to_wp_post( $kintoen_data );
					$this->update_kintone_data_to_wp_post_meta( $post_id, $kintoen_data );
					$this->update_kintone_data_to_wp_terms( $post_id, $kintoen_data );
					$this->update_kintone_data_to_wp_post_featured_image( $post_id, $kintoen_data );

					$status = 'insert';
				}
			}

			do_action( 'after_insert_or_update_to_post', $post_id, $kintoen_data, $status );
		}

		add_filter( 'content_save_pre', 'wp_filter_post_kses' );

	}

	private function update_kintone_data_to_wp_post_featured_image( $post_id, $kintone_data ) {

		$kintone_to_wp_kintone_field_code_for_featured_image = $this->kintone_to_wp_kintone_field_code_for_featured_image;
		if ( $kintone_to_wp_kintone_field_code_for_featured_image ) {
			if ( array_key_exists( $kintone_to_wp_kintone_field_code_for_featured_image, $kintone_data['record'] ) ) {
				$setting_kintone_field_code_for_featured_image = $kintone_to_wp_kintone_field_code_for_featured_image;

				if ( 'FILE' === $kintone_data['record'][ $setting_kintone_field_code_for_featured_image ]['type'] ) {
					if ( ! empty( $kintone_data['record'][ $setting_kintone_field_code_for_featured_image ]['value'] ) ) {
						$this->update_kintone_temp_file_to_meta( $post_id, $kintone_data['record'][ $setting_kintone_field_code_for_featured_image ]['value'][0], '', true );
					} else {
						$this->delete_kintone_temp_file( $post_id, '', true );
					}
				}
			}
		}
	}

	private function insert_kintone_data_to_wp_post( $kintoen_data ) {

		$field_code_for_post_title    = $this->kintone_to_wp_kintone_field_code_for_post_title;
		$field_code_for_post_contents = $this->kintone_to_wp_kintone_field_code_for_post_contents;

		$post_status = 'draft';
		$post_status = apply_filters( 'import_kintone_insert_post_status', $post_status );

		$post_title = '';
		if ( isset( $kintoen_data['record'][ $field_code_for_post_title ] ) && $kintoen_data['record'][ $field_code_for_post_title ]['value'] ) {
			$post_title = $kintoen_data['record'][ $field_code_for_post_title ]['value'];
		}

		$post_content = '';
		if ( isset( $kintoen_data['record'][ $field_code_for_post_contents ] ) && $kintoen_data['record'][ $field_code_for_post_contents ]['value'] ) {
			$post_content = $kintoen_data['record'][ $field_code_for_post_contents ]['value'];
		}

		$post_author = '';
		$post_author = apply_filters( 'import_kintone_insert_post_author', $post_author );

		$post_id = wp_insert_post(
			array(
				'post_type'    => $this->kintone_to_wp_reflect_post_type,
				'post_title'   => $post_title,
				'post_content' => $post_content,
				'post_status'  => $post_status,
				'post_author'  => $post_author,
			)
		);

		return $post_id;

	}

	private function update_kintone_data_to_wp_post( $post_id, $kintoen_data ) {

		$post_title = '';
		if ( ! empty( $this->kintone_to_wp_kintone_field_code_for_post_title ) && array_key_exists( $this->kintone_to_wp_kintone_field_code_for_post_title, $kintoen_data['record'] ) ) {
			$post_title = $kintoen_data['record'][ $this->kintone_to_wp_kintone_field_code_for_post_title ]['value'];
		}
		$post_contents = '';
		if ( ! empty( $this->kintone_to_wp_kintone_field_code_for_post_contents ) && array_key_exists( $this->kintone_to_wp_kintone_field_code_for_post_contents, $kintoen_data['record'] ) ) {
			$post_contents = $kintoen_data['record'][ $this->kintone_to_wp_kintone_field_code_for_post_contents ]['value'];
		}

		$my_post = array(
			'ID'           => $post_id,
			'post_type'    => $this->kintone_to_wp_reflect_post_type,
			'post_title'   => $post_title,
			'post_content' => $post_contents,
		);

		$post_id = wp_update_post( $my_post );
		if ( is_wp_error( $post_id ) ) {
			$errors = $post_id->get_error_messages();
			foreach ( $errors as $error ) {
				error_log( var_export( $error, true ) );
			}
		}

	}

	private function update_kintone_data_to_wp_post_meta( $post_id, $kintone_data ) {

		$setting_custom_fields = $this->kintone_to_wp_setting_custom_fields;

		// update kintone_id
		update_post_meta( $post_id, 'kintone_record_id', $kintone_data['record']['$id']['value'] );

		foreach ( $setting_custom_fields as $key => $kintone_fieldcode ) {

			if ( $kintone_fieldcode ) {

				if ( $kintone_data['record'][ $key ]['type'] == 'USER_SELECT' ) {

					update_post_meta( $post_id, $kintone_fieldcode, $kintone_data['record'][ $key ]['value'] );

				} elseif ( $kintone_data['record'][ $key ]['type'] == 'FILE' ) {

					if ( ! empty( $kintone_data['record'][ $key ]['value'] ) ) {
						$this->update_kintone_temp_file_to_meta( $post_id, $kintone_data['record'][ $key ]['value'][0], $kintone_fieldcode );
					} else {
						$this->delete_kintone_temp_file( $post_id, $kintone_fieldcode );
					}
				} elseif ( $kintone_data['record'][ $key ]['type'] == 'CREATOR' || $kintone_data['record'][ $key ]['type'] == 'MODIFIER' ) {

					update_post_meta( $post_id, $kintone_fieldcode . '_code', $kintone_data['record'][ $key ]['value']['code'] );
					update_post_meta( $post_id, $kintone_fieldcode . '_name', $kintone_data['record'][ $key ]['value']['name'] );

				} elseif ( $kintone_data['record'][ $key ]['type'] == 'SUBTABLE' ) {

					update_post_meta( $post_id, $kintone_fieldcode, $kintone_data['record'][ $key ]['value'] );

				} else {
					$record_data = $this->make_kintone_array_to_string( $kintone_data['record'][ $key ]['value'] );

					if ( function_exists( 'CFS' ) ) {

						$field_data = array(
							$kintone_fieldcode => $record_data,
						);

						$post_data = array(
							'ID' => $post_id,
						);

						CFS()->save( $field_data, $post_data );
					} else {
						update_post_meta( $post_id, $kintone_fieldcode, $record_data );
					}
				}
			}
		}
	}


	private function update_kintone_data_to_wp_terms( $post_id, $kintone_data ) {

		$kintone_field_code_for_terms = $this->kintone_to_wp_kintone_field_code_for_terms;

		if ( ! empty( $kintone_field_code_for_terms ) ) {
			foreach ( $kintone_field_code_for_terms as $key => $kintone_field_code_for_term ) {

				if ( isset( $kintone_data['record'][ $kintone_field_code_for_term ] ) ) {
					$terms = $kintone_data['record'][ $kintone_field_code_for_term ]['value'];

					if ( ! is_array( $terms ) ) {
						$terms = array( $terms );
					}

					wp_set_object_terms( $post_id, $terms, $key );
				}
			}
		}
	}



	private function make_kintone_array_to_string( $kintone_data ) {

		$record_data = $kintone_data;

		if ( is_array( $record_data ) ) {

			$record_data = implode( ',', $record_data );
		}

		return $record_data;
	}

	private function get_kintone_temp_file( $filekey ) {

		$url = 'https://' . get_option( 'kintone_to_wp_kintone_url' ) . '/k/v1/file.json?fileKey=' . $filekey;

		return Kintone_Utility::kintone_api( $url, $this->kintone_to_wp_kintone_api_token, true );

	}

	private function delete_kintone_temp_file( $post_id, $kintone_fieldcode, $featured_image_flag = false ) {

		if ( $featured_image_flag ) {
			$attachment_id = get_post_thumbnail_id( $post_id );
			if ( $attachment_id ) {
				wp_delete_attachment( $attachment_id );
				delete_post_thumbnail( $post_id );
			}
		} else {
			if ( $kintone_fieldcode ) {
				$attachment_id = get_post_meta( $post_id, $kintone_fieldcode, true );
				if ( ! empty( $attachment_id ) ) {

					wp_delete_attachment( $attachment_id );
					delete_post_meta( $post_id, $kintone_fieldcode, $attachment_id );

				}
			}
		}
	}

	private function update_kintone_temp_file_to_meta( $post_id, $temp_base_data, $post_meta_name, $featured_image_flag = false ) {

		if ( $temp_base_data['size'] === '0' ) {
			return;
		}

		$file_data = $this->get_kintone_temp_file( $temp_base_data['fileKey'] );

		$upload_dir = wp_upload_dir();
		$tmp_dir    = $upload_dir['basedir'] . '/import_kintone';

		$tmp_filename = $post_id . '_' . $temp_base_data['name'];
		$tmp_type     = $temp_base_data['contentType'];
		$tmp_size     = $temp_base_data['size'];

		if ( ! file_exists( $tmp_dir ) ) {
			if ( mkdir( $tmp_dir, 0777 ) ) {
				chmod( $tmp_dir, 0777 );
			}
		}

		$fp = fopen( $tmp_dir . '/' . $tmp_filename, 'w' );
		fwrite( $fp, $file_data );
		fclose( $fp );
		chmod( $tmp_dir . '/' . $tmp_filename, 0777 );

		$upload['tmp_name'] = $tmp_dir . '/' . $tmp_filename;
		$upload['name']     = $tmp_filename;
		$upload['type']     = $tmp_type;
		$upload['error']    = UPLOAD_ERR_OK;
		$upload['size']     = $tmp_size;

		include_once ABSPATH . 'wp-admin/includes/file.php';
		include_once ABSPATH . 'wp-admin/includes/media.php';
		include_once ABSPATH . 'wp-admin/includes/image.php';

		$overrides = array(
			'test_form' => false,
			'action'    => '',
		);
		$file      = wp_handle_upload( $upload, $overrides );

		$post_author = '';
		$post_author = apply_filters( 'import_kintone_insert_post_author', $post_author );

		$attachment = array(
			'post_title'     => $temp_base_data['name'],
			'post_mime_type' => $file['type'],
			'post_parent'    => $post_id,
			'post_author'    => $post_author,
		);

		$aid         = wp_insert_attachment( $attachment, $file['file'], $post_id );
		$attach_data = wp_generate_attachment_metadata( $aid, $file['file'] );

		if ( $featured_image_flag ) {
			if ( has_post_thumbnail( $post_id ) ) {
				$thumbnail_id = get_post_thumbnail_id( $post_id );
				delete_post_thumbnail( $post_id );
				wp_delete_attachment( $thumbnail_id );  /*Delete previous image画像が増えていかないように*/
			}
			set_post_thumbnail( $post_id, $aid );

		} else {
			if ( $post_meta_name ) {
				$attachment_id = get_post_meta( $post_id, $post_meta_name, true );
				if ( ! empty( $attachment_id ) ) {
					wp_delete_attachment( $attachment_id );  /*Delete previous image画像が増えていかないように*/
				}
				update_post_meta( $post_id, $post_meta_name, $aid );
			}
		}

		if ( ! is_wp_error( $aid ) ) {
			wp_update_attachment_metadata( $aid, $attach_data );  /*If there is no error, update the metadata of the newly uploaded image*/
		}

		// ディレクトリー削除.
		@unlink( $tmp_dir . '/' . $tmp_filename );

	}
	/**
	 * Kintoneのレコードが削除されたので、WordPressの記事を削除する
	 *
	 * @param $record_id
	 * @param $update_kintone_data
	 * @return void
	 */
	private function delete( $record_id, $update_kintone_data ) {

		$args      = array(
			'post_type'   => $this->kintone_to_wp_reflect_post_type,
			'meta_key'    => 'kintone_record_id',
			'meta_value'  => $record_id,
			'post_status' => array( 'publish', 'pending', 'draft', 'future', 'private' ),
		);
		$the_query = new WP_Query( $args );
		if ( $the_query->have_posts() ) {
			wp_delete_post( $the_query->post->ID );
		} else {
			// 削除できなかったらアプリコード付きで削除する
			$url              = 'https://' . get_option( 'kintone_to_wp_kintone_url' ) . '/k/v1/app.json?id=' . $update_kintone_data['app']['id'];
			$kintone_app_data = Kintone_Utility::kintone_api( $url, $this->kintone_to_wp_kintone_api_token );

			if ( $kintone_app_data['code'] ) {
				$args      = array(
					'post_type'   => $this->kintone_to_wp_reflect_post_type,
					'meta_key'    => 'kintone_record_id',
					'meta_value'  => $kintone_app_data['code'] . '-' . $record_id,
					'post_status' => array( 'publish', 'pending', 'draft', 'future', 'private' ),
				);
				$the_query = new WP_Query( $args );
				if ( $the_query->have_posts() ) {
					wp_delete_post( $the_query->post->ID );
				}
			}
		}
	}

	/**
	 * KintoneのRECORD_NUMBERフィールドの値を取得する
	 *
	 * @param $kintoen_data
	 * @return mixed|string
	 */
	private function get_kintone_record_number( $kintoen_data ) {

		$kintone_record_number = '';
		foreach ( $kintoen_data['record'] as $kintone_field_code => $kintone_field_info ) {
			if ( 'RECORD_NUMBER' === $kintone_field_info['type'] ) {
				$kintone_record_number = $kintone_field_info['value'];
			}
		}

		return $kintone_record_number;

	}
}
