<?php

declare(strict_types=1);

/**
 * Database migration class
 *
 * Makes the footer legal-entity line translatable. The templates run
 * `footer_legal_entity` through the translator, which expects the English
 * msgid `Non-profit organisation (ASBL)` (already catalogued with FR/NL
 * translations for the legal page). Only the untouched French seed value is
 * swapped; a value the admin changed by hand is left alone.
 */

use \Skeleton\Database\Database;

class Migration_20261020_100000_Footer_Legal_Entity_English extends \Skeleton\Database\Migration {

	/**
	 * Migrate up
	 *
	 * @access public
	 */
	public function up(): void {
		$db = Database::get();

		$db->query("
			UPDATE `setting`
			SET `value` = 'Non-profit organisation (ASBL)'
			WHERE `name` = 'footer_legal_entity'
			AND `value` = 'Association sans but lucratif';
		");
	}

	/**
	 * Migrate down
	 *
	 * Restores the French seed wording.
	 *
	 * @access public
	 */
	public function down(): void {
		$db = Database::get();

		$db->query("
			UPDATE `setting`
			SET `value` = 'Association sans but lucratif'
			WHERE `name` = 'footer_legal_entity'
			AND `value` = 'Non-profit organisation (ASBL)';
		");
	}
}
