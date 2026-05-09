<?php
/**
 * Weather service: caches and orchestrates the WeatherClient.
 *
 * @package DTMG\PostsWeatherBlock\Weather
 */

declare( strict_types=1 );

namespace DTMG\PostsWeatherBlock\Weather;

defined( 'ABSPATH' ) || exit;

/**
 * Caches DTOs in transients keyed by rounded lat/lon.
 *
 * Maintains an "index option" listing all keys it has written so flush_all()
 * can clear exactly the plugin's keys without scanning wp_options or
 * touching unrelated transients.
 */
final class WeatherService {

	private const CACHE_TTL    = HOUR_IN_SECONDS;
	private const CACHE_PREFIX = 'dtmg_pwb_weather_';
	private const INDEX_OPTION = 'dtmg_pwb_cache_index';

	/**
	 * Inject the HTTP client the service delegates to.
	 *
	 * @param WeatherClient $client HTTP client to delegate fetches to.
	 */
	public function __construct( private readonly WeatherClient $client ) {}

	/**
	 * Resolve weather for the given coordinates, hitting the cache first.
	 *
	 * @param float $lat Latitude.
	 * @param float $lon Longitude.
	 * @return WeatherDTO|\WP_Error
	 */
	public function get( float $lat, float $lon ) {
		[ $lat, $lon ] = self::normalize_coords( $lat, $lon );
		$key           = self::cache_key( $lat, $lon );

		$cached = get_transient( $key );
		if ( $cached instanceof WeatherDTO ) {
			return $cached;
		}

		$raw = $this->client->fetch( $lat, $lon );
		if ( is_wp_error( $raw ) ) {
			return $raw;
		}

		try {
			$dto = WeatherDTO::from_response( $raw );
		} catch ( \InvalidArgumentException $e ) {
			return new \WP_Error( 'dtmg_pwb_shape', $e->getMessage(), [ 'status' => 502 ] );
		}

		set_transient( $key, $dto, self::CACHE_TTL );
		$this->register_key( $key );
		return $dto;
	}

	/**
	 * Drop a single cached entry.
	 *
	 * @param float $lat Latitude.
	 * @param float $lon Longitude.
	 * @return bool True if a cache entry was deleted.
	 */
	public function flush( float $lat, float $lon ): bool {
		[ $lat, $lon ] = self::normalize_coords( $lat, $lon );
		$key           = self::cache_key( $lat, $lon );

		$deleted = delete_transient( $key );
		$this->unregister_key( $key );
		return $deleted;
	}

	/**
	 * Drop every cached entry tracked by the index.
	 *
	 * @return int Number of cache entries successfully deleted.
	 */
	public function flush_all(): int {
		$index = $this->index();
		$count = 0;
		foreach ( $index as $key ) {
			if ( delete_transient( $key ) ) {
				++$count;
			}
		}
		delete_option( self::INDEX_OPTION );
		return $count;
	}

	/**
	 * Read the persistent cache-key index, defensively decoded.
	 *
	 * @return array<int,string> List of cache keys currently tracked.
	 */
	private function index(): array {
		$stored = get_option( self::INDEX_OPTION, [] );
		return is_array( $stored ) ? array_values( array_unique( array_filter( $stored, 'is_string' ) ) ) : [];
	}

	/**
	 * Add a cache key to the persistent index.
	 *
	 * @param string $key Transient key.
	 */
	private function register_key( string $key ): void {
		$index = $this->index();
		if ( in_array( $key, $index, true ) ) {
			return;
		}
		$index[] = $key;
		update_option( self::INDEX_OPTION, $index, false );
	}

	/**
	 * Remove a cache key from the persistent index.
	 *
	 * @param string $key Transient key.
	 */
	private function unregister_key( string $key ): void {
		$index = $this->index();
		$index = array_values( array_filter( $index, static fn( string $k ): bool => $k !== $key ) );
		if ( $index ) {
			update_option( self::INDEX_OPTION, $index, false );
		} else {
			delete_option( self::INDEX_OPTION );
		}
	}

	/**
	 * Round coordinates to ~1 km precision so nearby requests share a cache key.
	 *
	 * @param float $lat Latitude.
	 * @param float $lon Longitude.
	 * @return array{0:float,1:float}
	 */
	private static function normalize_coords( float $lat, float $lon ): array {
		return [ round( $lat, 2 ), round( $lon, 2 ) ];
	}

	/**
	 * Build the transient key for a normalized lat/lon pair.
	 *
	 * @param float $lat Latitude.
	 * @param float $lon Longitude.
	 */
	private static function cache_key( float $lat, float $lon ): string {
		return self::CACHE_PREFIX . md5( $lat . '|' . $lon );
	}
}
