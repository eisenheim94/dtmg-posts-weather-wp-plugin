<?php
/**
 * HTTP client for OpenWeatherMap.
 *
 * @package DTMG\PostsWeatherBlock\Weather
 */

declare( strict_types=1 );

namespace DTMG\PostsWeatherBlock\Weather;

use DTMG\PostsWeatherBlock\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Performs a single GET against /data/2.5/weather.
 *
 * No caching, no normalization. The Service handles those.
 */
final class WeatherClient {

	private const ENDPOINT = 'https://api.openweathermap.org/data/2.5/weather';

	/**
	 * Capture the configured API key.
	 *
	 * @param string $api_key OpenWeatherMap API key. Empty string disables the client.
	 */
	public function __construct( private readonly string $api_key ) {}

	/**
	 * Fetch raw weather data.
	 *
	 * @param float $lat Latitude.
	 * @param float $lon Longitude.
	 * @return array<string,mixed>|\WP_Error Decoded JSON on success.
	 */
	public function fetch( float $lat, float $lon ) {
		if ( '' === $this->api_key ) {
			return new \WP_Error( 'dtmg_pwb_no_key', __( 'OpenWeatherMap API key is not configured.', 'dtmg-posts-weather-block' ), [ 'status' => 503 ] );
		}

		$url = add_query_arg(
			[
				'lat'   => $lat,
				'lon'   => $lon,
				'units' => 'metric',
				'appid' => $this->api_key,
			],
			self::ENDPOINT
		);

		$response = wp_remote_get(
			$url,
			[
				'timeout'    => 5,
				'user-agent' => 'dtmg-posts-weather-block/' . Plugin::VERSION,
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( 200 !== $code ) {
			return new \WP_Error(
				'dtmg_pwb_upstream',
				sprintf(
					/* translators: %d: HTTP status code from OpenWeatherMap. */
					__( 'OpenWeatherMap returned HTTP %d.', 'dtmg-posts-weather-block' ),
					$code
				),
				[ 'status' => 502 ]
			);
		}

		$decoded = json_decode( $body, true );
		if ( ! is_array( $decoded ) ) {
			return new \WP_Error( 'dtmg_pwb_decode', __( 'Could not decode OpenWeatherMap response.', 'dtmg-posts-weather-block' ), [ 'status' => 502 ] );
		}
		return $decoded;
	}
}
