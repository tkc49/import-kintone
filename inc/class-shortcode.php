<?php

/**
 * Shortcodeクラス
 */
class Shortcode {
	/**
	 * コンストラクト
	 */
	public function __construct() {
		add_shortcode( 'publish_kintone_data', array( $this, 'show_custom_field' ) );
	}

	/**
	 * カスタムフィールドの値を表示する
	 *
	 * @param array $atts .
	 *
	 * @return string
	 */
	public function show_custom_field( $atts ) {

		if ( get_option( 'kintone_to_wp_reflect_post_type' ) !== get_post_type() ) {
			return 'It is not the post type that is set in the "Public kintone data" plugin.';
		}

		$param            = shortcode_atts(
			array(
				'custom_field_key' => '',
				'format'           => '',
			),
			$atts
		);
		$custom_field_key = $param['custom_field_key'];
		$format           = $param['format'];

		if ( '' === $custom_field_key ) {
			return 'Set the custom_field_key parameter to the shortcode.( [publish_kintone_data custom_field_key="xxx"] )';
		}

		$value = get_post_meta( get_the_ID(), $custom_field_key, true );

		if ( '' === $format ) {
			return $value;
		}

		if ( '' === $value ) {
			return $value;
		}

		if ( 'number_format' === $format ) {
			$value = number_format( $value );
		} else {
			$value = date_i18n( $format, $value );
		}
		return $value;

	}

}
new Shortcode();
