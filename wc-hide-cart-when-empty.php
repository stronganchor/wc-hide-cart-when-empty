<?php
/**
 * Plugin Name: WC Hide Cart When Empty
 * Description: Hides WooCommerce cart links and the Cart page itself when the cart is empty.
 * Version:     1.0.0
 * Author:      Mike Mesenbring
 * License:     GPL-2.0-or-later
 * Text Domain: wc-hide-cart-when-empty
 */

defined( 'ABSPATH' ) || exit; // No direct access.

// Only run if WooCommerce is active.
if ( defined( 'WC_PLUGIN_FILE' ) && class_exists( 'WooCommerce' ) ) :

	final class WCHideCartWhenEmpty {

		public function __construct() {
			add_filter( 'wp_nav_menu_objects',               [ $this, 'maybe_strip_cart_menu_items' ] );
			add_action( 'template_redirect',                 [ $this, 'maybe_redirect_cart_page'   ] );
			add_filter( 'woocommerce_add_to_cart_fragments', [ $this, 'maybe_strip_cart_fragments' ] );
		}

		/**
		 * Remove menu items that point to the Cart page when the cart is empty.
		 *
		 * @param array $items Menu items.
		 * @return array
		 */
		public function maybe_strip_cart_menu_items( $items ) {
			if ( ! function_exists( 'wc' ) || ! wc()->cart || ! wc()->cart->is_empty() ) {
				return $items;
			}

			$cart_url = untrailingslashit( wc_get_cart_url() );

			foreach ( $items as $key => $item ) {
				if ( isset( $item->url ) && untrailingslashit( $item->url ) === $cart_url ) {
					unset( $items[ $key ] );
				}
			}
			return $items;
		}

		/**
		 * Redirect the Cart page to Shop (or Home) when the cart is empty.
		 */
		public function maybe_redirect_cart_page() {
			if ( ! is_cart() || ! function_exists( 'wc' ) || ! wc()->cart || ! wc()->cart->is_empty() ) {
				return;
			}
			$target = wc_get_page_permalink( 'shop' ) ?: home_url( '/' );
			wp_safe_redirect( $target );
			exit;
		}

		/**
		 * Strip mini-cart fragments so the cart icon stays hidden after AJAX updates.
		 *
		 * @param array $fragments AJAX fragments.
		 * @return array
		 */
		public function maybe_strip_cart_fragments( $fragments ) {
			if ( ! function_exists( 'wc' ) || ! wc()->cart || ! wc()->cart->is_empty() ) {
				return $fragments;
			}

			foreach ( $fragments as $key => $html ) {
				if ( stripos( $key . $html, 'cart' ) !== false ) {
					unset( $fragments[ $key ] );
				}
			}
			return $fragments;
		}
	}

	new WCHideCartWhenEmpty();

endif;
