<?php

declare(strict_types=1);

namespace App\Front\Module;

/**
 * Download
 *
 * Compatibility shim: the old /download URLs stay reachable after the
 * members-area rename (spec/07). Everything redirects to /members.
 *
 * @author Roan Buysse <roan@tigron.be>
 */

use Skeleton\Core\Http\Session;

class Download extends \Skeleton\Application\Web\Module {
	/**
	 * Login required
	 *
	 * @var bool $login_required
	 */
	protected bool $login_required = false;

	/**
	 * No template
	 *
	 * @var ?string $template
	 */
	protected ?string $template = null;

	/**
	 * Redirect to /members
	 *
	 * @access public
	 */
	public function display(): void {
		Session::redirect('/members');
	}
}
