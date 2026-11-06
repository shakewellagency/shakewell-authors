<?php
/**
 * The Authors box, template functions and profile privacy.
 *
 * @package Shakewell\Authors
 */

namespace Shakewell\Authors\Tests;

use Shakewell\Authors\Admin;
use Shakewell\Authors\Authors;
use Shakewell\Authors\Plugin;
use WP_UnitTestCase;

final class AuthorsTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		// The test case clears registered meta between tests; register again as init would.
		Plugin::register_meta();
	}

	private function submit( int $post_id, array $refs, string $nonce = '' ): void {
		$_POST = array(
			'shakewell_authors_nonce' => $nonce ? $nonce : wp_create_nonce( 'shakewell_authors_save' ),
			'shakewell_authors'       => $refs,
		);
		Admin::save( $post_id );
		$_POST = array();
	}

	private function profile( string $title ): int {
		return self::factory()->post->create(
			array(
				'post_type'  => Authors::PROFILE,
				'post_title' => $title,
			)
		);
	}

	public function test_saving_the_box_stores_the_ordered_list(): void {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$ann   = self::factory()->user->create( array( 'display_name' => 'Ann' ) );
		$prof  = $this->profile( 'Pat' );
		$post  = self::factory()->post->create();
		wp_set_current_user( $admin );

		$this->submit( $post, array( "profile:$prof", "user:$ann", "user:$ann", 'user:999999', 'junk' ) );

		$this->assertSame( array( "profile:$prof", "user:$ann" ), get_post_meta( $post, Authors::META, true ) );
		$this->assertSame( array( 'Pat', 'Ann' ), wp_list_pluck( shakewell_get_authors( $post ), 'display_name' ) );
		$this->assertSame( 'Pat and Ann', shakewell_authors_names( $post ) );
	}

	public function test_saving_without_a_valid_nonce_or_capability_changes_nothing(): void {
		$ann  = self::factory()->user->create();
		$post = self::factory()->post->create();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->submit( $post, array( "user:$ann" ), 'bad-nonce' );
		$this->assertSame( '', get_post_meta( $post, Authors::META, true ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->submit( $post, array( "user:$ann" ) );
		$this->assertSame( '', get_post_meta( $post, Authors::META, true ) );
		$this->assertFalse( current_user_can( 'edit_post_meta', $post, Authors::META ) );
	}

	public function test_empty_submission_clears_the_list(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$ann  = self::factory()->user->create();
		$post = self::factory()->post->create();
		$this->submit( $post, array( "user:$ann" ) );
		$this->submit( $post, array() );
		$this->assertSame( '', get_post_meta( $post, Authors::META, true ) );
	}

	public function test_falls_back_to_the_post_author(): void {
		$bo   = self::factory()->user->create( array( 'display_name' => 'Bo' ) );
		$post = self::factory()->post->create( array( 'post_author' => $bo ) );

		$this->assertSame(
			array(
				array(
					'type'         => 'user',
					'id'           => $bo,
					'display_name' => 'Bo',
				),
			),
			shakewell_get_authors( $post )
		);
		$this->assertSame( 'Bo', shakewell_authors_names( $post ) );
	}

	public function test_a_deleted_author_is_skipped_and_the_rest_keep_their_order(): void {
		$a    = self::factory()->user->create( array( 'display_name' => 'A' ) );
		$b    = self::factory()->user->create( array( 'display_name' => 'B' ) );
		$post = self::factory()->post->create();
		update_post_meta( $post, Authors::META, array( "user:$a", "user:$b" ) );
		wp_delete_user( $a );
		$this->assertSame( array( 'B' ), wp_list_pluck( shakewell_get_authors( $post ), 'display_name' ) );
	}

	public function test_profiles_are_private(): void {
		$prof = $this->profile( 'Pat' );
		$type = get_post_type_object( Authors::PROFILE );

		$this->assertFalse( $type->public );
		$this->assertFalse( $type->show_in_rest );
		$this->assertFalse( $type->has_archive );
		$this->assertArrayNotHasKey( '/wp/v2/' . Authors::PROFILE, rest_get_server()->get_routes() );
		$this->assertFalse( get_registered_meta_keys( 'post', 'post' )[ Authors::META ]['show_in_rest'] );

		$this->assertFalse( $type->publicly_queryable );
		$this->assertTrue( $type->exclude_from_search );

		// Anonymous front-end style queries (what a request for ?p=<id> or a search runs) never return it.
		wp_set_current_user( 0 );
		$this->assertSame( array(), ( new \WP_Query( array( 'p' => $prof, 'post_type' => 'any' ) ) )->posts );
		$this->assertSame( array(), ( new \WP_Query( array( 's' => 'Pat', 'post_type' => 'any' ) ) )->posts );
	}
}
