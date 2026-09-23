<?php

declare(strict_types=1);

/**
 * Database migration class
 *
 * Cards rework: the activities/gallery layout knobs move into the card
 * type itself. `featured` / single `project` activities become dedicated
 * `activities_light` / `activities_dark` types; the 2-packed project pair
 * (Entraînement + Activités jeunes) becomes ONE `activities_pair_dark`
 * card carrying both images and both texts (split by a pair marker in the
 * translation body). The footer row leaves the block table: the footer is
 * a fixed page element rendered by the site layout. Also adds the second
 * image column for pair cards and seeds the calendar category the Monday
 * openings card is fed from, plus opening events for the upcoming Mondays.
 */

use \Skeleton\Database\Database;

class Migration_20260917_160000_card_types extends \Skeleton\Database\Migration {

	/**
	 * Marker matched to split the two texts of a pair card
	 *
	 * Captures the id of the (now archived) second block so `down()` can
	 * split the merged body back onto the two original rows.
	 *
	 * @var string $pair_marker_pattern
	 */
	const PAIR_MARKER_PATTERN = '/<!--\s*pair(?::(\d+))?-->/';

	/**
	 * Canonical pair marker (the second block id is added by the merger)
	 *
	 * @var string $pair_marker
	 */
	const PAIR_MARKER = '<!--pair';

	/**
	 * Closing part of the pair marker
	 *
	 * @var string $pair_marker_end
	 */
	const PAIR_MARKER_END = '-->';

	/**
	 * Monday opening hours (Europe/Brussels; stored UTC)
	 *
	 * @var int[] $opening_hours
	 */
	const OPENING_TIME = [ 19, 22 ];

	/**
	 * How many upcoming Mondays get seeded
	 *
	 * @var int $opening_monday_count
	 */
	const OPENING_MONDAY_COUNT = 13;

	/**
	 * Migrate up
	 *
	 * @access public
	 */
	public function up(): void {
		$db = Database::get();

		if ($this->has_column('block', 'file_id_2') === false) {
			// Second image of a 2-row (pair) card, row order fixed.
			$db->query('ALTER TABLE `block` ADD `file_id_2` int unsigned NULL;');
		} else {
			$db->query('ALTER TABLE `block` MODIFY `file_id_2` int unsigned NULL;');
		}

		if ($this->has_fk_block_file_2() === false) {
			$db->query('ALTER TABLE `block` ADD CONSTRAINT `fk_block_file_2` FOREIGN KEY (`file_id_2`) REFERENCES `file` (`id`);');
		}

		// Pairs first: the first-and-only-then conversion of the remaining
		// single project rows would otherwise swallow the pair halves.
		$this->merge_project_pairs();
		$this->convert_single_activity_types();
		$this->remove_footer_block();
		$this->seed_monday_openings();
		$this->seed_monday_openings_block();

		// Layout knobs are superseded by the type; image_side stays (still
		// meaningful for the light card).
		$db->query('ALTER TABLE `block` DROP `image_layout`, DROP `group_pair`;');
	}

	/**
	 * Convert featured / single project activities into their new types
	 *
	 * @access private
	 */
	private function convert_single_activity_types(): void {
		$db = Database::get();
		$db->query('UPDATE `block` SET `type` = \'activities_light\' WHERE `type` = \'activities\' AND `image_layout` = \'featured\' AND archived IS NULL');
		$db->query('UPDATE `block` SET `type` = \'activities_dark\' WHERE `type` = \'activities\' AND `image_layout` = \'project\' AND `group_pair` = 0 AND archived IS NULL');
	}

	/**
	 * Weld each project pair into one activities_pair_dark card
	 *
	 * The second block (group_pair = 1) is archived; its image moves into
	 * file_id_2 of the first block and its body is appended behind the
	 * pair marker for every language.
	 *
	 * @access private
	 */
	private function merge_project_pairs(): void {
		$db = Database::get();

		$seconds = $db->get_all(
			'SELECT id, file_id, sort_order FROM block
			WHERE type = \'activities\' AND image_layout = \'project\' AND group_pair = 1 AND archived IS NULL
			ORDER BY sort_order ASC'
		);
		if ($seconds === null || $seconds === false) {
			$seconds = [];
		}

		foreach ($seconds as $second) {
			$first = $db->get_row('
				SELECT id FROM block
				WHERE type = \'activities\' AND image_layout = \'project\' AND group_pair = 0 AND archived IS NULL
				AND `sort_order` < ?
				ORDER BY `sort_order` DESC, id DESC
				LIMIT 1
			', [ $second['sort_order'] ]);

			if ($first === null || $first === false || isset($first['id']) === false) {
				continue;
			}

			$first_id = (int)$first['id'];
			$second_id = (int)$second['id'];
			$marker = self::PAIR_MARKER . ':' . $second_id . self::PAIR_MARKER_END;

			if ((int)$second['file_id'] > 0) {
				$db->query('UPDATE `block` SET `file_id_2` = ? WHERE `id` = ?', [ (int)$second['file_id'], $first_id ]);
			}

			$rows = $db->get_all('
				SELECT block_id, language_id, body FROM block_translation
				WHERE block_id IN (?, ?) AND archived IS NULL
			', [ $first_id, $second_id ]);
			if ($rows === null || $rows === false) {
				$rows = [];
			}

			foreach ($rows as $row) {
				if ((int)$row['block_id'] !== $second_id) {
					continue;
				}

				$db->query('
					UPDATE `block_translation`
					SET `body` = CONCAT(`body`, ?, IFNULL(?, \'\'))
					WHERE `block_id` = ? AND `language_id` = ?
				', [ "\n" . $marker . "\n", $row['body'], $first_id, $row['language_id'] ]);
			}

			$db->query('UPDATE `block` SET `type` = \'activities_pair_dark\' WHERE `id` = ?', [ $first_id ]);
			$db->query('UPDATE `block` SET `archived` = NOW() WHERE `id` = ?', [ $second_id ]);
			$db->query('UPDATE `block_translation` SET `archived` = NOW() WHERE `block_id` = ?', [ $second_id ]);
		}
	}

	/**
	 * Remove the footer block row (the footer is rendered by the layout)
	 *
	 * @access private
	 */
	private function remove_footer_block(): void {
		$db = Database::get();
		$db->query('
			DELETE `bt` FROM `block_translation` AS `bt`
			INNER JOIN `block` AS `b` ON `b`.`id` = `bt`.`block_id`
			WHERE `b`.`type` = \'footer\'
		');
		$db->query('DELETE FROM `block` WHERE `type` = \'footer\'');
	}

	/**
	 * Seed the calendar category and the upcoming Monday opening events
	 *
	 * @access private
	 */
	private function seed_monday_openings(): void {
		$db = Database::get();

		$existing = $db->get_one('SELECT COUNT(*) FROM calendar_category WHERE name = ?', [ 'Ouverture de la salle' ]);
		if ((int)$existing === 0) {
			$sort_order = $db->get_one('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM calendar_category');
			$db->query('
				INSERT INTO `calendar_category` (`name`, `color`, `sort_order`, `created`)
				VALUES (?, ?, ?, NOW())
			', [ 'Ouverture de la salle', '#3fa7a7', (int)$sort_order ]);
		}

		$category_id = $db->get_one('SELECT id FROM calendar_category WHERE name = ?', [ 'Ouverture de la salle' ]);

		// Upcoming Mondays from today, at 19:00-22:00 Brussels time.
		$brussels = new \DateTimeZone('Europe/Brussels');
		$utc = new \DateTimeZone('UTC');
		$monday = new \DateTime('today', $brussels);
		$monday->modify('next monday');

		for ($i = 0; $i < self::OPENING_MONDAY_COUNT; $i++) {
			$starts = clone $monday;
			$starts->setTime(self::OPENING_TIME[0], 0, 0);
			$ends = clone $monday;
			$ends->setTime(self::OPENING_TIME[1], 0, 0);

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
				(int)$category_id,
			]);

			$monday->modify('+7 days');
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
	 * Seed the fixed Monday openings card
	 *
	 * One locked block row at the end of the page with a menu label per
	 * language (the content itself is calendar-fed, not stored here).
	 *
	 * @access private
	 */
	private function seed_monday_openings_block(): void {
		$db = Database::get();

		$existing = $db->get_one('SELECT COUNT(*) FROM block WHERE type = ? AND archived IS NULL', [ 'monday_openings' ]);
		if ((int)$existing > 0) {
			return;
		}

		$sort_order = $db->get_one('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM block');
		$db->query('
			INSERT INTO `block` (`type`, `anchor`, `sort_order`, `visible`, `image_side`, `created`)
			VALUES (\'monday_openings\', \'openings\', ?, 1, \'right\', NOW())
		', [ (int)$sort_order ]);

		$block_id = (int)$db->get_one('SELECT id FROM block WHERE type = \'monday_openings\' AND archived IS NULL');

		$titles = [
			'fr' => 'Ouvertures du lundi',
			'nl' => 'Maandagopeningen',
			'en' => 'Monday openings',
		];

		foreach ($titles as $name_short => $title) {
			$language_id = $db->get_one('SELECT id FROM language WHERE name_short = ?', [ $name_short ]);
			if ($language_id === null) {
				continue;
			}

			$db->query('
				INSERT INTO `block_translation` (`block_id`, `language_id`, `title`, `body`, `created`)
				VALUES (?, ?, ?, \'\', NOW())
			', [ $block_id, (int)$language_id, $title ]);
		}
	}

	/**
	 * Does the file_id_2 foreign key exist on block?
	 *
	 * @access private
	 * @return bool
	 */
	private function has_fk_block_file_2(): bool {
		$count = Database::get()->get_one(
			'SELECT COUNT(*) FROM information_schema.key_column_usage
			WHERE table_schema = DATABASE()
			AND table_name = \'block\'
			AND column_name = \'file_id_2\'
			AND referenced_table_name = \'file\''
		);

		return (int)$count > 0;
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
	 * Rebuilds the pre-rework schema: re-adds image_layout/group_pair,
	 * splits pair cards back into the (restored) second rows, maps the new
	 * types back to activities, re-adds the layout columns, removes the
	 * Monday-opening seeds/category and recreates an empty footer row.
	 *
	 * @access public
	 */
	public function down(): void {
		$db = Database::get();

		$db->query('ALTER TABLE `block` ADD `image_layout` varchar(16) NOT NULL DEFAULT \'featured\' AFTER `file_id`, ADD `group_pair` tinyint(4) NOT NULL DEFAULT \'0\';');

		$this->split_project_pairs();

		// The split restored image_layout/group_pair on the pair halves;
		// single rows map back to activities plus their old layout knob.
		$db->query('UPDATE `block` SET `type` = \'activities\', `image_layout` = \'featured\' WHERE `type` = \'activities_light\'');
		$db->query('UPDATE `block` SET `type` = \'activities\', `image_layout` = \'project\' WHERE `type` = \'activities_dark\'');

		$footer_exists = $db->get_one('SELECT COUNT(*) FROM block WHERE type = \'footer\'');
		if ((int)$footer_exists === 0) {
			$sort_order = $db->get_one('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM block');
			$db->query('
				INSERT INTO `block` (`type`, `anchor`, `sort_order`, `visible`, `image_layout`, `image_side`, `group_pair`, `created`)
				VALUES (\'footer\', \'\', ?, 1, \'featured\', \'left\', 0, NOW())
			', [ (int)$sort_order ]);
		}

		$db->query('DELETE `ce` FROM `calendar_event` AS `ce` INNER JOIN `calendar_category` AS `cc` ON `cc`.`id` = `ce`.`calendar_category_id` WHERE `cc`.`name` = \'Ouverture de la salle\'');
		$db->query('DELETE FROM `calendar_category` WHERE `name` = \'Ouverture de la salle\'');

		$db->query('DELETE `bt` FROM `block_translation` AS `bt` INNER JOIN `block` AS `b` ON `b`.`id` = `bt`.`block_id` WHERE `b`.`type` = \'monday_openings\'');
		$db->query('DELETE FROM `block` WHERE `type` = \'monday_openings\'');

		// The FK blocks the column drop: drop the constraint first.
		$db->query('ALTER TABLE `block` DROP FOREIGN KEY `fk_block_file_2`;');
		$db->query('ALTER TABLE `block` DROP `file_id_2`;');
	}

	/**
	 * Split merged pair cards back into two activities rows
	 *
	 * @access private
	 */
	private function split_project_pairs(): void {
		$db = Database::get();

		$blocks = $db->get_all('SELECT id FROM block WHERE type = \'activities_pair_dark\'');
		if ($blocks === null || $blocks === false || count($blocks) === 0) {
			return;
		}

		foreach ($blocks as $block) {
			$db->query('
				UPDATE `block` SET `image_layout` = \'project\', `group_pair` = 0
				WHERE `id` = ?
			', [ $block['id'] ]);

			$pairs = $db->get_all('SELECT id, language_id, body FROM block_translation WHERE block_id = ?', [ $block['id'] ]);
			if ($pairs === null || $pairs === false) {
				continue;
			}

			foreach ($pairs as $pair) {
				if (preg_match(self::PAIR_MARKER_PATTERN, (string)$pair['body'], $matches) !== 1) {
					continue;
				}

				$second_id = (int)($matches[1] ?? 0);
				$parts = preg_split(self::PAIR_MARKER_PATTERN, (string)$pair['body']);
				if ($parts === false || count($parts) < 2) {
					continue;
				}

				$first_body = $parts[0];
				$second_body = $parts[1];

				$db->query('UPDATE `block_translation` SET `body` = ? WHERE `id` = ?', [ $first_body, $pair['id'] ]);

				// Un-archive the original second row and restore its body.
				if ($second_id > 0) {
					$db->query('
						UPDATE `block_translation` SET `body` = ?, `archived` = NULL
						WHERE `block_id` = ? AND `language_id` = ?
					', [ $second_body, $second_id, $pair['language_id'] ]);
					$db->query('
						UPDATE `block` SET `archived` = NULL, `type` = \'activities\', `image_layout` = \'project\', `group_pair` = 1, `file_id_2` = NULL
						WHERE `id` = ?
					', [ $second_id ]);
				}
			}
		}
	}
}
