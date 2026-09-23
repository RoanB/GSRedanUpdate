<?php
return [
	'routes' => [
		'\\App\\Front\\Module\\Lang' => [ '/lang/switch' ],
		// ICS export on the members module; the variable segment maps the
		// calendar.ics path so /members/calendar.ics is route-dispatched.
		'\\App\\Front\\Module\\Members' => [ '/members/$suffix[calendar.ics]' ],
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
