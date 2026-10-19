<?php

declare(strict_types=1);

/**
 * Legal
 *
 * "Mentions légales & vie privée" page. The public page is the static design
 * from ../gsredanupdate, rendered from templates (spec/02): the content is in
 * the templates and the po catalogs, not in the database blocks.
 *
 * @author Roan Buysse <roan@tigron.be>
 */

namespace App\Front\Module;

class Legal extends \Skeleton\Application\Web\Module {
	/**
	 * Login required
	 *
	 * @var bool $login_required
	 */
	protected bool $login_required = false;

	/**
	 * Display the legal page
	 *
	 * @access public
	 */
	public function display(): void {
		$this->template = 'legal.twig';
	}
}
