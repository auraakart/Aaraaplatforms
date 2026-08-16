<?php

/**
 * Fired during plugin deactivation
 *
 * @link       https://www.webtoffee.com/
 * @since      1.0.0
 *
 * @package    Wt_Import_Export_For_Woo
 * @subpackage Wt_Import_Export_For_Woo/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    Wt_Import_Export_For_Woo
 * @subpackage Wt_Import_Export_For_Woo/includes
 * @author     Webtoffee <info@webtoffee.com>
 */
class Wt_Import_Export_For_Woo_Deactivator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function deactivate() {
		delete_option('wt_iew_is_active');
	}

}
