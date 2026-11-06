<?php
/**
 * Reading and sanitising a post's ordered author list.
 *
 * @package Shakewell\Authors
 */

namespace Shakewell\Authors;

defined( 'ABSPATH' ) || exit;

/**
 * An author reference is "user:<id>" or "profile:<id>". The list is stored in one post meta key.
 */
final class Authors {

	public const META    = '_shakewell_authors';
	public const PROFILE = 'shakewell_author';

	/**
	 * Post types that get the Authors box. Filterable; defaults to posts.
	 *
	 * @return string[]
	 */
	public static function post_types(): array {
		$types = apply_filters( 'shakewell_authors_post_types', array( 'post' ) );
		return array_values( array_filter( (array) $types, 'post_type_exists' ) );
	}

	/**
	 * Splits "user:12" into type and id.
	 *
	 * @param mixed $ref Candidate reference.
	 * @return array{type:string,id:int}|null
	 */
	public static function parse( $ref ): ?array {
		if ( is_string( $ref ) && preg_match( '/^(user|profile):([1-9][0-9]{0,17})$/', $ref, $m ) ) {
			return array(
				'type' => $m[1],
				'id'   => (int) $m[2],
			);
		}
		return null;
	}

	/**
	 * The display name of a referenced author, or '' when it no longer exists.
	 *
	 * @param string $type 'user' or 'profile'.
	 * @param int    $id   User or profile id.
	 */
	public static function name( string $type, int $id ): string {
		if ( 'user' === $type ) {
			$user = get_userdata( $id );
			return $user ? $user->display_name : '';
		}
		$post = get_post( $id );
		if ( $post && self::PROFILE === $post->post_type && 'trash' !== $post->post_status ) {
			return $post->post_title;
		}
		return '';
	}

	/**
	 * Meta sanitiser: keeps well-formed references to authors that exist, once each, in order.
	 *
	 * @param mixed $value Submitted list.
	 * @return string[]
	 */
	public static function sanitize( $value ): array {
		$out = array();
		foreach ( is_array( $value ) ? $value : array() as $ref ) {
			$p = self::parse( $ref );
			if ( $p && '' !== self::name( $p['type'], $p['id'] ) ) {
				$out[ $p['type'] . ':' . $p['id'] ] = $p['type'] . ':' . $p['id'];
			}
		}
		return array_values( $out );
	}

	/**
	 * Ordered authors of a post; the post's own author when none are set.
	 *
	 * @param int|\WP_Post|null $post Post, defaults to the current one.
	 * @return array<int,array{type:string,id:int,display_name:string}>
	 */
	public static function get( $post = null ): array {
		$post = get_post( $post );
		if ( ! $post ) {
			return array();
		}
		$refs = get_post_meta( $post->ID, self::META, true );
		$out  = array();
		foreach ( is_array( $refs ) ? $refs : array() as $ref ) {
			$p    = self::parse( $ref );
			$name = $p ? self::name( $p['type'], $p['id'] ) : '';
			if ( '' !== $name ) {
				$out[] = $p + array( 'display_name' => $name );
			}
		}
		if ( $out ) {
			return $out;
		}
		$user = get_userdata( (int) $post->post_author );
		return $user ? array(
			array(
				'type'         => 'user',
				'id'           => $user->ID,
				'display_name' => $user->display_name,
			),
		) : array();
	}
}
