<?php
/**
 * Kintone を接続するクラス
 *
 * @package Publish_Kintone_Data
 */

/**
 * Kintone
 */
class Kintone_API {

	/**
	 * Kintoneのドメイン.
	 *
	 * @var string
	 */
	private $kintone_domain = '';

	/**
	 * Kintoneのトークン.
	 *
	 * @var string
	 */
	private $kintone_token = '';

	/**
	 * KintoneのアプリID.
	 *
	 * @var string
	 */
	private $kintone_app_id = '';


	/**
	 * Constructor
	 *
	 * @param string $kintone_domain kintoneのドメイン.
	 * @param string $kintone_token kintoneのトークン.
	 * @param string $kintone_app_id kintoneのアプリID.
	 */
	public function __construct( $kintone_domain, $kintone_token, $kintone_app_id ) {
		$this->kintone_domain = $kintone_domain;
		$this->kintone_token  = $kintone_token;
		$this->kintone_app_id = $kintone_app_id;

	}

	/**
	 * Kintoneの情報を取得する.
	 *
	 * @param string $token kintoneで設定したトークン.
	 * @param int    $app_no kintoneのアプリ番号.
	 *
	 * @return array|object 取得したkintoneのデータ
	 */
	public function get( $token, $app_no ) {
		return array();
	}

	/**
	 * レコードIDを使ってKintoneの情報を取得する.
	 *
	 * @param int $record_id kintoneのレコード番号.
	 *
	 * @return array kintoneのデータ.
	 */
	public function get_by_id( $record_id ) {
		$url          = $this->generate_kintone_url( $record_id );
		$kintone_data = $this->connect_to_kintone( $url );

		return $kintone_data;
	}

	/**
	 * 全レコードを取得する
	 *
	 * @return array kintoneのデータ.
	 */
	public function get_all() {
		$kintone_data = array();

		$offset             = 0;
		$reacquisition_flag = true;

		while ( $reacquisition_flag ) {

			$url        = $this->generate_kintone_url_for_all( $offset );
			$retun_data = $this->connect_to_kintone( $url );

			$kintone_data = array_merge( $kintone_data, $retun_data['records'] );

			if ( count( $kintone_data ) < 500 ) {
				$reacquisition_flag = false;
			} else {
				$offset = $offset + 500;
			}
		}

		return $kintone_data;
	}

	/**
	 * Kintoneの添付ファイルの情報を取得する.
	 *
	 * @param string $file_key ファイルキー.
	 *
	 * @return array kintoneのデータ.
	 */
	public function get_attached_file( $file_key ) {
		$url          = $this->generate_kintone_url_for_attached_file( $file_key );
		$kintone_data = $this->connect_to_kintone( $url, true );

		return $kintone_data;
	}


	/**
	 * Kintoneのフォーム情報を取得する.
	 *
	 * @return array
	 */
	public function get_kintone_form() {
		$url          = $this->generate_kintone_url_for_form();
		$kintone_data = $this->connect_to_kintone( $url );

		return $kintone_data;
	}

	/**
	 * Kintoneへ接続する.
	 *
	 * @param string  $url kintoneへの接続先URL.
	 * @param boolean $attached_file_flag 添付ファイルフラグ.
	 *
	 * @return array|WP_Error
	 */
	private function connect_to_kintone( $url, $attached_file_flag = false ) {

		if ( $url ) {

			$headers = array( 'X-Cybozu-API-Token' => $this->kintone_token );

			$res = wp_remote_get(
				$url,
				array(
					'headers' => $headers,
				)
			);

			if ( ! is_wp_error( $res ) ) {

				if ( $attached_file_flag ) {
					$kintone_data = $res['body'];
				} else {
					$kintone_data = json_decode( $res['body'], true );
				}

				if ( isset( $kintone_data['message'] ) && isset( $kintone_data['code'] ) ) {

					echo '<div class="error fade"><p><strong>' . esc_html( $kintone_data['message'] ) . '</strong></p></div>';

					return new WP_Error( $kintone_data['code'], $kintone_data['message'] );
				}

				return $kintone_data;

			} else {

				echo '<div class="error fade"><p><strong>' . esc_html( $res->get_error_message() ) . '</strong></p></div>';

				return $res;

			}
		} else {
			echo '<div class="error fade"><p><strong>URL is required</strong></p></div>';

			return new WP_Error( 'Error', 'URL is required' );
		}
	}

	/**
	 * Kintoneへ接続するURLを生成する.
	 *
	 * @param int $record_id kintoneのレコード番号.
	 *
	 * @return string kintoneへ接続するURL
	 */
	private function generate_kintone_url( $record_id = null ) {

		if ( is_null( $record_id ) ) {
			$url = 'https://' . $this->kintone_domain . '/k/v1/record.json?app=' . $this->kintone_app_id;
		} else {
			$url = 'https://' . $this->kintone_domain . '/k/v1/record.json?app=' . $this->kintone_app_id . '&id=' . $record_id;
		}

		return $url;
	}

	/**
	 * Kintoneの添付ファイルを取得するための接続するURLを生成する.
	 *
	 * @param string $filekey ファイルキー.
	 *
	 * @return string kintoneへ接続するURL
	 */
	private function generate_kintone_url_for_attached_file( $filekey ) {
		$url = 'https://' . $this->kintone_domain . '/k/v1/file.json?fileKey=' . $filekey;

		return $url;
	}

	/**
	 * Kintoneのフォーム設計情報を取得するための接続するURLを生成する.
	 *
	 * @return string kintoneへ接続するURL
	 */
	private function generate_kintone_url_for_form() {
		$url = 'https://' . $this->kintone_domain . '/k/v1/form.json?app=' . $this->kintone_app_id;

		return $url;
	}

	/**
	 * Kintoneへ全件取得するためのURLを生成する.
	 *
	 * @param int $offset オフセット.
	 *
	 * @return string kintoneへ接続するURL
	 */
	private function generate_kintone_url_for_all( $offset ) {

		$url = 'https://' . $this->kintone_domain . '/k/v1/records.json?app=' . $this->kintone_app_id . '&query=order by $id asc limit 500 offset ' . $offset;

		return $url;
	}

}

