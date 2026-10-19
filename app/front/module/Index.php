<?php

declare(strict_types=1);

/**
 * Index
 *
 * Homepage. The public page is the static design from ../gsredanupdate,
 * rendered from templates (spec/02): the content is in the templates and the
 * po catalogs, not in the database blocks. The blocks admin still exists, but
 * nothing it edits is displayed here any more.
 *
 * @author Roan Buysse <roan@tigron.be>
 */

namespace App\Front\Module;

class Index extends \Skeleton\Application\Web\Module {
	/**
	 * Login required
	 *
	 * @var bool $login_required
	 */
	protected bool $login_required = false;

	/**
	 * Display the homepage
	 *
	 * Two dynamic feeds besides the templates themselves:
	 * - the Monday-openings card: the upcoming visible events of the
	 *   calendar category 'Ouverture de la salle' (spec/07), one label
	 *   per month group, rendered as date chips;
	 * - the rally dates, rendered from the rally settings (see
	 *   event/Module.php, which assigns rally_date_label globally).
	 *
	 * @access public
	 */
	public function display(): void {
		$this->template = 'index.twig';

		$template = \Skeleton\Application\Web\Template::get();

		// The openings chips: no category or no upcoming events renders
		// no chips at all (the template hides the dates block).
		$openings = [];
		$opening_category = \Calendar_Category::get_opening_category();

		if ($opening_category !== null) {
			$openings = \Calendar_Event::format_opening_dates(
				\Calendar_Event::get_visible_upcoming_by_category((int)$opening_category->id, 24),
				\Language::get()->name_short
			);
		}

		$template->assign('openings', $openings);
	}
}
