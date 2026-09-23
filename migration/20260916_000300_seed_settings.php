<?php

declare(strict_types=1);

/**
 * Database migration class
 *
 * Seeds the key/value settings from spec/01-data-model.md. Combination
 * of `password_hash()` makes this non-deterministic, so `INSERT ... ON
 * DUPLICATE KEY UPDATE` semantics are avoided by only inserting when the
 * row is missing. Re-running never overwrites a value changed in the admin.
 */

use \Skeleton\Database\Database;

class Migration_20260916_000300_Seed_Settings extends \Skeleton\Database\Migration {

	/**
	 * Migrate up
	 *
	 * @access public
	 */
	public function up(): void {
		$db = Database::get();

		$settings = [
			'contact_email' => 'contact@gsredan.be',
			'address_local_street' => 'Parvis de la Basilique 1, porte 7',
			'address_local_zip' => '1083',
			'address_local_city' => 'Ganshoren',
			'address_local_maps' => 'https://maps.app.goo.gl/USRK75XeW6wMEvk9A',
			'address_club_street' => 'Bd de Smet de Naeyer 21',
			'address_club_zip' => '1090',
			'address_club_city' => 'Jette',
			'address_club_maps' => 'https://maps.app.goo.gl/FBkR26S1usDrRFsA9',
			'social_instagram' => 'https://www.instagram.com/gs_redan',
			'social_facebook' => 'https://www.facebook.com/GroupeSpeleoRedan/',
			'footer_company_number' => '0474.156.883',
			'footer_legal_entity' => 'Association sans but lucratif',
			'masthead_title' => 'Gs Redan',
			'download_password_hash' => password_hash('OuEstLaCorde', PASSWORD_DEFAULT),
		];

		foreach ($settings as $name => $value) {
			$db->query("
				INSERT INTO `setting` (`name`, `value`, `created`)
				SELECT ?, ?, NOW()
				WHERE NOT EXISTS (SELECT `id` FROM `setting` WHERE `name` = ?);
			", [ $name, $value, $name ]);
		}
	}

	/**
	 * Migrate down
	 *
	 * Removes the seeded settings; any admin-modified values are removed with
	 * them (a fully reversible seed migration).
	 *
	 * @access public
	 */
	public function down(): void {
		$db = Database::get();

		$names = [
			'contact_email',
			'address_local_street',
			'address_local_zip',
			'address_local_city',
			'address_local_maps',
			'address_club_street',
			'address_club_zip',
			'address_club_city',
			'address_club_maps',
			'social_instagram',
			'social_facebook',
			'footer_company_number',
			'footer_legal_entity',
			'masthead_title',
			'download_password_hash',
		];

		foreach ($names as $name) {
			$db->query('DELETE FROM `setting` WHERE `name` = ?;', [ $name ]);
		}
	}
}