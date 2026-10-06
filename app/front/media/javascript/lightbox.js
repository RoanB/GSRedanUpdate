/**
 * lightbox.js
 *
 * No-dependency lightbox for the gallery cards (.gallery-lightbox anchors),
 * loaded on the public site and inside the admin block preview iframe. It
 * lives in its own file (rather than base.js) because the public layout does
 * not load the admin bundle. Clicking a picture opens an overlay with the
 * full-quality copy; the overlay supports arrows, keyboard navigation
 * (Esc / arrows) and swiping past the ends closes nothing — arrows wrap
 * around.
 *
 * @author Roan Buysse <roan@tigron.be>
 */

'use strict';

/**
 * Lightbox for the gallery cards (.gallery-lightbox anchors).
 */
function init_gallery_lightbox() {
	var links = document.querySelectorAll('a.gallery-lightbox');

	if (links.length === 0) {
		return;
	}

	var overlay = null;
	var image = null;
	var caption = null;
	var loading = null;
	var group = '';
	var group_links = [];
	var current_index = 0;

	function build_overlay() {
		overlay = document.createElement('div');
		overlay.setAttribute('class', 'gallery-lightbox-overlay');
		overlay.setAttribute('role', 'dialog');
		overlay.setAttribute('aria-modal', 'true');
		overlay.setAttribute('aria-label', 'Image viewer');

		var close = document.createElement('button');
		close.setAttribute('class', 'gallery-lightbox-close');
		close.setAttribute('type', 'button');
		close.setAttribute('aria-label', 'Close');
		close.textContent = '\u00d7';

		var prev = document.createElement('button');
		prev.setAttribute('class', 'gallery-lightbox-prev');
		prev.setAttribute('type', 'button');
		prev.setAttribute('aria-label', 'Previous');
		prev.textContent = '\u2039';

		var next = document.createElement('button');
		next.setAttribute('class', 'gallery-lightbox-next');
		next.setAttribute('type', 'button');
		next.setAttribute('aria-label', 'Next');
		next.textContent = '\u203a';

		loading = document.createElement('div');
		loading.setAttribute('class', 'gallery-lightbox-loading');
		loading.textContent = 'Loading…';

		image = document.createElement('img');
		image.setAttribute('alt', '');

		caption = document.createElement('div');
		caption.setAttribute('class', 'gallery-lightbox-caption');

		overlay.appendChild(close);
		overlay.appendChild(prev);
		overlay.appendChild(loading);
		overlay.appendChild(image);
		overlay.appendChild(next);
		overlay.appendChild(caption);
		document.body.appendChild(overlay);

		close.addEventListener('click', close_overlay);
		prev.addEventListener('click', function () {
			show_index(current_index - 1);
		});
		next.addEventListener('click', function () {
			show_index(current_index + 1);
		});
		overlay.addEventListener('click', function (event) {
			if (event.target === overlay) {
				close_overlay();
			}
		});

		document.addEventListener('keydown', function (event) {
			if (overlay === null || document.body.contains(overlay) === false) {
				return;
			}

			if (document.body.classList.contains('gallery-lightbox-open') === false) {
				return;
			}

			if (event.key === 'Escape') {
				close_overlay();
			} else if (event.key === 'ArrowLeft') {
				show_index(current_index - 1);
			} else if (event.key === 'ArrowRight') {
				show_index(current_index + 1);
			}
		});
	}

	function show_index(index) {
		var count = group_links.length;

		if (count === 0) {
			return;
		}

		current_index = ((index % count) + count) % count;

		var link = group_links[current_index];

		loading.style.display = 'flex';
		image.style.opacity = '0';

		var full_image = new Image();
		full_image.onload = function () {
			image.setAttribute('src', link.href);
			image.setAttribute('alt', link.querySelector('img') !== null ? link.querySelector('img').alt : '');
			image.style.opacity = '1';
			loading.style.display = 'none';
		};
		full_image.onerror = function () {
			loading.style.display = 'none';
			image.setAttribute('src', link.href);
			image.style.opacity = '1';
		};
		full_image.src = link.href;

		caption.textContent = (current_index + 1) + ' / ' + count;
		document.body.classList.add('gallery-lightbox-open');
	}

	function close_overlay() {
		overlay.remove();
		overlay = null;
		document.body.classList.remove('gallery-lightbox-open');
	}

	links.forEach(function (link) {
		link.addEventListener('click', function (event) {
			event.preventDefault();

			group = link.getAttribute('data-gallery-group') || '';

			group_links = [];
			document.querySelectorAll('a.gallery-lightbox[data-gallery-group="' + group + '"]').forEach(function (group_link) {
				group_links.push(group_link);
			});

			current_index = parseInt(link.getAttribute('data-gallery-index') || '0', 10);

			if (overlay === null) {
				build_overlay();
			}

			show_index(current_index);
		});
	});
}

window.addEventListener('DOMContentLoaded', function () {
	init_gallery_lightbox();
});
