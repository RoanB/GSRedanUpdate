<?php

declare(strict_types=1);

/**
 * Database migration class
 *
 * Calendar category rename: the Entraînement seed became Réservation (same
 * colour `#4a90d9`). The seed constant in 20260917_140500_calendar_categories
 * already carries the new name for fresh installs; this migration converges
 * databases where the old row was seeded before the rename.
 */

use \Skeleton\Database\Database;

class Migration_20260918_100000_calendar_category_rename extends \Skeleton\Database\Migration {

	/**
	 * Migrate up
	 *
	 * @access public
	 */
	public function up(): void {
		$db = Database::get();

		$db->query('UPDATE `calendar_category` SET `name` = ? WHERE `name` = ?', [ 'Réservation', 'Entraînement' ]);
	}

	/**
	 * Migrate down
	 *
	 * @access public
	 */
	public function down(): void {
		$db = Database::get();

		$db->query('UPDATE `calendar_category` SET `name` = ? WHERE `name` = ?', [ 'Entraînement', 'Réservation' ]);
	}
}