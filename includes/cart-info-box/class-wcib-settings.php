<?php
/**
 * Einstellungsseite für das WooCommerce Cart Info Box Plugin.
 *
 * @package WooCartInfoBox
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WCIB_Settings
 */
class WCIB_Settings {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter(
			'plugin_action_links_' . plugin_basename( WCIB_PLUGIN_FILE ),
			array( $this, 'add_settings_link' )
		);
	}

	/**
	 * Standardwerte.
	 */
	public static function get_defaults() {
		return array(
			'enabled'         => 1,
			'title'           => __( 'Info', 'ecommerce-wunderkiste' ),
			'message'         => __( 'Versandkostenfrei ab 99 €', 'ecommerce-wunderkiste' ),
			'icon'            => '🚚',
			'bg_color'        => '#eaf6ff',
			'text_color'      => '#0a4a7a',
			'position'        => 'before_cart',
			'display_classic' => 1,
			'display_block'   => 1,
			'block_position'  => 'before',
		);
	}

	/**
	 * Optionen mit Defaults gemerged liefern.
	 */
	public static function get_options() {
		$saved = get_option( WCIB_OPTION_KEY, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::get_defaults() );
	}

	public function add_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Warenkorb Info-Box', 'ecommerce-wunderkiste' ),
			__( 'Warenkorb Info-Box', 'ecommerce-wunderkiste' ),
			'manage_woocommerce',
			'wcib-settings',
			array( $this, 'render_page' )
		);
	}

	public function add_settings_link( $links ) {
		$url           = admin_url( 'admin.php?page=wcib-settings' );
		$settings_link = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Cart Info Box', 'ecommerce-wunderkiste' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}

	public function register_settings() {
		register_setting(
			'wcib_settings_group',
			WCIB_OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => self::get_defaults(),
			)
		);
	}

	/**
	 * Sanitize-Callback: alle Eingaben hart bereinigen.
	 */
	public function sanitize( $input ) {
		$defaults = self::get_defaults();
		$output   = array();

		$output['enabled']         = ! empty( $input['enabled'] ) ? 1 : 0;
		$output['display_classic'] = ! empty( $input['display_classic'] ) ? 1 : 0;
		$output['display_block']   = ! empty( $input['display_block'] ) ? 1 : 0;

		$output['title'] = isset( $input['title'] )
			? sanitize_text_field( wp_unslash( $input['title'] ) )
			: $defaults['title'];

		$output['message'] = isset( $input['message'] )
			? wp_kses_post( wp_unslash( $input['message'] ) )
			: $defaults['message'];

		$output['icon'] = isset( $input['icon'] )
			? sanitize_text_field( wp_unslash( $input['icon'] ) )
			: $defaults['icon'];

		$bg                 = isset( $input['bg_color'] ) ? sanitize_hex_color( wp_unslash( $input['bg_color'] ) ) : null;
		$output['bg_color'] = $bg ? $bg : $defaults['bg_color'];

		$tx                   = isset( $input['text_color'] ) ? sanitize_hex_color( wp_unslash( $input['text_color'] ) ) : null;
		$output['text_color'] = $tx ? $tx : $defaults['text_color'];

		$allowed_positions  = array( 'before_cart', 'after_cart', 'before_cart_totals', 'after_cart_totals' );
		$position           = isset( $input['position'] ) ? sanitize_key( $input['position'] ) : '';
		$output['position'] = in_array( $position, $allowed_positions, true ) ? $position : $defaults['position'];

		$allowed_block_positions = array( 'before', 'after', 'manual' );
		$block_position          = isset( $input['block_position'] ) ? sanitize_key( $input['block_position'] ) : '';
		$output['block_position'] = in_array( $block_position, $allowed_block_positions, true )
			? $block_position
			: $defaults['block_position'];

		return $output;
	}

	/**
	 * Settings-Seite rendern.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$options = self::get_options();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Warenkorb Info-Box', 'ecommerce-wunderkiste' ); ?></h1>
			<p><?php echo esc_html__( 'Konfiguriere die Info-Box, die im WooCommerce Warenkorb angezeigt wird.', 'ecommerce-wunderkiste' ); ?></p>

			<form method="post" action="options.php">
				<?php settings_fields( 'wcib_settings_group' ); ?>

				<h2 class="title"><?php echo esc_html__( 'Allgemein', 'ecommerce-wunderkiste' ); ?></h2>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Plugin aktivieren', 'ecommerce-wunderkiste' ); ?></th>
							<td>
								<label>
									<input type="checkbox"
										name="<?php echo esc_attr( WCIB_OPTION_KEY ); ?>[enabled]"
										value="1"
										<?php checked( 1, (int) $options['enabled'] ); ?> />
									<?php echo esc_html__( 'Info-Box global aktivieren (Hauptschalter)', 'ecommerce-wunderkiste' ); ?>
								</label>
							</td>
						</tr>
					</tbody>
				</table>

				<h2 class="title"><?php echo esc_html__( 'Inhalt', 'ecommerce-wunderkiste' ); ?></h2>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">
								<label for="wcib-title"><?php echo esc_html__( 'Titel', 'ecommerce-wunderkiste' ); ?></label>
							</th>
							<td>
								<input id="wcib-title" type="text" class="regular-text"
									name="<?php echo esc_attr( WCIB_OPTION_KEY ); ?>[title]"
									value="<?php echo esc_attr( $options['title'] ); ?>" />
								<p class="description"><?php echo esc_html__( 'Optional. Leer lassen, wenn kein Titel angezeigt werden soll.', 'ecommerce-wunderkiste' ); ?></p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="wcib-icon"><?php echo esc_html__( 'Icon (Emoji)', 'ecommerce-wunderkiste' ); ?></label>
							</th>
							<td>
								<input id="wcib-icon" type="text" class="small-text" maxlength="8"
									name="<?php echo esc_attr( WCIB_OPTION_KEY ); ?>[icon]"
									value="<?php echo esc_attr( $options['icon'] ); ?>" />
								<p class="description"><?php echo esc_html__( 'Z. B. 🚚, 🍷, 💡 — leer lassen für kein Icon.', 'ecommerce-wunderkiste' ); ?></p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="wcib-message"><?php echo esc_html__( 'Nachricht', 'ecommerce-wunderkiste' ); ?></label>
							</th>
							<td>
								<textarea id="wcib-message" rows="4" class="large-text"
									name="<?php echo esc_attr( WCIB_OPTION_KEY ); ?>[message]"><?php echo esc_textarea( $options['message'] ); ?></textarea>
								<p class="description">
									<?php
									echo wp_kses(
										__( 'HTML-Tags wie <code>&lt;strong&gt;</code>, <code>&lt;em&gt;</code>, <code>&lt;a&gt;</code>, <code>&lt;br&gt;</code> sind erlaubt.', 'ecommerce-wunderkiste' ),
										array( 'code' => array() )
									);
									?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="wcib-bg"><?php echo esc_html__( 'Hintergrundfarbe', 'ecommerce-wunderkiste' ); ?></label>
							</th>
							<td>
								<input id="wcib-bg" type="color"
									name="<?php echo esc_attr( WCIB_OPTION_KEY ); ?>[bg_color]"
									value="<?php echo esc_attr( $options['bg_color'] ); ?>" />
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="wcib-text"><?php echo esc_html__( 'Textfarbe', 'ecommerce-wunderkiste' ); ?></label>
							</th>
							<td>
								<input id="wcib-text" type="color"
									name="<?php echo esc_attr( WCIB_OPTION_KEY ); ?>[text_color]"
									value="<?php echo esc_attr( $options['text_color'] ); ?>" />
							</td>
						</tr>
					</tbody>
				</table>

				<h2 class="title"><?php echo esc_html__( 'Klassischer Warenkorb (Shortcode)', 'ecommerce-wunderkiste' ); ?></h2>
				<p class="description">
					<?php echo esc_html__( 'Greift, wenn die Warenkorb-Seite den Shortcode [woocommerce_cart] verwendet.', 'ecommerce-wunderkiste' ); ?>
				</p>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Anzeigen', 'ecommerce-wunderkiste' ); ?></th>
							<td>
								<label>
									<input type="checkbox"
										name="<?php echo esc_attr( WCIB_OPTION_KEY ); ?>[display_classic]"
										value="1"
										<?php checked( 1, (int) $options['display_classic'] ); ?> />
									<?php echo esc_html__( 'Im klassischen Warenkorb anzeigen', 'ecommerce-wunderkiste' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="wcib-position"><?php echo esc_html__( 'Position', 'ecommerce-wunderkiste' ); ?></label>
							</th>
							<td>
								<select id="wcib-position" name="<?php echo esc_attr( WCIB_OPTION_KEY ); ?>[position]">
									<option value="before_cart" <?php selected( 'before_cart', $options['position'] ); ?>><?php echo esc_html__( 'Über dem Warenkorb', 'ecommerce-wunderkiste' ); ?></option>
									<option value="after_cart" <?php selected( 'after_cart', $options['position'] ); ?>><?php echo esc_html__( 'Unter der Produkttabelle', 'ecommerce-wunderkiste' ); ?></option>
									<option value="before_cart_totals" <?php selected( 'before_cart_totals', $options['position'] ); ?>><?php echo esc_html__( 'Über den Warenkorb-Summen', 'ecommerce-wunderkiste' ); ?></option>
									<option value="after_cart_totals" <?php selected( 'after_cart_totals', $options['position'] ); ?>><?php echo esc_html__( 'Unter den Warenkorb-Summen', 'ecommerce-wunderkiste' ); ?></option>
								</select>
							</td>
						</tr>
					</tbody>
				</table>

				<h2 class="title"><?php echo esc_html__( 'Cart-Block (Gutenberg)', 'ecommerce-wunderkiste' ); ?></h2>
				<p class="description">
					<?php echo esc_html__( 'Greift, wenn die Warenkorb-Seite den modernen WooCommerce Cart-Block verwendet.', 'ecommerce-wunderkiste' ); ?>
				</p>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Anzeigen', 'ecommerce-wunderkiste' ); ?></th>
							<td>
								<label>
									<input type="checkbox"
										name="<?php echo esc_attr( WCIB_OPTION_KEY ); ?>[display_block]"
										value="1"
										<?php checked( 1, (int) $options['display_block'] ); ?> />
									<?php echo esc_html__( 'Im Cart-Block anzeigen (registriert auch den einfügbaren Block „Warenkorb Info-Box")', 'ecommerce-wunderkiste' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="wcib-block-pos"><?php echo esc_html__( 'Modus', 'ecommerce-wunderkiste' ); ?></label>
							</th>
							<td>
								<select id="wcib-block-pos" name="<?php echo esc_attr( WCIB_OPTION_KEY ); ?>[block_position]">
									<option value="before" <?php selected( 'before', $options['block_position'] ); ?>><?php echo esc_html__( 'Automatisch über dem Cart-Block', 'ecommerce-wunderkiste' ); ?></option>
									<option value="after" <?php selected( 'after', $options['block_position'] ); ?>><?php echo esc_html__( 'Automatisch unter dem Cart-Block', 'ecommerce-wunderkiste' ); ?></option>
									<option value="manual" <?php selected( 'manual', $options['block_position'] ); ?>><?php echo esc_html__( 'Nur manuell als Block einfügen (keine Automatik)', 'ecommerce-wunderkiste' ); ?></option>
								</select>
								<p class="description">
									<?php echo esc_html__( 'In allen Modi steht der Block „Warenkorb Info-Box" zusätzlich im Block-Editor zur Verfügung und kann frei platziert werden.', 'ecommerce-wunderkiste' ); ?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
