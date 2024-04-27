<?php
/**
 * The scrobbling endpoint.
 *
 * @package Scrobbble
 */

namespace Scrobbble;

/**
 * Partially implements Last.fm's v2.0 "scrobbling" API.
 *
 * @link https://www.last.fm/api/scrobbling
 */
class Scrobbble_API_2 extends Scrobbble_API {
	/**
	 * Resolves method parameters to handler functions.
	 *
	 * @var array METHOD_MAP Method names.
	 */
	const METHOD_MAP = array(
		'auth.getmobilesession'  => 'auth_getmobilesession',
		'user.getinfo'           => 'user_getinfo',
		'track.scrobble'         => 'track_scrobble',
		'track.updatenowplaying' => 'track_now',
	);

	/**
	 * Registers API routes.
	 */
	public static function register_routes() {
		// phpcs:disable Squiz.PHP.CommentedOutCode.Found,Universal.WhiteSpace.DisallowInlineTabs.NonIndentTabsUsed,Squiz.Commenting.InlineComment.SpacingBefore,Squiz.Commenting.InlineComment.InvalidEndChar
		// register_rest_route(
		// 	'scrobbble/v1',
		// 	'/scrobbble/api/auth',
		// 	array(
		// 		'methods'             => array( 'GET' ),
		// 		'callback'            => array( __CLASS__, 'auth' ),
		// 		'permission_callback' => '__return_true',
		// 	)
		// );
		// phpcs:enable Squiz.PHP.CommentedOutCode.Found,Universal.WhiteSpace.DisallowInlineTabs.NonIndentTabsUsed,Squiz.Commenting.InlineComment.SpacingBefore,Squiz.Commenting.InlineComment.InvalidEndChar

		register_rest_route(
			'scrobbble/v1',
			'/scrobbble/2.0',
			array(
				'methods'             => array( 'GET', 'POST' ),
				'callback'            => array( __CLASS__, 'get' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Processes 2.0 API requests.
	 *
	 * Currently (somewhat) supports (only) the following API methods:
	 * - 'auth.getMobileSession'
	 * - 'user.getInfo'
	 * - 'track.scrobble'
	 * - 'track.updateNowPlaying'
	 *
	 * @param \WP_REST_Request $request REST request.
	 */
	public static function get( $request ) {
		error_log( '[Scrobbble/API 2.0] Got request: ' . wp_json_encode( $request->get_params() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

		$method = strtolower( $request->get_param( 'method' ) ?: '' ); // phpcs:ignore Universal.Operators.DisallowShortTernary.Found
		$method = str_replace( '.', '_', $method );

		if ( empty( $method ) || ! method_exists( __CLASS__, $method ) ) {
			error_log( '[Scrobbble/API 2.0] Invalid method.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return new \WP_REST_Response(
				array(
					'error'   => 3,
					'message' => 'Invalid Method - No method with that name in this package',
				)
			);
		}

		return static::$method( $request );
	}

	/**
	 * Sets up a new session.
	 *
	 * On Android, set up PanoScrobbler for use with "GNU FM." Use your
	 * WordPress login and a unique random "password" stored in `wp-config.php`.
	 *
	 * @param \WP_REST_Request $request REST request.
	 */
	public static function auth_getmobilesession( $request ) {
		$username = $request->get_param( 'username' ) ?: ''; // phpcs:ignore Universal.Operators.DisallowShortTernary.Found
		$password = $request->get_param( 'password' ) ?: ''; // phpcs:ignore Universal.Operators.DisallowShortTernary.Found

		if ( empty( $username ) || empty( $password ) ) {
			error_log( '[Scrobbble/API 2.0] Invalid login parameters.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return new \WP_REST_Response(
				array(
					'error'   => 3,
					'message' => 'Invalid authentication token supplied',
				)
			);
		}

		$user = get_user_by( 'login', $username );
		if ( empty( $user->ID ) ) {
			error_log( '[Scrobbble/API 2.0] Invalid username.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return new \WP_REST_Response(
				array(
					'error'   => 3,
					'message' => 'Invalid authentication token supplied',
				)
			);
		}

		// @todo: Allow site-wide "password."
		if ( ! defined( 'SCROBBBLE_PASS_' . strtoupper( $user->user_login ) ) || constant( 'SCROBBBLE_PASS_' . strtoupper( $user->user_login ) ) !== $password ) {
			error_log( '[Scrobbble/API 2.0] Invalid password.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return new \WP_REST_Response(
				array(
					'error'   => 3,
					'message' => 'Invalid authentication token supplied',
				)
			);
		}

		$session_id = md5( time() . wp_rand() );

		global $wpdb;

		$num_rows = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->prefix . 'scrobbble_sessions',
			array(
				'user_id'    => $user->ID,
				'session_id' => $session_id,
				'client'     => sanitize_text_field( $request->get_param( 'api_key' ) ?: '' ), // phpcs:ignore Universal.Operators.DisallowShortTernary.Found
				'expires'    => time() + MONTH_IN_SECONDS,
			)
		);

		return array(
			'session' => array(
				'name'       => $username,
				'key'        => $session_id,
				'subscriber' => 0,
			),
		);
	}

	/**
	 * Processes a scrobble.
	 *
	 * @param  \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|array   REST response.
	 */
	public static function track_updatenowplaying( $request ) {
		// phpcs:disable Universal.Operators.DisallowShortTernary.Found
		$session_id = $request->get_param( 'sk' ) ?: '';
		$title      = $request->get_param( 'track' ) ?: '';
		$artist     = $request->get_param( 'artist' ) ?: '';
		$album      = $request->get_param( 'album' ) ?: '';
		$length     = intval( $request->get_param( 'duration' ) ?: 300 ); // How long we want to store this track for. Defaults to 5 minutes.
		// phpcs:enable Universal.Operators.DisallowShortTernary.Found

		$session = static::get_session( $session_id );
		$user_id = ! empty( $session->user_id )
			? $session->user_id
			: 0;

		if ( 0 === $user_id ) {
			error_log( '[Scrobbble/API 2.0] Could not find user by session ID.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return new \WP_REST_Response(
				array(
					'error'   => 9,
					'message' => 'Invalid session key - Please re-authenticate',
				)
			);
		}

		if ( empty( $artist ) || empty( $title ) || ! is_string( $artist ) || ! is_string( $title ) ) {
			error_log( '[Scrobbble/API 2.0] Invalid format.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return new \WP_REST_Response(
				array(
					'error'   => 6,
					'message' => 'Invalid parameters - Your request is missing a required parameter',
				)
			);
		}

		$now = array(
			'title'  => apply_filters( 'scrobbble_title', sanitize_text_field( $title ) ),
			'artist' => apply_filters( 'scrobbble_artist', sanitize_text_field( $artist ) ),
			'album'  => apply_filters( 'scrobbble_album', sanitize_text_field( $album ) ),
			'mbid'   => ! empty( $mbid ) ? static::sanitize_mbid( $mbid ) : '',
		);

		if ( ! apply_filters( 'scrobbble_skip_track', false, $now ) ) {
			set_transient(
				'scrobbble_nowplaying',
				apply_filters( 'scrobbble_nowplaying', $now, $request ),
				$length < 5400 ? $length : 600
			);
		}

		$track = array_filter(
			array(
				'track'          => array(
					'#text'     => $title,
					'corrected' => $title,
				),
				'artist'         => array(
					'#text'     => $artist,
					'corrected' => $artist,
				),
				'album'          => array(
					'#text'     => $album,
					'corrected' => $album,
				),
				'ignoredMessage' => array(
					'#text' => '',
					'code'  => 0,
				),
			)
		);

		$response = array(
			'nowplaying' => $track,
		);

		return $response;
	}

	/**
	 * Processes a scrobble.
	 *
	 * @param  \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|array   REST response.
	 */
	public static function track_scrobble( $request ) {
		// phpcs:disable Universal.Operators.DisallowShortTernary.Found
		$session_id = $request->get_param( 'sk' ) ?: '';
		$titles     = $request->get_param( 'track' ) ?: array();
		$artists    = $request->get_param( 'artist' ) ?: array();
		$albums     = $request->get_param( 'album' ) ?: array();
		$times      = $request->get_param( 'timestamp' ) ?: array();
		// phpcs:enable Universal.Operators.DisallowShortTernary.Found

		$session = Scrobbble_API::get_session( $session_id ); // Here, too, we could (should?) do this in the permission callback.
		$user_id = ! empty( $session->user_id ) ? $session->user_id : 0;

		if ( 0 === $user_id ) {
			error_log( '[Scrobbble/API 2.0] Could not find user by session ID.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return new \WP_REST_Response(
				array(
					'error'   => 9,
					'message' => 'Invalid session key - Please re-authenticate',
				)
			);
		}

		if ( empty( $artists ) || empty( $titles ) || empty( $times ) ) {
			error_log( '[Scrobbble/API 2.0] Incomplete scrobble.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return new \WP_REST_Response(
				array(
					'error'   => 6,
					'message' => 'Invalid parameters - Your request is missing a required parameter',
				)
			);
		}

		$titles  = (array) $titles;
		$artists = (array) $artists;
		$albums  = (array) $albums;
		$times   = (array) $times;

		$count    = count( $titles );
		$accepted = 0;
		$ignored  = 0;

		$tracks = array();

		for ( $i = 0; $i < $count; $i++ ) {
			// phpcs:disable Universal.Operators.DisallowShortTernary.Found
			$title  = apply_filters( 'scrobbble_title', sanitize_text_field( $titles[ $i ] ) ?: '' );
			$artist = apply_filters( 'scrobbble_artist', sanitize_text_field( $artists[ $i ] ) ?: '' );
			$album  = apply_filters( 'scrobbble_album', sanitize_text_field( $albums[ $i ] ) ?: '' );
			$time   = $times[ $i ] ?: time();
			// phpcs:ignore Squiz.PHP.CommentedOutCode.Found
			// $mbid   = $mbids[ $i ] ? static::sanitize_mbid( $mbids[ $i ] ) : '';
			// phpcs:enable Universal.Operators.DisallowShortTernary.Found

			if ( empty( $title ) || empty( $artist ) ) {
				// Skip.
				continue;
			}

			$data = array_filter( // Removes empty items.
				array(
					'title'  => $title,
					'artist' => $artist,
					'album'  => $album,
					'time'   => $time,
					// phpcs:ignore Squiz.PHP.CommentedOutCode.Found
					// 'mbid'   => $mbid,
				)
			);

			// Of course, the 2.0 API expects a much more elaborate response.
			$track = array(
				'track'          => array(
					'#text'     => $title,
					'corrected' => $title,
				),
				'artist'         => array(
					'#text'     => $artist,
					'corrected' => $artist,
				),
				'album'          => array(
					'#text'     => $album,
					'corrected' => $album,
				),
				'timestamp'      => $time,
				'ignoredMessage' => array(
					'#text' => '',
					'code'  => 0,
				),
			);

			if ( apply_filters( 'scrobbble_skip_track', false, $data ) ) {
				// Skip this track.
				++$ignored;

				$track['ignoredMessage'] = array(
					'#text' => 'Track was ignored',
					'code'  => 2,
				);
			} else {
				// Save scrobble.
				$result = static::create_listen( $data, $session );

				// Avoid duplicates, so we don't have to rely on clients for this.
				if ( is_int( $result ) ) {
					++$accepted;

				} elseif ( 'duplicate' === $result ) {
					// Duplicate.
					++$ignored;

					$track['ignoredMessage'] = array(
						'#text' => 'Already scrobbled',
						'code'  => 91,
					);
				} else {
					// Something else went wrong.
					++$ignored;

					$track['ignoredMessage'] = array(
						'#text' => 'Service temporary unavailable', // I mean, what do we do?
						'code'  => 16,
					);
				}
			}

			$tracks[] = $track;
		}

		$response = array(
			'scrobbles' => array(
				'scrobble' => count( $tracks ) === 1 ? $tracks[0] : $tracks,
				'@attr'    => array(
					'accepted' => $accepted,
					'ignored'  => $ignored,
				),
			),
		);

		return $response;
	}

	/**
	 * Returns user information.
	 *
	 * @param \WP_REST_Request $request REST request.
	 */
	public static function user_getinfo( $request ) {
		error_log( '[Scrobbble/API 2.0] Got request: ' . wp_json_encode( $request->get_params() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

		$session_id = $request->get_param( 'sk' );
		$username   = $request->get_param( 'user' );

		$user = get_user_by( 'login', $username );
		if ( empty( $user->ID ) ) {
			return new \WP_REST_Response(
				array(
					'error'   => 7,
					'message' => 'Invalid resource specified',
				)
			);
		}

		$session = Scrobbble_API::get_session( $session_id );
		if ( empty( $session->user_id ) || intval( $session->user_id ) !== $user->ID ) {
			return new \WP_REST_Response(
				array(
					'error'   => 9,
					'message' => 'Invalid session key - Please re-authenticate',
				)
			);
		}

		return array(
			'user' => array(
				'name'            => esc_html( $user->user_login ),
				'realname'        => esc_html( trim( get_user_meta( $user->ID, 'first_name', true ) . ' ' . get_user_meta( $user->ID, 'last_name', true ) ) ),
				'image'           => array(
					array(
						'#text' => esc_url_raw( get_avatar_url( $user, array( 'size' => 32 ) ) ),
						'size'  => 'small',
					),
					array(
						'#text' => esc_url_raw( get_avatar_url( $user, array( 'size' => 64 ) ) ),
						'size'  => 'medium',
					),
				),
				'profile_created' => gmdate( '%c', $user->user_registered ),
			),
		);
	}
}
