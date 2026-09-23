<?php

declare(strict_types=1);

/**
 * Members Calendar
 *
 * The calendar tab of the members area (spec/07), served at
 * /members/calendar. It consolidates the club calendar information: the
 * read-only fullcalendar view, the upcoming-events list, the public
 * read-only CalDAV subscription URL and the one-shot ICS download.
 *
 * The page keeps the members password gate (the parent Members module owns
 * the gate form), so an unauthenticated visitor is sent back to /members.
 * The JSON event feed and the ICS/CalDAV endpoints stay anonymous, because
 * calendar clients cannot share the PHP session.
 *
 * @author Roan Buysse <roan@tigron.be>
 */

namespace App\Front\Module\Members;

use Skeleton\Core\Http\Session;

class Calendar extends \Skeleton\Application\Web\Module {
	/**
	 * Login required
	 *
	 * @var bool $login_required
	 */
	protected bool $login_required = false;

	/**
	 * Template
	 *
	 * @var string|null $template
	 */
	protected ?string $template = 'members_calendar.twig';

	/**
	 * Member layout shell (own nav, no admin menu)
	 *
	 * @var string $layout_name
	 */
	protected string $layout_name = 'members';

	/**
	 * Display the calendar tab
	 *
	 * @access public
	 */
	public function display(): void {
		if ($this->is_authenticated() === false) {
			Session::redirect('/members');
		}

		$template = \Skeleton\Application\Web\Template::get();

		$template->assign('members_tab', 'calendar');
		$template->assign('events', $this->get_calendar_list());
		$template->assign('calendar_categories', $this->get_legend_categories());

		$scheme = $_SERVER['REQUEST_SCHEME'] ?? 'https';
		$host = $_SERVER['HTTP_HOST'] ?? '';

		$template->assign('caldav_url', $scheme . '://' . $host . '/caldav/GSRedan/');
		$template->assign('ics_url', $scheme . '://' . $host . '/members/calendar.ics');
	}

	/**
	 * Event feed for the read-only members calendar (JSON)
	 *
	 * Visible events only, category colours, no edit URL: the members view
	 * is strictly read-only. Anonymous on purpose, matching the ICS and
	 * CalDAV GET endpoints.
	 *
	 * @access public
	 */
	public function display_events(): void {
		$this->template = null;

		$events = [];

		foreach (\Calendar_Event::get_all_ordered() as $event) {
			if ((int)$event->visible !== 1) {
				continue;
			}

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

			$events[] = [
				'id' => (int)$event->id,
				'title' => (string)$event->title,
				'start' => $this->to_frontend_datetime((string)$event->starts_at, false, (int)$event->all_day === 1),
				'end' => $this->to_frontend_datetime((string)$event->ends_at, true, (int)$event->all_day === 1),
				'allDay' => (int)$event->all_day === 1,
				'color' => $category_color,
				'textColor' => \Calendar_Category::best_text_color($category_color),
				'category' => ['id' => $category_id, 'name' => $category_name],
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
	 * Is the visitor authenticated for the members area?
	 *
	 * @access private
	 * @return bool
	 */
	private function is_authenticated(): bool {
		return isset($_SESSION['members_authenticated']) && $_SESSION['members_authenticated'] === true;
	}

	/**
	 * Categories with visible events, for the legend under the calendar
	 *
	 * Only categories that actually appear on the members calendar are
	 * listed: the feed is filtered to visible events, so a category with no
	 * visible event would make the legend lie about what is shown. Each
	 * entry carries the badge colour and the contrasting text colour used
	 * in the calendar chips. Mirrors the admin legend, minus empty
	 * categories.
	 *
	 * @access private
	 * @return array category legend entries
	 */
	private function get_legend_categories(): array {
		$categories = [];
		foreach (\Calendar_Category::get_all_ordered() as $category) {
			if ($category->count_visible_events() === 0) {
				continue;
			}

			$color = $category->get_color();
			$categories[] = [
				'name' => $category->name,
				'color' => $color,
				'text_color' => \Calendar_Category::best_text_color($color),
			];
		}

		return $categories;
	}

	/**
	 * Build the upcoming-events list for the template
	 *
	 * @access private
	 * @return array events with display fields
	 */
	private function get_calendar_list(): array {
		return \Calendar_Event::get_upcoming_entries(10, true);
	}

	/**
	 * Format a stored UTC datetime for the JS calendar
	 *
	 * @access private
	 * @param ?string $datetime
	 * @param bool $is_end
	 * @param bool $all_day
	 * @return ?string
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

		if ($all_day === true) {
			if ($is_end === true) {
				$date->modify('+1 day');
			}

			return $date->format('Y-m-d');
		}

		return $date->format('Y-m-d\TH:i:s');
	}
}
