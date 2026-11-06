<?php
/**
 * The Authors box on the post edit screen.
 *
 * @package Shakewell\Authors
 */

namespace Shakewell\Authors;

defined( 'ABSPATH' ) || exit;

/**
 * Meta box, save handler and the search endpoint behind the "Add an author" field.
 */
final class Admin {

	private const NONCE = 'shakewell_authors_save';

	/**
	 * Registers admin hooks.
	 */
	public static function init(): void {
		add_action( 'add_meta_boxes', array( self::class, 'add_box' ) );
		add_action( 'save_post', array( self::class, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
		add_action( 'wp_ajax_shakewell_authors_search', array( self::class, 'search' ) );
	}

	/**
	 * Adds the box to each chosen post type.
	 */
	public static function add_box(): void {
		add_meta_box( 'shakewell-authors', __( 'Authors', 'shakewell-authors' ), array( self::class, 'render' ), Authors::post_types(), 'side' );
	}

	/**
	 * Loads the box script and styles on the post edit screens.
	 *
	 * @param string $hook Current admin page.
	 */
	public static function enqueue( string $hook ): void {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		wp_enqueue_style( 'shakewell-authors', plugins_url( 'assets/admin.css', FILE ), array(), VERSION );
		wp_enqueue_script( 'shakewell-authors', plugins_url( 'assets/admin.js', FILE ), array( 'jquery', 'jquery-ui-sortable' ), VERSION, true );
		wp_localize_script(
			'shakewell-authors',
			'shakewellAuthors',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'shakewell_authors_search' ),
				'i18n'    => array(
					/* translators: %s: author name */
					'up'      => __( 'Move %s up', 'shakewell-authors' ),
					/* translators: %s: author name */
					'down'    => __( 'Move %s down', 'shakewell-authors' ),
					/* translators: %s: author name */
					'remove'  => __( 'Remove %s', 'shakewell-authors' ),
					/* translators: %s: author name */
					'add'     => __( 'Add %s', 'shakewell-authors' ),
					'upLabel' => __( 'Up', 'shakewell-authors' ),
					'dnLabel' => __( 'Down', 'shakewell-authors' ),
					'rmLabel' => __( 'Remove', 'shakewell-authors' ),
					/* translators: 1: author name, 2: position, 3: total */
					'moved'   => __( '%1$s moved to position %2$d of %3$d', 'shakewell-authors' ),
					/* translators: %s: author name */
					'added'   => __( '%s added', 'shakewell-authors' ),
					/* translators: %s: author name */
					'removed' => __( '%s removed', 'shakewell-authors' ),
					'none'    => __( 'No matches', 'shakewell-authors' ),
				),
			)
		);
	}

	/**
	 * Draws the ordered list and the search field.
	 *
	 * @param \WP_Post $post Post being edited.
	 */
	public static function render( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE, 'shakewell_authors_nonce' );
		$refs = get_post_meta( $post->ID, Authors::META, true );
		echo '<ol class="shakewell-authors__list" aria-label="' . esc_attr__( 'Authors, in order', 'shakewell-authors' ) . '">';
		foreach ( Authors::sanitize( $refs ) as $ref ) {
			$p     = Authors::parse( $ref );
			$name  = Authors::name( $p['type'], $p['id'] );
			$label = 'user' === $p['type'] ? __( 'User', 'shakewell-authors' ) : __( 'Profile', 'shakewell-authors' );
			echo '<li><input type="hidden" name="shakewell_authors[]" value="' . esc_attr( $ref ) . '">';
			echo '<span class="shakewell-authors__name">' . esc_html( $name ) . '</span> <em>(' . esc_html( $label ) . ')</em></li>';
		}
		echo '</ol>';
		echo '<p class="description">' . esc_html__( 'With no authors listed, the post author is used.', 'shakewell-authors' ) . '</p>';
		echo '<p role="status" aria-live="polite" class="screen-reader-text shakewell-authors__status"></p>';
		echo '<label for="shakewell-authors-search">' . esc_html__( 'Add an author', 'shakewell-authors' ) . '</label>';
		echo '<input type="search" id="shakewell-authors-search" class="widefat" autocomplete="off" aria-describedby="shakewell-authors-hint">';
		echo '<p id="shakewell-authors-hint" class="description">' . esc_html__( 'Type a name to find users and author profiles.', 'shakewell-authors' ) . '</p>';
		echo '<ul class="shakewell-authors__results"></ul>';
	}

	/**
	 * Saves the submitted list. Needs the box nonce and permission to edit this post.
	 *
	 * @param int $post_id Post id.
	 */
	public static function save( $post_id ): void {
		$post_id = (int) $post_id;
		if ( ! isset( $_POST['shakewell_authors_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['shakewell_authors_nonce'] ) ), self::NONCE )
			|| ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			|| ! in_array( get_post_type( $post_id ), Authors::post_types(), true )
			|| ! current_user_can( 'edit_post', $post_id )
		) {
			return;
		}
		// Each value is checked by Authors::sanitize(), also registered as the meta sanitiser.
		$refs = isset( $_POST['shakewell_authors'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['shakewell_authors'] ) ) : array();
		$refs = Authors::sanitize( $refs );
		if ( $refs ) {
			update_post_meta( $post_id, Authors::META, $refs );
		} else {
			delete_post_meta( $post_id, Authors::META );
		}
	}

	/**
	 * Ajax: finds users and author profiles matching a typed name.
	 */
	public static function search(): void {
		check_ajax_referer( 'shakewell_authors_search', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( null, 403 );
		}
		$term = isset( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '';
		$out  = array();
		if ( '' !== $term ) {
			$users = get_users(
				array(
					'search'         => '*' . $term . '*',
					'search_columns' => array( 'user_login', 'user_nicename', 'display_name' ),
					'capability__in' => array( 'edit_posts' ),
					'number'         => 10,
				)
			);
			foreach ( $users as $user ) {
				$out[] = array(
					'ref'   => 'user:' . $user->ID,
					'label' => $user->display_name,
					'kind'  => __( 'User', 'shakewell-authors' ),
				);
			}
			$profiles = get_posts(
				array(
					'post_type'      => Authors::PROFILE,
					'post_status'    => array( 'publish', 'draft', 'private' ),
					's'              => $term,
					'posts_per_page' => 10,
					'no_found_rows'  => true,
				)
			);
			foreach ( $profiles as $profile ) {
				$out[] = array(
					'ref'   => 'profile:' . $profile->ID,
					'label' => $profile->post_title,
					'kind'  => __( 'Profile', 'shakewell-authors' ),
				);
			}
		}
		wp_send_json_success( $out );
	}
}
