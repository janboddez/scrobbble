<?php
/**
 * Everything CPT and taxonomy registration.
 *
 * @package Scrobbble
 */

namespace Scrobbble;

/**
 * Handles CPT and taxonomy registration.
 */
class Scrobbble_CPT {
	/**
	 * Registers custom post types.
	 */
	public static function register() {
		// Artists.
		register_taxonomy(
			'iwcpt_artist',
			array( 'iwcpt_listen' ),
			array(
				'labels'                => array(
					'name'                       => __( 'Artists', 'scrobbble' ),
					'singular_name'              => __( 'Artist', 'scrobbble' ),
					'search_items'               => __( 'Search Artists', 'scrobbble' ),
					'popular_items'              => __( 'Popular Artists', 'scrobbble' ),
					'all_items'                  => __( 'All Artists', 'scrobbble' ),
					'edit_item'                  => __( 'Edit Artist', 'scrobbble' ),
					'update_item'                => __( 'Update Artist', 'scrobbble' ),
					'add_new_item'               => __( 'Add New Artist', 'scrobbble' ),
					'new_item_name'              => __( 'New Artist Name', 'scrobbble' ),
					'separate_items_with_commas' => __( 'Separate artists with commas', 'scrobbble' ),
					'add_or_remove_items'        => __( 'Add or remove artists', 'scrobbble' ),
					'choose_from_most_used'      => __( 'Choose from the most used artists', 'scrobbble' ),
					'not_found'                  => __( 'No artists found.', 'scrobbble' ),
				),
				'hierarchical'          => false,
				'show_ui'               => true,
				'show_admin_column'     => true,
				'update_count_callback' => '_update_post_term_count',
				'query_var'             => true,
				'rewrite'               => array(
					'slug'       => __( 'listens/artist', 'scrobbble' ),
					'with_front' => false,
				),
			)
		);

		// Albums.
		register_taxonomy(
			'iwcpt_album',
			array( 'iwcpt_listen' ),
			array(
				'labels'                => array(
					'name'                       => __( 'Albums', 'scrobbble' ),
					'singular_name'              => __( 'Album', 'scrobbble' ),
					'search_items'               => __( 'Search Albums', 'scrobbble' ),
					'popular_items'              => __( 'Popular Albums', 'scrobbble' ),
					'all_items'                  => __( 'All Albums', 'scrobbble' ),
					'edit_item'                  => __( 'Edit Album', 'scrobbble' ),
					'update_item'                => __( 'Update Album', 'scrobbble' ),
					'add_new_item'               => __( 'Add New Album', 'scrobbble' ),
					'new_item_name'              => __( 'New Album Name', 'scrobbble' ),
					'separate_items_with_commas' => __( 'Separate albums with commas', 'scrobbble' ),
					'add_or_remove_items'        => __( 'Add or remove albums', 'scrobbble' ),
					'choose_from_most_used'      => __( 'Choose from the most used albums', 'scrobbble' ),
					'not_found'                  => __( 'No albums found.', 'scrobbble' ),
				),
				'hierarchical'          => false,
				'show_ui'               => true,
				'show_admin_column'     => true,
				'update_count_callback' => '_update_post_term_count',
				'query_var'             => true,
				'rewrite'               => array(
					'slug'       => __( 'listens/album', 'scrobbble' ),
					'with_front' => false,
				),
			)
		);

		// Genres.
		register_taxonomy(
			'iwcpt_genre',
			array( 'iwcpt_listen' ),
			array(
				'labels'                => array(
					'name'                       => __( 'Genres', 'scrobbble' ),
					'singular_name'              => __( 'Genre', 'scrobbble' ),
					'search_items'               => __( 'Search Genres', 'scrobbble' ),
					'popular_items'              => __( 'Popular Genres', 'scrobbble' ),
					'all_items'                  => __( 'All Genres', 'scrobbble' ),
					'edit_item'                  => __( 'Edit Genre', 'scrobbble' ),
					'update_item'                => __( 'Update Genre', 'scrobbble' ),
					'add_new_item'               => __( 'Add New Genre', 'scrobbble' ),
					'new_item_name'              => __( 'New Genre Name', 'scrobbble' ),
					'separate_items_with_commas' => __( 'Separate genres with commas', 'scrobbble' ),
					'add_or_remove_items'        => __( 'Add or remove genres', 'scrobbble' ),
					'choose_from_most_used'      => __( 'Choose from the most used genres', 'scrobbble' ),
					'not_found'                  => __( 'No genres found.', 'scrobbble' ),
				),
				'hierarchical'          => false,
				'show_ui'               => true,
				'show_admin_column'     => true,
				'update_count_callback' => '_update_post_term_count',
				'query_var'             => true,
				'rewrite'               => array(
					'slug'       => __( 'listens/genre', 'scrobbble' ),
					'with_front' => false,
				),
			)
		);

		// Scrobbles.
		register_post_type(
			'iwcpt_listen',
			array(
				'labels'            => array(
					'name'               => __( 'Listens', 'scrobbble' ),
					'singular_name'      => __( 'Listen', 'scrobbble' ),
					'add_new'            => __( 'New Listen', 'scrobbble' ),
					'add_new_item'       => __( 'Add New Listen', 'scrobbble' ),
					'edit_item'          => __( 'Edit Listen', 'scrobbble' ),
					'view_item'          => __( 'View Listen', 'scrobbble' ),
					'view_items'         => __( 'View Listens', 'scrobbble' ),
					'search_items'       => __( 'Search Listens', 'scrobbble' ),
					'not_found'          => __( 'No listens found.', 'scrobbble' ),
					'not_found_in_trash' => __( 'No listens found in trash.', 'scrobbble' ),
				),
				'public'            => true,
				'has_archive'       => true,
				'show_in_nav_menus' => true,
				'rewrite'           => array(
					'slug'       => __( 'listens', 'scrobbble' ),
					'with_front' => false,
				),
				'supports'          => array( 'author', 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'trackbacks', 'comments' ),
				'menu_icon'         => 'dashicons-format-audio',
			)
		);

		// Add listens to IndieBlocks' list of "titleless" post types.
		add_filter( 'indieblocks_short-form_post_types', array( __CLASS__, 'indieblocks_post_types' ) );
	}

	/**
	 * Prevents IndieBlocks from adding a `p-name` class to listens' post title.
	 *
	 * @param  array $post_types Post types considered titleless.
	 * @return array             Filtered post type array.
	 */
	public static function indieblocks_post_types( $post_types ) {
		$post_types[] = 'iwcpt_listen';

		return array_unique( $post_types );
	}
}
