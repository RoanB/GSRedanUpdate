<?php

declare(strict_types=1);

/**
 * Database migration class
 *
 * Adds the password reset token table backing the admin password reset.
 *
 * The raw token never leaves the mail client: `User::issue_password_reset_token()`
 * puts a bin2hex(random_bytes(32)) value in the reset link and only its
 * SHA-256 hash is stored here. A row is spendable once (used) and expires
 * after Password_Reset_Token::VALIDITY_SECONDS.
 *
 * Table name is `user_password_reset_token` while the model class is
 * Password_Reset_Token (the autoloader derives `password_reset_token`), so
 * the model overrides the table through $class_configuration.
 */

use \Skeleton\Database\Database;

class Migration_20260928_100000_Password_Reset_Token extends \Skeleton\Database\Migration {

	/**
	 * Migrate up
	 *
	 * @access public
	 */
	public function up(): void {
		$db = Database::get();

		$db->query("
			CREATE TABLE `user_password_reset_token` (
				`id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
				`uuid` varchar(36) NULL,
				`user_id` int NOT NULL,
				`token` varchar(64) NOT NULL,
				`expires_at` datetime NOT NULL,
				`used` tinyint NOT NULL DEFAULT '0',
				`created` datetime NOT NULL,
				`updated` datetime NULL,
				`archived` datetime NULL,
				UNIQUE KEY `token` (`token`),
				FOREIGN KEY (`user_id`) REFERENCES `user` (`id`)
			);
		");
	}

	/**
	 * Migrate down
	 *
	 * Drops the token table. Outstanding reset links stop working, which is
	 * the point: a rollback should not leave usable credentials behind.
	 *
	 * @access public
	 */
	public function down(): void {
		$db = Database::get();

		$db->query('DROP TABLE IF EXISTS `user_password_reset_token`;');
	}
}
