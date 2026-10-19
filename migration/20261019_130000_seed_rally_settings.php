<?php

declare(strict_types=1);

/**
 * Database migration class
 *
 * Seeds the rally settings: the public countdown and the admin's new
 * Rally card read these. The Rally is announced for 10-11-12 September
 * 2027, so the seed matches the values the static site hardcoded — the
 * site does not change on deploy, but the club now controls it.
 *
 * The upsert-guard pattern (like seed_download_password): existing values
 * are never overwritten, so a club that already changed a date in the
 * admin keeps it when the chain replays somewhere.
 */

use \Skeleton\Database\Database;

class Migration_20261019_130000_Seed_Rally_Settings extends \Skeleton\Database\Migration {

	/**
	 * The rally settings and their initial values
	 *
	 * @access private
	 * @return array
	 */
	private function get_seeds(): array {
		return [
			'rally_enabled' => '1',
			'rally_start_date' => '2027-09-10',
			'rally_end_date' => '2027-09-12',
		];
	}

	/**
	 * Migrate up
	 *
	 * @access public
	 */
	public function up(): void {
		$db = Database::get();

		foreach (self::get_seeds() as $name => $value) {
			// Same idiom as the 20260916 seed: an existing value (a club
			// that already turned the rally off) is never overwritten.
			$db->query("
				INSERT INTO `setting` (`name`, `value`, `created`)
				SELECT ?, ?, NOW()
				WHERE NOT EXISTS (SELECT `id` FROM `setting` WHERE `name` = ? AND `archived` IS NULL);
			", [ $name, $value, $name ]);
		}
	}

	/**
	 * Migrate down
	 *
	 * Removes the unmodified seeds (an admin-edited row carries `updated`
	 * and survives).
	 *
	 * @access public
	 */
	public function down(): void {
		$db = Database::get();

		foreach (array_keys(self::get_seeds()) as $name) {
			$db->query('DELETE FROM `setting` WHERE `name` = ? AND `archived` IS NULL AND `updated` IS NULL', [ $name ]);
		}
	}
}
