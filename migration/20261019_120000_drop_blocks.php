<?php

declare(strict_types=1);

/**
 * Database migration class
 *
 * Drops the block tables. The public site no longer renders database blocks
 * (spec/02: the pages are the static ../gsredanupdate design, translated via
 * the po catalogs), and the block admin screens were removed with them — so
 * the tables and their data have no reader left.
 *
 * The down() recreates the exact schema the tables had at drop time (verified
 * against SHOW CREATE TABLE on the live database), so migrate:down stays
 * possible. The dropped DATA is not recoverable by design; the seed
 * migrations (20260916_000400_seed_blocks and friends) can rebuild it if the
 * whole chain is replayed.
 */

use \Skeleton\Database\Database;

class Migration_20261019_120000_Drop_Blocks extends \Skeleton\Database\Migration {

	/**
	 * Migrate up
	 *
	 * @access public
	 */
	public function up(): void {
		$db = Database::get();

		// Children first: block_translation and block_picture carry the
		// foreign keys to block.
		$db->query('DROP TABLE IF EXISTS `block_picture`;');
		$db->query('DROP TABLE IF EXISTS `block_translation`;');
		$db->query('DROP TABLE IF EXISTS `block`;');
	}

	/**
	 * Migrate down
	 *
	 * Recreates the tables exactly as they were when up() dropped them
	 * (SHOW CREATE TABLE on the live database, 2026-10-19). The drop
	 * destroyed the rows; a down() gives empty tables with the same shape.
	 *
	 * @access public
	 */
	public function down(): void {
		$db = Database::get();

		$db->query("CREATE TABLE `block` (
		  `id` int NOT NULL AUTO_INCREMENT,
		  `uuid` varchar(36) DEFAULT NULL,
		  `type` varchar(32) NOT NULL,
		  `anchor` varchar(64) NOT NULL DEFAULT '',
		  `sort_order` int NOT NULL DEFAULT '0',
		  `visible` tinyint NOT NULL DEFAULT '1',
		  `created` datetime NOT NULL,
		  `updated` datetime DEFAULT NULL,
		  `archived` datetime DEFAULT NULL,
		  `file_id` int unsigned DEFAULT NULL,
		  `image_side` varchar(8) NOT NULL DEFAULT 'left',
		  `file_id_2` int unsigned DEFAULT NULL,
		  PRIMARY KEY (`id`),
		  KEY `fk_block_file` (`file_id`),
		  KEY `fk_block_file_2` (`file_id_2`),
		  CONSTRAINT `fk_block_file` FOREIGN KEY (`file_id`) REFERENCES `file` (`id`),
		  CONSTRAINT `fk_block_file_2` FOREIGN KEY (`file_id_2`) REFERENCES `file` (`id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci");

		$db->query("CREATE TABLE `block_translation` (
		  `id` int NOT NULL AUTO_INCREMENT,
		  `uuid` varchar(36) DEFAULT NULL,
		  `block_id` int NOT NULL,
		  `language_id` int NOT NULL,
		  `title` varchar(255) NOT NULL DEFAULT '',
		  `body` text,
		  `created` datetime NOT NULL,
		  `updated` datetime DEFAULT NULL,
		  `archived` datetime DEFAULT NULL,
		  PRIMARY KEY (`id`),
		  UNIQUE KEY `block_language` (`block_id`,`language_id`),
		  KEY `language_id` (`language_id`),
		  CONSTRAINT `block_translation_ibfk_1` FOREIGN KEY (`block_id`) REFERENCES `block` (`id`),
		  CONSTRAINT `block_translation_ibfk_2` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci");

		$db->query("CREATE TABLE `block_picture` (
		  `id` int NOT NULL AUTO_INCREMENT,
		  `uuid` varchar(36) DEFAULT NULL,
		  `block_id` int NOT NULL,
		  `file_id` int unsigned NOT NULL,
		  `sort_order` int NOT NULL DEFAULT '0',
		  `visible` tinyint NOT NULL DEFAULT '1',
		  `created` datetime NOT NULL,
		  `updated` datetime DEFAULT NULL,
		  `archived` datetime DEFAULT NULL,
		  PRIMARY KEY (`id`),
		  KEY `block_id` (`block_id`),
		  KEY `file_id` (`file_id`),
		  CONSTRAINT `block_picture_ibfk_1` FOREIGN KEY (`block_id`) REFERENCES `block` (`id`),
		  CONSTRAINT `block_picture_ibfk_2` FOREIGN KEY (`file_id`) REFERENCES `file` (`id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci");
	}
}
