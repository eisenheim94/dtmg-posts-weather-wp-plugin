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
	 * Lucide icon-font handle. Loaded from the unpkg CDN.
	 *
	 * Body typography is intentionally left to the active theme so the block
	 * inherits the surrounding site's font stack. Only the Lucide icon font is
	 * registered here because the weather snippet relies on its glyphs and no
	 * theme is expected to ship an equivalent.
	 */
	private const LUCIDE_HANDLE = 'dtmg-pwb-lucide';

	/**
	 * Hook block registration onto init.
	 */
	public function register(): void {
		add_action( 'init', [ $this, 'register_block' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'register_lucide' ] );

		/*
		 * `enqueue_block_assets` (vs. `enqueue_block_editor_assets`) is the
		 * hook that fires INSIDE the editor iframe. Modern Gutenberg renders
		 * block previews in an iframe, and stylesheets attached only to the
		 * parent admin frame don't reach the iframe content. We gate on
		 * is_admin() so the front-end branch is left to the lazy render_block
		 * attach below.
		 */
		add_action( 'enqueue_block_assets', [ $this, 'enqueue_editor_lucide' ] );
		add_filter( 'render_block', [ $this, 'attach_lucide_on_render' ], 10, 2 );
	}

	/**
	 * Register the Lucide stylesheet so render-time enqueue is cheap.
	 *
	 * Hooked on `wp_enqueue_scripts` (front-end). Editor uses
	 * {@see enqueue_editor_lucide()} which always loads the stylesheet because
	 * ServerSideRender doesn't pass through `render_block`.
	 */
	public function register_lucide(): void {
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
	 * Editor preview: load Lucide inside the editor iframe so the
	 * ServerSideRender output looks identical to the front-end. Gated on
	 * is_admin() because `enqueue_block_assets` also fires on the front-end,
	 * where {@see attach_lucide_on_render()} handles lazy loading instead.
	 */
	public function enqueue_editor_lucide(): void {
		if ( ! is_admin() ) {
			return;
		}
		$this->register_lucide();
		wp_enqueue_style( self::LUCIDE_HANDLE );
	}

	/**
	 * Attach the Lucide handle the moment our block is rendered. Avoids
	 * loading the CDN on pages that don't use the block.
	 *
	 * @param string              $block_content Rendered block markup.
	 * @param array<string,mixed> $block         Parsed block array.
	 */
	public function attach_lucide_on_render( string $block_content, array $block ): string {
		if ( isset( $block['blockName'] ) && 'dtmg/posts-weather' === $block['blockName'] ) {
			$this->register_lucide();
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
