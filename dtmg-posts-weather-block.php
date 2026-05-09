<?php
/**
 * Plugin Name:       DTMG Posts + Weather Block
 * Plugin URI:        https://github.com/<placeholder>/dtmg-posts-weather-block
 * Description:       A Gutenberg block that displays two selected posts and a cached OpenWeatherMap snippet.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Demyd Hanenko
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       dtmg-posts-weather-block
 * Domain Path:       /languages
 *
 * @package DTMG\PostsWeatherBlock
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

$dtmg_pwb_autoload = __DIR__ . '/vendor/autoload.php';
if ( ! is_readable( $dtmg_pwb_autoload ) ) {
	add_action(
		'admin_notices',
		static function () {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__(
					'DTMG Posts + Weather Block: vendor/ is missing. Run `composer install` in the plugin directory.',
					'dtmg-posts-weather-block'
				)
			);
		}
	);
	return;
}
require_once $dtmg_pwb_autoload;

\DTMG\PostsWeatherBlock\Plugin::instance( __FILE__ )->init();
