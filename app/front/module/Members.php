<?php

declare(strict_types=1);

/**
 * Members
 *
 * The semi-public members area (former "download center"): one shared
 * password unlocks the categorized file list and the calendar tab
 * (spec/07). This module owns the password gate, the file list, the file
 * download and the ICS export; the calendar tab itself lives in
 * `Members\Calendar` (/members/calendar).
 *
 * @author Roan Buysse <roan@tigron.be>
 */

namespace App\Front\Module;

use Skeleton\Core\Http\Session;

class Members extends \Skeleton\Application\Web\Module {
	/**
	 * Login required
	 *
	 * @var bool $login_required
	 */
	protected bool $login_required = false;

	/**
	 * Template
	 *
	 * @var string|null $template
	 */
	protected ?string $template = 'members.twig';

	/**
	 * Member layout shell (own nav, no admin menu)
	 *
	 * @var string $layout_name
	 */
	protected string $layout_name = 'members';

	/**
	 * Display the gate or the members content
	 *
	 * @access public
	 */
	public function display(): void {
		// Route-driven ICS endpoint (/members/calendar.ics via the route
		// variable `suffix`, see app/front/config/routes.php).
		if (($_GET['suffix'] ?? '') === 'calendar.ics') {
			$this->display_ics();
			return;
		}

		// Token URL (/members?access=<shared password>): a valid token
		// grants the members area exactly like the gate and redirects to
		// a clean URL so the secret leaves the address bar.
		if (($_GET['access'] ?? '') !== '') {
			$this->grant_from_secret((string)$_GET['access']);
			return;
		}

		$template = \Skeleton\Application\Web\Template::get();

		if ($this->is_authenticated() === false) {
			$template->assign('show_gate', true);
			return;
		}

		$template->assign('show_gate', false);
		$template->assign('members_tab', 'files');
		$template->assign('categories', $this->get_file_list());
		$template->assign('upcoming_events', \Calendar_Event::get_upcoming_entries(10, true));
	}

	/**
	 * Verify the password and grant access
	 *
	 * @access public
	 */
	public function display_gate(): void {
		if ($this->is_authenticated() === true) {
			Session::redirect('/members');
		}

		$this->grant_from_secret((string)($_POST['password'] ?? ''));
	}

	/**
	 * Grant the members area when the secret matches, redirect to the
	 * gate otherwise
	 *
	 * Shared by the password form (POST) and the token URL (GET): both
	 * verify against the seeded `download_password_hash` setting. The
	 * success redirect always drops the secret from the URL.
	 *
	 * @access private
	 * @param string $secret
	 */
	private function grant_from_secret(string $secret): void {
		$expected_hash = \Setting::get_by_name('download_password_hash');

		if ($expected_hash !== null && $secret !== '' && password_verify($secret, $expected_hash)) {
			$_SESSION['members_authenticated'] = true;
			unset($_SESSION['download_authenticated']);
			Session::redirect('/members');
		}

		Session::redirect('/members?failed=1');
	}

	/**
	 * Stream a single file
	 *
	 * @access public
	 */
	public function display_get(): void {
		if ($this->is_authenticated() === false) {
			Session::redirect('/members');
		}

		$file_id = (int)($_GET['id'] ?? 0);
		$download_file = \Download_File::get_by_id($file_id);
		$file = $download_file->get_file();

		// Stream and stop: handle_request() would otherwise append the
		// template output after the file bytes.
		$file->client_download();
		exit;
	}

	/**
	 * Stream the full ICS calendar (visible events only)
	 *
	 * Public on purpose (spec/07 follow-up): calendar apps and the members
	 * page cannot share the PHP session, so the ICS download needs no
	 * password — exactly like the read-only CalDAV GET. The members *page*
	 * keeps the password gate.
	 *
	 * @access public
	 */
	public function display_ics(): void {
		header('Content-Type: text/calendar; charset=utf-8');
		header('Content-Disposition: inline; filename="gsredan-calendar.ics"');

		echo \Calendar_Event::get_ics();
		exit;
	}

	/**
	 * Clear the members access
	 *
	 * @access public
	 */
	public function display_logout(): void {
		unset($_SESSION['members_authenticated']);
		unset($_SESSION['download_authenticated']);
		Session::redirect('/members');
	}

	/**
	 * Is the visitor authenticated for the members area?
	 *
	 * @access private
	 * @return bool
	 */
	private function is_authenticated(): bool {
		return isset($_SESSION['members_authenticated']) && $_SESSION['members_authenticated'] === true;
	}

	/**
	 * Build the categorized file list for the template
	 *
	 * Groups the visible files per category (ordered); files without a
	 * category land first in an implicit uncategorised group. Categories
	 * without visible files are skipped.
	 *
	 * @access private
	 * @return array [ ['category' => ?name, 'files' => [...]], ... ]
	 */
	private function get_file_list(): array {
		$download_files = \Download_File::get_visible_ordered();

		$groups = [];
		$group_unknown = [];

		foreach ($download_files as $download_file) {
			$category = $download_file->get_category();
			$file = $download_file->get_file();

			$entry = [
				'id' => $download_file->id,
				'name' => $download_file->name,
				'size' => $file->size,
				'size_label' => self::format_bytes((int)$file->size),
			];

			if ($category !== null) {
				$group_key = 'c' . $category->id;
				if (isset($groups[$group_key]) === false) {
					$groups[$group_key] = [
						'category' => $category->name,
						'files' => [],
					];
				}

				$groups[$group_key]['files'][] = $entry;
			} else {
				$group_unknown[] = $entry;
			}
		}

		$result = [];
		if (count($group_unknown) > 0) {
			$result[] = [
				'category' => '',
				'files' => $group_unknown,
			];
		}

		foreach ($groups as $group) {
			$result[] = $group;
		}

		return $result;
	}

	/**
	 * Format a byte count the way the original download list did
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
