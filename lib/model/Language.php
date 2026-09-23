<?php
/**
 * Language class
 *
 * @author Lionel Laffineur <lionel@tigron.be>
 */

use \Skeleton\Database\Database;

class Language extends \Skeleton\I18n\Language {

	/**
	 * Get default Language
	 *
	 * @access public
	 * @return Language
	 */
	public static function get_default() {
		return self::get_by_name_short(\Skeleton\Core\Config::Get()->default_language);
	}

}
