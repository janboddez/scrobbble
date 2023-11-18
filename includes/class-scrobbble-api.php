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
		header( 'Cache-Control: no-cache' );
		header( 'Content-Type: text/plain; charset=UTF-8' );

		$username = $request->get_param( 'u' ) ?: ''; // phpcs:ignore Universal.Operators.DisallowShortTernary.Found

		if ( empty( $username ) ) {
			error_log( '[Scrobbble] Missing username.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			die( "FAILED\n" );
		}

		$user = get_user_by( 'login', $username );

		if ( empty( $user ) ) {
			error_log( '[Scrobbble] Invalid username.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			die( "FAILED\n" );
		}

		// phpcs:disable Universal.Operators.DisallowShortTernary.Found
		$protocol   = $request->get_param( 'p' ) ?: '';
		$timestamp  = $request->get_param( 't' ) ?: '';
		$auth_token = $request->get_param( 'a' ) ?: '';
		$client     = $request->get_param( 'c' ) ?: '';
		// phpcs:enable Universal.Operators.DisallowShortTernary.Found

		if ( ! static::check_standard_auth( $auth_token, $timestamp, $user ) ) { // Assuming "Auth Token" auth. We could use the permission callback for this.
			error_log( '[Scrobbble] Authentication failed.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
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
			die( "FAILED\n" );
		}

		error_log( '[Scrobbble] Handshake succeeded.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

		echo "OK\n";
		echo "$session_id\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo esc_url_raw( get_rest_url( null, 'scrobbble/v1/nowplaying' ) ) . "\n"; // Not really interested in implementing this, but maybe we have to?
		echo esc_url_raw( get_rest_url( null, 'scrobbble/v1/submissions' ) ) . "\n";
		exit;
	}

	/**
	 * "Now Playing" callback.
	 *
	 * @param  \WP_REST_Request $request Rest request.
	 * @return array                     Response.
	 */
	public static function now( $request ) {
		header( 'Cache-Control: no-cache' );
		header( 'Content-Type: text/plain; charset=UTF-8' );

		if ( 'POST' === $request->get_method() ) {
			// phpcs:disable Universal.Operators.DisallowShortTernary.Found
			$session_id = $request->get_param( 's' ) ?: '';
			$title      = $request->get_param( 't' ) ?: '';
			$artist     = $request->get_param( 'a' ) ?: '';
			$album      = $request->get_param( 'b' ) ?: '';
			$mbid       = $request->get_param( 'm' ) ?: '';
			$length     = intval( $request->get_param( 'l' ) ?: 300 );
			// phpcs:enable Universal.Operators.DisallowShortTernary.Found

			$session = static::get_session( $session_id );
			$user_id = ! empty( $session->user_id ) ? $session->user_id : 0;

			if ( 0 === $user_id ) {
				error_log( '[Scrobbble] Could not find user by session ID.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				die( "FAILED\n" );
			}

			error_log( '[Scrobbble] Got request: ' . wp_json_encode( $request->get_params() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

			if ( empty( $artist ) || empty( $title ) ) {
				error_log( '[Scrobbble] Incomplete scrobble.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				die( "FAILED\n" );
			}

			if ( ! is_string( $artist ) || ! is_string( $title ) ) {
				error_log( '[Scrobbble] Incorrectly formatted array data.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				die( "FAILED\n" );
			}

			// @todo: Move to add-on plugin, i.e., make the whole "now playing" array (or object) filterable.
			$now = array(
				'title'  => apply_filters( 'scrobbble_title', sanitize_text_field( $title ) ),
				'artist' => apply_filters( 'scrobbble_artist', sanitize_text_field( $artist ) ),
				'album'  => apply_filters( 'scrobbble_album', sanitize_text_field( $album ) ),
				'mbid'   => ! empty( $mbid ) ? static::sanitize_mbid( $mbid ) : '',
			);

			set_transient(
				'scrobbble_nowplaying',
				apply_filters( 'scrobbble_nowplaying', $now, $request ),
				$length < 5400 ? $length : 600
			);

			die( "OK\n" );
		}

		// GET request. This bit is always publicly accessible.
		$now_playing = get_transient( 'scrobbble_nowplaying' );

		if ( ! is_array( $now_playing ) ) {
			return array();
		}

		// Return previously stored song information.
		return array_filter( $now_playing );
	}

	/**
	 * Submission callback.
	 *
	 * @param WP_REST_Request $request WP Rest request.
	 */
	public static function scrobble( $request ) {
		header( 'Cache-Control: no-cache' );
		header( 'Content-Type: text/plain; charset=UTF-8' );

		// phpcs:disable Universal.Operators.DisallowShortTernary.Found
		$session_id = $request->get_param( 's' ) ?: '';
		$titles     = $request->get_param( 't' ) ?: array();
		$artists    = $request->get_param( 'a' ) ?: array();
		$albums     = $request->get_param( 'b' ) ?: array();
		$mbids      = $request->get_param( 'm' ) ?: array();
		$times      = $request->get_param( 'i' ) ?: array();
		// phpcs:enable Universal.Operators.DisallowShortTernary.Found

		$session = static::get_session( $session_id ); // Here, too, we could (should?) do this in the permission callback.
		$user_id = ! empty( $session->user_id ) ? $session->user_id : 0;

		if ( 0 === $user_id ) {
			error_log( '[Scrobbble] Could not find user by session ID.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			die( "FAILED\n" );
		}

		error_log( '[Scrobbble] Got request: ' . wp_json_encode( $request->get_params() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

		if ( empty( $artists ) || empty( $titles ) || empty( $times ) ) {
			error_log( '[Scrobbble] Incomplete scrobble.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			die( "FAILED\n" );
		}

		if ( ! is_array( $artists ) || ! is_array( $titles ) || ! is_array( $times ) || ! is_array( $albums ) || ! is_array( $mbids ) ) {
			error_log( '[Scrobbble] Incorrectly formatted array data.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			die( "FAILED\n" );
		}

		$count = count( $titles );

		for ( $i = 0; $i < $count; $i++ ) {
			// phpcs:disable Universal.Operators.DisallowShortTernary.Found
			$title  = apply_filters( 'scrobbble_title', sanitize_text_field( $titles[ $i ] ) ?: '' );
			$artist = apply_filters( 'scrobbble_artist', sanitize_text_field( $artists[ $i ] ) ?: '' );
			$album  = apply_filters( 'scrobbble_album', sanitize_text_field( $albums[ $i ] ) ?: '' );
			$mbid   = $mbids[ $i ] ? static::sanitize_mbid( $mbids[ $i ] ) : '';
			$time   = $times[ $i ] ?: time();
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

			// Avoid duplicates, so we don't have to rely on clients for this.
			if ( self::track_exists( $content, $time ) ) {
				continue;
			}

			$args = array(
				'post_author'   => $user_id,
				'post_type'     => 'iwcpt_listen',
				'post_title'    => wp_strip_all_tags( $content ),
				'post_content'  => $content,
				'post_status'   => 'publish',
				'post_date_gmt' => gmdate( 'Y-m-d H:i:s', $time ),
			);

			// Store the track's MusicBrainz ID.
			$args['meta_input'] = array_filter(
				array(
					'_scrobbble_mbid'   => $mbid,
					'_scrobbble_client' => ! empty( $session->client ) ? $session->client : '',
				)
			);

			$post_id = wp_insert_post( $args );

			// Using custom taxonomies for artist and album information.
			wp_set_object_terms( $post_id, array( $artist ), 'iwcpt_artist' );
			wp_set_object_terms( $post_id, array( "$artist - $album" ), 'iwcpt_album' );

			// For add-on plugins (cover art, etc.) to be able to do their
			// thing.
			// To do: deprecate in favor of core hooks, e.g.,
			// `save_post_iwcpt_listen`?
			do_action( 'scrobbble_save_track', $post_id, $data );
		}

		die( "OK\n" );
	}

	/**
	 * Auth token validation.
	 *
	 * @param  string   $token     Auth token.
	 * @param  string   $timestamp Timestamp.
	 * @param  \WP_User $user      User.
	 * @return bool
	 */
	protected static function check_standard_auth( $token, $timestamp, $user = null ) {
		// Allow per-user passwords. We might eventually choose to store these
		// as user meta.
		if ( null !== $user && defined( 'SCROBBBLE_PASS_' . strtoupper( $user->user_login ) ) ) {
			return md5( md5( constant( 'SCROBBBLE_PASS_' . strtoupper( $user->user_login ) ) ) . $timestamp ) === $token;
		}

		if ( ! defined( 'SCROBBBLE_PASS' ) ) {
			return false;
		}

		return md5( md5( SCROBBBLE_PASS ) . $timestamp ) === $token;
	}

	/**
	 * Whether a listen already exists.
	 *
	 * @param  string $content "Listen" content.
	 * @param  int    $time    Timestamp.
	 * @return bool
	 */
	protected static function track_exists( $content, $time ) {
		$tracks = get_posts(
			array(
				'post_type'    => 'iwcpt_listen',
				'post_content' => $content,
				'date_query'   => array(
					array(
						'column'    => 'post_date_gmt',
						'before'    => gmdate( 'Y-m-d H:i:s', $time ),
						'after'     => gmdate( 'Y-m-d H:i:s', $time ),
						'inclusive' => true, // Include exact matches for `before` and `after`.
					),
				),
				'fields'       => 'ids',
			)
		);

		if ( empty( $tracks ) ) {
			return false;
		}

		error_log( '[Scrobbble] Looks like this listen already exists.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		return true;
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
	 * @return object|null        Session data, or `null` on failure.
	 */
	protected static function get_session( $session_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'scrobbble_sessions';

		// Delete expired sessions.
		$wpdb->query( $wpdb->prepare( "DELETE FROM $table_name WHERE expires < %d", time() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$session = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE session_id = %s", $session_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( empty( $session ) ) {
			error_log( '[Scrobbble] User has no active sessions.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return null;
		}

		return $session;
	}
}
