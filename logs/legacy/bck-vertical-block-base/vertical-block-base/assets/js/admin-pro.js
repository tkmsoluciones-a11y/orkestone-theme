/**
 * vbbCommandCenter — Interactive Control Panel for Vertical Block Base Settings.
 *
 * Provides card-based UI, debounced REST saves via orkestone/v1, and iframe
 * live preview refresh. Designed for vanilla WordPress admin — no build step.
 */
(function () {
  'use strict';

  if (typeof window.vbbCommandCenter !== 'undefined') {
    return; // Already initialised.
  }

  var CC = (window.vbbCommandCenter = {
    state: {
      settings: {},
      dirty: false,
      previewUrl: '',
      ajaxUrl: '',
      nonce: '',
    },

    debounceTimer: null,
    debounceDelay: 500,

    el: {
      cards: null,
      iframe: null,
      saveProfileBtn: null,
      resetBtn: null,
      hiddenForm: null,
    },

    /* ── Initialisation ─────────────────────── */

    init: function () {
      if (!document.getElementById('vbb-cc-cards')) {
        return; // Not on the Command Center page.
      }

      CC.el.cards = document.getElementById('vbb-cc-cards');
      CC.el.iframe = document.getElementById('vbb-cc-iframe');
      CC.el.saveProfileBtn = document.getElementById('vbb-cc-save-profile');
      CC.el.resetBtn = document.getElementById('vbb-cc-reset');
      CC.el.hiddenForm = document.getElementById('vbb-cc-hidden-form');

      // Force rest_url() to be absolute to avoid routing issues
      CC.state.restBaseUrl = window.vbbCommandCenterData
        ? window.vbbCommandCenterData.restUrl
        : rest_url('orkestone/v1/');
      CC.state.nonce = window.vbbCommandCenterData
        ? window.vbbCommandCenterData.nonce
        : '';
      CC.state.ajaxUrl = window.location.origin + CC.state.restBaseUrl;
      CC.state.previewUrl = CC.el.iframe ? CC.el.iframe.src : '';

      CC.loadSettings();

      if (CC.el.saveProfileBtn) {
        CC.el.saveProfileBtn.addEventListener('click', CC.saveAsProfile);
      }
      if (CC.el.resetBtn) {
        CC.el.resetBtn.addEventListener('click', CC.resetSettings);
      }
    },

    /* ── API helpers ────────────────────────── */

    loadSettings: function () {
      CC.xhr(
        CC.state.restBaseUrl + 'vertical-settings',
        'GET',
        null,
        function (data) {
          if (data && data.settings) {
            CC.state.settings = data.settings;
            CC.renderCards();
          }
        },
        function () {
          CC.el.cards.innerHTML =
            '<div class="notice notice-error"><p>Failed to load settings. Check your REST API connection.</p></div>';
        }
      );
    },

    saveSettings: function (callback) {
      CC.state.dirty = false;
      CC.xhr(
        CC.state.restBaseUrl + 'vertical-settings',
        'POST',
        { settings: CC.state.settings },
        function (data) {
          if (data && data.settings) {
            CC.state.settings = data.settings;
          }
          if (typeof callback === 'function') {
            callback(data);
          }
          CC.refreshPreview();
        },
        function (xhr) {
          var msg = 'Save failed.';
          try {
            var err = JSON.parse(xhr.responseText);
            msg = err.message || msg;
          } catch (e) {
            // ignore parse errors
          }
          // eslint-disable-next-line no-alert
          alert(msg + '\n\nTip: If you see 404, your WordPress site might be in a subdirectory (e.g., /a11y, /wp, /admin). Check the URL in DevTools → Network tab.');
        }
      );
    },

    xhr: function (url, method, body, onSuccess, onError) {
      var xhr = new XMLHttpRequest();
      xhr.open(method, url, true);
      xhr.setRequestHeader('Accept', 'application/json');
      xhr.setRequestHeader('X-WP-Nonce', CC.state.nonce);
      if (body) {
        xhr.setRequestHeader('Content-Type', 'application/json');
      }
      xhr.onload = function () {
        if (xhr.status >= 200 && xhr.status < 300) {
          try {
            var data = JSON.parse(xhr.responseText);
            if (typeof onSuccess === 'function') {
              onSuccess(data);
            }
          } catch (e) {
            if (typeof onError === 'function') {
              onError(xhr);
            }
          }
        } else {
          if (typeof onError === 'function') {
            onError(xhr);
          }
        }
      };
      xhr.onerror = function () {
        if (typeof onError === 'function') {
          onError(xhr);
        }
      };
      xhr.send(body ? JSON.stringify(body) : null);
    },

    /* ── Debounce ───────────────────────────── */

    debouncedSave: function () {
      CC.state.dirty = true;
      if (CC.debounceTimer) {
        clearTimeout(CC.debounceTimer);
      }
      CC.debounceTimer = setTimeout(function () {
        CC.saveSettings();
      }, CC.debounceDelay);
    },

    /* ── Field change handler ────────────────── */

    onFieldChange: function (path, value, castToBool) {
      var keys = path.split('.');
      var current = CC.state.settings;
      for (var i = 0; i < keys.length - 1; i++) {
        if (!current[keys[i]] || typeof current[keys[i]] !== 'object') {
          current[keys[i]] = {};
        }
        current = current[keys[i]];
      }
      var lastKey = keys[keys.length - 1];
      current[lastKey] = castToBool ? !!value : value;
      CC.debouncedSave();
    },

    /* ── Card rendering ─────────────────────── */

    renderCards: function () {
      var s = CC.state.settings;
      var html = '';

      // Colors Card
      html += CC.buildCard(
        'Colors',
        'Light &amp; Dark palette — edit any swatch.',
        CC.renderColorGroups(s)
      );

      // Typography Card
      html += CC.buildCard(
        'Typography',
        'Heading and body font families.',
        CC.renderTypography(s)
      );

      // Layout Card
      html += CC.buildCard(
        'Layout',
        'Content width, spacing, shadows, and button style.',
        CC.renderLayout(s)
      );

      // Blocks Card
      html += CC.buildCard(
        'Blocks',
        'Toggle sections on/off across the site.',
        CC.renderBlocks(s)
      );

      // Color Mode selector
      html += CC.buildCard(
        'Color Mode',
        'Choose Light, Dark, or Auto (follows device preference).',
        CC.renderColorMode(s)
      );

      CC.el.cards.innerHTML = html;
      CC.bindCardEvents();
    },

    buildCard: function (title, description, body) {
      return (
        '<div class="vbb-cc-card"><h2>' +
        title +
        '</h2><p class="description">' +
        description +
        '</p>' +
        body +
        '</div>'
      );
    },

    renderColorGroups: function (s) {
      var html = '';
      ['light', 'dark'].forEach(function (mode) {
        if (!s.palettes || !s.palettes[mode]) return;
        html +=
          '<h3 style="margin:14px 0 8px;font-size:0.9rem;font-weight:600;text-transform:capitalize">' +
          mode +
          '</h3>';
        html += '<div class="vbb-cc-color-grid">';
        Object.keys(s.palettes[mode]).forEach(function (key) {
          var val = s.palettes[mode][key] || '';
          html +=
            '<div class="vbb-cc-field"><label>' +
            key +
            '</label><input type="color" data-path="palettes.' +
            mode +
            '.' +
            key +
            '" value="' +
            val +
            '"></div>';
        });
        html += '</div>';
      });
      return html;
    },

    renderTypography: function (s) {
      var heading = s.typography ? s.typography.heading || '' : '';
      var body = s.typography ? s.typography.body || '' : '';
      return (
        '<div class="vbb-cc-field"><label>Heading font</label><input type="text" data-path="typography.heading" value="' +
        CC.escAttr(heading) +
        '" placeholder="e.g. Georgia, serif"></div>' +
        '<div class="vbb-cc-field"><label>Body font</label><input type="text" data-path="typography.body" value="' +
        CC.escAttr(body) +
        '" placeholder="e.g. Inter, sans-serif"></div>'
      );
    },

    renderLayout: function (s) {
      var lay = s.layout || {};
      var shadowOptions = ['none', 'soft', 'medium', 'strong'];
      var spacingOptions = ['compact', 'comfortable', 'wide'];
      var buttonOptions = ['pill', 'rounded', 'square', 'outline'];

      return (
        '<div class="vbb-cc-field"><label>Content width</label><input type="text" data-path="layout.contentWidth" value="' +
        CC.escAttr(lay.contentWidth || '') +
        '" placeholder="1180px"></div>' +
        '<div class="vbb-cc-field"><label>Wide width</label><input type="text" data-path="layout.wideWidth" value="' +
        CC.escAttr(lay.wideWidth || '') +
        '" placeholder="1440px"></div>' +
        '<div class="vbb-cc-field"><label>Border radius</label><input type="text" data-path="layout.radius" value="' +
        CC.escAttr(lay.radius || '') +
        '" placeholder="24px"></div>' +
        '<div class="vbb-cc-field"><label>Shadow</label><select data-path="layout.shadow">' +
        CC.buildOptions(shadowOptions, lay.shadow || 'soft') +
        '</select></div>' +
        '<div class="vbb-cc-field"><label>Spacing</label><select data-path="layout.spacingScale">' +
        CC.buildOptions(spacingOptions, lay.spacingScale || 'comfortable') +
        '</select></div>' +
        '<div class="vbb-cc-field"><label>Button style</label><select data-path="buttons.style">' +
        CC.buildOptions(buttonOptions, (s.buttons && s.buttons.style) || 'pill') +
        '</select></div>' +
        '<div class="vbb-cc-field" style="margin-top:12px"><label class="vbb-cc-toggle"><input type="checkbox" data-path="buttons.uppercase" data-boolean="1"' +
        (s.buttons && s.buttons.uppercase ? ' checked' : '') +
        '><span class="vbb-cc-toggle-track"></span><span class="vbb-cc-toggle-label">Uppercase buttons</span></label></div>'
      );
    },

    renderBlocks: function (s) {
      if (!s.blocks) return '<p>No block data available.</p>';
      var html = '<div class="vbb-cc-check-grid">';
      Object.keys(s.blocks).forEach(function (key) {
        var enabled = !!s.blocks[key];
        html +=
          '<label class="vbb-cc-toggle"><input type="checkbox" data-path="blocks.' +
          key +
          '" data-boolean="1"' +
          (enabled ? ' checked' : '') +
          '><span class="vbb-cc-toggle-track"></span><span class="vbb-cc-toggle-label">' +
          key +
          '</span></label>';
      });
      html += '</div>';
      return html;
    },

    renderColorMode: function (s) {
      var modes = [
        { value: 'light', label: 'Light' },
        { value: 'dark', label: 'Dark' },
        { value: 'auto', label: 'Auto' },
      ];
      return (
        '<div class="vbb-cc-field"><label>Color mode</label><select data-path="colorMode">' +
        CC.buildOptions(modes, s.colorMode || 'light') +
        '</select></div>'
      );
    },

    /* ── Helpers ─────────────────────────────── */

    buildOptions: function (items, selected) {
      var html = '';
      for (var i = 0; i < items.length; i++) {
        var item = items[i];
        var value = typeof item === 'object' ? item.value : item;
        var label = typeof item === 'object' ? item.label : item;
        html +=
          '<option value="' +
          value +
          '"' +
          (value === selected ? ' selected' : '') +
          '>' +
          label +
          '</option>';
      }
      return html;
    },

    escAttr: function (str) {
      if (typeof str !== 'string') return '';
      return str
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
    },

    /* ── Event binding ───────────────────────── */

    bindCardEvents: function () {
      var cards = document.querySelectorAll('.vbb-cc-card');

      cards.forEach(function (card) {
        // Text / select changes
        var inputs = card.querySelectorAll(
          'input[type="text"], select, input[type="color"]'
        );
        inputs.forEach(function (input) {
          input.addEventListener('change', CC._handleChange);
          // colour inputs also update live on input for responsiveness
          if (input.type === 'color') {
            input.addEventListener('input', CC._handleChange);
          }
        });

        // Checkbox toggles
        var checks = card.querySelectorAll(
          'input[type="checkbox"][data-path]'
        );
        checks.forEach(function (cb) {
          cb.addEventListener('change', CC._handleChange);
        });
      });
    },

    _handleChange: function (e) {
      var input = e.currentTarget;
      var path = input.getAttribute('data-path');
      if (!path) return;

      var value;
      var isBool = input.getAttribute('data-boolean') === '1';

      if (input.type === 'checkbox') {
        value = input.checked;
      } else if (input.type === 'color') {
        value = input.value;
        input.title = input.value; // Original behaviour preserved.
      } else {
        value = input.value;
      }

      CC.onFieldChange(path, value, isBool);
    },

    /* ── Preview ─────────────────────────────── */

    refreshPreview: function () {
      if (!CC.el.iframe) return;
      var ts = new Date().getTime();
      var separator = CC.state.previewUrl.indexOf('?') > -1 ? '&' : '?';
      CC.el.iframe.src =
        CC.state.previewUrl.split('?')[0] +
        separator +
        'vbb_preview=' +
        ts +
        '&vbb_no_admin=1';
    },

    /* ── Toolbar actions ─────────────────────── */

    saveAsProfile: function (e) {
      if (e) e.preventDefault();
      CC.saveSettings(function () {
        // Submit the hidden form with a save_profile action.
        var form = CC.el.hiddenForm;
        if (!form) return;
        var actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'vbb_pro_action';
        actionInput.value = 'save_profile';
        form.appendChild(actionInput);
        form.submit();
      });
    },

    resetSettings: function (e) {
      if (e) e.preventDefault();
      if (!confirm('Reset all settings to vertical defaults? This cannot be undone.')) {
        return;
      }
      CC.xhr(
        CC.state.ajaxUrl + 'vertical-settings',
        'POST',
        { settings: {} },
        function (data) {
          if (data && data.settings) {
            CC.state.settings = data.settings;
            CC.renderCards();
            CC.refreshPreview();
          }
        },
        function () {
          // Fallback: submit the hidden reset form via admin-post.
          var form = CC.el.hiddenForm;
          if (!form) return;
          var actionInput = document.createElement('input');
          actionInput.type = 'hidden';
          actionInput.name = 'vbb_pro_action';
          actionInput.value = 'reset';
          form.appendChild(actionInput);
          form.submit();
        }
      );
    },
  });

  /* ── Boot ─────────────────────────────────── */
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', CC.init);
  } else {
    CC.init();
  }
})();
