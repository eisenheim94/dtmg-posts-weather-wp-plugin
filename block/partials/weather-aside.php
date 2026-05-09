<?php
/**
 * Weather aside partial for dtmg/posts-weather.
 *
 * Visually mirrors the Figma sidebar card rhythm:
 *   media tile (5:3) showing temp + condition → location headline → details list → refresh.
 *
 * @package DTMG\PostsWeatherBlock
 *
 * @var \DTMG\PostsWeatherBlock\Weather\WeatherDTO $weather        Weather DTO.
 * @var array<string,bool>                         $weather_fields Visibility map.
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

$f = wp_parse_args(
	$weather_fields,
	[
		'location'  => true,
		'temp'      => true,
		'feelsLike' => true,
		'condition' => true,
		'humidity'  => true,
		'pressure'  => true,
		'wind'      => true,
		'sunrise'   => true,
		'sunset'    => true,
	]
);

$tz          = wp_timezone();
$rise        = ( new \DateTimeImmutable( '@' . $weather->sunrise ) )->setTimezone( $tz );
$set         = ( new \DateTimeImmutable( '@' . $weather->sunset ) )->setTimezone( $tz );
$time_format = (string) get_option( 'time_format', 'H:i' );

/* Whether to show the gradient media tile at all. */
$show_media = $f['temp'] || $f['condition'];

/*
 * Map an OpenWeatherMap condition + icon code (suffix `d`/`n`) to a Lucide
 * icon class. Mirrors the JS implementation in view.js — keep them in sync.
 *
 * Reference: https://openweathermap.org/weather-conditions
 */
if ( ! function_exists( 'dtmg_pwb_lucide_icon_for_condition' ) ) {

	/**
	 * @param string $condition OWM `weather[0].main` value (e.g. "Clouds").
	 * @param string $icon      OWM `weather[0].icon` code (e.g. "01d", "10n").
	 */
	function dtmg_pwb_lucide_icon_for_condition( string $condition, string $icon ): string {
		$is_night = '' !== $icon && 'n' === substr( $icon, -1 );

		switch ( $condition ) {
			case 'Clear':
				return $is_night ? 'icon-moon' : 'icon-sun';
			case 'Clouds':
				/* OWM icon "02" == few clouds, gets sun/moon hybrid; "03"/"04" overcast. */
				$prefix = '' !== $icon ? substr( $icon, 0, 2 ) : '';
				if ( '02' === $prefix ) {
					return $is_night ? 'icon-cloud-moon' : 'icon-cloud-sun';
				}
				return 'icon-cloudy';
			case 'Rain':
				return 'icon-cloud-rain';
			case 'Drizzle':
				return 'icon-cloud-drizzle';
			case 'Thunderstorm':
				return 'icon-cloud-lightning';
			case 'Snow':
				return 'icon-cloud-snow';
			case 'Tornado':
				return 'icon-tornado';
			case 'Squall':
				return 'icon-wind';
			/* Atmospheric: Mist, Smoke, Haze, Dust, Fog, Sand, Ash. */
			default:
				return 'icon-cloud-fog';
		}
	}
}

$condition_icon_class = dtmg_pwb_lucide_icon_for_condition( $weather->condition, $weather->icon );
?>
<aside class="wp-block-dtmg-posts-weather__weather"
	aria-label="<?php esc_attr_e( 'Current weather', 'dtmg-posts-weather-block' ); ?>">

	<?php if ( $show_media ) : ?>
		<div class="wp-block-dtmg-posts-weather__weather-media" aria-hidden="true">
			<?php if ( $f['condition'] ) : ?>
				<i class="wp-block-dtmg-posts-weather__weather-condition-icon <?php echo esc_attr( $condition_icon_class ); ?>"
					data-pwb-condition-icon
					aria-hidden="true"></i>
			<?php endif; ?>
			<?php if ( $f['temp'] ) : ?>
				<p class="wp-block-dtmg-posts-weather__weather-temp" data-pwb-field="temp"><?php
					echo esc_html( sprintf( '%s°C', number_format_i18n( $weather->temp, 1 ) ) );
				?></p>
			<?php endif; ?>
			<?php if ( $f['condition'] ) : ?>
				<p class="wp-block-dtmg-posts-weather__weather-condition" data-pwb-field="description"><?php
					echo esc_html( ucfirst( $weather->description ) );
				?></p>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<div class="wp-block-dtmg-posts-weather__weather-body">
		<div class="wp-block-dtmg-posts-weather__weather-heading">
			<p class="wp-block-dtmg-posts-weather__weather-eyebrow"><?php esc_html_e( 'Weather', 'dtmg-posts-weather-block' ); ?></p>
			<?php if ( $f['location'] ) : ?>
				<p class="wp-block-dtmg-posts-weather__weather-location" data-pwb-field="location"><?php echo esc_html( $weather->location ); ?></p>
			<?php endif; ?>
		</div>

		<dl class="wp-block-dtmg-posts-weather__weather-list">
			<?php if ( $f['feelsLike'] ) : ?>
				<div class="wp-block-dtmg-posts-weather__weather-row">
					<dt>
						<i class="wp-block-dtmg-posts-weather__weather-row-icon icon-thermometer" aria-hidden="true"></i>
						<span><?php esc_html_e( 'Feels like', 'dtmg-posts-weather-block' ); ?></span>
					</dt>
					<dd data-pwb-field="feels_like"><?php
						echo esc_html( sprintf( '%s °C', number_format_i18n( $weather->feels_like, 1 ) ) );
					?></dd>
				</div>
			<?php endif; ?>

			<?php if ( $f['humidity'] ) : ?>
				<div class="wp-block-dtmg-posts-weather__weather-row">
					<dt>
						<i class="wp-block-dtmg-posts-weather__weather-row-icon icon-droplets" aria-hidden="true"></i>
						<span><?php esc_html_e( 'Humidity', 'dtmg-posts-weather-block' ); ?></span>
					</dt>
					<dd data-pwb-field="humidity"><?php
						echo esc_html( sprintf( '%d %%', $weather->humidity ) );
					?></dd>
				</div>
			<?php endif; ?>

			<?php if ( $f['pressure'] ) : ?>
				<div class="wp-block-dtmg-posts-weather__weather-row">
					<dt>
						<i class="wp-block-dtmg-posts-weather__weather-row-icon icon-gauge" aria-hidden="true"></i>
						<span><?php esc_html_e( 'Pressure', 'dtmg-posts-weather-block' ); ?></span>
					</dt>
					<dd data-pwb-field="pressure"><?php
						echo esc_html( sprintf( '%d hPa', $weather->pressure ) );
					?></dd>
				</div>
			<?php endif; ?>

			<?php if ( $f['wind'] ) : ?>
				<div class="wp-block-dtmg-posts-weather__weather-row">
					<dt>
						<i class="wp-block-dtmg-posts-weather__weather-row-icon icon-wind" aria-hidden="true"></i>
						<span><?php esc_html_e( 'Wind', 'dtmg-posts-weather-block' ); ?></span>
					</dt>
					<dd data-pwb-field="wind_speed"><?php
						echo esc_html( sprintf( '%s m/s', number_format_i18n( $weather->wind_speed, 1 ) ) );
					?></dd>
				</div>
			<?php endif; ?>

			<?php if ( $f['sunrise'] ) : ?>
				<div class="wp-block-dtmg-posts-weather__weather-row">
					<dt>
						<i class="wp-block-dtmg-posts-weather__weather-row-icon icon-sunrise" aria-hidden="true"></i>
						<span><?php esc_html_e( 'Sunrise', 'dtmg-posts-weather-block' ); ?></span>
					</dt>
					<dd data-pwb-field="sunrise">
						<time datetime="<?php echo esc_attr( $rise->format( DATE_W3C ) ); ?>">
							<?php echo esc_html( wp_date( $time_format, $weather->sunrise ) ); ?>
						</time>
					</dd>
				</div>
			<?php endif; ?>

			<?php if ( $f['sunset'] ) : ?>
				<div class="wp-block-dtmg-posts-weather__weather-row">
					<dt>
						<i class="wp-block-dtmg-posts-weather__weather-row-icon icon-sunset" aria-hidden="true"></i>
						<span><?php esc_html_e( 'Sunset', 'dtmg-posts-weather-block' ); ?></span>
					</dt>
					<dd data-pwb-field="sunset">
						<time datetime="<?php echo esc_attr( $set->format( DATE_W3C ) ); ?>">
							<?php echo esc_html( wp_date( $time_format, $weather->sunset ) ); ?>
						</time>
					</dd>
				</div>
			<?php endif; ?>
		</dl>

		<button type="button"
			class="wp-block-dtmg-posts-weather__weather-refresh"
			data-pwb-refresh
			aria-label="<?php esc_attr_e( 'Refresh weather', 'dtmg-posts-weather-block' ); ?>">
			<?php esc_html_e( 'Refresh', 'dtmg-posts-weather-block' ); ?>
		</button>
	</div>
</aside>
