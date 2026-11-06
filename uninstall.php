<?php
/**
 * Keeps the data on uninstall unless wp-config.php sets SHAKEWELL_AUTHORS_DELETE_DATA to true.
 *
 * @package Shakewell\Authors
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( ! defined( 'SHAKEWELL_AUTHORS_DELETE_DATA' ) || true !== SHAKEWELL_AUTHORS_DELETE_DATA ) {
	return;
}

delete_post_meta_by_key( '_shakewell_authors' );
$shakewell_authors_profiles = get_posts(
	array(
		'post_type'      => 'shakewell_author',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);
foreach ( $shakewell_authors_profiles as $shakewell_authors_id ) {
	wp_delete_post( $shakewell_authors_id, true );
}
