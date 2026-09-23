<?php

declare(strict_types=1);

/**
 * Database migration class
 *
 */


use \Skeleton\Database\Database;

class Migration_20251002_171902_Language extends \Skeleton\Database\Migration {

	/**
	 * Migrate up
	 *
	 * @access public
	 */
	public function up(): void {
		$db = Database::get();

		$db->query("
			INSERT INTO `language` (`id`, `name`, `name_local`, `name_short`) VALUES
			(2,	'French',	'Français',	'fr'),
			(3,	'Dutch',	'Nederlands',	'nl');
		");
	}

	/**
	 * Migrate down
	 *
	 * @access public
	 */
	public function down(): void {
	}
}
