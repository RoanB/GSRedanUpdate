<?php

declare(strict_types=1);

/**
 * Database migration class
 *
 * The rally settings become datetimes. The previous seed stored plain
 * dates (2027-09-10 / 2027-09-12); the countdown now targets the exact
 * datetime the club sets instead of a fixed 09:00. The start seeds at
 * 12:00 — the midday target the site had hardcoded — and the event is
 * assumed to end at 18:00 on the last day until the club adjusts it.
 */

use \Skeleton\Database\Database;

class Migration_20261019_140000_Rally_DateTimes extends \Skeleton\Database\Migration {

	/**
	 * The date-only seeds of 20261019_130000 and their datetime form
	 *
	 * @access private
	 * @return array
	 */
	private function get_conversions(): array {
		return [
			'rally_start_date' => [ '2027-09-10', '2027-09-10 12:00:00' ],
			'rally_end_date' => [ '2027-09-12', '2027-09-12 18:00:00' ],
		];
	}

	/**
	 * Migrate up
	 *
	 * @access public
	 */
	public function up(): void {
		$db = Database::get();

		// Only the untouched date-only seeds are converted; a club that
		// already entered a datetime keeps theirs.
		foreach (self::get_conversions() as $name => [ $date_only, $datetime ]) {
			$db->query(
				'UPDATE `setting` SET `value` = ?, `updated` = NOW()
				WHERE `name` = ? AND `archived` IS NULL AND `value` = ?',
				[ $datetime, $name, $date_only ]
			);
		}
	}

	/**
	 * Migrate down
	 *
	 * Restores the date-only seed values, but only for the rows this
	 * migration set (same `updated` protection both ways).
	 *
	 * @access public
	 */
	public function down(): void {
		$db = Database::get();

		foreach (self::get_conversions() as $name => [ $date_only, $datetime ]) {
			$db->query(
				'UPDATE `setting` SET `value` = ?, `updated` = NOW()
				WHERE `name` = ? AND `archived` IS NULL AND `value` = ?',
				[ $date_only, $name, $datetime ]
			);
		}
	}
}
