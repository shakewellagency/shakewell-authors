<?php
/**
 * Template functions for themes.
 *
 * @package Shakewell\Authors
 */

use Shakewell\Authors\Authors;

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'shakewell_get_authors' ) ) {
	/**
	 * Ordered authors of a post as arrays of type ('user' or 'profile'), id and display_name.
	 * Falls back to the post's own author when none are set. Escape display_name on output.
	 *
	 * @param int|WP_Post|null $post Post, defaults to the current one.
	 * @return array<int,array{type:string,id:int,display_name:string}>
	 */
	function shakewell_get_authors( $post = null ): array {
		return Authors::get( $post );
	}
}

if ( ! function_exists( 'shakewell_authors_names' ) ) {
	/**
	 * The authors' names as a readable list, such as "Ann, Bo, and Cy". Escape on output.
	 *
	 * @param int|WP_Post|null $post Post, defaults to the current one.
	 */
	function shakewell_authors_names( $post = null ): string {
		return wp_sprintf( '%l', wp_list_pluck( Authors::get( $post ), 'display_name' ) );
	}
}
