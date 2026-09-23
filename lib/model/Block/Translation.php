<?php

/**
 * Block_Translation
 *
 * Per-language content for a Block. Each block has one row per language.
 * The FR translation is the default fallback (spec/02).
 *
 * @author Roan Buysse <roan@tigron.be>
 */

use \Skeleton\Database\Database;

class Block_Translation {
	use \Skeleton\Object\Model;
	use \Skeleton\Object\Uuid;
	use \Skeleton\Object\Get;
	use \Skeleton\Object\Save;
	use \Skeleton\Object\Delete;

	/**
	 * Get translation by block + language
	 *
	 * @access public
	 * @param Block $block
	 * @param \Language $language
	 * @return Block_Translation
	 * @throws \Exception if not found
	 */
	public static function get_by_block_language(Block $block, \Language $language): self {
		$db = Database::get();
		$id = $db->get_one(
			'SELECT id FROM block_translation WHERE block_id = ? AND language_id = ? AND archived IS NULL',
			[ $block->id, $language->id ]
		);
		if ($id === null) {
			throw new \Exception('No translation found for block #' . $block->id . ', language #' . $language->id);
		}
		return self::get_by_id((int)$id);
	}

	/**
	 * Get translation by block id + language id (lightweight, no model load)
	 *
	 * @access public
	 * @param int $block_id
	 * @param int $language_id
	 * @return array|null
	 */
	public static function get_by_block_language_id(int $block_id, int $language_id): ?array {
		$db = Database::get();
		return $db->get_one(
			'SELECT * FROM block_translation WHERE block_id = ? AND language_id = ? AND archived IS NULL',
			[ $block_id, $language_id ]
		);
	}

	/**
	 * Get all translations for a block
	 *
	 * @access public
	 * @param Block $block
	 * @return array
	 */
	public static function get_by_block(Block $block): array {
		$db = Database::get();
		$rows = $db->get_all(
			'SELECT * FROM block_translation WHERE block_id = ? AND archived IS NULL ORDER BY language_id ASC',
			[ $block->id ]
		);
		return $rows ?: [];
	}
}