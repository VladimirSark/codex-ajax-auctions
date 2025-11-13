<?php
/**
 * Handles plugin activation and deactivation routines.
 *
 * @package CodexAjaxAuctions
 */

namespace Codfaa\Setup;

defined( 'ABSPATH' ) || exit;

/**
 * Installer class for database setup and teardown tasks.
 */
class Installer {

	/**
	 * Activation callback.
	 */
	public static function activate() {
		self::maybe_create_tables();
	}

	/**
	 * Deactivation callback.
	 */
	public static function deactivate() {
		// Reserved for future cleanup tasks.
	}

	/**
	 * Create required custom database tables as needed.
	 */
	private static function maybe_create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$tables = array(
			"CREATE TABLE {$wpdb->prefix}codfaa_user_credits (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				user_id BIGINT UNSIGNED NOT NULL,
				balance BIGINT NOT NULL DEFAULT 0,
				reserved BIGINT NOT NULL DEFAULT 0,
				updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				UNIQUE KEY user_id (user_id)
			) {$charset_collate};",
			"CREATE TABLE {$wpdb->prefix}codfaa_auction_participants (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				auction_id BIGINT UNSIGNED NOT NULL,
				user_id BIGINT UNSIGNED NOT NULL,
				order_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				registered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				total_reserved BIGINT NOT NULL DEFAULT 0,
				status VARCHAR(20) NOT NULL DEFAULT 'active',
				removed_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
				removed_at DATETIME NULL,
				removed_reason TEXT NULL,
				PRIMARY KEY (id),
				UNIQUE KEY auction_user (auction_id, user_id)
			) {$charset_collate};",
			"CREATE TABLE {$wpdb->prefix}codfaa_auction_bids (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				auction_id BIGINT UNSIGNED NOT NULL,
				user_id BIGINT UNSIGNED NOT NULL,
				bid_number BIGINT UNSIGNED NOT NULL,
				reserved_amount BIGINT NOT NULL DEFAULT 0,
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY auction_idx (auction_id),
				KEY auction_user_idx (auction_id, user_id)
			) {$charset_collate};",
			"CREATE TABLE {$wpdb->prefix}codfaa_auction_winners (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				auction_id BIGINT UNSIGNED NOT NULL,
				user_id BIGINT UNSIGNED NOT NULL,
				total_bids BIGINT UNSIGNED NOT NULL DEFAULT 0,
				credits_used BIGINT NOT NULL DEFAULT 0,
				recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				UNIQUE KEY auction_id (auction_id)
			) {$charset_collate};",
		);

		foreach ( $tables as $sql ) {
			dbDelta( $sql );
		}
	}

}
