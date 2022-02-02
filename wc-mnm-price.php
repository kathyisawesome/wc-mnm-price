<?php
/**
 * Plugin URI: http://www.woocommerce.com/products/woocommerce-mix-and-match-products/
 * Plugin Name: WooCommerce Mix and Match - By Price
 * Version: 1.3.1
 * Description: Validate container by price, requires MNM 1.10.5
 * Author: Kathy Darling
 * Author URI: http://kathyisawesome.com/
 * Developer: Kathy Darling
 * Developer URI: http://kathyisawesome.com/
 * Text Domain: wc-mnm-price
 * Domain Path: /languages
 * 
 * GitHub Plugin URI: kathyisawesome/wc-mnm-price
 * GitHub Plugin URI: https://github.com/kathyisawesome/wc-mnm-price
 * Release Asset: true
 *
 * Copyright: © 2020 Kathy Darling
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */



/**
 * The Main WC_MNM_Price class
 **/
if ( ! class_exists( 'WC_MNM_Price' ) ) :

class WC_MNM_Price {

	/**
	 * constants
	 */
	CONST VERSION = '1.1.0';
	CONST REQUIRED_WOO = '4.0.0';

	/**
	 * WC_MNM_Price Constructor
	 *
	 * @access 	public
     * @return 	WC_MNM_Price
	 */
	public static function init() {

		// Load translation files.
		add_action( 'init', array( __CLASS__, 'load_plugin_textdomain' ) );

		// Add extra meta.
		add_action( 'woocommerce_mnm_product_options', array( __CLASS__, 'container_options') , 10, 2 );
		add_filter( 'wc_mnm_validation_options', array( __CLASS__, 'validation_options' ) );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'process_meta' ), 20 );

		// Register Scripts.
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_scripts' ) );
		add_filter( 'woocommerce_mix_and_match_data_attributes', array( __CLASS__, 'add_data_attributes' ), 10, 2 );

		// Display Scripts.
		add_action( 'woocommerce_mix-and-match_add_to_cart', array( __CLASS__, 'load_scripts' ) );

		// QuickView support.
		add_action( 'wc_quick_view_enqueue_scripts', array( __CLASS__, 'load_scripts' ) );

		// Validation.
		add_filter( 'woocommerce_mnm_add_to_cart_container_validation', array( __CLASS__, 'validation' ), 10, 3 );
		add_filter( 'woocommerce_mnm_cart_container_validation', array( __CLASS__, 'validation' ), 10, 3 );
		add_filter( 'woocommerce_mnm_add_to_order_container_validation', array( __CLASS__, 'validation' ), 10, 3 );

		// Bypass min/max sizes when in price validation mode.
		add_filter( 'woocommerce_mnm_min_container_size', array( __CLASS__, 'remove_min_size' ), 10, 2 );
		add_filter( 'woocommerce_mnm_max_container_size', array( __CLASS__, 'remove_max_size' ), 10, 2 );

		// Share a validation mode input with Weight validation plugin.
		add_filter( 'wc_mnm_admin_show_validation_mode_option', '__return_false' );

    }


	/*-----------------------------------------------------------------------------------*/
	/* Localization */
	/*-----------------------------------------------------------------------------------*/


	/**
	 * Make the plugin translation ready
	 *
	 * @return void
	 */
	public static function load_plugin_textdomain() {
		load_plugin_textdomain( 'wc-mnm-price' , false , dirname( plugin_basename( __FILE__ ) ) .  '/languages/' );
	}

	/*-----------------------------------------------------------------------------------*/
	/* Admin */
	/*-----------------------------------------------------------------------------------*/


	/**
	 * Adds the container max price option writepanel options.
	 *
	 * @param int $post_id
	 * @param  WC_Product_Mix_and_Match  $mnm_product_object
	 */
	public static function container_options( $post_id, $mnm_product_object ) {
		
		$allowed_options = self::get_validation_options();
		$value = $mnm_product_object->get_meta( '_mnm_validation_mode' );
		$value = array_key_exists( $value, $allowed_options ) ? $value : '';

		woocommerce_wp_radio( 
			array(
				'id'      => '_mnm_validation_mode',
				'class'   => 'select short mnm_validation_mode',
				'label'   => __( 'Validation mode', 'wc-mnm-price' ),
				'value'	  => $value,
				'options' => $allowed_options,
			)
		);

		woocommerce_wp_text_input( array(
			'id'            => '_mnm_min_container_price',
			'label'       => __( 'Min Container Price', 'wc-mnm-price' ) . ' (' . get_woocommerce_currency_symbol() . ')',
			'desc_tip'    => true,
			'description' => __( 'Min price of containers in decimal form', 'wc-mnm-price' ),
			'type'        => 'text',
			'data_type'   => 'decimal',
			'value'			=> $mnm_product_object->get_meta( '_mnm_min_container_price', true, 'edit' ),
			'desc_tip'      => true,
			'wrapper_class' => 'show_if_validate_by_price'
		) );

		woocommerce_wp_text_input( array(
			'id'            => '_mnm_max_container_price',
			'label'       => __( 'Max Container Price', 'wc-mnm-price' ) . ' (' . get_woocommerce_currency_symbol() . ')',
			'desc_tip'    => true,
			'description' => __( 'Maximum price of containers in decimal form', 'wc-mnm-price' ),
			'type'        => 'text',
			'data_type'   => 'decimal',
			'value'			=> $mnm_product_object->get_meta( '_mnm_max_container_price', true, 'edit' ),
			'desc_tip'      => true,
			'wrapper_class' => 'show_if_validate_by_price'
		) );

		?>
		<script>
			jQuery( document ).ready( function( $ ) {

				$( '#mnm_product_data input#_mnm_per_product_pricing' ).change( function() {

                    var options      = $( '#mnm_product_data ._mnm_validation_mode_field' ).find( 'li' ).length;
                    var $price_input = $( '#mnm_product_data ._mnm_validation_mode_field' ).find( 'input[value="price"]' );

					if ( $( this ).prop( 'checked') ) {
						$( '#mnm_product_data ._mnm_validation_mode_field' ).show();
                        $price_input.closest( 'li' ).show();
					} else {
                        // More than 2 options means another validation plugin is in play.
                        if ( options > 2 ) {
                            $price_input.closest( 'li' ).hide();
                        } else {
                            $( '#mnm_product_data ._mnm_validation_mode_field' ).hide();
                        }

                        // If price validation mode when leaving per-item pricing, revert to default validation.
                        if ( $price_input.prop( 'checked' ) ) {
                            $price_input.prop( 'checked', false );
                            $( '#mnm_product_data ._mnm_validation_mode_field' ).find( 'input[value=""]' ).prop( 'checked', true );
                        }
					}

				} );

				$( '#mnm_product_data input#_mnm_per_product_pricing' ).change();


				$( '#mnm_product_data input.mnm_validation_mode' ).change( function() {

					var value = $( this ).val();

					if( '' === value ) {
						$( '#mnm_product_data .mnm_container_size_options' ).show();
						$( '#mnm_product_data .show_if_validate_by_price' ).hide();
					} else {
						$( '#mnm_product_data .mnm_container_size_options' ).hide();
						if( 'price' === value ) {
							$( '#mnm_product_data .show_if_validate_by_price' ).show();
						} else {
							$( '#mnm_product_data .show_if_validate_by_price' ).hide();
						}
					}
				} );

				$( '#mnm_product_data input.mnm_validation_mode:checked' ).change();

			} );

		</script>

		<?php

	}

	/**
	 * Add the options via filter, so we can work with other validation mini-extensions.
	 *
	 * @param  array $options Validation options
	 * @return array
	 */
	public static function validation_options( $options ) {
		$options[ '' ]       = esc_html__( 'Use default', 'wc-mnm-price' );
		$options[ 'price' ] = esc_html__( 'Validate by price', 'wc-mnm-price' );
		return $options;
	}

	/**
	 * Saves the new meta field.
	 *
	 * @param  WC_Product_Mix_and_Match  $mnm_product_object
	 */
	public static function process_meta( $product ) {

		if ( $product->is_type( 'mix-and-match' ) ) {

			$allowed_options = self::get_validation_options();

			if( ! empty( $_POST[ '_mnm_validation_mode' ] ) && array_key_exists( $_POST[ '_mnm_validation_mode' ], $allowed_options ) ) {
				$product->update_meta_data( '_mnm_validation_mode', wc_clean( $_POST[ '_mnm_validation_mode' ] ) );
			} else {
				$product->delete_meta_data( '_mnm_validation_mode' );
			}

			if( ! empty( $_POST[ '_mnm_max_container_price' ] ) ) {
				$product->update_meta_data( '_mnm_max_container_price', wc_clean( wp_unslash( $_POST[ '_mnm_max_container_price' ] ) ) );
			} else {
				$product->delete_meta_data( '_mnm_max_container_price' );
			}

			if( ! empty( $_POST[ '_mnm_min_container_price' ] ) ) {
				$product->update_meta_data( '_mnm_min_container_price', wc_clean( wp_unslash( $_POST[ '_mnm_min_container_price' ] ) ) );
			}	else {
				$product->delete_meta_data( '_mnm_min_container_price' );
			}

		}

	}



	/*-----------------------------------------------------------------------------------*/
	/* Cart Functions */
	/*-----------------------------------------------------------------------------------*/


	/**
	 * Server-side validation
	 * 
	 * @param bool $is_valid
	 * @param obj WC_Product_Mix_and_Match $product
	 * @param obj WC_Mix_and_Match_Stock_Manager $mnm_stock
	 * @return  bool 
	 */
	public static function validation( $valid, $product, $mnm_stock ) {

		if( self::validate_by_price( $product ) ) {		

			$managed_items = $mnm_stock->get_managed_items();

			$total_price = 0;

			foreach ( $managed_items as $managed_item_id => $managed_item ) {
				$managed_product       = wc_get_product( $managed_item_id );
				$item_title            = $managed_product->get_title();
				$total_price 		  += $managed_product->get_price() * $managed_item[ 'quantity' ];
			}

			// Validate the total price.
			if ( $total_price < $product->get_meta( '_mnm_min_container_price' ) ) {
				$error_message = sprintf( __( 'Your &quot;%s&quot; is too inexpensive.', 'wc-mnm-price' ), $product->get_title() );
				wc_add_notice( $error_message, 'error' );
				$valid = false;
			} elseif ( $total_price > $product->get_meta( '_mnm_max_container_price' ) ) {
				$error_message = sprintf( __( 'Your &quot;%s&quot; is too expensive.', 'wc-mnm-price' ), $product->get_title() );
				wc_add_notice( $error_message, 'error' );
				$valid = false;
			}

			$valid = true;

		}

		return $valid;
	}

	/*-----------------------------------------------------------------------------------*/
	/* Scripts and Styles */
	/*-----------------------------------------------------------------------------------*/

	/**
	 * Register scripts
	 *
	 * @return void
	 */
	public static function register_scripts() {

		$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

		wp_register_script( 'wc-add-to-cart-mnm-price-validation', plugins_url( '/assets/js/frontend/wc-add-to-cart-mnm-price-validation' .  $suffix . '.js', __FILE__ ), array( 'wc-add-to-cart-mnm' ), self::VERSION, true );

		$params = array(
			// translators: %s is current selected price
			'i18n_price_message'                   => __( 'You have selected %s worth of product. ', 'wc-mnm-price' ),

			// translators: %v is the error message. %s is price left to be selected.
			'i18n_price_error'                     => __( '%vPlease select %s to continue&hellip;', 'wc-mnm-price' ),

			// translators: %v is the error message. %min is the script placeholder for formatted min price. %max is script placeholder for formatted max price.
			'i18n_min_max_price_error'             => __( '%vPlease choose between %min and %max to continue&hellip;', 'wc-mnm-price' ),
			
			// translators: %v is the error message. %min is the script placeholder for formatted min price. %max is script placeholder for formatted max price.
			'i18n_min_price_error'                 => __( '%vPlease choose at least %min to continue&hellip;', 'wc-mnm-price' ),
			
			// translators: %v is the error message. %min is the script placeholder for formatted min price. %max is script placeholder for formatted max price.
			'i18n_max_price_error'                 => __( '%vPlease choose fewer than %max to continue&hellip;', 'wc-mnm-price' ),

		);

		wp_localize_script( 'wc-add-to-cart-mnm-price-validation', 'wc_mnm_price_params', $params );

	}

	/**
	 * Script parameters
	 *
	 * @param  array $params
	 * @param  obj WC_Mix_and_Match_Product
	 * 
	 * @return array
	 */
	public static function add_data_attributes( $params, $product ) {

		if( self::validate_by_price( $product ) ) {

			$new_params = array(
				'validation_mode' => $product->get_meta( '_mnm_validation_mode', true ),
			    'min_price'       => $product->get_meta( '_mnm_min_container_price', true ),
				'max_price'		  => $product->get_meta( '_mnm_max_container_price', true )
			);

			$params = array_merge( $params, $new_params );

		}

		return $params;

	}


	/**
	 * Load the script anywhere the MNN add to cart button is displayed
	 * 
	 * @return void
	 */
	public static function load_scripts() {
		wp_enqueue_script( 'wc-add-to-cart-mnm-price-validation' );
	}


	/**
	 * Set min back to zero
	 *
	 * @param  int $size
	 * @param  obj WC_Mix_and_Match_Product
	 * 
	 * @return int
	 */
	public static function remove_min_size( $size, $product ) {
		return self::validate_by_price( $product ) ? 0 : $size;
	}

	/**
	 * Set max back to unlimited
	 *
	 * @param  int $size
	 * @param  obj WC_Mix_and_Match_Product
	 * 
	 * @return int
	 */
	public static function remove_max_size( $size, $product ) {
		return self::validate_by_price( $product ) ? '' : $size;
	}

	/*-----------------------------------------------------------------------------------*/
	/* Helpers                                                                           */
	/*-----------------------------------------------------------------------------------*/

	/**
	 * Does this product validate by price.
	 * 
	 * @param  WC_Product
	 * @return bool
	 */
	public static function validate_by_price( $product ) {
		return $product && $product->is_type( 'mix-and-match' ) && $product->is_priced_per_product() && 'price' === $product->get_meta( '_mnm_validation_mode', true );
	}

	/**
	 * Get allowed validation options
	 *
	 * @return array
	 */
	public static function get_validation_options() {
		return array_unique( (array) apply_filters( 'wc_mnm_validation_options', array() ) );
	}


} //end class: do not remove or there will be no more guacamole for you

endif; // end class_exists check

// Launch the whole plugin.
add_action( 'woocommerce_mnm_loaded', array( 'WC_MNM_Price', 'init' ) );
