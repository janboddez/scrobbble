<?php
/**
 * The scrobbling endpoint.
 *
 * @package Scrobbble
 */

namespace Scrobbble;

// Error constants.
define( 'LFM_INVALID_SERVICE', 2 );
define( 'LFM_INVALID_METHOD', 3 );
define( 'LFM_INVALID_TOKEN', 4 );
define( 'LFM_INVALID_FORMAT', 5 );
define( 'LFM_INVALID_PARAMS', 6 );
define( 'LFM_INVALID_RESOURCE', 7 );
define( 'LFM_TOKEN_ERROR', 8 );
define( 'LFM_INVALID_SESSION', 9 );
define( 'LFM_INVALID_APIKEY', 10 );
define( 'LFM_SERVICE_OFFLINE', 11 );
define( 'LFM_SUBSCRIPTION_ERROR', 12 );
define( 'LFM_INVALID_SIGNATURE', 13 );
define( 'LFM_TOKEN_UNAUTHORISED', 14 );
define( 'LFM_SUBSCRIPTION_REQD', 18 );
define( 'LFM_NOT_ENOUGH_CONTENT', 20 );
define( 'LFM_NOT_ENOUGH_MEMBERS', 21 );
define( 'LFM_NOT_ENOUGH_FANS', 22 );
define( 'LFM_NOT_ENOUGH_NEIGHBORS', 23 );

/**
 * Implements Last.fm's 2.0 API.
 */
class Scrobbble_API_2 {
	/**
	 * Error descriptions as per API documentation.
	 *
	 * @var array $error_messages Error descriptions.
	 */
	public $error_messages = array(
		LFM_INVALID_SERVICE      => 'Invalid service - This service does not exist',
		LFM_INVALID_METHOD       => 'Invalid Method - No method with that name in this package',
		LFM_INVALID_TOKEN        => 'Invalid authentication token supplied',
		LFM_INVALID_FORMAT       => "Invalid format - This service doesn't exist in that format",
		LFM_INVALID_PARAMS       => 'Invalid parameters - Your request is missing a required parameter',
		LFM_INVALID_RESOURCE     => 'Invalid resource specified',
		LFM_TOKEN_ERROR          => 'There was an error granting the request token. Please try again later',
		LFM_INVALID_SESSION      => 'Invalid session key - Please re-authenticate',
		LFM_INVALID_APIKEY       => 'Invalid API key - You must be granted a valid key by last.fm',
		LFM_SERVICE_OFFLINE      => 'Service Offline - This service is temporarily offline. Try again later.',
		LFM_SUBSCRIPTION_ERROR   => 'Subscription Error - The user needs to be subscribed in order to do that',
		LFM_INVALID_SIGNATURE    => 'Invalid method signature supplied',
		LFM_TOKEN_UNAUTHORISED   => 'This token has not yet been authorised',
		LFM_SUBSCRIPTION_REQD    => 'This user has no free radio plays left. Subscription required.',
		LFM_NOT_ENOUGH_CONTENT   => 'There is not enough content to play this station',
		LFM_NOT_ENOUGH_MEMBERS   => 'This group does not have enough members for radio',
		LFM_NOT_ENOUGH_FANS      => 'This artist does not have enough fans for radio',
		LFM_NOT_ENOUGH_NEIGHBORS => 'There are not enough neighbors for radio',
	);

	/**
	 * Resolves method parameters to handler functions.
	 *
	 * @var array $method_map Method names.
	 */
	public $method_map = array(
		'auth.gettoken'          => 'method_auth_get_token',
		'auth.getsession'        => 'method_auth_get_session',
		'auth.getmobilesession'  => 'method_auth_getMobileSession',
		'artist.addtags'         => 'method_artist_addTags',
		'artist.getinfo'         => 'method_artist_getInfo',
		'artist.gettoptracks'    => 'method_artist_getTopTracks',
		'artist.gettoptags'      => 'method_artist_getTopTags',
		'artist.gettopfans'      => 'method_artist_getTopFans',
		'artist.gettags'         => 'method_artist_getTags',
		'artist.getflattr'       => 'method_artist_getFlattr',
		'artist.search'          => 'method_artist_search',
		'album.addtags'          => 'method_album_addTags',
		'album.gettoptags'       => 'method_album_getTopTags',
		'album.gettags'          => 'method_album_get_tags',
		'library.removescrobble' => 'method_library_removeScrobble',
		'user.getinfo'           => 'method_user_get_info',
		'user.gettopartists'     => 'method_user_getTopArtists',
		'user.gettoptracks'      => 'method_user_getTopTracks',
		'user.getrecenttracks'   => 'method_user_getRecentTracks',
		'user.gettoptags'        => 'method_user_getTopTags',
		'user.getpersonaltags'   => 'method_user_getPersonalTags',
		'user.gettaginfo'        => 'method_user_getTagInfo',
		'user.getlovedtracks'    => 'method_user_getLovedTracks',
		'user.getbannedtracks'   => 'method_user_getBannedTracks',
		'user.getneighbours'     => 'method_user_getNeighbours',
		'radio.tune'             => 'method_radio_tune',
		'radio.getplaylist'      => 'method_radio_getPlaylist',
		'tag.gettoptags'         => 'method_tag_getTopTags',
		'tag.gettopartists'      => 'method_tag_getTopArtists',
		'tag.gettopalbums'       => 'method_tag_getTopAlbums',
		'tag.gettoptracks'       => 'method_tag_getTopTracks',
		'tag.getinfo'            => 'method_tag_getInfo',
		'track.addtags'          => 'method_track_addTags',
		'track.removetag'        => 'method_track_removeTag',
		'track.getinfo'          => 'method_track_getInfo',
		'track.gettoptags'       => 'method_track_getTopTags',
		'track.gettopfans'       => 'method_track_getTopFans',
		'track.gettags'          => 'method_track_getTags',
		'track.ban'              => 'method_track_ban',
		'track.love'             => 'method_track_love',
		'track.unlove'           => 'method_track_unlove',
		'track.unban'            => 'method_track_unban',
		'track.updatenowplaying' => 'method_track_updateNowPlaying',
		'track.scrobble'         => 'method_track_scrobble',
		'chart.gettopartists'    => 'method_chart_getTopArtists',
	);

	/**
	 * Hook callbacks.
	 */
	public static function register() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_action( 'login_form_scrobbble', array( __CLASS__, 'auth_form' ) );
	}

	/**
	 * Registers API routes.
	 */
	public static function register_routes() {
		register_rest_route(
			'scrobbble/v1',
			'/scrobbble/api/auth',
			array(
				'methods'             => array( 'GET' ),
				'callback'            => array( __CLASS__, 'auth' ),
				'permission_callback' => '__return_true',
			)
		);
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
	 * Validates API key and callback URL, then forwards to auth form.
	 *
	 * @param \WP_REST_Request $request REST request.
	 */
	public static function auth( $request ) {
		// @todo: Add validation/sanitization.
		$api_key = $request->get_param( 'api_key' );
		$cb      = $request->get_param( 'cb' );

		$url = add_query_params_to_url(
			array_filter(
				array(
					'action'  => 'scrobbble',
					'api_key' => $api_key,
					'cb'      => $cb,
				)
			),
			wp_login_url( $cb )
		);

		wp_safe_redirect( esc_url_raw( $url ) ); // To auth form.
		exit;
	}

	/**
	 * Shows (or processes) the auth form.
	 */
	public static function auth_form() {
		if ( ! is_user_logged_in() ) {
			auth_redirect(); // Have the user log in first.
		}

		if ( 'GET' === $_SERVER['REQUEST_METHOD'] ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
			static::authorize();
		} elseif ( 'POST' === $_SERVER['REQUEST_METHOD'] ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
			static::confirmed();
		}
		exit;
	}

	/**
	 * Show auth form.
	 */
	public static function authorize() {
		// @todo: Add nonce verification?
		if ( ! isset( $_GET['api_key'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return new \WP_Error( 'invalid_parameter', __( 'Missing API key.', 'scrobbble' ), 400 );
		}

		if ( ! isset( $_GET['cb'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return new \WP_Error( 'invalid_parameter', __( 'Missing callback URL.', 'scrobbble' ), 400 );
		}

		$current_user = wp_get_current_user();
		$api_key      = wp_unslash( $_GET['api_key'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$cb           = wp_unslash( $_GET['cb'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$url          = add_query_params_to_url(
			array_filter(
				array(
					'action'  => 'scrobbble',
					'api_key' => $api_key,
					'cb'      => $cb,
				)
			),
			wp_login_url()
		);

		include dirname( __DIR__ ) . '/templates/auth-form.php';
	}

	/**
	 * Process auth form.
	 */
	public static function confirmed() {
		$user_id = wp_get_current_user()->ID;

		$api_key = isset( $_POST['api_key'] ) ? wp_unslash( $_POST['api_key'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$cb      = isset( $_POST['cb'] ) ? wp_unslash( $_POST['cb'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$url = add_query_params_to_url(
			array( 'token' => static::get_auth_token( $user_id, $api_key, $cb ) ),
			$cb
		);

		wp_redirect( $url ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	}

	/**
	 * Generate token.
	 *
	 * @param  int    $user_id Current user ID.
	 * @param  string $api_key Client API key.
	 * @param  string $cb      Callback URL.
	 * @return string          Generated token.
	 */
	public static function get_auth_token( $user_id, $api_key, $cb ) {
		$key = md5( time() . wp_rand() ); // @todo: We should probably change this.

		add_user_meta(
			$user_id,
			"scrobbble_$key",
			array(
				'api_key' => $api_key,
				'cb'      => $cb,
				'expires' => 0, // Tokens don't normally expire.
			),
			true
		);

		return $key;
	}

	/**
	 * Processes 2.0 API requests.
	 *
	 * @param \WP_REST_Request $request REST request.
	 */
	public static function get( $request ) {
		$method = $request->get_param( 'method' );

		if ( empty( $method ) || ! array_key_exists( $method, self::$method_map ) ) {
			static::abort( LFM_INVALID_METHOD );
		}

		static::$method( $request );
	}

	/**
	 * Sets up a new session.
	 *
	 * @param \WP_REST_Request $request REST request.
	 */
	public static function method_auth_get_session( $request ) {
		$token  = $request->get_param( 'token' );
		$client = $request->get_param( 'api_key' ) ?: ''; // phpcs:ignore Universal.Operators.DisallowShortTernary.Found

		if ( empty( $token ) ) {
			// Missing token.
			static::abort( LFM_INVALID_TOKEN );
		}

		$users = get_users(
			array(
				'number'      => 1,
				'count_total' => false,
				'fields'      => 'ID',
				'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => "scrobbble_$token",
						'compare' => 'EXISTS',
					),
				),
			)
		);

		if ( empty( $users[0] ) ) {
			// Invalid token.
			static::abort( LFM_INVALID_TOKEN );
		}

		// Create session.
		$user       = get_user_by( 'id', $users[0] );
		$session_id = md5( $token . time() );

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
			// Something went wrong.
			static::abort( LFM_SERVICE_OFFLINE );
		}

		header( 'Content-Type: text/xml' );

		echo "<lfm status=\"ok\">\n";
		echo "	<session>\n";
		echo "		<name>{$user->user_login}</name>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo "		<key>{$session_id}</key>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo "		<subscriber>0</subscriber>\n";
		echo "	</session>\n";
		echo '</lfm>';

		die();
	}

	/**
	 * Returns user information.
	 *
	 * @param \WP_REST_Request $request REST request.
	 */
	public static function method_user_get_info( $request ) {
		$session_id = $request->get_param( 'sk' );
		$username   = $request->get_param( 'user' );

		$user = get_user_by( 'login', $username );
		if ( empty( $user->ID ) ) {
			self::abort( LFM_INVALID_RESOURCE );
		}

		$session = Scrobbble_API::get_session( $session_id );
		if ( empty( $session->user_id ) || $session->user_id != $user->ID ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual
			self::abort( LFM_INVALID_PARAMS );
		}

		$xml = new \SimpleXMLElement( '<lfm status="ok"></lfm>' );

		$user_node = $xml->addChild( 'user', null );
		$user_node->addChild( 'name', $user->user_login );
		$user_node->addChild( 'realname', trim( get_user_meta( $user->ID, 'first_name', true ) . ' ' . get_user_meta( $user->ID, 'last_name', true ) ) );

		$images = $user_node->addChild( 'image', get_avatar_url( $user, array( 'size' => 64 ) ) );
		$images->addAttribute( 'size', 'medium' );

		$user_node->addChild( 'profile_created', gmdate( '%c', $user->user_registered ) );

		xml_response( $xml );
		exit;
	}

	/**
	 * Echoes an error message, then exits.
	 *
	 * @param int $code Error code.
	 */
	protected static function abort( $code ) {
		$xml = new \SimpleXMLElement( '<lfm status="failed"></lfm>' );

		$error_node = $xml->addChild( 'error', self::$error_messages[ $code ] );
		$error_node->addAttribute( 'code', $code );

		xml_response( $xml );
		die();
	}
}
