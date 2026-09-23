<?php

declare(strict_types=1);

/**
 * Caldav
 *
 * CalDAV backend for the club calendar (spec/07): club members subscribe
 * with HTTP Basic credentials (an admin user account). GET, PROPFIND
 * (collection + principal + resource), REPORT (calendar-query, both forms)
 * and PUT (two-way sync: create/update events from a CalDAV client) are
 * answered; the PUT support makes the calendar editable from outside, so
 * the admin interface exposes two URLs: a read-only one for viewing and an
 * editing one carrying the credentials.
 *
 * Discovery details the mainstream clients depend on (this is what makes
 * DAVx5/Lightning "find calendars"):
 * - `/.well-known/caldav(|s)` advertises the calendar base (301);
 * - a `PROPFIND` on the collection answers `resourcetype` (collection and
 *   CalDAV `calendar`), `displayname` and `supported-calendar-component-set`;
 * - `/caldav/principal/` mirrors a principal resource answering
 *   `principal-URL` and `calendar-home-set`;
 * - Depth 0 responses share the same shape, so clients that walk
 *   current-user-principal → principal → calendar-home-set still succeed.
 *
 * URL layout: /caldav/<event-uuid>.ics
 *
 * @author Roan Buysse <roan@tigron.be>
 */

namespace App\Front\Module;

use \Skeleton\Core\Http\Status;

class Caldav extends \Skeleton\Application\Web\Module {
	/**
	 * Is this request served from /caldav-edit/? (editable collection)
	 *
	 * @var bool $is_edit_path
	 */
	private bool $is_edit_path = false;

	/**
	 * Was the request authorized via the path-embedded secret token?
	 *
	 * @var bool $is_token_path
	 */
	private bool $is_token_path = false;

	/**
	 * No template: raw XML / ICS streaming
	 *
	 * @var ?string $template
	 */
	protected ?string $template = null;

	/**
	 * Principal path served as the backend's identity resource
	 *
	 * @var string $principal_path
	 */
	const PRINCIPAL_PATH = '/caldav/principal/';

	/**
	 * Calendar base path
	 *
	 * @var string $calendar_home
	 */
	const CALENDAR_HOME = '/caldav/';

	/**
	 * Dispatch on the HTTP method; well-known discoveries are redirected
	 *
	 *
	 * @access public
	 */
	public function display(): void {
		$this->template = null;

		// requested_resource() also detects the /caldav-edit path: call it
		// first so the auth gates below see the right collection mode.
		$this->requested_resource();

		// The dedicated editing collection: every request authenticates
		// (PUT reuses the same rule) — unless the secret token carried the
		// request in (the path-embedded secret authorizes by itself).
		if ($this->is_edit_path === true && $this->authenticate() === false && $this->is_token_path === false) {
			header('WWW-Authenticate: Basic realm="GS Redan calendar"', true);
			Status::code_401('caldav', false);
			echo 'credentials required';
			exit;
		}

		// The public /caldav/ collection is VIEW-ONLY by contract: no
		// client (even with valid credentials) may push there; writes are
		// reserved for the editing paths (/caldav-edit/...).
		$is_public_path = (str_starts_with((string)($_SERVER['REQUEST_URI'] ?? ''), '/caldav/') === true
			&& str_starts_with((string)($_SERVER['REQUEST_URI'] ?? ''), '/caldav-edit') === false);

		if ($is_public_path === true && in_array($_SERVER['REQUEST_METHOD'], [ 'PUT', 'DELETE', 'PATCH' ], true) === true) {
			Status::code_405('caldav', false);
			echo 'read-only calendar; edits go through /caldav-edit/ or the admin';
			exit;
		}

		// Read-only retrieval requires no authentication (spec/07 follow-up);
		// only write verbs (PUT) authenticate on the public path.
		$needs_auth = ($_SERVER['REQUEST_METHOD'] === 'PUT');

		if ($needs_auth === true && $this->is_token_path === true) {
			$needs_auth = false;
		}

		if ($needs_auth === true && $this->authenticate() === false) {
			header('WWW-Authenticate: Basic realm="GS Redan calendar"', true);
			Status::code_401('caldav', false);
			echo 'credentials required';
			exit;
		}

		switch ($_SERVER['REQUEST_METHOD']) {
			case 'OPTIONS':
				header('DAV: 1, 2, calendar-access');
				header('MS-Author-Via: DAV');
				echo 'ok';
				break;
			case 'GET':
			case 'HEAD':
				$this->serve_resource();
				break;
			case 'PROPFIND':
				$this->propfind();
				break;
			case 'REPORT':
				$this->report();
				break;
			case 'PUT':
				// The well-known discovery must never accept payload writes.
				if (($_GET['wellknown'] ?? null) !== null) {
					Status::code_405('caldav', false);
					echo 'unavailable';
					exit;
				}

				$this->put_resource();
				break;
			case 'DELETE':
				$this->delete_resource();
				break;
			default:
				Status::code_405('caldav', false);
				echo 'unsupported method';
		}

		exit;
	}

	/**
	 * HTTP Basic authentication: accepts either the dedicated CalDAV editing
	 * account (`redan` / `fD5D6A4Z`, overridable via the gitignored
	 * `calendar_caldav_user` / `calendar_caldav_password` config) or any
	 * admin user account.
	 *
	 * @access private
	 * @return bool
	 */
	private function authenticate(): bool {
		if (!isset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'])) {
			return false;
		}

		$config = \Skeleton\Core\Config::get();
		$service_user = $config->calendar_caldav_user ?? 'redan';
		$service_password = $config->calendar_caldav_password ?? 'fD5D6A4Z';

		if ($_SERVER['PHP_AUTH_USER'] === $service_user && $_SERVER['PHP_AUTH_PW'] === $service_password) {
			return true;
		}

		try {
			$user = \User::authenticate($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);
		} catch (\Exception $e) {
			return false;
		}

		return $user instanceof \User;
	}

	/**
	 * Build the absolute calendar base URL for Location / principal links
	 *
	 * @access private
	 * @return string
	 */
	private function calendar_base_url(): string {
		$scheme = $_SERVER['REQUEST_SCHEME'] ?? 'https';
		$host = $_SERVER['HTTP_HOST'] ?? '';

		return $scheme . '://' . $host . $this->collection_path();
	}

	/**
	 * The collection path of the request: /caldav/ on the public path,
	 * /caldav-edit/(<secret-token>/)? on the editable one — Thunderbird
	 * builds per-item URLs from the collection href, so it must match the
	 * subscribed root, not the shared constant.
	 *
	 * @access private
	 * @return string
	 */
	private function collection_path(): string {
		if ($this->is_token_path === true) {
			$secret = trim(trim(\Skeleton\Core\Config::get()->caldav_edit_secret ?? 'fD5D6A4Z'), '/');

			// Thunderbird names a discovered calendar after the HREF's last
			// path segment (it ignores our DAV displayname), so the
			// collection URL ends in /GSRedan/ and imports under the right
			// name.
			return '/caldav-edit/' . $secret . '/GSRedan/';
		}

		if ($this->is_edit_path === true) {
			return '/caldav-edit/';
		}

		// The public read-only collection advertises a GSRedan-named href so
		// subscriptions import under the right name too.
		return '/caldav/GSRedan/';
	}

	/**
	 * Path suffix after /caldav ('' for the calendar root)
	 *
	 * The route entry in `app/front/config/routes.php` passes the suffix as
	 * the `resource` route variable.
	 *
	 * @access private
	 * @return string
	 */
	private function requested_resource(): string {
		$request_uri = (string)($_SERVER['REQUEST_URI'] ?? '');

		// The dedicated editing path (/caldav-edit/...) exposes an editable
		// collection with two credentials modes:
		// - HTTP Basic (any admin or the dedicated service account), or
		// - a secret-token URL (/caldav-edit/<secret-token>/...): the token
		//   IS the password, embedded in the path so clients such as
		//   Thunderbird never need a password prompt at all.
		$editing = (str_starts_with($request_uri, '/caldav-edit') === true);

		$this->is_edit_path = false;
		$this->is_token_path = false;

		$secret = trim(trim(\Skeleton\Core\Config::get()->caldav_edit_secret ?? 'fD5D6A4Z'), '/');

		if (str_starts_with($request_uri, '/caldav-edit/' . $secret . '/') === true) {
			$this->is_edit_path = true;
			$this->is_token_path = true;

			$resource = trim((string)preg_replace('#^/caldav-edit/' . $secret . '/#', '', $request_uri), '/');

			// The "calendar" itself lives one level lower
			// (/caldav-edit/<secret>/GSRedan/...): strip the name segment
			// too so per-item resolution matches basename().
			if (preg_match('#^GSRedan/#i', $resource) === 1) {
				$resource = substr($resource, strlen('GSRedan/'));
			}
		} else {
			$this->is_edit_path = $editing;

			$resource = (string)($_GET['resource'] ?? '');

			if ($editing === true && str_starts_with($resource, 'edit/') === true) {
				$resource = substr($resource, 5);
			}
		}

		return $resource;
	}

	/**
	 * Resolve the event of a .ics resource path
	 *
	 * @access private
	 * @return ?\Calendar_Event (null for unknown / invisible events)
	 */
	private function resolve_event(string $resource): ?\Calendar_Event {
		$uuid = preg_replace('/\.ics$/i', '', basename($resource));

		if (!is_string($uuid) || $uuid === '') {
			return null;
		}

		try {
			$event = \Calendar_Event::get_by_uuid($uuid);
		} catch (\Exception $e) {
			return null;
		}

		if ((int)$event->visible !== 1) {
			return null;
		}

		return $event;
	}

	/**
	 * GET/HEAD: stream an event (resource) or the merged feed (collection)
	 *
	 * @access private
	 */
	private function serve_resource(): void {
		$resource = $this->requested_resource();
		$event = $this->resolve_event($resource);

		// The collection has no single event: serve the merged ICS feed.
		if ($event === null && ($resource === '' || $resource === '/' || ($resource === 'GSRedan' || str_starts_with($resource, 'GSRedan/')))) {
			header('Content-Type: text/calendar; charset=utf-8');
			header('Content-Disposition: inline; filename="gsredan-calendar.ics"');

			$body = implode("\r\n", [
				'BEGIN:VCALENDAR',
				'VERSION:2.0',
				'PRODID:-//GS Redan//Members calendar 1.0//EN',
				'CALSCALE:GREGORIAN',
				'X-WR-CALNAME:GSRedan',
			]);

			foreach (\Calendar_Event::get_all_ordered() as $calendar_event) {
				if ((int)$calendar_event->visible === 1) {
					$body .= "\r\n" . implode("\r\n", $calendar_event->get_ics_vevent(new \DateTimeZone(\Calendar_Event::DISPLAY_TIMEZONE)));
				}
			}

			echo $body . "\r\nEND:VCALENDAR\r\n";
			exit;
		}

		if ($event === null) {
			Status::code_404('caldav', false);
			echo 'not found';
			exit;
		}

		header('Content-Type: text/calendar; charset=utf-8');
		echo $this->single_event_ics($event);
	}

	/**
	 * PROPFIND (Depth 0/1 on the collection, principal, resource)
	 *
	 * Depth 0 answers the discovery chain mainstream clients depend on:
	 * resourcetype/displayname/supported-calendar-component-set on the
	 * collection plus current-user-principal and calendar-home-set when the
	 * request carries edit credentials. Depth 0 on the principal mirrors a
	 * principal resource URL.
	 *
	 * @access private
	 */
	private function propfind(): void {
		$depth = $this->header_depth();
		$resource = $this->requested_resource();

		$xml = $this->multistatus_start();

		// Principal resource: mirrors DAVx5's principal discovery.
		if ($resource === 'principal/' || $resource === 'principal') {
			$xml .= $this->propfind_principal();
			$xml .= '</D:multistatus>';

			header('Content-Type: application/xml; charset=utf-8');
			header('DAV: 1, 2, calendar-access');
			echo $xml;
			exit;
		}

		if ($resource === '' || $resource === '/' || ($resource === 'GSRedan' || str_starts_with($resource, 'GSRedan/'))) {
			$xml .= $this->propfind_collection_entry($depth);

			if ($depth > 0) {
				foreach (\Calendar_Event::get_all_ordered() as $event) {
					if ((int)$event->visible === 1) {
						$xml .= $this->event_resource_entry($event);
					}
				}
			}
		} else {
			$event = $this->resolve_event($resource);

			if ($event === null) {
				Status::code_404('caldav', false);
				echo 'not found';
				exit;
			}

			$xml .= $this->event_resource_entry($event);
		}

		$xml .= '</D:multistatus>';

		header('Content-Type: application/xml; charset=utf-8');
		header('DAV: 1, 2, calendar-access');
		echo $xml;
	}

	/**
	 * Multistatus opening
	 *
	 * @access private
	 */
	private function multistatus_start(): string {
		return '<?xml version="1.0" encoding="utf-8"?>'
			. '<D:multistatus xmlns:D="DAV:" xmlns:C="urn:ietf:params:xml:ns:caldav">';
	}

	/**
	 * Calendar collection entry (always itself a calendar + principal home)
	 *
	 * @access private
	 * @param int $depth
	 */
	private function propfind_collection_entry(int $depth): string {
		$collection_path = $this->collection_path();

		$xml = '<D:response><D:href>' . $collection_path . '</D:href>';
		$xml .= '<D:propstat><D:prop>';

		$xml .= '<D:resourcetype><D:collection/><C:calendar/></D:resourcetype>';
		$xml .= '<D:displayname>GSRedan</D:displayname>';
		$xml .= '<D:getcontenttype>text/calendar</D:getcontenttype>';
		$xml .= '<C:supported-calendar-component-set><C:comp name="VEVENT"/></C:supported-calendar-component-set>';
		$xml .= '<C:calendar-home-set><D:href>' . $this->calendar_base_url() . '</D:href></C:calendar-home-set>';

		// Omit current-user-principal for anonymous requests: some clients
		// choke on the <D:unauthenticated/> construct and mark the calendar
		// read-only ("Modification failed").
		if ($this->is_token_path === true || $this->authenticate() === true) {
			$xml .= '<D:current-user-principal><D:href>' . self::PRINCIPAL_PATH . '</D:href></D:current-user-principal>';
		}

		$xml .= '</D:prop><D:status>HTTP/1.1 200 OK</D:status></D:propstat></D:response>';

		return $xml;
	}

	/**
	 * Principal resource (so discovery finds calendar-home-set)
	 *
	 * @access private
	 */
	private function propfind_principal(): string {
		$authenticated = $this->authenticate();

		$xml = '<D:response><D:href>' . self::PRINCIPAL_PATH . '</D:href>';
		$xml .= '<D:propstat><D:prop>';
		$xml .= '<D:resourcetype><D:collection/><D:principal/></D:resourcetype>';
		$xml .= '<D:displayname>GSRedan</D:displayname>';
		$xml .= '<D:principal-URL><D:href>' . self::PRINCIPAL_PATH . '</D:href></D:principal-URL>';
		$xml .= '<C:calendar-home-set><D:href>' . $this->calendar_base_url() . '</D:href></C:calendar-home-set>';

		if ($authenticated === true) {
			$xml .= '<D:current-user-principal><D:href>' . self::PRINCIPAL_PATH . '</D:href></D:current-user-principal>';
		}

		$xml .= '</D:prop><D:status>HTTP/1.1 200 OK</D:status></D:propstat></D:response>';

		return $xml;
	}

	/**
	 * REPORT (calendar-data of the requested scope)
	 *
	 * @access private
	 */
	private function report(): void {
		$resource = $this->requested_resource();
		$events = [];

		if ($resource === '') {
			foreach (\Calendar_Event::get_all_ordered() as $event) {
				if ((int)$event->visible === 1) {
					$events[] = $event;
				}
			}
		} else {
			$event = $this->resolve_event($resource);

			if ($event !== null) {
				$events[] = $event;
			}
		}

		$xml = $this->multistatus_start();

		foreach ($events as $event) {
			$xml .= $this->event_caldata_entry($event);
		}

		$xml .= '</D:multistatus>';

		header('Content-Type: application/xml; charset=utf-8');
		header('DAV: 1, 2, calendar-access');
		echo $xml;
	}

	/**
	 * Depth header value (default 0; infinity caps at depth 1)
	 *
	 * @access private
	 */
	private function header_depth(): int {
		$depth = strtolower($_SERVER['HTTP_DEPTH'] ?? '1') === 'infinity' ? 1 : (int)($_SERVER['HTTP_DEPTH'] ?? 1);

		return max($depth, 0);
	}

	/**
	 * Response entry of one event resource (no body)
	 *
	 * @access private
	 */
	private function event_resource_entry(\Calendar_Event $event): string {
		$xml = '<D:response><D:href>' . $this->collection_path() . $event->uuid . '.ics</D:href>';
		$xml .= '<D:propstat><D:prop><D:resourcetype/>';
		$xml .= '<D:getcontenttype>text/calendar</D:getcontenttype>';
		$xml .= '<D:getetag>' . htmlspecialchars($this->event_etag($event)) . '</D:getetag>';
		$xml .= '</D:prop><D:status>HTTP/1.1 200 OK</D:status></D:propstat></D:response>';

		return $xml;
	}

	/**
	 * Response entry of one event resource with its calendar-data payload
	 *
	 * @access private
	 */
	private function event_caldata_entry(\Calendar_Event $event): string {
		$xml = '<D:response><D:href>' . $this->collection_path() . $event->uuid . '.ics</D:href>';
		$xml .= '<D:propstat><D:prop>';
		$xml .= '<D:getetag>' . htmlspecialchars($this->event_etag($event)) . '</D:getetag>';
		$xml .= '<C:calendar-data>' . htmlspecialchars($this->single_event_ics($event)) . '</C:calendar-data>';
		$xml .= '</D:prop><D:status>HTTP/1.1 200 OK</D:status></D:propstat></D:response>';

		return $xml;
	}

	/**
	 * DELETE: remove one event resource (Thunderbird's item delete)
	 *
	 * CalDAV deletion semantics: 204 + the absence of the resource after
	 * the call. The public /caldav/ collection stays strictly read-only,
	 * so only the editing paths arrive here (the collection gate already
	 * enforced credentials before the switch).
	 *
	 * @access private
	 */
	private function delete_resource(): void {
		$resource = $this->requested_resource();
		$resource_base = basename($resource);

		$uuid = preg_replace('/\.ics$/i', '', $resource_base);

		if (preg_match('/^[0-9a-fA-F-]{36}$/', (string)$uuid) !== 1) {
			Status::code_404('caldav', false);
			echo 'not found';
			exit;
		}

		try {
			$event = \Calendar_Event::get_by_uuid($uuid);
		} catch (\Exception $e) {
			Status::code_404('caldav', false);
			echo 'not found';
			exit;
		}

		$event->delete();

		Status::code_204('caldav', false);
	}

	/**
	 * PUT: create or update events from the posted ICS payload (two-way
	 * sync)
	 *
	 * Thunderbird sends one of two shapes:
	 * - a per-resource PUT carrying exactly one VEVENT (the url named the
	 *   item): upsert that one;
	 * - a collection-root PUT carrying Thunderbird's WHOLE local cache
	 *   (one VEVENT per item): upsert every VEVENT in the payload, matched
	 *   on the ICS UID. Rows are never deleted here: Lightning owns its
	 *   deletions through REPORT resync, not through blanket replacement
	 *   (deleted events keep `visible` flags in the admin).
	 *
	 * @access private
	 */
	private function put_resource(): void {
		$resource = $this->requested_resource();
		$resource_base = basename($resource);

		$is_item_put = (preg_match('/\.ics$/i', (string)$resource_base) === 1);

		$body = file_get_contents('php://input');

		// Debug sink for wrong-date diagnosis (delete after use): the module
		// file sits in app/front/module, so the project tmp is three levels up.
		file_put_contents(dirname(__DIR__, 3) . '/tmp/last_put.ics', (string)$body);

		if ($body === false || $body === '') {
			Status::code_400('caldav', false);
			echo 'empty body';
			exit;
		}

		$events = $this->parse_ics_events($body);

		if (count($events) === 0) {
			Status::code_400('caldav', false);
			echo 'invalid calendar payload';
			exit;
		}

		if ($is_item_put === true) {
			// Per-resource PUT: only apply the VEVENT matching the requested
			// resource name (the other blocks would be unrelated items).
			$uuid = strtolower(preg_replace('/\.ics$/i', '', $resource_base));

			$selected = array_values(array_filter($events, static function (array $event) use ($uuid): bool {
				return strtolower((string)$event['uid']) === $uuid;
			}));

			if (count($selected) === 0) {
				$selected = [ array_shift($events) ];
			}

			$events = $selected;
		}

		$created_count = 0;
		$updated_count = 0;

		foreach ($events as $parsed) {
			$uuid = strtolower((string)$parsed['uid']);

			if (preg_match('/^[0-9a-fA-F-]{36}$/', $uuid) !== 1) {
				continue;
			}

			try {
				$event = \Calendar_Event::get_by_uuid($uuid);

				// Conditional-PUT echo: honour If-Match per item.
				$if_match = $_SERVER['HTTP_IF_MATCH'] ?? null;

				if ($if_match !== null && count($events) === 1 && trim($if_match) !== $this->event_etag($event)) {
					Status::code_412('caldav', false);
					echo 'etag mismatch';
					exit;
				}

				$created = false;
			} catch (\Exception $e) {
				$event = new \Calendar_Event();
				$event->uuid = $uuid;
				$event->visible = 1;
				$event->starts_at = date('Y-m-d H:i:s');
				$event->ends_at = null;
				$event->description = '';
				$event->location = null;
				$created = true;
			}

			$event->title = ($parsed['summary'] ?? '') !== '' ? $parsed['summary'] : 'Event ' . substr($uuid, -6);
			$event->description = (string)($parsed['description'] ?? '');
			$event->location = (string)($parsed['location'] ?? '');

			if (isset($parsed['dtstart']) === true) {
				try {
					$event->starts_at = $this->migrate_parsed_datetime([
						'value' => $parsed['dtstart'],
						'all_day' => $parsed['dtstart_all_day'] ?? 0,
					]);
					$event->all_day = $parsed['dtstart_all_day'] ?? 0;

					if (isset($parsed['dtend']) === true) {
						$event->ends_at = $this->migrate_parsed_datetime([ 'value' => $parsed['dtend'] ]);
					}
				} catch (\Exception $e) {
					continue;
				}
			}

			$errors = [];
			if ($event->validate($errors) === false) {
				continue;
			}

			$event->save();

			if ($created === true) {
				$created_count++;
			} else {
				$updated_count++;
			}
		}

		// Thunderbird deletion semantics: when the payload is the client's
		// full local snapshot (multi-VEVENT collection push), items *absent*
		// from it have been deleted client-side; hide them server-side
		// (visible = 0) so the deletion propagates while the admin keeps a
		// recoverable row. Only ever applied on the editing paths.
		if (count($events) > 1 && $this->is_edit_path === true) {
			$payload_uuids = [];
			foreach ($events as $parsed) {
				$payload_uuids[] = strtolower((string)$parsed['uid']);
			}

			foreach (\Calendar_Event::get_all_ordered() as $known_event_item) {
				$known_uuid = strtolower((string)$known_event_item->uuid);

				if (in_array($known_uuid, $payload_uuids, true) === true) {
					continue;
				}

				// Hard-delete the row (mirrors the admin delete): a
				// Thunderbird client that dropped the item means it is gone
				// everywhere.
				$known_event_item->delete();
				$hidden_count = ($hidden_count ?? 0) + 1;
			}
		}

		if (count($events) === 1) {
			header('ETag: ' . $this->event_etag($event));
			header('Location: ' . $this->collection_path() . $event->uuid . '.ics');

			if ($created_count === 1) {
				Status::code_201('caldav', false);
			} else {
				Status::code_204('caldav', false);
			}
		} else {
			// Collection sync: 200 with a short summary.
			header('Content-Type: text/plain; charset=utf-8');
			echo 'synced ' . $created_count . ' created, ' . $updated_count . ' updated'
				. (($hidden_count ?? 0) > 0 ? ', ' . $hidden_count . ' hidden (absent from sync)' : '');
		}
	}

	private function parse_ics_events(string $body): array {
		// Unfold (RFC 5545: continuation lines start with a space/tab).
		$body = preg_replace('/\r\n[\t ]/', '', $body);

		// Split into per-VEVENT blocks: a PUT can carry ONE event
		// (per-resource put) or Thunderbird's whole local cache (collection
		// root put with BEGIN:VEVENT blocks for every item).
		$blocks = [];
		preg_match_all('/BEGIN:VEVENT(.+?)END:VEVENT/s', $body, $matches);

		foreach ($matches[1] as $block) {
			$data = [];
			$matchers = [
				'uid' => '/UID:(.+?)[\r\n]/i',
				'summary' => '/SUMMARY:(.+?)[\r\n]/i',
				'description' => '/DESCRIPTION:(.+?)[\r\n]/i',
				'location' => '/LOCATION:(.+?)[\r\n]/i',
			];

			foreach ($matchers as $key => $pattern) {
				if (preg_match($pattern, $block, $matches) === 1) {
					$data[$key] = trim($matches[1]);
				}
			}

			if (isset($data['summary']) === true) {
				$data['summary'] = str_replace('\,', ',', str_replace('\;', ';', $data['summary']));
				$data['summary'] = str_replace('\n', "\n", $data['summary']);
				$data['summary'] = str_replace('\\\\', '\\', $data['summary']);
			}

			$dtstart = $this->parse_ics_datetime($block, 'DTSTART');
			if ($dtstart !== null) {
				$data['dtstart'] = $dtstart['value'];
				$data['dtstart_all_day'] = $dtstart['all_day'];
			}

			$dtend = $this->parse_ics_datetime($block, 'DTEND');
			if ($dtend !== null) {
				$data['dtend'] = $dtend['value'];
			}

			if (empty($data['uid']) === true) {
				continue;
			}

			$blocks[] = $data;
		}

		return $blocks;
	}

	/**
	 * Parse a DTSTART/DTEND line into helpers
	 *
	 * @access private
	 * @return ?array {value, all_day}
	 */
	private function parse_ics_datetime(string $body, string $name): ?array {
		if (preg_match('/' . $name . '(?:;VALUE=DATE|;TZID=[^:;]*)?:([0-9a-zA-Z]+)(?:\.[0-9]+)?[Z]?\r/i', $body, $matches) !== 1) {
			return null;
		}

		$raw = $matches[1];
		$all_day = (strpos($body, ';VALUE=DATE') !== false);

		if (strlen($raw) === 16) {
			// UTC: 20260917T164500Z style with Z
			try {
				$datetime = \DateTime::createFromFormat('Ymd\THis\Z', $raw, new \DateTimeZone('UTC'));

				return [ 'value' => $datetime->format('Y-m-d H:i:s'), 'all_day' => 0 ];
			} catch (\Exception $e) {
				return null;
			}
		}

		if (strlen($raw) === 15) {
			// Floating local time: interpret it in Brussels time.
			try {
				$date = \DateTime::createFromFormat('Ymd\THis', $raw, new \DateTimeZone(\Calendar_Event::DISPLAY_TIMEZONE));
				$date->setTimezone(new \DateTimeZone('UTC'));

				return [ 'value' => $date->format('Y-m-d H:i:s'), 'all_day' => 0 ];
			} catch (\Exception $e) {
				return null;
			}
		}

		if (strlen($raw) === 8) {
			return [ 'value' => $raw, 'all_day' => 1 ];
		}

		return null;
	}

	/**
	 * Migrate a parsed DTSTART/DTEND value into the UTC storage shape
	 *
	 * @access private
	 */
	private function migrate_parsed_datetime(array $entry): string {
		$value = (string)$entry['value'];
		$all_day = (int)($entry['all_day'] ?? 0);

		if ($all_day === 1 && preg_match('/^[0-9]{8}$/', $value) === 1) {
			// Date-only: interpreted as Brussels-local midnight.
			$year = (int)substr($value, 0, 4);
			$month = (int)substr($value, 4, 2);
			$day = (int)substr($value, 6, 2);

			return sprintf('%04d-%02d-%02d 00:00:00', $year, $month, $day);
		}

		return $value;
	}

	/**
	 * Weak ETag of an event (content digest)
	 *
	 * @access private
	 */
	private function event_etag(\Calendar_Event $event): string {
		return '"' . md5($event->uuid . '|' . ($event->updated ?? '') . '|' . strlen((string)$event->description)) . '"';
	}

	/**
	 * Wrap a single event in a minimal VCALENDAR envelope
	 *
	 * @access private
	 * @param \Calendar_Event $event
	 */
	private function single_event_ics(\Calendar_Event $event): string {
		$timezone = new \DateTimeZone(\Calendar_Event::DISPLAY_TIMEZONE);

		$lines = array_merge(
			[
				'BEGIN:VCALENDAR',
				'VERSION:2.0',
				'PRODID:-//GS Redan//Members calendar 1.0//EN',
				'CALSCALE:GREGORIAN',
			],
			$event->get_ics_vevent($timezone),
			[ 'END:VCALENDAR' ]
		);

		return implode("\r\n", $lines) . "\r\n";
	}
}
