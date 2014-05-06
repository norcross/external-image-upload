//********************************************************
// now start the engine
//********************************************************
jQuery(document).ready( function($) {

	var defaultMessage	= eximAdmin.defaultMessage;
	var successRedirect	= eximAdmin.successRedirect;

// **************************************************************
//  fetch and pass search info for single day
// **************************************************************

	$( 'div#exim-button-wrap' ).on( 'click', 'input#exim-process', function( event ) {

		// remove any existing messages
		$('div#wpbody div#message').remove();
		$('div#wpbody div#setting-error-settings_updated').remove();

		// show our spinner
		$( 'span.exim-spinner' ).show();

		// fetch my data
		var post_id		= $( this ).data( 'post-id' );
		var nonce		= $( this ).data( 'nonce' );

		if ( nonce === '' ) {
			// hide our spinner
			$( 'span.exim-spinner' ).hide();
			// and bail
			return false;
		}

		// construct my data
		var data	= {
			action:		'exim_image_process',
			post_id:	post_id,
			nonce:		nonce
		};


		jQuery.post( ajaxurl, data, function( response ) {

			// hide our spinner
			$( 'span.exim-spinner' ).hide();

			var obj;
			try {
				obj = jQuery.parseJSON(response);
			}
			catch(e) {
				return false;
			}

			if( obj.success === true && obj.message !== '' ) {
				$( 'div#wpbody h2:first' ).after( '<div id="message" class="updated below-h2 exim-message"><p>' + obj.message + '</p></div>' );
				$( 'div.exim-message' ).delay( 4000 ).fadeOut( 'slow' );

				setTimeout( function () {
					window.location.href = successRedirect;
				}, 400 );

				return;
			}

			if ( obj.success === false && obj.message !== '' ) {
				$( 'div#wpbody h2:first' ).after( '<div id="message" class="error below-h2 exim-message"><p>' + obj.message + '</p></div>' );
				$( 'div.exim-message' ).delay( 4000 ).fadeOut( 'slow' );

				return;
			}

			if ( obj.success === false && obj.message === '' ) {
				$( 'div#wpbody h2:first' ).after( '<div id="message" class="error below-h2 exim-message"><p>' + defaultMsg + '</p></div>' );
				$( 'div.exim-message' ).delay( 4000 ).fadeOut( 'slow' );
				return;
			}

		});

	});

});