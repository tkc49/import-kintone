<?php
namespace publish_kintone_data;

use WP_Error;
use WP_Query;

class Publish_Kintone_Data {

	/**
	 * Kintone API token.
	 *
	 * @var string
	 */
	private $kintone_to_wp_kintone_api_token;

	/**
	 * Reflect Post type.
	 *
	 * @var string
	 */
	private $kintone_to_wp_reflect_post_type;

	/**
	 * Kintone field code for featured image.
	 *
	 * @var string
	 */
	private $kintone_to_wp_kintone_field_code_for_featured_image;

	/**
	 * Kintone field code for featured image.
	 *
	 * @var string
	 */
	private $kintone_to_wp_kintone_field_code_for_post_title;

	/**
	 * Kintone field code for post contents.
	 *
	 * @var string
	 */
	private $kintone_to_wp_kintone_field_code_for_post_contents;

	/**
	 * Setting custom fields.
	 *
	 * @var array
	 */
	private $kintone_to_wp_setting_custom_fields;

	/**
	 * Kintone field code for terms.
	 *
	 * @var array
	 */
	private $kintone_to_wp_kintone_field_code_for_terms;


	/**
	 * Construct
	 */
	public function __construct() {

		require_once KINTONE_TO_WP_PATH . '/inc/class-shortcode.php';

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
			header( 'HTTP/1.1 403 Forbidden' );
			echo '{}';
			exit;
		}

		$kintone_data_json_by_webhook = file_get_contents( 'php://input' );
		$kintone_data_by_webhook      = json_decode( $kintone_data_json_by_webhook, true );

		if ( null === $kintone_data_by_webhook ) {
			if ( isset( $_GET['type'] ) && $_GET['type'] ) {
				$kintone_data_by_webhook['type'] = $_GET['type'];
			}

			if ( isset( $_GET['recordId'] ) && $_GET['recordId'] ) {
				$kintone_data_by_webhook['recordId'] = $_GET['recordId'];
			}
		}

		$resp = $this->sync( $kintone_data_by_webhook );
		if(is_wp_error($resp)){
			header( 'HTTP/1.1 503 Service Unavailable' );
			exit;
		}

		header( 'Content-Type: application/json; charset=utf-8' );
		echo wp_json_encode( '{}' );
		exit;

	}

	/**
	 * Sync
	 *
	 * @param array $kintoen_data .
	 *
	 * @return void
	 */
	public function sync( $kintoen_data ) {

		$this->kintone_to_wp_kintone_api_token                     = apply_filters( 'publish_kintone_data_kintone_api_token', get_option( 'kintone_to_wp_kintone_api_token' ), $kintoen_data );
		$this->kintone_to_wp_reflect_post_type                     = apply_filters( 'publish_kintone_data_reflect_post_type', get_option( 'kintone_to_wp_reflect_post_type' ), $kintoen_data );
		$this->kintone_to_wp_kintone_field_code_for_featured_image = apply_filters( 'publish_kintone_data_kintone_field_code_for_featured_image', get_option( 'kintone_to_wp_kintone_field_code_for_featured_image' ), $kintoen_data );
		$this->kintone_to_wp_kintone_field_code_for_post_title     = apply_filters( 'publish_kintone_data_kintone_field_code_for_post_title', get_option( 'kintone_to_wp_kintone_field_code_for_post_title' ), $kintoen_data );
		$this->kintone_to_wp_kintone_field_code_for_post_contents  = apply_filters( 'publish_kintone_data_kintone_field_code_for_post_contents', get_option( 'kintone_to_wp_kintone_field_code_for_post_contents' ), $kintoen_data );
		$this->kintone_to_wp_kintone_field_code_for_terms          = apply_filters( 'publish_kintone_data_kintone_field_code_for_terms', get_option( 'kintone_to_wp_kintone_field_code_for_terms' ), $kintoen_data );
		$this->kintone_to_wp_setting_custom_fields                 = apply_filters( 'publish_kintone_data_setting_custom_fields', get_option( 'kintone_to_wp_setting_custom_fields' ), $kintoen_data );

		// WordPressの投稿を削除.
		if ( isset( $kintoen_data['type'] ) && 'DELETE_RECORD' === $kintoen_data['type'] ) {
			$kintone_id = $kintoen_data['recordId'];
			return $this->delete( $kintone_id );
		}

		$kintoen_data['kintone_to_wp_status'] = 'normal';
		$kintoen_data                         = apply_filters( 'kintone_to_wp_kintone_data', $kintoen_data );

		remove_action( 'save_post', array( $this, 'update_post_kintone_data' ), 10 );
		remove_filter( 'content_save_pre', 'wp_filter_post_kses' );

		$status  = '';
		$post_id = '';

		if ( ! empty( $kintoen_data ) ) {
			$args      = array(
				'post_type'   => $this->kintone_to_wp_reflect_post_type,
				'meta_key'    => 'kintone_record_id',
				'meta_value'  => $kintoen_data['record']['$id']['value'],
				'post_status' => array( 'publish', 'pending', 'draft', 'future', 'private' ),
			);
			$the_query = new WP_Query( $args );
			if ( $the_query->have_posts() ) {

				// WordPressにデータが存在するので、UPDATE or DELETE の処理をする.
				if ( 'normal' === $kintoen_data['kintone_to_wp_status'] ) {
					$this->update_kintone_data_to_wp_post( $the_query->post->ID, $kintoen_data );
					$this->update_kintone_data_to_wp_post_meta( $the_query->post->ID, $kintoen_data );
					$this->update_kintone_data_to_wp_terms( $the_query->post->ID, $kintoen_data );
					$this->update_kintone_data_to_wp_post_featured_image( $the_query->post->ID, $kintoen_data );

					$status  = 'update';
					$post_id = $the_query->post->ID;

				} elseif ( 'delete' === $kintoen_data['kintone_to_wp_status'] ) {
					wp_delete_post( $the_query->post->ID );

					$status  = 'delete';
					$post_id = $the_query->post->ID;

				}
			} else {

				// WordPressにデータが存在しないので、INSERT 処理をする.
				if ( 'normal' === $kintoen_data['kintone_to_wp_status'] ) {
					$post_id = $this->insert_kintone_data_to_wp_post( $kintoen_data );
					if ( $post_id ) {
						$this->update_kintone_data_to_wp_post_meta( $post_id, $kintoen_data );
						$this->update_kintone_data_to_wp_terms( $post_id, $kintoen_data );
						$this->update_kintone_data_to_wp_post_featured_image( $post_id, $kintoen_data );
					}
					$status = 'insert';
				}
			}

			do_action( 'after_insert_or_update_to_post', $post_id, $kintoen_data, $status );
		}

		add_filter( 'content_save_pre', 'wp_filter_post_kses' );

	}

	/**
	 * Update featured image
	 *
	 * @param int   $post_id .
	 * @param array $kintone_data .
	 *
	 * @return void
	 */
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

	/**
	 * Insert kintone data to WP post
	 *
	 * @param array $kintone_data .
	 *
	 * @return int|WP_Error
	 */
	private function insert_kintone_data_to_wp_post( $kintone_data ) {

		$field_code_for_post_title    = $this->kintone_to_wp_kintone_field_code_for_post_title;
		$field_code_for_post_contents = $this->kintone_to_wp_kintone_field_code_for_post_contents;

		$post_status = 'draft';
		$post_status = apply_filters_deprecated( 'import_kintone_insert_post_status', array( $post_status ), '1.11.0', 'import_kintone_insert_post_data' );

		$post_title = '';
		if ( isset( $kintone_data['record'][ $field_code_for_post_title ] ) && $kintone_data['record'][ $field_code_for_post_title ]['value'] ) {
			$post_title = $kintone_data['record'][ $field_code_for_post_title ]['value'];
		}

		$post_content = '';
		if ( isset( $kintone_data['record'][ $field_code_for_post_contents ] ) && $kintone_data['record'][ $field_code_for_post_contents ]['value'] ) {
			$post_content = $kintone_data['record'][ $field_code_for_post_contents ]['value'];
		}

		$post_author = '';
		$post_author = apply_filters_deprecated( 'import_kintone_insert_post_author', array( $post_author ), '1.11.0', 'import_kintone_insert_post_data' );

		$insert_post_data = array(
			'post_type'    => $this->kintone_to_wp_reflect_post_type,
			'post_title'   => $post_title,
			'post_content' => $post_content,
			'post_status'  => $post_status,
			'post_author'  => $post_author,
			'post_date'    => '',
		);
		/**
		 * Filters Change Post's parameter from kintone information.
		 *
		 * @param array $insert_post_data .
		 * @param array $kintone_data .
		 *
		 * @since 1.10.0
		 */
		$insert_post_data = apply_filters( 'import_kintone_insert_post_data', $insert_post_data, $kintone_data );
		$post_id          = null;
		if ( ! empty( $insert_post_data ) ) {
			$post_id = wp_insert_post( $insert_post_data );
		}

		return $post_id;

	}

	/**
	 * Update kintone data to WP post
	 *
	 * @param int   $post_id .
	 * @param array $kintone_data .
	 *
	 * @return int|null|WP_Error
	 */
	private function update_kintone_data_to_wp_post( $post_id, $kintone_data ) {

		$post_title = '';
		if ( ! empty( $this->kintone_to_wp_kintone_field_code_for_post_title ) && array_key_exists( $this->kintone_to_wp_kintone_field_code_for_post_title, $kintone_data['record'] ) ) {
			$post_title = $kintone_data['record'][ $this->kintone_to_wp_kintone_field_code_for_post_title ]['value'];
		}
		$_post         = get_post( $post_id );
		$post_contents = $_post->post_content;
		if ( ! empty( $this->kintone_to_wp_kintone_field_code_for_post_contents ) && array_key_exists( $this->kintone_to_wp_kintone_field_code_for_post_contents, $kintone_data['record'] ) ) {
			$post_contents = $kintone_data['record'][ $this->kintone_to_wp_kintone_field_code_for_post_contents ]['value'];
		}

		$update_post_data = array(
			'ID'           => $post_id,
			'post_type'    => $this->kintone_to_wp_reflect_post_type,
			'post_title'   => $post_title,
			'post_content' => $post_contents,
		);

		/**
		 * Filters Change Post's parameter from kintone information.
		 *
		 * @param array $insert_post_data .
		 * @param array $kintone_data .
		 *
		 * @since 1.10.0
		 */
		$update_post_data = apply_filters( 'import_kintone_update_post_data', $update_post_data, $kintone_data );

		$post_id = null;
		if ( ! empty( $update_post_data ) ) {
			$post_id = wp_update_post( $update_post_data, true );
			if ( is_wp_error( $post_id ) ) {
				$errors = $post_id->get_error_messages();
				foreach ( $errors as $error ) {
					error_log( var_export( $error, true ) );
				}
			}
		}
		do_action( 'import_kintone_after_update_post_data', $update_post_data, $kintone_data );

		return $post_id;

	}

	/**
	 * Update kintone data to wp post meta
	 *
	 * @param int   $post_id .
	 * @param array $kintone_data .
	 *
	 * @return void
	 */
	private function update_kintone_data_to_wp_post_meta( $post_id, $kintone_data ) {

		$setting_custom_fields = $this->kintone_to_wp_setting_custom_fields;
		if ( empty( $setting_custom_fields ) ) {
			return;
		}

		// update kintone_id.
		update_post_meta( $post_id, 'kintone_record_id', $kintone_data['record']['$id']['value'] );

		foreach ( $setting_custom_fields as $key => $kintone_fieldcode ) {

			if ( $kintone_fieldcode ) {

				if ( 'USER_SELECT' === $kintone_data['record'][ $key ]['type'] ) {

					update_post_meta( $post_id, $kintone_fieldcode, $kintone_data['record'][ $key ]['value'] );

				} elseif ( 'FILE' === $kintone_data['record'][ $key ]['type'] ) {

					if ( ! empty( $kintone_data['record'][ $key ]['value'] ) ) {
						$this->update_kintone_temp_file_to_meta( $post_id, $kintone_data['record'][ $key ]['value'][0], $kintone_fieldcode );
					} else {
						$this->delete_kintone_temp_file( $post_id, $kintone_fieldcode );
					}
				} elseif ( 'CREATOR' === $kintone_data['record'][ $key ]['type'] || 'MODIFIER' === $kintone_data['record'][ $key ]['type'] ) {

					update_post_meta( $post_id, $kintone_fieldcode . '_code', $kintone_data['record'][ $key ]['value']['code'] );
					update_post_meta( $post_id, $kintone_fieldcode . '_name', $kintone_data['record'][ $key ]['value']['name'] );

				} elseif ( 'SUBTABLE' === $kintone_data['record'][ $key ]['type'] ) {

					update_post_meta( $post_id, $kintone_fieldcode, $kintone_data['record'][ $key ]['value'] );

				} elseif ( 'DATETIME' === $kintone_data['record'][ $key ]['type'] ) {

					$value = date_i18n( 'Y-m-d H:i', strtotime( $kintone_data['record'][ $key ]['value'] ) + ( 9 * 60 * 60 ) );
					update_post_meta( $post_id, $kintone_fieldcode, $value );

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

	/**
	 * Update kintone data to WP terms
	 *
	 * @param int   $post_id .
	 * @param array $kintone_data .
	 *
	 * @return void
	 */
	private function update_kintone_data_to_wp_terms( $post_id, $kintone_data ) {

		$kintone_field_code_for_terms = $this->kintone_to_wp_kintone_field_code_for_terms;

		if ( ! empty( $kintone_field_code_for_terms ) ) {
			foreach ( $kintone_field_code_for_terms as $key => $kintone_field_code_for_term ) {

				if ( isset( $kintone_data['record'][ $kintone_field_code_for_term ] ) ) {

					$terms = array();
					if ( 'USER_SELECT' === $kintone_data['record'][ $kintone_field_code_for_term ]['type'] ) {

						foreach ( $kintone_data['record'][ $kintone_field_code_for_term ]['value'] as $user ) {
							$terms[] = $user['name'];
						}
					} else {
						$terms = $kintone_data['record'][ $kintone_field_code_for_term ]['value'];
					}

					if ( ! is_array( $terms ) ) {
						$terms = array( $terms );
					}

					wp_set_object_terms( $post_id, $terms, $key );
				}
			}
		}
	}

	/**
	 * Change kintone array value to string
	 *
	 * @param array $kintone_data .
	 *
	 * @return string
	 */
	private function make_kintone_array_to_string( $kintone_data ) {

		$record_data = $kintone_data;

		if ( is_array( $record_data ) ) {

			$record_data = implode( ',', $record_data );
		}

		return $record_data;
	}

	/**
	 * Get kintone template file
	 *
	 * @param string $filekey .
	 *
	 * @return array|mixed|WP_Error
	 */
	private function get_kintone_temp_file( $filekey ) {

		$url = 'https://' . get_option( 'kintone_to_wp_kintone_url' ) . '/k/v1/file.json?fileKey=' . $filekey;

		return Kintone_Utility::kintone_api( $url, $this->kintone_to_wp_kintone_api_token, true );

	}

	/**
	 * Delete kintone template file
	 *
	 * @param int     $post_id .
	 * @param string  $kintone_fieldcode .
	 * @param boolean $featured_image_flag .
	 *
	 * @return void
	 */
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

	/**
	 * Update template file of kintone to WP meta
	 *
	 * @param int     $post_id .
	 * @param array   $temp_base_data .
	 * @param string  $post_meta_name .
	 * @param boolean $featured_image_flag .
	 *
	 * @return void
	 */
	private function update_kintone_temp_file_to_meta( $post_id, $temp_base_data, $post_meta_name, $featured_image_flag = false ) {

		if ( '0' === $temp_base_data['size'] ) {
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
	 * @param $record_id int .
	 * @param $update_kintone_data array .
	 *
	 * @return array|false|mixed|\WP_Error|\WP_Post|null
	 */
	private function delete( $record_id ) {

		$args      = array(
			'post_type'   => $this->kintone_to_wp_reflect_post_type,
			'meta_key'    => 'kintone_record_id',
			'meta_value'  => $record_id,
			'post_status' => array( 'publish', 'pending', 'draft', 'future', 'private' ),
		);
		$the_query = new WP_Query( $args );
		if ( $the_query->have_posts() ) {
			return wp_delete_post( $the_query->post->ID );
		} else {

			// 削除できなかったらアプリコード付きで削除する.
			$url              = 'https://' . get_option( 'kintone_to_wp_kintone_url' ) . '/k/v1/app.json?id=' . get_option( 'kintone_to_wp_target_appid' );
			$kintone_app_data = Kintone_Utility::kintone_api( $url, $this->kintone_to_wp_kintone_api_token );

			if(is_wp_error($kintone_app_data)){
				return $kintone_app_data;
			}

			if ( $kintone_app_data['code'] ) {
				$args      = array(
					'post_type'   => $this->kintone_to_wp_reflect_post_type,
					'meta_key'    => 'kintone_record_id',
					'meta_value'  => $kintone_app_data['code'] . '-' . $record_id,
					'post_status' => array( 'publish', 'pending', 'draft', 'future', 'private' ),
				);
				$the_query = new WP_Query( $args );
				if ( $the_query->have_posts() ) {
					return wp_delete_post( $the_query->post->ID );
				}
			}
		}
		return null;
	}

	/**
	 * KintoneのRECORD_NUMBERフィールドの値を取得する
	 *
	 * @param array $kintoen_data .
	 * @return string
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
