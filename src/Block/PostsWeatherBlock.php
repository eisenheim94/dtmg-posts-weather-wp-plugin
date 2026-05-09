<?php
/**
 * Block registration.
 *
 * @package DTMG\PostsWeatherBlock\Block
 */

declare( strict_types=1 );

namespace DTMG\PostsWeatherBlock\Block;

use DTMG\PostsWeatherBlock\Plugin;
use DTMG\PostsWeatherBlock\Weather\WeatherService;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the dtmg/posts-weather block.
 *
 * The block is dynamic; render.php pulls the WeatherService via Plugin::instance(),
 * so this class only has to register the block metadata.
 */
final class PostsWeatherBlock {

	/**
	 * Inject the weather service so callers can pre-warm it.
	 *
	 * @param WeatherService $service Weather service the block render relies on.
	 */
	public function __construct( private readonly WeatherService $service ) {}

	/**
	 * Google Fonts handle. Loads Archivo + Archivo Narrow with `display=swap`
	 * so the block matches the Figma typography spec.
	 */
	private const FONTS_HANDLE = 'dtmg-pwb-fonts';

	/**
	 * Lucide icon-font handle. Loaded from the unpkg CDN.
	 */
	private const LUCIDE_HANDLE = 'dtmg-pwb-lucide';

	/**
	 * Hook block registration onto init.
	 */
	public function register(): void {
		add_action( 'init', [ $this, 'register_block' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'register_fonts' ] );
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_editor_fonts' ] );
		add_filter( 'render_block', [ $this, 'attach_fonts_on_render' ], 10, 2 );
	}

	/**
	 * Register the fonts stylesheet so render-time enqueue is cheap.
	 *
	 * Hooked on `wp_enqueue_scripts` (front-end). Editor uses
	 * {@see enqueue_editor_fonts()} which always loads the fonts because
	 * ServerSideRender doesn't pass through `render_block`.
	 */
	public function register_fonts(): void {
		if ( ! wp_style_is( self::FONTS_HANDLE, 'registered' ) ) {
			wp_register_style(
				self::FONTS_HANDLE,
				'https://fonts.googleapis.com/css2?family=Archivo+Narrow:wght@600&family=Archivo:wght@400;600&display=swap',
				[],
				null // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.NotInFooter, WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Google Fonts URL is versioned upstream.
			);
		}
		if ( ! wp_style_is( self::LUCIDE_HANDLE, 'registered' ) ) {
			wp_register_style(
				self::LUCIDE_HANDLE,
				'https://unpkg.com/lucide-static@latest/font/lucide.css',
				[],
				null // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.NotInFooter, WordPress.WP.EnqueuedResourceParameters.MissingVersion -- unpkg URL pins via @latest semantics.
			);
		}
	}

	/**
	 * Editor preview: load the same assets so the SSR preview matches the front-end.
	 */
	public function enqueue_editor_fonts(): void {
		$this->register_fonts();
		wp_enqueue_style( self::FONTS_HANDLE );
		wp_enqueue_style( self::LUCIDE_HANDLE );
	}

	/**
	 * Attach the fonts + Lucide handles the moment our block is rendered.
	 * Avoids loading either CDN on pages that don't use the block.
	 *
	 * @param string              $block_content Rendered block markup.
	 * @param array<string,mixed> $block         Parsed block array.
	 */
	public function attach_fonts_on_render( string $block_content, array $block ): string {
		if ( isset( $block['blockName'] ) && 'dtmg/posts-weather' === $block['blockName'] ) {
			$this->register_fonts();
			wp_enqueue_style( self::FONTS_HANDLE );
			wp_enqueue_style( self::LUCIDE_HANDLE );
		}
		return $block_content;
	}

	/**
	 * Register the block from the compiled build/ metadata directory.
	 */
	public function register_block(): void {
		$build_dir = Plugin::instance_dir() . 'build';
		if ( ! is_dir( $build_dir ) ) {
			// wp-scripts hasn't run yet; skip registration to avoid a noisy notice.
			return;
		}
		register_block_type( $build_dir );

		$registered = \WP_Block_Type_Registry::get_instance()->get_registered( 'dtmg/posts-weather' );
		if ( $registered && ! empty( $registered->editor_script_handles ) ) {
			foreach ( $registered->editor_script_handles as $handle ) {
				wp_set_script_translations(
					$handle,
					'dtmg-posts-weather-block',
					Plugin::instance_dir() . 'languages'
				);
			}
		}
	}
}
