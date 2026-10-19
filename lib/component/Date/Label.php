<?php

declare(strict_types=1);

/**
 * Date_Label
 *
 * Human date labels in the site languages, without an intl dependency:
 * PHP's IntlDateFormatter is not loaded on every host this project runs
 * on, and month names are a closed list anyway.
 *
 * @author Roan Buysse <roan@tigron.be>
 */

class Date_Label {
	/**
	 * Calendar month names, 1 = January
	 *
	 * @var array $months
	 */
	const MONTHS = [
		'fr' => [ 1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre' ],
		'nl' => [ 1 => 'januari', 'februari', 'maart', 'april', 'mei', 'juni', 'juli', 'augustus', 'september', 'oktober', 'november', 'december' ],
		'en' => [ 1 => 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December' ],
	];

	/**
	 * The month names of one language, falling back to French (the site's
	 * default language)
	 *
	 * @access public
	 * @param string $language_short
	 * @return array
	 */
	public static function get_month_names(string $language_short): array {
		return self::MONTHS[$language_short] ?? self::MONTHS['fr'];
	}

	/**
	 * A rally-style label for a date range
	 *
	 * Same month: every day of the span joined with " / " before the month
	 * and year — "10 / 11 / 12 septembre 2027", the shape the static site
	 * hardcoded. Single day: "10 septembre 2027". Anything wider (or a
	 * span over a week, where the day list stops being readable):
	 * "10 septembre 2027 – 12 octobre 2027".
	 *
	 * The datetimes are read as Brussels wall time (the same reading the
	 * countdown gives them). Unparsable input yields an empty string; the
	 * templates skip the date line when it is empty.
	 *
	 * @access public
	 * @param string $start Y-m-d H:i:s (or Y-m-d)
	 * @param string $end Y-m-d H:i:s (or Y-m-d)
	 * @param string $language_short
	 * @return string
	 */
	public static function format_range(string $start, string $end, string $language_short): string {
		$timezone = new \DateTimeZone(\Calendar_Event::DISPLAY_TIMEZONE);

		try {
			$start_date = new \DateTime($start, $timezone);
			$end_date = new \DateTime($end, $timezone);
		} catch (\Exception $e) {
			return '';
		}

		// A start after the end (an admin typo) would make the day list
		// below run backwards into nothing — normalize the order.
		if ($start_date > $end_date) {
			[ $start_date, $end_date ] = [ $end_date, $start_date ];
		}

		$months = self::get_month_names($language_short);
		$day = fn (\DateTime $date): string => $date->format('j') . ' ' . $months[(int)$date->format('n')] . ' ' . $date->format('Y');

		// Same day, or the end simply not set: one date.
		if ($start_date->format('Y-m-d') === $end_date->format('Y-m-d')) {
			return $day($start_date);
		}

		// Same month and a readable span: a list of day numbers.
		if ($start_date->format('Y-m') === $end_date->format('Y-m')) {
			$days = [];
			$current = clone $start_date;

			while ($current->format('Y-m-d') <= $end_date->format('Y-m-d')) {
				$days[] = (int)$current->format('j');
				$current->modify('+1 day');
			}

			if (count($days) <= 7) {
				return implode(' / ', $days) . ' ' . $months[(int)$end_date->format('n')] . ' ' . $end_date->format('Y');
			}
		}

		return $day($start_date) . ' – ' . $day($end_date);
	}
}
