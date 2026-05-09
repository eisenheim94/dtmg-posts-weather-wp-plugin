<?php
/**
 * Settings page for the OpenWeatherMap API key.
 *
 * @package DTMG\PostsWeatherBlock\Settings
 */

declare( strict_types=1 );

namespace DTMG\PostsWeatherBlock\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Registers a Settings sub-menu page and the dtmg_pwb_options option.
 */
final class SettingsPage {

	public const OPTION_KEY   = 'dtmg_pwb_options';
	public const PAGE_SLUG    = 'dtmg-posts-weather';
	public const OPTION_GROUP = 'dtmg_pwb';

	/**
	 * Read the option with defaults applied.
	 *
	 * @return array{api_key:string}
	 */
	public static function get_options(): array {
		$defaults = [ 'api_key' => '' ];
		$stored   = get_option( self::OPTION_KEY, [] );
		return array_merge( $defaults, is_array( $stored ) ? $stored : [] );
	}

	/**
	 * Convenience accessor for the saved API key.
	 *
	 * @return string Saved OpenWeatherMap API key, or empty string if none.
	 */
	public static function api_key(): string {
		return self::get_options()['api_key'];
	}

	/**
	 * Register the admin hooks for the settings page.
	 */
	public function register(): void {
		add_action( 'admin_init', [ $this, 'register_setting' ] );
		add_action( 'admin_menu', [ $this, 'register_page' ] );
	}

	/**
	 * Register the option, settings section, and field with the Settings API.
	 */
	public function register_setting(): void {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_KEY,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize' ],
				'default'           => [ 'api_key' => '' ],
				'show_in_rest'      => false,
			]
		);

		add_settings_section(
			'dtmg_pwb_main',
			__( 'OpenWeatherMap API', 'dtmg-posts-weather-block' ),
			[ $this, 'render_section_intro' ],
			self::PAGE_SLUG
		);

		add_settings_field(
			'dtmg_pwb_api_key',
			__( 'API key', 'dtmg-posts-weather-block' ),
			[ $this, 'render_api_key_field' ],
			self::PAGE_SLUG,
			'dtmg_pwb_main',
			[ 'label_for' => 'dtmg_pwb_api_key' ]
		);
	}

	/**
	 * Register the Settings sub-menu page.
	 */
	public function register_page(): void {
		add_options_page(
			__( 'DTMG Posts + Weather', 'dtmg-posts-weather-block' ),
			__( 'DTMG Posts + Weather', 'dtmg-posts-weather-block' ),
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render_page' ]
		);
	}

	/**
	 * Sanitize the option array.
	 *
	 * Empty `api_key` in the submission means "leave existing value alone";
	 * this lets the form display a masked value without re-submitting it.
	 *
	 * @param mixed $input Raw form submission.
	 * @return array{api_key:string}
	 */
	public function sanitize( $input ): array {
		$existing      = self::get_options();
		$submitted_key = '';

		if ( is_array( $input ) && isset( $input['api_key'] ) ) {
			$submitted_key = sanitize_text_field( (string) $input['api_key'] );
			$submitted_key = preg_replace( '/[^A-Za-z0-9]/', '', $submitted_key ) ?? '';
		}

		return [
			'api_key' => '' === $submitted_key ? $existing['api_key'] : $submitted_key,
		];
	}

	/**
	 * Render the section description above the API key field.
	 */
	public function render_section_intro(): void {
		echo '<p>' . esc_html__( 'Paste your OpenWeatherMap API key. The stored value is never sent to the browser; only weather data is.', 'dtmg-posts-weather-block' ) . '</p>';
	}

	/**
	 * Render the masked password input for the API key.
	 */
	public function render_api_key_field(): void {
		$key    = self::api_key();
		$masked = '' === $key ? '' : str_repeat( '•', max( 0, strlen( $key ) - 4 ) ) . substr( $key, -4 );
		?>
		<input
			type="password"
			id="dtmg_pwb_api_key"
			name="<?php echo esc_attr( self::OPTION_KEY ); ?>[api_key]"
			value=""
			placeholder="<?php echo esc_attr( $masked ); ?>"
			autocomplete="off"
			class="regular-text"
		/>
		<p class="description">
			<?php
			echo '' === $key
				? esc_html__( 'No API key saved yet.', 'dtmg-posts-weather-block' )
				: esc_html__( 'A key is saved. Paste a new value to replace it; leave blank to keep the existing key.', 'dtmg-posts-weather-block' );
			?>
		</p>
		<?php
	}

	/**
	 * Render the settings page wrapper and form.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
