<?php

declare(strict_types=1);

/**
 * Admin Block
 *
 * Lists the blocks with drag-and-drop ordering (the masthead is pinned and
 * not draggable) and a visibility toggle (handled by base.js via
 * ?action=order and ?action=visibility). Each block is a "card": singular
 * types (masthead, club, contact, footer) come from the seed, while generic
 * card types (activities, gallery, announcement) can be created, edited and
 * reordered. Activities cards carry one image (uploaded, resized by
 * skeleton-file-picture), gallery cards hold an ordered list of pictures.
 *
 * @author Roan Buysse <roan@tigron.be>
 */

namespace App\Front\Module\Admin;

class Block extends \Skeleton\Application\Web\Module {
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
	 * List the blocks
	 *
	 * @access public
	 */
	public function display(): void {
		$this->template = 'admin/block.twig';

		$template = \Skeleton\Application\Web\Template::get();

		$rows = [];
		foreach (\Block::get_all_ordered() as $block) {
			try {
				$title = $block->get_translation(\Language::get())->title;
			} catch (\Exception $e) {
				$title = '';
			}

			$kind = 'legacy';
			if (in_array($block->type, \Block::SINGULAR_TYPES, true) === true) {
				$kind = 'fixed';
			} elseif (in_array($block->type, \Block::CREATABLE_TYPES, true) === true) {
				$kind = 'card';
			}

			$rows[] = [
				'id' => $block->id,
				'type' => $block->type,
				'type_label' => \Block::TYPE_LABELS[$block->type] ?? $block->type,
				'kind' => $kind,
				'anchor' => $block->anchor,
				'title' => $title,
				'visible' => $block->visible,
				'draggable' => $block->is_draggable(),
			];
		}

		$creatable_types = [];
		foreach (\Block::CREATABLE_TYPES as $type) {
			$creatable_types[] = [
				'key' => $type,
				'label' => \Block::TYPE_LABELS[$type] ?? $type,
			];
		}

		$template->assign('blocks', $rows);
		$template->assign('creatable_types', $creatable_types);
	}

	/**
	 * Create a new card block
	 *
	 * New blocks start hidden at the end of the page; their per-language
	 * translation rows are created on the first edit.
	 *
	 * @access public
	 */
	public function display_add(): void {
		$this->template = 'admin/block_edit.twig';

		if (isset($_POST['type']) === false) {
			\Skeleton\Core\Http\Session::redirect('/admin/block');
		}

		$type = $_POST['type'];

		if (in_array($type, \Block::CREATABLE_TYPES, true) === false) {
			\Skeleton\Core\Http\Session::redirect('/admin/block?add_failed=1');
		}

		$block = new \Block();
		$block->type = $type;
		$block->sort_order = count(\Block::get_all_ordered());
		$block->visible = 0;

		$errors = [];
		if ($block->validate($errors) === false) {
			\Skeleton\Core\Http\Session::redirect('/admin/block?add_failed=1');
		}

		$block->save();
		$block = \Block::get_by_id((int)$block->id);

		// Seed an empty translation for every language so the first save has
		// a row to update.
		foreach (\Language::get_all() as $language) {
			$translation = new \Block_Translation();
			$translation->block_id = $block->id;
			$translation->language_id = $language->id;
			$translation->title = '';
			$translation->body = '';
			$translation->save();
		}

		\Skeleton\Core\Http\Session::redirect('/admin/block?action=edit&id=' . $block->id);
	}

	/**
	 * Edit a block
	 *
	 * @access public
	 */
	public function display_edit(): void {
		$this->template = 'admin/block_edit.twig';

		$template = \Skeleton\Application\Web\Template::get();

		$block_id = (int)($_GET['id'] ?? 0);
		$block = \Block::get_by_id($block_id);
		$languages = \Language::get_all();

		$translations = [];
		foreach ($languages as $language) {
			$translations[$language->id] = $block->get_translation($language);
		}

		if (isset($_POST['anchor'])) {
			$block->anchor = $_POST['anchor'];
			$block->visible = isset($_POST['visible']) ? 1 : 0;

			// Light cards keep the photo column side (left/right); the dark
			// and 2-row cards follow their own fixed layout.
			if ($block->type === 'activities_light' || $block->type === 'monday_openings') {
				$image_side = $_POST['image_side'] ?? 'left';
				if (in_array($image_side, [ 'left', 'right' ], true) === true) {
					$block->image_side = $image_side;
				}
			}

			// Form fields may be malformed (scalar, malformed array key), in
			// which case a direct $_POST['title'][$id] access would silently
			// write wrong data. Normalise to arrays up front.
			$post_title = is_array($_POST['title'] ?? null) ? $_POST['title'] : [];
			$post_body = is_array($_POST['body'] ?? null) ? $_POST['body'] : [];
			$post_body2 = is_array($_POST['body_pair'] ?? null) ? $_POST['body_pair'] : [];

			$errors = [];
			if ($block->validate($errors) === false) {
				foreach ($languages as $language) {
					$translation = $translations[$language->id];
					$translation->title = (string)($post_title[$language->id] ?? '');
					$translation->body = (string)($post_body[$language->id] ?? '');
				}

				$template->assign('errors', $errors);
			} else {
				$block->save();

				foreach ($languages as $language) {
					$translation = $translations[$language->id];
					$translation->title = (string)($post_title[$language->id] ?? '');

					// Pair cards stitch the two row texts behind the pair
					// marker; every other card keeps one body.
					if ($block->is_pair() === true) {
						$translation->body = \Block::stitch_pair_body(
							(string)($post_body[$language->id] ?? ''),
							(string)($post_body2[$language->id] ?? '')
						);
					} else {
						$translation->body = (string)($post_body[$language->id] ?? '');
					}

					$translation->save();
				}

				\Skeleton\Core\Http\Session::redirect('/admin/block?saved=1');
			}
		}

		$template->assign('block', $block);
		$template->assign('languages', $languages);
		$template->assign('translations', $translations);

		// Split the stitched pair body back into the two row textareas.
		if ($block->is_pair() === true) {
			$pair_split = [];
			foreach ($translations as $language_id => $translation) {
				$pair_split[$language_id] = \Block::split_pair_body((string)$translation->body);
			}

			$template->assign('pair_split', $pair_split);
		}

		if (in_array($block->type, [ 'monday_openings', 'activities_light', 'activities_dark' ], true) === true) {
			$template->assign('image', $this->get_block_image($block));
		}

		if ($block->is_pair() === true) {
			$template->assign('image', $this->get_block_image($block));
			$template->assign('image_2', $this->get_block_image($block, 2));
		}

		if (in_array($block->type, [ 'gallery' ], true) === true) {
			$pictures = [];
			foreach ($block->get_pictures() as $block_picture) {
				$pictures[] = [
					'id' => $block_picture->id,
					'file_id' => $block_picture->file_id,
				];
			}

			$template->assign('pictures', $pictures);
		}
	}

	/**
	 * Upload an image for its card
	 *
	 * Activities cards and the Monday openings card carry one picture
	 * (block.file_id), 2-row pair cards two (file_id for row 1, file_id_2
	 * for row 2, selected with &slot=2). The original upload is kept;
	 * public rendering resizes on demand through the /picture endpoint.
	 *
	 * @access public
	 */
	public function display_upload_image(): void {
		$this->template = 'admin/block_edit.twig';

		$block_id = (int)($_GET['id'] ?? 0);
		$block = \Block::get_by_id($block_id);
		$slot = (int)($_GET['slot'] ?? 1);
		$slot = in_array($slot, [ 1, 2 ], true) === true ? $slot : 1;

		$is_uploadable = (
			$block->type === 'activities_light'
			|| $block->type === 'activities_dark'
			|| $block->type === 'monday_openings'
			|| $block->is_pair() === true
		);

		if ($is_uploadable === false || isset($_FILES['file']) === false) {
			\Skeleton\Core\Http\Session::redirect('/admin/block?action=edit&id=' . $block->id);
		}

		if ($slot === 2 && $block->is_pair() === false) {
			\Skeleton\Core\Http\Session::redirect('/admin/block?action=edit&id=' . $block->id);
		}

		try {
			$uploaded = \File::upload($_FILES['file']);
		} catch (\Exception $e) {
			\Skeleton\Core\Http\Session::redirect('/admin/block?action=edit&id=' . $block->id . '&image_failed=1');
		}

		// get_by_id resolves images to \Skeleton\File\Picture\Picture and
		// creates the missing picture row on the way.
		$file = \File::get_by_id((int)$uploaded->id);

		if ($file instanceof \Skeleton\File\Picture\Picture === false) {
			// Not an image: the file row and stored bytes are dead weight.
			$uploaded->delete();
			\Skeleton\Core\Http\Session::redirect('/admin/block?action=edit&id=' . $block->id . '&image_failed=1');
		}

		// Remove the old image (if any) to avoid dead files.
		if ($slot === 1 && (int)$block->file_id > 0) {
			$this->delete_picture_file((int)$block->file_id);
		} elseif ($slot === 2 && (int)$block->file_id_2 > 0) {
			$this->delete_picture_file((int)$block->file_id_2);
		}

		if ($slot === 1) {
			$block->file_id = $file->id;
		} else {
			$block->file_id_2 = $file->id;
		}
		$block->save();

		\Skeleton\Core\Http\Session::redirect('/admin/block?action=edit&id=' . $block->id . '&image_saved=1');
	}

	/**
	 * Live preview of one card (iframe target)
	 *
	 * Renders the card partial with the submitted form values when present
	 * (POST, never saved) or the stored state (GET). The response is a
	 * standalone HTML page that loads the public stylesheet.
	 *
	 * @access public
	 */
	public function display_preview(): void {
		$this->template = 'admin/block_preview.twig';

		$template = \Skeleton\Application\Web\Template::get();

		$block_id = (int)($_GET['id'] ?? 0);
		$block = \Block::get_by_id($block_id);
		$language = \Language::get();

		$post_title = is_array($_POST['title'] ?? null) ? $_POST['title'] : [];
		$post_body = is_array($_POST['body'] ?? null) ? $_POST['body'] : [];
		$post_body2 = is_array($_POST['body_pair'] ?? null) ? $_POST['body_pair'] : [];

		$translation = clone $block->get_translation($language);
		if (isset($post_title[$language->id]) === true) {
			$translation->title = (string)$post_title[$language->id];
		}

		if ($block->is_pair() === true) {
			$pair_bodies = \Block::split_pair_body((string)$translation->body);

			if (isset($post_body[$language->id]) === true) {
				$pair_bodies[0] = (string)$post_body[$language->id];
			}
			if (isset($post_body2[$language->id]) === true) {
				$pair_bodies[1] = (string)$post_body2[$language->id];
			}

			$translation->body = \Block::stitch_pair_body($pair_bodies[0], $pair_bodies[1]);

			$template->assign('pair_bodies', [
				$block->id => [ $pair_bodies[0], $pair_bodies[1] ],
			]);
		} elseif (isset($post_body[$language->id]) === true) {
			$translation->body = (string)$post_body[$language->id];
		}

		$template->assign('block', $block);
		$template->assign('translation', $translation);
		$template->assign('settings', \Setting::get_all());

		// Calendar-fed Monday openings card: preview the real openings list.
		if ($block->type === 'monday_openings') {
			$opening_category = \Calendar_Category::get_opening_category();

			if ($opening_category !== null) {
				$opening_events = \Calendar_Event::get_visible_upcoming_by_category((int)$opening_category->id, 24);
			} else {
				$opening_events = [];
			}

			$template->assign('openings', [
				$block->id => \Calendar_Event::format_opening_dates($opening_events, $language->name_short),
			]);
		}

		// Image slots: row 1 for the image cards, rows 1 and 2 for pairs.
		$slots = [];
		if (in_array($block->type, [ 'monday_openings', 'activities_light', 'activities_dark' ], true) === true && (int)$block->file_id > 0) {
			$slots = [ [ 'key' => $block->id, 'file_id' => (int)$block->file_id ] ];
		}

		if ($block->is_pair() === true) {
			$slots = [];
			if ((int)$block->file_id > 0) {
				$slots[] = [ 'key' => $block->id, 'file_id' => (int)$block->file_id ];
			}
			if ((int)$block->file_id_2 > 0) {
				$slots[] = [ 'key' => $block->id . '_2', 'file_id' => (int)$block->file_id_2 ];
			}
		}

		$images = [];
		foreach ($slots as $slot) {
			try {
				$picture = \Skeleton\File\Picture\Picture::get_by_id($slot['file_id']);
				$images[$slot['key']] = [
					'file_id' => $slot['file_id'],
					'width' => (int)$picture->width,
					'height' => (int)$picture->height,
				];
			} catch (\Exception $e) {
				continue;
			}
		}

		$template->assign('images', $images);

		if ($block->type === 'gallery') {
			$pictures = [];
			foreach ($block->get_pictures() as $block_picture) {
				$pictures[] = [
					'id' => $block_picture->id,
					'file_id' => $block_picture->file_id,
				];
			}

			$template->assign('pictures', $pictures);
		}
	}

	/**
	 * Assign one image slot of a block (row 1 or row 2) for the preview
	 *
	 * @access private
	 * @param \Skeleton\Application\Web\Template $template
	 * @param \Block $block
	 * @param int $slot
	 */
	private function assign_block_image($template, \Block $block, int $slot): void {
		$file_id = $slot === 1 ? (int)$block->file_id : (int)$block->file_id_2;

		try {
			$picture = \Skeleton\File\Picture\Picture::get_by_id((int)$block->file_id);
			$this->assign_image($template, $block, $slot === 2 ? $block->id . '_2' : $block->id, (int)$block->file_id, (int)$picture->width, (int)$picture->height);
		} catch (\Exception $e) {
			return;
		}
	}

	/**
	 * Upload a picture into a gallery card (multipart, multiple allowed)
	 *
	 * @access public
	 */
	public function display_upload_picture(): void {
		$this->template = 'admin/block_edit.twig';

		$block_id = (int)($_GET['id'] ?? 0);
		$block = \Block::get_by_id($block_id);

		if (in_array($block->type, [ 'gallery' ], true) === false) {
			\Skeleton\Core\Http\Session::redirect('/admin/block');
		}

		if (isset($_FILES['files']) === false) {
			\Skeleton\Core\Http\Session::redirect('/admin/block?action=edit&id=' . $block->id . '&image_failed=1');
		}

		$uploaded_count = 0;

		try {
			$files = \File::upload_multiple($_FILES['files']);
		} catch (\Exception $e) {
			$files = [];
		}

		$sort_order = count($block->get_pictures());

		foreach ($files as $uploaded) {
			$file = \File::get_by_id((int)$uploaded->id);

			if ($file instanceof \Skeleton\File\Picture\Picture === false) {
				$uploaded->delete();
				continue;
			}

			$block_picture = new \Block_Picture();
			$block_picture->block_id = $block->id;
			$block_picture->file_id = $file->id;
			$block_picture->sort_order = $sort_order;
			$block_picture->visible = 1;
			$block_picture->save();

			$sort_order++;
			$uploaded_count++;
		}

		\Skeleton\Core\Http\Session::redirect('/admin/block?action=edit&id=' . $block->id . '&pictures_saved=' . $uploaded_count);
	}

	/**
	 * Delete a picture from a gallery card
	 *
	 * @access public
	 */
	public function display_delete_picture(): void {
		$this->template = 'admin/block_edit.twig';

		$block_id = (int)($_GET['id'] ?? 0);

		$block_picture_id = (int)($_GET['picture_id'] ?? 0);
		try {
			$block_picture = \Block_Picture::get_by_id($block_picture_id);
		} catch (\Exception $e) {
			\Skeleton\Core\Http\Session::redirect('/admin/block?action=edit&id=' . $block_id . '&image_failed=1');
		}

		if ((int)$block_picture->block_id !== $block_id) {
			\Skeleton\Core\Http\Session::redirect('/admin/block');
		}

		$block_picture->delete();

		\Skeleton\Core\Http\Session::redirect('/admin/block?action=edit&id=' . $block_id . '&pictures_deleted=1');
	}

	/**
	 * Persist the gallery picture order (AJAX)
	 *
	 * @access public
	 */
	public function display_picture_order(): void {
		$ordered = $_POST['ordered'] ?? [];
		$ids = [];

		if (is_array($ordered)) {
			foreach ($ordered as $id) {
				$ids[] = (int)$id;
			}
		}

		if (count($ids) > 0) {
			\Block_Picture::save_order($ids);
		}

		echo 'ok';
		exit;
	}

	/**
	 * Persist the new block sort order (AJAX)
	 *
	 * The masthead is non-draggable and always stays at the front of the
	 * page flow: whatever order arrives, it is moved to position 0 first.
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

		foreach ($ids as $key => $id) {
			if ($this->is_masthead_id($id) === true) {
				unset($ids[$key]);
				array_unshift($ids, $id);
				$ids = array_values($ids);
				break;
			}
		}

		if (count($ids) > 0) {
			\Block::save_order($ids);
		}

		echo 'ok';
		exit;
	}

	/**
	 * Is the block with this id the (singular) masthead?
	 *
	 * @access private
	 * @param int $id
	 * @return bool
	 */
	private function is_masthead_id(int $id): bool {
		try {
			$block = \Block::get_by_id($id);
		} catch (\Exception $e) {
			return false;
		}

		return $block->type === 'masthead';
	}

	/**
	 * Toggle block visibility (AJAX)
	 *
	 * @access public
	 */
	/**
	 * Delete a card
	 *
	 * Fixed singular cards (masthead, club, contact, footer) are refused:
	 * they are part of the page structure. Creatable cards archive the row
	 * and cascade-clean the translations and attached pictures.
	 *
	 * @access public
	 */
	public function display_delete(): void {
		$block_id = (int)($_GET['id'] ?? 0);

		try {
			$block = \Block::get_by_id($block_id);
		} catch (\Exception $e) {
			\Skeleton\Core\Http\Session::redirect('/admin/block');
		}

		try {
			$block->delete();
		} catch (\Exception $e) {
			\Skeleton\Core\Http\Session::redirect('/admin/block?delete_failed=1');
		}

		\Skeleton\Core\Http\Session::redirect('/admin/block?deleted=1');
	}

	public function display_visibility(): void {
		$block_id = (int)($_POST['id'] ?? 0);
		$block = \Block::get_by_id($block_id);
		$block->visible = $block->visible ? 0 : 1;
		$block->save();

		echo json_encode(['visible' => (bool)$block->visible]);
		exit;
	}

	/**
	 * Get the image details for an activity card
	 *
	 * @access private
	 * @param \Block $block
	 * @param int $slot
	 */
	private function get_block_image(\Block $block, int $slot = 1): ?array {
		$file_id = $slot === 1 ? (int)$block->file_id : (int)$block->file_id_2;

		if ($file_id <= 0) {
			return null;
		}

		try {
			$picture = \Skeleton\File\Picture\Picture::get_by_id($file_id);
		} catch (\Exception $e) {
			return null;
		}

		return [
			'file_id' => (int)$picture->id,
			'width' => (int)$picture->width,
			'height' => (int)$picture->height,
		];
	}

	/**
	 * Delete a picture file record (and its stored bytes)
	 *
	 * @access private
	 * @param int $file_id
	 */
	private function delete_picture_file(int $file_id): void {
		try {
			$picture = \Skeleton\File\Picture\Picture::get_by_id($file_id);
			$picture->delete();
		} catch (\Exception $e) {
			// Nothing to remove; the image was already gone.
		}
	}
}
