<?php
/**
 * WP-CLI commands.
 *
 * @package Shakewell\Authors
 */

namespace Shakewell\Authors;

defined( 'ABSPATH' ) || exit;

/**
 * Moves author data over from Co-Authors Plus.
 */
final class Cli {

	/**
	 * Imports Co-Authors Plus authors: creates profiles for guest authors and writes each post's ordered list.
	 *
	 * Safe to run again: existing profiles are reused and each post's list is rewritten from Co-Authors Plus.
	 * Run it before switching over, not after editors have started using this plugin's box.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Print the counts without writing anything.
	 *
	 * [--batch=<n>]
	 * : Posts read per query.
	 * ---
	 * default: 200
	 * ---
	 *
	 * @subcommand import-coauthors
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Flags.
	 */
	public function import_coauthors( $args, $assoc_args ): void {
		unset( $args );
		$dry    = isset( $assoc_args['dry-run'] );
		$counts = ( new Importer( (int) ( $assoc_args['batch'] ?? 200 ) ) )->import( $dry );
		foreach ( $counts as $label => $count ) {
			\WP_CLI::log( str_replace( '_', ' ', $label ) . ': ' . $count );
		}
		\WP_CLI::success( $dry ? 'Dry run finished, nothing was written.' : 'Import finished.' );
	}

	/**
	 * Checks every post's author names, in order, against Co-Authors Plus. Exits with an error on any difference.
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Flags.
	 */
	public function verify( $args, $assoc_args ): void {
		unset( $args, $assoc_args );
		$result = ( new Importer() )->verify();
		foreach ( $result['differences'] as $diff ) {
			\WP_CLI::log( sprintf( 'Post %d: expected [%s] got [%s]', $diff['post_id'], implode( ' | ', $diff['expected'] ), implode( ' | ', $diff['actual'] ) ) );
		}
		if ( $result['differences'] ) {
			\WP_CLI::error( sprintf( '%d of %d posts differ.', count( $result['differences'] ), $result['checked'] ) );
		}
		\WP_CLI::success( sprintf( 'All %d posts match.', $result['checked'] ) );
	}
}
