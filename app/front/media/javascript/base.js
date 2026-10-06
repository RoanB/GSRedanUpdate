/**
 * base.js
 *
 * Admin behaviour for the GS Redan backend: drag-and-drop ordering of
 * reorderable tables, visibility toggles, and showing/hiding password fields.
 * No jQuery or other dependency is required.
 *
 * @author Roan Buysse <roan@tigron.be>
 */

'use strict';

/**
 * Script-error probe: any uncaught JS error on a page that also contains a
 * calendar is exposed next to the calendar container (visible without the
 * console, useful when browser extensions intercept page scripts).
 */
function report_script_error(message) {
	var container = document.querySelector('.card > #admin-calendar, #admin-calendar');

	var note = document.getElementById('base-script-error');

	if (note === null && container !== null) {
		note = document.createElement('div');
		note.setAttribute('id', 'base-script-error');
		note.setAttribute('class', 'text-danger small');
		note.style.padding = '.25rem 0';

		container.parentNode.insertBefore(note, container);
	}

	if (note !== null) {
		note.textContent = 'Script error: ' + message;
	}
}

window.addEventListener('error', function (event) {
	report_script_error((event.message || 'unknown') + (event.filename ? ' (' + event.filename + ':' + event.lineno + ')' : ''));
}, true);

/**
 * Make tables with a data-sort-url attribute reorderable by dragging rows.
 *
 * The new order is posted as ordered[] to the data-sort-url on drop.
 */
function init_sortable_tables() {
	var tables = document.querySelectorAll('table[data-sort-url]');

	tables.forEach(function (table) {
		var tbody = table.querySelector('tbody');

		if (tbody === null) {
			return;
		}

	var dragged = null;

	tbody.querySelectorAll('tr.sortable-row').forEach(function (row) {
		if (row.classList.contains('non-draggable') === true) {
			return;
		}

		row.setAttribute('draggable', 'true');

		// Thumbnails inside the rows are natively draggable and hijack the
		// drag: the browser then drags the image instead of the row. Kill
		// the native behaviour so the row always wins. Form fields and
		// buttons get the same treatment, or the inline rename input would
		// drag its row instead of letting the admin select text in it, and a
		// copy button in a row would drag the row on a sloppy click.
		row.querySelectorAll('img').forEach(function (image) {
			image.setAttribute('draggable', 'false');
		});

		row.querySelectorAll('input, select, textarea, button').forEach(function (field) {
			field.setAttribute('draggable', 'false');
		});

			row.addEventListener('dragstart', function () {
				dragged = row;
				row.classList.add('table-active');
			});

			row.addEventListener('dragend', function () {
				row.classList.remove('table-active');
			});

			row.addEventListener('dragover', function (event) {
				event.preventDefault();

				if (dragged === null || dragged === row) {
					return;
				}

				var rect = row.getBoundingClientRect();
				var should_move_after = (event.clientY - rect.top) > (rect.height / 2);

				if (should_move_after) {
					row.parentNode.insertBefore(dragged, row.nextSibling);
				} else {
					row.parentNode.insertBefore(dragged, row);
				}
			});
		});

		// Allow drops anywhere on the tbody, not only over rows: without a
		// dragover default-prevention here the drop event never fires when
		// the pointer is over a gap instead of a row.
		tbody.addEventListener('dragover', function (event) {
			event.preventDefault();
		});

		tbody.addEventListener('drop', function (event) {
			event.preventDefault();
			var params = new URLSearchParams();
			tbody.querySelectorAll('tr.sortable-row').forEach(function (row) {
				params.append('ordered[]', row.getAttribute('data-id'));
			});

			fetch(table.getAttribute('data-sort-url'), {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: params.toString(),
			}).then(function () {
				// The edit screen previews the reordered table (gallery
				// pictures): re-render it once the new order is persisted,
				// otherwise the preview keeps the stale order.
				if (typeof window.block_preview_refresh === 'function') {
					window.block_preview_refresh();
				}
			});
		});
	});
}

/**
 * Toggle a visibility state through the button's data-visibility-url.
 *
 * The endpoint answers with { "visible": true|false } and the button label
 * comes from its data-label-on / data-label-off attributes.
 */
function init_visibility_toggles() {
	var buttons = document.querySelectorAll('.visibility-toggle');

	buttons.forEach(function (button) {
		button.addEventListener('click', function () {
			var params = new URLSearchParams();
			params.append('id', button.getAttribute('data-id'));

			fetch(button.getAttribute('data-visibility-url'), {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: params.toString(),
			})
				.then(function (response) {
					return response.json();
				})
				.then(function (data) {
					button.textContent = data.visible ? button.getAttribute('data-label-on') : button.getAttribute('data-label-off');

					if (data.visible) {
						button.classList.remove('btn-outline-secondary');
						button.classList.add('btn-success');
					} else {
						button.classList.remove('btn-success');
						button.classList.add('btn-outline-secondary');
					}
				});
		});
	});
}

/**
 * Explain the option a <select> currently has (data-explanation-for).
 *
 * A select cannot hold rich content, so every option carries its own one
 * line explanation in a data-explanation attribute and the hint the option
 * points at (the attribute's value is the hint's id) is refilled from it on
 * every change. The texts travel from the template for the same reason the
 * other JavaScript strings do: the i18n extractor does not scan JS files.
 */
function init_select_explanations() {
	var selects = document.querySelectorAll('select[data-explanation-for]');

	selects.forEach(function (select) {
		var target = document.getElementById(select.getAttribute('data-explanation-for'));

		if (target === null) {
			return;
		}

		var show_selected_explanation = function () {
			var explanation = select.options[select.selectedIndex].getAttribute('data-explanation');

			if (explanation === null) {
				return;
			}

			target.textContent = explanation;
		};

		select.addEventListener('change', show_selected_explanation);

		show_selected_explanation();
	});
}

/**
 * Show or hide a password field referenced by [data-toggle-password].
 */
function init_password_toggles() {
	var toggles = document.querySelectorAll('[data-toggle-password]');

	toggles.forEach(function (toggle) {
		toggle.addEventListener('click', function () {
			var target = document.querySelector(toggle.getAttribute('data-toggle-password'));

			if (target === null) {
				return;
			}

			if (target.getAttribute('type') === 'password') {
				target.setAttribute('type', 'text');
			} else {
				target.setAttribute('type', 'password');
			}
		});
	});
}

/**
 * Format a HTML string with consistent spacing: one tag per line, four
 * -tab indentation that follows the element nesting and collapsed bare
 * text nodes, so the saved HTML reads the same everywhere it is edited.
 *
 * Void tags and self-closing tags never change the depth; <pre>, <script>
 * and <style> bodies are copied verbatim.
 *
 * @param string code
 * @return string formatted_code
 */
function format_html(code) {
	var void_tags = ['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr'];
	var verbatim_tags = ['pre', 'script', 'style', 'textarea'];
	var tokens = code.match(/<!--[\s\S]*?-->|<[^>]+>|[^<]+/g) || [];
	var indent = '';
	var lines = [];
	var verbatim = null;
	var inline_run = [];

	/**
	 * Flush a run of text as a single line so words stay wrapped together.
	 */
	function flush_inline() {
		var text = inline_run.join('').trim();

		if (text !== '') {
			lines.push(indent + text);
		}

		inline_run = [];
	}

	tokens.forEach(function (token) {
		// Inside <pre>/<script>/<style>: copy the tokens untouched until the
		// matching closing tag ends the block.
		if (verbatim !== null) {
			if (token.toLowerCase().replace(/\s+/g, '') === '</' + verbatim + '>') {
				var verbatim_content = inline_run.join('');

				if (verbatim_content !== '') {
					lines.push(verbatim_content);
				}

				inline_run = [];
				indent = indent.slice(1);
				lines.push(indent + token);
				verbatim = null;
			} else {
				inline_run.push(token);
			}

			return;
		}

		var match = token.match(/^<\s*(\/)?\s*([a-zA-Z0-9]+)/);

		if (match === null) {
			// Text or comment: comments emit on their own line.
			if (token.indexOf('<!--') === 0) {
				flush_inline();
				lines.push(indent + token.trim());
			} else {
				inline_run.push(token);
			}

			return;
		}

		flush_inline();

		var tag_name = match[2].toLowerCase();
		var closing = match[1] !== undefined;
		var self_closing = /\/\s*>$/.test(token) || void_tags.indexOf(tag_name) !== -1;

		if (closing === true) {
			indent = indent.slice(1);
			lines.push(indent + token.trim());

			return;
		}

		lines.push(indent + token.trim());

		if (self_closing === false) {
			if (verbatim_tags.indexOf(tag_name) !== -1) {
				verbatim = tag_name;
				indent = indent + '\t';

				return;
			}

			indent = indent + '\t';
		}
	});

	flush_inline();

	return lines.join('\n');
}

/**
 * Replace the textareas marked with the class .code-editor by CodeMirror
 * instances with HTML parsing and highlighting.
 *
 * The form keeps working unchanged: CodeMirror keeps the original textarea
 * in the DOM and syncs the value on submit.
 */
function init_code_editors() {
	if (typeof window.CodeMirror !== 'function') {
		return;
	}

	window.block_code_editors = [];

	document.querySelectorAll('textarea.code-editor').forEach(function (textarea) {
		var editor = CodeMirror.fromTextArea(textarea, {
			mode: 'htmlmixed',
			lineNumbers: true,
			styleActiveLine: true,
			lineWrapping: true,
			indentUnit: 4,
			indentWithTabs: true,
		});

		var toolbar = document.createElement('div');
		toolbar.className = 'code-editor-toolbar';
		var format_button = document.createElement('button');
		format_button.type = 'button';
		format_button.className = 'btn btn-sm btn-outline-secondary';
		format_button.textContent = textarea.getAttribute('data-format-title') ?? 'Format';
		format_button.addEventListener('click', function () {
			editor.setValue(format_html(editor.getValue()));
		});

		var wrapper = editor.getWrapperElement();
		wrapper.parentNode.insertBefore(toolbar, wrapper);
		toolbar.appendChild(format_button);

		window.block_code_editors.push(editor);
	});
}

/**
 * Upload one or more files through a button with a data-upload-url attribute.
 *
 * The button's data-file-input names the file input to read; the upload
 * posts a multipart body and reloads to show the new list row.
 */
function init_picture_uploads() {
	document.querySelectorAll('button[data-upload-url]').forEach(function (button) {
		var input = document.getElementById(button.getAttribute('data-file-input'));

		if (input === null) {
			return;
		}

		// Enabled once a file is selected.
		input.addEventListener('change', function () {
			button.disabled = (input.files.length === 0);
		});

		button.addEventListener('click', function (event) {
			event.preventDefault();

			var form_data = new FormData();
			for (var i = 0; i < input.files.length; i++) {
				// The field name comes from the input itself: the gallery
				// input is named files[] (server expects an array), the
				// single-image inputs are named file. Deciding on
				// files.length broke single-file gallery uploads.
				form_data.append(input.name, input.files[i]);
			}

			button.disabled = true;

			fetch(button.getAttribute('data-upload-url'), {
				method: 'POST',
				body: form_data,
			}).then(function () {
				window.location.reload();
			});
		});
	});
}

/**
 * Live-refresh the card preview iframe (data-preview-url) with the current
 * form values. Triggered by CodeMirror change events (debounced) and the
 * plain inputs of the form. The iframe is a full-width collapsible panel
 * above the form (data-preview-collapse button toggles the panel).
 */
function init_preview_refresh() {
	var iframe = document.querySelector('iframe[data-preview-url]');
	var form = document.querySelector('form.preview-source');

	init_preview_collapse(iframe);

	if (iframe === null || form === null) {
		return;
	}

	var timeout = null;

	function refresh() {
		window.block_code_editors.forEach(function (editor) {
			editor.save();
		});

		fetch(iframe.getAttribute('data-preview-url'), {
			method: 'POST',
			body: new FormData(form),
		}).then(function (response) {
			var response_ok = (response.status === 200);

			return response.text().then(function (text) {
				if (response_ok) {
					iframe.setAttribute('srcdoc', text);
				}
			});
		});
	}

	function debounced_refresh() {
		window.clearTimeout(timeout);
		timeout = setTimeout(refresh, 500);
	}

	form.querySelectorAll('input[name="title[]"], input[name="anchor"]').forEach(function (input) {
		input.addEventListener('change', debounced_refresh);
	});

	form.querySelectorAll('input[type="checkbox"], select').forEach(function (input) {
		input.addEventListener('change', debounced_refresh);
	});

	window.block_code_editors.forEach(function (editor) {
		editor.on('change', debounced_refresh);
	});

	// Exposed so other flows (e.g. the gallery picture reorder) can re-render
	// the preview after the order has been persisted.
	window.block_preview_refresh = refresh;

	// Autoformat the HTML in every editor before the form is submitted so the
	// stored body is always readable (one tag per line, consistent indent).
	form.addEventListener('submit', function () {
		window.block_code_editors.forEach(function (editor) {
			editor.setValue(format_html(editor.getValue()));
		});
	});

	refresh();
}

/**
 * Resize the preview iframe to the height of the rendered fragment after
 * every load (the iframe is a full-width horizontal panel, so the height
 * grows with the card content).
 */
function init_preview_height() {
	var iframe = document.querySelector('iframe[data-preview-url]');

	if (iframe === null) {
		return;
	}

	iframe.addEventListener('load', function () {
		var inner = iframe.contentDocument ? iframe.contentDocument.body : null;

		if (inner === null || 'contentWindow' in iframe === false) {
			return;
		}

		iframe.style.height = (inner.scrollHeight + 40) + 'px';
	});
}

/**
 * Collapse/expand the preview panel; the button flips its label.
 */
function init_preview_collapse(iframe) {
	var button = document.querySelector('button[data-preview-collapse]');
	var panel = button !== null ? document.querySelector(button.getAttribute('data-preview-collapse')) : null;

	if (button === null || panel === null || iframe === null) {
		return;
	}

	var label_open = button.textContent;

	button.addEventListener('click', function () {
		var collapsed = panel.classList.contains('d-none');

		if (collapsed === true) {
			panel.classList.remove('d-none');
			button.textContent = label_open;
		} else {
			panel.classList.add('d-none');
			button.textContent = label_open.replace('Collapse', 'Expand');
		}
	});
}

/**

/* --------------------------------------------------------------------------
   Calendar helpers: date/time formatting for hover tooltips
   ------------------------------------------------------------------------ */

/**
 * Zero-pad a number to two digits.
 *
 * @param {number} value
 * @return {string}
 */
function calendar_pad(value) {
	return (value < 10 ? '0' : '') + value;
}

/**
 * Date as dd/mm/yyyy.
 *
 * @param {Date} date
 * @return {string}
 */
function calendar_format_date(date) {
	return calendar_pad(date.getDate()) + '/' + calendar_pad(date.getMonth() + 1) + '/' + date.getFullYear();
}

/**
 * Time as hh:mm.
 *
 * @param {Date} date
 * @return {string}
 */
function calendar_format_time(date) {
	return calendar_pad(date.getHours()) + ':' + calendar_pad(date.getMinutes());
}

/**
 * Date and time as "dd/mm/yyyy hh:mm".
 *
 * @param {Date} date
 * @return {string}
 */
function calendar_format_datetime(date) {
	return calendar_format_date(date) + ' ' + calendar_format_time(date);
}

/**
 * Human readable time range for the hover tooltip.
 *
 * Fullcalendar end dates are exclusive: the end day is shifted back one day
 * for the display label. All-day events render as day(s), timed events as
 * "dd/mm/yyyy hh:mm → hh:mm" when the end falls on the same day, otherwise
 * with the full end datetime.
 *
 * @param {Object} event
 * @return {string}
 */
function calendar_build_time_label(event) {
	var start = event.start;
	var end = event.end;

	if (start === null) {
		return '';
	}

	var start_day = calendar_format_date(start);

	if (event.allDay === true) {
		if (end instanceof Date && end.getTime() > start.getTime()) {
			var end_day = calendar_format_date(new Date(end.getTime() - 86400000));

			if (end_day !== start_day) {
				return start_day + ' → ' + end_day;
			}
		}

		return start_day;
	}

	if (end instanceof Date) {
		if (calendar_format_date(end) === start_day) {
			return calendar_format_datetime(start) + ' → ' + calendar_format_time(end);
		}

		return calendar_format_datetime(start) + ' → ' + calendar_format_datetime(end);
	}

	return calendar_format_datetime(start);
}

/**
 * Resolve the i18n labels for the calendar tooltip and error strings.
 *
 * The server renders the translations (po catalogs) into data-i18n-*
 * attributes on the calendar container; JS strings cannot go through the
 * po extractor, so the msgids travel as attributes. The English values are
 * the fallbacks when the attributes are missing.
 *
 * @return {Object}
 */
var calendar_i18n_cache = null;

function calendar_chip_labels() {
	if (calendar_i18n_cache !== null) {
		return calendar_i18n_cache;
	}

	var element = document.getElementById('admin-calendar');

	if (element === null) {
		element = document.getElementById('members-calendar');
	}

	function read(name, fallback) {
		if (element !== null) {
			var value = element.getAttribute('data-i18n-' + name);

			if (value !== null && value !== '') {
				return value;
			}
		}

		return fallback;
	}

	calendar_i18n_cache = {
		category: read('category', 'Category:'),
		location: read('location', 'Location:'),
		hidden: read('hidden', '(hidden: not published)'),
		calendar_missing: read('calendar-missing', 'Fullcalendar script missing'),
		not_loaded: read('calendar-missing-not-loaded', 'not loaded'),
		no_export: read('calendar-missing-no-export', 'loaded but no Calendar export'),
		calendar_failed: read('calendar-failed', 'Calendar failed to render'),
	};

	return calendar_i18n_cache;
}

/**
 * Build the chip content for a fullcalendar event (admin + members).
 *
 * Shared by both calendars so their day-grid chips always look the same:
 * bold title with the from/to time for timed events, an ellipsis guard on
 * the element itself, and a native tooltip showing the full info (time
 * range, category, location, description, hidden state). Returns null for
 * the not-first segments of multi-day events so fullcalendar renders its
 * own continuation chip there.
 *
 * @param {Object} arg The fullcalendar eventContent argument
 * @return {Object|null}
 */
function build_calendar_chip(arg) {
	var title_element = document.createElement('div');
	title_element.setAttribute('class', 'calendar-event-title');
	title_element.textContent = arg.event.title;

	// Inline self-guard: ellipsis directly on the element that carries the
	// text, independent of base.css.
	title_element.style.whiteSpace = 'nowrap';
	title_element.style.overflow = 'hidden';
	title_element.style.textOverflow = 'ellipsis';
	title_element.style.maxWidth = '100%';

	var event = arg.event;
	var time_label = arg.timeText || '';
	var location = event.extendedProps.location || '';
	var description = event.extendedProps.description || '';
	var category = event.extendedProps.category;
	var category_name = category !== null && category !== undefined ? category.name : '';
	var visible_state = event.extendedProps.visible === false ? ' 🚫' : '';

	// Timed events get their from/to time inside the chip.
	if (time_label !== '' && event.allDay !== true) {
		var time_prefix = document.createElement('span');
		time_prefix.setAttribute('class', 'calendar-event-time');
		time_prefix.textContent = time_label + ' ';
		title_element.insertBefore(time_prefix, title_element.firstChild);
	}

	if (visible_state !== '') {
		var hidden_suffix = document.createElement('span');
		hidden_suffix.setAttribute('class', 'text-secondary');
		hidden_suffix.textContent = ' 🚫';
		title_element.appendChild(hidden_suffix);
	}

	// Native tooltip: the title attribute shows the full info on hover
	// without an extra library. "\n" renders as a line break in the major
	// browsers.
	var labels = calendar_chip_labels();
	var hover_lines = [ event.title ];

	var time_hover = calendar_build_time_label(event);
	if (time_hover !== '') {
		hover_lines.push(time_hover);
	}

	if (category_name !== '') {
		hover_lines.push(labels.category + ' ' + category_name);
	}

	if (location !== '') {
		hover_lines.push(labels.location + ' ' + location);
	}

	if (description !== '') {
		hover_lines.push('');
		hover_lines.push(description);
	}

	if (visible_state !== '') {
		hover_lines.push('');
		hover_lines.push(labels.hidden);
	}

	title_element.title = hover_lines.join('\n');

	if (arg.isStart !== true) {
		return null;
	}

	return { domNodes: [ title_element ] };
}

/**
 * Fullcalendar month view on the admin calendar page (#admin-calendar).
 *
 * Events come from the /admin/calendar?action=events JSON endpoint; clicks
 * follow the event URL (the edit screen). Reload keeps the current date.
 */
function init_admin_calendar() {
	var element = document.getElementById('admin-calendar');

	if (element === null) {
		return;
	}

	if (typeof window.FullCalendar === 'undefined' || typeof window.FullCalendar.Calendar !== 'function') {
		var labels = calendar_chip_labels();
		var reason = typeof window.FullCalendar === 'undefined' ? labels.not_loaded : labels.no_export;
		element.innerHTML = '<span class="text-danger">' + labels.calendar_missing + ': ' + reason + '</span>';
		return;
	}

	try {
		var calendar = new FullCalendar.Calendar(element, {
			initialView: 'dayGridMonth',
			height: 'auto',
			firstDay: 1,
			locale: document.documentElement.lang || 'en',
			dayMaxEvents: 2,
			eventMaxStack: 3,
			events: '/admin/calendar?action=events',
			eventTimeFormat: {
				hour: '2-digit',
				minute: '2-digit',
				hour12: false,
			},
			eventClick: function (info) {
				info.jsEvent.preventDefault();

				// Only navigate when the event has a real URL: an undefined
				// url would otherwise try to load a bogus page.
				var url = info.event.url;

				if (url === null || url === undefined || url === '') {
					return;
				}

				window.location = url;
			},
			// Force the category colour on the chip as an inline style. The
			// colour is also applied by Fullcalendar itself from the event's
			// "color" property, but an explicit inline style survives browser
			// extensions that strip injected <style> tags (see the fullcalendar
			// safety net in base.css).
			eventDidMount: function (info) {
				var background = info.backgroundColor || '#9ab03e';

				info.el.style.backgroundColor = background;
				info.el.style.borderColor = background;
				// The chip is an <a>: without this the browser link colour
				// (blue) wins over the feed's chosen label colour.
				info.el.style.color = info.textColor || '#000000';
				info.el.style.textDecoration = 'none';
				// Clip guard on the chip element itself: long titles can never
				// paint past the chip, even when base.css is missing or an
				// extension stripped it.
				info.el.style.overflow = 'hidden';
			},
			headerToolbar: {
				left: 'prev,next today',
				center: 'title',
				right: 'dayGridMonth,timeGridWeek',
			},
			eventContent: function (arg) {
				return build_calendar_chip(arg);
			},
		});

		// v6 requires the explicit render() call: the constructor wires up
		// the object (and the event fetch) but does not paint the grid.
		calendar.render();

		window.admin_calendar = calendar;
	} catch (error) {
		element.innerHTML = '<span class="text-danger">' + calendar_chip_labels().calendar_failed + ': ' +
			String(error.message || error) + '</span>';
	}
}

/**
 * Ask for confirmation before following .delete-confirm links.
 */
function init_delete_confirms() {
	document.querySelectorAll('a.delete-confirm').forEach(function (link) {
		link.addEventListener('click', function (event) {
			var message = link.getAttribute('data-confirm-message');

			if (message !== null && window.confirm(message) === false) {
				event.preventDefault();
			}
		});
	});
}

/**
 * Read-only fullcalendar for the members area (#members-calendar).
 *
 * Same feed shape as the admin calendar but without navigation or edit
 * links: the members view is strictly read-only.
 */
function init_members_calendar() {
	var element = document.getElementById('members-calendar');

	if (element === null) {
		return;
	}

	if (typeof window.FullCalendar === 'undefined' || typeof window.FullCalendar.Calendar !== 'function') {
		element.innerHTML = '<span class="text-danger">' + calendar_chip_labels().calendar_missing + '</span>';
		return;
	}

	try {
		var calendar = new FullCalendar.Calendar(element, {
			initialView: 'dayGridMonth',
			height: 'auto',
			firstDay: 1,
			locale: document.documentElement.lang || 'en',
			dayMaxEvents: 2,
			events: '/members/calendar?action=events',
			eventTimeFormat: {
				hour: '2-digit',
				minute: '2-digit',
				hour12: false,
			},
			headerToolbar: {
				left: 'prev,next today',
				center: 'title',
				right: 'dayGridMonth,timeGridWeek',
			},
			// Same chip styling fix as the admin calendar: force the feed's
			// label colour onto the anchor (else the browser link colour
			// wins) and clip long titles.
			eventDidMount: function (info) {
				info.el.style.backgroundColor = info.backgroundColor || '#9ab03e';
				info.el.style.borderColor = info.backgroundColor || '#9ab03e';
				info.el.style.color = info.textColor || '#000000';
				info.el.style.textDecoration = 'none';
				info.el.style.overflow = 'hidden';
			},
			// Identical chip content as the admin calendar (bold title,
			// time, hover tooltip): the shared build_calendar_chip.
			eventContent: function (arg) {
				return build_calendar_chip(arg);
			},
		});

		// v6 requires the explicit render() call: the constructor wires up
		// the object (and the event fetch) but does not paint the grid.
		calendar.render();

		window.members_calendar = calendar;
	} catch (error) {
		element.innerHTML = '<span class="text-danger">' + calendar_chip_labels().calendar_failed + ': ' +
			String(error.message || error) + '</span>';
	}
}

/**
 * Copy-to-clipboard buttons on readonly URL inputs (.copy-button).
 *
 * The button's data-copy-target names the input holding the value; a
 * successful copy flips the icon to a check mark for a moment. When the
 * Clipboard API is unavailable, the input is selected instead so the user
 * can still copy manually.
 */
function init_copy_buttons() {
	var buttons = document.querySelectorAll('button.copy-button');

	buttons.forEach(function (button) {
		button.addEventListener('click', function () {
			var target = button.getAttribute('data-copy-target');

			if (target === null || target === '') {
				return;
			}

			var input = document.querySelector(target);

			if (input === null) {
				return;
			}

			if (typeof navigator.clipboard === 'undefined' || typeof navigator.clipboard.writeText !== 'function') {
				input.select();

				return;
			}

			navigator.clipboard.writeText(input.value).then(function () {
				show_copy_success(button);
			});
		});
	});
}

/**
 * Briefly swap a copy button's icon to a check mark.
 *
 * @param {HTMLElement} button
 */
function show_copy_success(button) {
	var icon = button.querySelector('.copy-button-icon');
	var check = button.querySelector('.copy-button-check');

	if (icon === null || check === null) {
		return;
	}

	icon.classList.add('d-none');
	check.classList.remove('d-none');

	var timeout = window.setTimeout(function () {
		icon.classList.remove('d-none');
		check.classList.add('d-none');
		window.clearTimeout(timeout);
	}, 1500);
}

/**
 * Collapse/expand toggle buttons (data-collapse = target selector).
 *
 * The button body holds two label spans, .collapse-label-open (shown
 * while the target is visible) and .collapse-label-closed (shown while
 * it is hidden); each carries its own icon and text, so the label flips
 * through the ordinary d-none toggle — no text surgery, safe for
 * translated strings. The section starts expanded, matching the previous
 * always-open layout.
 */
function init_collapse_toggles() {
	document.querySelectorAll('[data-collapse]').forEach(function (button) {
		var target = document.querySelector(button.getAttribute('data-collapse'));

		if (target === null) {
			return;
		}

		button.addEventListener('click', function () {
			var collapsed = target.classList.toggle('d-none');
			var open_label = button.querySelector('.collapse-label-open');
			var closed_label = button.querySelector('.collapse-label-closed');

			if (open_label !== null) {
				open_label.classList.toggle('d-none', collapsed);
			}

			if (closed_label !== null) {
				closed_label.classList.toggle('d-none', collapsed === false);
			}
		});
	});
}

window.addEventListener('DOMContentLoaded', function () {
	init_sortable_tables();
	init_visibility_toggles();
	init_select_explanations();
	init_password_toggles();
	init_code_editors();
	init_picture_uploads();
	init_delete_confirms();
	init_preview_refresh();
	init_preview_height();
	init_collapse_toggles();
	init_admin_calendar();
	init_members_calendar();
	init_copy_buttons();
});
