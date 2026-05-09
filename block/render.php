<?php
/**
 * Server-side render callback for dtmg/posts-weather.
 *
 * DOM follows Figma frame "TM-Topic-Page-L":
 *   .__inner
 *     .__hero  → post #1 (large)
 *     .__sidebar → post #2 (small) + weather widget
 *
 * Slot mapping is fixed to the design contract: the first selected post is
 * always the hero, the second is the sidebar tile, and the weather widget is
 * the bottom sidebar tile (rendered only when valid lat/long resolves to a
 * WeatherDTO). Empty cells are simply omitted; the column collapses.
 *
 * @package DTMG\PostsWeatherBlock
 *
 * @var array<string,mixed> $attributes Block attributes from block.json.
 * @var string              $content    Rendered inner blocks (unused).
 * @var \WP_Block           $block      Block instance.
 */

declare( strict_types=1 );

use DTMG\PostsWeatherBlock\Plugin;
use DTMG\PostsWeatherBlock\Weather\WeatherDTO;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- The closure builds a fully-escaped HTML string; partials handle escaping at the call site.
echo ( static function ( array $attributes ): string {
	$post_ids  = isset( $attributes['postIds'] ) && is_array( $attributes['postIds'] ) ? array_map( 'intval', $attributes['postIds'] ) : [];
	$latitude  = isset( $attributes['latitude'] ) && is_numeric( $attributes['latitude'] ) ? (float) $attributes['latitude'] : null;
	$longitude = isset( $attributes['longitude'] ) && is_numeric( $attributes['longitude'] ) ? (float) $attributes['longitude'] : null;

	/*
	 * `weatherFields` arrives as an array on the front-end (block-comment JSON
	 * parsed with assoc=true), but the block-renderer REST path used by the
	 * editor's ServerSideRender can hand us a stdClass instead. Normalise both
	 * shapes to an associative array so the partial sees the same data either
	 * way; otherwise an unrecognised shape collapsed to `[]`, wp_parse_args
	 * filled in all-true defaults, and the toggles silently had no effect.
	 */
	$weather_fields = [];
	if ( isset( $attributes['weatherFields'] ) ) {
		$raw = $attributes['weatherFields'];
		if ( is_array( $raw ) ) {
			$weather_fields = $raw;
		} elseif ( is_object( $raw ) ) {
			$weather_fields = get_object_vars( $raw );
		}
	}

	/* Resolve at most two valid posts in the order the user picked them. */
	$resolved_posts = [];
	foreach ( $post_ids as $candidate_id ) {
		if ( $candidate_id <= 0 ) {
			continue;
		}
		$candidate = get_post( $candidate_id );
		if ( $candidate && 'publish' === $candidate->post_status ) {
			$resolved_posts[] = $candidate;
		}
		if ( count( $resolved_posts ) >= 2 ) {
			break;
		}
	}

	$hero_post    = $resolved_posts[0] ?? null;
	$sidebar_post = $resolved_posts[1] ?? null;

	$weather       = null;
	$weather_error = null;
	if ( null !== $latitude && null !== $longitude
		&& $latitude >= -90.0 && $latitude <= 90.0
		&& $longitude >= -180.0 && $longitude <= 180.0 ) {
		$result = Plugin::instance( '' )->weather_service()->get( $latitude, $longitude );
		if ( $result instanceof WeatherDTO ) {
			$weather = $result;
		} else {
			$weather_error = $result;
		}
	}

	$show_admin_errors = current_user_can( 'manage_options' );

	/*
	 * Translate block color settings into CSS custom properties on the wrapper
	 * so weather elements (eyebrow, location, icons, refresh button, media-tile
	 * background) can opt into them via `var(--pwb-block-text, …fallback)` and
	 * `var(--pwb-block-bg, …fallback)`. We emit our own vars in addition to the
	 * `color` / `background-color` inline styles WP injects automatically,
	 * because `background-color` doesn't inherit naturally to nested children
	 * and several weather elements override `color` to a designed accent.
	 *
	 * Two source shapes are handled:
	 *   - Custom hex picks (`style.color.text` / `style.color.background`)
	 *   - Theme presets (`textColor` / `backgroundColor` slugs → wp preset var)
	 */
	$style_pairs = [];

	/*
	 * Theme preset slugs (`textColor` / `backgroundColor`) need the same
	 * kebab-case transform WP applies when generating the
	 * `--wp--preset--color--<slug>` variables, otherwise digit-trailing slugs
	 * like "theme-palette14" produce `…--theme-palette14` here while the
	 * actual WP-defined variable is `…--theme-palette-14`. The mismatch makes
	 * the var() call undefined, and every `var(--pwb-block-text, …)` in the
	 * SCSS silently falls through to its default — leaving the weather tile
	 * unchanged. `_wp_to_kebab_case()` is the same helper core uses for this.
	 */
	if ( ! empty( $attributes['style']['color']['text'] ) && is_string( $attributes['style']['color']['text'] ) ) {
		$style_pairs[] = '--pwb-block-text:' . $attributes['style']['color']['text'];
	} elseif ( ! empty( $attributes['textColor'] ) && is_string( $attributes['textColor'] ) ) {
		$style_pairs[] = '--pwb-block-text:var(--wp--preset--color--' . _wp_to_kebab_case( $attributes['textColor'] ) . ')';
	}

	if ( ! empty( $attributes['style']['color']['background'] ) && is_string( $attributes['style']['color']['background'] ) ) {
		$style_pairs[] = '--pwb-block-bg:' . $attributes['style']['color']['background'];
	} elseif ( ! empty( $attributes['backgroundColor'] ) && is_string( $attributes['backgroundColor'] ) ) {
		$style_pairs[] = '--pwb-block-bg:var(--wp--preset--color--' . _wp_to_kebab_case( $attributes['backgroundColor'] ) . ')';
	}

	$wrapper_args = [
		'class'    => 'wp-block-dtmg-posts-weather',
		'data-lat' => null !== $latitude ? (string) $latitude : '',
		'data-lon' => null !== $longitude ? (string) $longitude : '',
	];
	if ( ! empty( $style_pairs ) ) {
		/* `get_block_wrapper_attributes` merges this with WP's auto-generated style. */
		$wrapper_args['style'] = implode( ';', $style_pairs ) . ';';
	}

	$wrapper_attributes = get_block_wrapper_attributes( $wrapper_args );

	if ( null === $hero_post && null === $sidebar_post && null === $weather && ! $show_admin_errors ) {
		return '';
	}

	/* Sidebar shows nothing? Skip the column entirely so the hero can grow. */
	$has_sidebar = null !== $sidebar_post || null !== $weather || ( null !== $weather_error && $show_admin_errors );

	ob_start();
	?>
	<section <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() returns pre-escaped HTML. ?>
		aria-label="<?php esc_attr_e( 'Posts and weather', 'dtmg-posts-weather-block' ); ?>">
		<div class="wp-block-dtmg-posts-weather__inner">

			<?php if ( null !== $hero_post ) : ?>
				<div class="wp-block-dtmg-posts-weather__hero">
					<?php
					$resolved_post = $hero_post;
					$is_hero       = true;
					require __DIR__ . '/partials/post-card.php';
					?>
				</div>
			<?php endif; ?>

			<?php if ( $has_sidebar ) : ?>
				<div class="wp-block-dtmg-posts-weather__sidebar">

					<?php if ( null !== $sidebar_post ) : ?>
						<?php
						$resolved_post = $sidebar_post;
						$is_hero       = false;
						require __DIR__ . '/partials/post-card.php';
						?>
					<?php endif; ?>

					<?php if ( $weather instanceof WeatherDTO ) : ?>
						<?php require __DIR__ . '/partials/weather-aside.php'; ?>
					<?php elseif ( null !== $weather_error && $show_admin_errors ) : ?>
						<aside class="wp-block-dtmg-posts-weather__weather wp-block-dtmg-posts-weather__weather--error">
							<p>
								<?php
								printf(
									/* translators: %s: error message from weather service. */
									esc_html__( 'Weather unavailable: %s', 'dtmg-posts-weather-block' ),
									esc_html( $weather_error->get_error_message() )
								);
								?>
							</p>
						</aside>
					<?php endif; ?>

				</div>
			<?php endif; ?>

		</div>
	</section>
	<?php
	return (string) ob_get_clean();
} )( is_array( $attributes ?? null ) ? $attributes : [] );
// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
