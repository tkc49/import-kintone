<?php
namespace publish_kintone_data;
class Kintone_Utility {

	/**
	 * KintoneがWebhookを実行するときに使うIPアドレスかどうかチェックする.
	 *
	 * @param string $remote_ip .
	 *
	 * @return boolean
	 */
	public static function check_kintone_ip_adress( $remote_ip ) {

		$accept_after_aws_ip_list = array( '52.32.46.167', '35.155.158.164' );
		if ( in_array( $remote_ip, $accept_after_aws_ip_list, true ) ) {
			return true;
		}

		$accept_before_aws = '103.79.14.0/24';
		list( $accept_ip, $mask ) = explode( '/', $accept_before_aws );
		$accept_long = ip2long( $accept_ip ) >> ( 32 - $mask );
		$remote_long = ip2long( $remote_ip ) >> ( 32 - $mask );
		if ( $accept_long == $remote_long ) {
			return true;
		} else {
			if ( self::is_private_address( $remote_ip ) ) {
				return true;
			}
		}

		return false;
	}
	public static function kintone_api( $request_url, $kintone_token, $file = false ) {

		if ( $request_url ) {

			$headers = array( 'X-Cybozu-API-Token' => $kintone_token );

			$res = wp_remote_get(
				$request_url,
				array(
					'headers' => $headers
				)
			);

			if ( is_wp_error( $res ) ) {

				return $res;

			} else {

				if ( $file ) {
					$return_value = $res['body'];
				} else {
					$return_value = json_decode( $res['body'], true );
				}

				if ( isset( $return_value['message'] ) && isset( $return_value['code'] ) ) {

					echo '<div class="error fade"><p><strong>' . $return_value['message'] . '</strong></p></div>';

					return new \WP_Error( $return_value['code'], $return_value['message'] );
				}

				return $return_value;
			}
		} else {
			echo '<div class="error fade"><p><strong>URL is required</strong></p></div>';

			return new \WP_Error( 'Error', 'URL is required' );
		}

	}

	private static function is_private_address( $ip = null ) {
		is_null( $ip ) and $ip = $_SERVER['REMOTE_ADDR'];

		$iplong = ip2long( $ip );
		$iplong = sprintf( '%u', $iplong ); // for 32bit architecture

		return ( $iplong >= 167772160 && $iplong <= 184549375 ) || ( $iplong >= 2886729728 && $iplong <= 2887778303 ) || ( $iplong >= 3232235520 && $iplong <= 3232301055 );
	}

}
