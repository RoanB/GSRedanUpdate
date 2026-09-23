<?php
/**
 * Country class
 *
 * @author Lionel Laffineur <lionel@tigron.be>
 */

use \Skeleton\Database\Database;

class Country {
	use \Skeleton\Object\Model;
	use \Skeleton\Object\Get;
	use \Skeleton\Object\Save;

	/**
	 * Get the ISO2 code of the country
	 *
	 * @access public
	 * @return string $iso2
	 */
	public function get_iso2() {
		return $this->code;
	}

	/**
	 * get_by_code
	 *
	 * @access public
	 * @param string $code
	 * @return Country
	 */
	public static function get_by_code(string $code): Country {
		$db = Database::get();
		$id = $db->get_one("SELECT id FROM country WHERE code = ?", [ $code ]);
		if ($id === null) {
			throw new \Exception('Not found');
		}
		return self::get_by_id($id);
	}

		/**
	 * Get grouped
	 *
	 * @access public
	 * @return array $countries
	 */
	public static function get_grouped(): array {
		$countries = [
			'european' => [],
			'rest' => []
		];

		$db = Database::get();
		$ids = $db->get_column('SELECT id FROM country WHERE european = 1', []);
		$countries['european'] = self::get_by_ids($ids);

		usort($countries['european'], function($a, $b) {
			return strcasecmp($a->name, $b->name);
		});


		$ids = $db->get_column('SELECT id FROM country WHERE european = 0', []);
		$countries['rest'] = self::get_by_ids($ids);

		usort($countries['rest'], function($a, $b) {
			return strcasecmp($a->name, $b->name);
		});

		return $countries;
	}

}
