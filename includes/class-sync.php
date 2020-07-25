<?php
/**
 * Kintoneの情報をWordPressへ反映する.
 *
 * @package Publish_Kintone_Data
 */

/**
 * Sync
 */
class Sync {

	/**
	 * Kintoneクラス.
	 *
	 * @var Kintone_API
	 */
	private $kintone_api;


	/**
	 * Constructor
	 */
	public function __construct() {
	}

	/**
	 * Kintone のデータをWordPressへ反映する.
	 *
	 * @param array       $kintoen_data kintoneのデータ.
	 * @param Kintone_API $kintone_api .
	 *
	 * @return void
	 */
	public function main( $kintoen_data, $kintone_api ) {

		$this->kintone_api = $kintone_api;

		$args      = array(
			'post_type'   => get_option( 'kintone_to_wp_reflect_post_type' ),
			'meta_key'    => 'kintone_record_id',
			'meta_value'  => $kintoen_data['record']['$id']['value'],
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

			// WordPressにデータが存在するので、UPDATE or DELETE の処理をする.
			if ( 'normal' === $kintoen_data['kintone_to_wp_status'] ) {
				$this->update_kintone_data_to_wp_post( $the_query->post->ID, $kintoen_data );
				$this->update_kintone_data_to_wp_post_meta( $the_query->post->ID, $kintoen_data );
				$this->update_kintone_data_to_wp_terms( $the_query->post->ID, $kintoen_data );
				$this->update_kintone_data_to_wp_post_featured_image( $the_query->post->ID, $kintoen_data );

			} elseif ( 'delete' === $kintoen_data['kintone_to_wp_status'] ) {
				wp_delete_post( $the_query->post->ID );
			}
		} else {

			// WordPressにデータが存在しないので、INSERT 処理をする.
			if ( 'normal' === $kintoen_data['kintone_to_wp_status'] ) {
				$post_id = $this->insert_kintone_data_to_wp_post( $kintoen_data );
				$this->update_kintone_data_to_wp_post_meta( $post_id, $kintoen_data );
				$this->update_kintone_data_to_wp_terms( $post_id, $kintoen_data );
				$this->update_kintone_data_to_wp_post_featured_image( $post_id, $kintoen_data );
			}
		}
	}

	/**
	 * Kintone のデータをWordPressに更新する
	 *
	 * @param int   $post_id WordPressの記事ID.
	 * @param array $kintoen_data 更新するkintoneデータ.
	 *
	 * @return void
	 */
	private function update_kintone_data_to_wp_post( $post_id, $kintoen_data ) {

		$post_title = '';
		if ( array_key_exists( get_option( 'kintone_to_wp_kintone_field_code_for_post_title' ), $kintoen_data['record'] ) ) {
			$post_title = $kintoen_data['record'][ get_option( 'kintone_to_wp_kintone_field_code_for_post_title' ) ]['value'];
		}

		$my_post = array(
			'ID'         => $post_id,
			'post_type'  => get_option( 'kintone_to_wp_reflect_post_type' ),
			'post_title' => $post_title,
		);

		$post_id = wp_update_post( $my_post );
		if ( is_wp_error( $post_id ) ) {
			$errors = $post_id->get_error_messages();
			foreach ( $errors as $error ) {
				error_log( var_export( $error, true ) );
			}
		}

	}

	/**
	 * KintoneデータをPost Metaに反映する.
	 *
	 * @param int   $post_id WordPressの記事ID.
	 * @param array $kintone_data kintoneのデータ.
	 *
	 * @return void
	 */
	private function update_kintone_data_to_wp_post_meta( $post_id, $kintone_data ) {

		$setting_custom_fields = get_option( 'kintone_to_wp_setting_custom_fields' );

		// update kintone_id.
		update_post_meta( $post_id, 'kintone_record_id', $kintone_data['record']['$id']['value'] );

		foreach ( $setting_custom_fields as $key => $kintone_fieldcode ) {

			if ( $kintone_fieldcode ) {

				$record_data = '';

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

	/**
	 * WordPressのメディアファイルを削除する.
	 *
	 * @param int    $post_id WordPressの記事ID.
	 * @param string $kintone_fieldcode WordPressのpost metaに設定した名前.
	 *
	 * @return void
	 */
	private function delete_kintone_temp_file( $post_id, $kintone_fieldcode, $featured_image_flag = false ) {


		$attachment_id = get_post_meta( $post_id, $kintone_fieldcode, true );
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
	 * Kintoneの配列データををコンマ付きの文字列に変換する.
	 *
	 * @param array $kintone_data Kintoneから取得したデータ.
	 *
	 * @return string 文字列置換後のデータ.
	 */
	private function make_kintone_array_to_string( $kintone_data ) {

		$record_data = $kintone_data;

		if ( is_array( $record_data ) ) {

			$record_data = implode( ',', $record_data );
		}

		return $record_data;
	}

	/**
	 * WordPressのTermを更新する
	 *
	 * @param int   $post_id WordPressの記事ID.
	 * @param array $kintone_data kintoneのデータ.
	 *
	 * @return void
	 */
	private function update_kintone_data_to_wp_terms( $post_id, $kintone_data ) {

		$kintone_field_code_for_terms = get_option( 'kintone_to_wp_kintone_field_code_for_terms' );

		foreach ( $kintone_field_code_for_terms as $key => $kintone_field_code_for_term ) {

			if ( isset( $kintone_data['record'][ $kintone_field_code_for_term ] ) ) {
				$terms = $kintone_data['record'][ $kintone_field_code_for_term ]['value'];

				if ( ! is_array( $terms ) ) {
					$terms = array( $terms );
				}

				$return = wp_set_object_terms( $post_id, $terms, $key );
			}
		}
	}

	private function update_kintone_data_to_wp_post_featured_image( $post_id, $kintone_data ) {

		$setting_kintone_field_code_for_featured_image = '';
		if ( array_key_exists( get_option( 'kintone_to_wp_kintone_field_code_for_featured_image' ), $kintone_data['record'] ) ) {
			$setting_kintone_field_code_for_featured_image = get_option( 'kintone_to_wp_kintone_field_code_for_featured_image' );
		}

		if ( $kintone_data['record'][ $setting_kintone_field_code_for_featured_image ]['type'] == 'FILE' ) {
			if ( ! empty( $kintone_data['record'][ $setting_kintone_field_code_for_featured_image ]['value'] ) ) {
				$this->update_kintone_temp_file_to_meta( $post_id, $kintone_data['record'][ $setting_kintone_field_code_for_featured_image ]['value'][0], '', true );
			} else {
				$this->delete_kintone_temp_file( $post_id, '', true );
			}
		}

	}


	/**
	 * 記事を作成する.
	 *
	 * @param array $kintoen_data Kintone のデータ.
	 *
	 * @return int WordPressの記事ID.
	 */
	private function insert_kintone_data_to_wp_post( $kintoen_data ) {

		$field_code_for_post_title = get_option( 'kintone_to_wp_kintone_field_code_for_post_title' );

		$post_status = 'draft';
		$post_status = apply_filters( 'import_kintone_insert_post_status', $post_status );

		$post_id = wp_insert_post(
			array(
				'post_type'   => get_option( 'kintone_to_wp_reflect_post_type' ),
				'post_title'  => $kintoen_data['record'][ $field_code_for_post_title ]['value'],
				'post_status' => $post_status,
			)
		);

		return $post_id;

	}

	/**
	 * Kintoneの添付ファイルをWordPressのメディアに保存する.
	 *
	 * @param int    $post_id WordPressの記事ID.
	 * @param array  $temp_base_data Kintoneから取得した添付ファイルのデータ.
	 * @param string $post_meta_name 保存するpost meta の名前.
	 *
	 * @return void
	 */
	private function update_kintone_temp_file_to_meta( $post_id, $temp_base_data, $post_meta_name, $featured_image_flag = false ) {


		$file_data = $this->kintone_api->get_attached_file( $temp_base_data['fileKey'] );

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

		$attachment = array(
			'post_title'     => $temp_base_data['name'],
			'post_mime_type' => $file['type'],
			'post_parent'    => $post_id,
		);

		$aid         = wp_insert_attachment( $attachment, $file['file'], $post_id );
		$attach_data = wp_generate_attachment_metadata( $aid, $file['file'] );

		if ( $featured_image_flag ) {

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


}
