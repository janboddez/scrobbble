<?php
/**
 * All things Gutenberg.
 *
 * @package Scrobbble
 */

namespace Scrobbble;

/**
 * Where Gutenberg blocks are registered.
 */
class Blocks {
	/**
	 * Hooks and such.
	 */
	public static function register() {
		add_action( 'init', array( __CLASS__, 'register_blocks' ) );
	}

	/**
	 * Registers our blocks.
	 */
	public static function register_blocks() {
		register_block_type_from_metadata(
			dirname( __DIR__ ) . '/blocks/now-playing',
			array(
				'render_callback' => array( __CLASS__, 'render_now_playing_block' ),
			)
		);
	}

	/**
	 * Renders the `scrobbble/now-playing` block.
	 *
	 * @param  array     $attributes Block attributes.
	 * @param  string    $content    Block default content.
	 * @param  \WP_Block $block      Block instance.
	 * @return string                Output HTML.
	 */
	public static function render_now_playing_block( $attributes, $content, $block ) {
		wp_enqueue_style( 'scrobbble-now-playing', plugins_url( '/assets/now-playing.css', dirname( __FILE__ ) ), array(), Scrobbble::PLUGIN_VERSION );
		wp_enqueue_script( 'scrobbble-now-playing-js', plugins_url( '/assets/now-playing.js', dirname( __FILE__ ) ), array( 'wp-api-fetch', 'wp-i18n' ), Scrobbble::PLUGIN_VERSION, true );

		$title = ! empty( $attributes['title'] ) ? $attributes['title'] : __( 'Now Playing', 'scrobbble' );
		$url   = ! empty( $attributes['url'] ) ? $attributes['url'] : '';
		return '<div ' . get_block_wrapper_attributes() . ' data-title="' . esc_attr( $title ) . '" data-url="' . esc_url( $url ) . '"></div>';
	}
}
