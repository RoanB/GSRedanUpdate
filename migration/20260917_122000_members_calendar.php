<?php

declare(strict_types=1);

/**
 * Database migration class
 *
 * Members area + calendar:
 * - `download_category` table (the members area files are grouped in
 *   categories), FK `download_file.download_category_id`;
 * - seeds the three categories the club asked for;
 * - `calendar_event` table for ICS/CalDAV served events.
 */

use \Skeleton\Database\Database;

class Migration_20260917_122000_Members_Calendar extends \Skeleton\Database\Migration {

	/**
	 * Migrate up
	 *
	 * @access public
	 */
	public function up(): void {
		$db = Database::get();

		$db->query('
			CREATE TABLE IF NOT EXISTS `download_category` (
				`id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
				`uuid` varchar(36) NULL,
				`name` varchar(128) NOT NULL,
				`sort_order` int NOT NULL DEFAULT \'0\',
				`visible` tinyint NOT NULL DEFAULT \'1\',
				`created` datetime NOT NULL,
				`updated` datetime NULL,
				`archived` datetime NULL,
				UNIQUE KEY `name` (`name`)
			);
		');

		if ($this->has_column('download_file', 'download_category_id') === false) {
			$db->query('ALTER TABLE `download_file` ADD `download_category_id` int NULL;');
		} else {
			// The first attempt ran while the referenced id was unsigned:
			// align the column type with `download_category.id`.
			$db->query('ALTER TABLE `download_file` MODIFY `download_category_id` int NULL;');
		}

		$fk = $db->get_one("
			SELECT COUNT(*) FROM information_schema.key_column_usage
			WHERE referenced_table_name = 'download_category'
			AND table_schema = DATABASE()
			AND table_name = 'download_file'
		");
		if ((int)$fk === 0) {
			$db->query('ALTER TABLE `download_file` ADD CONSTRAINT `fk_download_file_category` FOREIGN KEY (`download_category_id`) REFERENCES `download_category` (`id`);');
		}

		$this->seed_categories();

		$db->query('
			CREATE TABLE IF NOT EXISTS `calendar_event` (
				`id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
				`uuid` varchar(36) NULL,
				`title` varchar(255) NOT NULL,
				`description` text NULL,
				`location` varchar(255) NULL,
				`starts_at` datetime NOT NULL,
				`ends_at` datetime NULL,
				`all_day` tinyint NOT NULL DEFAULT \'0\',
				`visible` tinyint NOT NULL DEFAULT \'1\',
				`created` datetime NOT NULL,
				`updated` datetime NULL,
				`archived` datetime NULL
			);
		');
	}

	/**
	 * Seed the default categories once
	 *
	 * @access private
	 */
	private function seed_categories(): void {
		$categories = [
			[ 'Documents', 0 ],
			[ 'Planning', 1 ],
			[ 'Photos', 2 ],
		];

		foreach ($categories as $row) {
			$existing = Database::get()->get_one('SELECT COUNT(*) FROM download_category WHERE name = ?', [ $row[0] ]);
			if ((int)$existing > 0) {
				continue;
			}

			Database::get()->query('
				INSERT INTO `download_category` (`name`, `sort_order`, `visible`, `created`)
				VALUES (?, ?, ?, NOW())
			', [ $row[0], $row[1], 1 ]);
		}
	}

	/**
	 * Does the given table have this column?
	 *
	 * @access private
	 * @param string $table
	 * @param string $column
	 */
	private function has_column(string $table, string $column): bool {
		return (int)Database::get()->get_one(
			'SELECT COUNT(*) FROM information_schema.columns
			WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
			[ $table, $column ]
		) > 0;
	}

	/**
	 * Migrate down
	 *
	 * @access public
	 */
	public function down(): void {
		$db = Database::get();

		$db->query('DELETE FROM `download_category` WHERE name IN (\'Documents\', \'Planning\', \'Photos\')');
		$db->query('ALTER TABLE `download_file` DROP `download_category_id`;');
		$db->query('DROP TABLE IF EXISTS `calendar_event`;');
		$db->query('DROP TABLE IF EXISTS `download_category`;');
	}
}
