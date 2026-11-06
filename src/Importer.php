<?php
/**
 * Reads Co-Authors Plus data straight from the database and writes this plugin's author lists.
 *
 * Co-Authors Plus stores a post's authors as terms of the taxonomy "author" (term_order is the author
 * order). A term's name is the author's login and its slug is "cap-" plus the nicename. A guest author is
 * a post of type "guest-author" whose post_name equals that term slug, with "cap-*" post meta. A term with
 * no guest author belongs to a WordPress user. Reading with SQL means this works with Co-Authors Plus
 * deactivated and its taxonomy unregistered.
 *
 * @package Shakewell\Authors
 */

namespace Shakewell\Authors;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- One-off migration over a taxonomy that may not be registered; results are read once.

/**
 * Import and verify. Methods return plain arrays so the CLI only has to print them.
 */
final class Importer {

	private const GUEST    = 'guest-author';
	private const TAXONOMY = 'author';
	private const SRC_META = '_shakewell_authors_source';

	/**
	 * Resolved authors by term slug.
	 *
	 * @var array<string,array|null>
	 */
	private array $cache = array();

	/**
	 * Posts per batch.
	 *
	 * @var int
	 */
	private int $batch;

	/**
	 * Creates an importer.
	 *
	 * @param int $batch Posts per batch.
	 */
	public function __construct( int $batch = 200 ) {
		$this->batch = max( 1, $batch );
	}

	/**
	 * Yields batches of post id => ordered list of author terms (slug, name).
	 *
	 * @return \Generator<int,array<int,array<int,array{slug:string,name:string}>>>
	 */
	private function batches(): \Generator {
		global $wpdb;
		$after = 0;
		while ( true ) {
			$ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT tr.object_id FROM {$wpdb->term_relationships} tr
					JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = %s
					JOIN {$wpdb->posts} p ON p.ID = tr.object_id AND p.post_type <> 'revision' AND p.post_status <> 'auto-draft'
					WHERE tr.object_id > %d ORDER BY tr.object_id LIMIT %d",
					self::TAXONOMY,
					$after,
					$this->batch
				)
			);
			if ( ! $ids ) {
				return;
			}
			// The batch is the next ids that have author terms, so this id range holds exactly their terms.
			$rows  = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT tr.object_id, t.slug, t.name FROM {$wpdb->term_relationships} tr
					JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = %s
					JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
					WHERE tr.object_id BETWEEN %d AND %d ORDER BY tr.object_id, tr.term_order, t.term_id",
					self::TAXONOMY,
					(int) $ids[0],
					(int) end( $ids )
				)
			);
			$batch = array();
			foreach ( $rows as $row ) {
				$batch[ (int) $row->object_id ][] = array(
					'slug' => $row->slug,
					'name' => $row->name,
				);
			}
			yield $batch;
			$after = (int) end( $ids );
		}
	}

	/**
	 * Works out who an author term stands for.
	 *
	 * @param string $slug Term slug.
	 * @param string $name Term name (the author's login).
	 * @return array{kind:string,display_name:string,user_id?:int,guest_id?:int,bio?:string,thumb?:int}|null Null when unresolvable.
	 */
	private function resolve( string $slug, string $name ): ?array {
		if ( array_key_exists( $slug, $this->cache ) ) {
			return $this->cache[ $slug ];
		}
		global $wpdb;
		$found = null;
		$guest = $wpdb->get_var(
			$wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_name = %s AND post_status <> 'trash' ORDER BY ID LIMIT 1", self::GUEST, $slug )
		);
		if ( $guest ) {
			$guest_id = (int) $guest;
			$display  = (string) get_post_meta( $guest_id, 'cap-display_name', true );
			$found    = array(
				'kind'         => 'guest',
				'guest_id'     => $guest_id,
				'display_name' => '' !== $display ? $display : get_the_title( $guest_id ),
				'bio'          => (string) get_post_meta( $guest_id, 'cap-description', true ),
				'thumb'        => (int) get_post_meta( $guest_id, '_thumbnail_id', true ),
			);
			$login    = (string) get_post_meta( $guest_id, 'cap-linked_account', true );
			$linked   = '' !== $login ? get_user_by( 'login', $login ) : false;
			// A guest author mapped to a user becomes that user, but only when the name stays the same.
			if ( $linked && $linked->display_name === $found['display_name'] ) {
				$found = array(
					'kind'         => 'user',
					'user_id'      => $linked->ID,
					'display_name' => $linked->display_name,
				);
			}
		} else {
			$nicename = 0 === strpos( $slug, 'cap-' ) ? substr( $slug, 4 ) : $slug;
			$user     = get_user_by( 'slug', $nicename );
			$user     = $user ? $user : get_user_by( 'slug', $slug );
			$user     = $user ? $user : get_user_by( 'login', $name );
			if ( $user ) {
				$found = array(
					'kind'         => 'user',
					'user_id'      => $user->ID,
					'display_name' => $user->display_name,
				);
			}
		}
		$this->cache[ $slug ] = $found;
		return $found;
	}

	/**
	 * Finds or creates the profile for a guest author, keyed by its Co-Authors Plus post name.
	 *
	 * @param string $slug  Term slug (the guest author's post_name).
	 * @param array  $guest Resolved guest author.
	 * @param bool   $dry   When true nothing is written and 0 is returned for a missing profile.
	 * @param bool   $made  Set to true when a profile was (or would be) created.
	 */
	private function profile_id( string $slug, array $guest, bool $dry, bool &$made ): int {
		$existing = get_posts(
			array(
				'post_type'      => Authors::PROFILE,
				'post_status'    => 'any',
				'meta_key'       => self::SRC_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $slug, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		if ( $existing ) {
			return (int) $existing[0];
		}
		$made = true;
		if ( $dry ) {
			return 0;
		}
		$id = wp_insert_post(
			array(
				'post_type'    => Authors::PROFILE,
				'post_status'  => 'publish',
				'post_title'   => wp_slash( $guest['display_name'] ),
				'post_excerpt' => wp_slash( $guest['bio'] ),
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return 0;
		}
		update_post_meta( $id, self::SRC_META, wp_slash( $slug ) );
		if ( $guest['thumb'] && 'attachment' === get_post_type( $guest['thumb'] ) ) {
			set_post_thumbnail( $id, $guest['thumb'] );
		}
		return (int) $id;
	}

	/**
	 * Writes the ordered author list on every post that has Co-Authors Plus terms.
	 *
	 * @param bool $dry Report what would change without writing.
	 * @return array<string,int>
	 */
	public function import( bool $dry = false ): array {
		$n             = array_fill_keys( array( 'posts', 'updated', 'unchanged', 'profiles_created', 'unresolved_terms' ), 0 );
		$seen_profiles = array();
		foreach ( $this->batches() as $batch ) {
			foreach ( $batch as $post_id => $terms ) {
				++$n['posts'];
				$refs = array();
				foreach ( $terms as $term ) {
					$who = $this->resolve( $term['slug'], $term['name'] );
					if ( ! $who ) {
						++$n['unresolved_terms'];
						continue;
					}
					if ( 'user' === $who['kind'] ) {
						$refs[] = 'user:' . $who['user_id'];
						continue;
					}
					$made = false;
					$pid  = $this->profile_id( $term['slug'], $who, $dry, $made );
					if ( $made && ! isset( $seen_profiles[ $term['slug'] ] ) ) {
						++$n['profiles_created'];
					}
					$seen_profiles[ $term['slug'] ] = true;
					if ( 0 === $pid && ! $dry ) {
						++$n['unresolved_terms'];
						continue;
					}
					// A profile a dry run would create has no id yet; 0 never equals a stored reference.
					$refs[] = 'profile:' . $pid;
				}
				if ( ! $refs || get_post_meta( $post_id, Authors::META, true ) === $refs ) {
					++$n['unchanged'];
					continue;
				}
				++$n['updated'];
				if ( ! $dry ) {
					update_post_meta( $post_id, Authors::META, $refs );
				}
			}
		}
		return $n;
	}

	/**
	 * Compares, for every post with Co-Authors Plus terms, its author names in order with this plugin's.
	 *
	 * @return array{checked:int,differences:array<int,array{post_id:int,expected:string[],actual:string[]}>}
	 */
	public function verify(): array {
		$out = array(
			'checked'     => 0,
			'differences' => array(),
		);
		foreach ( $this->batches() as $batch ) {
			foreach ( $batch as $post_id => $terms ) {
				$expected = array();
				foreach ( $terms as $term ) {
					$who = $this->resolve( $term['slug'], $term['name'] );
					if ( $who ) {
						$expected[] = $who['display_name'];
					}
				}
				if ( ! $expected ) {
					continue;
				}
				++$out['checked'];
				$actual = wp_list_pluck( Authors::get( $post_id ), 'display_name' );
				if ( $expected !== $actual ) {
					$out['differences'][] = array(
						'post_id'  => $post_id,
						'expected' => $expected,
						'actual'   => $actual,
					);
				}
			}
		}
		return $out;
	}
}
