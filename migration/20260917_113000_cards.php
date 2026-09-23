<?php

declare(strict_types=1);

/**
 * Database migration class
 *
 * Turns the fixed block types into reusable "cards": the activities row
 * blocks (rallye, salle, training, youth) become `activities` blocks with
 * their own image attachment, `rallye_gallery` becomes a `gallery` block
 * with picture attachments, and a `block_picture` link table allows admins
 * to attach and order arbitrary pictures on any gallery.
 *
 * Current content is transplanted: the hard-coded seed images are registered
 * as real files/pictures so the generic partials keep showing them.
 */

use \Skeleton\Database\Database;

class Migration_20260917_113000_Cards extends \Skeleton\Database\Migration {

	/**
	 * Migrate up
	 *
	 * @access public
	 */
	public function up(): void {
		$db = Database::get();

		// Keep ALTER statements narrow: one column per statement. The first
		// (failed) attempt had applied the schema already, so everything in
		// this phase is guarded to be re-run safely.
		if ($this->has_column('block', 'file_id') === false) {
			$db->query('ALTER TABLE `block` ADD `file_id` int unsigned NULL;');
		}

		if ($this->has_column('block', 'image_layout') === false) {
			$db->query('ALTER TABLE `block` ADD `image_layout` varchar(16) NOT NULL DEFAULT \'featured\';');
		}

		if ($this->has_column('block', 'image_side') === false) {
			$db->query('ALTER TABLE `block` ADD `image_side` varchar(8) NOT NULL DEFAULT \'left\';');
		}

		// The FK may exist from the first attempt (any constraint on
		// block.file_id); skip silently if present.
		$fk = $db->get_one("
			SELECT COUNT(*) FROM information_schema.key_column_usage
			WHERE referenced_table_name = 'file'
			AND table_schema = DATABASE()
			AND table_name = 'block'
		");
		if ((int)$fk === 0) {
			$db->query('ALTER TABLE `block` ADD CONSTRAINT `fk_block_file` FOREIGN KEY (`file_id`) REFERENCES `file` (`id`);');
		}

		$db->query('
			CREATE TABLE IF NOT EXISTS `block_picture` (
				`id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
				`uuid` varchar(36) NULL,
				`block_id` int NOT NULL,
				`file_id` int unsigned NOT NULL,
				`sort_order` int NOT NULL DEFAULT \'0\',
				`visible` tinyint NOT NULL DEFAULT \'1\',
				`created` datetime NOT NULL,
				`updated` datetime NULL,
				`archived` datetime NULL,
				FOREIGN KEY (`block_id`) REFERENCES `block` (`id`),
				FOREIGN KEY (`file_id`) REFERENCES `file` (`id`)
			);
		');

		$this->migrate_types();
	}

	/**
	 * Does the given table have this column?
	 *
	 * @access private
	 * @param string $table
	 * @param string $column
	 */
	private function has_column(string $table, string $column): bool {
		$column = Database::get()->get_one(
			'SELECT COUNT(*) FROM information_schema.columns
			WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
			[ $table, $column ]
		);

		return (int)$column > 0;
	}

	/**
	 * Convert the fixed seed blocks into generic cards and attach their
	 * hard-coded images as real picture files
	 *
	 * @access private
	 */
	private function migrate_types(): void {
		$block_id = $this->find_block('rallye');
		if ($block_id !== null) {
			$this->set_image($block_id, 'rallye-main.webp', 'featured', 'left');
		}

		$block_id = $this->find_block('salle');
		if ($block_id !== null) {
			$this->set_image($block_id, 'salle.webp', 'featured', 'left');
		}

		$block_id = $this->find_block('training');
		if ($block_id !== null) {
			$this->set_image($block_id, 'rally.webp', 'project', 'left');
		}

		$block_id = $this->find_block('youth');
		if ($block_id !== null) {
			$this->set_image($block_id, 'tower.webp', 'project', 'right');
		}

		$block_id = $this->find_block('rallye_gallery');
		if ($block_id !== null) {
			$picture_ids = [
				$this->create_picture('rallye-1.webp'),
				$this->create_picture('rallye-2.webp'),
				$this->create_picture('rallye-3.webp'),
			];

			$sort_order = 0;
			foreach ($picture_ids as $file_id) {
				if ($file_id === null) {
					continue;
				}

				Database::get()->query('
					INSERT INTO `block_picture` (`block_id`, `file_id`, `sort_order`, `visible`, `created`)
					VALUES (?, ?, ?, ?, NOW())
				', [ $block_id, $file_id, $sort_order, 1 ]);

				$sort_order++;
			}

			// The body held the raw <img> markup; the pictures are attached now.
			Database::get()->query('
				UPDATE `block_translation` SET `body` = \'\' WHERE `block_id` = ?
			', [ $block_id ]);
		}

		// Type conversions come last: the four visual text-card rows become
		// activities; the picture row block becomes a gallery.
		Database::get()->query("UPDATE `block` SET `type` = 'activities' WHERE `type` IN ('rallye', 'salle', 'training', 'youth');");
		Database::get()->query("UPDATE `block` SET `type` = 'gallery' WHERE `type` = 'rallye_gallery';");
	}

	/**
	 * Register a media image as a file + picture and return its file id
	 *
	 * @access private
	 * @param string $name filename in app/front/media/image
	 * @return ?int file id (null if the source image is missing)
	 */
	private function create_picture(string $name): ?int {
		$path = dirname(__DIR__) . '/app/front/media/image/' . $name;

		if (is_file($path) === false) {
			return null;
		}

		$content = file_get_contents($path);

		// File::create() is private, so build the file like upload() does.
		$file = new \Skeleton\File\File();
		$file->name = 'block-' . $name;
		$file->md5sum = hash('md5', $content);
		$file->save();

		$destination = $file->get_path();
		$destination_dir = dirname($destination);

		if (is_dir($destination_dir) === false) {
			mkdir($destination_dir, 0755, true);
		}

		file_put_contents($destination, $content);

		$file->mime_type = \Skeleton\File\Util::detect_mime_type($destination);
		$file->size = filesize($destination);
		$file->save();

		// Touching the Picture wrapper creates the picture row on first use.
		\Skeleton\File\Picture\Picture::get_by_id((int)$file->id);

		return (int)$file->id;
	}

	/**
	 * Attach an image to an activities block
	 *
	 * @access private
	 * @param int $block_id
	 * @param string $name
	 * @param string $image_layout 'featured' or 'project'
	 * @param string $image_side 'left' or 'right'
	 */
	private function set_image(int $block_id, string $name, string $image_layout, string $image_side): void {
		$file_id = $this->create_picture($name);
		if ($file_id === null) {
			return;
		}

		Database::get()->query('
			UPDATE `block` SET `file_id` = ?, `image_layout` = ?, `image_side` = ? WHERE `id` = ?
		', [ $file_id, $image_layout, $image_side, $block_id ]);
	}

	/**
	 * Find the singular seed block with the given old type
	 *
	 * @access private
	 */
	private function find_block(string $type): ?int {
		$id = Database::get()->get_one('SELECT id FROM block WHERE type = ? LIMIT 1', [ $type ]);

		return $id !== null ? (int)$id : null;
	}

	/**
	 * Migrate down
	 *
	 * Restores the original types and drops the new structures. Attached
	 * pictures cannot be traced back reliably, so the fixed image sizes best
	 * effort reverts: images stay attached as file ids but blocks fall back
	 * to their original type, where the partials pinned hardcoded images.
	 *
	 * @access public
	 */
	public function down(): void {
		$db = Database::get();

		$db->query("UPDATE `block` SET `type` = 'rallye' WHERE `type` = 'activities' AND `image_layout` = 'featured' AND `anchor` = 'rallye';");
		$db->query("UPDATE `block` SET `type` = 'salle' WHERE `type` = 'activities' AND `image_layout` = 'featured' AND `anchor` = 'salle';");
		$db->query("UPDATE `block` SET `type` = 'training' WHERE `type` = 'activities' AND `image_layout` = 'project' AND `anchor` = 'projects';");
		$db->query("UPDATE `block` SET `type` = 'youth' WHERE `type` = 'activities' AND `image_layout` = 'project' AND `anchor` = '';");
		$db->query("UPDATE `block` SET `type` = 'rallye_gallery' WHERE `type` = 'activities' AND `image_layout` = 'featured' AND `anchor` = '';");

		$db->query('DROP TABLE IF EXISTS `block_picture`;');
		$db->query('ALTER TABLE `block` DROP `file_id`;');
		$db->query('ALTER TABLE `block` DROP `image_layout`;');
		$db->query('ALTER TABLE `block` DROP `image_side`;');
	}
}
