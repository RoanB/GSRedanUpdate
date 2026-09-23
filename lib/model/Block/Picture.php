<?php

declare(strict_types=1);

/**
 * Block_Picture
 *
 * A single picture attached to a gallery block.
 *
 * @author Roan Buysse <roan@tigron.be>
 */

use \Skeleton\Database\Database;

class Block_Picture {
	use \Skeleton\Object\Model;
	use \Skeleton\Object\Uuid;
	use \Skeleton\Object\Get;
	use \Skeleton\Object\Save;
	use \Skeleton\Object\Delete {
		delete as trait_delete;
	}

	/**
	 * Validate before save
	 *
	 * @access public
	 * @param array &$errors
	 * @return bool
	 */
	public function validate(array &$errors = []): bool {
		$errors = [];

		$block = \Block::get_by_id((int)$this->block_id);

		// Gallery blocks hold a growing set of pictures; the fixed image +
		// text cards keep their (single) image in block.file_id instead.
		if (in_array($block->type, [ 'gallery' ], true) === false) {
			$errors['block'] = 'type_must_be_gallery';
		}

		if ($this->file_id <= 0) {
			$errors['file'] = 'invalid';
		}

		return count($errors) === 0;
	}

	/**
	 * Get the picture attached to this block_picture
	 *
	 * Always returns the generic \Skeleton\File\File; picture uploads resolve
	 * to \Skeleton\File\Picture\Picture which is not a project \File.
	 *
	 * @access public
	 * @return ?\Skeleton\File\Picture\Picture
	 */
	public function get_picture(): ?\Skeleton\File\Picture\Picture {
		try {
			$picture = \Skeleton\File\Picture\Picture::get_by_id((int)$this->file_id);
		} catch (\Exception $e) {
			return null;
		}

		return $picture;
	}

	/**
	 * Delete this block_picture including the underlying file
	 *
	 * @access public
	 * @param ?bool $delete_file also remove the underlying file
	 */
	public function delete(?bool $delete_file = true): void {
		$picture = $this->get_picture();

		$this->trait_delete();

		if ($delete_file === true && $picture !== null) {
			$picture->delete();
		}
	}

	/**
	 * Get the pictures of a gallery block, ordered
	 *
	 * @access public
	 * @param \Block $block
	 * @return array Block_Picture
	 */
	public static function get_by_block(\Block $block): array {
		$ids = Database::get()->get_column(
			'SELECT id FROM block_picture
			WHERE block_id = ? AND visible = 1 AND archived IS NULL
			ORDER BY sort_order ASC, id ASC',
			[ $block->id ]
		);

		$pictures = [];
		foreach ($ids as $id) {
			$pictures[] = self::get_by_id((int)$id);
		}

		return $pictures;
	}

	/**
	 * Save the picture order from an ordered list of block_picture ids
	 *
	 * @access public
	 * @param array $ids ordered block_picture ids
	 */
	public static function save_order(array $ids): void {
		$db = Database::get();

		foreach ($ids as $sort_order => $id) {
			$db->query(
				'UPDATE `block_picture` SET `sort_order` = ? WHERE `id` = ?',
				[ $sort_order, (int)$id ]
			);
		}
	}
}
