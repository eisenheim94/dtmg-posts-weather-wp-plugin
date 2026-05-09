<?php
/**
 * Weather data transfer object.
 *
 * @package DTMG\PostsWeatherBlock\Weather
 */

declare( strict_types=1 );

namespace DTMG\PostsWeatherBlock\Weather;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable, JSON-serialisable view of a single OpenWeatherMap response.
 */
final class WeatherDTO {

	/**
	 * Construct a fully-populated DTO. Prefer ::from_response() for raw API data.
	 *
	 * @param string $location    Location name.
	 * @param float  $temp        Temperature in Celsius.
	 * @param float  $feels_like  Apparent temperature in Celsius.
	 * @param string $condition   Short weather condition (e.g. "Clouds").
	 * @param string $description Long-form weather description.
	 * @param string $icon        OpenWeatherMap icon code.
	 * @param int    $humidity    Relative humidity percentage.
	 * @param int    $pressure    Atmospheric pressure in hPa.
	 * @param float  $wind_speed  Wind speed in m/s.
	 * @param int    $sunrise     Unix timestamp for sunrise.
	 * @param int    $sunset      Unix timestamp for sunset.
	 * @param int    $fetched_at  Unix timestamp when data was fetched.
	 */
	public function __construct(
		public readonly string $location,
		public readonly float $temp,
		public readonly float $feels_like,
		public readonly string $condition,
		public readonly string $description,
		public readonly string $icon,
		public readonly int $humidity,
		public readonly int $pressure,
		public readonly float $wind_speed,
		public readonly int $sunrise,
		public readonly int $sunset,
		public readonly int $fetched_at,
	) {}

	/**
	 * Build a DTO from the raw OpenWeatherMap /data/2.5/weather response array.
	 *
	 * @param array<string,mixed> $data Decoded JSON body.
	 * @throws \InvalidArgumentException If required fields are missing.
	 */
	public static function from_response( array $data ): self {
		$required = [ 'name', 'main', 'weather', 'wind', 'sys' ];
		foreach ( $required as $field ) {
			if ( ! isset( $data[ $field ] ) ) {
				throw new \InvalidArgumentException( sprintf( 'OpenWeatherMap response missing "%s".', esc_html( $field ) ) );
			}
		}
		$weather = $data['weather'][0] ?? [];

		return new self(
			location:    (string) $data['name'],
			temp:        (float) ( $data['main']['temp'] ?? 0.0 ),
			feels_like:  (float) ( $data['main']['feels_like'] ?? 0.0 ),
			condition:   (string) ( $weather['main'] ?? '' ),
			description: (string) ( $weather['description'] ?? '' ),
			icon:        (string) ( $weather['icon'] ?? '' ),
			humidity:    (int) ( $data['main']['humidity'] ?? 0 ),
			pressure:    (int) ( $data['main']['pressure'] ?? 0 ),
			wind_speed:  (float) ( $data['wind']['speed'] ?? 0.0 ),
			sunrise:     (int) ( $data['sys']['sunrise'] ?? 0 ),
			sunset:      (int) ( $data['sys']['sunset'] ?? 0 ),
			fetched_at:  time(),
		);
	}

	/**
	 * Convert the DTO to a JSON-serialisable associative array.
	 *
	 * @return array<string,scalar>
	 */
	public function to_array(): array {
		return [
			'location'    => $this->location,
			'temp'        => $this->temp,
			'feels_like'  => $this->feels_like,
			'condition'   => $this->condition,
			'description' => $this->description,
			'icon'        => $this->icon,
			'humidity'    => $this->humidity,
			'pressure'    => $this->pressure,
			'wind_speed'  => $this->wind_speed,
			'sunrise'     => $this->sunrise,
			'sunset'      => $this->sunset,
			'fetched_at'  => $this->fetched_at,
		];
	}
}
