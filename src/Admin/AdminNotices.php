<?php
/**
 * Admin notices.
 *
 * @package DTMG\PostsWeatherBlock\Admin
 */

declare( strict_types=1 );

namespace DTMG\PostsWeatherBlock\Admin;

use DTMG\PostsWeatherBlock\Settings\SettingsPage;

defined( 'ABSPATH' ) || exit;

/**
 * Renders a missing-API-key notice on every admin page.
 *
 * The notice is non-dismissible because dismissal would only hide the symptom;
 * the block still won't render weather until a key is saved. A "Configure now"
 * link is provided.
 */
final class AdminNotices {

	/**
	 * Hook the admin_notices callback.
	 */
	public function register(): void {
		add_action( 'admin_notices', [ $this, 'maybe_render_missing_key_notice' ] );
	}

	/**
	 * Render the missing-key notice if appropriate for the current screen.
	 */
	public function maybe_render_missing_key_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( '' !== SettingsPage::api_key() ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && 'settings_page_' . SettingsPage::PAGE_SLUG === $screen->id ) {
			// No need to nag on the settings page itself.
			return;
		}

		$url = admin_url( 'options-general.php?page=' . SettingsPage::PAGE_SLUG );
		?>
		<div class="notice notice-warning">
			<p>
				<strong><?php esc_html_e( 'DTMG Posts + Weather Block:', 'dtmg-posts-weather-block' ); ?></strong>
				<?php esc_html_e( 'an OpenWeatherMap API key is required for the block to display weather.', 'dtmg-posts-weather-block' ); ?>
				<a href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Configure now', 'dtmg-posts-weather-block' ); ?></a>
			</p>
		</div>
		<?php
	}
}
