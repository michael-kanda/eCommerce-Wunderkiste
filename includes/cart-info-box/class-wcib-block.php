<?php
/**
 * Gutenberg-Block-Registrierung und optionale Auto-Injection in den Cart-Block.
 *
 * @package WooCartInfoBox
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WCIB_Block
 */
class WCIB_Block {

	/**
	 * Block-Name (Namespace/Name).
	 */
	const BLOCK_NAME = 'woo-cart-info-box/info-box';

	public function __construct() {
		// Block bei `init` registrieren (Standard-Pattern für register_block_type).
		add_action( 'init', array( $this, 'register_block' ) );

		// Auto-Injection in den WooCommerce Cart-Block, wenn aktiviert.
		add_filter( 'render_block', array( $this, 'inject_into_cart_block' ), 10, 2 );
	}

	/**
	 * Block-Editor-Script registrieren und Block via block.json registrieren.
	 */
	public function register_block() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		$options = WCIB_Settings::get_options();

		// Wenn der Block-Modus nicht aktiv ist, Block nicht registrieren.
		if ( empty( $options['enabled'] ) || empty( $options['display_block'] ) ) {
			return;
		}

		// Editor-Script mit korrekten Abhängigkeiten registrieren (kein Build nötig).
		wp_register_script(
			'wcib-info-box-editor',
			WCIB_PLUGIN_URL . 'blocks/info-box/editor.js',
			array(
				'wp-blocks',
				'wp-element',
				'wp-block-editor',
				'wp-server-side-render',
				'wp-i18n',
				'wp-components',
			),
			WCIB_VERSION,
			true
		);

		// Übersetzungsfähigkeit für Editor-Strings.
		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'wcib-info-box-editor', 'ecommerce-wunderkiste', WCIB_PLUGIN_DIR . 'languages' );
		}

		register_block_type(
			WCIB_PLUGIN_DIR . 'blocks/info-box',
			array(
				'render_callback' => array( $this, 'render_block_callback' ),
			)
		);
	}

	/**
	 * Server-seitiger Render-Callback für den Block.
	 *
	 * @param array  $attributes Block-Attribute.
	 * @param string $content    Inner-Content (nicht verwendet).
	 * @return string
	 */
	public function render_block_callback( $attributes, $content = '' ) {
		$options = WCIB_Settings::get_options();

		if ( empty( $options['enabled'] ) ) {
			return '';
		}

		return WCIB_Display::get_box_html();
	}

	/**
	 * Optionale Auto-Injection: Info-Box vor oder nach dem Cart-Block einfügen.
	 *
	 * @param string $block_content Gerenderter Block-Output.
	 * @param array  $block         Block-Daten.
	 * @return string
	 */
	public function inject_into_cart_block( $block_content, $block ) {
		// Nur den WooCommerce Cart-Block behandeln.
		if ( empty( $block['blockName'] ) || 'woocommerce/cart' !== $block['blockName'] ) {
			return $block_content;
		}

		// Nur im Frontend, nicht im Editor.
		if ( is_admin() ) {
			return $block_content;
		}

		$options = WCIB_Settings::get_options();

		if ( empty( $options['enabled'] ) || empty( $options['display_block'] ) ) {
			return $block_content;
		}

		$mode = $options['block_position'];
		if ( 'before' !== $mode && 'after' !== $mode ) {
			return $block_content;
		}

		$box = WCIB_Display::get_box_html();
		if ( '' === $box ) {
			return $block_content;
		}

		// Stylesheet sicherstellen, falls Block über render_block läuft.
		if ( wp_style_is( 'wcib-frontend', 'registered' ) && ! wp_style_is( 'wcib-frontend', 'enqueued' ) ) {
			wp_enqueue_style( 'wcib-frontend' );
		}

		return ( 'before' === $mode )
			? $box . $block_content
			: $block_content . $box;
	}
}
