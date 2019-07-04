<?php
/**
 * Publush kintone Data
 *
 * @package Publish_Kintone_Data
 *
 * @wordpress-plugin
 * Plugin Name: Publish kintone data
 * Plugin URI:
 * Description: The data of kintone can be reflected on WordPress.
 * Version:     1.5.0
 * Author:      Takashi Hosoya
 * Author URI:  http://ht79.info/
 * License:     GPLv2
 * Text Domain: kintone-to-wp
 * Domain Path: /languages
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

define( 'KINTONE_TO_WP_URL', plugins_url( '', __FILE__ ) );
define( 'KINTONE_TO_WP_PATH', dirname( __FILE__ ) );


$data = get_file_data(
	__FILE__,
	array(
		'ver'   => 'Version',
		'langs' => 'Domain Path',
	)
);
define( 'KINTONE_TO_WP_VERSION', $data['ver'] );
define( 'KINTONE_TO_WP_LANGS', $data['langs'] );


load_plugin_textdomain(
	'kintone-to-wp',
	false,
	dirname( plugin_basename( __FILE__ ) ) . KINTONE_TO_WP_LANGS
);

// 管理画面の時のみ実行.
require_once dirname( __FILE__ ) . '/includes/class-publish-kintone-data.php';
$kintone_to_wp = new Publish_Kintone_Data();
$kintone_to_wp->register();

