<?php
/**
 * Frontend-Ausgabe der Info-Box im klassischen Warenkorb.
 *
 * @package WooCartInfoBox
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WCIB_Display
 */
class WCIB_Display {

	public function __construct() {
		// Style immer registrieren (sowohl Frontend als auch Editor),
		// damit block.json's "style"-Handle aufgelöst werden kann.
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ), 5 );
		add_action( 'admin_enqueue_scripts', array( $this, 'register_assets' ), 5 );
		add_action( 'enqueue_block_assets', array( $this, 'register_assets' ), 5 );

		$options = WCIB_Settings::get_options();

		if ( empty( $options['enabled'] ) || empty( $options['display_classic'] ) ) {
			return;
		}

		switch ( $options['position'] ) {
			case 'after_cart':
				add_action( 'woocommerce_after_cart_table', array( $this, 'render_box' ), 20 );
				break;
			case 'before_cart_totals':
				add_action( 'woocommerce_before_cart_totals', array( $this, 'render_box' ), 10 );
				break;
			case 'after_cart_totals':
				add_action( 'woocommerce_after_cart_totals', array( $this, 'render_box' ), 10 );
				break;
			case 'before_cart':
			default:
				add_action( 'woocommerce_before_cart', array( $this, 'render_box' ), 10 );
				break;
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_classic_style' ) );
	}

	/**
	 * Style-Handle registrieren (kein enqueue).
	 */
	public function register_assets() {
		if ( ! wp_style_is( 'wcib-frontend', 'registered' ) ) {
			wp_register_style(
				'wcib-frontend',
				WCIB_PLUGIN_URL . 'assets/css/frontend.css',
				array(),
				WCIB_VERSION
			);
		}
	}

	/**
	 * Stylesheet auf der klassischen Warenkorb-Seite laden.
	 */
	public function enqueue_classic_style() {
		if ( function_exists( 'is_cart' ) && is_cart() ) {
			wp_enqueue_style( 'wcib-frontend' );
		}
	}

	/**
	 * Klassische Warenkorb-Hooks: HTML ausgeben.
	 */
	public function render_box() {
		echo self::get_box_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — innerhalb getrennt escaped.
	}

	/**
	 * Liefert das HTML der Info-Box (oder leeren String).
	 * Wird vom klassischen Renderer und vom Block-Renderer verwendet.
	 *
	 * @return string
	 */
	public static function get_box_html() {
		$options = WCIB_Settings::get_options();

		if ( empty( $options['enabled'] ) ) {
			return '';
		}

		/**
		 * Filter: Erlaubt Dritten, die Optionen für die Ausgabe zu beeinflussen.
		 *
		 * @param array $options Aktuelle Plugin-Einstellungen.
		 */
		$options = apply_filters( 'wcib_box_options', $options );

		$title   = (string) $options['title'];
		$message = (string) $options['message'];
		$icon    = (string) $options['icon'];
		$bg      = (string) $options['bg_color'];
		$text    = (string) $options['text_color'];

		if ( '' === trim( wp_strip_all_tags( $message ) ) && '' === trim( $title ) ) {
			return '';
		}

		$allowed_html = array(
			'a'      => array(
				'href'   => array(),
				'title'  => array(),
				'target' => array(),
				'rel'    => array(),
			),
			'br'     => array(),
			'em'     => array(),
			'strong' => array(),
			'b'      => array(),
			'i'      => array(),
			'span'   => array(
				'class' => array(),
				'style' => array(),
			),
			'small'  => array(),
			'p'      => array(),
		);

		$style_attr = sprintf(
			'background-color:%1$s;color:%2$s;border-color:%2$s;',
			esc_attr( $bg ),
			esc_attr( $text )
		);

		ob_start();
		?>
		<div class="wcib-info-box" style="<?php echo esc_attr( $style_attr ); ?>" role="status">
			<?php if ( '' !== $icon ) : ?>
				<span class="wcib-info-box__icon" aria-hidden="true"><?php echo esc_html( $icon ); ?></span>
			<?php endif; ?>
			<div class="wcib-info-box__content">
				<?php if ( '' !== $title ) : ?>
					<p class="wcib-info-box__title"><strong><?php echo esc_html( $title ); ?></strong></p>
				<?php endif; ?>
				<?php if ( '' !== trim( wp_strip_all_tags( $message ) ) ) : ?>
					<div class="wcib-info-box__message">
						<?php echo wp_kses( wpautop( $message ), $allowed_html ); ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
