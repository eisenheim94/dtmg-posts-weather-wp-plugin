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
	$post_ids       = isset( $attributes['postIds'] ) && is_array( $attributes['postIds'] ) ? array_map( 'intval', $attributes['postIds'] ) : [];
	$latitude       = isset( $attributes['latitude'] ) && is_numeric( $attributes['latitude'] ) ? (float) $attributes['latitude'] : null;
	$longitude      = isset( $attributes['longitude'] ) && is_numeric( $attributes['longitude'] ) ? (float) $attributes['longitude'] : null;
	$weather_fields = isset( $attributes['weatherFields'] ) && is_array( $attributes['weatherFields'] ) ? $attributes['weatherFields'] : [];

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

	$wrapper_attributes = get_block_wrapper_attributes(
		[
			'class'    => 'wp-block-dtmg-posts-weather',
			'data-lat' => null !== $latitude ? (string) $latitude : '',
			'data-lon' => null !== $longitude ? (string) $longitude : '',
		]
	);

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
