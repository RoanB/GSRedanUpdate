<?php

declare(strict_types=1);

/**
 * Database migration class
 *
 * Monday openings correction: only the six announced days are imported
 * (all odd Mondays of 2026: 7 and 21 September, 5 October, 9 and 23
 * November, 7 December, 19:30-22:30 Brussels) — the blanket "next 13
 * Mondays" seed of the cards rework is removed. The card itself is no
 * longer a separate block: the existing salle (Training Facility)
 * activities_light card becomes the calendar-fed monday_openings card —
 * same look, same image, same anchor/menu labels — and its body loses
 * the hard-coded date list, replaced by the __OPENINGS__ placeholder the
 * Index module fills from the calendar.
 */

use \Skeleton\Database\Database;

class Migration_20260917_170000_openings_exact extends \Skeleton\Database\Migration {

	/**
	 * The exact openings for "Openings 2026" (odd Mondays)
	 *
	 * Times are Europe/Brussels 19:30-22:30, stored UTC.
	 *
	 * @var string[] $opening_dates
	 */
	const OPENING_DATES = [
		'2026-09-07',
		'2026-09-21',
		'2026-10-05',
		'2026-11-09',
		'2026-11-23',
		'2026-12-07',
	];

	/**
	 * Migrate up
	 *
	 * @access public
	 */
	public function up(): void {
		$db = Database::get();

		$category_id = $db->get_one('SELECT id FROM calendar_category WHERE name = ?', [ 'Ouverture de la salle' ]);
		$category_id = (int)($category_id ?? 0);

		// Remove the generic seeds of 20260917_160000 (title match keeps
		// events an admin retitled in the meantime).
		if ($category_id > 0) {
			$db->query('DELETE FROM `calendar_event` WHERE `title` = \'Ouverture de la salle\' AND `calendar_category_id` = ? AND `calendar_category_id` IS NOT NULL', [ $category_id ]);
		}

		$this->seed_exact_openings($category_id);

		// The separate Monday openings card is gone: the salle card becomes
		// the calendar-fed monday_openings card.
		$db->query('
			DELETE `bt` FROM `block_translation` AS `bt`
			INNER JOIN `block` AS `b` ON `b`.`id` = `bt`.`block_id`
			WHERE `b`.`type` = \'monday_openings\' AND `b`.`anchor` = \'openings\'
		');
		$db->query('DELETE FROM `block` WHERE `type` = \'monday_openings\' AND `anchor` = \'openings\'');

		$db->query('UPDATE `block` SET `type` = \'monday_openings\', `image_side` = \'left\' WHERE `anchor` = \'salle\' AND `archived` IS NULL');

		// Hand the date list of the body over to the calendar (the three
		// languages carried the exact seed text before). INSTR is used
		// instead of a LIKE guard — underscore is a single-char wildcard in
		// SQL, so a plain LIKE on __OPENINGS__ matches everything.
		$db->query('
			UPDATE `block_translation`
			SET `body` = REPLACE(`body`, \'7th and 21st of September<br>5th of October<br>9th and 23rd of November<br>7th of December\', \'__OPENINGS__\')
			WHERE `body` LIKE \'%salle-openings%\' AND INSTR(`body`, \'__OPENINGS__\') = 0
		');
		$db->query('
			UPDATE `block_translation`
			SET `body` = REPLACE(
				REPLACE(`body`, \'7 et 21 Septembre<br>5 Octobre<br>9 et 23 Novembre<br>7 Décembre\', \'__OPENINGS__\'),
				\'7 en 21 September<br>5 Oktober<br>9 en 23 November<br>7 December\', \'__OPENINGS__\'
			)
			WHERE `body` LIKE \'%salle-openings%\' AND INSTR(`body`, \'__OPENINGS__\') = 0
		');
	}

	/**
	 * Insert exactly the announced openings (once)
	 *
	 * @access private
	 * @param int $category_id
	 */
	private function seed_exact_openings(int $category_id): void {
		if ($category_id <= 0) {
			return;
		}

		$db = Database::get();

		$brussels = new \DateTimeZone('Europe/Brussels');
		$utc = new \DateTimeZone('UTC');

		foreach (self::OPENING_DATES as $date) {
			$existing = $db->get_one(
				'SELECT COUNT(*) FROM calendar_event WHERE starts_at LIKE ? AND calendar_category_id = ? AND archived IS NULL',
				[ $date . '%', $category_id ]
			);
			if ((int)$existing > 0) {
				continue;
			}

			$starts = \DateTime::createFromFormat('Y-m-d H:i', $date . ' 19:30', $brussels);
			$ends = \DateTime::createFromFormat('Y-m-d H:i', $date . ' 22:30', $brussels);
			if ($starts === false || $ends === false) {
				continue;
			}

			$starts->setTimezone($utc);
			$ends->setTimezone($utc);

			$db->query('
				INSERT INTO `calendar_event`
					(`uuid`, `title`, `description`, `location`, `starts_at`, `ends_at`, `all_day`, `visible`, `calendar_category_id`, `created`)
				VALUES
					(?, ?, \'\', ?, ?, ?, 0, 1, ?, NOW())
			', [
				$this->generate_uuid(),
				'Ouverture de la salle',
				null,
				$starts->format('Y-m-d H:i:s'),
				$ends->format('Y-m-d H:i:s'),
				$category_id,
			]);
		}
	}

	/**
	 * Generate a UUID v4
	 *
	 * @access private
	 * @return string
	 */
	private function generate_uuid(): string {
		$data = random_bytes(16);
		$data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
		$data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

		return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
	}

	/**
	 * Migrate down
	 *
	 * Restores the salle card as a plain activities_light card with its
	 * hard-coded date list back and drops the imported openings events.
	 *
	 * @access public
	 */
	public function down(): void {
		$db = Database::get();

		$category_id = (int)($db->get_one('SELECT id FROM calendar_category WHERE name = ?', [ 'Ouverture de la salle' ]) ?? 0);
		if ($category_id > 0) {
			$db->query('DELETE FROM `calendar_event` WHERE `title` = \'Ouverture de la salle\' AND `calendar_category_id` = ?', [ $category_id ]);
		}

		// Backfill the exact original date lists.
		$restore = [
			'fr' => '7 et 21 Septembre<br>5 Octobre<br>9 et 23 Novembre<br>7 Décembre',
			'nl' => '7 en 21 September<br>5 Oktober<br>9 en 23 November<br>7 December',
			'en' => '7th and 21st of September<br>5th of October<br>9th and 23rd of November<br>7th of December',
		];

		foreach ($restore as $name_short => $dates) {
			$row = $db->get_row('
				SELECT bt.id AS translation_id FROM block_translation AS bt
				INNER JOIN block AS b ON b.id = bt.block_id
				WHERE b.anchor = \'salle\' AND archived IS NULL AND body LIKE \'%__OPENINGS__%\'
				AND bt.language_id = (SELECT id FROM language WHERE name_short = ?)
			', [ $name_short ]);
			if ($row === null || $row === false || isset($row['translation_id']) === false) {
				continue;
			}

			$db->query('UPDATE `block_translation` SET `body` = REPLACE(`body`, \'__OPENINGS__\', ?) WHERE `id` = ?', [ $dates, (int)$row['translation_id'] ]);
		}

		$db->query('UPDATE `block` SET `type` = \'activities_light\' WHERE `anchor` = \'salle\' AND `archived` IS NULL');
	}
}
