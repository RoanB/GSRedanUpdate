<?php

declare(strict_types=1);

/**
 * Admin Calendar
 *
 * CRUD of the calendar events served on the members page and through ICS /
 * read-only CalDAV (spec/07). Times are edited in Europe/Brussels and
 * stored in UTC; `all_day` events carry no end time on the page.
 *
 * @author Roan Buysse <roan@tigron.be>
 */

namespace App\Front\Module\Admin;

class Calendar extends \Skeleton\Application\Web\Module {
	/**
	 * Login required
	 *
	 * @var bool $login_required
	 */
	protected bool $login_required = true;

	/**
	 * Secure
	 *
	 * @access public
	 * @return bool
	 */
	public function secure(): bool {
		return isset($_SESSION['user']) && $_SESSION['user']->is_admin();
	}

	/**
	 * List the events
	 *
	 * @access public
	 */
	public function display(): void {
		$this->template = 'admin/calendar.twig';

		$template = \Skeleton\Application\Web\Template::get();

		$template->assign('server_scheme', $_SERVER['REQUEST_SCHEME'] ?? 'https');
		$template->assign('server_host', $_SERVER['HTTP_HOST'] ?? '');

		$rows = [];
		foreach (\Calendar_Event::get_all_ordered() as $event) {
			$category_name = '';
			if ((int)$event->calendar_category_id > 0) {
				try {
					$rows_category = \Calendar_Category::get_by_id((int)$event->calendar_category_id);
					$category_name = $rows_category->name;
				} catch (\Exception $e) {
				}
			}

			$rows[] = [
				'id' => $event->id,
				'title' => $event->title,
				'starts_label' => $this->format_datetime((string)$event->starts_at),
				'ends_label' => $event->ends_at !== null && (string)$event->ends_at !== '' ? $this->format_datetime((string)$event->ends_at) : '',
				'all_day' => (int)$event->all_day,
				'visible' => $event->visible,
				'category' => $category_name,
			];
		}

		$template->assign('events', $rows);
		$template->assign('calendar_categories', $this->category_options());
	}

	/**
	 * Create a new event
	 *
	 * @access public
	 */
	public function display_add(): void {
		if (isset($_POST['title']) === false) {
			\Skeleton\Core\Http\Session::redirect('/admin/calendar');
		}

		$event = new \Calendar_Event();
		$errors = [];
		$this->fill_from_post($event, $errors);

		if (count($errors) === 0 && $event->validate($errors) === true) {
			$event->save();
			\Skeleton\Core\Http\Session::redirect('/admin/calendar?saved=1');
		}

		\Skeleton\Core\Http\Session::redirect('/admin/calendar?add_failed=1');
	}

	/**
	 * Edit an event
	 *
	 * @access public
	 */
	public function display_edit(): void {
		$this->template = 'admin/calendar_edit.twig';

		$template = \Skeleton\Application\Web\Template::get();

		$event_id = (int)($_GET['id'] ?? 0);
		$event = null;

		if ($event_id === 0) {
			// "Add event": blank form, saved through ?action=add.
			$event = new \Calendar_Event();
		} else {
			$event = \Calendar_Event::get_by_id($event_id);
		}

		$template->assign('event', $event);
		$template->assign('calendar_categories', $this->category_options());

		// The stored UTC values do not prefill in <input type="datetime-local">
		// (expects YYYY-MM-DDTHH:MM); expose Brussels-time values for the form.
		$event_display = [];
		foreach ([ 'starts_at_display', 'ends_at_display' ] as $key) {
			$property = str_replace('_display', '', $key);
			$value = (string)($event->{$property} ?? '');

			if ($value !== '') {
				try {
					$date = new \DateTime($value, new \DateTimeZone('UTC'));
					$date->setTimezone(new \DateTimeZone(\Calendar_Event::DISPLAY_TIMEZONE));
					$value = $date->format('Y-m-d\TH:i');
				} catch (\Exception $e) {
					$value = '';
				}
			}

			$template->assign($key, $value);
		}

		if (isset($_POST['title'])) {
			$errors = [];
			$this->fill_from_post($event, $errors);

			if (count($errors) === 0 && $event->validate($errors) === true) {
				$event->save();
				\Skeleton\Core\Http\Session::redirect('/admin/calendar?saved=1');
			}

			$template->assign('errors', $errors);
		}
	}

	/**
	 * Delete an event
	 *
	 * @access public
	 */
	public function display_delete(): void {
		$this->template = 'admin/calendar_edit.twig';

		try {
			$event = \Calendar_Event::get_by_id((int)($_GET['id'] ?? 0));
		} catch (\Exception $e) {
			\Skeleton\Core\Http\Session::redirect('/admin/calendar');
		}

		$event->delete();

		\Skeleton\Core\Http\Session::redirect('/admin/calendar?saved=1');
	}

	/**
	 * Option list for the category selects
	 *
	 * @access private
	 */
	private function category_options(): array {
		$options = [];
		foreach (\Calendar_Category::get_all_ordered() as $category) {
			$color = $category->get_color();
			$options[] = [
				'id' => $category->id,
				'name' => $category->name,
				'color' => $color,
				'text_color' => \Calendar_Category::best_text_color($color),
			];
		}

		return $options;
	}

	/**
	 * Events for the fullcalendar month view (JSON)
	 *
	 * @access public
	 */
	public function display_events(): void {
		$this->template = null;

		$timezone = new \DateTimeZone(\Calendar_Event::DISPLAY_TIMEZONE);

		$events = [];
		foreach (\Calendar_Event::get_all_ordered() as $event) {
			$starts = $this->to_frontend_datetime((string)$event->starts_at, false, (int)$event->all_day === 1);
			$ends = $this->to_frontend_datetime((string)$event->ends_at, true, (int)$event->all_day === 1);

			// Category colour (default lime when no category set).
			$category_color = \Calendar_Category::DEFAULT_COLOR;
			$category_name = '';
			$category_id = 0;

			if ((int)$event->calendar_category_id > 0) {
				try {
					$calendar_category = \Calendar_Category::get_by_id((int)$event->calendar_category_id);
					$category_color = $calendar_category->get_color();
					$category_name = $calendar_category->name;
					$category_id = (int)$calendar_category->id;
				} catch (\Exception $e) {
				}
			}

			// White text on dark category colours, black on light ones: the
			// chip label stays readable whatever the category colour is.
			$chip_background = (int)$event->visible === 1 ? $category_color : '#e9ecef';

			$events[] = [
				'id' => $event->id,
				'title' => $event->title,
				'start' => $starts,
				'end' => $ends,
				'allDay' => (int)$event->all_day === 1,
				'url' => '/admin/calendar?action=edit&id=' . $event->id,
				'color' => $chip_background,
				'textColor' => \Calendar_Category::best_text_color($chip_background),
				'category' => ['id' => $category_id, 'name' => $category_name],
				'visible' => (int)$event->visible === 1,
				'className' => (int)$event->all_day === 1 ? 'calendar-all-day' : '',
				'extendedProps' => [
					'location' => (string)$event->location,
					'description' => (string)$event->description,
				],
			];
		}

		header('Content-Type: application/json; charset=utf-8');
		header('Cache-Control: no-store');
		echo json_encode($events);
		exit;
	}

	/**
	 * Format a stored UTC datetime for the JS calendar
	 *
	 * @access private
	 * @param ?string $datetime
	 * @param bool $is_end
	 */
	private function to_frontend_datetime(?string $datetime, bool $is_end = false, bool $all_day = false): ?string {
		if ($datetime === null || $datetime === '') {
			return null;
		}

		try {
			$date = new \DateTime($datetime, new \DateTimeZone('UTC'));
			$date->setTimezone(new \DateTimeZone(\Calendar_Event::DISPLAY_TIMEZONE));
		} catch (\Exception $e) {
			return null;
		}

		// Fullcalendar wants dates only (no Z) for all-day events; the
		// exclusive end day becomes the next day so fullcalendar displays
		// single-day all-day entries correctly.
		if ($all_day === true) {
			if ($is_end === true) {
				$date->modify('+1 day');
			}

			return $date->format('Y-m-d');
		}

		return $date->format('Y-m-d\TH:i:s');
	}

	/**
	 * Toggle event visibility (AJAX)
	 *
	 * @access public
	 */
	public function display_visibility(): void {
		$event_id = (int)($_POST['id'] ?? 0);

		try {
			$event = \Calendar_Event::get_by_id($event_id);
		} catch (\Exception $e) {
			echo json_encode([]);
			exit;
		}

		$event->visible = $event->visible ? 0 : 1;
		$event->save();

		echo json_encode(['visible' => (bool)$event->visible]);
		exit;
	}

	/**
	 * Fill an event from the posted datetime-local values
	 *
	 * Datetimes arrive in Europe/Brussels (YYYY-MM-DD HH:MM) and are stored
	 * in UTC (spec/07).
	 *
	 * @access private
	 */
	private function fill_from_post(\Calendar_Event $event, array &$errors): void {
		$title = trim((string)($_POST['title'] ?? ''));
		$description = (string)($_POST['description'] ?? '');
		$location = trim((string)($_POST['location'] ?? ''));
		$start_post = str_replace('T', ' ', (string)($_POST['starts_at'] ?? ''));
		$end_post = str_replace('T', ' ', trim((string)($_POST['ends_at'] ?? '')));
		$all_day = ((int)($_POST['all_day'] ?? 0) === 1);

		$event->title = $title;
		$event->description = $description;
		$event->location = $location;
		$event->all_day = $all_day;

		$calendar_category_id = (int)($_POST['calendar_category_id'] ?? 0);

		if ($calendar_category_id > 0) {
			try {
				\Calendar_Category::get_by_id($calendar_category_id);
				$event->calendar_category_id = $calendar_category_id;
			} catch (\Exception $e) {
				$errors['calendar_category_id'] = 'invalid';

				return;
			}
		} else {
			$event->calendar_category_id = null;
		}

		$start = $this->parse_local_datetime($start_post);

		if ($start === null) {
			$errors['starts_at'] = 'invalid';
			return;
		}

		if ((int)$event->all_day === 1) {
			// All day events are stored as local midnight and exported as a
			// DATE value (spec/07); the end date is optional.
			$event->starts_at = $start->format('Y-m-d H:i:00');

			$event->ends_at = null;
			if ($end_post !== '') {
				$end = $this->parse_local_datetime($end_post);
				if ($end !== null) {
					$event->ends_at = $end->format('Y-m-d H:i:s');
				}
			}

			return;
		}

		$utc = new \DateTimeZone('UTC');
		$starts = clone $start;
		$starts->setTimezone($utc);
		$event->starts_at = $starts->format('Y-m-d H:i:s');

		$event->ends_at = null;
		$ends = null;
		if ($end_post !== '') {
			$end = $this->parse_local_datetime($end_post);
			if ($end === null) {
				$errors['ends_at'] = 'invalid';
				return;
			}

			$ends = clone $end;
			$ends->setTimezone(new \DateTimeZone('UTC'));
			$event->ends_at = $ends->format('Y-m-d H:i:s');
		}

		if ($ends !== null && $ends < $starts) {
			$errors['ends_at'] = 'before_start';
		}
	}

	/**
	 * Parse a posted datetime into a Brussels DateTime
	 *
	 * Handles the 'T', minute-only and date-only variants of datetime-local
	 * submissions.
	 *
	 * @access private
	 * @param string $value
	 * @return ?\DateTime
	 */
	private function parse_local_datetime(string $value): ?\DateTime {
		$value = trim($value);

		foreach ([ 'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d' ] as $format) {
			$datetime = \DateTime::createFromFormat($format, $value, new \DateTimeZone(\Calendar_Event::DISPLAY_TIMEZONE));

			if ($datetime !== false) {
				return $datetime;
			}
		}

		return null;
	}

	/**
	 * Human readable label of a UTC datetime in the site timezone
	 *
	 * @access private
	 * @param string $datetime
	 */
	private function format_datetime(string $datetime): string {
		try {
			$datetime = new \DateTime($datetime, new \DateTimeZone('UTC'));
			$datetime->setTimezone(new \DateTimeZone(\Calendar_Event::DISPLAY_TIMEZONE));
		} catch (\Exception $e) {
			return $datetime;
		}

		return $datetime->format('d/m/Y H:i');
	}
}
