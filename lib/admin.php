<?php

// Start up the engine
class EXIM_Import_Admin
{

	/**
	 * this is our constructor.
	 * there are many like it, but this one is mine
	 */
	public function __construct() {
		add_action		(	'admin_enqueue_scripts',			array(	$this,	'scripts_styles'				),	10		);
		add_action		(	'post_submitbox_misc_actions',		array(	$this,	'post_button_side'				)			);
		add_action		(	'admin_menu',						array(	$this,	'batch_process_menu'			)			);
	}

	/**
	 * load our JS file
	 * @return [type]       [description]
	 */
	public function scripts_styles() {
		// get my current screen
		$screen	= get_current_screen();

		// bail if my screen isn't an object
		if ( ! is_object( $screen ) ) {
			return;
		}

		// get my allowed types
		$allow	= EXIM_Import_Helper::post_types_allowed();

		if ( $screen->base == 'media_page_exim-import-images' || in_array( $screen->post_type, $allow ) ) {

			wp_enqueue_style( 'exim-admin', plugins_url( '/css/exim.admin.css', __FILE__ ), array(), EXIM_IMPORT_VER, 'all' );

			wp_enqueue_script( 'exim-admin', plugins_url( '/js/exim.admin.js', __FILE__ ), array( 'jquery' ), EXIM_IMPORT_VER, true );
			wp_localize_script( 'exim-admin', 'eximAdmin', array(
				'defaultMessage'	=> __( 'There was an error in the process. Please try again later.', 'external-image-import' ),
			));

		}

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
		$allow	= EXIM_Import_Helper::post_types_allowed();
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
	 * [batch_process_menu description]
	 * @return [type] [description]
	 */
	public function batch_process_menu() {
		add_media_page( __( 'Import External Images', 'external-image-import' ), __( 'Import External Images', 'external-image-import' ), 'manage_options', 'exim-import-images', array( $this, 'batch_process_page' ) );
	}

	/**
	 * [batch_process_page description]
	 * @return [type] [description]
	 */
	public function batch_process_page() {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Cheatin&#8217; uh?' ) );
		}

		// fetch our total count
		$total	= EXIM_Import_Helper::get_content_items( true );

		echo '<div class="wrap">';
			echo '<h2>' . __( 'Import External Images', 'external-image-import' ) . '</h2>';
			echo '<p>' . __( 'Run the process below to comb through all your site content and attempt to find externally hosted images. Be patient, this may take a while.', 'external-image-import' ) . '</p>';

			if ( ! empty( $total ) ) {
				// our info count
				echo '<p>' . sprintf( _n( 'There is %d item to check.', 'There are %d items to check.', $total, 'external-image-import' ), $total ) . '</p>';

				// our empty message field

				// our form setup
				echo '<form>';

					echo '<p class="submit">';
						echo '<input type="button" name="exim-batch-process" id="exim-batch-process" class="button button-secondary" value="' . __( 'Begin Process', 'external-image-import' ) . '">';
						echo '<i class="dashicons dashicons-update rotating exim-admin-spinner"></i>';
					echo '</p>';

					// set our hidden fields
					wp_nonce_field( 'exim-batch-nonce' );
					echo '<input type="hidden" class="exim-batch-count" value="10">';
					echo '<input type="hidden" class="exim-batch-offset" value="0">';

				echo '</form>';

			} else {

				echo '<p>' . __( 'You have no content on your site.', 'external-image-import' ) . '</p>';
			}

		echo '</div>';

	}

	/// end class
}

new EXIM_Import_Admin();
