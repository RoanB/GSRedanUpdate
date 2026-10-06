<?php

declare(strict_types=1);

/**
 * Event
 *
 * @author Roan Buysse <roan@tigron.be>
 */

namespace App\Front\Event;

class I18n extends \Skeleton\Application\Web\Event\I18n {
	/**
	 * Get the translator extractor for this app
	 *
	 * The web templates and the mail templates share one `front` catalog:
	 * both are rendered with the same Translation object, so both have to be
	 * scanned or `i18n:generate` drops the mail strings on the next run.
	 *
	 * @access public
	 * @return \Skeleton\I18n\Translator\Extractor $extractor
	 */
	public function get_translator_extractor(): \Skeleton\I18n\Translator\Extractor {
		$root_path = realpath(dirname(__FILE__) . '/../../..');

		return new \I18n_Extractor_Multiple([
			$this->application->template_path,
			$root_path . '/store/email/template/',
		]);
	}
}
