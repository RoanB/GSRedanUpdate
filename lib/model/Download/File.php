<?php

/**
 * Download_File
 *
 * Links a skeleton File to a public-facing name and sort order. Admin
 * uploads a File (skeleton-file model), then creates a Download_File row
 * pointing to it with a display name.
 *
 * @author Roan Buysse <roan@tigron.be>
 */

use \Skeleton\Database\Database;

class Download_File {
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

		if (empty($this->name)) {
			$errors['name'] = 'mandatory';
		}

		if (empty($this->file_id)) {
			$errors['file_id'] = 'mandatory';
		}

		return count($errors) === 0;
	}

	/**
	 * Get the category of this file (or null for uncategorised)
	 *
	 * @access public
	 * @return ?Download_Category
	 */
	public function get_category(): ?Download_Category {
		if ((int)$this->download_category_id <= 0) {
			return null;
		}

		try {
			return Download_Category::get_by_id((int)$this->download_category_id);
		} catch (\Exception $e) {
			return null;
		}
	}

	/**
	 * Get all download files ordered by sort_order
	 *
	 * @access public
	 * @return array
	 */
	public static function get_all_ordered(): array {
		$db = Database::get();
		$ids = $db->get_column(
			'SELECT id FROM download_file ORDER BY sort_order ASC, name ASC'
		);

		$results = [];
		foreach ($ids as $id) {
			$results[] = self::get_by_id((int)$id);
		}
		return $results;
	}

	/**
	 * Get visible download files ordered by sort_order
	 *
	 * @access public
	 * @return array
	 */
	public static function get_visible_ordered(): array {
		$db = Database::get();
		$ids = $db->get_column(
			'SELECT id FROM download_file WHERE visible = 1 AND archived IS NULL ORDER BY sort_order ASC, name ASC'
		);

		$results = [];
		foreach ($ids as $id) {
			$results[] = self::get_by_id((int)$id);
		}
		return $results;
	}

	/**
	 * Get the associated File model
	 *
	 * Returns the generic skeleton file type because picture uploads resolve
	 * to `\Skeleton\File\Picture\Picture`, which is a `Skeleton\File\File`
	 * but not a project `\File`.
	 *
	 * @access public
	 * @return \Skeleton\File\File
	 */
	public function get_file(): \Skeleton\File\File {
		return \File::get_by_id($this->file_id);
	}

	/**
	 * Persist a new sort order from an array of ids
	 *
	 * @access public
	 * @param array $ids  ordered list of download_file ids
	 */
	public static function save_order(array $ids): void {
		$db = Database::get();
		foreach ($ids as $sort_order => $id) {
			$db->query(
				'UPDATE `download_file` SET `sort_order` = ? WHERE `id` = ?',
				[ $sort_order, (int)$id ]
			);
		}
	}
}