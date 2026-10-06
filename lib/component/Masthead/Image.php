<?php

declare(strict_types=1);

/**
 * Masthead_Image
 *
 * The hero photo of the masthead card, as the three responsive variants the
 * hero needs (spec/02, "Masthead hero image").
 *
 * The masthead is the largest contentful paint of every public page, so it
 * keeps the responsive picture set of the static banner it replaces: one URL
 * per breakpoint, picked by the --masthead-image-{small,medium,wide,large}
 * custom properties in styles.css. The variant dimensions are the dimensions
 * of the static /caving-banner*.webp files, so replacing the photo does not
 * change the pixel budget of the page.
 *
 * A hero photo is optional: an empty result means the static banner stays the
 * background, which is also the background of the error pages.
 *
 * @author Roan Buysse <roan@tigron.be>
 */

class Masthead_Image {
	/**
	 * The hero variant per breakpoint
	 *
	 * The keys are the custom property suffixes styles.css reads; the values
	 * are the resize configurations registered in Bootstrap. They are
	 * separate from the card sizes (1600x1200 and down) because the hero is a
	 * full-width banner, not a card in a column.
	 *
	 * Each size is the width of the viewport range it serves, because the
	 * masthead is `width: 100%` with `background-size: cover` and so needs at
	 * least one image pixel per viewport pixel. styles.css switches variants
	 * at exactly these numbers (640/1280/1920) and the preloads in
	 * _default/hero_image.twig mirror the same boundaries; change one and you
	 * have to change all three.
	 *
	 * @var array $sizes
	 */
	const SIZES = [
		'small' => '640x320',
		'medium' => '1280x640',
		'wide' => '1920x960',
		'large' => '2560x1280',
	];

	/**
	 * Get the hero URLs of the masthead card
	 *
	 * Site-relative, so they can be used as a href and inside a url() just as
	 * well as the static banner paths are.
	 *
	 * @access public
	 * @return array [ 'small', 'medium', 'wide', 'large' ] URLs, empty when
	 *               the masthead has no usable photo attached
	 */
	public static function get_urls(): array {
		$file_id = self::get_file_id();

		if ($file_id === null) {
			return [];
		}

		$urls = [];
		foreach (self::SIZES as $key => $size) {
			$urls[$key] = '/picture?id=' . $file_id . '&size=' . $size;
		}

		return $urls;
	}

	/**
	 * Get the absolute URL of the largest variant
	 *
	 * og:image needs an absolute URL: a crawler resolving a share from
	 * another host cannot follow a site-relative one.
	 *
	 * @access public
	 * @param array $urls as returned by get_urls()
	 * @return ?string
	 */
	public static function get_absolute_url(array $urls): ?string {
		if (isset($urls['large']) === false) {
			return null;
		}

		return Http_Base_Url::get() . $urls['large'];
	}

	/**
	 * The file id of the masthead photo
	 *
	 * Null when the masthead has no photo, or when the stored file is gone:
	 * both cases fall back to the static banner instead of painting a hero
	 * whose background 404s. The picture is resolved (as the Index module
	 * does for the card images) rather than trusting the foreign key.
	 *
	 * @access private
	 * @return ?int
	 */
	private static function get_file_id(): ?int {
		$block = Block::get_masthead();

		if ($block === null || (int)$block->file_id <= 0) {
			return null;
		}

		try {
			\Skeleton\File\Picture\Picture::get_by_id((int)$block->file_id);
		} catch (\Exception $e) {
			return null;
		}

		return (int)$block->file_id;
	}
}