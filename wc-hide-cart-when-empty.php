<?php
/**
 * Plugin Name: WC Hide Cart When Empty
 * Description: Hides any cart icon you specify until the WooCommerce cart contains items.
 * Version:     2.0.2
 * Author:      Strong Anchor Tech
 * License:     GPL-2.0-or-later
 * Text Domain: wc-hide-cart-when-empty
 *
 * @package WC_Hide_Cart_When_Empty
 */

defined( 'ABSPATH' ) || exit;          // No direct access.
define( 'WCHC_OPTION_KEY', 'wc_hide_cart_selectors' );

/*
|--------------------------------------------------------------------
|  Boot only after all plugins (incl. WooCommerce) are loaded
|--------------------------------------------------------------------
*/
add_action( 'plugins_loaded', 'wchc_bootstrap', 20 );

function wchc_bootstrap() {

	/* ------------------------------------------------------------- *
	 * 0. Gracefully bail if WooCommerce really is missing
	 * ------------------------------------------------------------- */
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action(
			'admin_notices',
			function () {
				printf(
					'<div class="error"><p>%s</p></div>',
					wp_kses_post(
						sprintf(
							/* translators: 1: <strong>, 2: </strong>, 3: <a>, 4: </a> */
							__( '%1$sWC Hide Cart When Empty%2$s requires WooCommerce. Please %3$sinstall/activate WooCommerce%4$s.', 'wc-hide-cart-when-empty' ),
							'<strong>', '</strong>',
							'<a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">', ' »</a>'
						)
					)
				);
			}
		);
		return;
	}

	/* ------------------------------------------------------------- *
	 * 1. Admin-side settings tab (class only needed in dashboard)
	 * ------------------------------------------------------------- */
	if ( is_admin() ) {
		class WCHC_Settings_Tab {

			public static function init() {
				add_filter( 'woocommerce_settings_tabs_array', [ __CLASS__, 'add_tab' ], 50 );
				add_action( 'woocommerce_settings_tabs_hide_cart', [ __CLASS__, 'render' ] );
				add_action( 'woocommerce_update_options_hide_cart', [ __CLASS__, 'save' ] );
			}

			public static function add_tab( $tabs ) {
				$tabs['hide_cart'] = __( 'Hide Cart', 'wc-hide-cart-when-empty' );
				return $tabs;
			}

			public static function render() {
				woocommerce_admin_fields( self::fields() );
			}

			public static function save() {
				woocommerce_update_options( self::fields() );
			}

			private static function fields() {
				return [
					[
						'name' => __( 'Hide Cart Icon Settings', 'wc-hide-cart-when-empty' ),
						'type' => 'title',
						'id'   => 'wchc_section_title',
						'desc' => __(
							'Enter one or more CSS selectors (comma-separated) that wrap your cart icon. Example: <code>.show-cart-btn, .header-cart</code>.',
							'wc-hide-cart-when-empty'
						),
					],
					[
						'name'     => __( 'Cart icon selector(s)', 'wc-hide-cart-when-empty' ),
						'id'       => WCHC_OPTION_KEY,
						'type'     => 'text',
						'css'      => 'min-width:400px;',
						'desc_tip' => true,
					],
					[ 'type' => 'sectionend', 'id' => 'wchc_section_end' ],
				];
			}
		}
		WCHC_Settings_Tab::init();
	}

	/* ------------------------------------------------------------- *
	 * 2. Front-end logic
	 * ------------------------------------------------------------- */
	final class WCHC_Front {

		private array $defaults = [
			'.show-cart-btn',
			'.wd-header-cart',
			'.site-header .cart-contents',
			'.menu-item-type-woocommerce-cart',
		];

		public function __construct() {
			add_action( 'wp_footer', [ $this, 'maybe_print_css' ], 9999 );
			add_filter( 'woocommerce_add_to_cart_fragments', [ $this, 'sync_fragment' ] );
			add_action( 'template_redirect', [ $this, 'redirect_if_empty_cart_page' ] );
		}

		/* ---------- helpers ---------- */

		private function cart_is_empty(): bool {
			return wc()->cart && wc()->cart->is_empty();
		}

		private function selectors(): array {
			$user_raw  = get_option( WCHC_OPTION_KEY, '' );
			$user_list = array_filter( array_map( 'trim', explode( ',', wp_strip_all_tags( $user_raw ) ) ) );
			return array_unique( array_merge( $user_list, $this->defaults ) );
		}

		private function css_rule(): string {
			$sel = implode( ',', array_map( 'esc_html', $this->selectors() ) );
			return $sel . '{display:none!important}';
		}

		/* ---------- main tasks -------- */

		public function maybe_print_css(): void {
			if ( ! $this->cart_is_empty() ) {
				return;
			}
			printf( "\n<style id='wc-hide-cart-when-empty'>%s</style>\n", $this->css_rule() );
		}

		public function sync_fragment( array $fragments ): array {
			$fragments['style#wc-hide-cart-when-empty'] = $this->cart_is_empty()
				? '<style id="wc-hide-cart-when-empty">' . $this->css_rule() . '</style>'
				: '';
			return $fragments;
		}

		public function redirect_if_empty_cart_page(): void {
			if ( is_cart() && $this->cart_is_empty() ) {
				wp_safe_redirect( wc_get_page_permalink( 'shop' ) ?: home_url( '/' ) );
				exit;
			}
		}
	}
	new WCHC_Front();

	/* ------------------------------------------------------------- *
	 * 3. One-time “activated” notice
	 * ------------------------------------------------------------- */
	register_activation_hook(
		__FILE__,
		function () {
			set_transient( 'wchc_welcome', true, 5 );
		}
	);
	add_action(
		'admin_notices',
		function () {
			if ( get_transient( 'wchc_welcome' ) ) {
				?>
				<div class="updated notice is-dismissible">
					<p><?php esc_html_e( 'WC Hide Cart When Empty activated – visit WooCommerce → Settings → Hide Cart to set your selector.', 'wc-hide-cart-when-empty' ); ?></p>
				</div>
				<?php
				delete_transient( 'wchc_welcome' );
			}
		}
	);
}
