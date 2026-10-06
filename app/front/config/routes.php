<?php
return [
	'routes' => [
		'\\App\\Front\\Module\\Lang' => [
			'/lang/switch',
			'/$language[en,fr,nl]/lang/switch',
		],
		// Language-prefixed routes: the first segment is a $language[...]
		// variable, which the router places in $_GET['language']; the module
		// bootstrap event treats that exactly like the ?language=xx query
		// string, so /fr/..., /nl/..., /en/... render in that language and
		// remember the choice in the session. The unprefixed paths keep
		// working with the session language. /admin and the CalDAV endpoints
		// stay unprefixed by design (see spec/05-i18n.md).
		'\\App\\Front\\Module\\Index' => [ '/$language[en,fr,nl]' ],
		'\\App\\Front\\Module\\Login' => [ '/$language[en,fr,nl]/login' ],
		'\\App\\Front\\Module\\Download' => [ '/$language[en,fr,nl]/download' ],
		'\\App\\Front\\Module\\Picture' => [ '/$language[en,fr,nl]/picture' ],
		// ICS export on the members module; the variable segment maps the
		// calendar.ics path so /members/calendar.ics is route-dispatched.
		'\\App\\Front\\Module\\Members' => [
			'/$language[en,fr,nl]/members',
			'/$language[en,fr,nl]/members/$suffix[calendar.ics]',
			'/members/$suffix[calendar.ics]',
		],
		'\\App\\Front\\Module\\Members\\Calendar' => [
			'/$language[en,fr,nl]/members/calendar',
		],
		// /sitemap.xml cannot be dispatched by path: the module resolver
		// turns the URI into a classname segment by segment, and "Sitemap.xml"
		// is not a classname. A route with a constrained variable is the only
		// way in. robots.txt is NOT here: "txt" is a known media extension, so
		// the media detector answers it before routing — see
		// app/front/event/Media.php.
		'\\App\\Front\\Module\\Sitemap' => [
			'/$file[sitemap.xml]',
		],
		// CalDAV backend; also the well-known discovery paths clients probe
		// first (RFC 6764) before given-URL subscriptions.
		'\\App\\Front\\Module\\Caldav' => [
			'/caldav/$resource',
			'/caldav/GSRedan/$resource',
			'/caldav/GSRedan',
			'/caldav-edit/$resource',
			'/caldav-edit/fD5D6A4Z/$resource',
			'/caldav-edit/fD5D6A4Z/GSRedan/$resource',
			'/caldav-edit',
			'/caldav-edit/fD5D6A4Z',
			'/caldav-edit/fD5D6A4Z',
			'/caldav-edit/fD5D6A4Z/GSRedan',
			'/.well-known/caldav',
			'/.well-known/caldavs',
		],
	],
];
