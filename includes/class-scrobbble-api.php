<?php
/**
 * The scrobbling endpoint.
 *
 * @package Scrobbble
 */

namespace Scrobbble;

/**
 * Implements Last.fm's v1.2 scrobbing API.
 */
class Scrobbble_API {
	/**
	 * Registers API routes.
	 */
	public static function register_api_routes() {
		register_rest_route(
			'scrobbble/v1',
			'/scrobbble',
			array(
				'methods'             => array( 'GET' ),
				'callback'            => 'Scrobbble\\Scrobbble_API::handshake',
				'permission_callback' => '__return_true', // Auth logic is dealt with in the main callback.
			)
		);

		register_rest_route(
			'scrobbble/v1',
			'/nowplaying',
			array(
				'methods'             => array( 'GET', 'POST' ),
				'callback'            => 'Scrobbble\\Scrobbble_API::now',
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'scrobbble/v1',
			'/submissions', // Actual scrobbling.
			array(
				'methods'             => array( 'POST' ),
				'callback'            => 'Scrobbble\\Scrobbble_API::scrobble',
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Handshake callback.
	 *
	 * Creates a new session, then echoes the session ID and scrobble URLs.
	 *
	 * @param \WP_REST_Request $request Rest request.
	 */
	public static function handshake( $request ) {
		$username = $request->get_param( 'u' ) ?: ''; // phpcs:ignore WordPress.PHP.DisallowShortTernary.Found

		if ( empty( $username ) ) {
			error_log( '[Scrobbble] Missing username.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			header( 'Content-Type: text/plain; charset=UTF-8' );
			die( "FAILED\n" );
		}

		$user = get_user_by( 'login', $username );

		if ( empty( $user ) ) {
			error_log( '[Scrobbble] Invalid username.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			header( 'Content-Type: text/plain; charset=UTF-8' );
			die( "FAILED\n" );
		}

		// phpcs:disable WordPress.PHP.DisallowShortTernary.Found
		$protocol   = $request->get_param( 'p' ) ?: '';
		$timestamp  = $request->get_param( 't' ) ?: '';
		$auth_token = $request->get_param( 'a' ) ?: '';
		$client     = $request->get_param( 'c' ) ?: '';
		// phpcs:enable WordPress.PHP.DisallowShortTernary.Found

		if ( ! static::check_standard_auth( $auth_token, $timestamp ) ) { // Assuming "Auth Token" auth. We could use the permission callback for this.
			error_log( '[Scrobbble] Authentication failed.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			header( 'Content-Type: text/plain; charset=UTF-8' );
			die( "FAILED\n" );
		}

		$session_id = md5( $auth_token . time() );

		global $wpdb;

		$num_rows = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->prefix . 'scrobbble_sessions',
			array(
				'user_id'    => $user->ID,
				'session_id' => $session_id,
				'client'     => $client,
				'expires'    => time() + MONTH_IN_SECONDS,
			)
		);

		if ( empty( $num_rows ) ) {
			error_log( '[Scrobbble] Handshake failed.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			header( 'Content-Type: text/plain; charset=UTF-8' );
			die( "FAILED\n" );
		}

		error_log( '[Scrobbble] Handshake succeeded.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

		header( 'Content-Type: text/plain; charset=UTF-8' );
		echo "OK\n";
		echo "$session_id\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo esc_url_raw( get_rest_url( null, 'scrobbble/v1/nowplaying' ) ) . "\n"; // Not really interested in implementing this, but maybe we have to?
		echo esc_url_raw( get_rest_url( null, 'scrobbble/v1/submissions' ) ) . "\n";
		exit;
	}

	/**
	 * "Now Playing" callback. Not actually implemented.
	 *
	 * @param  \WP_REST_Request $request Rest request.
	 * @return array                     Response.
	 */
	public static function now( $request ) {
		if ( 'POST' === $request->get_method() ) {
			// phpcs:disable WordPress.PHP.DisallowShortTernary.Found
			$session_id = $request->get_param( 's' ) ?: '';
			$title      = $request->get_param( 't' ) ?: '';
			$artist     = $request->get_param( 'a' ) ?: '';
			$album      = $request->get_param( 'b' ) ?: '';
			$mbid       = $request->get_param( 'm' ) ?: '';
			$length     = intval( $request->get_param( 'l' ) ?: 300 );
			// phpcs:enable WordPress.PHP.DisallowShortTernary.Found

			$user_id = static::user_id_from_session_id( $session_id ); // Here, too, we could (should?) do this in the permission callback.

			if ( empty( $user_id ) ) {
				error_log( '[Scrobbble] Could not find user by session ID.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				header( 'Content-Type: text/plain; charset=UTF-8' );
				die( "FAILED\n" );
			}

			error_log( '[Scrobbble] Got request: ' . wp_json_encode( $request->get_params() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

			if ( empty( $artist ) || empty( $title ) ) {
				error_log( '[Scrobbble] Incomplete scrobble.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				header( 'Content-Type: text/plain; charset=UTF-8' );
				die( "FAILED\n" );
			}

			if ( ! is_string( $artist ) || ! is_string( $title ) ) {
				error_log( '[Scrobbble] Incorrectly formatted array data.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				header( 'Content-Type: text/plain; charset=UTF-8' );
				die( "FAILED\n" );
			}

			set_transient(
				'scrobbble_nowplaying',
				array(
					'title'  => apply_filters( 'scrobbble_title', sanitize_text_field( $title ) ),
					'artist' => apply_filters( 'scrobbble_artist', sanitize_text_field( $artist ) ),
					'album'  => apply_filters( 'scrobbble_album', sanitize_text_field( $album ) ),
					'mbid'   => ! empty( $mbid ) ? static::sanitize_mbid( $mbid ) : '',
				),
				$length < 5400 ? $length : 600
			);

			header( 'Content-Type: text/plain; charset=UTF-8' );
			die( "OK\n" );
		} else {
			// GET requests. This bit is always publicly accessible.
			$now_playing = get_transient( 'scrobbble_nowplaying' );

			if ( ! is_array( $now_playing ) ) {
				return array();
			}

			// Return previously stored song information.
			return array_filter( $now_playing );
		}
	}

	/**
	 * Submission callback.
	 *
	 * @param WP_REST_Request $request WP Rest request.
	 */
	public static function scrobble( $request ) {
		// phpcs:disable WordPress.PHP.DisallowShortTernary.Found
		$session_id = $request->get_param( 's' ) ?: '';
		$titles     = $request->get_param( 't' ) ?: array();
		$artists    = $request->get_param( 'a' ) ?: array();
		$albums     = $request->get_param( 'b' ) ?: array();
		$mbids      = $request->get_param( 'm' ) ?: array();
		$times      = $request->get_param( 'i' ) ?: array();
		// phpcs:enable WordPress.PHP.DisallowShortTernary.Found

		$user_id = static::user_id_from_session_id( $session_id ); // Here, too, we could (should?) do this in the permission callback.

		if ( empty( $user_id ) ) {
			error_log( '[Scrobbble] Could not find user by session ID.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			header( 'Content-Type: text/plain; charset=UTF-8' );
			die( "FAILED\n" );
		}

		error_log( '[Scrobbble] Got request: ' . wp_json_encode( $request->get_params() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

		if ( empty( $artists ) || empty( $titles ) || empty( $times ) ) {
			error_log( '[Scrobbble] Incomplete scrobble.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			header( 'Content-Type: text/plain; charset=UTF-8' );
			die( "FAILED\n" );
		}

		if ( ! is_array( $artists ) || ! is_array( $titles ) || ! is_array( $times ) || ! is_array( $albums ) || ! is_array( $mbids ) ) {
			error_log( '[Scrobbble] Incorrectly formatted array data.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			header( 'Content-Type: text/plain; charset=UTF-8' );
			die( "FAILED\n" );
		}

		$count = count( $titles );

		for ( $i = 0; $i < $count; $i++ ) {
			// phpcs:disable WordPress.PHP.DisallowShortTernary.Found
			$title  = apply_filters( 'scrobbble_title', sanitize_text_field( $titles[ $i ] ) ?: '' );
			$artist = apply_filters( 'scrobbble_artist', sanitize_text_field( $artists[ $i ] ) ?: '' );
			$album  = apply_filters( 'scrobbble_album', sanitize_text_field( $albums[ $i ] ) ?: '' );
			$mbid   = $mbids[ $i ] ? static::sanitize_mbid( $mbids[ $i ] ) : '';
			$time   = $times[ $i ] ?: time();
			// phpcs:enable WordPress.PHP.DisallowShortTernary.Found

			if ( empty( $title ) || empty( $artist ) ) {
				// Skip.
				continue;
			}

			$data = array_filter( // Removes empty items.
				array(
					'title'  => $title,
					'artist' => $artist,
					'album'  => $album,
					'mbid'   => $mbid,
					'time'   => $time,
				)
			);

			if ( apply_filters( 'scrobbble_skip_track', false, $data ) ) {
				// Skip this track.
				continue;
			}

			$content = "Listening to <span class=\"p-listen-of h-cite\"><cite class=\"p-name\">$title</cite> by <span class=\"p-author h-card\"><span class=\"p-name\">$artist</span></span></span>";

			if ( ! empty( $album ) ) {
				$content .= "<span class=\"screen-reader-text\"> ($album)</span>";
			}

			$content .= '.';
			$content  = apply_filters( 'scrobbble_content', $content, $data );

			$args = array(
				'post_author'   => $user_id,
				'post_type'     => 'iwcpt_listen',
				'post_title'    => wp_strip_all_tags( $content ),
				'post_content'  => $content,
				'post_status'   => 'publish',
				'post_date_gmt' => gmdate( 'Y-m-d H:i:s', $time ),
			);

			if ( ! empty( $mbid ) ) {
				// Store the track's MusicBrainz ID.
				$args['meta_input'] = array( 'mbid' => $mbid );
				// To do: add client information?
			}

			$post_id = wp_insert_post( $args );

			// For add-on plugins (cover art, etc.) to be able to do their
			// thing.
			// To do: deprecate in favor of core hooks, e.g.,
			// `save_post_iwcpt_listen`?
			do_action( 'scrobbble_save_track', $post_id, $data );
		}

		header( 'Content-Type: text/plain; charset=UTF-8' );
		die( "OK\n" );
	}

	/**
	 * Auth token validation.
	 *
	 * @param  string $token     Auth token.
	 * @param  string $timestamp Timestamp.
	 * @return bool
	 */
	protected static function check_standard_auth( $token, $timestamp ) {
		if ( ! defined( 'SCROBBBLE_PASS' ) ) {
			return false;
		}

		return md5( md5( SCROBBBLE_PASS ) . $timestamp ) === $token;
	}

	/**
	 * Sanitizes MusicBrainz IDs.
	 *
	 * @param  string $mbid MusicBrainz track ID.
	 * @return string|null
	 */
	protected static function sanitize_mbid( $mbid ) {
		$mbid = strtolower( trim( $mbid ) );

		if ( preg_match( '/^[\da-f]{8}-[\da-f]{4}-[\da-f]{4}-[\da-f]{4}-[\da-f]{12}$/D', $mbid ) ) {
			return $mbid;
		}

		return null;
	}

	/**
	 * Returns the WP user ID for a given session.
	 *
	 * @param  string $session_id API session ID.
	 * @return int
	 */
	protected static function user_id_from_session_id( $session_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'scrobbble_sessions';

		// Delete expired sessions.
		$wpdb->query( $wpdb->prepare( "DELETE FROM $table_name WHERE expires < %d", time() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$user_id = $wpdb->get_var( $wpdb->prepare( "SELECT user_id FROM $table_name WHERE session_id = %s", $session_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( empty( $user_id ) ) {
			error_log( '[Scrobbble] User has no active sessions.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return 0;
		}

		return (int) $user_id;
	}
}
