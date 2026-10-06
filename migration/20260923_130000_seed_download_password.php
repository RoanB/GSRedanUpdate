<?php

declare(strict_types=1);

/**
 * Database migration class
 *
 * Seeds the plaintext mirror of the members password (`download_password`).
 * The hash alone (`download_password_hash`) cannot be read back, and the
 * admin share links embed the password in the URL — so the plaintext has to
 * live somewhere to build them (spec/07 "Shared file link"). Only mirrors
 * the value when the seed hash still matches `OuEstLaCorde`; a hash that was
 * changed in the admin does not get a mirror here (the share link stays
 * hidden until the admin re-submits the password, which writes both).
 */

use \Skeleton\Database\Database;

class Migration_20260923_130000_Seed_Download_Password extends \Skeleton\Database\Migration {

	/**
	 * Migrate up
	 *
	 * @access public
	 */
	public function up(): void {
		$db = Database::get();

		$existing = $db->get_one(
			"SELECT `value` FROM `setting` WHERE `name` = 'download_password'"
		);

		if ($existing !== null) {
			return;
		}

		$hash = $db->get_one(
			"SELECT `value` FROM `setting` WHERE `name` = 'download_password_hash'"
		);

		if ($hash === null || password_verify('OuEstLaCorde', (string)$hash) === false) {
			return;
		}

		$db->query("
			INSERT INTO `setting` (`name`, `value`, `created`)
			VALUES ('download_password', 'OuEstLaCorde', NOW());
		");
	}

	/**
	 * Migrate down
	 *
	 * @access public
	 */
	public function down(): void {
		$db = Database::get();

		$db->query('DELETE FROM `setting` WHERE `name` = ?;', [ 'download_password' ]);
	}
}