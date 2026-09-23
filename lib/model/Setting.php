<?php

/**
 * Setting
 *
 * Key/value store for editable site settings (email, address, masthead
 * title, download password hash, etc.). Read via static helpers; admin
 * edits persist through the model.
 *
 * @author Roan Buysse <roan@tigron.be>
 */

use \Skeleton\Database\Database;

class Setting {
	use \Skeleton\Object\Model;
	use \Skeleton\Object\Uuid;
	use \Skeleton\Object\Get;
	use \Skeleton\Object\Save;
	use \Skeleton\Object\Delete;

	/**
	 * Cached settings
	 *
	 * @var array|null $cache
	 */
	private static ?array $cache = null;

	/**
	 * Get a setting value by name
	 *
	 * @access public
	 * @param string $name
	 * @return string|null
	 */
	public static function get_by_name(string $name): ?string {
		$all = self::get_all();
		return $all[$name] ?? null;
	}

	/**
	 * Get all active settings as name=>value array
	 *
	 * @access public
	 * @return array
	 */
	public static function get_all(): array {
		if (self::$cache !== null) {
			return self::$cache;
		}

		$db = Database::get();
		$rows = $db->get_all('SELECT name, value FROM setting WHERE archived IS NULL');
		self::$cache = [];
		if ($rows) {
			foreach ($rows as $row) {
				self::$cache[$row['name']] = $row['value'];
			}
		}
		return self::$cache;
	}

	/**
	 * Get all settings as Setting model instances
	 *
	 * @access public
	 * @return array
	 */
	public static function get_all_models(): array {
		$db = Database::get();
		$ids = $db->get_column('SELECT id FROM setting WHERE archived IS NULL ORDER BY name ASC');
		$results = [];
		foreach ($ids as $id) {
			$results[] = self::get_by_id((int)$id);
		}
		return $results;
	}

	/**
	 * Get a single model instance by name
	 *
	 * @access public
	 * @param string $name
	 * @return Setting
	 * @throws \Exception if not found
	 */
	public static function get_instance_by_name(string $name): self {
		$db = Database::get();
		$id = $db->get_one(
			'SELECT id FROM setting WHERE name = ? AND archived IS NULL',
			[ $name ]
		);
		if ($id === null) {
			throw new \Exception('Setting "' . $name . '" not found.');
		}
		return self::get_by_id((int)$id);
	}

	/**
	 * Set a value (inserts if not present)
	 *
	 * @access public
	 * @param string $name
	 * @param string|null $value
	 */
	public static function set_value(string $name, ?string $value): void {
		$db = Database::get();
		$db->query('DELETE FROM setting WHERE name = ?', [ $name ]);
		$db->query(
			'INSERT INTO setting (name, value, created) VALUES (?, ?, NOW())',
			[ $name, $value ]
		);
		self::$cache = null;
	}

	/**
	 * Validate before save
	 *
	 * @access public
	 * @param array &$errors
	 * @return bool
	 */
	public function validate(array &$errors = []): bool {
		$db = Database::get();
		$duplicate = $db->get_one(
			'SELECT COUNT(*) FROM `setting` WHERE `name` = ? AND (`id` != ? OR `id` IS NULL)',
			[ $this->name, $this->id ?? 0 ]
		);
		if ((int)$duplicate > 0) {
			$errors['name'] = 'duplicate';
		}
		return count($errors) === 0;
	}

	/**
	 * Invalidate cache after save/delete
	 *
	 * @access public
	 */
	public function post_save(): void {
		self::$cache = null;
	}

	/**
	 * Invalidate cache after delete
	 *
	 * @access public
	 */
	public function post_delete(): void {
		self::$cache = null;
	}
}