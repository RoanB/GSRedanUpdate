<?php

declare(strict_types=1);

/**
 * Rallye
 *
 * Rallye page. The public page is the static design from ../gsredanupdate,
 * rendered from templates (spec/02): the content is in the templates and the
 * po catalogs, not in the database blocks.
 *
 * The whole rally block is controlled by the `rally_enabled` setting: when
 * the toggle is off, no rally content stays on the site (the layout links,
 * the hero CTA, the teaser band, the countdown and the 404 button are all
 * conditioned on it), so this page sends the visitor home instead of being
 * a straggler behind a hidden section.
 *
 * @author Roan Buysse <roan@tigron.be>
 */

namespace App\Front\Module;

use Skeleton\Core\Http\Session;

class Rallye extends \Skeleton\Application\Web\Module {
	/**
	 * Login required
	 *
	 * @var bool $login_required
	 */
	protected bool $login_required = false;

	/**
	 * Display the rallye page
	 *
	 * @access public
	 */
	public function display(): void {
		if (\Setting::get_by_name('rally_enabled') !== '1') {
			Session::redirect('/');
		}

		$this->template = 'rallye.twig';
	}
}

