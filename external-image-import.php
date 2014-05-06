<?php
/*
Plugin Name: External Image Import
Plugin URI: http://reaktivstudios.com/
Description: Add known terms into the WordPress blacklist keys to manage spam
Author: Andrew Norcross
Version: 1.0.0
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
	define( 'EXIM_IMPORT_VER', '1.0.0' );
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
		add_action		( 	'admin_notices',					array(	$this,	'domdoc_check'					)			);
		add_action		(	'admin_enqueue_scripts',			array(	$this,	'scripts_styles'				),	10		);
		add_action		(	'post_submitbox_misc_actions',		array(	$this,	'post_button_side'				)			);
		add_action		(	'wp_ajax_exim_image_process',		array(	$this,	'exim_image_process'			)			);
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

	/**
	 * load our JS file
	 * @param  [type] $hook [description]
	 * @return [type]       [description]
	 */
	public function scripts_styles( $hook ) {

		$screen	= get_current_screen();

		$allow	= self::post_types_allowed();

		if ( is_object( $screen ) && in_array( $screen->post_type, $allow ) ) :

			global $post;
			$redirect	= isset( $post ) && ! empty( $post ) ? get_edit_post_link( $post->ID ).'&exim-update=true' : null;

			wp_enqueue_script( 'exim-admin', plugins_url( '/js/exim.admin.js', __FILE__ ) , array( 'jquery' ), EXIM_IMPORT_VER, true );
			wp_localize_script( 'exim-admin', 'eximAdmin', array(
				'defaultMessage'	=> __( 'There was an error in the process. Please try again later.', 'external-image-import' ),
				'successRedirect'	=> esc_url( $redirect )
			));
		endif;

	}

	/**
	 * load our side button to check for external images
	 * @return [type] [description]
	 */
	public function post_button_side() {

		// first check that we have domDocument
		if ( ! class_exists( 'domDocument') ) {
			return;
		}

		global $post;

		// load on posts by default but filter
		$allow	= self::post_types_allowed();
		if ( ! in_array( $post->post_type, $allow ) ) {
			return;
		}

		// set my nonce
		$nonce	= wp_create_nonce( 'exim_process_nonce' );

		// build our button
		echo '<div id="exim-button-wrap" class="misc-pub-section">';
			echo '<p class="exim-process-button">';

			echo '<input type="button" class="button button-secondary" name="exim-process" id="exim-process" value="' . __( 'Check For External Images', 'external-image-import' ) . '" data-post-id="' . absint( $post->ID ) . '" data-nonce="' . $nonce . '" >';

			echo '<span class="spinner exim-spinner"></span>';

			echo '</p>';
		echo '</div>';

	}

	/**
	 * our ajax function
	 * @return [type] [description]
	 */
	public function exim_image_process() {

		// verify our nonce
		$check	= check_ajax_referer( 'exim_process_nonce', 'nonce', true );

		if( ! $check ) {
			$ret['success'] = false;
			$ret['errcode']	= 'INVALD_NONCE';
			$ret['message']	= __( 'The provided nonce was invalid.', 'external-image-import' );
			echo json_encode( $ret );
			die();
		}

		// make sure we got a post ID
		if( ! isset( $_POST['post_id'] ) || isset( $_POST['post_id'] ) && ! is_numeric( $_POST['post_id'] ) ) {
			$ret['success'] = false;
			$ret['errcode']	= 'NO_POST_ID';
			$ret['message']	= __( 'A missing or invalid post ID was provided.', 'external-image-import' );
			echo json_encode( $ret );
			die();
		}

		// do another check for domDocument
		if ( ! class_exists( 'domDocument') ) {
			$ret['success'] = false;
			$ret['errcode']	= 'NO_DOM_DOC';
			$ret['message']	= __( 'The PHP DOMDocument library is required for this plugin.', 'external-image-import' );
			echo json_encode( $ret );
			die();
		}

		// set our post ID
		$post_id	= absint( $_POST['post_id'] );

		// check for external images
		$external	= self::get_external_images( $post_id );

		// bail if we have no external images
		if( ! $external || empty( $external ) ) {
			$ret['success'] = false;
			$ret['errcode']	= 'NO_IMAGES';
			$ret['message']	= __( 'No external images were found in this content.', 'external-image-import' );
			echo json_encode( $ret );
			die();
		}

		// add a meta string of our external images for replacement
		update_post_meta( $post_id, '_exim_image_src', $external );

		// set an array for the uploaded URLs
		$uploaded	= array();

		//now loop through and update
		foreach( $external as $external_url ) {
			$uploaded[]	= self::upload_external_image( $post_id, $external_url );
		}

		// make sure images got uploaded
		if( ! $uploaded || empty( $uploaded ) ) {
			$ret['success'] = false;
			$ret['errcode']	= 'NO_UPLOAD';
			$ret['message']	= __( 'The external images could not be uploaded. Please try again later.', 'external-image-import' );
			echo json_encode( $ret );
			die();
		}

		// add a meta string of our new images for replacement
		update_post_meta( $post_id, '_exim_image_new', $uploaded );

		// now run our search / replace
		$updated	= self::replace_image_urls( $post_id, $external, $uploaded );

		// make sure images got uploaded
		if( ! $updated ) {
			$ret['success'] = false;
			$ret['errcode']	= 'NO_UPDATE';
			$ret['message']	= __( 'The URLs could not be updated in the content.', 'external-image-import' );
			echo json_encode( $ret );
			die();
		}

		// we have updated the content, we can go home now
		if( $updated ) {

			$ret['success']	= true;
			$ret['message']	= __( 'Success! The images have been imported and content has been updated.', 'external-image-import' );
			echo json_encode( $ret );
			die();
		}

		// our unknown
		$ret['success'] = false;
		$ret['errcode']	= 'UNKNOWN_ERROR';
		$ret['message']	= __( 'There was an unknown error. Please try again later.', 'external-image-import' );
		echo json_encode($ret);
		die();

	}


	/**
	 * search the content for any external images based on URL
	 * @param  [type] $post_id [description]
	 * @return [type]          [description]
	 */
	static function get_external_images( $post_id ) {

		// get our post content
		$content	= get_post_field( 'post_content', $post_id, 'raw' );

		if ( ! $content ) {
			return;
		}

		// start a blank array
		$data	= array();

		// now load PHPDOM
		$dom	= new domDocument;
		$dom->loadHTML( $content );
		$dom->preserveWhiteSpace = false;

		// look for images
		$images	= $dom->getElementsByTagName( 'img' );

		// no images. bail
		if ( ! $images ) {
			return;
		}

		// set an array
		$external	= array();

		// loop through and fetch the source
		foreach ( $images as $image ) {
			$external[]	= $image->getAttribute( 'src' );
		}

		// check for empty sources
		if ( empty( $external ) ) {
			return;
		}

		// loop through each one and check against the site URL
		foreach( $external as $key => $image_url ) {
			if ( false !== strpos( $image_url, home_url() ) ) {
				unset( $external[$key] );
			}
		}

		// no non-native images. bail.
		if ( empty( $external ) ) {
			return;
		}

		return array_unique( $external );

	}

	/**
	 * process the external image upload
	 * @param  [type] $post_id   [description]
	 * @param  [type] $image_url [description]
	 * @return [type]            [description]
	 */
	static function upload_external_image( $post_id, $image_url ) {

		// load media handlers
		require_once( ABSPATH . 'wp-admin' . '/includes/image.php'	);
		require_once( ABSPATH . 'wp-admin' . '/includes/file.php'	);
		require_once( ABSPATH . 'wp-admin' . '/includes/media.php'	);

		// create a temp file
		$temp_file	= download_url( $image_url );

		// make a file array
		$file_array	= array(
			'name'		=> basename( $image_url ),
			'tmp_name'	=> $temp_file
		);

		// Check for download errors
		if ( is_wp_error( $temp_file ) ) {
			@unlink( $file_array[ 'tmp_name' ] );
			return $temp_file;
		}

		// run sideload function
		$image_id = media_handle_sideload( $file_array, $post_id );
		// Check for handle sideload errors.
		if ( is_wp_error( $image_id ) ) {
			@unlink( $file_array['tmp_name'] );
			return $image_id;
		}

		// send back the image URL
		return wp_get_attachment_url( $image_id );

	}

	/**
	 * [replace_image_urls description]
	 * @param  [type] $post_id  [description]
	 * @param  [type] $external [description]
	 * @param  [type] $uploaded [description]
	 * @return [type]           [description]
	 */
	static function replace_image_urls( $post_id, $external, $uploaded ) {

		// get our post content
		$content	= get_post_field( 'post_content', $post_id, 'raw' );

		if ( ! $content ) {
			return;
		}

		// replace my URLs in content
		$content	= str_replace( $external, $uploaded, $content );

		// run the update
		$updated	= wp_update_post( array( 'ID' => $post_id, 'post_content' => $content ) );

		// return true / false
		if ( ! is_wp_error( $updated ) ) {
			return true;
		} else {
			return false;
		}

	}

	/**
	 * helper function to set which post types we are allowing this on
	 * used in both the button display and loading JS
	 *
	 * @return [type] [description]
	 */
	static function post_types_allowed() {

		return apply_filters( 'exim_post_types', array( 'post' ) );

	}

/// end class
}

// Instantiate our class
$EXIM_Import_Core = EXIM_Import_Core::getInstance();