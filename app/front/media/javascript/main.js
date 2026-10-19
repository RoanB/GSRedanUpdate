/*!
 * GS Redan — site behaviour (public pages)
 * Navbar, scroll reveal, Rally countdown, contact form (POST /contact) and Leaflet map.
 *
 * This is the ../gsredanupdate/js/main.js behaviour ported to the skeleton
 * site. The client-side i18n.js dictionary is gone: every string is rendered
 * into the page by the templates (trans tags -> po catalogs), and this script
 * reads the strings back out of the DOM. Language switching is server-side
 * (language-prefixed URLs), so there is no "gsredan:lang" event either.
 *
 * Depends on Bootstrap 5.3.3 (bundle).
 */
(function () {
	'use strict';

	window.addEventListener('DOMContentLoaded', function () {

		/* Current year in the footer (the templates render it server-side
		 * already; this only keeps it honest if the page stays open for a
		 * year turn). */
		var year = String(new Date().getFullYear());
		var yearNodes = document.querySelectorAll('.current-year');
		for (var i = 0; i < yearNodes.length; i++) { yearNodes[i].textContent = year; }

		/* ------------------------------------------------------------------
		 * Navbar: solid background after scrolling, toggler label, auto-close
		 * ------------------------------------------------------------------ */
		var nav = document.getElementById('mainNav');
		var toggler = document.querySelector('#mainNav .navbar-toggler');

		function updateNav() {
			if (!nav) { return; }
			nav.classList.toggle('navbar-shrink', window.scrollY > 24);
		}

		updateNav();
		document.addEventListener('scroll', updateNav, { passive: true });

		if (toggler) {
			var closeLabel = toggler.getAttribute('data-menu-close') || toggler.getAttribute('aria-label') || 'Close menu';
			var openLabel = toggler.getAttribute('aria-label') || 'Open menu';
			var collapseEl = document.getElementById('navbarResponsive');
			if (collapseEl) {
				collapseEl.addEventListener('show.bs.collapse', function () {
					toggler.setAttribute('aria-label', closeLabel);
				});
				collapseEl.addEventListener('hide.bs.collapse', function () {
					toggler.setAttribute('aria-label', openLabel);
				});
				/* Close the mobile menu after choosing a destination */
				collapseEl.addEventListener('click', function (event) {
					var link = event.target.closest('a');
					if (!link) { return; }
					if (window.getComputedStyle(toggler).display !== 'none') {
						bootstrap.Collapse.getOrCreateInstance(collapseEl).hide();
					}
				});
			}
		}

		/* ------------------------------------------------------------------
		 * Topic pre-selection: any a[data-topic] link routes the request
		 * through the contact form with the right topic pre-selected.
		 * ------------------------------------------------------------------ */
		document.addEventListener('click', function (event) {
			var link = event.target.closest('a[data-topic]');
			if (!link) { return; }
			var topic = link.getAttribute('data-topic');
			var select = document.getElementById('cf-topic');

			if (select) {
				/* Same page: select right away, no need to remember anything */
				select.value = topic;
				select.classList.remove('is-invalid');
				select.removeAttribute('aria-invalid');
				try { window.sessionStorage.removeItem('gsredan.topic'); } catch (e) { /* ignore */ }
			} else {
				/* Cross-page: remember the choice for the home page form */
				try { window.sessionStorage.setItem('gsredan.topic', topic); } catch (e) { /* ignore */ }
			}
		});

		/* ------------------------------------------------------------------
		 * Scroll reveal (IntersectionObserver, no library)
		 * ------------------------------------------------------------------ */
		var revealNodes = document.querySelectorAll('.reveal');
		var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

		if (reduceMotion || !('IntersectionObserver' in window)) {
			for (var r = 0; r < revealNodes.length; r++) { revealNodes[r].classList.add('revealed'); }
		} else {
			var observer = new IntersectionObserver(function (entries) {
				entries.forEach(function (entry) {
					if (entry.isIntersecting) {
						entry.target.classList.add('revealed');
						observer.unobserve(entry.target);
					}
				});
			}, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });

			for (var r2 = 0; r2 < revealNodes.length; r2++) { observer.observe(revealNodes[r2]); }
		}

		/* ------------------------------------------------------------------
		 * Rally countdown
		 *
		 * The target date comes from the rally settings (rally_start_date),
		 * rendered by the templates as data-countdown-target on each box;
		 * when the rally is disabled in the admin the countdown is not
		 * rendered at all. The date is read as local time on that day
		 * (09:00), the same reading the original site hardcoded.
		 * The unit labels, the "done" message and the aria-label are
		 * rendered into the markup by the templates and already carry the
		 * page language.
		 * ------------------------------------------------------------------ */
		var countdowns = document.querySelectorAll('[data-countdown]');

		function pad(n) { return (n < 10 ? '0' : '') + n; }

		function renderCountdowns() {
			for (var c = 0; c < countdowns.length; c++) {
				var box = countdowns[c];
				var target = new Date(box.getAttribute('data-target') || '');
				if (isNaN(target.getTime())) { continue; }

				var diff = target.getTime() - Date.now();
				var doneEl = box.querySelector('.cd-done');
				var cellsEl = box.querySelector('.cd-cells');

				if (diff <= 0) {
					if (cellsEl) { cellsEl.hidden = true; }
					if (doneEl) { doneEl.hidden = false; }
					continue;
				}

				if (cellsEl && cellsEl.hidden) { cellsEl.hidden = false; }
				if (doneEl) { doneEl.hidden = true; }

				var days = Math.floor(diff / 86400000);
				var hours = Math.floor(diff / 3600000) % 24;
				var minutes = Math.floor(diff / 60000) % 60;
				var seconds = Math.floor(diff / 1000) % 60;

				var map = { d: String(days), h: pad(hours), m: pad(minutes), s: pad(seconds) };
				for (var key in map) {
					if (Object.prototype.hasOwnProperty.call(map, key)) {
						var slot = box.querySelector('[data-cd="' + key + '"]');
						if (slot) { slot.textContent = map[key]; }
					}
				}
			}
		}

		if (countdowns.length) {
			renderCountdowns();
			window.setInterval(renderCountdowns, 1000);
		}

		/* ------------------------------------------------------------------
		 * Contact form → POST /contact
		 *
		 * The server (module Contact) validates again and sends the mail
		 * through PHP; the alert that appears afterwards is rendered by the
		 * template from a sticky session flag, so there is nothing for this
		 * script on the success path. Here: client-side validation only —
		 * an invalid form never reaches the server; a valid one posts.
		 * ------------------------------------------------------------------ */
		var form = document.getElementById('contact-form');

		if (form) {
			var fields = {
				name: document.getElementById('cf-name'),
				email: document.getElementById('cf-email'),
				topic: document.getElementById('cf-topic'),
				message: document.getElementById('cf-message'),
				consent: document.getElementById('cf-consent')
			};

			/* Restore a topic pre-selected by a data-topic link on another page */
			try {
				var wantedTopic = window.sessionStorage.getItem('gsredan.topic');
				if (wantedTopic && fields.topic) {
					fields.topic.value = wantedTopic;
					window.sessionStorage.removeItem('gsredan.topic');
				}
			} catch (e) { /* storage unavailable */ }

			function setError(input, invalid) {
				if (!input) { return; }
				input.classList.toggle('is-invalid', !!invalid);
				input.setAttribute('aria-invalid', invalid ? 'true' : 'false');
			}

			function validEmail(value) {
				return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(value);
			}

			Object.keys(fields).forEach(function (name) {
				var input = fields[name];
				if (!input) { return; }
				input.addEventListener('input', function () { setError(input, false); });
				input.addEventListener('change', function () { setError(input, false); });
			});

			form.addEventListener('submit', function (event) {
				var name = fields.name.value.trim();
				var email = fields.email.value.trim();
				var topic = fields.topic.value;
				var message = fields.message.value.trim();
				var consent = fields.consent.checked;

				var bad = [];

				if (name.length < 2) { setError(fields.name, true); bad.push(fields.name); }
				if (!validEmail(email)) { setError(fields.email, true); bad.push(fields.email); }
				if (!topic) { setError(fields.topic, true); bad.push(fields.topic); }
				if (message.length < 10) { setError(fields.message, true); bad.push(fields.message); }
				if (!consent) { setError(fields.consent, true); bad.push(fields.consent); }

				if (bad.length) {
					event.preventDefault();
					event.stopPropagation();
					bad[0].focus();
				}

				/* Every field valid: let the browser post the form. */
			});
		}

		/* ------------------------------------------------------------------
		 * Leaflet map — two markers, translated popups, scroll zoom off.
		 * The popup strings (titles, address lines, Maps link) are rendered
		 * into the map container as data-i18n-* attributes.
		 * ------------------------------------------------------------------ */
		var mapEl = document.getElementById('map');

		function mapString(kind, name) {
			return mapEl.getAttribute('data-i18n-' + kind + '-' + name) || '';
		}

		if (mapEl && typeof window.L !== 'undefined') {
			initMap();
		} else if (mapEl && 'IntersectionObserver' in window) {
			var mapObserver = new IntersectionObserver(function (entries) {
				entries.forEach(function (entry) {
					if (entry.isIntersecting) {
						mapObserver.disconnect();
						var css = document.createElement('link');
						css.rel = 'stylesheet';
						css.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
						document.head.appendChild(css);
						var js = document.createElement('script');
						js.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
						js.onload = initMap;
						document.head.appendChild(js);
					}
				});
			}, { rootMargin: '400px' });
			mapObserver.observe(mapEl);
		}

		function initMap() {
			if (initMap.done) { return; }
			initMap.done = true;
			var VENUE = [50.867137, 4.317509];
			var CLUB = [50.8723265, 4.3267280];
			var LINKS = {
				venue: 'https://maps.app.goo.gl/USRK75XeW6wMEvk9A',
				club: 'https://maps.app.goo.gl/FBkR26S1usDrRFsA9'
			};

			var map = L.map(mapEl, {
				scrollWheelZoom: false,
				tapHold: false
			});

			L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
				maxZoom: 19,
				attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>'
			}).addTo(map);

			function pinIcon(hue) {
				return L.divIcon({
					className: '',
					html: '<span class="map-pin" style="background:' + hue + '"><svg class="icon ti" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path stroke="none" d="M0 0h24v24H0z" fill="none"/> <path d="M9 11a3 3 0 1 0 6 0a3 3 0 0 0 -6 0" /> <path d="M17.657 16.657l-4.243 4.243a2 2 0 0 1 -2.827 0l-4.244 -4.243a8 8 0 1 1 11.314 0z" /></svg></span>',
					iconSize: [38, 38],
					iconAnchor: [19, 36],
					popupAnchor: [0, -30]
				});
			}

			function popupHtml(kind) {
				var title = mapString(kind, 'popup');
				var l1 = mapString(kind, 'l1');
				var l2 = mapString(kind, 'l2');
				var linkLabel = mapString(kind, 'link');
				return '<strong>' + title + '</strong><br>' + l1 + '<br>' + l2
					+ '<br><a href="' + LINKS[kind] + '" target="_blank" rel="noopener">' + linkLabel + '</a>';
			}

			var venueTitle = mapString('venue', 'popup');
			var clubTitle = mapString('club', 'popup');

			var venueMarker = L.marker(VENUE, { icon: pinIcon('var(--yellow)'), title: venueTitle, alt: venueTitle })
				.addTo(map).bindPopup(popupHtml('venue'));
			var clubMarker = L.marker(CLUB, { icon: pinIcon('var(--orange)'), title: clubTitle, alt: clubTitle })
				.addTo(map).bindPopup(popupHtml('club'));

			/* Frame both markers, and re-fit once the container has laid out */
			var bounds = L.latLngBounds([VENUE, CLUB]);
			map.fitBounds(bounds.pad(0.35));

			window.setTimeout(function () {
				map.invalidateSize();
				map.fitBounds(bounds.pad(0.35));
			}, 250);
		}

	});
})();
