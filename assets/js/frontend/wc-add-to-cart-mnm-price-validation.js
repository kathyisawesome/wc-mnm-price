/* global wc_mnm_price_params */

( function( $ ) {

	/**
	 * Main container object.
	 */
	function WC_MNM_Price( container ) {

		var self       = this;
		this.container = container;
		this.$form     = container.$mnm_form;

		/**
		 * Init.
		 */
		this.initialize = function() {
			if( 'price' === container.$mnm_cart.data( 'validation_mode' ) ) {
				this.bind_event_handlers();		
			}

		};

		/**
		 * Container-Level Event Handlers.
		 */
		this.bind_event_handlers = function() {
			this.$form.on( 'wc-mnm-container-quantities-updated', this.update_totals );
			this.$form.on( 'wc-mnm-validation', this.validate );
		};

		/**
		 * Update Totals.
		 */
		this.update_totals = function( event, container ) {
			var total_price  = 0;

			$.each( container.child_items, function( index, child_item ) {

				var item_price = child_item.$self.data( 'price' );

				if( 'undefined' === typeof item_price ) { 
					item_price = 0;
				}

				item_price = parseFloat( item_price );

				total_price += child_item.get_quantity() * item_price;
			} );

			container.$mnm_cart.data( 'total_price', total_price );

		};

		/**
		 * Validate Weight.
		 */
		this.validate = function( event, container ) {

			container.reset_messages();

			var total_price = container.$mnm_cart.data( 'total_price' );

			if( typeof total_price === 'undefined' ) {
				total_price = 0;
			}

			var min_price = container.$mnm_cart.data( 'min_price' );
			var max_price = container.$mnm_cart.data( 'max_price' );
			var error_message = '';

			// Validation.
			if( min_price === max_price && total_price !== min_price ) {
				error_message = wc_mnm_price_params.i18n_price_error.replace( '%s', wc_mnm_price_format( min_price ) );
			}
			// Validate a range.
			else if( max_price > 0 && min_price > 0 && ( total_price < min_price || total_price > max_price ) ) {
				error_message = wc_mnm_price_params.i18n_min_max_price_error.replace( '%max', wc_mnm_price_format( max_price ) ).replace( '%min', wc_mnm_price_format( min_price ) );
			}
			// Validate that a container has minimum price.
			else if( min_price > 0 && total_price < min_price ) {
				error_message = wc_mnm_price_params.i18n_min_price_error.replace( '%min', wc_mnm_price_format( min_price ) );
			// Validate that a container has less than the maximum price.
			} else if ( max_price > 0 && total_price > max_price ) {
				error_message = wc_mnm_price_params.i18n_max_price_error.replace( '%max', wc_mnm_price_format( max_price ) );
			}

			// Add error message.
			if ( error_message !== '' ) {
				// "Selected Xunit".
				var selected_price_message = self.selected_price_message( total_price );

				// Add error message, replacing placeholders with current values.
				container.add_message( error_message.replace( '%v', selected_price_message ), 'error' );

			// Add selected price status message if there are no error messages and infinite container is used.
			} else if ( false === max_price ) {
				container.add_message( self.selected_price_message( total_price ) );
			}

		};

		/**
		 * Selected total message builder.
		 */
		this.selected_price_message = function( price ) {
			return wc_mnm_price_params.i18n_price_message.replace( '%s', wc_mnm_price_format( price ) );
		};

	} // End WC_MNM_Price.

	/*-----------------------------------------------------------------*/
	/*  Initialization.                                                */
	/*-----------------------------------------------------------------*/

	$( 'body' ).on( 'wc-mnm-initializing', function( e, container ) {
		var price = new WC_MNM_Price( container );
		price.initialize();
	});

} ) ( jQuery );