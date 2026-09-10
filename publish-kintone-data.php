<?php
/**
 * Plugin Name: Publish kintone data
 * Plugin URI:
 * Description: The data of kintone can be reflected on WordPress.
 * Version:     1.15.1
 * Author:      Takashi Hosoya
 * Author URI:  http://ht79.info/
 * License:     GPLv2
 * Text Domain: import-kintone
 *
 * @package import-kintone
 */

/**
 * Copyright (c) 2017 Takashi Hosoya ( http://ht79.info/ )
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2 or, at
 * your discretion, any later version, as published by the Free
 * Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

namespace publish_kintone_data;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'KINTONE_TO_WP_URL', plugins_url( '', __FILE__ ) );
define( 'KINTONE_TO_WP_PATH', __DIR__ );

$data = get_file_data(
	__FILE__,
	array(
		'ver' => 'Version',
	)
);

define( 'KINTONE_TO_WP_VERSION', $data['ver'] );

/*
 * load_plugin_textdomain() は呼ばない。WordPress.org 配布のプラグインは
 * 4.6 以降、翻訳が必要になった時点でコアが自動で読み込む。
 * 同梱の翻訳ファイルも無いため Domain Path も持たない.
 */

/**
 * Initialize the plugin.
 *
 * @return void
 */
function init() {
	require_once KINTONE_TO_WP_PATH . '/inc/class-kintone-utility.php';

	require_once KINTONE_TO_WP_PATH . '/admin/class-admin.php';
	new Admin();

	require_once KINTONE_TO_WP_PATH . '/class-publish-kintone-data.php';
	new Publish_Kintone_Data();
}

add_action( 'plugins_loaded', 'publish_kintone_data\init', 10 );
