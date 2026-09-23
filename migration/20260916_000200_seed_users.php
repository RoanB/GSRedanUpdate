<?php

declare(strict_types=1);

/**
 * Database migration class
 *
 * Seeds the admin users from migration/data/admin_users.csv (gitignored).
 * CSV layout: email,firstname,lastname,password. Passwords are hashed here
 * with password_hash(); re-running the migration keeps the existing hashes
 * and only re-asserts the admin/verified flags, so password changes made via
 * the admin UI survive a re-migrate.
 *
 * The seed file must not exist in version control; copy it in on a fresh
 * install.
 */

use \Skeleton\Database\Database;

class Migration_20260916_000200_Seed_Users extends \Skeleton\Database\Migration {

	/**
	 * Migrate up
	 *
	 * @access public
	 */
	public function up(): void {
		$db = Database::get();

		$users_path = dirname(__FILE__) . '/data/admin_users.csv';
		if (file_exists($users_path) === false) {
			throw new \Exception(
				'Missing seed file "' . $users_path . '". Copy it into migration/data/ and rerun migrate:up.'
			);
		}

		$user_lines = file($users_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		if ($user_lines === false) {
			throw new \Exception('Could not read seed file "' . $users_path . '".');
		}

		$user_header = str_getcsv(array_shift($user_lines), ',', '"', "\\");

		foreach ($user_lines as $user_line) {
			$user = array_combine($user_header, str_getcsv($user_line, ',', '"', "\\"));

			$password_hash = password_hash($user['password'], PASSWORD_DEFAULT);

			$db->query("
				INSERT INTO `user` (`email`, `password`, `firstname`, `lastname`, `admin`, `verified`, `created`)
				VALUES (?, ?, ?, ?, 1, 1, NOW())
				ON DUPLICATE KEY UPDATE `admin` = 1, `verified` = 1;
			", [ $user['email'], $password_hash, $user['firstname'], $user['lastname'] ]);
		}
	}

	/**
	 * Migrate down
	 *
	 * Removes the seeded admins. Users rows are matched on the email
	 * addresses found in the seed file.
	 *
	 * @access public
	 */
	public function down(): void {
		$db = Database::get();

		$users_path = dirname(__FILE__) . '/data/admin_users.csv';
		if (file_exists($users_path) === false) {
			return;
		}

		$user_lines = file($users_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		if ($user_lines === false) {
			return;
		}

		$user_header = str_getcsv(array_shift($user_lines), ',', '"', "\\");

		foreach ($user_lines as $user_line) {
			$user = array_combine($user_header, str_getcsv($user_line, ',', '"', "\\"));
			$db->query('DELETE FROM `user` WHERE `email` = ?;', [ $user['email'] ]);
		}
	}
}