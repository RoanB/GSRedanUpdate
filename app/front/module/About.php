<?php

declare(strict_types=1);

/**
 * About
 *
 * "Le club" page. The public page is the static design from
 * ../gsredanupdate, rendered from templates (spec/02): the content is in the
 * templates and the po catalogs, not in the database blocks.
 *
 * @author Roan Buysse <roan@tigron.be>
 */

namespace App\Front\Module;

class About extends \Skeleton\Application\Web\Module {
	/**
	 * Login required
	 *
	 * @var bool $login_required
	 */
	protected bool $login_required = false;

	/**
	 * Display the about page
	 *
	 * @access public
	 */
	public function display(): void {
		$this->template = 'about.twig';
	}
}
