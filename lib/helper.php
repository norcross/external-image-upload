<?php

// Start up the engine
class EXIM_Import_Helper
{

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

		// loop through and fetch the source and alt tag
		foreach ( $images as $image ) {
			$img_src	= $image->getAttribute( 'src' );
			$img_alt	= $image->getAttribute( 'alt' );

			$external[]	= array(
				'src'	=> $img_src,
				'alt'	=> $img_alt
			);

		}

		// check for empty sources
		if ( empty( $external ) ) {
			return;
		}

		// loop through each one and check against the site URL
		foreach( $external as $key => $image_data ) {

			// if for some reason the image src field is empty, bail
			if ( ! isset( $image_data['src'] ) || isset( $image_data['src'] ) && empty( $image_data['src'] ) ) {
				unset( $external[$key] );
			}

			// now run check against the site URL
			if ( false !== strpos( $image_data['src'], home_url() ) ) {
				unset( $external[$key] );
			}
		}

		// no non-native images. bail.
		if ( empty( $external ) ) {
			return;
		}

		// run our duplicate check
		$external	= self::remove_dupe_src( $external, 'src' );

		return $external;

	}

	/**
	 * process the external image upload
	 * @param  [type] $post_id   [description]
	 * @param  [type] $image_url [description]
	 * @return [type]            [description]
	 */
	static function upload_external_image( $post_id, $image_data ) {

		// load media handlers
		require_once( ABSPATH . 'wp-admin' . '/includes/image.php'	);
		require_once( ABSPATH . 'wp-admin' . '/includes/file.php'	);
		require_once( ABSPATH . 'wp-admin' . '/includes/media.php'	);

		// if we don't have an image src, bail
		if ( ! isset( $image_data['src'] ) || isset( $image_data['src'] ) && empty( $image_data['src'] ) ) {
			return;
		}

		// create a temp file
		$temp_file	= download_url( $image_data['src'] );

		// make a file array
		$file_array	= array(
			'name'		=> basename( $image_data['src'] ),
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

		// run our function to update the image name and other data
		self::update_image_data( $image_id, $image_data );

		// send back the image ID and URL
		return array(
			'id'	=> $image_id,
			'src'	=> wp_get_attachment_url( $image_id )
		);

	}

	/**
	 * [replace_image_urls description]
	 * @param  [type] $post_id  [description]
	 * @param  [type] $external [description]
	 * @param  [type] $uploaded [description]
	 * @return [type]           [description]
	 */
	static function replace_image_urls( $post_id, $external, $uploaded ) {

		// if we got an array, parse it
		if ( is_array( $external ) ) {
			$external	= wp_list_pluck( $external, 'src' );
		}

		// if we got an array, parse it
		if ( is_array( $uploaded ) ) {
			$uploaded	= wp_list_pluck( $uploaded, 'src' );
		}

		// get our post content
		$content	= get_post_field( 'post_content', $post_id, 'raw' );

		if ( ! $content ) {
			return;
		}

		// replace my URLs in content
		$content	= str_replace( $external, $uploaded, $content );

		// set args for updating
		$args	= array(
			'ID'			=> $post_id,
			'post_content'	=> $content
		);

		// filter the args
		$args	= apply_filters( 'exim_image_update_args', $args, $post_id, $external, $uploaded );

		// run the update
		$update	= wp_update_post( $args );

		// return true / false
		if ( ! is_wp_error( $update ) ) {
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

	/**
	 * [update_replacement_meta description]
	 * @param  [type] $post_id  [description]
	 * @param  [type] $external [description]
	 * @param  [type] $uploaded [description]
	 * @return [type]           [description]
	 */
	static function update_replacement_meta( $post_id, $external, $uploaded ) {

		// if we got an array, parse it
		if ( is_array( $external ) ) {
			$external	= wp_list_pluck( $external, 'src' );
		}

		// if we got an array, parse it
		if ( is_array( $uploaded ) ) {
			$uploaded	= wp_list_pluck( $uploaded, 'src' );
		}

		// add a meta string of our external images for replacement
		if ( ! empty( $external ) ) {
			update_post_meta( $post_id, '_exim_image_src', $external );
		}

		// add a meta string of our new images for replacement
		if ( ! empty( $uploaded ) ) {
			update_post_meta( $post_id, '_exim_image_new', $uploaded );
		}

		// allow for other actions that may be related to image meta
		do_action( 'exim_image_meta_update', $post_id, $external, $uploaded );

	}

	/**
	 * check the array for duplicate image sources while ignoring alt text
	 * @param  [type] $images [description]
	 * @param  [type] $key    [description]
	 * @return [type]         [description]
	 */
	static function remove_dupe_src( $images, $key = 'src' ) {

		// set our array for uniques
		$unique	= array();

		// loop through our array
		foreach ( $images as $value ) {

			// check for our passed key inside the array
			if ( ! isset( $unique[$value[$key]] ) ) {
				$unique[$value[$key]] = $value;
			}

		}

		// separate array values
		$images = array_values( $unique );

		// return the new array
		return $images;

	}

	/**
	 * update the image name and other related items itself
	 * @param  [type] $image_id   [description]
	 * @param  [type] $image_data [description]
	 * @return [type]             [description]
	 */
	static function update_image_data( $image_id, $image_data ) {

		// fetch our cleaned up names
		$image_name	= self::sanitize_image_name( $image_id, $image_data );

		// set the args for updating
		$args	= array(
			'ID'				=> $image_id,
			'post_title'		=> $image_name['title'],
			'post_name'			=> $image_name['name'],
			'comment_status'	=> 'closed',
			'ping_status'		=> 'closed'
		);

		// run update
		$image_update	= wp_update_post( $args );

		// if we have no error, update a meta key for original URL
		if ( ! is_wp_error( $image_update ) ) {

			update_post_meta( $image_id, '_exim_source_url', esc_url( $image_data['src'] ) );
		}

		return;

	}

	/**
	 * clean up the image name for saving and uploading
	 * @return [type] [description]
	 */
	static function sanitize_image_name( $image_id, $image_data ) {

		// first check for alt text data
		$name	= isset( $image_data['alt'] ) && ! empty( $image_data['alt'] ) ? $image_data['alt'] : basename( $image_data['src'] );

		// now run a quick filter for image extension removal
		$name	= str_replace( array( '.jpg', '.jpeg', '.png', '.gif', '.bmp', '.tiff' ), '', $name );

		// now send it back sanitized
		$data	= array(
			'name'	=> sanitize_key( $name ),
			'title'	=> sanitize_text_field( $name )
		);

		return apply_filters( 'exim_image_name_setup', $data, $image_id );

	}

	/// end class
}

new EXIM_Import_Helper();
