<?php

declare(strict_types=1);

/**
 * Database migration class
 *
 * Calendar categories: reusable, colour-coded labels for the club
 * calendar (admin + ICS). The calendar event references its category;
 * seeding sets up the first five the club asked for.
 */

use \Skeleton\Database\Database;

class Migration_20260917_140500_calendar_categories extends \Skeleton\Database\Migration {

	/**
	 * Category seeds (name => hex colour)
	 *
	 * @var array $categories
	 */
	const CATEGORY_SEEDS = [
		'Réservation' => '#4a90d9',
		'Rallye' => '#f0ba4a',
		'Réunion' => '#e46a8f',
		'Sortie' => '#7a5cc9',
		'Divers' => '#67c29c',
	];

	/**
	 * Migrate up
	 *
	 * @access public
	 */
	public function up(): void {
		$db = Database::get();

		$db->query('
			CREATE TABLE IF NOT EXISTS `calendar_category` (
				`id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
				`uuid` varchar(36) NULL,
				`name` varchar(128) NOT NULL,
				`color` varchar(16) NOT NULL DEFAULT \'#9ab03e\',
				`sort_order` int NOT NULL DEFAULT \'0\',
				`created` datetime NOT NULL,
				`updated` datetime NULL,
				`archived` datetime NULL,
				UNIQUE KEY `name` (`name`)
			);
		');

		if ($this->has_column('calendar_event', 'calendar_category_id') === false) {
			// Signed int: aligns with `calendar_category.id` (int primary key).
			$db->query('ALTER TABLE `calendar_event` ADD `calendar_category_id` int NULL;');
		} else {
			$db->query('ALTER TABLE `calendar_event` MODIFY `calendar_category_id` int NULL;');
		}

		$fk = $db->get_one("
			SELECT COUNT(*) FROM information_schema.key_column_usage
			WHERE referenced_table_name = 'calendar_category'
			AND table_schema = DATABASE()
			AND table_name = 'calendar_event'
		");
		if ((int)$fk === 0) {
			$db->query('ALTER TABLE `calendar_event` ADD CONSTRAINT `fk_calendar_event_category` FOREIGN KEY (`calendar_category_id`) REFERENCES `calendar_category` (`id`);');
		}

		$this->seed_categories();
	}

	/**
	 * Seed the default categories once
	 *
	 * @access private
	 */
	private function seed_categories(): void {
		$sort_order = 0;

		// The category was originally named 'Entraînement'; rename any row
		// created by an earlier run so a re-run converges instead of duplicating.
		Database::get()->query('UPDATE `calendar_category` SET `name` = ? WHERE `name` = ?', [ 'Réservation', 'Entraînement' ]);

		foreach (self::CATEGORY_SEEDS as $name => $color) {
			$existing = Database::get()->get_one('SELECT COUNT(*) FROM calendar_category WHERE name = ?', [ $name ]);
			if ((int)$existing > 0) {
				continue;
			}

			Database::get()->query('
				INSERT INTO `calendar_category` (`name`, `color`, `sort_order`, `created`)
				VALUES (?, ?, ?, NOW())
			', [ $name, $color, $sort_order ]);

			$sort_order++;
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
		$count = Database::get()->get_one(
			'SELECT COUNT(*) FROM information_schema.columns
			WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
			[ $table, $column ]
		);

		return (int)$count > 0;
	}

	/**
	 * Migrate down
	 *
	 * @access public
	 */
	public function down(): void {
		$db = Database::get();

		$db->query('DELETE FROM `calendar_category` WHERE name IN (\'Réservation\', \'Rallye\', \'Réunions\', \'Sortie\', \'Divers\')');
		$db->query('ALTER TABLE `calendar_event` DROP `calendar_category_id`;');
		$db->query('DROP TABLE IF EXISTS `calendar_category`;');
	}
}
