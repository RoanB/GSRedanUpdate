<?php

declare(strict_types=1);

/**
 * Admin Download
 *
 * Lists the download-center files with ordering/visibility, and edits a
 * download entry (display name, visibility, replacement file upload).
 *
 * @author Roan Buysse <roan@tigron.be>
 */

namespace App\Front\Module\Admin;

class Download extends \Skeleton\Application\Web\Module {
	/**
	 * Login required
	 *
	 * @var bool $login_required
	 */
	protected bool $login_required = true;

	/**
	 * Secure
	 *
	 * @access public
	 * @return bool
	 */
	public function secure(): bool {
		return isset($_SESSION['user']) && $_SESSION['user']->is_admin();
	}

	/**
	 * List the download files
	 *
	 * @access public
	 */
	public function display(): void {
		$this->template = 'admin/download.twig';

		$template = \Skeleton\Application\Web\Template::get();

		$download_files = \Download_File::get_all_ordered();
		$files = [];
		foreach ($download_files as $download_file) {
			$file = $download_file->get_file();
			$category = $download_file->get_category();
			$files[] = [
				'id' => $download_file->id,
				'name' => $download_file->name,
				'visible' => $download_file->visible,
				'file_name' => $file->name,
				'size' => $file->size,
				'size_label' => self::format_bytes((int)$file->size),
				'category_id' => $category !== null ? (int)$category->id : 0,
			];
		}

		$categories = [];
		foreach (\Download_Category::get_all_ordered() as $category) {
			$categories[] = [
				'id' => $category->id,
				'name' => $category->name,
				'visible' => $category->visible,
				'is_deletable' => $category->is_deletable(),
				'file_count' => $category->count_visible_files(),
			];
		}

		$template->assign('files', $files);
		$template->assign('categories', $categories);
	}

	/**
	 * Create a new category
	 *
	 * @access public
	 */
	public function display_add_category(): void {
		$this->template = 'admin/download.twig';

		if (isset($_POST['name']) === true) {
			$category = new \Download_Category();
			$category->name = trim((string)$_POST['name']);
			$category->sort_order = count(\Download_Category::get_all_ordered());
			$category->visible = 1;

			$errors = [];
			if ($category->validate($errors) === true) {
				$category->save();
			}
		}

		\Skeleton\Core\Http\Session::redirect('/admin/download?saved=1');
	}

	/**
	 * Rename a category
	 *
	 * @access public
	 */
	public function display_rename_category(): void {
		$this->template = 'admin/download.twig';

		$category_id = (int)($_POST['id'] ?? 0);
		$name = trim((string)($_POST['name'] ?? ''));

		try {
			$category = \Download_Category::get_by_id($category_id);
		} catch (\Exception $e) {
			\Skeleton\Core\Http\Session::redirect('/admin/download');
		}

		$category->name = $name;
		$errors = [];
		if ($name !== '' && $category->validate($errors) === true) {
			$category->save();
		}

		\Skeleton\Core\Http\Session::redirect('/admin/download?saved=1');
	}

	/**
	 * Delete an empty category
	 *
	 * @access public
	 */
	public function display_delete_category(): void {
		$this->template = 'admin/download.twig';

		try {
			$category = \Download_Category::get_by_id((int)($_GET['id'] ?? 0));
		} catch (\Exception $e) {
			\Skeleton\Core\Http\Session::redirect('/admin/download');
		}

		if ($category->is_deletable() === true) {
			$category->delete();
		}

		\Skeleton\Core\Http\Session::redirect('/admin/download?saved=1');
	}

	/**
	 * Persist the category order (AJAX)
	 *
	 * @access public
	 */
	public function display_category_order(): void {
		$ordered = $_POST['ordered'] ?? [];
		$ids = [];

		if (is_array($ordered)) {
			foreach ($ordered as $value) {
				$ids[] = (int)$value;
			}
		}

		if (count($ids) > 0) {
			\Download_Category::save_order($ids);
		}

		echo 'ok';
		exit;
	}

	/**
	 * Toggle category visibility of the members list (AJAX)
	 *
	 * @access public
	 */
	public function display_category_visibility(): void {
		$category_id = (int)($_POST['id'] ?? 0);

		try {
			$category = \Download_Category::get_by_id($category_id);
		} catch (\Exception $e) {
			echo json_encode([]);
			exit;
		}

		$category->visible = $category->visible ? 0 : 1;
		$category->save();

		echo json_encode(['visible' => (bool)$category->visible]);
		exit;
	}

	/**
	 * Edit a download entry (or create a new one)
	 *
	 * @access public
	 */
	public function display_edit(): void {
		$this->template = 'admin/download_edit.twig';

		$template = \Skeleton\Application\Web\Template::get();

		$download_id = (int)($_GET['id'] ?? 0);
		$download_file = null;
		if ($download_id > 0) {
			$download_file = \Download_File::get_by_id($download_id);
			$template->assign('download_file', $download_file);
		}

		$edit_categories = [];
		foreach (\Download_Category::get_all_ordered() as $category) {
			$edit_categories[] = [
				'id' => $category->id,
				'name' => $category->name,
			];
		}
		$template->assign('categories', $edit_categories);

		if (isset($_POST['name'])) {
			$has_new_file = isset($_FILES['file']) && isset($_FILES['file']['tmp_name']) && $_FILES['file']['tmp_name'] !== '';

			$errors = [];
			if (empty($_POST['name'])) {
				$errors['name'] = 'mandatory';
			}
			if ($download_file === null && !$has_new_file) {
				$errors['file'] = 'mandatory';
			}

			if (count($errors) === 0) {
				if ($has_new_file) {
					try {
						$file = \File::upload($_FILES['file']);
					} catch (\Exception $e) {
						$errors['file'] = 'upload_failed';
					}
				}

				if (!isset($errors['file'])) {
					if ($download_file === null) {
						$download_file = new \Download_File();
						$download_file->sort_order = count(\Download_File::get_all_ordered());
					}

					if ($has_new_file) {
						$download_file->file_id = $file->id;
					}

					$download_file->name = $_POST['name'];
					$download_file->visible = isset($_POST['visible']) ? 1 : 0;
					$download_file->download_category_id = ((int)($_POST['download_category_id'] ?? 0) > 0) ? (int)$_POST['download_category_id'] : null;
					$download_file->save();

					\Skeleton\Core\Http\Session::redirect('/admin/download?saved=1');
				}
			}

			$template->assign('errors', $errors);
		}
	}

	/**
	 * Persist the new sort order (AJAX)
	 *
	 * @access public
	 */
	public function display_order(): void {
		$ordered = $_POST['ordered'] ?? [];
		$ids = [];

		if (is_array($ordered)) {
			foreach ($ordered as $value) {
				$ids[] = (int)$value;
			}
		}

		if (count($ids) > 0) {
			\Download_File::save_order($ids);
		}

		echo 'ok';
		exit;
	}

	/**
	 * Toggle download visibility (AJAX)
	 *
	 * @access public
	 */
	public function display_visibility(): void {
		$download_id = (int)($_POST['id'] ?? 0);
		$download_file = \Download_File::get_by_id($download_id);
		$download_file->visible = $download_file->visible ? 0 : 1;
		$download_file->save();

		echo json_encode(['visible' => (bool)$download_file->visible]);
		exit;
	}

	/**
	 * Human readable byte size for the admin list
	 *
	 * @access private
	 * @param int $bytes
	 */
	private static function format_bytes(int $bytes): string {
		if ($bytes >= 1048576) {
			return round($bytes / 1048576, 1) . ' MB';
		}

		if ($bytes >= 1024) {
			return round($bytes / 1024, 1) . ' KB';
		}

		return $bytes . ' B';
	}
}