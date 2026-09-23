<?php

declare(strict_types=1);

/**
 * Download_Category
 *
 * A category of the members area files. Files that do not reference a
 * category are rendered in a default "All" group.
 *
 * @author Roan Buysse <roan@tigron.be>
 */

use \Skeleton\Database\Database;

class Download_Category {
	use \Skeleton\Object\Model;
	use \Skeleton\Object\Uuid;
	use \Skeleton\Object\Get;
	use \Skeleton\Object\Save;
	use \Skeleton\Object\Delete;

	/**
	 * Validate before save
	 *
	 * @access public
	 * @param array &$errors
	 * @return bool
	 */
	public function validate(array &$errors = []): bool {
		$errors = [];

		if (trim((string)$this->name) === '') {
			$errors['name'] = 'required';
		}

		$db = Database::get();
		$duplicate = $db->get_one(
			'SELECT COUNT(*) FROM `download_category` WHERE `name` = ? AND (`id` != ? OR `id` IS NULL)',
			[ trim((string)$this->name), $this->id ?? 0 ]
		);
		if ((int)$duplicate > 0) {
			$errors['name'] = 'duplicate';
		}

		return count($errors) === 0;
	}

	/**
	 * Can this category be deleted?
	 *
	 * A category referenced by files must stay (the admin re-assigns the
	 * files first).
	 *
	 * @access public
	 * @return bool
	 */
	public function is_deletable(): bool {
		$count = Database::get()->get_one(
			'SELECT COUNT(*) FROM `download_file` WHERE `download_category_id` = ? AND `archived` IS NULL',
			[ $this->id ]
		);

		return (int)$count === 0;
	}

	/**
	 * Count the visible files in this category
	 *
	 * @access public
	 * @return int
	 */
	public function count_visible_files(): int {
		$count = Database::get()->get_one(
			'SELECT COUNT(*) FROM `download_file` WHERE `download_category_id` = ? AND `visible` = 1 AND `archived` IS NULL',
			[ $this->id ]
		);

		return (int)$count;
	}

	/**
	 * All categories ordered
	 *
	 * @access public
	 * @return array Download_Category
	 */
	public static function get_all_ordered(): array {
		$db = Database::get();
		$ids = $db->get_column(
			'SELECT id FROM download_category WHERE archived IS NULL ORDER BY sort_order ASC, id ASC'
		);

		$categories = [];
		foreach ($ids as $id) {
			$categories[] = self::get_by_id((int)$id);
		}

		return $categories;
	}

	/**
	 * Persist the order from an ordered list of category ids
	 *
	 * @access public
	 * @param array $ids
	 */
	public static function save_order(array $ids): void {
		$db = Database::get();

		foreach ($ids as $sort_order => $id) {
			$db->query(
				'UPDATE `download_category` SET `sort_order` = ? WHERE `id` = ?',
				[ $sort_order, (int)$id ]
			);
		}
	}
}
