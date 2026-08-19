/**
 * vbbCommandCenter — Interactive Control Panel for OrkestOne Theme Settings.
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
      undoRedoStack: [],
      redoStack: [],
      comparisonMode: 'after',
      previewUrl: '',
      ajaxUrl: '',
      nonce: '',
      currentPageId: null,
      availablePages: [],
      menuItems: [],
      presets: [],
      _darkPreviewEnabled: false,
    },

    debounceTimer: null,
    debounceDelay: 500,

    supportsPostMessage: true,
    previewOrigin: '*',
    _needsRegeneration: true, // Start true so first save always regenerates

    el: {
      cards: null,
      iframe: null,
      saveProfileBtn: null,
      exportBtn: null,
      resetBtn: null,
      regenerateBtn: null,
      hiddenForm: null,
      pageSelector: null,
      statusBar: null,
      toastContainer: null,
      previewViewport: null,
      previewOverlay: null,
      presetBtns: null,
      refreshBtn: null,
      zoomBtn: null,
      darkPreviewBtn: null,
      presetSelect: null,
      presetApplyBtn: null,
    },

    /* ── Initialisation ─────────────────────── */

    init: function () {
      console.log('VBB Command Center: Initialising...');
      
      // 1. Immediate element check
      CC.el.cards = document.getElementById('vbb-cc-cards');
      CC.el.pageSelector = document.getElementById('vbb-page-selector');
      
      if (!CC.el.cards || !CC.el.pageSelector) {
        console.error('VBB Command Center: Required DOM elements not found.');
        return;
      }

      // 2. Show skeletons for cards while data loads
      CC._showSkeletons();
      
      console.log('VBB Command Center: UI cleaned, starting data load.');

      // 3. Assign remaining elements (simple null assignments)
      CC.el.iframe = document.getElementById('vbb-cc-iframe');
      CC.el.saveProfileBtn = document.getElementById('vbb-cc-save-profile');
      CC.el.exportBtn = document.getElementById('vbb-cc-export');
      CC.el.resetBtn = document.getElementById('vbb-cc-reset');
      CC.el.regenerateBtn = document.getElementById('vbb-cc-regenerate');
      CC.el.hiddenForm = document.getElementById('vbb-cc-hidden-form');
      CC.el.statusBar = document.getElementById('vbb-cc-status-bar');
      CC.el.toastContainer = document.getElementById('vbb-cc-toast-container');
      CC.el.previewViewport = document.getElementById('vbb-cc-preview-viewport');
      CC.el.previewOverlay = document.getElementById('vbb-cc-preview-overlay');
      CC.el.presetBtns = document.querySelectorAll('.vbb-cc-preset-btn');
      CC.el.refreshBtn = document.getElementById('vbb-cc-preview-refresh');
      CC.el.zoomBtn = document.getElementById('vbb-cc-zoom-btn');
      CC.el.darkPreviewBtn = document.getElementById('vbb-cc-dark-preview-btn');
      CC.el.presetSelect = document.getElementById('vbb-cc-preset-select');
      CC.el.presetApplyBtn = document.getElementById('vbb-cc-preset-apply');

      // 4. Data & API setup
      CC.state.ajaxUrl = (window.vbbCommandCenterData && window.vbbCommandCenterData.restUrl) || '/wp-json/orkestone/v1/';
      CC.state.nonce = (window.vbbCommandCenterData && window.vbbCommandCenterData.nonce) || '';
      CC.state.presets = (window.vbbCommandCenterData && window.vbbCommandCenterData.presets) || {};
      CC.state.previewUrl = CC.el.iframe && CC.el.iframe.src
        ? CC.el.iframe.src
        : (window.vbbCommandCenterData
            ? window.vbbCommandCenterData.previewUrl
            : window.location.origin + '/');
      CC.previewOrigin = window.vbbCommandCenterData
        ? window.vbbCommandCenterData.previewOrigin || '*'
        : '*';
      CC.supportsPostMessage = true;

      // 5. Trigger data loads (async — safe no-op if undefined)
      CC.loadPages();
      CC.loadSettings();

      // Fetch block registry for generic rendering.
      CC.registry = {};
      CC.fetchRegistry();

      // 6. Event listeners (all guarded by existence check)
      if (CC.el.saveProfileBtn) CC.el.saveProfileBtn.addEventListener('click', CC.saveAsProfile);
      if (CC.el.exportBtn) CC.el.exportBtn.addEventListener('click', CC.exportSite);
      if (CC.el.resetBtn) CC.el.resetBtn.addEventListener('click', CC.resetSettings);
      if (CC.el.regenerateBtn) CC.el.regenerateBtn.addEventListener('click', CC.regeneratePages);
      if (CC.el.presetBtns && CC.el.presetBtns.length) {
        for (var pi = 0; pi < CC.el.presetBtns.length; pi++) {
          CC.el.presetBtns[pi].addEventListener('click', CC._onPresetChange);
        }
      }
      if (CC.el.refreshBtn) {
        CC.el.refreshBtn.addEventListener('click', function (e) {
          e.preventDefault();
          CC.refreshPreview(true);
        });
      }
      if (CC.el.zoomBtn) {
        CC.el.zoomBtn.addEventListener('click', function (e) {
          e.preventDefault();
          CC.toggleZoom();
        });
      }
      if (CC.el.darkPreviewBtn) {
        CC.el.darkPreviewBtn.addEventListener('click', function (e) {
          e.preventDefault();
          CC.toggleDarkPreview();
        });
      }
      // Preview URL buttons — open in new tab / copy
      var openBtn = document.getElementById('vbb-cc-preview-open');
      var copyBtn = document.getElementById('vbb-cc-preview-copy');
      if (openBtn) {
        openBtn.addEventListener('click', function (e) {
          e.preventDefault();
          var url = this.getAttribute('data-url');
          if (url) window.open(url, '_blank');
        });
      }
      if (copyBtn) {
        copyBtn.addEventListener('click', function (e) {
          e.preventDefault();
          var url = this.getAttribute('data-url');
          if (!url) return;
          if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url).then(function () {
              CC.showToast('Enlace copiado al portapapeles', 'success');
            }).catch(function () {
              CC._fallbackCopy(url);
            });
          } else {
            CC._fallbackCopy(url);
          }
        });
      }
      if (CC.el.iframe) {
        CC.el.iframe.addEventListener('load', function () {
          CC._hidePreviewOverlay();
          if (CC.state._darkPreviewEnabled) {
            var darkVars = CC.buildCssVars('dark');
            if (darkVars) {
              CC.postMessage({ type: 'vbb:css-vars', styleTag: darkVars });
            }
            CC.postMessage({ type: 'vbb:dark-preview', enabled: true });
          }
        });
        CC.el.iframe.addEventListener('error', function () {
          CC._showPreviewOverlay('error');
        });

        // Lazy load iframe when preview column enters viewport
        CC._setupLazyIframe();
      }

      // 6b. Media library — delegated for dynamically added content.
      document.addEventListener('click', function (e) {
        var mediaBtn = e.target.closest('.vbb-cc-media-btn');
        if (mediaBtn) {
          e.preventDefault();
          var targetPath = mediaBtn.getAttribute('data-target');
          var field = mediaBtn.closest('.vbb-cc-media-field');
          if (!field) return;
          var preview = field.querySelector('.vbb-cc-media-preview');
          var hiddenInput = field.querySelector('input[type="hidden"]');
          var removeBtn = field.querySelector('.vbb-cc-media-remove-btn');

          var frame = wp.media({
            title: 'Seleccionar Imagen',
            library: { type: 'image' },
            button: { text: 'Usar esta imagen' },
            multiple: false,
          });

          frame.on('select', function () {
            var attachment = frame.state().get('selection').first().toJSON();
            var imgUrl = attachment.url;

            if (hiddenInput) {
              hiddenInput.value = imgUrl;
              hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
            }
            if (preview) {
              preview.innerHTML = '<img src="' + CC.escAttr(imgUrl) + '" class="vbb-cc-media-thumb" style="max-width:150px;max-height:100px;object-fit:cover;border-radius:4px;" />';
            }
            if (removeBtn) {
              removeBtn.style.display = '';
            }
          });

          frame.open();
        }

        var removeBtn = e.target.closest('.vbb-cc-media-remove-btn');
        if (removeBtn) {
          e.preventDefault();
          var targetPath = removeBtn.getAttribute('data-target');
          var field = removeBtn.closest('.vbb-cc-media-field');
          if (!field) return;
          var preview = field.querySelector('.vbb-cc-media-preview');
          var hiddenInput = field.querySelector('input[type="hidden"]');

          if (hiddenInput) {
            hiddenInput.value = '';
            hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
          }
          if (preview) {
            preview.innerHTML = '';
          }
          removeBtn.style.display = 'none';
        }
      });

      // Add repeatable item (delegated).
      document.addEventListener('click', function (e) {
        var addBtn = e.target.closest('.vbb-cc-add-item');
        if (addBtn) {
          e.preventDefault();
          var blockKey = addBtn.getAttribute('data-block-key');
          var prefix = addBtn.getAttribute('data-prefix');
          var fields = JSON.parse(addBtn.getAttribute('data-fields') || '[]');
          var container = addBtn.closest('.vbb-cc-repeatable');

          // Create default item from fields definition.
          var newItem = {};
          for (var fi = 0; fi < fields.length; fi++) {
            newItem[fields[fi].key] = fields[fi].default !== undefined ? fields[fi].default : '';
          }

          // Update state.
          CC.state.settings.blocks[blockKey] = CC.state.settings.blocks[blockKey] || { items: [] };
          CC.state.settings.blocks[blockKey].items = CC.state.settings.blocks[blockKey].items || [];
          CC.state.settings.blocks[blockKey].items.push(newItem);

          // Re-render the repeatable section.
          var reRendered = CC._renderRepeatableFromRegistry(blockKey, CC.state.settings.blocks[blockKey].items, fields, 'blocks.' + blockKey);
          var tempDiv = document.createElement('div');
          tempDiv.innerHTML = reRendered;
          var newList = tempDiv.querySelector('.vbb-cc-repeatable');
          if (newList && container) {
            container.innerHTML = newList.innerHTML;
          }
        }
      });

      // Remove repeatable item (delegated).
      document.addEventListener('click', function (e) {
        var removeBtn = e.target.closest('.vbb-cc-remove-item');
        if (removeBtn) {
          e.preventDefault();
          var index = parseInt(removeBtn.getAttribute('data-index'), 10);
          var prefix = removeBtn.getAttribute('data-prefix');
          var parts = prefix.split('.');
          var blockKey = parts[2]; // blocks.{key}.items

          if (CC.state.settings.blocks[blockKey] && CC.state.settings.blocks[blockKey].items) {
            CC.state.settings.blocks[blockKey].items.splice(index, 1);
            var container = removeBtn.closest('.vbb-cc-repeatable');
            var itemFields = JSON.parse(container.querySelector('.vbb-cc-add-item').getAttribute('data-fields') || '[]');
            var reRendered = CC._renderRepeatableFromRegistry(blockKey, CC.state.settings.blocks[blockKey].items, itemFields, 'blocks.' + blockKey);
            var tempDiv = document.createElement('div');
            tempDiv.innerHTML = reRendered;
            var newList = tempDiv.querySelector('.vbb-cc-repeatable');
            if (newList && container) {
              container.innerHTML = newList.innerHTML;
            }
          }
        }
      });

      // 6c. Listen for messages from preview iframe (section clicks, card select, etc.)
      window.addEventListener('message', function (event) {
        var data = event.data;
        if (!data || typeof data !== 'object' || !data.type) return;
        if (data.type === 'vbb:section-clicked' && data.sectionKey) {
          CC._highlightSectionCard(data.sectionKey);
        } else if (data.type === 'vbb:card-select' && data.blockKey) {
          CC._selectBlockCard(data.blockKey, data.field || '');
        }
      });

      // Keyboard shortcuts
      CC._bindKeyboardShortcuts();

      // Initialize undo/redo buttons state
      CC._initUndoRedoButtons();

      // Initialize comparison mode
      CC._initComparisonMode();

      // 7. Dark mode toggle
      CC.initDarkMode();
    },

    /* ── API helpers ────────────────────────── */

    exportSite: function (e) {
      if (e) e.preventDefault();
      CC.showStatus('saving', 'Preparing export\u2026');
      CC.xhr(
        CC.state.ajaxUrl + 'export',
        'GET',
        null,
        function (data) {
          CC.showStatus('saved', 'Export ready');
          var blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
          var url = URL.createObjectURL(blob);
          var a = document.createElement('a');
          a.href = url;
          a.download = 'orkestone-export-' + new Date().toISOString().replace(/[:.]/g, '').slice(0, 15) + '.json';
          document.body.appendChild(a);
          a.click();
          document.body.removeChild(a);
          URL.revokeObjectURL(url);
          CC.showToast('Export downloaded successfully!', 'success');
        },
        function () {
          CC.showStatus('error', 'Export failed');
          CC.showToast('Export failed. Check server logs.', 'error');
        }
      );
    },

    regeneratePages: function () {
      CC.showConfirmToast(
        'This will regenerate all pages to apply new content structures. Continue?',
        function () {
          CC.showStatus('saving', 'Regenerating pages\u2026');
          CC.xhr(
            CC.state.ajaxUrl + 'regenerate-pages',
            'POST',
            null,
            function (data) {
              CC.showStatus('saved', 'Pages regenerated');
              CC.showToast(data.message || 'Pages regenerated successfully!', 'success');
              CC.refreshPreview();
            },
            function () {
              CC.showStatus('error', 'Page regeneration failed');
              CC.showToast('Error regenerating pages.', 'error');
            }
          );
        }
      );
    },

    loadPages: function () {
      CC.xhr(
        CC.state.ajaxUrl + 'pages',
        'GET',
        null,
        function (data) {
          if (data && data.pages) {
            CC.state.availablePages = data.pages;
            CC.renderPageSelector();
            // If a page is already selected, re-render to pick up sections now that pages are loaded
            if (CC.state.currentPageId) {
              CC.renderCards();
            }
            // Load menu items
            CC.loadMenu();
          } else {
            CC._renderPageError('No se pudieron cargar las páginas.');
          }
        },
        function (xhr) {
          var msg = 'Error de conexión con la API de páginas.';
          try {
            var err = JSON.parse(xhr.responseText);
            msg = err.message || msg;
            if (err.code) msg += ' (' + err.code + ')';
          } catch (e) {
            msg += ' (HTTP ' + xhr.status + ')';
          }
          CC._renderPageError(msg);
        }
      );
    },

    loadMenu: function () {
      CC.xhr(
        CC.state.ajaxUrl + 'menu',
        'GET',
        null,
        function (data) {
          if (data && data.menuItems) {
            CC.state.menuItems = data.menuItems;
            // If the menu card is already rendered (race condition guard),
            // re-render just the menu card with loaded data.
            var menuCard = document.querySelector('#vbb-cc-cards .vbb-cc-card h2');
            if (menuCard && menuCard.textContent === 'Menu Editor') {
              CC._reRenderMenu();
            }
          }
        }
      );
    },

    /**
     * Fetch block registry from REST endpoint.
     */
    fetchRegistry: function () {
      var xhr = new XMLHttpRequest();
      xhr.open('GET', CC.state.ajaxUrl + 'blocks');
      xhr.setRequestHeader('X-WP-Nonce', CC.state.nonce);
      xhr.onload = function () {
        if (xhr.status === 200) {
          try {
            var data = JSON.parse(xhr.responseText);
            if (data && data.blocks) {
              CC.registry = data.blocks;
            }
          } catch (e) {}
        }
      };
      xhr.send();
    },

    loadSettings: function (pageId) {
      if (pageId) {
        CC.state.currentPageId = pageId;
      } else {
        CC.state.currentPageId = null;
      }

      // Show skeleton cards while loading
      CC._showSkeletons();

      // ALWAYS load global settings first
      var globalEndpoint = CC.state.ajaxUrl + 'vertical-settings';
      CC.xhr(
        globalEndpoint,
        'GET',
        null,
        function (globalData) {
          var globalSettings = (globalData && globalData.settings) ? globalData.settings : {};

          // If page-specific, also load page overrides and merge
          if (CC.state.currentPageId) {
            var pageEndpoint = CC.state.ajaxUrl + 'vertical-settings/' + CC.state.currentPageId;
            CC.xhr(
              pageEndpoint,
              'GET',
              null,
              function (pageData) {
                var pageSettings = (pageData && pageData.settings) ? pageData.settings : {};
                // Merge: global as base, page overrides only for blocks.*.enabled and sections
                CC.state.settings = CC._mergePageSettings(globalSettings, pageSettings);
                console.log('[VBB Load] PAGE-SPECIFIC hero data:', JSON.stringify(CC.state.settings.blocks && CC.state.settings.blocks.hero, null, 2));
                CC.renderCards();
              },
              function () {
                // Fallback to global only
                CC.state.settings = globalSettings;
                console.log('[VBB Load] FALLBACK (page fail) hero data:', JSON.stringify(CC.state.settings.blocks && CC.state.settings.blocks.hero, null, 2));
                CC.renderCards();
              }
            );
          } else {
            // Global only
            CC.state.settings = globalSettings;
            console.log('[VBB Load] GLOBAL-ONLY hero data:', JSON.stringify(CC.state.settings.blocks && CC.state.settings.blocks.hero, null, 2));
            CC.renderCards();
          }
        },
        function () {
          CC.el.cards.innerHTML =
            '<div class="notice notice-error"><p>Failed to load settings. Check your REST API connection.</p></div>';
        }
      );
    },

    _mergePageSettings: function (globalSettings, pageSettings) {
      // Deep clone global
      var merged = JSON.parse(JSON.stringify(globalSettings));

      // Override only enabled toggles and sections from page settings
      if (pageSettings.blocks) {
        if (!merged.blocks) merged.blocks = {};
        Object.keys(pageSettings.blocks).forEach(function (key) {
          var pageBlock = pageSettings.blocks[key];
          if (pageBlock && typeof pageBlock === 'object' && 'enabled' in pageBlock) {
            if (!merged.blocks[key]) merged.blocks[key] = {};
            merged.blocks[key].enabled = !!pageBlock.enabled;
          }
        });
      }
      if (pageSettings.sections) {
        merged.sections = pageSettings.sections;
      }

      return merged;
    },

    _renderPageError: function (msg) {
      if (!CC.el.pageSelector) return;
      CC.el.pageSelector.innerHTML = '<div class="notice notice-error" style="margin:0;padding:8px;"><p>' + msg + '</p></div>';
    },

    _validateColor: function (val) {
      return (val && typeof val === 'string' && val.startsWith('#')) ? val : '#FFFFFF';
    },

    _showSkeletons: function () {
      if (!CC.el.cards) return;
      var skeletonTypes = ['short', 'medium', 'tall', 'short', 'medium', 'short', 'tall', 'medium', 'short'];
      var html = '';
      for (var i = 0; i < skeletonTypes.length; i++) {
        html += '<div class="vbb-cc-skeleton vbb-cc-skeleton--' + skeletonTypes[i] + '"></div>';
      }
      CC.el.cards.innerHTML = html;
    },

    saveSettings: function (callback) {
      CC.state.dirty = false;
      CC.showStatus('saving');

      // When a specific page is selected, split settings:
      // - Block content (title, subtitle, colors, style, effect, etc.) → GLOBAL endpoint
      // - Section toggles (blocks.*.enabled) → PAGE-SPECIFIC endpoint
      var isPageSpecific = !!CC.state.currentPageId;
      var settingsToSave = CC.state.settings;
      var globalSettings = settingsToSave;
      var pageSettings = {};

      if (isPageSpecific && settingsToSave.blocks) {
        // Extract only the 'enabled' toggles for page-specific save
        pageSettings.blocks = {};
        Object.keys(settingsToSave.blocks).forEach(function (key) {
          var block = settingsToSave.blocks[key];
          if (block && typeof block === 'object' && 'enabled' in block) {
            pageSettings.blocks[key] = { enabled: !!block.enabled };
          }
        });
        // Also include sections if present (legacy)
        if (settingsToSave.sections) {
          pageSettings.sections = settingsToSave.sections;
        }
      }

      // LOG: hero data being sent to REST
      console.log('[VBB Save] SENDING hero data to GLOBAL:', JSON.stringify(globalSettings.blocks && globalSettings.blocks.hero, null, 2));
      console.log('[VBB Save] Current pageId:', CC.state.currentPageId, '| isPageSpecific:', isPageSpecific, '| pageSettings:', JSON.stringify(pageSettings));

      // Save global settings (always)
      var globalEndpoint = CC.state.ajaxUrl + 'vertical-settings';
      CC.xhr(
        globalEndpoint,
        'POST',
        { settings: globalSettings },
        function (data) {
          // LOG: hero data returned from REST
          console.log('[VBB Save] REST RESPONSE hero data:', JSON.stringify(data && data.settings && data.settings.blocks && data.settings.blocks.hero, null, 2));
          if (data && data.settings) {
            CC.state.settings = data.settings;
          }

          // If page-specific, also save page overrides (only enabled toggles)
          if (isPageSpecific && Object.keys(pageSettings).length > 0) {
            var pageEndpoint = CC.state.ajaxUrl + 'vertical-settings/' + CC.state.currentPageId;
            CC.xhr(
              pageEndpoint,
              'POST',
              { settings: pageSettings },
              function () {
                CC._finishSave(callback);
              },
              function (xhr) {
                var msg = 'Page-specific save failed.';
                try { var err = JSON.parse(xhr.responseText); msg = err.message || msg; } catch (e) { msg += ' (HTTP ' + xhr.status + ')'; }
                CC.showStatus('error', msg);
                CC.showToast(msg, 'error');
                CC._finishSave(callback);
              }
            );
          } else {
            CC._finishSave(callback);
          }
        },
        function (xhr) {
          var msg = 'Save failed.';
          try { var err = JSON.parse(xhr.responseText); msg = err.message || msg; } catch (e) { msg += ' (HTTP ' + xhr.status + ')'; }
          CC.showStatus('error', msg);
          CC.showToast(msg, 'error');
          if (typeof callback === 'function') callback(null);
        }
      );
    },

    _finishSave: function (callback) {
      CC.showStatus('saved');
      CC.showToast('Settings saved successfully!', 'success');
      CC._flashChangedField();
      if (!CC._isContentChange(CC._lastChangedPath)) {
        if (CC.supportsPostMessage && CC.el.iframe && CC.el.iframe.contentWindow) {
          var cssVars = CC.buildCssVars();
          if (cssVars) {
            CC.postMessage({ type: 'vbb:css-vars', styleTag: cssVars });
          }
        } else {
          CC.refreshPreview();
        }
      }
      if (typeof callback === 'function') {
        callback({ success: true });
      }
      // Reset unsaved visual indicator
      CC.state.dirty = false;
      if (CC._needsRegeneration) {
        CC.regenerateAndRefresh();
      } else {
        CC.showStatus('idle');
      }
      CC._needsRegeneration = false; // Reset flag
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

    debounceDelay: 500, // Base delay

    // Smart debounce: batches rapid changes, updates preview immediately for visual fields
    debouncedSave: function (options) {
      options = options || {};
      var isVisual = options.visual === true;
      var isImmediate = options.immediate === true;

      CC.state.dirty = true;

      if (CC.debounceTimer) {
        clearTimeout(CC.debounceTimer);
      }

      // For visual changes (colors, palette, style, layout), update preview immediately
      // via postMessage but ALSO save to server for persistence (no early return).
      if (isVisual) {
        var cssVars = CC.buildCssVars();
        if (cssVars && CC.supportsPostMessage && CC.el.iframe && CC.el.iframe.contentWindow) {
          CC.postMessage({ type: 'vbb:css-vars', styleTag: cssVars });
        }
        // Do NOT return — fall through to debounced save so palette/color/layout
        // changes persist across preview reloads.
      }

      if (isImmediate) {
        CC.saveSettings();
        return;
      }

      // Adaptive debounce: shorter delay for rapid changes, longer for sporadic ones
      var delay = CC.debounceDelay;
      if (CC._changeCount > 3) {
        delay = Math.min(delay * 2, 2000); // Increase delay if many rapid changes
      }

      CC.debounceTimer = setTimeout(function () {
        CC._changeCount = 0;
        CC.saveSettings();
      }, delay);

      CC._changeCount = (CC._changeCount || 0) + 1;
    },

    /* ── Status Bar ───────────────────────────── */

    showStatus: function (state, message) {
      var bar = CC.el.statusBar;
      if (!bar) return;

      // Remove all state classes
      bar.className = 'vbb-cc-status-bar';

      if (state === 'idle') {
        bar.classList.add('vbb-cc-status-bar--idle');
        bar.style.display = 'none';
        return;
      }

      bar.style.display = 'flex';
      var iconEl = bar.querySelector('.vbb-cc-status-icon');
      var textEl = bar.querySelector('.vbb-cc-status-text');

      if (state === 'saving') {
        bar.classList.add('vbb-cc-status-bar--saving');
        iconEl.innerHTML = '<span class="vbb-cc-status-spinner"></span>';
        textEl.textContent = message || 'Saving\u2026';
      } else if (state === 'saved') {
        bar.classList.add('vbb-cc-status-bar--saved');
        iconEl.innerHTML = '<span class="vbb-cc-status-check">\u2713</span>';
        textEl.textContent = message || 'Saved';
        // Auto-dismiss after 2s
        setTimeout(function () {
          CC.showStatus('idle');
        }, 2000);
      } else if (state === 'error') {
        bar.classList.add('vbb-cc-status-bar--error');
        iconEl.innerHTML = '\u26A0';
        textEl.textContent = message || 'Save failed';
        // Add retry button if not already present
        var retryBtn = bar.querySelector('.vbb-cc-status-retry');
        if (!retryBtn) {
          retryBtn = document.createElement('button');
          retryBtn.className = 'vbb-cc-status-retry';
          retryBtn.textContent = 'Retry';
          retryBtn.addEventListener('click', function () {
            CC.saveSettings();
          });
          bar.appendChild(retryBtn);
        }
      } else if (state === 'unsaved') {
        bar.classList.add('vbb-cc-status-bar--unsaved');
        bar.setAttribute('title', 'Unsaved changes');
        iconEl.innerHTML = '<span class="vbb-cc-status-unsaved-icon">🡱</span>';
        textEl.textContent = 'Unsaved changes';
      }
    },

    /* ── Toast System ──────────────────────────── */

    showToast: function (message, type, duration) {
      if (!CC.el.toastContainer) return;
      type = type || 'info';

      var toast = document.createElement('div');
      toast.className = 'vbb-cc-toast vbb-cc-toast--' + type;

      var iconMap = {
        success: '\u2713',
        error: '\u26A0',
        info: '\u2139',
        confirm: '\u2757',
      };
      var iconSpan = document.createElement('span');
      iconSpan.className = 'vbb-cc-toast-icon';
      iconSpan.textContent = iconMap[type] || '\u2139';
      toast.appendChild(iconSpan);

      var body = document.createElement('div');
      body.className = 'vbb-cc-toast-body';

      var msgSpan = document.createElement('span');
      msgSpan.className = 'vbb-cc-toast-msg';
      msgSpan.textContent = message;
      body.appendChild(msgSpan);

      if (type === 'confirm') {
        var actions = document.createElement('div');
        actions.className = 'vbb-cc-toast-actions';
        // Confirm button
        var confirmBtn = document.createElement('button');
        confirmBtn.className = 'vbb-cc-toast-btn vbb-cc-toast-btn--confirm';
        confirmBtn.textContent = 'Confirm';
        actions.appendChild(confirmBtn);
        // Cancel button
        var cancelBtn = document.createElement('button');
        cancelBtn.className = 'vbb-cc-toast-btn vbb-cc-toast-btn--cancel';
        cancelBtn.textContent = 'Cancel';
        actions.appendChild(cancelBtn);

        confirmBtn.addEventListener('click', function () {
          if (toast._confirmCallback) toast._confirmCallback();
          CC._dismissToast(toast);
        });
        cancelBtn.addEventListener('click', function () {
          if (toast._cancelCallback) toast._cancelCallback();
          CC._dismissToast(toast);
        });

        body.appendChild(actions);
      }

      toast.appendChild(body);

      // Dismiss button (hidden for confirm toasts which use action buttons)
      if (type !== 'confirm') {
        var dismiss = document.createElement('button');
        dismiss.className = 'vbb-cc-toast-dismiss';
        dismiss.textContent = '\u2715';
        dismiss.addEventListener('click', function () {
          CC._dismissToast(toast);
        });
        toast.appendChild(dismiss);
      }

      CC.el.toastContainer.appendChild(toast);

      // Auto-dismiss for success and info
      var autoDismiss = duration !== undefined ? duration : (type === 'success' || type === 'info' ? 3000 : 0);
      if (autoDismiss > 0) {
        setTimeout(function () {
          CC._dismissToast(toast);
        }, autoDismiss);
      }

      return toast;
    },

    showConfirmToast: function (message, onConfirm, onCancel) {
      var toast = CC.showToast(message, 'confirm', 0);
      toast._confirmCallback = onConfirm || null;
      toast._cancelCallback = onCancel || null;
      return toast;
    },

    undo: function () {
      if (CC.undoRedoStack.length === 0) return;
      var lastChange = CC.undoRedoStack.pop();
      CC.redoStack.push(lastChange);
      // Revert the setting
      var path = lastChange.path;
      var value = lastChange.value;
      var keys = path.split('.');
      var current = CC.state.settings;
      for (var i = 0; i < keys.length - 1; i++) {
        if (!current[keys[i]] || typeof current[keys[i]] !== 'object') {
          current[keys[i]] = {};
        }
        current = current[keys[i]];
      }
      var lastKey = keys[keys.length - 1];
      current[lastKey] = value;
      // Re-render affected cards
      CC.renderCards();
      // Show toast feedback
      CC.showToast('Undo performed', 'info');
    },

    redo: function () {
      if (CC.redoStack.length === 0) return;
      var undoneChange = CC.redoStack.pop();
      CC.undoRedoStack.push(undoneChange);
      // Re-apply the setting
      var path = undoneChange.path;
      var value = undoneChange.value;
      var keys = path.split('.');
      var current = CC.state.settings;
      for (var i = 0; i < keys.length - 1; i++) {
        if (!current[keys[i]] || typeof current[keys[i]] !== 'object') {
          current[keys[i]] = {};
        }
        current = current[keys[i]];
      }
      var lastKey = keys[keys.length - 1];
      current[lastKey] = value;
      // Re-render affected cards
      CC.renderCards();
      // Show toast feedback
      CC.showToast('Redo performed', 'info');
    },

    _dismissToast: function (toast) {
      if (!toast || toast._dismissing) return;
      toast._dismissing = true;
      toast.classList.add('vbb-cc-toast--removing');
      setTimeout(function () {
        if (toast.parentNode) {
          toast.parentNode.removeChild(toast);
        }
      }, 250);
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
      // LOG: hero data on change
      if (path.indexOf('blocks.hero') === 0 || path.indexOf('blocks.hero-centered') === 0) {
        console.log('[VBB Hero Change]', path, '=', value);
        console.log('[VBB Hero State] blocks.hero:', JSON.stringify(CC.state.settings.blocks && CC.state.settings.blocks.hero, null, 2));
      }
      CC._lastChangedPath = path;

      // Track non-visual changes across debounced saves — if anything other
      // than palette/color hex values changed, the next _finishSave MUST
      // regenerate + reload to pick up fresh baked HTML.
      if (path.indexOf('palettes.') === 0 || path.indexOf('colors.') === 0) {
        // Pure palette/color hex change — track for undo/redo
        CC.undoRedoStack.push({ path: path, value: CC.state.settings });
        // Keep only last 5 entries
        if (CC.undoRedoStack.length > 5) {
          CC.undoRedoStack.shift();
        }
        // Clear redo stack on new color change
        CC.redoStack = [];
      } else {
        CC._needsRegeneration = true;
      }

      // When colorMode changes, re-render the Colors card to show the active palette
      if (path === 'colorMode') {
        CC._reRenderColorsCard();
      }

      // Undo stack size limit notification (optional)
      if (CC.undoRedoStack.length >= 5) {
        // Maximum reached, oldest will be dropped on next change
      }

      // Smart debounce based on field type
      var isColor = path.indexOf('.colors.') !== -1;
      var isVisual = path.indexOf('colors.') !== -1 || path.indexOf('.style') !== -1 || path.indexOf('.layout') !== -1 || path.indexOf('palettes.') === 0;
      var isImmediate = path.indexOf('.enabled') !== -1 || path.indexOf('style') !== -1;
      CC.debouncedSave({ visual: isVisual, immediate: isImmediate });
    },

    _isInputFocused: function () {
      var focused = document.activeElement;
      if (!focused) return false;
      // Check if focused element is a color input, text input, or color picker
      var tag = focused.tagName.toLowerCase();
      if (tag === 'input') {
        var type = focused.getAttribute('type');
        if (type === 'color' || type === 'text') {
          // Check if it has a data-path (color swatch hex input or font dropdown)
          var path = focused.getAttribute('data-path');
          if (path) return true;
          // Generic text input
          return true;
        }
      }
      if (tag === 'select') return true;
      return false;
    },

    _flashChangedField: function () {
      if (!CC._lastChangedPath) return;
      // Find the input element that was last changed via data-path attribute
      var input = document.querySelector('[data-path="' + CC._lastChangedPath.replace(/"/g, '\\"') + '"]');
      if (input) {
        input.classList.add('vbb-saved-flash');
        setTimeout(function () {
          input.classList.remove('vbb-saved-flash');
        }, 800);
      }
    },

    /* ── Card rendering ─────────────────────── */

    renderPageSelector: function () {
      if (!CC.el.pageSelector) return;
      var pages = CC.state.availablePages;
      var current = CC.state.currentPageId;
      var s = CC.state.settings || {};
      var config = s.siteConfig || {};
      var siteTypes = [
        { value: 'landing', label: 'Landing Page (One Page)' },
        { value: 'multi', label: 'Multi-page Website' },
      ];

      if (!pages || pages.length === 0) {
        CC.el.pageSelector.innerHTML =
          '<div class="vbb-cc-empty-state"><div class="vbb-cc-empty-state-icon">\uD83D\uDCDD</div><h3>No pages found</h3><p>Create a new page to get started with per-page settings.</p></div>';
        return;
      }

      var html = '<div class="vbb-cc-page-selector-inner vbb-cc-page-and-site">';
      html += '<div class="vbb-cc-page-select-row">';
      html += '<div class="vbb-cc-page-field"><label>Editing Page:</label>';
      html += '<select id="vbb-cc-page-dropdown">';
      html += '<option value="">Global Settings (All Pages)</option>';
      pages.forEach(function (page) {
        var selected = (current == page.id) ? ' selected' : '';
        html += '<option value="' + page.id + '"' + selected + '>' + CC.escAttr(page.title) + '</option>';
      });
      html += '</select></div>'; // close page-field and select
      // Site Type selector — unified with page selector
      html += '<div class="vbb-cc-page-field"><label>Site Type</label>';
      html += '<select id="vbb-cc-site-type-select" data-path="siteConfig.type">';
      html += CC.buildOptions(siteTypes, config.type || 'landing');
      html += '</select></div>';
      html += '</div>'; // close page-select-row
      html += '<p class="description" style="margin:4px 0 0;font-size:0.8rem;color:#666;">Select a page to edit its settings, or keep Global to apply everywhere. Site Type determines whether only the front page (Landing) or all pages (Multi-page) are available.</p>';
      html += '</div>'; // close page-selector-inner

      CC.el.pageSelector.innerHTML = html;

      var dropdown = document.getElementById('vbb-cc-page-dropdown');
      if (dropdown) {
        dropdown.addEventListener('change', function (e) {
          CC.onPageChange(e.target.value);
        });
      }
      // Bind site type change
      var siteTypeEl = document.getElementById('vbb-cc-site-type-select');
      if (siteTypeEl) {
        siteTypeEl.addEventListener('change', function (e) {
          CC._handleChange({ currentTarget: siteTypeEl });
        });
      }
    },

    renderCards: function () {
      var s = CC.state.settings;
      if (!s) s = {};
      var html = '';

      // Page Sections Audit Card — shows section list when a page is selected
      if (CC.state.currentPageId) {
        html += CC.renderSectionAudit(s);
      }

      // Brand & Header Card
      html += CC.buildCard(
        'Brand & Header',
        ' Configure your site identity and main navigation.',
        CC.renderHeaderSettings(s)
      );

      // Menu Settings Card
      html += CC.buildCard(
        'Navigation & Menu',
        'Set your menu type and overall navigation style.',
        CC.renderMenuSettings(s)
      );

      // Colors Card
      html += '<div class="vbb-cc-card" data-card="colors"><h2>Colors</h2><p class="description">Light &amp; Dark palette — edit any swatch.</p>' + CC.renderColorGroups(s) + '</div>';

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

      // Footer Card
      html += CC.buildCard(
        'Footer',
        'Customize the footer content, links, colors, and bottom bar.',
        CC.renderFooterSettings(s)
      );

      // Menu Editor Card
      html += CC.buildCard(
        'Menu Editor',
        'Add, remove, and reorder navigation items. Changes are saved independently via the menu API and synced to the frontend navigation.',
        CC.renderMenuEditor()
      );

      // Blocks Card
      html += CC.buildCard(
        'Blocks',
        'Toggle sections on/off across the site.',
        CC.renderBlocks(s)
      );

      // Preset Selector card
      html += CC.buildCard(
        'Presets',
        'Apply a pre-built theme configuration instantly.',
        CC.renderPresetSelector()
      );

      // Color Mode selector
      html += CC.buildCard(
        'Color Mode',
        'Choose Light, Dark, or Auto (follows device preference).',
        CC.renderColorMode(s)
      );

      // Client Briefing card
      html += CC.buildCard(
        'Client Briefing',
        'Collect client requirements and send them to the Agency Hub for processing.',
        CC.renderBriefingForm()
      );

CC.el.cards.innerHTML = html;
CC.bindCardEvents();
CC.bindMenuEvents();
CC.bindBriefingEvents();
CC.initFontDropdowns();
CC._applyStaggerAnimation();
CC._initExtras();
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

    _applyStaggerAnimation: function () {
      if (!CC.el.cards) return;
      var cards = CC.el.cards.querySelectorAll('.vbb-cc-card');
      cards.forEach(function (card, i) {
        card.style.animationDelay = (i * 80) + 'ms';
        card.classList.add('vbb-cc-card-animate');
      });
    },

    renderColorGroups: function (s) {
      var validateColor = function(val) {
        return (val && typeof val === 'string' && val.startsWith('#')) ? val : '#FFFFFF';
      };
      var activeMode = CC.state._darkPreviewEnabled ? 'dark' : (s.colorMode || 'light');
      var html = '';
      // Show only the active palette based on colorMode (or dark preview toggle)
      var modesToRender = activeMode === 'auto' ? ['light', 'dark'] : [activeMode];
      modesToRender.forEach(function (mode) {
        if (!s.palettes || !s.palettes[mode]) return;
        html +=
          '<h3 style="margin:14px 0 8px;font-size:0.9rem;font-weight:600;text-transform:capitalize">' +
          mode +
          '</h3>';
        html += '<div class="vbb-cc-color-grid">';
        Object.keys(s.palettes[mode]).forEach(function (key) {
          var val = s.palettes[mode][key] || '';
          var path = 'palettes.' + mode + '.' + key;
          html +=
            '<div class="vbb-cc-field"><label>' +
            key +
            '</label><div class="vbb-cc-color-swatch">' +
            '<input type="color" data-path="' + path + '" value="' + validateColor(val) + '">' +
            '<input type="text" class="vbb-cc-hex-input" value="' + val + '" data-path="' + path + '" pattern="^#[0-9a-fA-F]{6}$" maxlength="7">' +
            '<button class="vbb-cc-copy-btn" title="Copy hex" data-hex="' + val + '">' +
            '<span class="vbb-cc-copy-btn-icon">\uD83D\uDCCB</span>' +
            '<span class="vbb-cc-copy-tooltip">Copied</span>' +
            '</button></div></div>';
        });
        html += '</div>';
      });
      return html;
    },

    _reRenderColorsCard: function () {
      var card = document.querySelector('.vbb-cc-card[data-card="colors"]');
      if (!card) return;
      var s = CC.state.settings;
      var newCard = document.createElement('div');
      newCard.className = 'vbb-cc-card';
      newCard.setAttribute('data-card', 'colors');
      newCard.innerHTML = '<h2>Colors</h2><p class="description">Light &amp; Dark palette — edit any swatch.</p>' + CC.renderColorGroups(s);
      card.parentNode.replaceChild(newCard, card);
      // Re-bind change events on the new color inputs
      var newInputs = newCard.querySelectorAll('input[type="color"], input[type="text"][data-path]');
      newInputs.forEach(function (el) {
        if (el.type === 'color') {
          el.addEventListener('input', CC._handleChange);
        }
        el.addEventListener('change', CC._handleChange);
      });
    },

    /* ── Curated Google Fonts ──────────────────── */

    fonts: [
      { category: 'Sans-Serif', fonts: [
        'Inter', 'Roboto', 'Open Sans', 'Montserrat', 'Poppins',
        'Lato', 'Nunito', 'Raleway', 'Ubuntu', 'DM Sans',
        'Work Sans', 'Plus Jakarta Sans', 'Manrope', 'Outfit', 'Figtree',
        'Sora', 'Jost', 'Quicksand', 'Rubik', 'Muli',
        'Source Sans 3', 'Overpass', 'Karla', 'Public Sans', 'Noto Sans',
      ]},
      { category: 'Serif', fonts: [
        'Playfair Display', 'Merriweather', 'Lora', 'PT Serif', 'DM Serif Display',
        'Source Serif 4', 'Cormorant Garamond', 'Libre Baskerville', 'Noto Serif', 'Spectral',
        'Bitter', 'Alegreya', 'Cardo', 'Proza Libre', 'Sorts Mill Goudy',
      ]},
      { category: 'Display', fonts: [
        'Pacifico', 'Dancing Script', 'Bangers', 'Lobster', 'Righteous',
        'Abril Fatface', 'Anton', 'Archivo Black', 'Luckiest Guy', 'Unica One',
        'Oleo Script', 'Rubik Glitch', 'Sedgwick Ave', 'Monoton', 'Fugaz One',
      ]},
      { category: 'Handwriting', fonts: [
        'Caveat', 'Indie Flower', 'Shadows Into Light', 'Patrick Hand', 'Gloria Hallelujah',
        'Reenie Beanie', 'Kalam', 'Satisfy', 'Permanent Marker', 'Gochi Hand',
      ]},
      { category: 'Monospace', fonts: [
        'JetBrains Mono', 'Fira Code', 'Source Code Pro', 'Space Mono', 'IBM Plex Mono',
        'Inconsolata', 'DM Mono', 'Cutive Mono', 'Fragment Mono', 'Red Hat Mono',
      ]},
    ],

    renderFontDropdown: function (path, currentValue, label) {
      var html = '<div class="vbb-cc-field vbb-cc-field-font">';
      html += '<label>' + label + '</label>';
      html += '<div class="vbb-cc-font-dropdown" data-path="' + path + '">';

      // Search input
      html += '<div class="vbb-cc-font-search-wrap">';
      html += '<input type="text" class="vbb-cc-font-search" placeholder="Search fonts…" autocomplete="off">';
      html += '</div>';

      // Dropdown list
      html += '<div class="vbb-cc-font-list">';

      // 'Custom…' option always first
      var customSelected = '';
      var isCustom = true;
      // Check if current value is in our curated list
      var found = false;
      for (var ci = 0; ci < CC.fonts.length; ci++) {
        var group = CC.fonts[ci];
        for (var fi = 0; fi < group.fonts.length; fi++) {
          if (group.fonts[fi] === currentValue) {
            found = true;
            isCustom = false;
            break;
          }
        }
        if (found) break;
      }
      if (currentValue && !found) {
        isCustom = true;
        customSelected = ' data-custom-value="' + CC.escAttr(currentValue) + '"';
      }

      html += '<div class="vbb-cc-font-option vbb-cc-font-option--custom"' + customSelected + ' data-font="">';
      html += '<span class="vbb-cc-font-option-label">Custom…</span>';
      html += '</div>';

      // Font groups
      for (var ci = 0; ci < CC.fonts.length; ci++) {
        var group = CC.fonts[ci];
        html += '<div class="vbb-cc-font-group-label">' + group.category + '</div>';
        for (var fi = 0; fi < group.fonts.length; fi++) {
          var fontName = group.fonts[fi];
          var active = fontName === currentValue ? ' vbb-cc-font-option--active' : '';
          html += '<div class="vbb-cc-font-option' + active + '" data-font="' + CC.escAttr(fontName) + '" style="font-family:\'' + fontName + '\',sans-serif">';
          html += '<span class="vbb-cc-font-option-label">' + fontName + '</span>';
          html += '</div>';
        }
      }

      html += '</div>'; // .vbb-cc-font-list
      html += '</div>'; // .vbb-cc-font-dropdown

      // Custom font text input (hidden by default, shown when "Custom…" is selected)
      var customDisplay = isCustom && currentValue ? '' : ' style="display:none"';
      html += '<input type="text" class="vbb-cc-font-custom-input" data-path="' + path + '" value="' + CC.escAttr(currentValue) + '" placeholder="e.g. Georgia, serif"' + customDisplay + '>';

      html += '</div>';
      return html;
    },

    renderTypography: function (s) {
      var heading = s.typography ? s.typography.heading || '' : '';
      var body = s.typography ? s.typography.body || '' : '';
      return (
        CC.renderFontDropdown('typography.heading', heading, 'Heading font') +
        CC.renderFontDropdown('typography.body', body, 'Body font')
      );
    },

    initFontDropdowns: function () {
      var dropdowns = document.querySelectorAll('.vbb-cc-font-dropdown');
      dropdowns.forEach(function (dd) {
        var path = dd.getAttribute('data-path');
        if (!path) return;

        var searchInput = dd.querySelector('.vbb-cc-font-search');
        var fontList = dd.querySelector('.vbb-cc-font-list');
        var customInput = dd.parentNode.querySelector('.vbb-cc-font-custom-input');

        // Toggle dropdown on search focus
        searchInput.addEventListener('focus', function () {
          dd.classList.add('vbb-cc-font-dropdown--open');
        });

        // Close dropdown on click outside
        document.addEventListener('click', function (e) {
          if (!dd.contains(e.target)) {
            dd.classList.remove('vbb-cc-font-dropdown--open');
          }
        });

        // Search filter
        searchInput.addEventListener('input', function () {
          var query = searchInput.value.toLowerCase();
          var options = fontList.querySelectorAll('.vbb-cc-font-option');
          var groupLabels = fontList.querySelectorAll('.vbb-cc-font-group-label');
          options.forEach(function (opt) {
            var label = opt.querySelector('.vbb-cc-font-option-label');
            if (label) {
              var text = label.textContent.toLowerCase();
              if (text.indexOf(query) > -1) {
                opt.style.display = '';
              } else {
                opt.style.display = 'none';
              }
            }
          });
          // Hide group labels that have no visible options after them
          groupLabels.forEach(function (gl) {
            var sibling = gl.nextElementSibling;
            var hasVisible = false;
            while (sibling && !sibling.classList.contains('vbb-cc-font-group-label')) {
              if (sibling.style.display !== 'none') {
                hasVisible = true;
                break;
              }
              sibling = sibling.nextElementSibling;
            }
            gl.style.display = hasVisible ? '' : 'none';
          });
        });

        // Prevent search input click from closing dropdown
        searchInput.addEventListener('click', function (e) {
          e.stopPropagation();
        });

        // Font option click
        fontList.addEventListener('click', function (e) {
          var option = e.target.closest('.vbb-cc-font-option');
          if (!option) return;

          var fontName = option.getAttribute('data-font');
          if (fontName === '') {
            // Custom… selected — show text input
            dd.classList.remove('vbb-cc-font-dropdown--open');
            if (customInput) {
              var customVal = option.getAttribute('data-custom-value') || '';
              customInput.value = customVal;
              customInput.style.display = '';
              customInput.focus();
            }
            return;
          }

          // Update active state
          fontList.querySelectorAll('.vbb-cc-font-option--active').forEach(function (a) {
            a.classList.remove('vbb-cc-font-option--active');
          });
          option.classList.add('vbb-cc-font-option--active');

          // Update search input with selected font name
          searchInput.value = fontName;

          // Hide custom input if visible
          if (customInput) {
            customInput.style.display = 'none';
          }

          dd.classList.remove('vbb-cc-font-dropdown--open');

          // Trigger onFieldChange
          CC.onFieldChange(path, fontName);
        });

        // Custom input change
        if (customInput) {
          customInput.addEventListener('change', function () {
            var val = customInput.value;
            if (val) {
              CC.onFieldChange(path, val);
            }
          });
        }

        // Set initial search input text
        var activeOption = fontList.querySelector('.vbb-cc-font-option--active');
        if (activeOption) {
          searchInput.value = activeOption.getAttribute('data-font') || '';
        }
      });
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

    renderHeaderSettings: function (s) {
      var hc = s.headerConfig || {};
      var logoPreview = hc.logoUrl
        ? '<img src="' + CC.escAttr(hc.logoUrl) + '" class="vbb-cc-media-thumb" style="max-width:150px;max-height:100px;object-fit:cover;border-radius:4px;" />'
        : '';
      var showRemove = hc.logoUrl ? '' : ' style="display:none"';
      return (
        '<div class="vbb-cc-field"><label>Site Title</label><input type="text" data-path="headerConfig.siteTitle" value="' +
        CC.escAttr(hc.siteTitle || '') +
        '" placeholder="e.g. OrkestOne Agency"></div>' +
        '<div class="vbb-cc-field"><label>Logo</label>' +
        '<div class="vbb-cc-media-field">' +
          '<div class="vbb-cc-media-preview">' + logoPreview + '</div>' +
          '<button type="button" class="button vbb-cc-media-btn" data-target="headerConfig.logoUrl">Seleccionar de biblioteca</button>' +
          '<button type="button" class="button vbb-cc-media-remove-btn" data-target="headerConfig.logoUrl"' + showRemove + '>Eliminar</button>' +
          '<input type="hidden" data-path="headerConfig.logoUrl" value="' + CC.escAttr(hc.logoUrl || '') + '">' +
        '</div></div>' +
        '<div class="vbb-cc-field"><label>Menu Display</label><select data-path="headerConfig.menuType">' +
        CC.buildOptions([
          { value: 'logo-title', label: 'Logo + Title' },
          { value: 'logo-only', label: 'Only Logo' },
          { value: 'title-only', label: 'Only Title' },
        ], hc.menuType || 'logo-title') +
        '</select></div>' +
        '<div class="vbb-cc-field"><label>Title Color</label><input type="color" data-path="headerConfig.textColor" value="' +
        CC.escAttr(hc.textColor || '#000000') +
        '"></div>' +
        '<div class="vbb-cc-field"><label>Header Background</label><input type="color" data-path="headerConfig.bgColor" value="' +
        CC.escAttr(hc.bgColor || '#ffffff') +
        '"></div>'
      );
    },

    renderBlocks: function (s) {
      if (!s.blocks) {
        return '<div class="vbb-cc-empty-state"><div class="vbb-cc-empty-state-icon">\uD83E\uDEA8</div><h3>No blocks available</h3><p>Blocks define the sections of your site. They will appear here once configured in your vertical JSON.</p></div>';
      }
      var html = '<div class="vbb-cc-blocks-list">';
      
      Object.keys(s.blocks).forEach(function (key) {
        var block = s.blocks[key];
        // Ensure it's an object
        if (typeof block !== 'object') {
          block = { enabled: !!block };
        }
        var enabled = !!block.enabled;
        
        html += '<div class="vbb-cc-block-item">';
        html += '  <div class="vbb-cc-block-header">';
        html +=    '<label class="vbb-cc-toggle"><input type="checkbox" data-path="blocks.' + key + '.enabled" data-boolean="1"' +
                   (enabled ? ' checked' : '') +
                   '><span class="vbb-cc-toggle-track"></span><span class="vbb-cc-toggle-label">' + key + '</span></label>';
        html += '  </div>';
        
        if (enabled) {
          html += '  <div class="vbb-cc-block-settings">';
          html +=    CC.renderBlockSettings(key, block);
          html += '  </div>';
        }
        
        html += '</div>';
      });
      
      html += '</div>';
      return html;
    },

    renderBlockSettings: function (key, block) {
      // Generic rendering for standard blocks (from registry).
      var standardBlocks = ['servicesGrid', 'benefits', 'process', 'testimonials', 'faq', 'pricing', 'team', 'logoCloud', 'stats', 'gallery'];
      if (standardBlocks.indexOf(key) !== -1 && CC.registry && CC.registry[key]) {
        return CC._renderStandardBlock(key, block);
      }

      var html = '<div class="vbb-cc-block-settings-inner">';

      if (key === 'hero' || key === 'hero-centered') {
        // Hero block — full content editor (eyebrow, title, subtitle, CTAs, background)
        html += '<div class="vbb-cc-field"><label>Eyebrow (texto superior)</label><input type="text" data-path="blocks.' + key + '.eyebrow" value="' + CC.escAttr(block.eyebrow || '') + '"></div>';
        html += '<div class="vbb-cc-field"><label>Title</label><input type="text" data-path="blocks.' + key + '.title" value="' + CC.escAttr(block.title || '') + '"></div>';
        html += '<div class="vbb-cc-field"><label>Subtitle</label><input type="text" data-path="blocks.' + key + '.subtitle" value="' + CC.escAttr(block.subtitle || '') + '"></div>';
        
        html += '<div class="vbb-cc-field"><label>CTA Principal - Texto</label><input type="text" data-path="blocks.' + key + '.primaryCta" value="' + CC.escAttr(block.primaryCta || '') + '"></div>';
        html += '<div class="vbb-cc-field"><label>CTA Principal - URL</label><input type="text" data-path="blocks.' + key + '.primaryUrl" value="' + CC.escAttr(block.primaryUrl || '') + '" placeholder="/contacto"></div>';
        
        html += '<div class="vbb-cc-field"><label>CTA Secundario - Texto</label><input type="text" data-path="blocks.' + key + '.secondaryCta" value="' + CC.escAttr(block.secondaryCta || '') + '"></div>';
        html += '<div class="vbb-cc-field"><label>CTA Secundario - URL</label><input type="text" data-path="blocks.' + key + '.secondaryUrl" value="' + CC.escAttr(block.secondaryUrl || '') + '" placeholder="/contacto"></div>';
        
        html += '<div class="vbb-cc-field"><label>Effect</label><select data-path="blocks.' + key + '.effect">' +
                CC.buildOptions([{value:'fade', label:'Fade In'}, {value:'slide', label:'Slide Up'}, {value:'zoom', label:'Zoom'}], block.effect || 'fade') +
                '</select></div>';
        
        // Background Image with Media Library picker
        var currentImageId = block.image_id || 0;
        var currentImageUrl = block.image_url || '';
        var previewHtml = currentImageUrl ? '<div class="vbb-cc-image-preview" style="margin-top:8px;"><img src="' + CC.escAttr(currentImageUrl) + '" style="max-width:100%;height:auto;border-radius:4px;border:1px solid #ddd;"></div>' : '';
        
        html += '<div class="vbb-cc-field vbb-cc-field-image">';
        html += '<label>Background Image</label>';
        html += '<div class="vbb-cc-image-controls">';
        html += '<input type="hidden" class="vbb-cc-image-id" data-path="blocks.' + key + '.image_id" value="' + CC.escAttr(currentImageId) + '">';
        html += '<input type="hidden" class="vbb-cc-image-url" data-path="blocks.' + key + '.image_url" value="' + CC.escAttr(currentImageUrl) + '">';
        html += '<button type="button" class="button vbb-cc-media-btn" data-target="blocks.' + key + '">Seleccionar de biblioteca</button>';
        html += '<button type="button" class="button vbb-cc-media-clear" data-target="blocks.' + key + '" style="margin-left:8px;">Eliminar</button>';
        html += '</div>';
        html += previewHtml;
        html += '</div>';

      } else if (key === 'servicesGrid') {
        // Services Grid — heading + repeatable items
        html += '<div class="vbb-cc-field"><label>Section Heading</label><input type="text" data-path="blocks.' + key + '.heading" value="' + CC.escAttr(block.heading || '') + '" placeholder="Nuestros Servicios"></div>';
        html += CC.renderRepeatableItems(key, block.items || [], [
          { key: 'icon', label: 'Icono (slug)', placeholder: 'layout, rocket, users...', type: 'text' },
          { key: 'title', label: 'Título', placeholder: 'Diseño Web', type: 'text' },
          { key: 'summary', label: 'Descripción', placeholder: 'Descripción breve del servicio', type: 'textarea' },
          { key: 'ctaText', label: 'Texto CTA', placeholder: 'Ver más', type: 'text' },
          { key: 'ctaUrl', label: 'URL CTA', placeholder: '/servicios/diseno', type: 'text' },
        ]);

      } else if (key === 'benefits') {
        // Benefits — heading + repeatable items
        html += '<div class="vbb-cc-field"><label>Section Heading</label><input type="text" data-path="blocks.' + key + '.heading" value="' + CC.escAttr(block.heading || '') + '" placeholder="Por qué elegirnos"></div>';
        html += CC.renderRepeatableItems(key, block.items || [], [
          { key: 'icon', label: 'Icono (slug dashicons)', placeholder: 'layout, check, star, shield...', type: 'text' },
          { key: 'title', label: 'Título', placeholder: 'Arquitectura Reutilizable', type: 'text' },
          { key: 'description', label: 'Descripción', placeholder: 'Beneficio principal...', type: 'textarea' },
        ]);

      } else if (key === 'testimonials') {
        // Testimonials — heading + repeatable items + style
        html += '<div class="vbb-cc-field"><label>Section Heading</label><input type="text" data-path="blocks.' + key + '.heading" value="' + CC.escAttr(block.heading || '') + '" placeholder="Lo que dicen nuestros clientes"></div>';
        html += CC.renderRepeatableItems(key, block.items || [], [
          { key: 'quote', label: 'Testimonio', placeholder: 'Mejor decisión que tomamos...', type: 'textarea' },
          { key: 'author', label: 'Autor', placeholder: 'María González', type: 'text' },
          { key: 'role', label: 'Cargo / Empresa', placeholder: 'CEO @ TechCorp', type: 'text' },
          { key: 'avatar', label: 'Avatar URL', placeholder: 'https://...', type: 'text' },
        ]);

      } else if (key === 'faq') {
        // FAQ — heading + repeatable Q&A items
        html += '<div class="vbb-cc-field"><label>Section Heading</label><input type="text" data-path="blocks.' + key + '.heading" value="' + CC.escAttr(block.heading || '') + '" placeholder="Preguntas Frecuentes"></div>';
        html += CC.renderRepeatableItems(key, block.items || [], [
          { key: 'question', label: 'Pregunta', placeholder: '¿Cuánto tarda el proyecto?', type: 'text' },
          { key: 'answer', label: 'Respuesta', placeholder: 'Depende del alcance...', type: 'textarea' },
        ]);

      } else if (key === 'process') {
        // Process — heading + repeatable steps
        html += '<div class="vbb-cc-field"><label>Section Heading</label><input type="text" data-path="blocks.' + key + '.heading" value="' + CC.escAttr(block.heading || '') + '" placeholder="Nuestro Proceso"></div>';
        html += CC.renderRepeatableItems(key, block.items || [], [
          { key: 'number', label: 'Número de paso', placeholder: '1', type: 'number', min: 1 },
          { key: 'title', label: 'Título del paso', placeholder: 'Auditoría', type: 'text' },
          { key: 'description', label: 'Descripción', placeholder: 'Analizamos tu estado actual...', type: 'textarea' },
          { key: 'icon', label: 'Icono (slug)', placeholder: 'search, lightbulb, rocket...', type: 'text' },
        ]);

      } else if (key === 'pricing') {
        // Pricing — heading + repeatable plans
        html += '<div class="vbb-cc-field"><label>Section Heading</label><input type="text" data-path="blocks.' + key + '.heading" value="' + CC.escAttr(block.heading || '') + '" placeholder="Planes y Precios"></div>';
        html += CC.renderRepeatableItems(key, block.items || [], [
          { key: 'name', label: 'Nombre del plan', placeholder: 'Profesional', type: 'text' },
          { key: 'price', label: 'Precio', placeholder: '299', type: 'text' },
          { key: 'period', label: 'Periodo', placeholder: '/mes', type: 'text' },
          { key: 'features', label: 'Características (una por línea)', placeholder: 'Incluye X\nIncluye Y\nIncluye Z', type: 'textarea' },
          { key: 'ctaText', label: 'Texto CTA', placeholder: 'Comenzar', type: 'text' },
          { key: 'ctaUrl', label: 'URL CTA', placeholder: '/contacto', type: 'text' },
          { key: 'featured', label: 'Plan destacado', type: 'checkbox' },
        ]);

      } else if (key === 'team') {
        // Team — heading + repeatable members
        html += '<div class="vbb-cc-field"><label>Section Heading</label><input type="text" data-path="blocks.' + key + '.heading" value="' + CC.escAttr(block.heading || '') + '" placeholder="Nuestro Equipo"></div>';
        html += CC.renderRepeatableItems(key, block.items || [], [
          { key: 'name', label: 'Nombre', placeholder: 'Juan Pérez', type: 'text' },
          { key: 'role', label: 'Cargo', placeholder: 'Lead Developer', type: 'text' },
          { key: 'bio', label: 'Bio / Descripción', placeholder: 'Experto en...', type: 'textarea' },
          { key: 'image', label: 'Imagen URL', placeholder: 'https://...', type: 'text' },
          { key: 'linkedin', label: 'LinkedIn URL', placeholder: 'https://linkedin.com/in/...', type: 'text' },
          { key: 'twitter', label: 'Twitter/X URL', placeholder: 'https://x.com/...', type: 'text' },
          { key: 'github', label: 'GitHub URL', placeholder: 'https://github.com/...', type: 'text' },
        ]);

      } else if (key === 'logoCloud') {
        // Logo Cloud — heading + repeatable logos
        html += '<div class="vbb-cc-field"><label>Section Heading</label><input type="text" data-path="blocks.' + key + '.heading" value="' + CC.escAttr(block.heading || '') + '" placeholder="Con la confianza de"></div>';
        html += CC.renderRepeatableItems(key, block.items || [], [
          { key: 'name', label: 'Nombre empresa', placeholder: 'TechCorp', type: 'text' },
          { key: 'logo', label: 'Logo URL', placeholder: 'https://.../logo.svg', type: 'text' },
          { key: 'url', label: 'URL enlace (opcional)', placeholder: 'https://techcorp.com', type: 'text' },
        ]);

      } else if (key === 'ctaFinal') {
        // CTA Final — text, button, URL, subtitle (style C), secondary CTA
        html += '<div class="vbb-cc-field"><label>Main Text</label><input type="text" data-path="blocks.' + key + '.text" value="' + CC.escAttr(block.text || '') + '"></div>';
        html += '<div class="vbb-cc-field"><label>Subtitle (Style C)</label><input type="text" data-path="blocks.' + key + '.subtitle" value="' + CC.escAttr(block.subtitle || '') + '" placeholder="Texto adicional para estilo C"></div>';
        html += '<div class="vbb-cc-field"><label>Button Text</label><input type="text" data-path="blocks.' + key + '.buttonText" value="' + CC.escAttr(block.buttonText || '') + '"></div>';
        html += '<div class="vbb-cc-field"><label>Button URL</label><input type="text" data-path="blocks.' + key + '.buttonUrl" value="' + CC.escAttr(block.buttonUrl || '') + '"></div>';
        html += '<div class="vbb-cc-field"><label>Secondary CTA Text</label><input type="text" data-path="blocks.' + key + '.secondaryCta" value="' + CC.escAttr(block.secondaryCta || '') + '" placeholder="Texto botón secundario"></div>';
        html += '<div class="vbb-cc-field"><label>Secondary CTA URL</label><input type="text" data-path="blocks.' + key + '.secondaryUrl" value="' + CC.escAttr(block.secondaryUrl || '') + '" placeholder="/contacto"></div>';

      } else if (key === 'contact') {
        // Contact — heading, email, phone, address, form fields, endpoint, reCAPTCHA
        html += '<div class="vbb-cc-field"><label>Section Heading</label><input type="text" data-path="blocks.' + key + '.heading" value="' + CC.escAttr(block.heading || '') + '" placeholder="Contacto"></div>';
        html += '<div class="vbb-cc-field"><label>Email</label><input type="email" data-path="blocks.' + key + '.email" value="' + CC.escAttr(block.email || '') + '" placeholder="email@example.com"></div>';
        html += '<div class="vbb-cc-field"><label>Phone</label><input type="text" data-path="blocks.' + key + '.phone" value="' + CC.escAttr(block.phone || '') + '" placeholder="+54 11 9999-8888"></div>';
        html += '<div class="vbb-cc-field"><label>Dirección</label><input type="text" data-path="blocks.' + key + '.address" value="' + CC.escAttr(block.address || '') + '" placeholder="Av. Corrientes 1234, CABA"></div>';
        
        html += '<h4 style="margin:16px 0 8px;font-size:0.9rem;">Configuración del Formulario</h4>';
        html += '<div class="vbb-cc-field"><label>Endpoint del formulario</label><input type="text" data-path="blocks.' + key + '.formEndpoint" value="' + CC.escAttr(block.formEndpoint || '') + '" placeholder="/wp-json/orkestone/v1/contact"></div>';
        
        // reCAPTCHA
        html += '<div class="vbb-cc-field"><label>reCAPTCHA</label><select data-path="blocks.' + key + '.recaptcha">' +
                CC.buildOptions([
                    {value:'none', label:'Sin reCAPTCHA'},
                    {value:'v2', label:'reCAPTCHA v2 (checkbox)'},
                    {value:'v3', label:'reCAPTCHA v3 (invisible)'}
                ], block.recaptcha || 'none') +
                '</select></div>';
        html += '<div class="vbb-cc-field"><label>reCAPTCHA Site Key</label><input type="text" data-path="blocks.' + key + '.recaptchaKey" value="' + CC.escAttr(block.recaptchaKey || '') + '" placeholder="6Lc..."></div>';
        html += '<div class="vbb-cc-field"><label>reCAPTCHA Secret Key (solo admin)</label><input type="password" data-path="blocks.' + key + '.recaptchaSecret" value="' + CC.escAttr(block.recaptchaSecret || '') + '" placeholder="6Lc..."></div>';
        
        // Form fields as repeatable items
        var formFields = block.formFields || [
          { type: 'text', name: 'name', label: 'Nombre', required: true, placeholder: 'Tu nombre' },
          { type: 'email', name: 'email', label: 'Email', required: true, placeholder: 'tu@email.com' },
          { type: 'textarea', name: 'message', label: 'Mensaje', required: true, placeholder: 'Tu mensaje...' },
        ];
        html += CC.renderRepeatableItems(key, formFields, [
          { key: 'type', label: 'Tipo', placeholder: 'text, email, tel, url, number, textarea, select, checkbox', type: 'select', options: [
            {value:'text', label:'Texto'},
            {value:'email', label:'Email'},
            {value:'tel', label:'Teléfono'},
            {value:'url', label:'URL'},
            {value:'number', label:'Número'},
            {value:'textarea', label:'Área de texto'},
            {value:'select', label:'Selector (dropdown)'},
            {value:'checkbox', label:'Casilla de verificación'}
          ]},
          { key: 'name', label: 'Name (identificador)', placeholder: 'name, email, message...', type: 'text' },
          { key: 'label', label: 'Label visible', placeholder: 'Nombre, Email, Mensaje...', type: 'text' },
          { key: 'placeholder', label: 'Placeholder', placeholder: 'Tu nombre, tu@email.com...', type: 'text' },
          { key: 'required', label: 'Requerido', type: 'checkbox' },
          { key: 'options', label: 'Opciones (para select, JSON)', placeholder: '[{"value":"ventas","label":"Ventas"},{"value":"soporte","label":"Soporte"}]', type: 'textarea' },
        ]);

      } else if (key === 'stats') {
        // Stats/Numbers — heading + repeatable items (value, label, icon, description)
        html += '<div class="vbb-cc-field"><label>Section Heading</label><input type="text" data-path="blocks.' + key + '.heading" value="' + CC.escAttr(block.heading || '') + '" placeholder="En Números"></div>';
        html += CC.renderRepeatableItems(key, block.items || [], [
          { key: 'value', label: 'Valor', placeholder: '500+, 98%, 24/7...', type: 'text' },
          { key: 'label', label: 'Etiqueta', placeholder: 'Proyectos, Satisfacción...', type: 'text' },
          { key: 'icon', label: 'Icono (dashicons slug)', placeholder: 'folder, heart, clock...', type: 'text' },
          { key: 'description', label: 'Descripción', placeholder: 'Completados a tiempo...', type: 'text' },
        ]);

      } else if (key === 'gallery') {
        // Gallery/Portfolio — heading + layout + repeatable items
        html += '<div class="vbb-cc-field"><label>Section Heading</label><input type="text" data-path="blocks.' + key + '.heading" value="' + CC.escAttr(block.heading || '') + '" placeholder="Portfolio"></div>';
        html += '<div class="vbb-cc-field"><label>Layout</label><select data-path="blocks.' + key + '.layout">' +
                CC.buildOptions([{value:'masonry', label:'Masonry'}, {value:'grid', label:'Grid'}, {value:'carousel', label:'Carousel'}], block.layout || 'masonry') +
                '</select></div>';
        html += CC.renderRepeatableItems(key, block.items || [], [
          { key: 'image', label: 'Imagen URL', placeholder: 'https://...', type: 'text' },
          { key: 'title', label: 'Título', placeholder: 'Proyecto 1', type: 'text' },
          { key: 'category', label: 'Categoría', placeholder: 'Web, Mobile, Branding...', type: 'text' },
          { key: 'url', label: 'URL enlace', placeholder: 'https://...', type: 'text' },
          { key: 'description', label: 'Descripción', placeholder: 'Descripción del proyecto', type: 'textarea' },
        ]);

      } else if (key === 'video') {
        // Video — heading, subtitle, video URL, type, poster, CTA
        html += '<div class="vbb-cc-field"><label>Section Heading</label><input type="text" data-path="blocks.' + key + '.heading" value="' + CC.escAttr(block.heading || '') + '" placeholder="Ver en Acción"></div>';
        html += '<div class="vbb-cc-field"><label>Subtitle</label><input type="text" data-path="blocks.' + key + '.subtitle" value="' + CC.escAttr(block.subtitle || '') + '" placeholder="Subtítulo opcional"></div>';
        html += '<div class="vbb-cc-field"><label>Video URL</label><input type="text" data-path="blocks.' + key + '.video_url" value="' + CC.escAttr(block.video_url || '') + '" placeholder="https://youtube.com/watch?v=... o https://vimeo.com/..."></div>';
        html += '<div class="vbb-cc-field"><label>Tipo de Video</label><select data-path="blocks.' + key + '.video_type">' +
                CC.buildOptions([{value:'youtube', label:'YouTube'}, {value:'vimeo', label:'Vimeo'}, {value:'mp4', label:'MP4 (autohospedado)'}], block.video_type || 'youtube') +
                '</select></div>';
        html += '<div class="vbb-cc-field"><label>Poster Image URL (opcional, para MP4)</label><input type="text" data-path="blocks.' + key + '.poster" value="' + CC.escAttr(block.poster || '') + '" placeholder="https://..."></div>';
        html += '<div class="vbb-cc-field"><label>CTA Text</label><input type="text" data-path="blocks.' + key + '.cta_text" value="' + CC.escAttr(block.cta_text || '') + '" placeholder="Ver Demo"></div>';
        html += '<div class="vbb-cc-field"><label>CTA URL</label><input type="text" data-path="blocks.' + key + '.cta_url" value="' + CC.escAttr(block.cta_url || '') + '" placeholder="/contacto"></div>';

      } else if (key === 'newsletter') {
        // Newsletter — heading, description, placeholder, button text, provider, listId
        html += '<div class="vbb-cc-field"><label>Section Heading</label><input type="text" data-path="blocks.' + key + '.heading" value="' + CC.escAttr(block.heading || '') + '" placeholder="Suscríbete"></div>';
        html += '<div class="vbb-cc-field"><label>Description</label><input type="text" data-path="blocks.' + key + '.description" value="' + CC.escAttr(block.description || '') + '" placeholder="Recibe novedades semanales"></div>';
        html += '<div class="vbb-cc-field"><label>Placeholder Email</label><input type="text" data-path="blocks.' + key + '.placeholder" value="' + CC.escAttr(block.placeholder || '') + '" placeholder="tu@email.com"></div>';
        html += '<div class="vbb-cc-field"><label>Button Text</label><input type="text" data-path="blocks.' + key + '.button_text" value="' + CC.escAttr(block.button_text || '') + '" placeholder="Suscribirme"></div>';
        html += '<div class="vbb-cc-field"><label>Provider</label><select data-path="blocks.' + key + '.provider">' +
                CC.buildOptions([{value:'custom', label:'Custom Endpoint'}, {value:'mailchimp', label:'Mailchimp'}, {value:'convertkit', label:'ConvertKit'}], block.provider || 'custom') +
                '</select></div>';
        html += '<div class="vbb-cc-field"><label>List ID</label><input type="text" data-path="blocks.' + key + '.listId" value="' + CC.escAttr(block.listId || '') + '" placeholder="abc123 (Mailchimp/ConvertKit)"></div>';

      } else if (key === 'map') {
        // Map — heading, address, lat/lng, zoom, map type, marker title
        html += '<div class="vbb-cc-field"><label>Section Heading</label><input type="text" data-path="blocks.' + key + '.heading" value="' + CC.escAttr(block.heading || '') + '" placeholder="Nuestra Oficina"></div>';
        html += '<div class="vbb-cc-field"><label>Dirección</label><input type="text" data-path="blocks.' + key + '.address" value="' + CC.escAttr(block.address || '') + '" placeholder="Av. Corrientes 1234, CABA"></div>';
        html += '<div class="vbb-cc-field"><label>Latitud</label><input type="number" step="any" data-path="blocks.' + key + '.lat" value="' + CC.escAttr(block.lat || '') + '" placeholder="-34.6037"></div>';
        html += '<div class="vbb-cc-field"><label>Longitud</label><input type="number" step="any" data-path="blocks.' + key + '.lng" value="' + CC.escAttr(block.lng || '') + '" placeholder="-58.3816"></div>';
        html += '<div class="vbb-cc-field"><label>Zoom</label><input type="number" data-path="blocks.' + key + '.zoom" value="' + CC.escAttr(block.zoom || '') + '" placeholder="15"></div>';
        html += '<div class="vbb-cc-field"><label>Map Type</label><select data-path="blocks.' + key + '.map_type">' +
                CC.buildOptions([{value:'roadmap', label:'Roadmap'}, {value:'satellite', label:'Satélite'}, {value:'hybrid', label:'Híbrido'}, {value:'terrain', label:'Terreno'}], block.map_type || 'roadmap') +
                '</select></div>';
        html += '<div class="vbb-cc-field"><label>Marker Title</label><input type="text" data-path="blocks.' + key + '.marker_title" value="' + CC.escAttr(block.marker_title || '') + '" placeholder="Nuestra Oficina"></div>';

      } else if (key === 'comparison') {
        // Comparison Table — heading + repeatable rows
        html += '<div class="vbb-cc-field"><label>Section Heading</label><input type="text" data-path="blocks.' + key + '.heading" value="' + CC.escAttr(block.heading || '') + '" placeholder="Comparativa de Planes"></div>';
        html += CC.renderRepeatableItems(key, block.rows || [], [
          { key: 'feature', label: 'Característica', placeholder: 'Proyectos ilimitados', type: 'text' },
          { key: 'plan1', label: 'Plan 1', placeholder: '✓', type: 'text' },
          { key: 'plan2', label: 'Plan 2', placeholder: '✓', type: 'text' },
          { key: 'plan3', label: 'Plan 3', placeholder: '✓', type: 'text' },
          { key: 'highlight', label: 'Destacar', type: 'checkbox' },
        ]);

      } else if (key === 'blog') {
        // Blog — heading, category, limit, layout, show options
        html += '<div class="vbb-cc-field"><label>Section Heading</label><input type="text" data-path="blocks.' + key + '.heading" value="' + CC.escAttr(block.heading || '') + '" placeholder="Últimas Noticias"></div>';
        html += '<div class="vbb-cc-field"><label>Categoría (slug)</label><input type="text" data-path="blocks.' + key + '.category" value="' + CC.escAttr(block.category || '') + '" placeholder="noticias"></div>';
        html += '<div class="vbb-cc-field"><label>Límite</label><input type="number" data-path="blocks.' + key + '.limit" value="' + CC.escAttr(block.limit || '') + '" placeholder="6"></div>';
        html += '<div class="vbb-cc-field"><label>Layout</label><select data-path="blocks.' + key + '.layout">' +
                CC.buildOptions([{value:'grid', label:'Grid'}, {value:'list', label:'List'}, {value:'masonry', label:'Masonry'}], block.layout || 'grid') +
                '</select></div>';
        html += '<div class="vbb-cc-field"><label class="vbb-cc-toggle"><input type="checkbox" data-path="blocks.' + key + '.showExcerpt" data-boolean="1"' + (block.showExcerpt ? ' checked' : '') + '><span class="vbb-cc-toggle-track"></span><span class="vbb-cc-toggle-label">Mostrar extracto</span></label></div>';
        html += '<div class="vbb-cc-field"><label class="vbb-cc-toggle"><input type="checkbox" data-path="blocks.' + key + '.showDate" data-boolean="1"' + (block.showDate ? ' checked' : '') + '><span class="vbb-cc-toggle-track"></span><span class="vbb-cc-toggle-label">Mostrar fecha</span></label></div>';
        html += '<div class="vbb-cc-field"><label class="vbb-cc-toggle"><input type="checkbox" data-path="blocks.' + key + '.showAuthor" data-boolean="1"' + (block.showAuthor ? ' checked' : '') + '><span class="vbb-cc-toggle-track"></span><span class="vbb-cc-toggle-label">Mostrar autor</span></label></div>';

      } else if (key === 'divider') {
        // Divider — type, color, thickness, margin
        html += '<div class="vbb-cc-field"><label>Tipo</label><select data-path="blocks.' + key + '.type">' +
                CC.buildOptions([{value:'line', label:'Línea'}, {value:'space', label:'Espacio'}, {value:'wave', label:'Onda'}, {value:'dots', label:'Puntos'}], block.type || 'line') +
                '</select></div>';
        html += '<div class="vbb-cc-field"><label>Color</label><input type="color" data-path="blocks.' + key + '.color" value="' + CC.escAttr(block.color || '') + '"></div>';
        html += '<div class="vbb-cc-field"><label>Grosor (px)</label><input type="number" data-path="blocks.' + key + '.thickness" value="' + CC.escAttr(block.thickness || '') + '" placeholder="2"></div>';
        html += '<div class="vbb-cc-field"><label>Margen vertical (px)</label><input type="number" data-path="blocks.' + key + '.margin" value="' + CC.escAttr(block.margin || '') + '" placeholder="40"></div>';

      } else {
        // Fallback for any other block
        var labelMap = {
          servicesGrid: 'Services Heading',
          benefits:     'Benefits Heading',
          testimonials: 'Testimonials Heading',
          faq:          'FAQ Heading',
          process:      'Process Heading',
          pricing:      'Pricing Heading',
          team:         'Team Heading',
          logoCloud:    'Logo Cloud Heading',
          stats:        'Stats Heading',
          gallery:      'Gallery Heading',
          video:        'Video Heading',
          newsletter:   'Newsletter Heading',
          map:          'Map Heading',
          comparison:   'Comparison Heading',
          blog:         'Blog Heading',
          divider:      'Divider Heading',
        };
        html += '<div class="vbb-cc-field"><label>' + (labelMap[key] || 'Section Heading') + '</label><input type="text" data-path="blocks.' + key + '.heading" value="' + CC.escAttr(block.heading || '') + '" placeholder="Section heading\u2026"></div>';
      }

      // Style selector for blocks that support style variants
      if (key === 'hero' || key === 'hero-centered' || key === 'ctaFinal' || key === 'testimonials' || key === 'pricing' || key === 'team' || key === 'gallery' || key === 'video' || key === 'pricing' || key === 'team') {
        var currentStyle = block.style || 'A';
        html += '<div class="vbb-cc-style-selector">';
        html += '<label>Section Style</label>';
        html += '<div class="vbb-cc-style-buttons">';
        html += '<button class="vbb-cc-style-btn' + (currentStyle === 'A' ? ' vbb-cc-style-btn--active' : '') + '" data-path="blocks.' + key + '.style" data-style="A">A</button>';
        html += '<button class="vbb-cc-style-btn' + (currentStyle === 'B' ? ' vbb-cc-style-btn--active' : '') + '" data-path="blocks.' + key + '.style" data-style="B">B</button>';
        html += '<button class="vbb-cc-style-btn' + (currentStyle === 'C' ? ' vbb-cc-style-btn--active' : '') + '" data-path="blocks.' + key + '.style" data-style="C">C</button>';
        html += '</div></div>';
      }

      // Per-block color pickers (excludes primary, secondary)
      var blockColors = (block && block.colors) || {};
      var perBlockKeys = ['accent', 'background', 'surface', 'text', 'mutedText'];
      html += '<div class="vbb-cc-block-colors">';
      html += '<h4 style="margin:12px 0 8px;font-size:0.85rem;font-weight:600;color:#444;">Block Colors</h4>';
      html += '<p class="description" style="margin:0 0 8px;font-size:0.8rem;">Override palette colors for this section only.</p>';
      html += '<div class="vbb-cc-color-grid">';
      for (var ci = 0; ci < perBlockKeys.length; ci++) {
        var ck = perBlockKeys[ci];
        var cpath = 'blocks.' + key + '.colors.' + ck;
        var val = blockColors[ck] || '';
        html +=
          '<div class="vbb-cc-field"><label>' + ck + '</label>' +
          '<div class="vbb-cc-color-swatch">' +
                  '<input type="color" data-path="' + cpath + '" value="' + CC._validateColor(val) + '">' +
                  '<input type="text" class="vbb-cc-hex-input" value="' + val + '" data-path="' + cpath + '" pattern="^#[0-9a-fA-F]{6}$" maxlength="7">' +
          '<button class="vbb-cc-copy-btn" title="Copy hex" data-hex="' + val + '">' +
          '<span class="vbb-cc-copy-btn-icon">\uD83D\uDCCB</span>' +
          '<span class="vbb-cc-copy-tooltip">Copied</span>' +
          '</button></div></div>';
      }
      html += '</div></div>';
      
      html += '</div>';
      return html;
    },

    /**
     * Render a standard block using registry field definitions.
     * @param {string} key
     * @param {object} block
     * @returns {string}
     */
    _renderStandardBlock: function (key, block) {
      var html = '';
      html += CC.renderFromRegistry(key, block, 'blocks.' + key);

      // Style selector (same for all that support it).
      var def = CC.registry[key];
      if (def && def.styles && def.styles.length > 1) {
        var currentStyle = block.style || def.styles[0];
        html += '<div class="vbb-cc-style-selector">';
        html += '<label>Section Style</label>';
        html += '<div class="vbb-cc-style-buttons">';
        for (var si = 0; si < def.styles.length; si++) {
          html += '<button class="vbb-cc-style-btn' + (currentStyle === def.styles[si] ? ' vbb-cc-style-btn--active' : '') + '" data-path="blocks.' + key + '.style" data-style="' + def.styles[si] + '">' + def.styles[si] + '</button>';
        }
        html += '</div></div>';
      }

      // Per-block colors.
      var blockColors = (block && block.colors) || {};
      var perBlockKeys = ['accent', 'background', 'surface', 'text', 'mutedText'];
      html += '<div class="vbb-cc-block-colors">';
      html += '<h4 style="margin:12px 0 8px;font-size:0.85rem;font-weight:600;color:#444;">Block Colors</h4>';
      html += '<p class="description" style="margin:0 0 8px;font-size:0.8rem;">Override palette colors for this section only.</p>';
      html += '<div class="vbb-cc-color-grid">';
      for (var ci = 0; ci < perBlockKeys.length; ci++) {
        var ck = perBlockKeys[ci];
        var cpath = 'blocks.' + key + '.colors.' + ck;
        var val = blockColors[ck] || '';
        html += '<div class="vbb-cc-field"><label>' + ck + '</label>' +
          '<div class="vbb-cc-color-swatch">' +
          '<input type="color" data-path="' + cpath + '" value="' + CC._validateColor(val) + '">' +
          '<input type="text" class="vbb-cc-hex-input" value="' + val + '" data-path="' + cpath + '" pattern="^#[0-9a-fA-F]{6}$" maxlength="7">' +
          '</div></div>';
      }
      html += '</div></div>';

      return html;
    },

    /**
     * Render repeatable items UI for blocks with item arrays (services, benefits, testimonials, etc.)
     * @param {string} blockKey - e.g. 'servicesGrid'
     * @param {Array} items - Array of item objects
     * @param {Array} fields - Array of field definitions: [{key, label, placeholder, type}]
     * @returns {string} HTML
     */
    renderRepeatableItems: function (blockKey, items, fields) {
      var html = '';
      var itemPrefix = 'blocks.' + blockKey + '.items';
      var pathForHandlers = itemPrefix; // e.g., "blocks.servicesGrid.items"
      var fieldsJson = JSON.stringify(fields);

      // Container with add button
      html += '<div class="vbb-cc-repeatable" data-block-key="' + blockKey + '">';
      html += '<div class="vbb-cc-repeatable-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">';
      html += '<h4 style="margin:0;font-size:0.9rem;">Items (' + (items && items.length ? items.length : 0) + ')</h4>';
      html += '<button type="button" class="button vbb-cc-repeatable-add" data-path="' + pathForHandlers + '" data-fields=\'' + fieldsJson + '\' style="font-size:0.8rem;padding:4px 10px;">+ Añadir item</button>';
      html += '</div>';

      // Items list
      html += '<div class="vbb-cc-repeatable-list" style="display:flex;flex-direction:column;gap:12px;">';

      if (items && items.length > 0) {
        for (var i = 0; i < items.length; i++) {
          var item = items[i] || {};
          html += '<div class="vbb-cc-repeatable-item" style="background:#fafafa;border:1px solid #e0e0e0;border-radius:8px;padding:12px;position:relative;" draggable="true" data-index="' + i + '">';
          html += '<div class="vbb-cc-repeatable-item-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;padding-bottom:8px;border-bottom:1px solid #e0e0e0;">';
          html += '<div style="display:flex;align-items:center;gap:8px;">';
          html += '<span class="vbb-cc-drag-handle" style="cursor:grab;padding:4px;color:#888;" title="Arrastrar para reordenar">⋮⋮</span>';
          html += '<span style="font-weight:600;font-size:0.85rem;">Item ' + (i + 1) + '</span>';
          html += '</div>';
          html += '<div style="display:flex;gap:4px;">';
          html += '<button type="button" class="vbb-cc-repeatable-move-up" data-path="' + pathForHandlers + '" data-index="' + i + '" title="Subir" style="background:none;border:none;cursor:pointer;padding:4px;">▲</button>';
          html += '<button type="button" class="vbb-cc-repeatable-move-down" data-path="' + pathForHandlers + '" data-index="' + i + '" title="Bajar" style="background:none;border:none;cursor:pointer;padding:4px;">▼</button>';
          html += '<button type="button" class="vbb-cc-repeatable-duplicate" data-path="' + pathForHandlers + '" data-index="' + i + '" title="Duplicar" style="background:none;border:none;cursor:pointer;padding:4px;">⎘</button>';
          html += '<button type="button" class="vbb-cc-repeatable-remove" data-path="' + pathForHandlers + '" data-index="' + i + '" title="Eliminar" style="background:none;border:none;cursor:pointer;padding:4px;color:#dc3232;">✕</button>';
          html += '</div>';
          html += '</div>';

          // Fields for this item
          for (var fi = 0; fi < fields.length; fi++) {
            var field = fields[fi];
            var fkey = field.key;
            var flabel = field.label || fkey;
            var fplaceholder = field.placeholder || '';
            var ftype = field.type || 'text';
            var fvalue = item[fkey] || '';

            html += '<div class="vbb-cc-field" style="margin-bottom:8px;">';
            html += '<label>' + flabel + '</label>';
            if (ftype === 'textarea') {
              html += '<textarea data-path="' + itemPrefix + '.' + i + '.' + fkey + '" placeholder="' + CC.escAttr(fplaceholder) + '" style="min-height:60px;">' + CC.escAttr(fvalue) + '</textarea>';
            } else {
              html += '<input type="' + ftype + '" data-path="' + itemPrefix + '.' + i + '.' + fkey + '" value="' + CC.escAttr(fvalue) + '" placeholder="' + CC.escAttr(fplaceholder) + '">';
            }
            html += '</div>';
          }

          html += '</div>'; // .vbb-cc-repeatable-item
        }
      } else {
        html += '<p class="description" style="text-align:center;padding:20px;color:#888;">No hay items. Click "Añadir item" para crear el primero.</p>';
      }

      html += '</div>'; // .vbb-cc-repeatable-list
      html += '</div>'; // .vbb-cc-repeatable

      return html;
    },

    /**
     * Render an image/media field with wp.media picker.
     * @param {string} path - data-path attribute value
     * @param {string} value - Current image URL
     * @returns {string} HTML
     */
    _renderImageField: function (path, value) {
      var html = '';
      html += '<div class="vbb-cc-field vbb-cc-media-field" data-path="' + path + '">';
      html += '<div class="vbb-cc-media-preview">';
      if (value) {
        html += '<img src="' + CC.escAttr(value) + '" class="vbb-cc-media-thumb" style="max-width:150px;max-height:100px;object-fit:cover;border-radius:4px;" />';
      }
      html += '</div>';
      html += '<div class="vbb-cc-media-actions">';
      html += '<button class="button vbb-cc-media-btn" data-target="' + path + '">Seleccionar Imagen</button>';
      html += '<button class="button vbb-cc-media-remove-btn" data-target="' + path + '"' + (value ? '' : ' style="display:none;"') + '>Quitar</button>';
      html += '<input type="hidden" data-path="' + path + '" value="' + CC.escAttr(value || '') + '" />';
      html += '</div>';
      html += '</div>';
      return html;
    },

    /**
     * Render block settings from registry field definitions.
     * @param {string} key - Block key
     * @param {object} block - Block data from state
     * @param {string} prefix - Path prefix for data-path attributes
     * @returns {string} HTML
     */
    renderFromRegistry: function (key, block, prefix) {
      var def = CC.registry[key];
      if (!def) return '';

      prefix = prefix || 'blocks.' + key;
      var html = '';
      var fields = def.fields || [];

      for (var fi = 0; fi < fields.length; fi++) {
        var field = fields[fi];
        var fkey = field.key;
        var ftype = field.type;
        var fpath = prefix + '.' + fkey;
        var value = block && block[fkey] !== undefined ? block[fkey] : field.default;

        if (ftype === 'repeatable') {
          html += '<h4 style="margin:16px 0 8px;font-size:0.9rem;font-weight:600;">' + field.label + '</h4>';
          html += CC._renderRepeatableFromRegistry(fkey, value || [], field.item_fields, prefix);
        } else if (ftype === 'image') {
          html += CC._renderImageField(fpath, value || '');
        } else if (ftype === 'textarea') {
          html += '<div class="vbb-cc-field"><label>' + field.label + '</label><textarea data-path="' + fpath + '" placeholder="' + (field.placeholder || '') + '">' + CC.escAttr(value || '') + '</textarea></div>';
        } else if (ftype === 'select') {
          var opts = field.options || {};
          var htmlOpts = '';
          for (var ok in opts) {
            htmlOpts += '<option value="' + ok + '"' + (value === ok ? ' selected' : '') + '>' + opts[ok] + '</option>';
          }
          html += '<div class="vbb-cc-field"><label>' + field.label + '</label><select data-path="' + fpath + '">' + htmlOpts + '</select></div>';
        } else if (ftype === 'checkbox') {
          html += '<div class="vbb-cc-field"><label class="vbb-cc-toggle">' +
            '<input type="checkbox" data-path="' + fpath + '" data-boolean="1"' + (value ? ' checked' : '') + '>' +
            '<span class="vbb-cc-toggle-track"></span>' +
            '<span class="vbb-cc-toggle-label">' + field.label + '</span>' +
            '</label></div>';
        } else if (ftype === 'color') {
          html += '<div class="vbb-cc-field"><label>' + field.label + '</label>' +
            '<div class="vbb-cc-color-swatch">' +
            '<input type="color" data-path="' + fpath + '" value="' + CC._validateColor(value || '') + '">' +
            '<input type="text" class="vbb-cc-hex-input" value="' + (value || '') + '" data-path="' + fpath + '" pattern="^#[0-9a-fA-F]{6}$" maxlength="7">' +
            '</div></div>';
        } else if (ftype === 'number') {
          html += '<div class="vbb-cc-field"><label>' + field.label + '</label><input type="number" data-path="' + fpath + '" value="' + CC.escAttr(value !== undefined ? value : '') + '" /></div>';
        } else {
          var inputType = (ftype === 'url') ? 'url' : 'text';
          html += '<div class="vbb-cc-field"><label>' + field.label + '</label><input type="' + inputType + '" data-path="' + fpath + '" value="' + CC.escAttr(value || '') + '" placeholder="' + (field.placeholder || '') + '" /></div>';
        }
      }

      // Effects selector if block supports multiple effects.
      if (def.effects && def.effects.length > 1) {
        var currentEffect = block.effect || 'none';
        html += '<div class="vbb-cc-field"><label>Effect</label><select data-path="' + prefix + '.effect">';
        for (var ei = 0; ei < def.effects.length; ei++) {
          var eVal = def.effects[ei];
          var eLabels = { none: 'Sin efecto', fade: 'Fade In', 'slide-up': 'Slide Up', zoom: 'Zoom In', flip: 'Flip' };
          html += '<option value="' + eVal + '"' + (currentEffect === eVal ? ' selected' : '') + '>' + (eLabels[eVal] || eVal) + '</option>';
        }
        html += '</select></div>';
      }

      return html;
    },

    /**
     * Render repeatable items from registry field definitions.
     * @param {string} blockKey
     * @param {Array} items
     * @param {Array} itemFields
     * @param {string} prefix
     * @returns {string} HTML
     */
    _renderRepeatableFromRegistry: function (blockKey, items, itemFields, prefix) {
      var html = '';
      var itemPrefix = prefix + '.' + blockKey + '.items';
      var fieldsJson = JSON.stringify(itemFields);

      html += '<div class="vbb-cc-repeatable" data-block-key="' + blockKey + '">';
      html += '<div class="vbb-cc-repeatable-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">';
      html += '<h4 style="margin:0;font-size:0.9rem;">Items (' + (items && items.length ? items.length : 0) + ')</h4>';

      var fieldsJsonAttr = fieldsJson.replace(/"/g, '&quot;');
      html += '<button type="button" class="button vbb-cc-add-item" data-block-key="' + blockKey + '" data-prefix="' + itemPrefix + '" data-fields="' + fieldsJsonAttr + '" style="font-size:0.8rem;padding:4px 10px;">+ Añadir item</button>';
      html += '</div>';

      html += '<div class="vbb-cc-repeatable-list" style="display:flex;flex-direction:column;gap:12px;">';

      if (items && items.length > 0) {
        for (var ii = 0; ii < items.length; ii++) {
          var itemTitle = items[ii].title || items[ii].name || 'Item ' + (ii + 1);
          html += '<div class="vbb-cc-repeatable-item" style="background:#fafafa;border:1px solid #e0e0e0;border-radius:8px;padding:12px;position:relative;">';
          html += '<div class="vbb-cc-repeatable-item-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;padding-bottom:8px;border-bottom:1px solid #e0e0e0;">';
          html += '<div style="display:flex;align-items:center;gap:8px;">';
          html += '<span class="vbb-cc-drag-handle" style="cursor:grab;padding:4px;color:#888;">⠿</span>';
          html += '<span style="font-weight:600;font-size:0.85rem;">' + itemTitle + '</span>';
          html += '</div>';
          html += '<div style="display:flex;gap:4px;">';
          html += '<button class="vbb-cc-remove-item" data-index="' + ii + '" data-prefix="' + itemPrefix + '" title="Eliminar" style="background:none;border:none;cursor:pointer;padding:4px;color:#dc3232;">✕</button>';
          html += '</div></div>';

          html += '<div class="vbb-cc-repeatable-item-fields">';
          for (var fi = 0; fi < itemFields.length; fi++) {
            var fdef = itemFields[fi];
            var ikey = fdef.key;
            var ipath = itemPrefix + '.' + ii + '.' + ikey;
            var ival = items[ii] && items[ii][ikey] !== undefined ? items[ii][ikey] : fdef.default;

            if (fdef.type === 'image') {
              html += CC._renderImageField(ipath, ival || '');
            } else if (fdef.type === 'textarea') {
              html += '<div class="vbb-cc-field"><label>' + fdef.label + '</label><textarea data-path="' + ipath + '" placeholder="' + (fdef.placeholder || '') + '">' + CC.escAttr(ival || '') + '</textarea></div>';
            } else if (fdef.type === 'select') {
              var opts = fdef.options || {};
              var optHtml = '';
              for (var ok in opts) {
                optHtml += '<option value="' + ok + '"' + (ival === ok ? ' selected' : '') + '>' + opts[ok] + '</option>';
              }
              html += '<div class="vbb-cc-field"><label>' + fdef.label + '</label><select data-path="' + ipath + '">' + optHtml + '</select></div>';
            } else if (fdef.type === 'checkbox') {
              html += '<div class="vbb-cc-field"><label class="vbb-cc-toggle">' +
                '<input type="checkbox" data-path="' + ipath + '" data-boolean="1"' + (ival ? ' checked' : '') + '>' +
                '<span class="vbb-cc-toggle-track"></span>' +
                '<span class="vbb-cc-toggle-label">' + fdef.label + '</span>' +
                '</label></div>';
            } else if (fdef.type === 'color') {
              html += '<div class="vbb-cc-field"><label>' + fdef.label + '</label>' +
                '<div class="vbb-cc-color-swatch">' +
                '<input type="color" data-path="' + ipath + '" value="' + CC._validateColor(ival || '') + '">' +
                '<input type="text" class="vbb-cc-hex-input" value="' + (ival || '') + '" data-path="' + ipath + '" pattern="^#[0-9a-fA-F]{6}$" maxlength="7">' +
                '</div></div>';
            } else {
              html += '<div class="vbb-cc-field"><label>' + fdef.label + '</label><input type="' + (fdef.type === 'url' ? 'url' : 'text') + '" data-path="' + ipath + '" value="' + CC.escAttr(ival || '') + '" placeholder="' + (fdef.placeholder || '') + '" /></div>';
            }
          }
          html += '</div></div>';
        }
      } else {
        html += '<p class="description" style="text-align:center;padding:20px;color:#888;">No hay items. Click "Añadir item" para crear el primero.</p>';
      }

      html += '</div>'; // .vbb-cc-repeatable-list
      html += '</div>'; // .vbb-cc-repeatable

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

    renderPresetSelector: function () {
      var presets = CC.state.presets;
      var keys = Object.keys(presets);
      if (keys.length === 0) {
        return '<p class="description">No presets available. Add JSON preset files to <code>config/presets/</code>.</p>';
      }
      var html = '<div class="vbb-cc-preset-selector">';
      html += '<select id="vbb-cc-preset-select">';
      html += '<option value="">Select a preset\u2026</option>';
      for (var i = 0; i < keys.length; i++) {
        var key = keys[i];
        var name = presets[key].name || key;
        html += '<option value="' + CC.escAttr(key) + '">' + CC.escAttr(name) + '</option>';
      }
      html += '</select>';
      html += '<button class="vbb-cc-preset-apply" id="vbb-cc-preset-apply">Apply</button>';
      html += '</div>';
      return html;
    },

    /* ── Client Briefing Form ───────────────── */

    renderBriefingForm: function () {
      var briefingData = CC.state.briefingData || {};
      var activeTab = briefingData._activeTab || 'branding';
      var tabs = [
        { key: 'branding', label: 'Branding' },
        { key: 'architecture', label: 'Architecture' },
        { key: 'content', label: 'Content' },
        { key: 'style', label: 'Style' },
      ];

      var html = '<div class="vbb-cc-briefing">';

      // Tab navigation
      html += '<div class="vbb-cc-briefing-tabs" style="display:flex;gap:0;margin-bottom:16px;border-bottom:2px solid #e0e0e0;">';
      for (var ti = 0; ti < tabs.length; ti++) {
        var t = tabs[ti];
        var activeClass = t.key === activeTab ? ' vbb-cc-briefing-tab--active' : '';
        html += '<button class="vbb-cc-briefing-tab' + activeClass + '" data-briefing-tab="' + t.key + '" style="padding:8px 16px;border:none;background:none;cursor:pointer;font-weight:' + (t.key === activeTab ? '600' : '400') + ';border-bottom:' + (t.key === activeTab ? '2px solid #2271b1' : '2px solid transparent') + ';margin-bottom:-2px;color:' + (t.key === activeTab ? '#2271b1' : '#555') + ';">' + t.label + '</button>';
      }
      html += '</div>';

      // Tab panels
      html += '<div class="vbb-cc-briefing-panels">';

      // Tab: Branding
      html += '<div class="vbb-cc-briefing-panel" data-briefing-panel="branding"' + (activeTab === 'branding' ? '' : ' style="display:none"') + '>';
      html += '<div class="vbb-cc-field"><label>Site Name *</label><input type="text" class="vbb-cc-briefing-input" data-briefing-field="siteName" value="' + CC.escAttr(briefingData.siteName || '') + '" placeholder="e.g. Acme Corp"></div>';
      html += '<div class="vbb-cc-field"><label>Tagline</label><input type="text" class="vbb-cc-briefing-input" data-briefing-field="tagline" value="' + CC.escAttr(briefingData.tagline || '') + '" placeholder="e.g. Building the future"></div>';
      html += '<div class="vbb-cc-field"><label>Primary Color</label><input type="color" class="vbb-cc-briefing-input" data-briefing-field="primaryColor" value="' + (briefingData.primaryColor || '#1a365d') + '"></div>';
      html += '<div class="vbb-cc-field"><label>Secondary Color</label><input type="color" class="vbb-cc-briefing-input" data-briefing-field="secondaryColor" value="' + (briefingData.secondaryColor || '#e2e8f0') + '"></div>';
      html += '<div class="vbb-cc-field"><label>Accent Color</label><input type="color" class="vbb-cc-briefing-input" data-briefing-field="accentColor" value="' + (briefingData.accentColor || '#3b82f6') + '"></div>';
      html += '</div>';

      // Tab: Architecture (Pages & Sections)
      html += '<div class="vbb-cc-briefing-panel" data-briefing-panel="architecture"' + (activeTab === 'architecture' ? '' : ' style="display:none"') + '>';
      html += '<div class="vbb-cc-briefing-pages">';
      var pages = briefingData.pages || [''];
      for (var pi = 0; pi < pages.length; pi++) {
        html += '<div class="vbb-cc-briefing-page-row" style="display:flex;gap:8px;margin-bottom:8px;">';
        html += '<input type="text" class="vbb-cc-briefing-input vbb-cc-briefing-page-input" data-briefing-field="page_' + pi + '" value="' + CC.escAttr(pages[pi]) + '" placeholder="Page name (e.g. Home)" style="flex:1;">';
        html += '<button class="button vbb-cc-briefing-remove-page" data-index="' + pi + '" style="' + (pages.length > 1 ? '' : 'display:none') + '">\u2715</button>';
        html += '</div>';
      }
      html += '</div>';
      html += '<button class="button vbb-cc-briefing-add-page" style="margin-top:4px;">+ Add Page</button>';
      html += '</div>';

      // Tab: Content
      html += '<div class="vbb-cc-briefing-panel" data-briefing-panel="content"' + (activeTab === 'content' ? '' : ' style="display:none"') + '>';
      html += '<div class="vbb-cc-field"><label>Services (comma separated)</label><input type="text" class="vbb-cc-briefing-input" data-briefing-field="services" value="' + CC.escAttr(briefingData.services || '') + '" placeholder="e.g. Web Design, SEO, Branding"></div>';
      html += '<div class="vbb-cc-field"><label>Team Members (comma separated)</label><input type="text" class="vbb-cc-briefing-input" data-briefing-field="team" value="' + CC.escAttr(briefingData.team || '') + '" placeholder="e.g. Jane Doe, John Smith"></div>';
      html += '<div class="vbb-cc-field"><label>Target Audience</label><textarea class="vbb-cc-briefing-input" data-briefing-field="audience" rows="3" placeholder="Describe the target audience\u2026">' + CC.escAttr(briefingData.audience || '') + '</textarea></div>';
      html += '<div class="vbb-cc-field"><label>Key Message</label><textarea class="vbb-cc-briefing-input" data-briefing-field="message" rows="2" placeholder="Main message to communicate\u2026">' + CC.escAttr(briefingData.message || '') + '</textarea></div>';
      html += '</div>';

      // Tab: Style
      html += '<div class="vbb-cc-briefing-panel" data-briefing-panel="style"' + (activeTab === 'style' ? '' : ' style="display:none"') + '>';
      html += '<div class="vbb-cc-field"><label>Heading Font</label><input type="text" class="vbb-cc-briefing-input" data-briefing-field="headingFont" value="' + CC.escAttr(briefingData.headingFont || 'Inter') + '" placeholder="e.g. Inter"></div>';
      html += '<div class="vbb-cc-field"><label>Body Font</label><input type="text" class="vbb-cc-briefing-input" data-briefing-field="bodyFont" value="' + CC.escAttr(briefingData.bodyFont || 'Inter') + '" placeholder="e.g. Inter"></div>';
      html += '<div class="vbb-cc-field"><label>Mood / Vibe</label><select class="vbb-cc-briefing-input" data-briefing-field="mood">' +
        '<option value="professional"' + (briefingData.mood === 'professional' ? ' selected' : '') + '>Professional</option>' +
        '<option value="playful"' + (briefingData.mood === 'playful' ? ' selected' : '') + '>Playful</option>' +
        '<option value="minimal"' + (briefingData.mood === 'minimal' ? ' selected' : '') + '>Minimal</option>' +
        '<option value="luxury"' + (briefingData.mood === 'luxury' ? ' selected' : '') + '>Luxury</option>' +
        '<option value="bold"' + (briefingData.mood === 'bold' ? ' selected' : '') + '>Bold</option>' +
      '</select></div>';
      html += '<div class="vbb-cc-field"><label>Notes</label><textarea class="vbb-cc-briefing-input" data-briefing-field="notes" rows="3" placeholder="Any additional notes\u2026">' + CC.escAttr(briefingData.notes || '') + '</textarea></div>';
      html += '</div>';

      html += '</div>'; // .vbb-cc-briefing-panels

      // Send button
      html += '<div style="margin-top:16px;display:flex;gap:8px;align-items:center;">';
      html += '<button class="button button-primary" id="vbb-cc-send-briefing">Send to Agency Hub</button>';
      html += '<span id="vbb-cc-briefing-status" style="font-size:0.85rem;color:#666;"></span>';
      html += '</div>';

      html += '</div>'; // .vbb-cc-briefing

      return html;
    },

    applyPreset: function () {
      var select = CC.el.presetSelect;
      if (!select) {
        select = document.getElementById('vbb-cc-preset-select');
        CC.el.presetSelect = select;
      }
      if (!select || !select.value) {
        CC.showToast('Please select a preset first.', 'info', 2000);
        return;
      }
      var key = select.value;
      var preset = CC.state.presets[key];
      if (!preset || !preset.settings) {
        CC.showToast('Preset data not found.', 'error', 3000);
        return;
      }

      // Merge preset settings with current settings (preserve per-page specific keys)
      var merged = CC._deepMergeSettings(CC.state.settings, preset.settings);
      CC.state.settings = merged;

      // Re-render cards with new settings
      CC.renderCards();

      // Save to server via debounced save
      CC.debouncedSave();

      // Refresh preview with new CSS vars
      var cssVars = CC.buildCssVars();
      if (cssVars) {
        CC.postMessage({ type: 'vbb:css-vars', styleTag: cssVars });
      }

      CC.showToast('Preset "' + (preset.name || key) + '" applied.', 'success', 3000);
    },

    _deepMergeSettings: function (base, override) {
      var result = {};
      for (var key in base) {
        if (base.hasOwnProperty(key)) {
          result[key] = base[key];
        }
      }
      for (var key in override) {
        if (override.hasOwnProperty(key)) {
          if (typeof override[key] === 'object' && override[key] !== null &&
              typeof result[key] === 'object' && result[key] !== null &&
              !Array.isArray(override[key])) {
            result[key] = CC._deepMergeSettings(result[key], override[key]);
          } else {
            result[key] = override[key];
          }
        }
      }
      return result;
    },

    renderSiteConfig: function (s) {
      var types = [
        { value: 'landing', label: 'Landing Page (One Page)' },
        { value: 'multi', label: 'Multi-page Website' },
      ];
      var config = s.siteConfig || {};
      return (
        '<div class="vbb-cc-field"><label>Site Type</label><select data-path="siteConfig.type">' +
        CC.buildOptions(types, config.type || 'landing') +
        '</select></div>'
      );
    },

    renderMenuSettings: function (s) {
      var types = [
        { value: 'standard', label: 'Standard' },
        { value: 'hamburger', label: 'Hamburger' },
        { value: 'sticky', label: 'Sticky Header' },
      ];
      var styles = [
        { value: 'modern', label: 'Modern' },
        { value: 'minimal', label: 'Minimal' },
        { value: 'classic', label: 'Classic' },
        { value: 'pill', label: 'Pill' },
      ];
      var menu = s.menuConfig || {};
      var cta = menu.ctaButton || {};
      var topBar = s.topBar || {};
      return (
        /* ── Menu Type & Style ── */
        '<div class="vbb-cc-field"><label>Menu Type</label><select data-path="menuConfig.type">' +
        CC.buildOptions(types, menu.type || 'standard') +
        '</select></div>' +
        '<div class="vbb-cc-field"><label>Menu Style</label><select data-path="menuConfig.style">' +
        CC.buildOptions(styles, menu.style || 'modern') +
        '</select></div>' +
        /* ── Menu Colors ── */
        '<hr style="margin:12px 0;border:none;border-top:1px solid var(--vbb-admin-border,#ddd)">' +
        '<h3 style="margin:0 0 8px;font-size:13px;color:var(--vbb-admin-text-secondary,#667085)">Menu Colors</h3>' +
        '<div class="vbb-cc-field"><label>Menu Background</label><input type="color" data-path="menuConfig.bgColor" value="' +
        CC.escAttr(menu.bgColor || '#ffffff') +
        '"></div>' +
        '<div class="vbb-cc-field"><label>Menu Text Color</label><input type="color" data-path="menuConfig.textColor" value="' +
        CC.escAttr(menu.textColor || '#000000') +
        '"></div>' +
        '<div class="vbb-cc-field"><label>Dark Toggle Background</label><input type="color" data-path="menuConfig.darkBtnBg" value="' +
        CC.escAttr(menu.darkBtnBg || '#ffffff') +
        '"></div>' +
        '<div class="vbb-cc-field"><label>Dark Toggle Icon</label><input type="color" data-path="menuConfig.darkBtnText" value="' +
        CC.escAttr(menu.darkBtnText || '#000000') +
        '"></div>' +
        /* ── CTA Button ── */
        '<hr style="margin:12px 0;border:none;border-top:1px solid var(--vbb-admin-border,#ddd)">' +
        '<h3 style="margin:0 0 8px;font-size:13px;color:var(--vbb-admin-text-secondary,#667085)">CTA Button</h3>' +
        '<label class="vbb-cc-field vbb-cc-field--inline" style="display:flex;align-items:center;gap:8px">' +
        '<input type="checkbox" data-path="menuConfig.ctaButton.enabled"' + (cta.enabled ? ' checked' : '') + '> Enable CTA</label>' +
        '<div class="vbb-cc-field"><label>Button Text</label><input type="text" data-path="menuConfig.ctaButton.text" value="' +
        CC.escAttr(cta.text || '') + '" placeholder="Contacto"></div>' +
        '<div class="vbb-cc-field"><label>Button URL</label><input type="text" data-path="menuConfig.ctaButton.url" value="' +
        CC.escAttr(cta.url || '') + '" placeholder="/contacto"></div>' +
        '<div class="vbb-cc-field"><label>Button Background</label><input type="color" data-path="menuConfig.ctaButton.bgColor" value="' +
        CC.escAttr(cta.bgColor || '#2c5f2d') +
        '"></div>' +
        '<div class="vbb-cc-field"><label>Button Text Color</label><input type="color" data-path="menuConfig.ctaButton.textColor" value="' +
        CC.escAttr(cta.textColor || '#ffffff') +
        '"></div>' +
        /* ── Top Bar ── */
        '<hr style="margin:12px 0;border:none;border-top:1px solid var(--vbb-admin-border,#ddd)">' +
        '<h3 style="margin:0 0 8px;font-size:13px;color:var(--vbb-admin-text-secondary,#667085)">Top Bar</h3>' +
        '<label class="vbb-cc-field vbb-cc-field--inline" style="display:flex;align-items:center;gap:8px">' +
        '<input type="checkbox" data-path="topBar.enabled"' + (topBar.enabled ? ' checked' : '') + '> Enable Top Bar</label>' +
        '<div class="vbb-cc-field"><label>Item 1 (texto)</label><input type="text" data-path="topBar.info1.text" value="' +
        CC.escAttr((topBar.info1 && topBar.info1.text) || '') + '" placeholder="Lun–Vie 9:00–18:00"></div>' +
        '<div class="vbb-cc-field" style="margin-top:-6px"><label>Link 1 (opcional)</label><input type="text" data-path="topBar.info1.link" value="' +
        CC.escAttr((topBar.info1 && topBar.info1.link) || '') + '" placeholder="mailto:... / tel:... / https://..."></div>' +
        '<div class="vbb-cc-field"><label>Item 2 (email)</label><input type="text" data-path="topBar.info2.text" value="' +
        CC.escAttr((topBar.info2 && topBar.info2.text) || '') + '" placeholder="hola@ejemplo.com"></div>' +
        '<div class="vbb-cc-field" style="margin-top:-6px"><label>Link 2 (opcional)</label><input type="text" data-path="topBar.info2.link" value="' +
        CC.escAttr((topBar.info2 && topBar.info2.link) || '') + '" placeholder="mailto:hola@ejemplo.com"></div>' +
        '<div class="vbb-cc-field"><label>Item 3 (teléfono)</label><input type="text" data-path="topBar.info3.text" value="' +
        CC.escAttr((topBar.info3 && topBar.info3.text) || '') + '" placeholder="+54 11 5555-5555"></div>' +
        '<div class="vbb-cc-field" style="margin-top:-6px"><label>Link 3 (opcional)</label><input type="text" data-path="topBar.info3.link" value="' +
        CC.escAttr((topBar.info3 && topBar.info3.link) || '') + '" placeholder="tel:+541155555555"></div>' +
        '<div class="vbb-cc-field"><label>Facebook URL</label><input type="text" data-path="topBar.socialFacebook" value="' +
        CC.escAttr(topBar.socialFacebook || '') + '" placeholder="https://facebook.com/..."></div>' +
        '<div class="vbb-cc-field"><label>Instagram URL</label><input type="text" data-path="topBar.socialInstagram" value="' +
        CC.escAttr(topBar.socialInstagram || '') + '" placeholder="https://instagram.com/..."></div>' +
        '<div class="vbb-cc-field"><label>LinkedIn URL</label><input type="text" data-path="topBar.socialLinkedin" value="' +
        CC.escAttr(topBar.socialLinkedin || '') + '" placeholder="https://linkedin.com/..."></div>' +
        '<div class="vbb-cc-field"><label>Top Bar Background</label><input type="color" data-path="topBar.bgColor" value="' +
        CC.escAttr(topBar.bgColor || '#1a1a2e') +
        '"></div>' +
        '<div class="vbb-cc-field"><label>Top Bar Text Color</label><input type="color" data-path="topBar.textColor" value="' +
        CC.escAttr(topBar.textColor || '#ffffff') +
        '"></div>'
      );
    },

    /* ── Menu Editor ─────────────────────────── */

    renderMenuEditor: function () {
      var items = CC.state.menuItems;
      var html = '<div class="vbb-cc-menu-editor">';
      html += '<div class="vbb-cc-menu-items">';
      html += CC.renderMenuItemsList(items, 0, '');
      html += '</div>';
      html += '<div class="vbb-cc-menu-actions" style="margin-top:12px;">';
      html += '<button class="button" id="vbb-cc-menu-add">+ Add Menu Item</button>';
      html += '<button class="button button-primary" id="vbb-cc-menu-save" style="margin-left:8px;">Save Menu</button>';
      html += '</div>';
      html += '</div>';
      return html;
    },

    renderMenuItemsList: function (items, depth, prefix) {
      if (!items || !items.length) {
        return '<div class="vbb-cc-empty-state"><div class="vbb-cc-empty-state-icon">\uD83D\uDCCB</div><h3>No menu items yet</h3><p>Click "+ Add Menu Item" to start building your site navigation.</p></div>';
      }
      var html = '<ul class="vbb-cc-menu-list"' + (depth > 0 ? ' style="margin-left:24px;"' : '') + '>';
      for (var i = 0; i < items.length; i++) {
        var item = items[i];
        var key = prefix + i;
        var depthClass = depth > 0 ? ' vbb-cc-menu-item-child' : '';
        html += '<li class="vbb-cc-menu-item' + depthClass + '" data-menu-key="' + key + '">';
        html += '<div class="vbb-cc-menu-item-row">';

        // Drag handle (visual only — up/down buttons for reorder)
        html += '<span class="vbb-cc-menu-drag" title="Drag to reorder">⠿</span>';

        // Label
        html += '<div class="vbb-cc-menu-field vbb-cc-menu-label">';
        html += '<input type="text" data-menu-key="' + key + '" data-menu-field="label" value="' + CC.escAttr(item.label || '') + '" placeholder="Menu label">';
        html += '</div>';

        // Type selector
        html += '<div class="vbb-cc-menu-field vbb-cc-menu-type">';
        html += '<select data-menu-key="' + key + '" data-menu-field="type">';
        var types = [
          { value: 'custom', label: 'Custom' },
          { value: 'page', label: 'Page' },
        ];
        html += CC.buildOptions(types, item.type || 'custom');
        html += '</select>';
        html += '</div>';

        // Target (page dropdown or URL)
        html += '<div class="vbb-cc-menu-field vbb-cc-menu-target">';
        if ((item.type || 'custom') === 'page') {
          html += '<select data-menu-key="' + key + '" data-menu-field="targetPageId">';
          html += '<option value="">Select page…</option>';
          var pages = CC.state.availablePages || [];
          for (var p = 0; p < pages.length; p++) {
            var sel = (pages[p].id == item.targetPageId) ? ' selected' : '';
            html += '<option value="' + pages[p].id + '"' + sel + '>' + CC.escAttr(pages[p].title) + '</option>';
          }
          html += '</select>';
        } else {
          html += '<input type="text" data-menu-key="' + key + '" data-menu-field="url" value="' + CC.escAttr(item.url || '') + '" placeholder="https://…">';
        }
        html += '</div>';

        // Child toggle (add child button)
        html += '<button class="button vbb-cc-menu-add-child" data-menu-key="' + key + '" title="Add child item">+</button>';

        // Move up
        if (i > 0) {
          html += '<button class="button vbb-cc-menu-move-up" data-menu-key="' + key + '" title="Move up">↑</button>';
        }

        // Delete
        html += '<button class="button vbb-cc-menu-delete" data-menu-key="' + key + '" title="Delete item">✕</button>';

        html += '</div>'; // .vbb-cc-menu-item-row

        // Render children recursively
        if (item.children && item.children.length > 0) {
          html += CC.renderMenuItemsList(item.children, depth + 1, key + '.children.');
        }

        html += '</li>';
      }
      html += '</ul>';
      return html;
    },

    /* ── Menu actions ────────────────────── */

    addMenuItem: function () {
      var newItem = {
        id: 'menu_' + Date.now(),
        label: 'New Item',
        type: 'page',
        url: '',
        targetPageId: 0,
        children: [],
      };
      CC.state.menuItems.push(newItem);
      CC._reRenderMenu();
    },

    addMenuChild: function (parentKey) {
      var newChild = {
        id: 'menu_' + Date.now(),
        label: 'New Sub Item',
        type: 'custom',
        url: '',
        targetPageId: 0,
        children: [],
      };
      var parts = parentKey.split('.');
      var parent = CC._resolveMenuKey(parts);
      if (parent) {
        if (!parent.children) parent.children = [];
        parent.children.push(newChild);
        CC._reRenderMenu();
      }
    },

    deleteMenuItem: function (key) {
      CC.showConfirmToast(
        'Delete this menu item?',
        function () {
          var parts = key.split('.');
          var parentKey = parts.slice(0, -1);
          var idx = parseInt(parts[parts.length - 1], 10);
          // _resolveMenuKey can return an array (the children[] itself)
          // or an object (the parent item with .children prop)
          var resolved = parentKey.length > 0 ? CC._resolveMenuKey(parentKey) : null;
          var target;
          if (!resolved) {
            target = CC.state.menuItems;
          } else if (Array.isArray(resolved)) {
            target = resolved;
          } else {
            target = resolved.children || [];
          }
          if (idx >= 0 && idx < target.length) {
            target.splice(idx, 1);
            CC._reRenderMenu();
          }
        }
      );
    },

    moveMenuItem: function (key, direction) {
      var parts = key.split('.');
      var idx = parseInt(parts[parts.length - 1], 10);
      var parentKey = parts.slice(0, -1);
      var parent = parentKey.length > 0 ? CC._resolveMenuKey(parentKey) : null;
      var target = parent ? (parent.children || []) : CC.state.menuItems;

      var newIdx = idx + direction;
      if (newIdx < 0 || newIdx >= target.length) return;

      var item = target.splice(idx, 1)[0];
      target.splice(newIdx, 0, item);
      CC._reRenderMenu();
    },

    _resolveMenuKey: function (parts) {
      var current = CC.state.menuItems;
      for (var i = 0; i < parts.length; i++) {
        var part = parts[i];
        if (part === 'children') continue;
        var idx = parseInt(part, 10);
        if (!isNaN(idx) && current && idx < current.length) {
          current = current[idx];
          // If there's a next part and it's 'children', step into children
          if (i + 1 < parts.length && parts[i + 1] === 'children') {
            if (!current.children) current.children = [];
            current = current.children;
            i++; // skip 'children' part
          }
        } else {
          return null;
        }
      }
      return current;
    },

    _reRenderMenu: function () {
      var cards = document.querySelectorAll('.vbb-cc-card');
      var menuCard = null;
      cards.forEach(function (card) {
        var h2 = card.querySelector('h2');
        if (h2 && h2.textContent === 'Menu Editor') {
          menuCard = card;
        }
      });
      if (menuCard) {
        var h2El  = menuCard.querySelector('h2');
        var desc  = menuCard.querySelector('.description');
        var items = CC.state.menuItems;
        var body  = '<div class="vbb-cc-menu-editor">' +
          '<div class="vbb-cc-menu-items">' +
          CC.renderMenuItemsList(items, 0, '') +
          '</div>' +
          '<div class="vbb-cc-menu-actions" style="margin-top:12px;">' +
          '<button class="button" id="vbb-cc-menu-add">+ Add Menu Item</button>' +
          '<button class="button button-primary" id="vbb-cc-menu-save" style="margin-left:8px;">Save Menu</button>' +
          '</div></div>';
        menuCard.innerHTML = (h2El ? h2El.outerHTML : '') +
          (desc ? desc.outerHTML : '') + body;
        CC.bindMenuEvents();
      }
    },

    /* ── Menu field change ───────────────── */

    _handleMenuChange: function (e) {
      var input = e.currentTarget;
      var key = input.getAttribute('data-menu-key');
      var field = input.getAttribute('data-menu-field');
      if (!key || !field) return;

      var parts = key.split('.');
      var idx = parseInt(parts[parts.length - 1], 10);
      var parentKey = parts.slice(0, -1);
      var parent = parentKey.length > 0 ? CC._resolveMenuKey(parentKey) : null;
      var target = parent ? (parent.children || []) : CC.state.menuItems;

      if (idx < 0 || idx >= target.length) return;

      var item = target[idx];

      if (field === 'label') {
        item.label = input.value;
      } else if (field === 'type') {
        item.type = input.value;
        // Clear the other field when switching
        if (item.type === 'page') {
          item.url = '';
        } else {
          item.targetPageId = 0;
        }
      } else if (field === 'url') {
        item.url = input.value;
      } else if (field === 'targetPageId') {
        item.targetPageId = parseInt(input.value, 10) || 0;
      }
    },

    /* ── Menu save ───────────────────────── */

    saveMenu: function (callback) {
      CC.showStatus('saving', 'Saving menu\u2026');

      CC.xhr(
        CC.state.ajaxUrl + 'menu',
        'PUT',
        { menuItems: CC.state.menuItems },
        function (data) {
          if (data && data.menuItems) {
            CC.state.menuItems = data.menuItems;
          }
          CC.showStatus('saved', 'Menu saved');
          // Menu is content — force full preview reload
          CC.refreshPreview(true);
          if (typeof callback === 'function') {
            callback(data);
          }
        },
        function () {
          CC.showStatus('error', 'Menu save failed');
        }
      );
    },

    debouncedMenuSave: function () {
      if (CC.debounceTimer) {
        clearTimeout(CC.debounceTimer);
      }
      CC.debounceTimer = setTimeout(function () {
        CC.saveMenu();
      }, CC.debounceDelay);
    },

    /* ── Menu event binding ──────────────── */

    bindMenuEvents: function () {
      // Add item button
      var addBtn = document.getElementById('vbb-cc-menu-add');
      if (addBtn) {
        addBtn.addEventListener('click', function (e) {
          e.preventDefault();
          CC.addMenuItem();
        });
      }

      // Save button
      var saveBtn = document.getElementById('vbb-cc-menu-save');
      if (saveBtn) {
        saveBtn.addEventListener('click', function (e) {
          e.preventDefault();
          CC.saveMenu();
        });
      }

      // Field changes (label, type, url, targetPageId)
      var fields = document.querySelectorAll('[data-menu-field]');
      fields.forEach(function (field) {
        field.addEventListener('change', CC._handleMenuChange);
        if (field.type === 'text') {
          field.addEventListener('input', CC.debouncedMenuSave);
        }
      });

      // Add child buttons
      var childBtns = document.querySelectorAll('.vbb-cc-menu-add-child');
      childBtns.forEach(function (btn) {
        btn.addEventListener('click', function (e) {
          e.preventDefault();
          var key = btn.getAttribute('data-menu-key');
          if (key) CC.addMenuChild(key);
        });
      });

      // Delete buttons
      var deleteBtns = document.querySelectorAll('.vbb-cc-menu-delete');
      deleteBtns.forEach(function (btn) {
        btn.addEventListener('click', function (e) {
          e.preventDefault();
          var key = btn.getAttribute('data-menu-key');
          if (key) CC.deleteMenuItem(key);
        });
      });

      // Move up buttons
      var moveUpBtns = document.querySelectorAll('.vbb-cc-menu-move-up');
      moveUpBtns.forEach(function (btn) {
        btn.addEventListener('click', function (e) {
          e.preventDefault();
          var key = btn.getAttribute('data-menu-key');
          if (key) CC.moveMenuItem(key, -1);
        });
      });

      // Type selector change — re-render target field
      var typeSelects = document.querySelectorAll('[data-menu-field="type"]');
      typeSelects.forEach(function (sel) {
        sel.addEventListener('change', function () {
          CC._reRenderMenu();
        });
      });
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
      // Re-bind preset apply button (recreated on each render)
      var presetApplyBtn = document.getElementById('vbb-cc-preset-apply');
      if (presetApplyBtn) {
        // Remove existing listener by cloning
        var newBtn = presetApplyBtn.cloneNode(true);
        presetApplyBtn.parentNode.replaceChild(newBtn, presetApplyBtn);
        newBtn.addEventListener('click', function (e) {
          e.preventDefault();
          var select = document.getElementById('vbb-cc-preset-select');
          CC.el.presetSelect = select;
          CC.applyPreset();
        });
        CC.el.presetApplyBtn = newBtn;
      }

      // Section audit: click to scroll to section in preview
      var sectionAudit = document.getElementById('vbb-cc-section-audit');
      if (sectionAudit) {
        sectionAudit.addEventListener('click', function (e) {
          var item = e.target.closest('.vbb-cc-section-item');
          if (!item) return;
          var sectionKey = item.getAttribute('data-section-key');
          if (sectionKey) {
            CC._scrollToSection(sectionKey);
          }
        });
      }

      var cards = document.querySelectorAll('.vbb-cc-card');

      cards.forEach(function (card) {
        // Text / select changes (including color change on blur)
        var inputs = card.querySelectorAll(
          'input[type="text"], select, input[type="color"], input[type="hidden"][data-path]'
        );
        inputs.forEach(function (input) {
          if (input.type === 'text') {
            input.addEventListener('input', CC._handleChange);
          } else {
            input.addEventListener('change', CC._handleChange);
          }
          // Color inputs: input event → preview only (no XHR)
          if (input.type === 'color') {
            input.addEventListener('input', CC._handleColorInput);
          }
        });

        // Checkbox toggles
        var checks = card.querySelectorAll(
          'input[type="checkbox"][data-path]'
        );
        checks.forEach(function (cb) {
          cb.addEventListener('change', CC._handleChange);
        });

        // Hex input sync with color input
        var hexInputs = card.querySelectorAll('.vbb-cc-hex-input');
        hexInputs.forEach(function (hexInput) {
          hexInput.addEventListener('change', function (e) {
            var val = e.currentTarget.value;
            // Validate hex format
            if (/^#[0-9a-fA-F]{6}$/.test(val)) {
              e.currentTarget.classList.remove('vbb-cc-hex-input--invalid');
              // Sync to corresponding color input
              var path = e.currentTarget.getAttribute('data-path');
              var colorInput = card.querySelector('input[type="color"][data-path="' + path + '"]');
              if (colorInput) {
                colorInput.value = val;
                // Trigger change event (save)
                var evt = document.createEvent('HTMLEvents');
                evt.initEvent('change', true, false);
                colorInput.dispatchEvent(evt);
              }
            } else {
              e.currentTarget.classList.add('vbb-cc-hex-input--invalid');
            }
          });
        });

        // Color input → hex sync (and preview update)
        var colorInputs = card.querySelectorAll('.vbb-cc-color-swatch input[type="color"]');
        colorInputs.forEach(function (colorInput) {
          colorInput.addEventListener('input', function (e) {
            var path = e.currentTarget.getAttribute('data-path');
            var hexInput = card.querySelector('.vbb-cc-hex-input[data-path="' + path + '"]');
            if (hexInput) {
              hexInput.value = e.currentTarget.value;
              hexInput.classList.remove('vbb-cc-hex-input--invalid');
            }
          });
        });

        // Copy button
        var copyBtns = card.querySelectorAll('.vbb-cc-copy-btn');
        copyBtns.forEach(function (btn) {
          btn.addEventListener('click', function (e) {
            e.preventDefault();
            var hex = btn.getAttribute('data-hex');
            if (!hex) return;
            // Use clipboard API with fallback
            if (navigator.clipboard && navigator.clipboard.writeText) {
              navigator.clipboard.writeText(hex).then(function () {
                CC._showCopyTooltip(btn);
              });
            } else {
              // Fallback: select text from a temporary input
              var temp = document.createElement('input');
              temp.value = hex;
              document.body.appendChild(temp);
              temp.select();
              document.execCommand('copy');
              document.body.removeChild(temp);
              CC._showCopyTooltip(btn);
            }
          });
        });

        // Style selector buttons
        var styleBtns = card.querySelectorAll('.vbb-cc-style-btn');
        styleBtns.forEach(function (btn) {
          btn.addEventListener('click', function (e) {
            e.preventDefault();
            var path = btn.getAttribute('data-path');
            var newStyle = btn.getAttribute('data-style');
            if (!path || !newStyle) return;

            // Read previous value from current state
            var keys = path.split('.');
            var cur = CC.state.settings;
            for (var i = 0; i < keys.length; i++) {
              if (cur && typeof cur === 'object') cur = cur[keys[i]];
            }
            var prevStyle = cur || 'A';
            if (newStyle === prevStyle) return;

            // Update state immediately (before save — so preview reflects it)
            CC._setNested(CC.state.settings, path, newStyle);

            // Show confirmation BEFORE saving (server still has old value)
            var isPerPage = !!CC.state.currentPageId;
            var msg = isPerPage
              ? 'Style change will regenerate this page. Any manual edits will be lost.'
              : 'This style change will regenerate ALL pages. Continue?';

            CC.showConfirmToast(msg,
              function () {
                // Confirm: save settings, then regenerate pages
                CC.saveSettings(function () {
                  CC.showStatus('saving', 'Regenerating\u2026');
                  var endpoint = isPerPage
                    ? CC.state.ajaxUrl + 'pages/' + CC.state.currentPageId + '/regenerate'
                    : CC.state.ajaxUrl + 'regenerate-pages';
                  CC.xhr(endpoint, 'POST', null, function () {
                    CC.showToast('Page regenerated with new style.', 'success');
                    CC.refreshPreview();
                  });
});
      
      // Media Library picker for hero background images
      var mediaBtns = card.querySelectorAll('.vbb-cc-media-btn');
      mediaBtns.forEach(function (btn) {
        btn.addEventListener('click', function (e) {
          e.preventDefault();
          var targetPath = btn.getAttribute('data-target'); // e.g. "blocks.hero"
          if (!targetPath) return;
          
          if (typeof wp === 'undefined' || !wp.media) {
            alert('WordPress Media Library not available. Make sure you are in wp-admin.');
            return;
          }
          
          var mediaFrame = wp.media({
            title: 'Select Background Image',
            button: { text: 'Use this image' },
            multiple: false,
            library: { type: 'image' }
          });
          
          mediaFrame.on('select', function () {
            var attachment = mediaFrame.state().get('selection').first().toJSON();
            var imageId = attachment.id;
            var imageUrl = attachment.url;
            
            // Update hidden inputs
            var idInput = card.querySelector('.vbb-cc-image-id[data-path="' + targetPath + '.image_id"]');
            var urlInput = card.querySelector('.vbb-cc-image-url[data-path="' + targetPath + '.image_url"]');
            var previewContainer = card.querySelector('.vbb-cc-field-image .vbb-cc-image-preview');
            
            if (idInput) {
              idInput.value = imageId;
              idInput.dispatchEvent(new Event('change', { bubbles: true }));
            }
            if (urlInput) {
              urlInput.value = imageUrl;
              urlInput.dispatchEvent(new Event('change', { bubbles: true }));
            }

            // Update preview
            if (previewContainer) {
              previewContainer.innerHTML = '<img src="' + imageUrl + '" style="max-width:100%;height:auto;border-radius:4px;border:1px solid #ddd;">';
            }
          });

          mediaFrame.open();
        });
      });

      // Clear image button
      var clearBtns = card.querySelectorAll('.vbb-cc-media-clear');
      clearBtns.forEach(function (btn) {
        btn.addEventListener('click', function (e) {
          e.preventDefault();
          var targetPath = btn.getAttribute('data-target');
          if (!targetPath) return;

          var idInput = card.querySelector('.vbb-cc-image-id[data-path="' + targetPath + '.image_id"]');
          var urlInput = card.querySelector('.vbb-cc-image-url[data-path="' + targetPath + '.image_url"]');
          var previewContainer = card.querySelector('.vbb-cc-field-image .vbb-cc-image-preview');

          if (idInput) {
            idInput.value = '';
            idInput.dispatchEvent(new Event('change', { bubbles: true }));
          }
          if (urlInput) {
            urlInput.value = '';
            urlInput.dispatchEvent(new Event('change', { bubbles: true }));
          }
          if (previewContainer) {
            previewContainer.innerHTML = '';
          }
        });
      });

      // Repeatable items: Add new item
      var addItemBtns = card.querySelectorAll('.vbb-cc-repeatable-add');
      addItemBtns.forEach(function (btn) {
        btn.addEventListener('click', function (e) {
          e.preventDefault();
          var path = btn.getAttribute('data-path'); // e.g. "blocks.servicesGrid.items"
          var fields = JSON.parse(btn.getAttribute('data-fields') || '[]');
          CC._addRepeatableItem(path, fields);
        });
      });

      // Repeatable items: Remove item
      var removeItemBtns = card.querySelectorAll('.vbb-cc-repeatable-remove');
      removeItemBtns.forEach(function (btn) {
        btn.addEventListener('click', function (e) {
          e.preventDefault();
          var path = btn.getAttribute('data-path'); // e.g. "blocks.servicesGrid.items"
          var index = parseInt(btn.getAttribute('data-index'), 10);
          CC._removeRepeatableItem(path, index);
        });
      });

      // Repeatable items: Move up/down
      var moveUpBtns = card.querySelectorAll('.vbb-cc-repeatable-move-up');
      moveUpBtns.forEach(function (btn) {
        btn.addEventListener('click', function (e) {
          e.preventDefault();
          var path = btn.getAttribute('data-path');
          var index = parseInt(btn.getAttribute('data-index'), 10);
          CC._moveRepeatableItem(path, index, -1);
        });
      });
      var moveDownBtns = card.querySelectorAll('.vbb-cc-repeatable-move-down');
      moveDownBtns.forEach(function (btn) {
        btn.addEventListener('click', function (e) {
          e.preventDefault();
          var path = btn.getAttribute('data-path');
          var index = parseInt(btn.getAttribute('data-index'), 10);
          CC._moveRepeatableItem(path, index, 1);
        });
      });

      // Repeatable items: Duplicate item
      var duplicateItemBtns = card.querySelectorAll('.vbb-cc-repeatable-duplicate');
      duplicateItemBtns.forEach(function (btn) {
        btn.addEventListener('click', function (e) {
          e.preventDefault();
          var path = btn.getAttribute('data-path');
          var index = parseInt(btn.getAttribute('data-index'), 10);
          CC._duplicateRepeatableItem(path, index);
        });
      });

      // Repeatable items: Drag & Drop reordering
      var dragItems = card.querySelectorAll('.vbb-cc-repeatable-item[draggable="true"]');
      var dragSrcEl = null;

      dragItems.forEach(function (item) {
        item.addEventListener('dragstart', function (e) {
          dragSrcEl = this;
          this.classList.add('dragging');
          e.dataTransfer.effectAllowed = 'move';
          e.dataTransfer.setData('text/plain', this.getAttribute('data-index'));
        });

        item.addEventListener('dragend', function () {
          this.classList.remove('dragging');
          // Remove all drag-over classes
          card.querySelectorAll('.vbb-cc-repeatable-item.drag-over').forEach(function (el) {
            el.classList.remove('drag-over');
          });
        });

        item.addEventListener('dragover', function (e) {
          e.preventDefault();
          e.dataTransfer.dropEffect = 'move';
          if (this !== dragSrcEl) {
            this.classList.add('drag-over');
          }
        });

        item.addEventListener('dragleave', function () {
          this.classList.remove('drag-over');
        });

        item.addEventListener('drop', function (e) {
          e.preventDefault();
          if (this !== dragSrcEl) {
            var fromIndex = parseInt(dragSrcEl.getAttribute('data-index'), 10);
            var toIndex = parseInt(this.getAttribute('data-index'), 10);
            var path = this.closest('.vbb-cc-repeatable').querySelector('.vbb-cc-repeatable-add').getAttribute('data-path');
            CC._moveRepeatableItem(path, fromIndex, toIndex - fromIndex);
          }
          this.classList.remove('drag-over');
        });
      });
    },
              function () {
                // Cancel: revert style by reloading old settings from server
                CC.loadSettings(CC.state.currentPageId);
              }
            );
          });
        });
      });
    },

    _showCopyTooltip: function (btn) {
      var tooltip = btn.querySelector('.vbb-cc-copy-tooltip');
      if (tooltip) {
        tooltip.classList.add('vbb-cc-copy-tooltip--visible');
        setTimeout(function () {
          tooltip.classList.remove('vbb-cc-copy-tooltip--visible');
        }, 1500);
      }
    },

    onPageChange: function (pageId) {
      CC.loadSettings(pageId);
      // Update the iframe preview to the specific page if applicable
      if (CC.el.iframe && pageId) {
        var pageUrl = window.location.origin + '/?p=' + pageId + '&vbb_preview=' + new Date().getTime() + '&vbb_no_admin=1&vbb_origin=' + encodeURIComponent(CC.previewOrigin);
        CC._showPreviewOverlay('loading');
        CC.el.iframe.src = pageUrl;
        // Keep state in sync so refreshPreview uses correct page
        CC.state.previewUrl = pageUrl;
      } else if (CC.el.iframe) {
        CC.refreshPreview();
      }
      CC._updatePreviewUrlDisplay();
    },

    _handleChange: function (e) {
      var input = e.currentTarget;
      var path = input.getAttribute('data-path');
      if (!path) return;

      var value;
      var isBool = input.getAttribute('data-boolean') === '1';

      if (input.type === 'checkbox') {
        value = input.checked;
        // Block toggle: show/hide expanded settings immediately
        if (path.match(/^blocks\.\w+\.enabled$/)) {
          CC._toggleBlockSettings(input, value);
        }
      } else if (input.type === 'color') {
        value = input.value;
        input.title = input.value; // Original behaviour preserved.
      } else {
        value = input.value;
      }

      // Smart debounce options based on input type
      var isColor = input.type === 'color' || path.indexOf('.colors.') !== -1;
      var isImmediate = input.type === 'checkbox' || path.indexOf('.enabled') !== -1;
      CC.onFieldChange(path, value, isBool);
    },

    _toggleBlockSettings: function (checkbox, enabled) {
      var item = checkbox.closest('.vbb-cc-block-item');
      if (!item) return;

      // Extract block key from path: "blocks.hero.enabled" → "hero"
      var parts = checkbox.getAttribute('data-path').split('.');
      var key = parts[1];

      var existing = item.querySelector('.vbb-cc-block-settings');
      if (enabled) {
        if (!existing) {
          var div = document.createElement('div');
          div.className = 'vbb-cc-block-settings';
          var block = CC.state.settings.blocks && CC.state.settings.blocks[key]
            ? CC.state.settings.blocks[key]
            : { enabled: true };
          div.innerHTML = CC.renderBlockSettings(key, block);
          item.appendChild(div);
          // Bind events on the newly added elements
          var newInputs = div.querySelectorAll(
            'input[type="text"], select, input[type="color"], input[type="checkbox"][data-path], input[type="hidden"]'
          );
          newInputs.forEach(function (el) {
            if (el.type === 'text' || el.type === 'checkbox') {
              el.addEventListener('input', CC._handleChange);
            } else if (el.type === 'hidden') {
              el.addEventListener('change', CC._handleChange);
            } else {
              el.addEventListener('change', CC._handleChange);
            }
            if (el.type === 'color') {
              el.addEventListener('input', CC._handleColorInput);
            }
          });
          // Re-bind hex inputs and color sync
          var hexInputs = div.querySelectorAll('.vbb-cc-hex-input');
          hexInputs.forEach(function (hexInput) {
            hexInput.addEventListener('change', function (e) {
              var val = e.currentTarget.value;
              if (/^#[0-9a-fA-F]{6}$/.test(val)) {
                e.currentTarget.classList.remove('vbb-cc-hex-input--invalid');
                var colorInput = div.querySelector('input[type="color"][data-path="' + e.currentTarget.getAttribute('data-path') + '"]');
                if (colorInput) {
                  colorInput.value = val;
                  var evt = new Event('change', { bubbles: true });
                  colorInput.dispatchEvent(evt);
                }
              } else {
                e.currentTarget.classList.add('vbb-cc-hex-input--invalid');
              }
            });
          });
          var colorSwatches = div.querySelectorAll('.vbb-cc-color-swatch input[type="color"]');
          colorSwatches.forEach(function (colorInput) {
            colorInput.addEventListener('input', function (e) {
              var cp = e.currentTarget.getAttribute('data-path');
              var hexIn = div.querySelector('.vbb-cc-hex-input[data-path="' + cp + '"]');
              if (hexIn) {
                hexIn.value = e.currentTarget.value;
                hexIn.classList.remove('vbb-cc-hex-input--invalid');
              }
            });
          });
          // Copy buttons
          var copyBtns = div.querySelectorAll('.vbb-cc-copy-btn');
          copyBtns.forEach(function (btn) {
            btn.addEventListener('click', function (e) {
              e.preventDefault();
              var hex = btn.getAttribute('data-hex');
              if (!hex) return;
              if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(hex);
              }
            });
});
          
          // Media Library picker for hero background images (re-bind for newly added elements)
          var newMediaBtns = div.querySelectorAll('.vbb-cc-media-btn');
          newMediaBtns.forEach(function (btn) {
            btn.addEventListener('click', function (e) {
              e.preventDefault();
              var targetPath = btn.getAttribute('data-target'); // e.g. "blocks.hero"
              if (!targetPath) return;
              
              if (typeof wp === 'undefined' || !wp.media) {
                alert('WordPress Media Library not available. Make sure you are in wp-admin.');
                return;
              }
              
              var mediaFrame = wp.media({
                title: 'Select Background Image',
                button: { text: 'Use this image' },
                multiple: false,
                library: { type: 'image' }
              });
              
              mediaFrame.on('select', function () {
                var attachment = mediaFrame.state().get('selection').first().toJSON();
                var imageId = attachment.id;
                var imageUrl = attachment.url;
                
                // Update hidden inputs
                var idInput = div.querySelector('.vbb-cc-image-id[data-path="' + targetPath + '.image_id"]');
                var urlInput = div.querySelector('.vbb-cc-image-url[data-path="' + targetPath + '.image_url"]');
                var previewContainer = div.querySelector('.vbb-cc-field-image .vbb-cc-image-preview');
                
            if (idInput) {
              idInput.value = imageId;
              idInput.dispatchEvent(new Event('change', { bubbles: true }));
            }
            if (urlInput) {
              urlInput.value = imageUrl;
              urlInput.dispatchEvent(new Event('change', { bubbles: true }));
            }

            // Update preview
            if (previewContainer) {
              previewContainer.innerHTML = '<img src="' + imageUrl + '" style="max-width:100%;height:auto;border-radius:4px;border:1px solid #ddd;">';
            }
          });

          mediaFrame.open();
        });
      });

      // Clear image button (re-bind for newly added elements)
      var newClearBtns = div.querySelectorAll('.vbb-cc-media-clear');
      newClearBtns.forEach(function (btn) {
        btn.addEventListener('click', function (e) {
          e.preventDefault();
          var targetPath = btn.getAttribute('data-target');
          if (!targetPath) return;

          var idInput = div.querySelector('.vbb-cc-image-id[data-path="' + targetPath + '.image_id"]');
          var urlInput = div.querySelector('.vbb-cc-image-url[data-path="' + targetPath + '.image_url"]');
          var previewContainer = div.querySelector('.vbb-cc-field-image .vbb-cc-image-preview');

          if (idInput) {
            idInput.value = '';
            idInput.dispatchEvent(new Event('change', { bubbles: true }));
          }
          if (urlInput) {
            urlInput.value = '';
            urlInput.dispatchEvent(new Event('change', { bubbles: true }));
          }
              if (previewContainer) {
                previewContainer.innerHTML = '';
              }
            });
          });
        }
      } else {
        if (existing) {
          existing.remove();
        }
      }
    },

    /* ── Repeatable Items Helpers ───────────────── */

    /**
     * Add a new item to a repeatable items array
     */
    addRepeatableItem: function (blockKey, itemData) {
      var path = 'blocks.' + blockKey + '.items';
      var items = CC._getNested(CC.state.settings, path) || [];
      items.push(itemData || {});
      CC._setNested(CC.state.settings, path, items);
      CC.debouncedSave();
      CC.renderCards(); // Re-render to show new item
    },

    /**
     * Remove an item from a repeatable items array
     */
    removeRepeatableItem: function (blockKey, index) {
      var path = 'blocks.' + blockKey + '.items';
      var items = CC._getNested(CC.state.settings, path) || [];
      if (index >= 0 && index < items.length) {
        items.splice(index, 1);
        CC._setNested(CC.state.settings, path, items);
        CC.debouncedSave();
        CC.renderCards();
      }
    },

    /**
     * Move an item up/down in a repeatable items array
     */
    moveRepeatableItem: function (blockKey, index, direction) {
      var path = 'blocks.' + blockKey + '.items';
      var items = CC._getNested(CC.state.settings, path) || [];
      var newIndex = index + direction;
      if (newIndex >= 0 && newIndex < items.length) {
        var temp = items[index];
        items[index] = items[newIndex];
        items[newIndex] = temp;
        CC._setNested(CC.state.settings, path, items);
        CC.debouncedSave();
        CC.renderCards();
      }
    },

    /**
     * Duplicate an item in a repeatable items array
     */
    duplicateRepeatableItem: function (blockKey, index) {
      var path = 'blocks.' + blockKey + '.items';
      var items = CC._getNested(CC.state.settings, path) || [];
      if (index >= 0 && index < items.length) {
        var newItem = JSON.parse(JSON.stringify(items[index])); // Deep clone
        items.splice(index + 1, 0, newItem);
        CC._setNested(CC.state.settings, path, items);
        CC.debouncedSave();
        CC.renderCards();
      }
    },

    /* ── Preview ─────────────────────────────── */

    /* ── Zoom Toggle ──────────────────────────── */

    /* ── Private helpers for repeatable items (called from event handlers) ── */

    _addRepeatableItem: function (path, fields) {
      // path: "blocks.servicesGrid.items"
      var blockKey = path.split('.')[1]; // "servicesGrid"
      var newItem = {};
      for (var i = 0; i < fields.length; i++) {
        newItem[fields[i].key] = fields[i].type === 'checkbox' ? false : '';
      }
      CC.addRepeatableItem(blockKey, newItem);
    },

    _removeRepeatableItem: function (path, index) {
      var blockKey = path.split('.')[1];
      CC.removeRepeatableItem(blockKey, index);
    },

    _moveRepeatableItem: function (path, index, direction) {
      var blockKey = path.split('.')[1];
      CC.moveRepeatableItem(blockKey, index, direction);
    },

    _duplicateRepeatableItem: function (path, index) {
      var blockKey = path.split('.')[1];
      CC.duplicateRepeatableItem(blockKey, index);
    },

    /* ── Preview ─────────────────────────────── */

    /* ── Zoom Toggle ──────────────────────────── */

    toggleZoom: function () {
      var viewport = CC.el.previewViewport;
      if (!viewport) return;
      viewport.classList.toggle('vbb-cc-preview-viewport--zoomed');
      var isZoomed = viewport.classList.contains('vbb-cc-preview-viewport--zoomed');
      if (CC.el.zoomBtn) {
        CC.el.zoomBtn.textContent = isZoomed ? '\u00D7' : '\u26B0';
        CC.el.zoomBtn.title = isZoomed ? 'Reset zoom' : 'Zoom 2x';
      }
    },

    /* ── Dark Preview Toggle ────────────────── */

    toggleDarkPreview: function () {
      CC.state._darkPreviewEnabled = !CC.state._darkPreviewEnabled;
      var enabled = CC.state._darkPreviewEnabled;

      if (CC.el.darkPreviewBtn) {
        CC.el.darkPreviewBtn.classList.toggle('vbb-cc-dark-preview-btn--active', enabled);
        CC.el.darkPreviewBtn.textContent = enabled ? '\u263E Dark' : '\u2600 Light';
      }

      if (enabled) {
        var darkVars = CC.buildCssVars('dark');
        if (darkVars) {
          CC.postMessage({ type: 'vbb:css-vars', styleTag: darkVars });
        }
      } else {
        var normalVars = CC.buildCssVars();
        if (normalVars) {
          CC.postMessage({ type: 'vbb:css-vars', styleTag: normalVars });
        }
      }

      CC.postMessage({ type: 'vbb:dark-preview', enabled: enabled });

      // Re-render Colors card to show the palette matching the preview mode
      CC._reRenderColorsCard();
    },

    refreshPreview: function (forceReload) {
      if (!CC.el.iframe) return;

      if (forceReload) {
        // Content change: force full iframe reload to fetch freshly baked HTML
        CC._showPreviewOverlay('loading');
        var baseUrl = CC.state.currentPageId
          ? window.location.origin + '/?p=' + CC.state.currentPageId
          : (CC.state.previewUrl ? CC.state.previewUrl.split('?')[0] : window.location.origin + '/');
        var sep = baseUrl.indexOf('?') === -1 ? '?' : '&';
        var ts = new Date().getTime();
        var newSrc = baseUrl + sep + 'vbb_preview=' + ts + '&vbb_no_admin=1&vbb_origin=' + encodeURIComponent(CC.previewOrigin);
        console.log('[VBB Refresh] forceReload=true | baseUrl:', baseUrl, '| pageId:', CC.state.currentPageId, '| newSrc:', newSrc);
        CC.el.iframe.src = newSrc;
        CC._updatePreviewUrlDisplay();
        return;
      }

      // Style/color change: try postMessage for live CSS vars update (no reload)
      if (CC.supportsPostMessage && CC.el.iframe && CC.el.iframe.contentWindow) {
        var cssVars = CC.buildCssVars();
        if (cssVars) {
          CC.postMessage({ type: 'vbb:css-vars', styleTag: cssVars });
          return;
        }
      }

      // Fallback: reload iframe
      CC._showPreviewOverlay('loading');
      var baseUrl = CC.state.currentPageId
        ? window.location.origin + '/?p=' + CC.state.currentPageId
        : (CC.state.previewUrl ? CC.state.previewUrl.split('?')[0] : window.location.origin + '/');
      var sep = baseUrl.indexOf('?') === -1 ? '?' : '&';
      var ts = new Date().getTime();
      CC.el.iframe.src = baseUrl + sep + 'vbb_preview=' + ts + '&vbb_no_admin=1&vbb_origin=' + encodeURIComponent(CC.previewOrigin);
      CC._updatePreviewUrlDisplay();
    },

    /* ── Regenerate & Refresh ─────────────────── */

    regenerateAndRefresh: function () {
      CC.showStatus('saving', 'Regenerating pages\u2026');
      var isPerPage = !!CC.state.currentPageId;
      var endpoint = isPerPage
        ? CC.state.ajaxUrl + 'pages/' + CC.state.currentPageId + '/regenerate'
        : CC.state.ajaxUrl + 'regenerate-pages';
      console.log('[VBB Regenerate] Calling endpoint:', endpoint, '| currentPageId:', CC.state.currentPageId, '| ajaxUrl:', CC.state.ajaxUrl);

      CC.xhr(
        endpoint,
        'POST',
        null,
        function (data) {
          console.log('[VBB Regenerate] SUCCESS response:', data);
          CC.showStatus('saved');
          CC.showToast(
            data && data.message ? data.message : 'P\u00e1ginas regeneradas.',
            'success'
          );
          CC.refreshPreview(true);
        },
        function (xhr) {
          var msg = 'Regeneration failed.';
          try {
            var err = JSON.parse(xhr.responseText);
            msg = err.message || msg;
          } catch (e) {
            msg += ' (HTTP ' + xhr.status + ')';
          }
          console.log('[VBB Regenerate] FAILURE:', msg, '| status:', xhr.status);
          CC.showStatus('error', msg);
          CC.showToast(msg, 'error');
        }
      );
    },

    /* ── Preview URL Display ──────────────────── */

    _updatePreviewUrlDisplay: function () {
      var el = document.getElementById('vbb-cc-preview-url');
      if (!el) return;
      var url = CC.el.iframe ? CC.el.iframe.src : CC.state.previewUrl;
      el.textContent = url;
      var openBtn = document.getElementById('vbb-cc-preview-open');
      var copyBtn = document.getElementById('vbb-cc-preview-copy');
      if (openBtn) openBtn.setAttribute('data-url', url);
      if (copyBtn) copyBtn.setAttribute('data-url', url);
    },

    _fallbackCopy: function (text) {
      var ta = document.createElement('textarea');
      ta.value = text;
      ta.style.position = 'fixed';
      ta.style.left = '-9999px';
      document.body.appendChild(ta);
      ta.select();
      try {
        document.execCommand('copy');
        CC.showToast('Enlace copiado al portapapeles', 'success');
      } catch (e) {
        CC.showToast('No se pudo copiar el enlace', 'error');
      }
      document.body.removeChild(ta);
    },

    /* ── postMessage Bridge ──────────────────── */

    postMessage: function (msg) {
      if (!CC.el.iframe || !CC.el.iframe.contentWindow) {
        CC.supportsPostMessage = false;
        CC.refreshPreview();
        return;
      }
      try {
        var targetOrigin = CC.previewOrigin !== '*' ? CC.previewOrigin : '*';
        CC.el.iframe.contentWindow.postMessage(msg, targetOrigin);
      } catch (e) {
        CC.supportsPostMessage = false;
        CC.refreshPreview();
      }
    },

    buildCssVars: function (overrideMode) {
      var s = CC.state.settings;
      if (!s || !s.palettes) return '';

      var mode = overrideMode || s.colorMode || 'light';
      var palette = s.palettes[mode] || s.palettes.light || {};
      var lines = [];

      // :root vars
      lines.push(':root{');
      var colorKeys = ['primary', 'secondary', 'accent', 'background', 'surface', 'text', 'mutedText'];
      for (var ci = 0; ci < colorKeys.length; ci++) {
        var ck = colorKeys[ci];
        if (palette[ck]) {
          lines.push('--vbb-pro-' + ck + ':' + palette[ck] + ';');
        }
      }
      if (s.typography) {
        lines.push('--vbb-pro-heading-font:' + (s.typography.heading || 'inherit') + ';');
        lines.push('--vbb-pro-body-font:' + (s.typography.body || 'inherit') + ';');
      }
      if (s.layout) {
        lines.push('--vbb-pro-content-width:' + (s.layout.contentWidth || '1180px') + ';');
        lines.push('--vbb-pro-wide-width:' + (s.layout.wideWidth || '1440px') + ';');
        lines.push('--vbb-pro-radius:' + (s.layout.radius || '24px') + ';');
      }
      lines.push('}');

      // Override hardcoded theme.json presets with VBB dynamic palette
      lines.push(':root{--vbb-pro-base:' + (mode === 'dark' ? (palette.background || palette.text || '#1A1A2E') : '#FFFFFF') + ';--wp--preset--color--primary:var(--vbb-pro-primary);--wp--preset--color--secondary:var(--vbb-pro-secondary);--wp--preset--color--accent:var(--vbb-pro-accent);--wp--preset--color--base:var(--vbb-pro-base);--wp--preset--color--contrast:var(--vbb-pro-text);--wp--preset--color--muted:var(--vbb-pro-mutedText)}');

      // Non-standard slugs — class-level overrides
      lines.push('.has-background-background-color{background-color:var(--vbb-pro-background)!important}.has-surface-background-color{background-color:var(--vbb-pro-surface)!important}');

      // Per-block scoped vars
      if (s.blocks) {
        Object.keys(s.blocks).forEach(function (bk) {
          var block = s.blocks[bk];
          if (typeof block !== 'object' || !block.colors) return;
          var blockVars = [];
          Object.keys(block.colors).forEach(function (ck) {
            if (block.colors[ck]) {
              blockVars.push('--vbb-pro-' + ck + ':' + block.colors[ck]);
            }
          });
          if (blockVars.length > 0) {
            var sectionClass = CC._blockKeyToSectionClass(bk);
            lines.push(sectionClass + '{' + blockVars.join(';') + '}');
          }
        });
      }

      // Footer scoped vars
      var fc = s.footerConfig || {};
      if (fc.bgColor || fc.textColor || fc.linkColor || fc.linkHoverColor || fc.bottomBarBgColor) {
        var fVars = [];
        if (fc.bgColor) fVars.push('--vbb-footer-bg:' + fc.bgColor);
        if (fc.textColor) fVars.push('--vbb-footer-text:' + fc.textColor);
        if (fc.linkColor) fVars.push('--vbb-footer-link:' + fc.linkColor);
        if (fc.linkHoverColor) fVars.push('--vbb-footer-link-hover:' + fc.linkHoverColor);
        if (fc.bottomBarBgColor) fVars.push('--vbb-footer-bottom-bg:' + fc.bottomBarBgColor);
        lines.push('.vbb-site-footer{' + fVars.join(';') + '}');
      }

      return lines.join('');
    },

    _blockKeyToSectionClass: function (key) {
      var map = {
        'hero': 'hero',
        'hero-centered': 'hero-centered',
        'servicesGrid': 'services-grid',
        'benefits': 'benefits',
        'process': 'process',
        'testimonials': 'testimonials',
        'faq': 'faq',
        'contact': 'contact-section',
        'ctaFinal': 'cta-final',
        'logoCloud': 'logo-cloud',
        'pricing': 'pricing-tables',
        'team': 'team',
      };
      var suffix = map[key] || key.replace(/_/g, '-');
      return '.vbb-section-' + suffix;
    },

    /* ── Section Key Info Map ──────────────────
       Maps section keys (from vertical JSON) to friendly labels,
       block keys (for enabled/disabled status), and CSS class suffixes.
    */
    _sectionInfo: {
      'hero':              { label: 'Hero',              blockKey: 'hero',           cssSuffix: 'hero' },
      'hero-centered':     { label: 'Hero Centered',     blockKey: 'heroCentered',   cssSuffix: 'hero-centered' },
      'services-grid':     { label: 'Services Grid',     blockKey: 'servicesGrid',   cssSuffix: 'services-grid' },
      'benefits':          { label: 'Benefits',          blockKey: 'benefits',       cssSuffix: 'benefits' },
      'process':           { label: 'Process',           blockKey: 'process',        cssSuffix: 'process' },
      'testimonials':      { label: 'Testimonials',      blockKey: 'testimonials',   cssSuffix: 'testimonials' },
      'faq':               { label: 'FAQ',               blockKey: 'faq',            cssSuffix: 'faq' },
      'contact-section':   { label: 'Contact',           blockKey: 'contact',        cssSuffix: 'contact-section' },
      'cta-final':         { label: 'CTA Final',         blockKey: 'ctaFinal',       cssSuffix: 'cta-final' },
      'logo-cloud':        { label: 'Logo Cloud',        blockKey: 'logoCloud',      cssSuffix: 'logo-cloud' },
      'pricing':           { label: 'Pricing',           blockKey: 'pricing',        cssSuffix: 'pricing-tables' },
      'team':              { label: 'Team',              blockKey: 'team',           cssSuffix: 'team' },
      'stats':             { label: 'Stats',             blockKey: 'stats',          cssSuffix: 'stats' },
      'gallery':           { label: 'Gallery',           blockKey: 'gallery',        cssSuffix: 'gallery' },
      'video':             { label: 'Video',             blockKey: 'video',          cssSuffix: 'video' },
      'newsletter':        { label: 'Newsletter',        blockKey: 'newsletter',     cssSuffix: 'newsletter' },
      'problem':           { label: 'Problem',           blockKey: null,             cssSuffix: 'problem' },
    },

    /* ── kebab-case → Title Case ───────────────── */
    _kebabToTitle: function (str) {
      return str
        .replace(/[-_]/g, ' ')
        .replace(/\b\w/g, function (c) { return c.toUpperCase(); });
    },

    /* ── Footer Settings ───────────────────── */

    renderFooterSettings: function (s) {
      var fc = s.footerConfig || {};
      var c1 = fc.column1 || {};
      var c2 = fc.column2 || {};
      var bb = fc.bottomBar || {};
      var items = c2.items || [];
      var logoPreview = c1.logoUrl
        ? '<img src="' + CC.escAttr(c1.logoUrl) + '" class="vbb-cc-media-thumb" style="max-width:120px;max-height:80px;object-fit:cover;border-radius:4px;" />'
        : '';

      var html = '';

      // Column 1 — Brand
      html += '<h4 style="margin:12px 0 8px;font-size:0.85rem;font-weight:600;color:#444;">Column 1 — Brand</h4>';
      html += '<div class="vbb-cc-field"><label>Logo</label>';
      html += '<div class="vbb-cc-media-field">';
      html += '<div class="vbb-cc-media-preview">' + logoPreview + '</div>';
      html += '<button type="button" class="button vbb-cc-media-btn" data-target="footerConfig.column1.logoUrl">Seleccionar de biblioteca</button>';
      html += '<input type="hidden" data-path="footerConfig.column1.logoUrl" value="' + CC.escAttr(c1.logoUrl || '') + '">';
      html += '</div></div>';
      html += '<div class="vbb-cc-field"><label>Description</label><textarea data-path="footerConfig.column1.description" rows="3" placeholder="Brief description about your brand\u2026">' + CC.escAttr(c1.description || '') + '</textarea></div>';

      var socialFields = [
        { key: 'socialFacebook', label: 'Facebook URL' },
        { key: 'socialInstagram', label: 'Instagram URL' },
        { key: 'socialLinkedin', label: 'LinkedIn URL' },
        { key: 'socialTwitter', label: 'X (Twitter) URL' },
      ];
      for (var si = 0; si < socialFields.length; si++) {
        var sf = socialFields[si];
        html += '<div class="vbb-cc-field"><label>' + sf.label + '</label><input type="text" data-path="footerConfig.column1.' + sf.key + '" value="' + CC.escAttr(c1[sf.key] || '') + '" placeholder="https://\u2026"></div>';
      }

      // Column 2 — Links
      html += '<h4 style="margin:16px 0 8px;font-size:0.85rem;font-weight:600;color:#444;">Column 2 — Links</h4>';
      html += '<div class="vbb-cc-field"><label>Section Title</label><input type="text" data-path="footerConfig.column2.title" value="' + CC.escAttr(c2.title || '') + '" placeholder="Quick Links"></div>';
      for (var ii = 0; ii < 4; ii++) {
        var item = items[ii] || {};
        var idxStr = 'items.' + ii;
        html += '<div class="vbb-cc-field" style="display:flex;gap:8px;align-items:center;">';
        html += '<input type="text" data-path="footerConfig.column2.' + idxStr + '.text" value="' + CC.escAttr(item.text || '') + '" placeholder="Link text" style="flex:1;">';
        html += '<input type="text" data-path="footerConfig.column2.' + idxStr + '.url" value="' + CC.escAttr(item.url || '') + '" placeholder="URL" style="flex:1.5;">';
        html += '</div>';
      }

      // Bottom Bar
      html += '<h4 style="margin:16px 0 8px;font-size:0.85rem;font-weight:600;color:#444;">Bottom Bar</h4>';
      html += '<div class="vbb-cc-field"><label>Copyright text <span style="font-weight:400;opacity:.7;">(use {year} for dynamic year)</span></label><input type="text" data-path="footerConfig.bottomBar.copyright" value="' + CC.escAttr(bb.copyright || '') + '" placeholder="© {year} Todos los derechos reservados."></div>';
      html += '<div class="vbb-cc-field" style="display:flex;gap:8px;align-items:center;">';
      html += '<div style="flex:1;"><label style="font-size:.8rem;">Button Text (optional)</label><input type="text" data-path="footerConfig.bottomBar.button.text" value="' + CC.escAttr(bb.button && bb.button.text || '') + '" placeholder="Contacto"></div>';
      html += '<div style="flex:1.5;"><label style="font-size:.8rem;">Button URL</label><input type="text" data-path="footerConfig.bottomBar.button.url" value="' + CC.escAttr(bb.button && bb.button.url || '') + '" placeholder="/contacto"></div>';
      html += '</div>';

      // Colors
      html += '<h4 style="margin:16px 0 8px;font-size:0.85rem;font-weight:600;color:#444;">Colors</h4>';
      var colorFields = [
        { key: 'bgColor', label: 'Footer Background' },
        { key: 'textColor', label: 'Text Color' },
        { key: 'linkColor', label: 'Link Color' },
        { key: 'linkHoverColor', label: 'Link Hover Color' },
        { key: 'bottomBarBgColor', label: 'Bottom Bar Background' },
      ];
      html += '<div class="vbb-cc-color-grid">';
      for (var ci = 0; ci < colorFields.length; ci++) {
        var cf = colorFields[ci];
        var val = fc[cf.key] || '';
        html += '<div class="vbb-cc-field"><label>' + cf.label + '</label>' +
          '<div class="vbb-cc-color-swatch">' +
          '<input type="color" data-path="footerConfig.' + cf.key + '" value="' + CC._validateColor(val) + '">' +
          '<input type="text" class="vbb-cc-hex-input" value="' + val + '" data-path="footerConfig.' + cf.key + '" pattern="^#[0-9a-fA-F]{6}$" maxlength="7"></div></div>';
      }
      html += '</div>';

      return html;
    },

    /* ── Section Audit Card ────────────────────
       Renders a card listing all sections of the current page.
       Each item is clickable to scroll to that section in the preview.
    */
    renderSectionAudit: function (s) {
      var pageId = CC.state.currentPageId;
      var pages = CC.state.availablePages || [];
      var currentPage = null;
      for (var i = 0; i < pages.length; i++) {
        if (pages[i].id == pageId) {
          currentPage = pages[i];
          break;
        }
      }
      if (!currentPage || !currentPage.sections || currentPage.sections.length === 0) {
        return '';
      }

      var items = '';
      var activeSectionKey = CC.state._activeSectionKey || '';

      for (var si = 0; si < currentPage.sections.length; si++) {
        var sectionKey = currentPage.sections[si];
        var info = CC._sectionInfo[sectionKey] || {};
        var label = info.label || CC._kebabToTitle(sectionKey);
        var blockKey = info.blockKey || sectionKey;
        var cssSuffix = info.cssSuffix || sectionKey;

        // Check enabled status from blocks settings
        var enabled = true;
        if (blockKey && s.blocks && s.blocks[blockKey] && 'enabled' in s.blocks[blockKey]) {
          enabled = s.blocks[blockKey].enabled;
        }

        var statusClass = enabled ? 'vbb-cc-section-item--enabled' : 'vbb-cc-section-item--disabled';
        var statusText = enabled ? 'On' : 'Off';
        var activeClass = (activeSectionKey === sectionKey) ? ' vbb-cc-section-item--active' : '';

        items += '<div class="vbb-cc-section-item ' + statusClass + activeClass + '" data-section-key="' + sectionKey + '">' +
          '<span class="vbb-cc-section-item-icon">' + (enabled ? '&#9679;' : '&#9675;') + '</span>' +
          '<span class="vbb-cc-section-item-label">' + label + '</span>' +
          '<span class="vbb-cc-section-item-status">' + statusText + '</span>' +
          '</div>';
      }

      return CC.buildCard(
        'Page Sections',
        'Click a section below to scroll to it in the preview. Click any section in the preview to locate it here.',
        '<div class="vbb-cc-section-audit" id="vbb-cc-section-audit">' + items + '</div>'
      );
    },

    /* ── Scroll preview to a section ────────────── */
    _scrollToSection: function (sectionKey) {
      CC.postMessage({ type: 'vbb:scroll-to-section', sectionKey: sectionKey });
      CC._highlightSectionCard(sectionKey);
    },

    /* ── Highlight a section card item ──────────── */
    _highlightSectionCard: function (sectionKey) {
      CC.state._activeSectionKey = sectionKey;
      var audit = document.getElementById('vbb-cc-section-audit');
      if (!audit) return;
      var items = audit.querySelectorAll('.vbb-cc-section-item');
      items.forEach(function (item) {
        var key = item.getAttribute('data-section-key');
        if (key === sectionKey) {
          item.classList.add('vbb-cc-section-item--active');
          item.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } else {
          item.classList.remove('vbb-cc-section-item--active');
        }
      });
    },

    _selectBlockCard: function (blockKey, field) {
      // Normalize kebab-case section class to camelCase settings key
      var _blockKeyNorm = {
        'hero': 'hero',
        'hero-centered': 'heroCentered',
        'services-grid': 'servicesGrid',
        'benefits': 'benefits',
        'process': 'process',
        'testimonials': 'testimonials',
        'faq': 'faq',
        'contact-section': 'contact',
        'cta-final': 'ctaFinal',
        'logo-cloud': 'logoCloud',
        'pricing': 'pricing',
        'team': 'team',
        'stats': 'stats',
        'gallery': 'gallery',
        'video': 'video',
        'newsletter': 'newsletter',
        'map': 'map',
        'comparison': 'comparison',
        'blog': 'blog',
        'divider': 'divider'
      };
      blockKey = _blockKeyNorm[blockKey] || blockKey;

      // Find the block item whose toggle has data-path="blocks.{blockKey}.enabled"
      var blockItem = document.querySelector(
        '.vbb-cc-block-item input[data-path="blocks.' + blockKey + '.enabled"]'
      );
      if (!blockItem) {
        // Fallback: search for data-block-key attribute on any card element
        blockItem = document.querySelector(
          '[data-block-key="' + blockKey + '"]'
        );
        if (!blockItem) {
          CC.showToast('Block "' + blockKey + '" not found.', 'info');
          return;
        }
      }
      var block = blockItem.closest('.vbb-cc-block-item');
      if (!block) {
        block = blockItem;
      }

      // Remove previous highlights
      var prev = document.querySelectorAll('.vbb-cc-card-selected');
      prev.forEach(function (el) { el.classList.remove('vbb-cc-card-selected'); });

      // Find the parent card and scroll it into view
      var card = block.closest('.vbb-cc-card');
      if (card) {
        card.scrollIntoView({ behavior: 'smooth', block: 'center' });
        card.classList.add('vbb-cc-card-selected');
        // Auto-collapse/expand: ensure block settings are visible
        var checkbox = block.querySelector(
          'input[type="checkbox"][data-path="blocks.' + blockKey + '.enabled"]'
        );
        if (checkbox && !checkbox.checked) {
          checkbox.checked = true;
          CC._toggleBlockSettings(checkbox, true);
        }
      }

      // Highlight the block item with a brief pulse
      block.classList.add('vbb-cc-block-item--selected');
      setTimeout(function () {
        block.classList.remove('vbb-cc-block-item--selected');
      }, 2000);

      // If field specified, find and focus the corresponding input
      if (field && card) {
        var fieldInput = card.querySelector(
          'input[data-path="blocks.' + blockKey + '.' + field + '"]'
        );
        if (fieldInput) {
          fieldInput.focus();
          fieldInput.select();
          fieldInput.classList.add('vbb-cc-field-highlight');
          setTimeout(function () {
            fieldInput.classList.remove('vbb-cc-field-highlight');
          }, 3000);
        } else {
          // Try textarea
          fieldInput = card.querySelector(
            'textarea[data-path="blocks.' + blockKey + '.' + field + '"]'
          );
          if (fieldInput) {
            fieldInput.focus();
            fieldInput.select();
          }
        }
      }
    },

    /* ── Preview Loading Overlay ────────────── */

    _showPreviewOverlay: function (state) {
      var overlay = CC.el.previewOverlay;
      if (!overlay) return;
      overlay.style.display = 'flex';
      var spinner = overlay.querySelector('.vbb-cc-preview-overlay-spinner');
      var textEl = overlay.querySelector('.vbb-cc-preview-overlay-text');
      if (spinner) spinner.style.display = state === 'error' ? 'none' : 'block';
      if (textEl) {
        if (state === 'error') {
          textEl.innerHTML = 'Preview failed. <button class="button vbb-cc-preview-retry">Retry</button>';
        } else {
          textEl.textContent = 'Loading preview\u2026';
        }
      }
    },

    _hidePreviewOverlay: function () {
      var overlay = CC.el.previewOverlay;
      if (!overlay) return;
      overlay.style.display = 'none';
    },

    /* ── Responsive Presets ─────────────────── */

    _onPresetChange: function (e) {
      var btn = e.currentTarget;
      var width = btn.getAttribute('data-width');
      var viewport = CC.el.previewViewport;
      if (!viewport) return;

      // Update active state
      CC.el.presetBtns.forEach(function (b) {
        b.classList.remove('vbb-cc-preset-btn--active');
      });
      btn.classList.add('vbb-cc-preset-btn--active');

      if (width === 'desktop') {
        viewport.style.maxWidth = '';
        viewport.style.width = '';
      } else {
        viewport.style.maxWidth = width + 'px';
        viewport.style.width = '100%';
      }
    },

    /* ── Color Input Preview Handler ────────── */

    _handleColorInput: function (e) {
      var input = e.currentTarget;
      var path = input.getAttribute('data-path');
      if (!path) return;

      CC._setNested(CC.state.settings, path, input.value);

      // Send preview-only update via postMessage (no XHR)
      if (CC.supportsPostMessage) {
        var cssVars = CC.buildCssVars();
        if (cssVars) {
          CC.postMessage({ type: 'vbb:css-vars', styleTag: cssVars });
        }
      }
    },

    /* ── Nested setter helper ───────────────── */

    _setNested: function (obj, path, value) {
      var keys = path.split('.');
      var current = obj;
      for (var i = 0; i < keys.length - 1; i++) {
        if (!current[keys[i]] || typeof current[keys[i]] !== 'object') {
          current[keys[i]] = {};
        }
        current = current[keys[i]];
      }
      current[keys[keys.length - 1]] = value;
      return obj;
    },

    /* ── Briefing: Send to Agency Hub ────────── */

    _collectBriefingData: function () {
      var data = {};
      var inputs = document.querySelectorAll('.vbb-cc-briefing-input');
      inputs.forEach(function (input) {
        var field = input.getAttribute('data-briefing-field');
        if (!field) return;
        // Serialise page fields into array
        if (field.indexOf('page_') === 0) {
          return; // handled separately below
        }
        data[field] = input.value;
      });
      // Collect pages
      var pages = [];
      var pageInputs = document.querySelectorAll('.vbb-cc-briefing-page-input');
      pageInputs.forEach(function (input) {
        var val = input.value.trim();
        if (val) pages.push(val);
      });
      if (pages.length > 0) data.pages = pages;
      return data;
    },

    sendBriefing: function () {
      var data = CC._collectBriefingData();

      // Validate
      if (!data.siteName || data.siteName.trim() === '') {
        CC.showToast('Site Name is required in the Branding tab.', 'error');
        return;
      }
      if (!data.pages || data.pages.length === 0) {
        CC.showToast('At least one page is required in the Architecture tab.', 'error');
        return;
      }

      var statusEl = document.getElementById('vbb-cc-briefing-status');
      if (statusEl) statusEl.textContent = 'Sending\u2026';

      // Target the Agency Hub plugin's REST endpoint
      var hubUrl = window.vbbCommandCenterData
        ? window.vbbCommandCenterData.restUrl.replace('orkestone/v1/', 'orkestone-agency/v1/receive-briefing')
        : '/wp-json/orkestone-agency/v1/receive-briefing';

      CC.xhr(
        hubUrl,
        'POST',
        { briefing: data },
        function (response) {
          if (statusEl) statusEl.textContent = '';
          if (response && response.success) {
            CC.showToast('Briefing sent to Agency Hub! Request #' + (response.config_id || response.id || ''), 'success', 5000);
          } else {
            CC.showToast(response.message || 'Failed to send briefing.', 'error');
          }
        },
        function () {
          if (statusEl) statusEl.textContent = '';
          CC.showToast('Failed to send briefing. Check plugin connectivity.', 'error');
        }
      );
    },

    /* ── Briefing Event Binding ─────────────── */

    bindBriefingEvents: function () {
      // Tab switching
      var tabBtns = document.querySelectorAll('[data-briefing-tab]');
      tabBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
          var tabKey = btn.getAttribute('data-briefing-tab');
          // Save active tab to state
          if (!CC.state.briefingData) CC.state.briefingData = {};
          CC.state.briefingData._activeTab = tabKey;

          // Update tab button styles
          tabBtns.forEach(function (b) {
            b.style.fontWeight = '400';
            b.style.borderBottom = '2px solid transparent';
            b.style.color = '#555';
          });
          btn.style.fontWeight = '600';
          btn.style.borderBottom = '2px solid #2271b1';
          btn.style.color = '#2271b1';

          // Show/hide panels
          var panels = document.querySelectorAll('[data-briefing-panel]');
          panels.forEach(function (panel) {
            panel.style.display = panel.getAttribute('data-briefing-panel') === tabKey ? '' : 'none';
          });
        });
      });

      // Add page button
      var addBtn = document.querySelector('.vbb-cc-briefing-add-page');
      if (addBtn) {
        addBtn.addEventListener('click', function () {
          var container = document.querySelector('.vbb-cc-briefing-pages');
          if (!container) return;
          var rows = container.querySelectorAll('.vbb-cc-briefing-page-row');
          var newIndex = rows.length;
          var row = document.createElement('div');
          row.className = 'vbb-cc-briefing-page-row';
          row.style.cssText = 'display:flex;gap:8px;margin-bottom:8px;';
          row.innerHTML = '<input type="text" class="vbb-cc-briefing-input vbb-cc-briefing-page-input" data-briefing-field="page_' + newIndex + '" value="" placeholder="Page name (e.g. About)" style="flex:1;">' +
            '<button class="button vbb-cc-briefing-remove-page" data-index="' + newIndex + '">\u2715</button>';
          container.appendChild(row);
          // Show remove buttons on all existing rows
          var removeBtns = container.querySelectorAll('.vbb-cc-briefing-remove-page');
          removeBtns.forEach(function (b) { b.style.display = ''; });
          // Focus the new input
          var input = row.querySelector('input');
          if (input) input.focus();
        });
      }

      // Remove page (delegated)
      var pagesContainer = document.querySelector('.vbb-cc-briefing-pages');
      if (pagesContainer) {
        pagesContainer.addEventListener('click', function (e) {
          var btn = e.target.closest('.vbb-cc-briefing-remove-page');
          if (!btn) return;
          var row = btn.closest('.vbb-cc-briefing-page-row');
          if (row) {
            row.parentNode.removeChild(row);
            // If only one row remains, hide its remove button
            var remaining = document.querySelectorAll('.vbb-cc-briefing-page-row');
            if (remaining.length === 1) {
              var lastRemove = remaining[0].querySelector('.vbb-cc-briefing-remove-page');
              if (lastRemove) lastRemove.style.display = 'none';
            }
          }
        });
      }

      // Send to Agency Hub button
      var sendBtn = document.getElementById('vbb-cc-send-briefing');
      if (sendBtn) {
        sendBtn.addEventListener('click', function (e) {
          e.preventDefault();
          CC.sendBriefing();
        });
      }
    },

    /* ── Content vs Style Change Detection ──── */

    _isContentChange: function (path) {
      if (!path) return false;
      // Estos segmentos indican cambios de contenido (texto) o estructura (bloques activos)
      // que necesitan una recarga completa del iframe para obtener el HTML recién horneado.
      var contentKeys = ['title', 'subtitle', 'text', 'buttonText', 'heading', 'label', 'url', 'enabled'];
      var segments = path.split('.');
      for (var i = 0; i < segments.length; i++) {
        if (contentKeys.indexOf(segments[i]) > -1) {
          return true;
        }
      }
      // headerConfig, menuConfig y topBar necesitan recarga completa
      if (path.indexOf('headerConfig.') === 0 || path.indexOf('menuConfig.') === 0 || path.indexOf('topBar.') === 0) {
        return true;
      }
      return false;
    },

    /* ── Toolbar actions ─────────────────────── */

    saveAsProfile: function (e) {
      if (e) e.preventDefault();
      CC.saveSettings(function () {
        // Save profile via XHR — no page reload, preserves currentPageId.
        var profileName = (CC.state.settings.profileName || 'Profile ' + new Date().toLocaleDateString());
        CC.showStatus('saving', 'Saving profile\u2026');
        CC.xhr(
          CC.state.ajaxUrl + 'profile',
          'POST',
          { name: profileName },
          function (data) {
            if (data && data.success) {
              CC.showStatus('saved');
              CC.showToast('Profile "' + (data.name || profileName) + '" saved!', 'success');
            } else {
              CC.showStatus('error', 'Profile save failed.');
              CC.showToast('Profile save failed.', 'error');
            }
          },
          function (xhr) {
            var msg = 'Profile save failed.';
            try { var err = JSON.parse(xhr.responseText); msg = err.message || msg; } catch (ex) { msg += ' (HTTP ' + xhr.status + ')'; }
            CC.showStatus('error', msg);
            CC.showToast(msg, 'error');
          }
        );
      });
    },

    resetSettings: function (e) {
      if (e) e.preventDefault();
      CC.showConfirmToast(
        'Reset all settings to vertical defaults? This cannot be undone.',
        function () {
          CC.showStatus('saving', 'Resetting\u2026');
          CC.xhr(
            CC.state.ajaxUrl + 'vertical-settings',
            'POST',
            { settings: {} },
            function (data) {
              if (data && data.settings) {
                CC.state.settings = data.settings;
                CC.renderCards();
                CC.showStatus('saved', 'Settings reset');
                CC.showToast('Settings reset to vertical defaults.', 'success');
                CC.refreshPreview();
              }
            },
            function () {
              CC.showStatus('error', 'Reset failed');
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
        }
      );
    },

    /* ── Dark Mode ────────────────────────────── */

    initDarkMode: function () {
      var toggle = document.getElementById('vbb-cc-dark-toggle');
      if (!toggle) return;

      var ccEl = document.querySelector('.vbb-command-center');
      if (!ccEl) return;

      var icon = toggle.querySelector('.vbb-cc-dark-toggle-icon');

      // Determine initial state
      var stored = localStorage.getItem('vbb-cc-dark-mode');
      var isDark = false;

      if (stored === 'true') {
        isDark = true;
      } else if (stored === null) {
        // No stored preference — check OS preference
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
          isDark = true;
        }
      }

      // Apply initial state (before paint — class is added synchronously)
      if (isDark) {
        ccEl.classList.add('vbb-command-center--dark');
        icon.textContent = '\u2600'; // Sun
      } else {
        ccEl.classList.remove('vbb-command-center--dark');
        icon.textContent = '\u263E'; // Crescent moon
      }

      // Toggle click handler
      toggle.addEventListener('click', function () {
        var currentlyDark = ccEl.classList.contains('vbb-command-center--dark');
        if (currentlyDark) {
          ccEl.classList.remove('vbb-command-center--dark');
          localStorage.setItem('vbb-cc-dark-mode', 'false');
          icon.textContent = '\u263E'; // Moon
        } else {
          ccEl.classList.add('vbb-command-center--dark');
          localStorage.setItem('vbb-cc-dark-mode', 'true');
          icon.textContent = '\u2600'; // Sun
        }
      });

      // Listen for OS preference changes (only if no stored preference)
      if (window.matchMedia) {
        var mq = window.matchMedia('(prefers-color-scheme: dark)');
        mq.addListener(function (e) {
          if (localStorage.getItem('vbb-cc-dark-mode') !== null) return;
          if (e.matches) {
            ccEl.classList.add('vbb-command-center--dark');
            icon.textContent = '\u2600';
          } else {
            ccEl.classList.remove('vbb-command-center--dark');
            icon.textContent = '\u263E';
          }
        });
      }
    },
  });

  /* ── Diagnostics ──────────────────────────── */

  CC.diagnose = function () {
    console.log('=== VBB Command Center Diagnostics ===');
    console.log('ajaxUrl:', CC.state.ajaxUrl);
    console.log('nonce:', CC.state.nonce ? '[set]' : '[MISSING]');
    console.log('currentPageId:', CC.state.currentPageId);
    console.log('availablePages:', CC.state.availablePages.length);
    console.log('settings keys:', Object.keys(CC.state.settings));
    console.log('settings dirty:', CC.state.dirty);

    // Test REST API connectivity
    fetch(CC.state.ajaxUrl + 'vertical-settings', {
      headers: { 'X-WP-Nonce': CC.state.nonce }
    })
    .then(function (r) {
      console.log('GET vertical-settings:', r.status, r.statusText);
      return r.json().catch(function () { return null; });
    })
    .then(function (data) {
      if (data && data.settings) {
        console.log('headerConfig:', JSON.stringify(data.settings.headerConfig));
        console.log('menuConfig:', JSON.stringify(data.settings.menuConfig));
      } else {
        console.warn('Response does not contain settings:', data);
      }
    })
    .catch(function (err) {
      console.error('API test failed:', err);
    });

    fetch(CC.state.ajaxUrl + 'pages', {
      headers: { 'X-WP-Nonce': CC.state.nonce }
    })
    .then(function (r) {
      console.log('GET pages:', r.status, r.statusText);
    })
    .catch(function (err) {
      console.error('Pages API test failed:', err);
    });
  };

  console.log('Run CC.diagnose() in console to test REST API connectivity.');

  /* ── Keyboard Shortcuts ───────────────────────── */

  CC._bindKeyboardShortcuts = function () {
    var self = this;

    document.addEventListener('keydown', function (e) {
      var target = e.target;
      // Disable keyboard shortcuts when focused on input fields (color pickers, text inputs)
      if (target.tagName === 'INPUT') {
        var type = target.getAttribute('type');
        if (type === 'color' || type === 'text') {
          // Allow Ctrl+S to be handled by the input, but block Ctrl+Z
          if (e.ctrlKey || e.metaKey) {
            e.preventDefault();
            return;
          }
        }
      }
      if (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.tagName === 'SELECT') {
        if (e.key === 'Escape') {
          self._closeAllModals();
        }
        return;
      }

      var isMeta = e.metaKey || e.ctrlKey;
      var isShift = e.shiftKey;

      if (isMeta && e.key === 's') {
        e.preventDefault();
        self.saveSettings();
        return;
      }
      if (isMeta && e.key === 'r') {
        e.preventDefault();
        self.refreshPreview();
        return;
      }
      if (isMeta && e.key === 'e') {
        e.preventDefault();
        if (self.el.exportBtn) self.el.exportBtn.click();
        return;
      }
      if (isMeta && e.shiftKey && e.key === 'r') {
        e.preventDefault();
        if (self.el.regenerateBtn) self.el.regenerateBtn.click();
        return;
      }
      if (e.key === 'Escape') {
        self._closeAllModals();
        return;
      }
      if (isMeta && e.key === 'b') {
        e.preventDefault();
        var firstCard = document.querySelector('.vbb-cc-card');
        if (firstCard) firstCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
        return;
      }
      if (isMeta && e.key === 'p') {
        e.preventDefault();
        var preview = document.getElementById('vbb-cc-iframe');
        if (preview) preview.focus();
        return;
      }
      // Ctrl+Z = Undo (from 5-action color stack or full settings snapshot)
      if (isMeta && !isShift && e.key === 'z') {
        e.preventDefault();
        // Use the 5-action color undo stack if available, otherwise fall back to full settings snapshot
        if (CC.undoRedoStack.length > 0) {
          CC.undo();
        } else {
          CC._undo();
        }
        return;
      }
      // Ctrl+Shift+Z = Redo (from 5-action color stack or full settings snapshot)
      if (isMeta && isShift && e.key === 'z') {
        e.preventDefault();
        if (CC.redoStack.length > 0) {
          CC.redo();
        } else {
          CC._redo();
        }
        return;
      }
      if (!e.metaKey && !e.ctrlKey && e.key >= '1' && e.key <= '9') {
        var cards = document.querySelectorAll('.vbb-cc-card');
        var idx = parseInt(e.key, 10) - 1;
        if (cards[idx]) {
          cards[idx].scrollIntoView({ behavior: 'smooth', block: 'start' });
          cards[idx].querySelector('input, select, textarea')?.focus();
        }
      }
    });
  };

  CC._closeAllModals = function () {
    document.querySelectorAll('.vbb-cc-toast--confirm').forEach(function (toast) {
      if (toast._cancelCallback) toast._cancelCallback();
      CC._dismissToast(toast);
    });
    document.querySelectorAll('.vbb-cc-font-dropdown--open').forEach(function (dd) {
      dd.classList.remove('vbb-cc-font-dropdown--open');
    });
  };

  /* ── Lazy Iframe Setup ─────────────────────── */
  CC._setupLazyIframe = function () {
    var self = this;
    var iframe = self.el.iframe;
    var previewViewport = self.el.previewViewport;

    if (!iframe || !previewViewport) return;

    // Initially set src to about:blank to prevent loading
    if (iframe.src && iframe.src !== 'about:blank') {
      iframe.dataset.src = iframe.src;
      iframe.src = 'about:blank';
    }

    // IntersectionObserver to load iframe when preview column is visible
    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          var iframeEl = entry.target.querySelector('iframe');
          if (iframeEl && iframeEl.dataset.src && iframeEl.src === 'about:blank') {
            iframeEl.src = iframeEl.dataset.src;
            observer.unobserve(entry.target);
          }
        }
      });
    }, {
      root: null,
      rootMargin: '100px', // Load 100px before entering viewport
      threshold: 0.1
    });

    observer.observe(previewViewport);
  };

  /* ── SortableJS Integration ──────────────────── */

  CC._initSortable = function () {
    if (typeof Sortable === 'undefined') return;
    document.querySelectorAll('.vbb-cc-repeatable-list').forEach(function (list) {
      if (list._sortable) return;
      var blockKey = list.closest('.vbb-cc-repeatable') && list.closest('.vbb-cc-repeatable').getAttribute('data-block-key');
      if (!blockKey) return;
      var self = CC;
      list._sortable = new Sortable(list, {
        handle: '.vbb-cc-drag-handle',
        animation: 200,
        ghostClass: 'vbb-cc-repeatable-item--ghost',
        chosenClass: 'vbb-cc-repeatable-item--chosen',
        dragClass: 'vbb-cc-repeatable-item--drag',
        onEnd: function (evt) {
          if (evt.oldIndex === evt.newIndex) return;
          var items = self._getNested(self.state.settings, 'blocks.' + blockKey + '.items') || [];
          var moved = items.splice(evt.oldIndex, 1)[0];
          items.splice(evt.newIndex, 0, moved);
          self._setNested(self.state.settings, 'blocks.' + blockKey + '.items', items);
          self.debouncedSave({ immediate: true });
          self.renderCards();
        }
      });
    });
  };

  CC._bindSortable = function () {
    if (typeof Sortable === 'undefined') return;
    setTimeout(CC._initSortable.bind(CC), 100);
  };

  /* ── Undo/Redo Stack ──────────────────────────── */

  CC._undoStack = [];
  CC._redoStack = [];
  CC._maxUndo = 50;

  CC._pushUndo = function () {
    CC._undoStack.push(JSON.stringify(CC.state.settings));
    if (CC._undoStack.length > CC._maxUndo) CC._undoStack.shift();
    CC._redoStack = [];
  };

  CC._undo = function () {
    if (CC._undoStack.length === 0) return;
    var current = JSON.stringify(CC.state.settings);
    var prev = CC._undoStack.pop();
    CC._redoStack.push(current);
    CC.state.settings = JSON.parse(prev);
    CC.debouncedSave({ immediate: true });
    CC.renderCards();
    CC.showToast('⇦ Deshecho', 'info', 1500);
  };

  CC._redo = function () {
    if (CC._redoStack.length === 0) return;
    var current = JSON.stringify(CC.state.settings);
    var next = CC._redoStack.pop();
    CC._undoStack.push(current);
    CC.state.settings = JSON.parse(next);
    CC.debouncedSave({ immediate: true });
    CC.renderCards();
    CC.showToast('⇨ Rehecho', 'info', 1500);
  };

  /* ── Preview Responsive Resize Handle ────────── */

  CC._initResizeHandle = function () {
    var viewport = document.getElementById('vbb-cc-preview-viewport');
    if (!viewport || viewport._resizeInit) return;
    viewport._resizeInit = true;

    var handle = document.createElement('div');
    handle.className = 'vbb-cc-resize-handle';
    handle.title = 'Arrastra para redimensionar';
    var isResizing = false, startX = 0, startWidth = 0;
    viewport.parentNode.insertBefore(handle, viewport.nextSibling);

    handle.addEventListener('mousedown', function (e) {
      isResizing = true;
      startX = e.clientX;
      startWidth = viewport.offsetWidth;
      document.body.style.cursor = 'ew-resize';
      document.body.style.userSelect = 'none';
      document.addEventListener('mousemove', onMouseMove);
      document.addEventListener('mouseup', onMouseUp);
    });

    function onMouseMove(e) {
      if (!isResizing) return;
      var maxW = viewport.parentNode.offsetWidth;
      var newW = Math.max(300, Math.min(startWidth + (e.clientX - startX), maxW));
      viewport.style.maxWidth = newW + 'px';
      viewport.style.width = newW + 'px';
    }

    function onMouseUp() {
      isResizing = false;
      document.body.style.cursor = '';
      document.body.style.userSelect = '';
      document.removeEventListener('mousemove', onMouseMove);
      document.removeEventListener('mouseup', onMouseUp);
    }
  };

  /* ── Init All Extras (called after renderCards) ── */

  CC._initExtras = function () {
    CC._bindSortable();
    CC._initResizeHandle();
  };

  /* ── Diagnostics ──────────────────────────── */
  // Exponer CC globalmente para debug
  window.CC = window.vbbCommandCenter;

  var boot = function() {
    try {
      CC.init();
    } catch (err) {
      console.error('VBB Command Center: Fallo crítico en inicialización:', err);
    }
  };

  // Initialize undo/redo and comparison on load
  CC._initUndoRedoButtons();
  CC._initComparisonMode();

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();

/* ── Undo/Redo Initialization ───────────────────── */
CC._initUndoRedoButtons = function () {
  var undoBtn = CC.el.undoBtn;
  var redoBtn = CC.el.redoBtn;
  if (!undoBtn || !redoBtn) return;
  // Enable/disable based on stack state
  if (CC.undoRedoStack.length === 0) {
    undoBtn.disabled = true;
  } else {
    undoBtn.disabled = false;
  }
  if (CC.redoStack.length === 0) {
    redoBtn.disabled = true;
  } else {
    redoBtn.disabled = false;
  }
};

/* ── Comparison Mode Initialization ──────────────── */
CC._initComparisonMode = function () {
  var compareBtn = document.getElementById('vbb-cc-compare-btn');
  var cc = CC || window.vbbCommandCenter;
  if (!compareBtn) return;
  // Restore saved mode or default to 'after'
  var savedMode = localStorage.getItem('vbb-cc-comparison-mode');
  cc.state.comparisonMode = savedMode === 'before' ? 'before' : 'after';
  // Update button visual state
  if (cc.state.comparisonMode === 'before') {
    compareBtn.classList.add('vbb-cc-compare-btn--active');
  } else {
    compareBtn.classList.remove('vbb-cc-compare-btn--active');
  }
  // Add click handler
  compareBtn.addEventListener('click', function () {
    cc.state.comparisonMode = cc.state.comparisonMode === 'after' ? 'before' : 'after';
    localStorage.setItem('vbb-cc-comparison-mode', cc.state.comparisonMode);
    // Update button visual state
    if (cc.state.comparisonMode === 'before') {
      compareBtn.classList.add('vbb-cc-compare-btn--active');
    } else {
      compareBtn.classList.remove('vbb-cc-compare-btn--active');
    }
    // Switch preview iframe state
    var iframe = document.getElementById('vbb-cc-iframe');
    if (iframe) {
      var allowedOrigin = window.location.origin;
      if (cc.state.comparisonMode === 'before') {
        // Send saved CSS vars via postMessage
        var cssVars = cc.buildCssVars();
        if (cssVars && iframe.contentWindow) {
          iframe.contentWindow.postMessage({ type: 'vbb:css-vars', styleTag: cssVars }, allowedOrigin);
        }
      }
    }
  });
};

/* ── Keyboard Shortcuts Fix ──────────────────────── */
CC._fixKeyboardShortcuts = function () {
  // Already handled in _bindKeyboardShortcuts - disable on input focus
  var inputs = document.querySelectorAll('input[type="color"], input[type="text"][data-path]');
  inputs.forEach(function (input) {
    input.addEventListener('focus', function () {
      input.classList.add('vbb-cc-input-focused');
    });
    input.addEventListener('blur', function () {
      input.classList.remove('vbb-cc-input-focused');
    });
  });
};

/* ── Export/Import Profile Functions ───────────── */
CC.exportProfile = function () {
  var settings = CC.state.settings || {};
  var profileName = settings.profileName || 'Pro Elite Profile';
  var data = {
    profileName: profileName,
    colorMode: settings.colorMode || 'light',
    palettes: settings.palettes || { light: {}, dark: {} },
    typography: settings.typography || { heading: '', body: '' },
    layout: settings.layout || { contentWidth: '', wideWidth: '', radius: '', shadow: '', spacingScale: '' },
    blocks: settings.blocks || {},
    buttons: settings.buttons || {},
    exportedAt: new Date().toISOString(),
    theme: 'vertical-block-base',
    profileType: 'pro-elite-settings',
    schemaVersion: '0.3.2'
  };
  var blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
  var url = URL.createObjectURL(blob);
  var a = document.createElement('a');
  a.href = url;
  a.download = 'vbb-pro-profile-' + new Date().toISOString().replace(/[:.]/g, '').slice(0, 15) + '.json';
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
  CC.showToast('Perfil exportado exitosamente', 'success');
};

CC.importProfile = function () {
  var fileInput = document.createElement('input');
  fileInput.type = 'file';
  fileInput.accept = '.json';
  fileInput.click();
  fileInput.addEventListener('change', function (e) {
    var file = e.target.files[0];
    if (!file) return;
    var reader = new FileReader();
    reader.onload = function (e) {
      var data = JSON.parse(e.target.result);
      if (!data || typeof data !== 'object') {
        CC.showToast('El JSON no es válido', 'error');
        return;
      }
      // Validate required fields
      var hasSettings = data.settings && typeof data.settings === 'object';
      if (!hasSettings) {
        CC.showToast('El JSON no es válido: falta settings', 'error');
        return;
      }
      // Apply via AJAX
      CC.showStatus('saving', 'Importando configuración…');
      CC.xhr(
        CC.state.ajaxUrl + 'vertical-settings',
        'POST',
        { settings: data.settings },
        function (resp) {
          CC.showStatus('saved', 'Importación completada');
          CC.showToast('Configuración Pro Elite importada.', 'success');
          // Refresh the UI
          CC.loadSettings();
        },
        function (xhr) {
          var msg = 'Import failed.';
          try { var err = JSON.parse(xhr.responseText); msg = err.message || msg; } catch (e) { msg += ' (HTTP ' + xhr.status + ')'; }
          CC.showStatus('error', msg);
          CC.showToast(msg, 'error');
        }
      );
    };
    reader.readAsText(file);
  });
};
