<?php

declare(strict_types=1);

/**
 * Database migration class
 *
 * `block.group_pair`: the flag that 2-packs consecutive project-style
 * activity cards into a single visual card (spec/03 "pair grouping").
 *
 * Only the SECOND row of a 2-pack carries the flag (group_pair = 1); the
 * first row stays 0. The welder in 20260917_160000_card_types keeps the
 * first row as the merged card and folds the second into it. (The first
 * implementation flagged both rows, so the welder found no "first" partner
 * and left both project cards as the obsolete `activities` type.)
 */

use \Skeleton\Database\Database;

class Migration_20260917_130500_card_pair extends \Skeleton\Database\Migration {

	/**
	 * Migrate up
	 *
	 * @access public
	 */
	public function up(): void {
		if ($this->has_column('block', 'group_pair') === false) {
			Database::get()->query('ALTER TABLE `block` ADD `group_pair` tinyint NOT NULL DEFAULT \'0\';');
		}

		// The existing project cards (Entraînement + Activités pour les
		// jeunes) are the first 2-pack of the site; the second card (youth,
		// anchor-less project card) carries the grouping flag. The first
		// (training) stays group_pair = 0 so the welder in
		// 20260917_160000_card_types can find it as the merge target. Their
		// image sides follow the ../redan layout: text left / photo right on
		// the top half, photo left / text right on the bottom half.
		Database::get()->query("UPDATE `block` SET `image_side` = 'right' WHERE `type` = 'activities' AND `image_layout` = 'project' AND `image_side` = 'left' AND `anchor` = 'projects';");
		Database::get()->query("UPDATE `block` SET `group_pair` = 1, `image_side` = 'left' WHERE `type` = 'activities' AND `image_layout` = 'project' AND `image_side` = 'right' AND `anchor` = '';");
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
	 * @access public
	 */
	public function down(): void {
		Database::get()->query('ALTER TABLE `block` DROP `group_pair`;');
	}
}
