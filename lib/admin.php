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
	}

	/**
	 * load our JS file
	 * @param  [type] $hook [description]
	 * @return [type]       [description]
	 */
	public function scripts_styles( $hook ) {

		$screen	= get_current_screen();

		$allow	= EXIM_Import_Helper::post_types_allowed();

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


	/// end class
}

new EXIM_Import_Admin();
