<?php
/*
Plugin Name: External Image Import
Plugin URI: http://reaktivstudios.com/
Description: Search a post or page for external image URLs and import them into WP
Author: Andrew Norcross
Version: 0.0.1
Requires at least: 3.7
Author URI: http://reaktivstudios.com/
*/
/*  Copyright 2014 Andrew Norcross

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; version 2 of the License (GPL v2) only.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/


if( ! defined( 'EXIM_IMPORT_BASE ' ) ) {
	define( 'EXIM_IMPORT_BASE', plugin_basename(__FILE__) );
}

if( ! defined( 'EXIM_IMPORT_DIR' ) ) {
	define( 'EXIM_IMPORT_DIR', plugin_dir_path( __FILE__ ) );
}

if( ! defined( 'EXIM_IMPORT_VER' ) ) {
	define( 'EXIM_IMPORT_VER', '0.0.1' );
}


class EXIM_Import_Core
{

	/**
	 * Static property to hold our singleton instance
	 * @var $instance
	 */
	static $instance = false;

	/**
	 * this is our constructor.
	 * there are many like it, but this one is mine
	 */
	private function __construct() {
		add_action		(	'plugins_loaded',					array(  $this,  'textdomain'					)			);
		add_action		(	'plugins_loaded',					array(  $this,  'load_files'					)			);
		add_action		( 	'admin_notices',					array(	$this,	'domdoc_check'					)			);
	}

	/**
	 * If an instance exists, this returns it.  If not, it creates one and
	 * retuns it.
	 *
	 * @return $instance
	 */
	public static function getInstance() {

		if ( ! self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * load our textdomain for localization
	 *
	 * @return void
	 */
	public function textdomain() {

		load_plugin_textdomain( 'external-image-import', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * load our secondary files
	 * @return void
	 */
	public function load_files() {

		require_once( EXIM_IMPORT_DIR . 'lib/admin.php'		);
		require_once( EXIM_IMPORT_DIR . 'lib/ajax.php'		);
		require_once( EXIM_IMPORT_DIR . 'lib/helper.php'	);

	}

	/**
	 * make sure we have PHP DOMDocument and deactivate without it
	 * @return [type] [description]
	 */
	public function domdoc_check() {

		$screen = get_current_screen();

		if ( is_object( $screen ) && $screen->parent_file !== 'plugins.php' ) {
			return;
		}

		// not active. show message
		if ( ! class_exists( 'domDocument') ) :

			echo '<div id="message" class="error fade below-h2"><p><strong>'.__( 'This plugin requires the PHP DOMDocument library to function and cannot be activated.', 'external-image-import' ).'</strong></p></div>';

			// hide activation method
			unset( $_GET['activate'] );

			// deactivate YOURSELF
			deactivate_plugins( plugin_basename( __FILE__ ) );

		endif;

		return;

	}



/// end class
}

// Instantiate our class
$EXIM_Import_Core = EXIM_Import_Core::getInstance();