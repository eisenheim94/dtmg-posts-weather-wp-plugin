<?php
/**
 * Post card partial for dtmg/posts-weather.
 *
 * Layout follows Figma frame "TM-Topic-Page-L":
 *   image (5:3) → category eyebrow → headline → excerpt.
 * No date is rendered (it isn't in the Figma).
 *
 * @package DTMG\PostsWeatherBlock
 *
 * @var \WP_Post $resolved_post Post to render.
 * @var bool     $is_hero       True for the large left-column tile, false for sidebar tiles.
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

$is_hero = isset( $is_hero ) ? (bool) $is_hero : false;

/*
 * Visual word cap for the card excerpt. `wp_trim_words` splits on whitespace,
 * so hyphenated tokens like "limited-edition" stay intact (no mid-word cuts).
 * The partial is `require`d once per card, so guard against re-declaration.
 * Tune here (or via the `dtmg_pwb_excerpt_words` filter) if the design changes.
 */
if ( ! defined( 'DTMG_PWB_EXCERPT_WORDS' ) ) {
	define( 'DTMG_PWB_EXCERPT_WORDS', 25 );
}

/**
 * Filter the per-card excerpt word cap.
 *
 * @param int      $words         Word cap, default {@see DTMG_PWB_EXCERPT_WORDS}.
 * @param \WP_Post $resolved_post Post being rendered.
 * @param bool     $is_hero       Whether this is the large hero tile.
 */
$excerpt_words = (int) apply_filters( 'dtmg_pwb_excerpt_words', DTMG_PWB_EXCERPT_WORDS, $resolved_post, $is_hero );

$permalink   = get_permalink( $resolved_post );
$title       = get_the_title( $resolved_post );
$excerpt_raw = get_the_excerpt( $resolved_post );
/* Unicode ellipsis — not the `&hellip;` entity — so `esc_html` doesn't double-encode it. */
$excerpt = '' !== $excerpt_raw
	? wp_trim_words( $excerpt_raw, $excerpt_words, '…' )
	: '';
$thumb_id    = (int) get_post_thumbnail_id( $resolved_post );
$image_size = $is_hero ? 'large' : 'medium_large';
$heading_tag = $is_hero ? 'h2' : 'h3';
$variant_class = $is_hero ? 'wp-block-dtmg-posts-weather__card--hero' : 'wp-block-dtmg-posts-weather__card--small';

/* Use the first category as the eyebrow; link it to the term archive. */
$category_name = '';
$category_link = '';
$cats          = get_the_category( $resolved_post->ID );
if ( $cats && isset( $cats[0] ) ) {
	$category_name = $cats[0]->name;
	$link          = get_category_link( $cats[0]->term_id );
	if ( ! is_wp_error( $link ) ) {
		$category_link = (string) $link;
	}
}
?>
<article class="wp-block-dtmg-posts-weather__card <?php echo esc_attr( $variant_class ); ?>">
	<?php if ( $thumb_id > 0 ) : ?>
		<a class="wp-block-dtmg-posts-weather__card-media" href="<?php echo esc_url( $permalink ); ?>" tabindex="-1" aria-hidden="true">
			<?php
			echo wp_get_attachment_image( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image returns pre-escaped HTML.
				$thumb_id,
				$image_size,
				false,
				[
					'loading'  => 'lazy',
					'decoding' => 'async',
					'alt'      => '',
				]
			);
			?>
		</a>
	<?php endif; ?>
	<div class="wp-block-dtmg-posts-weather__card-body">
		<div class="wp-block-dtmg-posts-weather__card-heading">
			<?php if ( '' !== $category_name ) : ?>
				<p class="wp-block-dtmg-posts-weather__card-eyebrow">
					<?php if ( '' !== $category_link ) : ?>
						<a href="<?php echo esc_url( $category_link ); ?>"><?php echo esc_html( $category_name ); ?></a>
					<?php else : ?>
						<?php echo esc_html( $category_name ); ?>
					<?php endif; ?>
				</p>
			<?php endif; ?>
			<<?php echo esc_html( $heading_tag ); ?> class="wp-block-dtmg-posts-weather__card-title">
				<a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $title ); ?></a>
			</<?php echo esc_html( $heading_tag ); ?>>
		</div>
		<?php if ( '' !== $excerpt ) : ?>
			<p class="wp-block-dtmg-posts-weather__card-excerpt"><?php echo esc_html( $excerpt ); ?></p>
		<?php endif; ?>
	</div>
</article>
