<?php

declare(strict_types=1);

/**
 * Admin
 *
 * Admin dashboard at /admin. Shows overview counts and quick links. The
 * admin session check lives in Admin\Base, which every admin module
 * extends.
 *
 * @author Roan Buysse <roan@tigron.be>
 */

namespace App\Front\Module;

class Admin extends Admin\Base {
	/**
	 * Display the dashboard
	 *
	 * @access public
	 */
	public function display(): void {
		$this->template = 'admin/index.twig';

		$template = \Skeleton\Application\Web\Template::get();

		$template->assign('block_count', \Block::count_all());
		$template->assign('block_active_count', \Block::count_active());
		$template->assign('download_count', \Download_File::count_all());
		$template->assign('calendar_count', \Calendar_Event::count_all());
		$template->assign('user_count', \User::count_all());
		$template->assign('calendar_upcoming', $this->get_calendar_upcoming());
	}

	/**
	 * The next ten upcoming events for the dashboard list
	 *
	 * The dashboard is a management view, so hidden events are included
	 * (and flagged visible = 0) — unlike the members lists, which only
	 * show published events. Each entry wins its category badge colours.
	 *
	 * @access private
	 * @return array display rows
	 */
	private function get_calendar_upcoming(): array {
		$rows = [];
		foreach (\Calendar_Event::get_upcoming_entries(10, false) as $entry) {
			$category_name = '';
			$category_color = \Calendar_Category::DEFAULT_COLOR;

			if ($entry['calendar_category_id'] > 0) {
				try {
					$category = \Calendar_Category::get_by_id($entry['calendar_category_id']);
					$category_name = $category->name;
					$category_color = $category->get_color();
				} catch (\Exception $e) {
				}
			}

			$entry['category'] = $category_name;
			$entry['category_color'] = $category_color;
			$entry['category_text_color'] = \Calendar_Category::best_text_color($category_color);
			$rows[] = $entry;
		}

		return $rows;
	}
}