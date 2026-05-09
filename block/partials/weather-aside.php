<?php
/**
 * Weather aside partial for dtmg/posts-weather.
 *
 * Visually mirrors the Figma sidebar card rhythm:
 *   media tile (5:3) showing temp + condition → location headline → details list → refresh.
 *
 * @package DTMG\PostsWeatherBlock
 *
 * @var \DTMG\PostsWeatherBlock\Weather\WeatherDTO $weather          Weather DTO.
 * @var array<string,bool>                         $weather_fields   Visibility map.
 * @var string                                     $units            'metric' | 'imperial'.
 * @var string                                     $time_format_pref 'auto' | '12' | '24'.
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

$f = wp_parse_args(
	is_array( $weather_fields ) ? $weather_fields : ( is_object( $weather_fields ) ? get_object_vars( $weather_fields ) : [] ),
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

/*
 * Coerce every flag to a strict boolean. The REST → JSON → query-string round
 * trip used by `<ServerSideRender>` in the editor can produce values like the
 * string "false" — which is truthy in PHP and would silently re-enable a
 * toggled-off field. Accept the common falsy spellings explicitly.
 */
foreach ( $f as $key => $value ) {
	if ( is_bool( $value ) ) {
		continue;
	}
	if ( is_string( $value ) ) {
		$f[ $key ] = ! in_array( strtolower( trim( $value ) ), [ '', '0', 'false', 'no', 'off' ], true );
	} else {
		$f[ $key ] = (bool) $value;
	}
}

$tz   = wp_timezone();
$rise = ( new \DateTimeImmutable( '@' . $weather->sunrise ) )->setTimezone( $tz );
$set  = ( new \DateTimeImmutable( '@' . $weather->sunset ) )->setTimezone( $tz );

/*
 * Resolve the time-format preference into:
 *   - $time_format:  the PHP `wp_date()` format string used for SSR output.
 *   - $localize:     whether to emit `data-pwb-localtime`, which is the marker
 *                    the inline script + edit.js MutationObserver key off to
 *                    rewrite text using the visitor's browser locale.
 *
 * 'auto' keeps the current behaviour: render the site-wide time format
 * server-side as the no-JS fallback, then let the client localizer override.
 * '12' / '24' are explicit choices — render that exact format and skip the
 * marker so the client localizer leaves the text alone.
 */
$time_format_pref = isset( $time_format_pref ) && in_array( $time_format_pref, [ '12', '24' ], true ) ? $time_format_pref : 'auto';
if ( '12' === $time_format_pref ) {
	$time_format = 'g:i A';
	$localize    = false;
} elseif ( '24' === $time_format_pref ) {
	$time_format = 'H:i';
	$localize    = false;
} else {
	$time_format = (string) get_option( 'time_format', 'H:i' );
	$localize    = true;
}

/*
 * Resolve the display unit. The OWM client always fetches metric (and the
 * cache is keyed only on lat/lon), so any imperial output is computed here
 * from the cached Celsius/m·s values — no extra HTTP round-trip, no second
 * cache key.
 */
$units_choice = ( isset( $units ) && 'imperial' === $units ) ? 'imperial' : 'metric';

if ( ! function_exists( 'dtmg_pwb_format_temperature' ) ) {

	/**
	 * Render a temperature value in the chosen unit, fully translated.
	 *
	 * @param float  $celsius     Temperature in degrees Celsius (DTO value).
	 * @param string $units       'metric' or 'imperial'.
	 * @param bool   $with_spacer Whether the unit symbol should be space-separated (true for "feels like" rows; false for the big tile temp).
	 */
	function dtmg_pwb_format_temperature( float $celsius, string $units, bool $with_spacer ): string {
		$value     = 'imperial' === $units ? ( $celsius * 9 / 5 ) + 32 : $celsius;
		$formatted = number_format_i18n( (float) round( $value ), 0 );

		if ( $with_spacer ) {
			$template = 'imperial' === $units
				/* translators: %s: temperature value in degrees Fahrenheit. */
				? __( '%s °F', 'dtmg-posts-weather-block' )
				/* translators: %s: temperature value in degrees Celsius. */
				: __( '%s °C', 'dtmg-posts-weather-block' );
		} else {
			$template = 'imperial' === $units
				/* translators: %s: temperature value in degrees Fahrenheit. */
				? __( '%s°F', 'dtmg-posts-weather-block' )
				/* translators: %s: temperature value in degrees Celsius. */
				: __( '%s°C', 'dtmg-posts-weather-block' );
		}
		return sprintf( $template, $formatted );
	}
}

if ( ! function_exists( 'dtmg_pwb_format_wind' ) ) {

	/**
	 * Render a wind-speed value in the chosen unit, fully translated.
	 *
	 * @param float  $ms    Wind speed in metres per second (DTO value).
	 * @param string $units 'metric' or 'imperial'.
	 */
	function dtmg_pwb_format_wind( float $ms, string $units ): string {
		$value     = 'imperial' === $units ? $ms * 2.23694 : $ms;
		$formatted = number_format_i18n( (float) round( $value ), 0 );

		$template = 'imperial' === $units
			/* translators: %s: wind speed in miles per hour. */
			? __( '%s mph', 'dtmg-posts-weather-block' )
			/* translators: %s: wind speed in metres per second. */
			: __( '%s m/s', 'dtmg-posts-weather-block' );

		return sprintf( $template, $formatted );
	}
}

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
					echo esc_html( dtmg_pwb_format_temperature( $weather->temp, $units_choice, false ) );
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
						echo esc_html( dtmg_pwb_format_temperature( $weather->feels_like, $units_choice, true ) );
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
						echo esc_html( dtmg_pwb_format_wind( $weather->wind_speed, $units_choice ) );
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
						<time<?php echo $localize ? ' data-pwb-localtime' : ''; ?> datetime="<?php echo esc_attr( $rise->format( DATE_W3C ) ); ?>">
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
						<time<?php echo $localize ? ' data-pwb-localtime' : ''; ?> datetime="<?php echo esc_attr( $set->format( DATE_W3C ) ); ?>">
							<?php echo esc_html( wp_date( $time_format, $weather->sunset ) ); ?>
						</time>
					</dd>
				</div>
			<?php endif; ?>
		</dl>
	</div>
	<?php if ( $localize && ( $f['sunrise'] || $f['sunset'] ) ) : ?>
		<?php
		/*
		 * Localize sunrise/sunset to the visitor's browser locale + hour-cycle
		 * preference (so a US visitor sees "4:58 AM" while a German one sees
		 * "04:58"). The PHP-rendered `wp_date()` value above is the SSR fallback
		 * for no-JS clients; this swap is a progressive enhancement.
		 *
		 * Inlined deliberately: a single ~10-line DOM tweak doesn't justify
		 * re-introducing a `viewScript` entry-point (extra HTTP request, build
		 * step, asset.php). If a strict CSP rules out inline scripts later, lift
		 * this back into a `view.js`.
		 *
		 * Output is escaped via `wp_strip_all_tags`+`wp_json_encode` indirectly
		 * (no dynamic data flows in), and the script is statically authored.
		 */
		?>
		<script>
		/*
		 * Written without `&&` / `&` operators on purpose: this script lives
		 * inside `the_content`, and WP's text filters (wptexturize et al.)
		 * encode any literal `&` to `&#038;`, which would turn `&&` into
		 * `&#038;&#038;` — invalid JS, silent SyntaxError, swap never runs.
		 * Using guarded early-`continue` keeps the source ASCII-safe through
		 * those filters. Same reason `<` only appears inside the `for` header
		 * (single `<` survives texturize, but the pattern is fragile so any
		 * future edit should keep operators ampersand-free or move this script
		 * out of the_content via wp_print_footer_scripts).
		 */
		(function(){
			var nodes = document.currentScript.parentNode.querySelectorAll('[data-pwb-localtime]');
			for (var i = 0; i < nodes.length; i++) {
				var iso = nodes[i].getAttribute('datetime');
				if (!iso) { continue; }
				var d = new Date(iso);
				if (isNaN(d.getTime())) { continue; }
				nodes[i].textContent = d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
			}
		})();
		</script>
	<?php endif; ?>
</aside>
