<?php

/**
 * Block
 *
 * Represents one homepage section. The set of blocks is fixed by the seed
 * migration; admins reorder and hide them, and edit per-language content.
 *
 * @author Roan Buysse <roan@tigron.be>
 */

use \Skeleton\Database\Database;

class Block {
	use \Skeleton\Object\Model;
	use \Skeleton\Object\Uuid;
	use \Skeleton\Object\Get;
	use \Skeleton\Object\Save;
	use \Skeleton\Object\Delete {
		delete as trait_delete;
	}

	/**
	 * Block types an admin can create
	 *
	 * The generic "cards". The look (light / dark background, 2-row layout)
	 * is part of the card type; there is no separate layout knob.
	 * masthead, club, contact and monday_openings render with dedicated
	 * partials and stay singular (not creatable).
	 *
	 * @var array $creatable_types
	 */
	const CREATABLE_TYPES = [
		'activities_light',
		'activities_dark',
		'activities_pair_light',
		'activities_pair_dark',
		'gallery',
		'announcement',
	];

	/**
	 * Singular block types that only exist once
	 *
	 * Their layout does not multiply well (masthead is the hero, contact
	 * closes the page and monday_openings is calendar-fed), so the admin
	 * cannot create more of them.
	 *
	 * @var array $singular_types
	 */
	const SINGULAR_TYPES = [
		'masthead',
		'club',
		'contact',
		'monday_openings',
	];

	/**
	 * Known block types
	 *
	 * @var array $known_types
	 */
	const KNOWN_TYPES = [
		'masthead',
		'club',
		'activities_light',
		'activities_dark',
		'activities_pair_light',
		'activities_pair_dark',
		'gallery',
		'announcement',
		'contact',
		'monday_openings',
	];

	/**
	 * Human readable labels for the block types (admin + i18n keys)
	 *
	 * @var array $type_labels
	 */
	const TYPE_LABELS = [
		'masthead' => 'Masthead hero',
		'club' => 'Club text card',
		'activities_light' => 'Activity card (light background)',
		'activities_dark' => 'Activity card (dark background)',
		'activities_pair_light' => 'Activity 2-row card (light background)',
		'activities_pair_dark' => 'Activity 2-row card (dark background)',
		'gallery' => 'Gallery card (pictures row)',
		'announcement' => 'Announcement card',
		'contact' => 'Contact card',
		'monday_openings' => 'Monday openings card (calendar-fed)',
	];

	/**
	 * Marker stitched between the two texts of a pair card
	 *
	 * The migration carrier adds the archived second block id, which the
	 * split regex below tolerates.
	 *
	 * @var string $pair_separator_pattern
	 */
	const PAIR_SEPARATOR_PATTERN = '/<!--\s*pair(?::\d+)?-->/';

	/**
	 * Validate before save
	 *
	 * @access public
	 * @param array &$errors
	 * @return bool
	 */
	public function validate(array &$errors = []): bool {
		$errors = [];

		if (empty($this->type) || !in_array($this->type, self::KNOWN_TYPES, true)) {
			$errors['type'] = 'invalid';
		}

		// An empty anchor is valid: masthead, gallery, youth and footer have no
		// HTML id in the source site. Only check uniqueness when one is set.
		if (empty($this->anchor) === false) {
			$db = Database::get();
			$duplicate = $db->get_one(
				'SELECT COUNT(*) FROM `block` WHERE `anchor` = ? AND (`id` != ? OR `id` IS NULL)',
				[ $this->anchor, $this->id ?? 0 ]
			);
			if ((int)$duplicate > 0) {
				$errors['anchor'] = 'duplicate';
			}
		}

		return count($errors) === 0;
	}

	/**
	 * Get all blocks ordered by sort_order
	 *
	 * @access public
	 * @return array
	 */
	public static function get_all_ordered(): array {
		$db = Database::get();
		$ids = $db->get_column(
			'SELECT id FROM block WHERE archived IS NULL ORDER BY sort_order ASC, id ASC'
		);

		$results = [];
		foreach ($ids as $id) {
			$results[] = self::get_by_id((int)$id);
		}

		return $results;
	}

	/**
	 * Get visible blocks ordered by sort_order
	 *
	 * @access public
	 * @return array
	 */
	public static function get_visible_ordered(): array {
		$db = Database::get();
		$ids = $db->get_column(
			'SELECT id FROM block WHERE visible = 1 AND archived IS NULL ORDER BY sort_order ASC, id ASC'
		);

		$results = [];
		foreach ($ids as $id) {
			$results[] = self::get_by_id((int)$id);
		}

		return $results;
	}

	/**
	 * Get the pictures attached to this gallery block
	 *
	 * @access public
	 * @return array Block_Picture objects in sort order
	 */
	public function get_pictures(): array {
		if (in_array($this->type, [ 'gallery' ], true) === false) {
			return [];
		}

		return Block_Picture::get_by_block($this);
	}

	/**
	 * Is this block draggable in the admin list?
	 *
	 * The masthead is the fixed hero: its position is always first, so it is
	 * not drag & drop reorderable. The Monday openings card is locked too,
	 * but draggable (it may be moved anywhere the club wants).
	 *
	 * @access public
	 * @return bool
	 */
	public function is_draggable(): bool {
		return $this->type !== 'masthead';
	}

	/**
	 * Get translation for the given language, falling back to FR
	 *
	 * @access public
	 * @param \Language $language
	 * @return Block_Translation
	 */
	public function get_translation(\Language $language): Block_Translation {
		try {
			return Block_Translation::get_by_block_language($this, $language);
		} catch (\Exception $e) {
			return Block_Translation::get_by_block_language($this, \Language::get_default());
		}
	}

	/**
	 * Persist a new sort order from an array of ids
	 *
	 * @access public
	 * @param array $ids  ordered list of block ids
	 */
	public static function save_order(array $ids): void {
		$db = Database::get();
		foreach ($ids as $sort_order => $id) {
			$db->query(
				'UPDATE `block` SET `sort_order` = ? WHERE `id` = ?',
				[ $sort_order, (int)$id ]
			);
		}
	}

	/**
	 * Count blocks
	 *
	 * @access public
	 * @return int
	 */
	public static function count_active(): int {
		$db = Database::get();
		return (int)$db->get_one('SELECT COUNT(*) FROM block WHERE visible = 1 AND archived IS NULL');
	}

	/**
	 * May this card be removed in the admin?
	 *
	 * The fixed singular cards (masthead, club, contact, monday_openings)
	 * are part of the page structure and cannot be deleted; creatable cards
	 * can.
	 *
	 * @access public
	 * @return bool
	 */
	public function is_deletable(): bool {
		return in_array($this->type, self::SINGULAR_TYPES, true) === false;
	}

	/**
	 * Delete this card
	 *
	 * Fixed singular cards (masthead / club / contact / monday_openings)
	 * refuse. For creatable cards: cascade-deletes translations, full rows
	 * and the underlying picture files; references are cleared before the
	 * file rows go (block.file_id and block_picture.file_id FKs).
	 *
	 * @access public
	 */
	public function delete(): void {
		if ($this->is_deletable() === false) {
			throw new \Exception('The ' . $this->type . ' card cannot be deleted: it is a fixed part of the page structure');
		}

		$db = Database::get();

		// Collect the file ids of all attached images...
		$file_ids = [];
		foreach (Block_Picture::get_by_block($this) as $block_picture) {
			$file_ids[] = (int)$block_picture->file_id;
		}
		if ((int)$this->file_id > 0) {
			$file_ids[] = (int)$this->file_id;
		}

		// ...clear the referencing rows first (block.file_id FK and
		// block_picture.file_id FK would otherwise block the file delete)...
		$db->query('DELETE FROM `block_translation` WHERE `block_id` = ?', [ $this->id ]);
		$db->query('DELETE FROM `block_picture` WHERE `block_id` = ?', [ $this->id ]);
		$db->query('UPDATE `block` SET `file_id` = NULL WHERE `id` = ?', [ $this->id ]);

		// ...then delete the underlying picture files, and only the block.
		foreach ($file_ids as $file_id) {
			try {
				\Skeleton\File\Picture\Picture::get_by_id($file_id)->delete();
			} catch (\Exception $e) {
				// The file row was already gone.
			}
		}

		$this->trait_delete();
	}

	/**
	 * The activity card image as a Picture (null when absent)
	 *
	 * @access public
	 * @return ?\Skeleton\File\Picture\Picture
	 */
	public function get_activity_picture(): ?\Skeleton\File\Picture\Picture {
		if ((int)$this->file_id <= 0) {
			return null;
		}

		try {
			return \Skeleton\File\Picture\Picture::get_by_id((int)$this->file_id);
		} catch (\Exception $e) {
			return null;
		}
	}

	/**
	 * The second (bottom-row) image of a pair card as a Picture
	 *
	 * @access public
	 * @return ?\Skeleton\File\Picture\Picture
	 */
	public function get_second_picture(): ?\Skeleton\File\Picture\Picture {
		if ((int)$this->file_id_2 <= 0) {
			return null;
		}

		try {
			return \Skeleton\File\Picture\Picture::get_by_id((int)$this->file_id_2);
		} catch (\Exception $e) {
			return null;
		}
	}

	/**
	 * Is this card a 2-row pair card?
	 *
	 * @access public
	 * @return bool
	 */
	public function is_pair(): bool {
		return in_array($this->type, [ 'activities_pair_light', 'activities_pair_dark' ], true);
	}

	/**
	 * Split a pair body into its two row texts
	 *
	 * The marker sits between the top and bottom text of a pair card; a
	 * body without marker applies to the top row only.
	 *
	 * @access public
	 * @param string $body
	 * @return array [ top_text, bottom_text ]
	 */
	public static function split_pair_body(string $body): array {
		if (preg_match(self::PAIR_SEPARATOR_PATTERN, $body) !== 1) {
			return [ $body, '' ];
		}

		$parts = preg_split(self::PAIR_SEPARATOR_PATTERN, $body);
		if ($parts === false) {
			return [ $body, '' ];
		}

		return [ $parts[0], $parts[1] ?? '' ];
	}

	/**
	 * Stitch the two row texts of a pair card back into one body
	 *
	 * @access public
	 * @param string $top
	 * @param string $bottom
	 * @return string
	 */
	public static function stitch_pair_body(string $top, string $bottom): string {
		return $top . "\n<!--pair-->\n" . $bottom;
	}

	/**
	 * Count all blocks
	 *
	 * @access public
	 * @return int
	 */
	public static function count_all(): int {
		$db = Database::get();
		return (int)$db->get_one('SELECT COUNT(*) FROM block WHERE archived IS NULL');
	}
}