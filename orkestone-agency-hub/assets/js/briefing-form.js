/**
 * Orkestone Agency Hub — Briefing Form Interactions
 *
 * Handles tab navigation, dynamic field additions (pages, content model items,
 * menu items), field validation, the "Calculate Budget" trigger, and
 * inline budget/JSON preview rendering.
 *
 * @package OrkestoneAgencyHub
 */

(function () {
	'use strict';

	/**
	 * --------------------------------------------------------------------------
	 * 1. Tab Navigation
	 * --------------------------------------------------------------------------
	 */

	const tabNav = document.querySelector('.orke-hub-tabs-nav');
	const tabPanels = document.querySelectorAll('.orke-hub-tab-panel');

	if (tabNav) {
		tabNav.addEventListener('click', function (e) {
			const link = e.target.closest('a');
			if (!link) return;

			e.preventDefault();

			const li = link.parentElement;
			const tabKey = li.getAttribute('data-tab');

			// Deactivate all tabs
			tabNav.querySelectorAll('li').forEach(function (item) {
				item.classList.remove('active');
			});

			tabPanels.forEach(function (panel) {
				panel.classList.remove('active');
			});

			// Activate selected tab
			li.classList.add('active');

			const targetPanel = document.getElementById('tab-' + tabKey);
			if (targetPanel) {
				targetPanel.classList.add('active');
			}
		});
	}

	/**
	 * --------------------------------------------------------------------------
	 * 2. Dynamic Pages — Add / Remove
	 * --------------------------------------------------------------------------
	 */

	const pagesContainer = document.getElementById('orke-pages-container');
	const addPageBtn = document.getElementById('orke-add-page');

	if (addPageBtn && pagesContainer) {
		addPageBtn.addEventListener('click', function () {
			const pageRows = pagesContainer.querySelectorAll('.orke-hub-page-row');
			const newIndex = pageRows.length;

			const template = document.createElement('div');
			template.className = 'orke-hub-page-row orke-hub-card';
			template.setAttribute('data-index', newIndex);
			template.innerHTML =
				'<div class="orke-hub-field">' +
					'<label>Page Title</label>' +
					'<input type="text" name="orke_pages[' + newIndex + '][title]" value="" placeholder="e.g., About" />' +
				'</div>' +
				'<div class="orke-hub-field">' +
					'<label>Slug</label>' +
					'<input type="text" name="orke_pages[' + newIndex + '][slug]" value="" placeholder="e.g., about" />' +
				'</div>' +
				'<div class="orke-hub-field">' +
					'<label>Sections</label>' +
					'<select name="orke_pages[' + newIndex + '][sections][]" multiple class="orke-section-select" style="height:80px;">' +
						'<option value="hero">Hero</option>' +
						'<option value="hero-centered">Hero (Centered)</option>' +
						'<option value="services-grid">Services Grid</option>' +
						'<option value="about">About</option>' +
						'<option value="testimonials">Testimonials</option>' +
						'<option value="pricing">Pricing</option>' +
						'<option value="faq-section">FAQ</option>' +
						'<option value="cta">Call to Action</option>' +
						'<option value="contact-section">Contact</option>' +
						'<option value="features">Features</option>' +
						'<option value="gallery">Gallery</option>' +
						'<option value="team-grid">Team Grid</option>' +
						'<option value="content">Content</option>' +
						'<option value="form">Form</option>' +
					'</select>' +
				'</div>' +
				'<button type="button" class="orke-hub-button orke-hub-button--danger orke-remove-page">Remove Page</button>';

			pagesContainer.appendChild(template);
		});
	}

	// Remove page (delegated)
	if (pagesContainer) {
		pagesContainer.addEventListener('click', function (e) {
			const btn = e.target.closest('.orke-remove-page');
			if (!btn) return;

			const row = btn.closest('.orke-hub-page-row');
			if (row) {
				row.remove();
			}
		});
	}

	/**
	 * --------------------------------------------------------------------------
	 * 3. Dynamic Content Models — Add / Remove
	 * --------------------------------------------------------------------------
	 */

	// Add model item (services, team, pricing, faq, testimonials)
	document.querySelectorAll('.orke-add-model').forEach(function (btn) {
		btn.addEventListener('click', function () {
			const containerId = btn.getAttribute('data-container');
			const fieldName = btn.getAttribute('data-name');
			const container = document.getElementById(containerId);

			if (!container) return;

			const rows = container.querySelectorAll('.orke-hub-model-row');
			const newIndex = rows.length;

			let fieldsHtml = '';
			var label = '';

			if (fieldName === 'orke_services') {
				label = 'Service Title';
				fieldsHtml =
					'<div class="orke-hub-field">' +
						'<label>Service Title</label>' +
						'<input type="text" name="' + fieldName + '[' + newIndex + '][title]" value="" />' +
					'</div>' +
					'<div class="orke-hub-field">' +
						'<label>Description</label>' +
						'<textarea name="' + fieldName + '[' + newIndex + '][description]" rows="2"></textarea>' +
					'</div>';
			} else if (fieldName === 'orke_team') {
				fieldsHtml =
					'<div class="orke-hub-field">' +
						'<label>Name</label>' +
						'<input type="text" name="' + fieldName + '[' + newIndex + '][name]" value="" />' +
					'</div>' +
					'<div class="orke-hub-field">' +
						'<label>Role</label>' +
						'<input type="text" name="' + fieldName + '[' + newIndex + '][role]" value="" />' +
					'</div>' +
					'<div class="orke-hub-field">' +
						'<label>Bio</label>' +
						'<textarea name="' + fieldName + '[' + newIndex + '][description]" rows="2"></textarea>' +
					'</div>';
			} else if (fieldName === 'orke_pricing') {
				fieldsHtml =
					'<div class="orke-hub-field">' +
						'<label>Plan Name</label>' +
						'<input type="text" name="' + fieldName + '[' + newIndex + '][plan]" value="" />' +
					'</div>' +
					'<div class="orke-hub-field">' +
						'<label>Price</label>' +
						'<input type="number" name="' + fieldName + '[' + newIndex + '][price]" step="0.01" value="0" />' +
					'</div>';
			} else if (fieldName === 'orke_faq') {
				fieldsHtml =
					'<div class="orke-hub-field">' +
						'<label>Question</label>' +
						'<input type="text" name="' + fieldName + '[' + newIndex + '][question]" value="" />' +
					'</div>' +
					'<div class="orke-hub-field">' +
						'<label>Answer</label>' +
						'<textarea name="' + fieldName + '[' + newIndex + '][answer]" rows="2"></textarea>' +
					'</div>';
			} else if (fieldName === 'orke_testimonials') {
				fieldsHtml =
					'<div class="orke-hub-field">' +
						'<label>Quote</label>' +
						'<textarea name="' + fieldName + '[' + newIndex + '][quote]" rows="2"></textarea>' +
					'</div>' +
					'<div class="orke-hub-field">' +
						'<label>Author</label>' +
						'<input type="text" name="' + fieldName + '[' + newIndex + '][author]" value="" />' +
					'</div>';
			}

			const template = document.createElement('div');
			template.className = 'orke-hub-model-row orke-hub-card';
			template.setAttribute('data-index', newIndex);
			template.innerHTML = fieldsHtml +
				'<button type="button" class="orke-hub-button orke-hub-button--danger orke-remove-model">Remove</button>';

			container.appendChild(template);
		});
	});

	// Remove model item (delegated)
	document.querySelectorAll('#orke-services-container, #orke-team-container, #orke-pricing-container, #orke-faq-container, #orke-testimonials-container').forEach(function (container) {
		container.addEventListener('click', function (e) {
			const btn = e.target.closest('.orke-remove-model');
			if (!btn) return;

			const row = btn.closest('.orke-hub-model-row');
			if (row) {
				row.remove();
			}
		});
	});

	/**
	 * --------------------------------------------------------------------------
	 * 4. Dynamic Menu Items — Add / Remove
	 * --------------------------------------------------------------------------
	 */

	const menuContainer = document.getElementById('orke-menu-container');
	const addMenuItemBtn = document.getElementById('orke-add-menu-item');

	if (addMenuItemBtn && menuContainer) {
		addMenuItemBtn.addEventListener('click', function () {
			const menuRows = menuContainer.querySelectorAll('.orke-hub-menu-row');
			const newIndex = menuRows.length;

			const template = document.createElement('div');
			template.className = 'orke-hub-menu-row';
			template.setAttribute('data-index', newIndex);
			template.innerHTML =
				'<div class="orke-hub-field" style="display:inline-block;width:calc(50% - 8px);margin-right:8px;">' +
					'<input type="text" name="orke_menu_items[' + newIndex + '][label]" value="" placeholder="Label" />' +
				'</div>' +
				'<div class="orke-hub-field" style="display:inline-block;width:calc(50% - 60px);">' +
					'<input type="text" name="orke_menu_items[' + newIndex + '][url]" value="" placeholder="URL (e.g., /services)" />' +
				'</div>' +
				'<div class="orke-hub-field" style="display:inline-block;width:100%;margin-top:4px;">' +
					'<input type="text" name="orke_menu_items[' + newIndex + '][url_slug]" value="" class="nav-url-slug regular-text" placeholder="p.ej. servicios" pattern="^[a-z0-9]+(?:-[a-z0-9]+)*$"/>' +
				'</div>' +
				'<button type="button" class="orke-hub-button orke-hub-button--danger orke-remove-menu-item" style="vertical-align:top;">&times;</button>';

			menuContainer.appendChild(template);
		});
	}

	// Remove menu item (delegated)
	if (menuContainer) {
		menuContainer.addEventListener('click', function (e) {
			const btn = e.target.closest('.orke-remove-menu-item');
			if (!btn) return;

			const row = btn.closest('.orke-hub-menu-row');
			if (row) {
				row.remove();
			}
		});
	}

	/**
	 * --------------------------------------------------------------------------
	 * 5. Calculate Budget Trigger
	 * --------------------------------------------------------------------------
	 *
	 * When the "Calculate Budget" button is clicked, we change the form action
	 * to use the calculate_budget handler via JavaScript. Alternatively,
	 * we let the native form submit handle it via formaction attribute.
	 *
	 * The button already has formaction set in the template, but we add
	 * client-side validation here as well.
	 */

	const calculateBtn = document.getElementById('orke-calculate-budget');
	const briefingForm = document.getElementById('orke-briefing-form');

	if (calculateBtn && briefingForm) {
		calculateBtn.addEventListener('click', function (e) {
			// Client-side validation before submission
			var errors = [];

			// Check site name
			var siteName = document.getElementById('orke_site_name');
			if (!siteName || siteName.value.trim() === '') {
				errors.push('Site name is required.');
			}

			// Check at least one page
			var pageRows = document.querySelectorAll('.orke-hub-page-row');
			var hasValidPage = false;
			pageRows.forEach(function (row) {
				var titleInput = row.querySelector('input[name*="[title]"]');
				if (titleInput && titleInput.value.trim() !== '') {
					hasValidPage = true;
				}
			});
			if (!hasValidPage) {
				errors.push('At least one page with a title is required.');
			}

			// Check at least one menu item
			var menuRows = document.querySelectorAll('.orke-hub-menu-row');
			var hasValidMenuItem = false;
			menuRows.forEach(function (row) {
				var labelInput = row.querySelector('input[name*="[label]"]');
				if (labelInput && labelInput.value.trim() !== '') {
					hasValidMenuItem = true;
				}
			});
			if (!hasValidMenuItem) {
				errors.push('At least one menu item is required.');
			}

			if (errors.length > 0) {
				e.preventDefault();
				alert('Please fix the following:\n\n- ' + errors.join('\n- '));
				return false;
			}

			// Switch form action to calculate budget
			briefingForm.querySelector('input[name="action"]').value = 'orke_calculate_budget';
		});
	}

	/**
	 * --------------------------------------------------------------------------
	 * 6. Save Configuration handling
	 * --------------------------------------------------------------------------
	 */

	// If there's a "Save Configuration" button, ensure it uses the save action
	var saveBtns = document.querySelectorAll('button[value="save_draft"]');
	saveBtns.forEach(function (btn) {
		btn.addEventListener('click', function () {
			briefingForm.querySelector('input[name="action"]').value = 'orke_save_briefing';
		});
	});

/**
	 * --------------------------------------------------------------------------
	 * 7. url_slug population and validation for nav items
	 * --------------------------------------------------------------------------
	 *
	 * On page load, server-rendered .nav-url-slug inputs are already populated
	 * via PHP. For rows added dynamically by the "Add Menu Item" button, the
	 * template includes an empty .nav-url-slug field.
	 *
	 * On blur or submit, a non-blocking warning is shown when the value does
	 * not match the Wordpress-slug pattern: ^[a-z0-9]+(?:-[a-z0-9]+)*$
	 */

	// Attach blur validation to all .nav-url-slug inputs (existing and future).
	if (briefingForm) {
		briefingForm.addEventListener('blur', function (e) {
			if (!e.target.classList.contains('nav-url-slug')) return;

			var val = e.target.value.trim();
			if (val === '') return; // empty is allowed (external URL fallback)

			var pattern = /^[a-z0-9]+(?:-[a-z0-9]+)*$/;
			if (!pattern.test(val)) {
				alert('Page Slug "' + val + '" no es un slug válido. Usa solo letras minúsculas, números y guiones. Ej: mis-servicios');
				e.target.focus();
			}
		}, true);

		// On form submit, collect url_slug values — native POST includes them
		// automatically because the inputs carry the correct name attribute.
		// This collector is kept as an explicit hook for any future AJAX save path.
		briefingForm.addEventListener('submit', function () {
			var slugInputs = briefingForm.querySelectorAll('.nav-url-slug');
			slugInputs.forEach(function (input) {
				// Ensure blank values are submitted as empty string (not undefined).
				if (!input.value) {
					input.value = '';
				}
			});
		});
	}

})();
