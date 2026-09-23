<?php

declare(strict_types=1);

/**
 * Database migration class
 *
 * Converges a database that was migrated with the broken pair flagging.
 *
 * 20260917_130500_card_pair set `group_pair = 1` on BOTH halves of the
 * project 2-pack, so the welder in 20260917_160000_card_types found no
 * "first" partner (it searches for a preceding row with `group_pair = 0`)
 * and left both rows as the now-obsolete `activities` type, which has no
 * template and renders the homepage with a missing-partial error.
 *
 * This migration welds every remaining orphaned `activities` row pair into
 * one `activities_pair_dark` card, exactly as the cards rework intended: the
 * second row's image moves into `file_id_2` of the first, its body is
 * appended behind the pair marker for every language, and the second row is
 * archived. An odd leftover (no partner) becomes `activities_dark`. It is
 * safe to re-run: once welded, the rows no longer match the `activities`
 * selector.
 */

use \Skeleton\Database\Database;

class Migration_20260923_120000_Repair_Orphan_Activities_Pair extends \Skeleton\Database\Migration {

	/**
	 * Marker matched to split the two texts of a pair card
	 *
	 * @var string $pair_marker_pattern
	 */
	const PAIR_MARKER_PATTERN = '/<!--\s*pair(?::\d+)?-->/';

	/**
	 * Migrate up
	 *
	 * @access public
	 */
	public function up(): void {
		$db = Database::get();

		$orphans = $db->get_all('
			SELECT id, file_id, sort_order FROM block
			WHERE type = \'activities\' AND archived IS NULL
			ORDER BY sort_order ASC, id ASC
		');
		if ($orphans === null || $orphans === false) {
			return;
		}

		$count = count($orphans);
		for ($i = 0; $i + 1 < $count; $i += 2) {
			$this->weld_pair((int)$orphans[$i]['id'], (int)$orphans[$i + 1]['id']);
		}

		// An odd leftover has no second row to fold in; the project style
		// maps to the single dark card.
		if ($count % 2 === 1) {
			$db->query('UPDATE `block` SET `type` = \'activities_dark\' WHERE `id` = ?', [ (int)$orphans[$count - 1]['id'] ]);
		}
	}

	/**
	 * Weld two orphaned activity rows into one activities_pair_dark card
	 *
	 * The first row keeps its identity (image, anchor, sort order); the
	 * second row's image becomes file_id_2 and its body is appended behind
	 * the pair marker. The second row is then archived.
	 *
	 * @access private
	 * @param int $first_id
	 * @param int $second_id
	 */
	private function weld_pair(int $first_id, int $second_id): void {
		$db = Database::get();

		$second = $db->get_row('SELECT file_id FROM block WHERE id = ?', [ $second_id ]);
		if ((int)($second['file_id'] ?? 0) > 0) {
			$db->query('UPDATE `block` SET `file_id_2` = ? WHERE `id` = ?', [ (int)$second['file_id'], $first_id ]);
		}

		$rows = $db->get_all('
			SELECT block_id, language_id, body FROM block_translation
			WHERE block_id IN (?, ?) AND archived IS NULL
		', [ $first_id, $second_id ]);
		if ($rows === null || $rows === false) {
			$rows = [];
		}

		$marker = '<!--pair:' . $second_id . '-->';
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

	/**
	 * Migrate down
	 *
	 * Best effort: splits fused pair cards back into two plain `activities`
	 * rows (un-archives the second row, moves file_id_2 back onto it and
	 * splits the merged body at the pair marker). The single dark rows this
	 * migration created cannot be distinguished from real dark cards, so
	 * they are left untouched.
	 *
	 * @access public
	 */
	public function down(): void {
		$db = Database::get();

		$pairs = $db->get_all('SELECT id, file_id_2 FROM block WHERE type = \'activities_pair_dark\' AND archived IS NULL');
		if ($pairs === null || $pairs === false) {
			return;
		}

		foreach ($pairs as $pair) {
			$first_id = (int)$pair['id'];

			$translations = $db->get_all('SELECT id, language_id, body FROM block_translation WHERE block_id = ?', [ $first_id ]);
			if ($translations === null || $translations === false) {
				continue;
			}

			foreach ($translations as $translation) {
				if (preg_match(self::PAIR_MARKER_PATTERN, (string)$translation['body'], $matches) !== 1) {
					continue;
				}

				$second_id = (int)($matches[1] ?? 0);
				$parts = preg_split(self::PAIR_MARKER_PATTERN, (string)$translation['body']);
				if ($parts === false || count($parts) < 2) {
					continue;
				}

				$db->query('UPDATE `block_translation` SET `body` = ? WHERE `id` = ?', [ $parts[0], $translation['id'] ]);

				if ($second_id > 0) {
					$db->query('
						UPDATE `block_translation` SET `body` = ?, `archived` = NULL
						WHERE `block_id` = ? AND `language_id` = ?
					', [ $parts[1], $second_id, $translation['language_id'] ]);
				}
			}

			if (isset($second_id) === true && $second_id > 0) {
				$db->query('UPDATE `block` SET `archived` = NULL, `type` = \'activities\', `file_id` = ? WHERE `id` = ?', [ (int)$pair['file_id_2'], $second_id ]);
				$db->query('UPDATE `block` SET `file_id_2` = NULL WHERE `id` = ?', [ $first_id ]);
				$db->query('UPDATE `block` SET `type` = \'activities\' WHERE `id` = ?', [ $first_id ]);
			}
		}
	}
}