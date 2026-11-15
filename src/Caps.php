<?php
/**
 * Optional edit rights for listed authors.
 *
 * @package Shakewell\Authors
 */

namespace Shakewell\Authors;

defined( 'ABSPATH' ) || exit;

/**
 * Off by default. Turn it on with `add_filter( 'shakewell_authors_edit_rights', '__return_true' );`.
 *
 * It matches what Co-Authors Plus does, so a site can switch plugins without anyone losing access: a user
 * listed as an author of a post is treated like the post's own author for editing and deleting it. Core
 * asks for the "edit others" or "delete others" capability when someone else wrote the post, and that
 * request is changed into the plain "edit" or "delete" one. The status capabilities stay, so the user's
 * own role still decides what they may do (an author cannot touch a published post unless their role
 * allows editing published posts).
 */
final class Caps {

	/**
	 * The post being saved from the classic editor, set once core has checked the save nonce.
	 *
	 * @var int
	 */
	private static int $saving = 0;

	/**
	 * Registers hooks. Called on plugins_loaded.
	 */
	public static function boot(): void {
		add_filter( 'map_meta_cap', array( self::class, 'map' ), 10, 4 );
		add_action( 'check_admin_referer', array( self::class, 'note_save' ), 10, 2 );
	}

	/**
	 * Whether the feature is on.
	 */
	public static function enabled(): bool {
		return (bool) apply_filters( 'shakewell_authors_edit_rights', false );
	}

	/**
	 * Whether this user is listed as an author of the post.
	 *
	 * @param int $post_id Post id.
	 * @param int $user_id User id.
	 */
	public static function listed( int $post_id, int $user_id ): bool {
		$refs = get_post_meta( $post_id, Authors::META, true );
		return is_array( $refs ) && in_array( 'user:' . $user_id, $refs, true );
	}

	/**
	 * Whether the user may change a post's author list. With edit rights on, a listed author must not be able to
	 * add other people to the list, so the user's own "edit others" capability is required, not edit_post.
	 *
	 * @param int $post_id Post id.
	 * @param int $user_id User id; the current user when 0.
	 */
	public static function can_set( int $post_id, int $user_id = 0 ): bool {
		$user = $user_id ? get_userdata( $user_id ) : wp_get_current_user();
		$type = get_post_type_object( (string) get_post_type( $post_id ) );
		if ( ! $user || ! $type ) {
			return false;
		}
		$can = user_can( $user, 'edit_post', $post_id );
		if ( $can && self::enabled() ) {
			$can = user_can( $user, $type->cap->edit_others_posts );
		}
		/**
		 * Filters whether a user may change a post's author list. With edit rights on, the default needs the
		 * post type's "edit others" capability, as Co-Authors Plus does, so an author-role owner cannot either.
		 * Return true for the owner to relax that.
		 *
		 * @param bool $can     Default answer.
		 * @param int  $post_id Post id.
		 * @param int  $user_id User id.
		 */
		return (bool) apply_filters( 'shakewell_authors_can_set', $can, $post_id, (int) $user->ID );
	}

	/**
	 * Swaps the "others" capability for the plain one when the user is a listed author.
	 *
	 * @param string[] $caps    Primitive capabilities required.
	 * @param string   $cap     Capability being checked.
	 * @param int      $user_id User id.
	 * @param array    $args    Extra arguments; the first is the post id.
	 * @return string[]
	 */
	public static function map( $caps, $cap, $user_id, $args ) {
		if ( ! self::enabled() ) {
			return $caps;
		}
		if ( empty( $args ) && $user_id && is_admin() && get_current_user_id() === (int) $user_id ) {
			return self::allow_save( $caps, (string) $cap, (int) $user_id );
		}
		$actions = array(
			'edit_post'   => 'edit',
			'edit_page'   => 'edit',
			'delete_post' => 'delete',
			'delete_page' => 'delete',
		);
		if ( ! isset( $actions[ $cap ] ) || empty( $args[0] ) || ! $user_id ) {
			return $caps;
		}
		$post = get_post( $args[0] );
		if ( ! $post || 'revision' === $post->post_type || (int) $post->post_author === (int) $user_id || ! in_array( $post->post_type, Authors::post_types(), true ) ) {
			return $caps;
		}
		$type = get_post_type_object( $post->post_type );
		if ( ! $type || ! $type->map_meta_cap ) {
			return $caps;
		}
		$verb = $actions[ $cap ];
		$key  = array_search( $type->cap->{ $verb . '_others_posts' }, $caps, true );
		if ( false === $key || ! self::listed( $post->ID, (int) $user_id ) ) {
			return $caps;
		}
		$caps[ $key ] = $type->cap->{ $verb . '_posts' };
		// Like core does for the post author: a trashed post that was published or scheduled still needs the published capability.
		if ( 'trash' === $post->post_status && in_array( get_post_meta( $post->ID, '_wp_trash_meta_status', true ), array( 'publish', 'future' ), true ) ) {
			$caps[] = $type->cap->{ $verb . '_published_posts' };
		}
		return $caps;
	}

	/**
	 * Notes the post being saved once core has checked its save nonce.
	 *
	 * @param string|int $action Nonce action.
	 * @param int|false  $result Whether the nonce was valid.
	 */
	public static function note_save( $action, $result ): void {
		$post_id      = isset( $_POST['post_ID'] ) ? absint( $_POST['post_ID'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Core has just verified the nonce.
		self::$saving = ( $result && $post_id && 'update-post_' . $post_id === $action ) ? $post_id : 0;
	}

	/**
	 * Lets a listed author save from the classic editor.
	 *
	 * When the submitted post author is not the current user, core asks for "edit others" while it reads the form,
	 * which a listed author who did not write the post always triggers. This allows that one check, only for the post
	 * whose nonce core has just verified, only when the post author is unchanged, and not after the update has started.
	 *
	 * @param string[] $caps    Primitive capabilities required.
	 * @param string   $cap     Capability being checked.
	 * @param int      $user_id User id.
	 * @return string[]
	 */
	private static function allow_save( array $caps, string $cap, int $user_id ): array {
		if ( ! self::$saving || did_action( 'pre_post_update' ) ) {
			return $caps;
		}
		$post = get_post( self::$saving );
		if ( ! $post || 'revision' === $post->post_type || ! in_array( $post->post_type, Authors::post_types(), true ) ) {
			return $caps;
		}
		$type = get_post_type_object( $post->post_type );
		if ( ! $type || $cap !== $type->cap->edit_others_posts ) {
			return $caps;
		}
		self::$saving = 0; // Only the first check after the nonce.
		if ( ! isset( $_POST['post_author'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Core verified the nonce before note_save().
			return $caps;
		}
		$submitted = absint( ! empty( $_POST['post_author_override'] ) ? $_POST['post_author_override'] : $_POST['post_author'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( (int) $post->post_author === $user_id || (int) $post->post_author !== $submitted || ! self::listed( $post->ID, $user_id ) ) {
			return $caps;
		}
		return array( $type->cap->edit_posts );
	}
}
