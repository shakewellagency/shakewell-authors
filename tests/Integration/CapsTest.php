<?php
/**
 * Optional edit rights for listed authors.
 *
 * @package Shakewell\Authors
 */

namespace Shakewell\Authors\Tests;

use Shakewell\Authors\Admin;
use Shakewell\Authors\Authors;
use Shakewell\Authors\Caps;
use Shakewell\Authors\Plugin;
use WP_UnitTestCase;

final class CapsTest extends WP_UnitTestCase {

	/** @var int Post written by $owner. */
	private int $post;
	private int $owner;
	private int $co;
	private int $stranger;

	public function set_up(): void {
		parent::set_up();
		Plugin::register_meta();
		$this->owner    = self::factory()->user->create( array( 'role' => 'editor' ) );
		$this->co       = self::factory()->user->create( array( 'role' => 'author' ) );
		$this->stranger = self::factory()->user->create( array( 'role' => 'author' ) );
		$this->post     = self::factory()->post->create( array( 'post_author' => $this->owner, 'post_status' => 'draft' ) );
		update_post_meta( $this->post, Authors::META, array( "user:{$this->owner}", "user:{$this->co}" ) );
	}

	public function tear_down(): void {
		$_POST = array();
		parent::tear_down();
	}

	private function on(): void {
		add_filter( 'shakewell_authors_edit_rights', '__return_true' );
	}

	public function test_off_by_default_a_listed_author_has_no_extra_rights(): void {
		$this->assertFalse( user_can( $this->co, 'edit_post', $this->post ) );
	}

	public function test_a_listed_author_can_edit_and_delete_but_a_stranger_cannot(): void {
		$this->on();
		$this->assertTrue( user_can( $this->co, 'edit_post', $this->post ) );
		$this->assertTrue( user_can( $this->co, 'delete_post', $this->post ) );
		$this->assertFalse( user_can( $this->stranger, 'edit_post', $this->post ) );
	}

	public function test_the_role_still_decides_for_published_posts(): void {
		$this->on();
		wp_update_post( array( 'ID' => $this->post, 'post_status' => 'publish' ) );
		// An author role may edit its own published posts, so a listed author may too.
		$this->assertTrue( user_can( $this->co, 'edit_post', $this->post ) );
		// A contributor may not edit published posts at all, listed or not.
		$contributor = self::factory()->user->create( array( 'role' => 'contributor' ) );
		update_post_meta( $this->post, Authors::META, array( "user:$contributor" ) );
		$this->assertFalse( user_can( $contributor, 'edit_post', $this->post ) );
	}

	public function test_only_types_with_the_box_count(): void {
		$this->on();
		$page = self::factory()->post->create( array( 'post_type' => 'page', 'post_author' => $this->owner, 'post_status' => 'draft' ) );
		update_post_meta( $page, Authors::META, array( "user:{$this->co}" ) );
		get_userdata( $this->co )->add_cap( 'edit_pages' );
		$this->assertFalse( user_can( $this->co, 'edit_post', $page ) );
		add_filter( 'shakewell_authors_post_types', static fn() => array( 'post', 'page' ) );
		$this->assertTrue( user_can( $this->co, 'edit_page', $page ) );
	}

	public function test_a_listed_author_cannot_change_the_list(): void {
		$this->on();
		$this->assertFalse( Caps::can_set( $this->post, $this->co ) );
		$this->assertTrue( Caps::can_set( $this->post, $this->owner ) );
		wp_set_current_user( $this->co );
		$_POST = array(
			'shakewell_authors_nonce' => wp_create_nonce( 'shakewell_authors_save' ),
			'shakewell_authors'       => array( "user:{$this->stranger}" ),
		);
		Admin::save( $this->post );
		$this->assertSame( array( "user:{$this->owner}", "user:{$this->co}" ), get_post_meta( $this->post, Authors::META, true ) );
	}

	public function test_an_author_role_owner_cannot_set_authors_unless_a_site_relaxes_it(): void {
		$this->on();
		$mine = self::factory()->post->create( array( 'post_author' => $this->co ) );
		$this->assertFalse( Caps::can_set( $mine, $this->co ) );
		add_filter( 'shakewell_authors_can_set', static fn( $can, $id, $uid ) => $can || (int) get_post_field( 'post_author', $id ) === $uid, 10, 3 );
		$this->assertTrue( Caps::can_set( $mine, $this->co ) );
		$this->assertFalse( Caps::can_set( $this->post, $this->co ) );
	}

	public function test_the_box_is_read_only_for_a_user_who_cannot_set_authors(): void {
		$this->on();
		wp_set_current_user( $this->co );
		ob_start();
		Admin::render( get_post( $this->post ) );
		$html = ob_get_clean();
		$this->assertStringNotContainsString( 'shakewell-authors-search', $html );
		$this->assertStringNotContainsString( 'name="shakewell_authors[]"', $html );
	}

	public function test_classic_editor_save_is_allowed_once_for_the_verified_post(): void {
		$this->on();
		set_current_screen( 'edit-post' );
		wp_set_current_user( $this->co );
		$_POST = array( 'post_ID' => $this->post, 'post_author' => $this->owner );
		do_action( 'check_admin_referer', 'update-post_' . $this->post, 1 );
		// Core's first "edit others" check after the nonce, made while reading the form.
		$caps = apply_filters( 'map_meta_cap', array( 'edit_others_posts' ), 'edit_others_posts', $this->co, array() );
		$this->assertSame( array( 'edit_posts' ), $caps );
		// A second check sees the user's own capability again.
		$caps = apply_filters( 'map_meta_cap', array( 'edit_others_posts' ), 'edit_others_posts', $this->co, array() );
		$this->assertSame( array( 'edit_others_posts' ), $caps );
	}

	public function test_classic_editor_save_is_refused_when_the_post_author_is_changed(): void {
		$this->on();
		set_current_screen( 'edit-post' );
		wp_set_current_user( $this->co );
		$_POST = array( 'post_ID' => $this->post, 'post_author' => $this->stranger );
		do_action( 'check_admin_referer', 'update-post_' . $this->post, 1 );
		$caps = apply_filters( 'map_meta_cap', array( 'edit_others_posts' ), 'edit_others_posts', $this->co, array() );
		$this->assertSame( array( 'edit_others_posts' ), $caps );
	}
}
