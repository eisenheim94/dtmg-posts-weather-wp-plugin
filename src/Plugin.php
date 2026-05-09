<?php
/**
 * Plugin bootstrap.
 *
 * @package DTMG\PostsWeatherBlock
 */

declare( strict_types=1 );

namespace DTMG\PostsWeatherBlock;

use DTMG\PostsWeatherBlock\Admin\AdminNotices;
use DTMG\PostsWeatherBlock\Block\PostsWeatherBlock;
use DTMG\PostsWeatherBlock\CLI\FlushCacheCommand;
use DTMG\PostsWeatherBlock\REST\WeatherController;
use DTMG\PostsWeatherBlock\Settings\SettingsPage;
use DTMG\PostsWeatherBlock\Weather\WeatherClient;
use DTMG\PostsWeatherBlock\Weather\WeatherService;

defined( 'ABSPATH' ) || exit;

/**
 * Singleton bootstrap.
 *
 * Holds the plugin's basename, file path, and version. All hooks are
 * registered here in init(). Subsystem classes are instantiated by init()
 * and given any collaborators they need (manual DI; no container).
 */
final class Plugin {

	public const VERSION = '1.0.0';

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Plugin entry file path.
	 *
	 * @var string
	 */
	private string $file;

	/**
	 * Plugin directory path with trailing slash.
	 *
	 * @var string
	 */
	private string $dir;

	/**
	 * Plugin URL with trailing slash.
	 *
	 * @var string
	 */
	private string $url;

	/**
	 * Lazily-created weather service shared by REST, CLI, and render.
	 *
	 * @var WeatherService|null
	 */
	private ?WeatherService $weather_service = null;

	/**
	 * Get or create the singleton instance.
	 *
	 * @param string $file Plugin entry file path; only used on first call.
	 */
	public static function instance( string $file ): self {
		if ( null === self::$instance ) {
			self::$instance = new self( $file );
		}
		return self::$instance;
	}

	/**
	 * Cache the file paths used elsewhere in the plugin.
	 *
	 * @param string $file Plugin entry file path.
	 */
	private function __construct( string $file ) {
		$this->file = $file;
		$this->dir  = plugin_dir_path( $file );
		$this->url  = plugin_dir_url( $file );
	}

	/**
	 * Register all WordPress hooks. Called once from the plugin entry file.
	 */
	public function init(): void {
		add_action( 'init', [ $this, 'load_textdomain' ] );

		( new SettingsPage() )->register();
		if ( is_admin() ) {
			( new AdminNotices() )->register();
		}

		( new WeatherController( $this->weather_service() ) )->register();

		if ( defined( 'WP_CLI' ) && \WP_CLI ) {
			\WP_CLI::add_command( 'dtmg-weather', new FlushCacheCommand( $this->weather_service() ) );
		}

		( new PostsWeatherBlock( $this->weather_service() ) )->register();
	}

	/**
	 * Load the plugin's translations on the `init` hook.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'dtmg-posts-weather-block',
			false,
			dirname( plugin_basename( $this->file ) ) . '/languages'
		);
	}

	/**
	 * Lazy factory so render.php can re-use the same Service.
	 */
	public function weather_service(): WeatherService {
		if ( null === $this->weather_service ) {
			$this->weather_service = new WeatherService(
				new WeatherClient( SettingsPage::api_key() )
			);
		}
		return $this->weather_service;
	}

	/**
	 * Static convenience for code that doesn't have a Plugin reference.
	 *
	 * @return string Plugin directory path with trailing slash, or '' if not yet booted.
	 */
	public static function instance_dir(): string {
		return self::$instance ? self::$instance->dir() : '';
	}

	/**
	 * Get the plugin's directory path.
	 *
	 * @return string Plugin directory path with trailing slash.
	 */
	public function dir(): string {
		return $this->dir;
	}

	/**
	 * Get the plugin's directory URL.
	 *
	 * @return string Plugin URL with trailing slash.
	 */
	public function url(): string {
		return $this->url;
	}

	/**
	 * Get the plugin's entry file path.
	 *
	 * @return string Plugin entry file path.
	 */
	public function file(): string {
		return $this->file;
	}
}
