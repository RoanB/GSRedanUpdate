<?php

declare(strict_types=1);

/**
 * I18n_Extractor_Multiple
 *
 * Collects trans strings from several template trees into one catalog.
 *
 * skeleton-i18n registers one translator per application and its Twig
 * extractor scans a single template path. The mail templates in
 * store/email/template are rendered with the same `front` catalog, so they
 * have to be scanned too — otherwise `i18n:generate` considers their strings
 * stale and deletes them from the po files.
 *
 * @author Roan Buysse <roan@tigron.be>
 */

class I18n_Extractor_Multiple implements \Skeleton\I18n\Translator\Extractor {
	/**
	 * Extractors, one per template path
	 *
	 * @access private
	 * @var array $extractors
	 */
	private array $extractors = [];

	/**
	 * Constructor
	 *
	 * @access public
	 * @param array $template_paths
	 */
	public function __construct(array $template_paths) {
		foreach ($template_paths as $template_path) {
			if (file_exists($template_path) === false) {
				continue;
			}

			$extractor = new \Skeleton\I18n\Translator\Extractor\Twig();
			$extractor->set_template_path($template_path);

			$this->extractors[] = $extractor;
		}
	}

	/**
	 * Get strings
	 *
	 * @access public
	 * @return array $strings
	 */
	public function get_strings(): array {
		$strings = [];

		foreach ($this->extractors as $extractor) {
			$strings = array_merge($strings, $extractor->get_strings());
		}

		return array_unique($strings);
	}
}
