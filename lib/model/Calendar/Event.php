<?php

declare(strict_types=1);

/**
 * Calendar_Event
 *
 * A single calendar entry rendered on the members page and exported as
 * ICS / served over the read-only CalDAV endpoint (spec/07). All times
 * stored in UTC; rendering uses Europe/Brussels.
 *
 * @author Roan Buysse <roan@tigron.be>
 */

use \Skeleton\Database\Database;

class Calendar_Event {
	use \Skeleton\Object\Model;
	use \Skeleton\Object\Uuid;
	use \Skeleton\Object\Get;
	use \Skeleton\Object\Save;
	use \Skeleton\Object\Delete;

	/**
	 * The site timezone for rendering and admin input
	 *
	 * @var string $display_timezone
	 */
	const DISPLAY_TIMEZONE = 'Europe/Brussels';

	/**
	 * Validate before save
	 *
	 * @access public
	 * @param array &$errors
	 * @return bool
	 */
	public function validate(array &$errors = []): bool {
		$errors = [];

		if (trim((string)$this->title) === '') {
			$errors['title'] = 'required';
		}

		if (empty($this->starts_at)) {
			$errors['starts_at'] = 'required';
		}

		if (strtotime((string)$this->ends_at ?? '') !== false && (string)$this->ends_at !== '' && strtotime((string)$this->ends_at) < strtotime((string)$this->starts_at)) {
			$errors['ends_at'] = 'before_start';
		}

		return count($errors) === 0;
	}

	/**
	 * All events (admin list), newest first
	 *
	 * @access public
	 * @return array Calendar_Event
	 */
	public static function get_all_ordered(): array {
		$ids = Database::get()->get_column(
			'SELECT id FROM calendar_event WHERE archived IS NULL ORDER BY starts_at DESC'
		);

		return self::get_by_ids($ids);
	}

	/**
	 * Visible upcoming events for the members page, earliest first
	 *
	 * @access public
	 * @param int $limit
	 * @return array Calendar_Event
	 */
	public static function get_visible_upcoming(int $limit = 10): array {
		$ids = Database::get()->get_column(
			'SELECT id FROM calendar_event
			WHERE archived IS NULL
			AND visible = 1
			AND DATE(COALESCE(ends_at, starts_at)) >= DATE(NOW())
			ORDER BY starts_at ASC
			LIMIT ' . (int)$limit
		);

		return self::get_by_ids($ids);
	}

	/**
	 * Upcoming events for the admin dashboard, earliest first
	 *
	 * Same window as get_visible_upcoming() but without the visibility
	 * filter: the dashboard is a management view, so hidden (scheduled but
	 * not yet published) events show up there too, flagged as such.
	 *
	 * @access public
	 * @param int $limit
	 * @return array Calendar_Event
	 */
	public static function get_upcoming(int $limit = 10): array {
		$ids = Database::get()->get_column(
			'SELECT id FROM calendar_event
			WHERE archived IS NULL
			AND DATE(COALESCE(ends_at, starts_at)) >= DATE(NOW())
			ORDER BY starts_at ASC
			LIMIT ' . (int)$limit
		);

		return self::get_by_ids($ids);
	}

	/**
	 * Upcoming events pre-formatted for the list widgets
	 *
	 * Builds the display rows used by the members page, the members
	 * calendar tab and the admin dashboard: start date and time rendered
	 * in Europe/Brussels (d/m/Y, H:i), the time skipped for all-day
	 * events, plus the event id, a visibility flag and the category id so
	 * the admin variant can link to its edit screen, badge its category
	 * and mark hidden rows.
	 *
	 * @access public
	 * @param int $limit
	 * @param bool $visible_only
	 * @return array of display rows
	 */
	public static function get_upcoming_entries(int $limit = 10, bool $visible_only = true): array {
		$timezone = new \DateTimeZone(self::DISPLAY_TIMEZONE);

		$events = $visible_only ? self::get_visible_upcoming($limit) : self::get_upcoming($limit);

		$entries = [];
		foreach ($events as $event) {
			$starts = new \DateTime((string)$event->starts_at, new \DateTimeZone('UTC'));
			$starts->setTimezone($timezone);

			$entry = [
				'id' => (int)$event->id,
				'title' => (string)$event->title,
				'location' => (string)$event->location,
				'all_day' => (int)$event->all_day === 1,
				'visible' => (int)$event->visible === 1,
				'calendar_category_id' => (int)$event->calendar_category_id,
				'starts_label' => $starts->format('d/m/Y'),
				'time_label' => (int)$event->all_day === 1 ? '' : $starts->format('H:i'),
			];

			if ((string)$event->ends_at !== '' && $event->ends_at !== null && (int)$event->all_day !== 1) {
				$ends = new \DateTime((string)$event->ends_at, new \DateTimeZone('UTC'));
				$ends->setTimezone($timezone);
				$entry['ends_label'] = $ends->format('H:i');
			}

			$entries[] = $entry;
		}

		return $entries;
	}

	/**
	 * Visible upcoming events of one category, earliest first
	 *
	 * Feeds the Monday openings card (spec/03 cards rework): the card is
	 * filled from the calendar, per calendar category.
	 *
	 * @access public
	 * @param int $category_id
	 * @param int $limit
	 * @return array Calendar_Event
	 */
	public static function get_visible_upcoming_by_category(int $category_id, int $limit = 8): array {
		if ($category_id <= 0) {
			return [];
		}

		$ids = Database::get()->get_column(
			'SELECT id FROM calendar_event
			WHERE archived IS NULL
			AND visible = 1
			AND calendar_category_id = ?
			AND DATE(COALESCE(ends_at, starts_at)) >= DATE(NOW())
			ORDER BY starts_at ASC
			LIMIT ' . (int)$limit,
			[ $category_id ]
		);

		return self::get_by_ids($ids);
	}

	/**
	 * Localized month-grouped labels of opening dates
	 *
	 * Renders the openings like the original salle card: dates grouped
	 * per month ("7th and 21st of September", FR "7 et 21 Septembre"),
	 * multiple dates per group joined with the language's "and", always
	 * ascending, pre-formatted as <br> unsigned list markup.
	 *
	 * @access public
	 * @param array $events Calendar_Event objects (all_day also fine)
	 * @param string $language_short fr/nl/en
	 * @return string html fragment joined with <br> (may be an empty string)
	 */
	public static function format_opening_dates(array $events, string $language_short): string {
		if (count($events) === 0) {
			return '';
		}

		$settings = [
			'fr' => [ 'locale' => 'fr_FR', 'connective' => 'et' ],
			'nl' => [ 'locale' => 'nl_BE', 'connective' => 'en' ],
			'en' => [ 'locale' => 'en_GB', 'connective' => 'and' ],
		];
		$config = $settings[$language_short] ?? $settings['fr'];

		$timezone = new \DateTimeZone(\Calendar_Event::DISPLAY_TIMEZONE);

		// Group per (month number, month label), keeping chronological order.
		$formatter = new \IntlDateFormatter($config['locale'], \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, $timezone, \IntlDateFormatter::GREGORIAN, 'MMMM');

		$groups = [];
		foreach ($events as $event) {
			$starts = new \DateTime((string)$event->starts_at, new \DateTimeZone('UTC'));
			$starts->setTimezone($timezone);

			$month_key = $starts->format('Y-m');
			$month_name = (string)$formatter->format($starts);
			$month_name = mb_strtoupper(mb_substr($month_name, 0, 1)) . mb_substr($month_name, 1);

			$day = (int)$starts->format('j');
			if ($language_short === 'en') {
				$day = $day . self::ordinal_suffix((int)$starts->format('j'));
			}

			if (isset($groups[$month_key]) === false) {
				$groups[$month_key] = [ 'name' => $month_name, 'days' => [] ];
			}

			$groups[$month_key]['days'][] = $day;
		}

		$labels = [];
		foreach ($groups as $group) {
			$days = $group['days'];
			$connective = $config['connective'];

			if (count($days) === 1) {
				$grouped = (string)reset($days);
			} else {
				$last = array_pop($days);
				$grouped = implode(', ', $days) . ' ' . $connective . ' ' . $last;
			}

			if ($language_short === 'en') {
				$labels[] = $grouped . ' of ' . $group['name'];

				continue;
			}

			$labels[] = $grouped . ' ' . $group['name'];
		}

		$out = '';
		foreach ($labels as $label) {
			$out = $out === '' ? $label : $out . '<br>' . $label;
		}

		return $out;
	}

	/**
	 * English ordinal suffix of a day number (st/nd/rd/th)
	 *
	 * @access private
	 * @param int $number
	 * @return string
	 */
	private static function ordinal_suffix(int $number): string {
		if ($number % 100 >= 11 && $number % 100 <= 13) {
			return 'th';
		}

		switch ($number % 10) {
			case 1:
				return 'st';
			case 2:
				return 'nd';
			case 3:
				return 'rd';
		}

		return 'th';
	}

	/**
	 * ICS feed of the visible events
	 *
	 * @access public
	 * @return string
	 */
	public static function get_ics(): string {
		$lines = [
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:-//GS Redan//Members calendar 1.0//EN',
			'CALSCALE:GREGORIAN',
			'METHOD:PUBLISH',
			'X-WR-CALNAME:GSRedan',
			'X-WR-TIMEZONE:Europe/Brussels',
		];

		$events = self::get_all_ordered();
		$timezone = new \DateTimeZone(self::DISPLAY_TIMEZONE);

		foreach ($events as $event) {
			if ((int)$event->visible !== 1) {
				continue;
			}

			$lines = array_merge($lines, $event->get_ics_vevent($timezone));
		}

		$lines[] = 'END:VCALENDAR';

		return implode("\r\n", $lines) . "\r\n";
	}

	/**
	 * The VEVENT lines for this event
	 *
	 * @access public
	 * @param \DateTimeZone $timezone
	 * @return array of ICS line strings
	 */
	public function get_ics_vevent(\DateTimeZone $timezone): array {
		$lines = [
			'BEGIN:VEVENT',
			'UID:' . $this->uuid,
		];

		// UTC handling: ICS carries real "Z" timestamps independent of the
		// display timezone; all_day events use DATE values without time.
		if ((int)$this->all_day === 1) {
			$lines[] = 'DTSTART;VALUE=DATE:' . (new \DateTime((string)$this->starts_at))->format('Ymd');
			if ((string)$this->ends_at !== '' && $this->ends_at !== null) {
				$lines[] = 'DTEND;VALUE=DATE:' . (new \DateTime((string)$this->ends_at))->format('Ymd');
			}
		} else {
			$lines[] = 'DTSTART:' . $this->format_utc((string)$this->starts_at);
			if ((string)$this->ends_at !== '' && $this->ends_at !== null) {
				$lines[] = 'DTEND:' . $this->format_utc((string)$this->ends_at);
			}
		}

		$lines[] = 'SUMMARY:' . $this->escape_ics((string)$this->title);
		if ((string)$this->location !== '' && $this->location !== null) {
			$lines[] = 'LOCATION:' . $this->escape_ics((string)$this->location);
		}
		if ((string)$this->description !== '' && $this->description !== null) {
			$lines[] = 'DESCRIPTION:' . $this->escape_ics((string)$this->description);
		}

		$dtstamp = new \DateTime('now', new \DateTimeZone('UTC'));
		$lines[] = 'DTSTAMP:' . $dtstamp->format('Ymd\THis\Z');
		$lines[] = 'END:VEVENT';

		return $lines;
	}

	/**
	 * Format a stored datetime into a UTC ICS timestamp
	 *
	 * @access private
	 * @param string $datetime UTC datetime string (stored in UTC)
	 */
	private function format_utc(string $datetime): string {
		return (new \DateTime($datetime, new \DateTimeZone('UTC')))->format('Ymd\THis\Z');
	}

	/**
	 * Escape an ICS text value (comma, semicolon, backslash, newlines)
	 *
	 * ICS fold limit is ignored: mainstream clients accept long lines, and
	 * folding complicates the CalDAV XML wrapping.
	 *
	 * @access private
	 * @param string $value
	 */
	private function escape_ics(string $value): string {
		$value = str_replace('\\', '\\\\', $value);
		$value = str_replace([ "\r\n", "\n", "\r" ], "\\n", $value);
		$value = str_replace([ ';', ',' ], [ '\;', '\,' ], $value);

		return $value;
	}
}
