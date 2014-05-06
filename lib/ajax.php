<?php

// Start up the engine
class EXIM_Import_Ajax
{

	/**
	 * this is our constructor.
	 * there are many like it, but this one is mine
	 */
	public function __construct() {
		add_action		(	'wp_ajax_exim_image_process',		array(	$this,	'exim_image_process'			)			);
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
		$external_data	= EXIM_Import_Helper::get_external_images( $post_id );

		// bail if we have no external images
		if( ! $external_data || empty( $external_data ) ) {
			$ret['success'] = false;
			$ret['errcode']	= 'NO_IMAGES';
			$ret['message']	= __( 'No external images were found in this content.', 'external-image-import' );
			echo json_encode( $ret );
			die();
		}

		// set an array for the uploaded URLs
		$uploaded_data	= array();

		//now loop through and update
		foreach( $external_data as $external_item ) {
			$uploaded_data[]	= EXIM_Import_Helper::upload_external_image( $post_id, $external_item );
		}

		// make sure images got uploaded
		if( ! $uploaded_data || empty( $uploaded_data ) ) {
			$ret['success'] = false;
			$ret['errcode']	= 'NO_UPLOAD';
			$ret['message']	= __( 'The external images could not be uploaded. Please try again later.', 'external-image-import' );
			echo json_encode( $ret );
			die();
		}

		// add meta strings of our new images for replacement
		$meta		= EXIM_Import_Helper::update_replacement_meta( $post_id, $external_data, $uploaded_data );

		// now run our search / replace in the content
		$updated	= EXIM_Import_Helper::replace_image_urls( $post_id, $external_data, $uploaded_data );

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

			// allow for other actions that may be related to images
			do_action( 'exim_after_upload', $post_id );

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


	/// end class
}

new EXIM_Import_Ajax();
