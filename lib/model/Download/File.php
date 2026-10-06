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
			$errors['name'] = 'required';
		}

		if (empty($this->file_id)) {
			$errors['file_id'] = 'required';
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
	 * Number of download files (admin dashboard counter)
	 *
	 * @access public
	 * @return int $count
	 */
	public static function count_all(): int {
		$count = Database::get()->get_one(
			'SELECT COUNT(*) FROM download_file WHERE archived IS NULL'
		);

		return (int)$count;
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
	 * Replace the attached file
	 *
	 * Swaps the reference, saves, and only then deletes the file that was
	 * replaced: the download_file.file_id foreign key has to point at the new
	 * file before the old row can go. Without this the old file stays in the
	 * `file` table and on disk with nothing referencing it.
	 *
	 * @access public
	 * @param \Skeleton\File\File $file
	 */
	public function replace_file(\Skeleton\File\File $file): void {
		$old_file_id = (int)$this->file_id;

		$this->file_id = $file->id;
		$this->save();

		if ($old_file_id > 0 && $old_file_id !== (int)$file->id) {
			try {
				\File::get_by_id($old_file_id)->delete();
			} catch (\Exception $e) {
				// The old file row was already gone; nothing to clean up.
			}
		}
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