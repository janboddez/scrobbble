<?php
/**
 * Main plugin class.
 *
 * @package Scrobbble
 */

namespace Scrobbble;

/**
 * Our main plugin class.
 */
class Scrobbble {
	/**
	 * Plugin version.
	 */
	const PLUGIN_VERSION = '0.1.2';

	/**
	 * Single plugin instance.
	 *
	 * @var Scrobbble $instance Single plugin instance.
	 */
	private static $instance;

	/**
	 * Returns single plugin instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Registers hook callbacks.
	 */
	public function register() {
		register_activation_hook( dirname( __DIR__ ) . '/scrobbble.php', array( $this, 'activate' ) );

		add_action( 'init', array( 'Scrobbble\\Blocks', 'register_blocks' ) );
		add_action( 'init', array( 'Scrobbble\\Scrobbble_CPT', 'register' ), 1 ); // Early, because otherwise the custom taxonomy block won't show.
		add_action( 'rest_api_init', array( 'Scrobbble\\Scrobbble_API', 'register_api_routes' ) );

		Scrobbble_API_2::register();

		add_filter( 'wp_insert_post_data', array( $this, 'set_slug' ), 10, 2 );
	}

	/**
	 * Generate a random slug (for scrobbles, at least).
	 *
	 * @param array $data    Post data.
	 * @param array $postarr Original data.
	 */
	public function set_slug( $data, $postarr ) {
		if ( ! empty( $postarr['ID'] ) ) {
			// Not a new post. Bail.
			return $data;
		}

		if ( ! in_array( $data['post_type'], array( 'iwcpt_listen' ), true ) ) {
			return $data;
		}

		global $wpdb;

		// Generate a random slug for short-form content, i.e., notes and likes.
		do {
			// Generate random slug.
			$slug = bin2hex( openssl_random_pseudo_bytes( 5 ) );

			// Check uniqueness.
			$result = $wpdb->get_var( $wpdb->prepare( "SELECT post_name FROM $wpdb->posts WHERE post_name = %s LIMIT 1", $slug ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		} while ( $result );

		$data['post_name'] = $slug;

		return $data;
	}

	/**
	 * Runs when the plugin is activated.
	 */
	public function activate() {
		// Register post types, then flush rewrite rules.
		Scrobbble_CPT::register();
		flush_rewrite_rules();

		global $wpdb;

		$table_name      = $wpdb->prefix . 'scrobbble_sessions';
		$charset_collate = $wpdb->get_charset_collate();

		// Create database table (if it doesn't exist, yet).
		$sql = "CREATE TABLE $table_name (
			session_id varchar(32) DEFAULT '' NOT NULL,
			expires bigint(20) UNSIGNED DEFAULT 0 NOT NULL,
			client varchar(161) DEFAULT '' NOT NULL,
			user_id bigint(20) UNSIGNED DEFAULT 0 NOT NULL,
			PRIMARY KEY (session_id)
		) $charset_collate; ";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( $sql );
	}
}
