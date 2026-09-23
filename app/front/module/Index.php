<?php

declare(strict_types=1);

/**
 * Index
 *
 * Homepage. Renders the visible blocks in sort order with their translated
 * content, plus the settings needed by the partials. The visual look of
 * every card is fixed by its type (spec/03 cards rework); the 2-row pair
 * cards carry both images and both texts on one block.
 *
 * @author Roan Buysse <roan@tigron.be>
 */

namespace App\Front\Module;

class Index extends \Skeleton\Application\Web\Module {
	/**
	 * Display the homepage
	 *
	 * @access public
	 */
	public function display(): void {
		$this->template = 'index.twig';

		$template = \Skeleton\Application\Web\Template::get();
		$language = \Language::get();

		$blocks = \Block::get_visible_ordered();

		$block_translations = [];
		$block_pictures = [];
		$block_images = [];

		foreach ($blocks as $block) {
			try {
				$block_translations[$block->id] = $block->get_translation($language);
			} catch (\Exception $e) {
				$block_translations[$block->id] = $block->get_translation(\Language::get_default());
			}

			if ($block->type === 'gallery') {
				$block_pictures[$block->id] = [];
				foreach ($block->get_pictures() as $block_picture) {
					$picture = $block_picture->get_picture();
					$block_pictures[$block->id][] = [
						'id' => $block_picture->id,
						'file_id' => $block_picture->file_id,
						'width' => $picture !== null ? (int)$picture->width : null,
						'height' => $picture !== null ? (int)$picture->height : null,
					];
				}
			}

			if ($block->type !== 'gallery' && (int)$block->file_id > 0) {
				try {
					$picture = \Skeleton\File\Picture\Picture::get_by_id((int)$block->file_id);

					$block_images[$block->id] = [
						'file_id' => (int)$block->file_id,
						'width' => (int)$picture->width,
						'height' => (int)$picture->height,
					];
				} catch (\Exception $e) {
					// No viable image; the card falls back to the text layout.
				}
			}

			if ($block->is_pair() === true && (int)$block->file_id_2 > 0) {
				try {
					$picture = \Skeleton\File\Picture\Picture::get_by_id((int)$block->file_id_2);

					$block_images[$block->id . '_2'] = [
						'file_id' => (int)$block->file_id_2,
						'width' => (int)$picture->width,
						'height' => (int)$picture->height,
					];
				} catch (\Exception $e) {
					// Missing second image; the bottom row renders text-only.
				}
			}

			if ($block->is_pair() === true) {
				$block_pair_bodies[$block->id] = \Block::split_pair_body((string)$block_translations[$block->id]->body);
			}
		}

		// Dark-card parity: every dark background card alternates its corner
		// treatment (top vs bottom) across the page run, like ../redan.
		$dark_parity = 0;
		foreach ($blocks as $block) {
			if (in_array($block->type, [ 'activities_dark' ], true) === false) {
				continue;
			}

			$block_images[$block->id]['parity'] = $dark_parity;
			$dark_parity = 1 - $dark_parity;
		}

		// Monday openings card: filled from the calendar, Ouverture category.
		$block_openings = [];
		$opening_category = \Calendar_Category::get_opening_category();
		if ($opening_category !== null) {
			foreach ($blocks as $block) {
				if ($block->type !== 'monday_openings') {
					continue;
				}

				$block_openings[$block->id] = \Calendar_Event::format_opening_dates(
					\Calendar_Event::get_visible_upcoming_by_category((int)$opening_category->id, 24),
					$language->name_short
				);
			}
		}

		$template->assign('blocks', $blocks);
		$template->assign('block_translations', $block_translations);
		$template->assign('block_pictures', $block_pictures);
		$template->assign('block_images', $block_images);
		$template->assign('block_pair_bodies', $block_pair_bodies ?? []);
		$template->assign('block_openings', $block_openings);
		$template->assign('settings', \Setting::get_all());
	}
}
