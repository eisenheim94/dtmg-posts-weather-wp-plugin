<?php
/**
 * REST controller for the weather endpoint.
 *
 * @package DTMG\PostsWeatherBlock\REST
 */

declare( strict_types=1 );

namespace DTMG\PostsWeatherBlock\REST;

use DTMG\PostsWeatherBlock\Weather\WeatherService;

defined( 'ABSPATH' ) || exit;

/**
 * Registers GET /wp-json/dtmg/v1/weather.
 *
 * Public endpoint (read-only). The OpenWeatherMap API key never leaves PHP;
 * only normalized weather data is returned to clients.
 */
final class WeatherController {

	public const REST_NAMESPACE = 'dtmg/v1';
	public const ROUTE          = '/weather';

	/**
	 * Inject the weather service.
	 *
	 * @param WeatherService $service Weather service the controller delegates to.
	 */
	public function __construct( private readonly WeatherService $service ) {}

	/**
	 * Register the rest_api_init hook.
	 */
	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register the controller's REST routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			self::ROUTE,
			[
				'methods'             => \WP_REST_Server::READABLE,
				'permission_callback' => '__return_true',
				'callback'            => [ $this, 'handle' ],
				'args'                => [
					'lat' => [
						'type'              => 'number',
						'required'          => true,
						'validate_callback' => static fn( $v ) => is_numeric( $v ) && (float) $v >= -90.0 && (float) $v <= 90.0,
						'sanitize_callback' => static fn( $v ) => (float) $v,
					],
					'lon' => [
						'type'              => 'number',
						'required'          => true,
						'validate_callback' => static fn( $v ) => is_numeric( $v ) && (float) $v >= -180.0 && (float) $v <= 180.0,
						'sanitize_callback' => static fn( $v ) => (float) $v,
					],
				],
			]
		);
	}

	/**
	 * Handle GET /wp-json/dtmg/v1/weather.
	 *
	 * @param \WP_REST_Request $request Validated request with `lat`/`lon` params.
	 * @return \WP_REST_Response 200 with normalized weather, or an error response.
	 */
	public function handle( \WP_REST_Request $request ): \WP_REST_Response {
		$result = $this->service->get( (float) $request['lat'], (float) $request['lon'] );

		if ( is_wp_error( $result ) ) {
			$status = (int) ( $result->get_error_data()['status'] ?? 502 );
			return new \WP_REST_Response(
				[
					'code'    => $result->get_error_code(),
					'message' => $result->get_error_message(),
				],
				$status
			);
		}

		return new \WP_REST_Response( $result->to_array(), 200 );
	}
}
