<?php

declare(strict_types=1);

/**
 * Calendar_Category
 *
 * Colour-coded calendar label.
 *
 * @author Roan Buysse <roan@tigron.be>
 */

use \Skeleton\Database\Database;

class Calendar_Category {
	use \Skeleton\Object\Model;
	use \Skeleton\Object\Uuid;
	use \Skeleton\Object\Get;
	use \Skeleton\Object\Save;
	use \Skeleton\Object\Delete {
		delete as trait_delete;
	}

	/**
	 * Standard error colour (fallback when a category has none)
	 *
	 * @var string $default_color
	 */
	const DEFAULT_COLOR = '#9ab03e';

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

		if (preg_match('/^#[0-9a-fA-F]{6}$/', (string)$this->color) !== 1) {
			$errors['color'] = 'invalid';
		}

		$duplicate = Database::get()->get_one(
			'SELECT COUNT(*) FROM `calendar_category` WHERE `name` = ? AND (`id` != ? OR `id` IS NULL)',
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
	 * @access public
	 * @return bool
	 */
	public function is_deletable(): bool {
		$count = Database::get()->get_one(
			'SELECT COUNT(*) FROM `calendar_event` WHERE `calendar_category_id` = ? AND `archived` IS NULL',
			[ $this->id ]
		);

		return (int)$count === 0;
	}

	/**
	 * The category colour (falls back to the shared default)
	 *
	 * @access public
	 * @return string
	 */
	public function get_color(): string {
		if (empty($this->color) === false && preg_match('/^#[0-9a-fA-F]{6}$/', (string)$this->color) === 1) {
			return (string)$this->color;
		}

		return self::DEFAULT_COLOR;
	}

	/**
	 * Black or white text for a background hex colour
	 *
	 * Compares the WCAG relative luminance of the background against the
	 * crossover point where black and white text reach equal contrast
	 * (luminance 0.179): dark backgrounds get white text, light ones black.
	 * Used for the calendar chip labels so the event text stays readable on
	 * every category colour.
	 *
	 * @access public
	 * @param string $background Six-char hex colour (invalid input → black)
	 * @return string
	 */
	public static function best_text_color(string $background): string {
		if (preg_match('/^#[0-9a-fA-F]{6}$/', $background) !== 1) {
			return '#000000';
		}

		$linear = function (float $channel): float {
			if ($channel <= 0.03928) {
				return $channel / 12.92;
			}

			return pow((($channel + 0.055) / 1.055), 2.4);
		};

		$red = $linear(hexdec(substr($background, 1, 2)) / 255);
		$green = $linear(hexdec(substr($background, 3, 2)) / 255);
		$blue = $linear(hexdec(substr($background, 5, 2)) / 255);
		$luminance = 0.2126 * $red + 0.7152 * $green + 0.0722 * $blue;

		if ($luminance < 0.179) {
			return '#ffffff';
		}

		return '#000000';
	}

	/**
	 * The category whose events feed the Monday openings card
	 *
	 * @var string $opening_category_name
	 */
	const OPENING_CATEGORY_NAME = 'Ouverture de la salle';

	/**
	 * All categories ordered
	 *
	 * @access public
	 * @return array Calendar_Category
	 */
	public static function get_all_ordered(): array {
		$ids = Database::get()->get_column(
			'SELECT id FROM calendar_category WHERE archived IS NULL ORDER BY sort_order ASC, id ASC'
		);

		$categories = [];
		foreach ($ids as $id) {
			$categories[] = self::get_by_id((int)$id);
		}

		return $categories;
	}

	/**
	 * Get the category by (unique) name
	 *
	 * @access public
	 * @param string $name
	 * @return ?self (null when unknown or archived)
	 */
	public static function get_by_name(string $name): ?self {
		$id = Database::get()->get_one(
			'SELECT id FROM calendar_category WHERE name = ? AND archived IS NULL',
			[ $name ]
		);

		if ($id === null) {
			return null;
		}

		return self::get_by_id((int)$id);
	}

	/**
	 * The category that feeds the Monday openings card
	 *
	 * @access public
	 * @return ?self
	 */
	public static function get_opening_category(): ?self {
		return self::get_by_name(self::OPENING_CATEGORY_NAME);
	}

	/**
	 * Count the visible events in this category
	 *
	 * @access public
	 * @return int
	 */
	public function count_visible_events(): int {
		$count = Database::get()->get_one(
			'SELECT COUNT(*) FROM `calendar_event` WHERE `calendar_category_id` = ? AND `visible` = 1 AND `archived` IS NULL',
			[ $this->id ]
		);

		return (int)$count;
	}
}
