<?php
/**
 * WP-CLI command: dtmg-weather flush.
 *
 * @package DTMG\PostsWeatherBlock\CLI
 */

declare( strict_types=1 );

namespace DTMG\PostsWeatherBlock\CLI;

use DTMG\PostsWeatherBlock\Weather\WeatherService;

defined( 'ABSPATH' ) || exit;

/**
 * Manage the cached OpenWeatherMap responses.
 */
final class FlushCacheCommand {

	/**
	 * Inject the weather service the command operates on.
	 *
	 * @param WeatherService $service Weather service that owns the cache.
	 */
	public function __construct( private readonly WeatherService $service ) {}

	/**
	 * Flush cached weather data.
	 *
	 * ## OPTIONS
	 *
	 * [--lat=<lat>]
	 * : Flush only the cache entry for this latitude. Requires --lon.
	 *
	 * [--lon=<lon>]
	 * : Flush only the cache entry for this longitude. Requires --lat.
	 *
	 * ## EXAMPLES
	 *
	 *     # Clear every cached entry.
	 *     $ wp dtmg-weather flush
	 *     Success: 3 entries flushed.
	 *
	 *     # Clear a single entry.
	 *     $ wp dtmg-weather flush --lat=50.45 --lon=30.52
	 *     Success: Flushed.
	 *
	 * @when after_wp_load
	 *
	 * @param array<int,string>    $args       Positional args (unused).
	 * @param array<string,string> $assoc_args Associative args.
	 */
	public function flush( array $args, array $assoc_args ): void {
		$has_lat = isset( $assoc_args['lat'] );
		$has_lon = isset( $assoc_args['lon'] );

		if ( $has_lat xor $has_lon ) {
			\WP_CLI::error( 'Both --lat and --lon are required when flushing a single entry.' );
		}

		if ( $has_lat && $has_lon ) {
			$deleted = $this->service->flush( (float) $assoc_args['lat'], (float) $assoc_args['lon'] );
			\WP_CLI::success( $deleted ? 'Flushed.' : 'No matching cache entry.' );
			return;
		}

		$count = $this->service->flush_all();
		\WP_CLI::success( sprintf( '%d entries flushed.', $count ) );
	}
}
