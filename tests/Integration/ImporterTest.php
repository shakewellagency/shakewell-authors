<?php
/**
 * Co-Authors Plus import and verify, against data stored the way Co-Authors Plus stores it.
 *
 * @package Shakewell\Authors
 */

namespace Shakewell\Authors\Tests;

use Shakewell\Authors\Authors;
use Shakewell\Authors\Importer;
use WP_UnitTestCase;

final class ImporterTest extends WP_UnitTestCase {

	/** @var int[] */
	private array $posts = array();

	public function set_up(): void {
		parent::set_up();
		\Shakewell\Authors\Plugin::register_meta();
		// What Co-Authors Plus registers: a sorted "author" taxonomy and a "guest-author" type.
		register_taxonomy( 'author', 'post', array( 'sort' => true ) );
		register_post_type( 'guest-author', array( 'public' => false ) );

		$ann = self::factory()->user->create(
			array(
				'user_login'   => 'ann',
				'display_name' => 'Ann Admin',
			)
		);
		$bob = self::factory()->user->create(
			array(
				'user_login'   => 'bob',
				'display_name' => 'Bob',
			)
		);

		$one   = $this->guest( 'guest-one', 'Guest One', 'Bio one' );
		$three = $this->guest( 'guest-three', 'Guest Three', 'Bio three', 'ann' ); // Linked, but named differently.
		$bobg  = $this->guest( 'bob-guest', 'Bob', '', 'bob' ); // Linked and named the same: becomes the user.

		$terms = array(
			'ann'         => $this->term( 'ann', 'ann' ),
			'guest-one'   => $one,
			'guest-three' => $three,
			'bob-guest'   => $bobg,
		);

		$this->posts[1] = $this->post_with( array( $terms['guest-one'], $terms['ann'] ) );
		$this->posts[2] = $this->post_with( array( $terms['ann'], $terms['guest-three'], $terms['guest-one'] ) );
		$this->posts[3] = $this->post_with( array( $terms['bob-guest'] ) );
		$this->posts[4] = self::factory()->post->create( array( 'post_author' => $bob ) ); // No terms.
	}

	public function tear_down(): void {
		unregister_taxonomy( 'author' );
		parent::tear_down();
	}

	private function term( string $login, string $nicename ): int {
		$t = wp_insert_term(
			$login,
			'author',
			array(
				'slug'        => 'cap-' . $nicename,
				'description' => $login,
			)
		);
		return $t['term_id'];
	}

	private function guest( string $login, string $name, string $bio, string $linked = '' ): int {
		$id = self::factory()->post->create(
			array(
				'post_type'  => 'guest-author',
				'post_title' => $name,
				'post_name'  => 'cap-' . $login,
			)
		);
		$meta = array(
			'cap-display_name'   => $name,
			'cap-user_login'     => $login,
			'cap-user_email'     => $login . '@example.test',
			'cap-description'    => $bio,
			'cap-linked_account' => $linked,
		);
		foreach ( $meta as $k => $v ) {
			update_post_meta( $id, $k, $v );
		}
		return $this->term( $login, $login );
	}

	private function post_with( array $term_ids ): int {
		$id = self::factory()->post->create();
		wp_set_object_terms( $id, $term_ids, 'author' );
		return $id;
	}

	private function names( int $post_id ): array {
		return wp_list_pluck( shakewell_get_authors( $post_id ), 'display_name' );
	}

	private function profile_count(): int {
		return count( get_posts( array( 'post_type' => Authors::PROFILE, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) ) );
	}

	public function test_import_gives_the_same_names_in_the_same_order(): void {
		unregister_taxonomy( 'author' ); // The import must not need Co-Authors Plus to be active.
		$counts = ( new Importer( 2 ) )->import();

		$this->assertSame( array( 'Guest One', 'Ann Admin' ), $this->names( $this->posts[1] ) );
		$this->assertSame( array( 'Ann Admin', 'Guest Three', 'Guest One' ), $this->names( $this->posts[2] ) );
		$this->assertSame( array( 'Bob' ), $this->names( $this->posts[3] ) );
		$this->assertSame( 'user', shakewell_get_authors( $this->posts[3] )[0]['type'] );
		$this->assertSame( 'profile', shakewell_get_authors( $this->posts[1] )[0]['type'] );
		$this->assertSame( 'Bob', $this->names( $this->posts[4] )[0] );
		$this->assertSame( 3, $counts['posts'] );
		$this->assertSame( 2, $counts['profiles_created'] );
		$this->assertSame( 2, $this->profile_count() );
		$this->assertSame( 'Bio one', get_post( shakewell_get_authors( $this->posts[1] )[0]['id'] )->post_excerpt );
		$this->assertSame( '', get_post_meta( $this->posts[4], Authors::META, true ) );
	}

	public function test_import_is_idempotent(): void {
		( new Importer() )->import();
		$before = array_map( fn( $p ) => get_post_meta( $p, Authors::META, true ), $this->posts );
		$again  = ( new Importer() )->import();

		$this->assertSame( 0, $again['profiles_created'] );
		$this->assertSame( 0, $again['updated'] );
		$this->assertSame( 2, $this->profile_count() );
		$this->assertSame( $before, array_map( fn( $p ) => get_post_meta( $p, Authors::META, true ), $this->posts ) );
	}

	public function test_dry_run_writes_nothing(): void {
		$counts = ( new Importer() )->import( true );

		$this->assertSame( 3, $counts['updated'] );
		$this->assertSame( 2, $counts['profiles_created'] );
		$this->assertSame( 0, $this->profile_count() );
		foreach ( $this->posts as $p ) {
			$this->assertSame( '', get_post_meta( $p, Authors::META, true ) );
		}
	}

	public function test_verify_is_clean_after_import_and_catches_a_difference(): void {
		( new Importer() )->import();
		$ok = ( new Importer() )->verify();
		$this->assertSame( 3, $ok['checked'] );
		$this->assertSame( array(), $ok['differences'] );

		$refs = get_post_meta( $this->posts[2], Authors::META, true );
		update_post_meta( $this->posts[2], Authors::META, array_reverse( $refs ) );
		$bad = ( new Importer() )->verify();
		$this->assertCount( 1, $bad['differences'] );
		$this->assertSame( $this->posts[2], $bad['differences'][0]['post_id'] );
		$this->assertSame( array( 'Ann Admin', 'Guest Three', 'Guest One' ), $bad['differences'][0]['expected'] );
	}

	public function test_import_does_not_touch_post_author_or_co_authors_plus_data(): void {
		$author = (int) get_post( $this->posts[1] )->post_author;
		( new Importer() )->import();
		$this->assertSame( $author, (int) get_post( $this->posts[1] )->post_author );
		$this->assertCount( 3, wp_get_object_terms( $this->posts[2], 'author' ) );
	}
}
