<?php
/**
 * Plugin bootstrap.
 *
 * @package Shakewell\Authors
 */

namespace Shakewell\Authors;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the private profile post type and the author list meta key, and starts the admin box and CLI.
 */
final class Plugin {

	/**
	 * Registers hooks. Called on plugins_loaded.
	 */
	public static function boot(): void {
		add_action( 'init', array( self::class, 'register_profiles' ) );
		add_action( 'init', array( self::class, 'register_meta' ), 20 );

		if ( is_admin() ) {
			Admin::init();
		}
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require_once __DIR__ . '/Cli.php';
			\WP_CLI::add_command( 'shakewell-authors', Cli::class );
		}
	}

	/**
	 * Author profiles: never public, no REST route, no archive, no permalink.
	 */
	public static function register_profiles(): void {
		register_post_type(
			Authors::PROFILE,
			array(
				'labels'              => array(
					'name'          => __( 'Author profiles', 'shakewell-authors' ),
					'singular_name' => __( 'Author profile', 'shakewell-authors' ),
					'add_new_item'  => __( 'Add author profile', 'shakewell-authors' ),
					'edit_item'     => __( 'Edit author profile', 'shakewell-authors' ),
					'menu_name'     => __( 'Author profiles', 'shakewell-authors' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => 'users.php',
				'show_in_rest'        => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'can_export'          => true,
				'supports'            => array( 'title', 'excerpt', 'thumbnail' ),
			)
		);
	}

	/**
	 * One protected meta key holds the ordered list. It is not exposed in REST.
	 */
	public static function register_meta(): void {
		foreach ( Authors::post_types() as $type ) {
			register_post_meta(
				$type,
				Authors::META,
				array(
					'type'              => 'array',
					'single'            => true,
					'show_in_rest'      => false,
					'sanitize_callback' => array( Authors::class, 'sanitize' ),
					'auth_callback'     => static function ( $allowed, $key, $post_id ) {
						unset( $allowed, $key );
						return current_user_can( 'edit_post', $post_id );
					},
				)
			);
		}
	}
}
