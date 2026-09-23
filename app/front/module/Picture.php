<?php

declare(strict_types=1);

/**
 * Picture
 *
 * Serves the images attached to cards (gallery blocks and activity images)
 * through registered resize configurations. Files are streamed as binary.
 *
 * Serving rules (see spec/02 "Image quality"):
 * - The resize target larger than the original streams the original file:
 *   no re-encode, no quality loss. (Picture::show() would re-encode with
 *   quality -1, which degrades webp to quality 9.)
 * - A real downsize re-encodes once with quality 85 in image/webp (jpeg for
 *   non-webp sources) and the copy is cached in tmp/picture/<size>/<id>.
 *
 * @author Roan Buysse <roan@tigron.be>
 */

namespace App\Front\Module;

use \Skeleton\Core\Http\Status;
use \Skeleton\File\Picture\Manipulation;
use \Skeleton\File\Picture\Picture as Picture_File;

class Picture extends \Skeleton\Application\Web\Module {
	/**
	 * Login required
	 *
	 * @var bool $login_required
	 */
	protected bool $login_required = false;

	/**
	 * No template: binary output
	 *
	 * @var ?string $template
	 */
	protected ?string $template = null;

	/**
	 * Stream the requested picture (optionally resized)
	 *
	 * @access public
	 */
	public function display(): void {
		$this->template = null;

		$file_id = (int)($_GET['id'] ?? 0);

		try {
			$picture = Picture_File::get_by_id($file_id);
		} catch (\Exception $e) {
			Status::code_404();
		}

		if ($picture instanceof Picture_File === false) {
			Status::code_404();
		}

		$configuration = $this->get_configuration($_GET['size'] ?? null);

		if ($configuration === null || $this->needs_resize($picture, $configuration) === false) {
			$this->stream($picture->get_path(), $picture->mime_type);
		}

		$format = 'image/webp';
		if ($picture->mime_type !== 'image/webp') {
			// Re-encode webp sources to webp; anything else keeps its type.
			$format = $picture->mime_type;
		}

		$path = $this->build_resize($picture, $configuration, $format);

		$this->stream($path, $format);
	}

	/**
	 * Resolve the requested size to a resize configuration
	 *
	 * @access private
	 * @param ?string $size
	 * @return ?array with name, width, height, mode
	 */
	private function get_configuration(?string $name): ?array {
		if ($name === null || isset(\Skeleton\File\Picture\Config::$resize_configurations[$name]) === false) {
			return null;
		}

		return \Skeleton\File\Picture\Config::get_configuration($name);
	}

	/**
	 * Is a resize needed (target smaller than the original)?
	 *
	 * @access private
	 * @param Picture_File $picture
	 * @param array $configuration
	 */
	private function needs_resize(Picture_File $picture, array $configuration): bool {
		$width = (int)$picture->width;
		$height = (int)$picture->height;

		if ($configuration['mode'] === 'crop') {
			return true;
		}

		if ((int)$configuration['width'] >= $width && ($configuration['height'] === null || (int)$configuration['height'] >= $height)) {
			return false;
		}

		return true;
	}

	/**
	 * Build (or reuse) the resized copy for this size
	 *
	 * @access private
	 * @param Picture_File $picture
	 * @param array $configuration
	 * @param string $format
	 * @return string path on disk
	 */
	private function build_resize(Picture_File $picture, array $configuration, string $format): string {
		$directory = \Skeleton\File\Picture\Config::$tmp_path . $configuration['name'] . '/';
		$path = $directory . $picture->id;

		if (file_exists($path) === true) {
			return $path;
		}

		if (is_dir($directory) === false) {
			mkdir($directory, 0755, true);
		}

		// Work on a temp file first: a half-written cache must never be served.
		$tmp_path = $path . '.part';

		$image = new Manipulation($picture);
		$image->resize($configuration['width'], $configuration['height'], $configuration['mode']);
		$image->output($tmp_path, $format, 85);

		rename($tmp_path, $path);

		return $path;
	}

	/**
	 * Stream a file with long-lived caching headers
	 *
	 * @access private
	 * @param string $path
	 * @param string $mime_type
	 */
	private function stream(string $path, string $mime_type): void {
		if (is_file($path) === false) {
			Status::code_404();
		}

		$gmt_mtime = gmdate('D, d M Y H:i:s', filemtime($path)) . ' GMT';
		$etag = '"' . md5($path . filemtime($path)) . '"';
		header('Etag: ' . $etag);
		header('Last-Modified: ' . $gmt_mtime);

		if ((isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && trim($_SERVER['HTTP_IF_MODIFIED_SINCE']) === $gmt_mtime)
			|| (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag)
		) {
			header('HTTP/1.1 304 Not Modified');
			exit;
		}

		header('Pragma: public');
		header('Cache-Control: max-age=3600, public');
		header('Content-Type: ' . $mime_type);
		header('Content-Disposition: inline; filename="' . basename($path) . '"');
		header('Content-Transfer-Encoding: binary');
		header('Content-Length: ' . filesize($path));

		readfile($path);
		exit;
	}
}
