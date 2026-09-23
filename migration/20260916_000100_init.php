<?php

declare(strict_types=1);

/**
 * Database migration class
 *
 * Creates the complete GS Redan schema in a single migration. The
 * skeleton-core user table (no uuid/admin/verified, different collation) is
 * incompatible with this project's User model, so it is dropped and
 * recreated. The `file` table gets the `updated` column compound packages
 * expect (mirrors vvsjongeren).
 *
 * The old 20251017_170550_init.php was a broken copy-paste and is neutralized
 * to a no-op; this is the real schema.
 */

use \Skeleton\Database\Database;

class Migration_20260916_000100_Init extends \Skeleton\Database\Migration {

	/**
	 * Migrate up
	 *
	 * @access public
	 */
	public function up(): void {
		$db = Database::get();

		$db->query('DROP TABLE IF EXISTS `user`;');

		$db->query("
			CREATE TABLE `user` (
				`id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
				`uuid` varchar(36) NULL,
				`language_id` int NULL,
				`email` varchar(128) NOT NULL,
				`password` varchar(255) NULL,
				`firstname` varchar(64) NULL,
				`lastname` varchar(64) NULL,
				`admin` tinyint NOT NULL DEFAULT '0',
				`verified` tinyint NOT NULL DEFAULT '0',
				`created` datetime NOT NULL,
				`updated` datetime NULL,
				`archived` datetime NULL,
				UNIQUE KEY `email` (`email`),
				FOREIGN KEY (`language_id`) REFERENCES `language` (`id`)
			);
		");

		$db->query("
			ALTER TABLE `file`
			ADD `updated` datetime NULL;
		");

		$db->query("
			CREATE TABLE `block` (
				`id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
				`uuid` varchar(36) NULL,
				`type` varchar(32) NOT NULL,
				`anchor` varchar(64) NOT NULL DEFAULT '',
				`sort_order` int NOT NULL DEFAULT '0',
				`visible` tinyint NOT NULL DEFAULT '1',
				`created` datetime NOT NULL,
				`updated` datetime NULL,
				`archived` datetime NULL
			);
		");

		$db->query("
			CREATE TABLE `block_translation` (
				`id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
				`uuid` varchar(36) NULL,
				`block_id` int NOT NULL,
				`language_id` int NOT NULL,
				`title` varchar(255) NOT NULL DEFAULT '',
				`body` text NULL,
				`created` datetime NOT NULL,
				`updated` datetime NULL,
				`archived` datetime NULL,
				FOREIGN KEY (`block_id`) REFERENCES `block` (`id`),
				FOREIGN KEY (`language_id`) REFERENCES `language` (`id`),
				UNIQUE KEY `block_language` (`block_id`, `language_id`)
			);
		");

		$db->query("
			CREATE TABLE `setting` (
				`id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
				`uuid` varchar(36) NULL,
				`name` varchar(128) NOT NULL,
				`value` text NULL,
				`created` datetime NOT NULL,
				`updated` datetime NULL,
				`archived` datetime NULL,
				UNIQUE KEY `name` (`name`)
			);
		");

		$db->query("
			CREATE TABLE `download_file` (
				`id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
				`uuid` varchar(36) NULL,
				`file_id` int unsigned NOT NULL,
				`name` varchar(128) NOT NULL,
				`sort_order` int NOT NULL DEFAULT '0',
				`visible` tinyint NOT NULL DEFAULT '1',
				`created` datetime NOT NULL,
				`updated` datetime NULL,
				`archived` datetime NULL,
				FOREIGN KEY (`file_id`) REFERENCES `file` (`id`)
			);
		");
	}

	/**
	 * Migrate down
	 *
	 * Drops the new tables in reverse order and restores the skeleton-core
	 * user table shape so a rollback leaves the app in its pre-migration
	 * state.
	 *
	 * @access public
	 */
	public function down(): void {
		$db = Database::get();

		$db->query('DROP TABLE IF EXISTS `download_file`;');
		$db->query('DROP TABLE IF EXISTS `setting`;');
		$db->query('DROP TABLE IF EXISTS `block_translation`;');
		$db->query('DROP TABLE IF EXISTS `block`;');

		$db->query("
			ALTER TABLE `file`
			DROP `updated`;
		");

		$db->query("
			CREATE TABLE IF NOT EXISTS `user` (
				`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
				`email` varchar(128) NOT NULL,
				`password` varchar(64) NULL,
				`firstname` varchar(64) NULL,
				`lastname` varchar(64) NULL,
				`created` datetime NOT NULL,
				PRIMARY KEY (`id`)
			);
		");
	}
}