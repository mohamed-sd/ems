/*
 * EMS Column Groups — unified show/hide for table column groups.
 * One implementation for every page: classic tables and DataTables alike,
 * single table or several, mapped by index or by a column CSS class.
 *
 * Usage (called once per page, after the table/DataTable exists):
 *   EmsColumnGroups.init({
 *     storageKey: 'contractGroupStates',      // unique per page (required to persist)
 *     mode: 'classic',                         // 'classic' | 'datatable'
 *
 *     // classic: cells carry class "group-<name>", hidden via "group-hidden".
 *
 *     // datatable, choose ONE column-mapping strategy:
 *     table: dt,                               // a DataTables instance or a getter
 *     tables: [dtA, dtB],                      // ...or several (applied to all)
 *     columnMap: { basic: [0,1], dates: [2] }, // explicit group→indices, or
 *     columnClass: true,                       // select via ".col-g-<name>", or
 *     // (omit both → indices derived from each header's data-group attribute)
 *     columnClassPrefix: 'col-g-',             // default prefix for columnClass
 *
 *     buttons: '.btn-group-toggle[data-group]',// group buttons (default)
 *     allButton: '.btn-group-toggle-all',      // show/hide-all button (optional)
 *     scope: document                          // optional container to scope queries
 *   });
 *
 * Buttons must carry data-group="<name>". Initial visibility (when nothing is
 * saved) is taken from whether each button already has the "active" class.
 */
(function (window, document) {
  'use strict';

  function asArray(nodeList) {
    return Array.prototype.slice.call(nodeList || []);
  }

  function resolveOne(t) {
    if (typeof t === 'function') { try { return t(); } catch (e) { return null; } }
    return t || null;
  }

  function Controller(cfg) {
    this.cfg = cfg;
    this.scope = cfg.scope || document;
    this.storageKey = cfg.storageKey || '';
    this.mode = cfg.mode === 'datatable' ? 'datatable' : 'classic';
    this.classPrefix = cfg.columnClassPrefix || 'col-g-';
    this.buttons = asArray(this.scope.querySelectorAll(cfg.buttons || '.btn-group-toggle[data-group]'));
    this.allButton = cfg.allButton === null ? null
      : this.scope.querySelector(cfg.allButton || '.btn-group-toggle-all');

    // Normalise table list (single or many; instances or getters).
    if (cfg.tables && cfg.tables.length) this.tableRefs = cfg.tables.slice();
    else if (cfg.table) this.tableRefs = [cfg.table];
    else this.tableRefs = [];

    this.groups = [];
    this.state = {};
    this._dtColumnMap = null;

    var self = this;
    this.buttons.forEach(function (btn) {
      var g = btn.getAttribute('data-group');
      if (g && self.groups.indexOf(g) === -1) self.groups.push(g);
    });

    this.loadState();
    this.applyAll();
    this.bind();
  }

  Controller.prototype.tables = function () {
    var out = [];
    this.tableRefs.forEach(function (r) { var t = resolveOne(r); if (t) out.push(t); });
    return out;
  };

  Controller.prototype.defaultState = function () {
    var st = {}, byGroup = {};
    this.buttons.forEach(function (btn) {
      byGroup[btn.getAttribute('data-group')] = btn.classList.contains('active');
    });
    this.groups.forEach(function (g) { st[g] = byGroup[g] !== undefined ? byGroup[g] : true; });
    return st;
  };

  Controller.prototype.loadState = function () {
    var st = this.defaultState();
    if (this.storageKey) {
      try {
        var saved = JSON.parse(window.localStorage.getItem(this.storageKey) || 'null');
        if (saved && typeof saved === 'object') {
          this.groups.forEach(function (g) { if (typeof saved[g] === 'boolean') st[g] = saved[g]; });
        }
      } catch (e) { /* ignore corrupt storage */ }
    }
    this.state = st;
  };

  Controller.prototype.saveState = function () {
    if (!this.storageKey) return;
    try { window.localStorage.setItem(this.storageKey, JSON.stringify(this.state)); } catch (e) {}
  };

  // Indices derived from header data-group (used only when neither columnMap
  // nor columnClass is supplied). Built from the first table.
  Controller.prototype.derivedMap = function () {
    if (this._dtColumnMap) return this._dtColumnMap;
    var map = {};
    var dt = this.tables()[0];
    if (dt && dt.columns) {
      dt.columns().every(function () {
        var header = this.header();
        var g = header ? (header.getAttribute('data-group') || '') : '';
        if (g) { if (!map[g]) map[g] = []; map[g].push(this.index()); }
      });
    }
    this._dtColumnMap = map;
    return map;
  };

  // Set one group's visibility across all DataTables.
  Controller.prototype.dtSetVisible = function (group, visible) {
    var self = this;
    this.tables().forEach(function (dt) {
      if (!dt || !dt.columns) return;
      if (self.cfg.columnClass) {
        dt.columns('.' + self.classPrefix + group).visible(visible, false);
      } else {
        var idxs = (self.cfg.columnMap ? self.cfg.columnMap[group] : self.derivedMap()[group]) || [];
        idxs.forEach(function (i) { dt.column(i).visible(visible, false); });
      }
    });
  };

  Controller.prototype.dtRefresh = function () {
    this.tables().forEach(function (dt) {
      try { dt.columns.adjust(); } catch (e) {}
      if (dt.responsive && dt.responsive.recalc) { try { dt.responsive.recalc(); } catch (e) {} }
    });
  };

  // Apply one group's visibility + keep its button(s) in sync.
  Controller.prototype.applyGroup = function (group, refresh) {
    var visible = this.state[group] !== false;

    if (this.mode === 'classic') {
      asArray(this.scope.querySelectorAll('.group-' + group)).forEach(function (el) {
        el.classList.toggle('group-hidden', !visible);
      });
    } else {
      this.dtSetVisible(group, visible);
      if (refresh !== false) this.dtRefresh();
    }

    this.buttons.forEach(function (btn) {
      if (btn.getAttribute('data-group') === group) btn.classList.toggle('active', visible);
    });
  };

  Controller.prototype.applyAll = function () {
    var self = this;
    this.groups.forEach(function (g) { self.applyGroup(g, false); });
    if (this.mode === 'datatable') this.dtRefresh();
    this.syncAllButton();
  };

  Controller.prototype.allVisible = function () {
    var st = this.state;
    return this.groups.every(function (g) { return st[g] !== false; });
  };

  Controller.prototype.syncAllButton = function () {
    if (!this.allButton) return;
    var all = this.allVisible();
    this.allButton.classList.toggle('active', all);
    var text = all ? (this.cfg.hideAllText || 'إخفاء الكل') : (this.cfg.showAllText || 'إظهار الكل');
    var label = this.allButton.querySelector('.cg-all-label');
    if (label) {
      label.textContent = text;
    } else {
      var icon = this.allButton.querySelector('i');
      if (icon) {
        this.allButton.innerHTML = icon.outerHTML + ' <span class="cg-all-label">' + text + '</span>';
      } else {
        this.allButton.textContent = text;
      }
    }
  };

  Controller.prototype.toggleGroup = function (group) {
    this.state[group] = this.state[group] === false; // flip
    this.applyGroup(group, true);
    this.syncAllButton();
    this.saveState();
  };

  Controller.prototype.setAll = function (visible) {
    var self = this;
    this.groups.forEach(function (g) { self.state[g] = visible; });
    this.groups.forEach(function (g) { self.applyGroup(g, false); });
    if (this.mode === 'datatable') this.dtRefresh();
    this.syncAllButton();
    this.saveState();
  };

  Controller.prototype.bind = function () {
    var self = this;
    this.buttons.forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        self.toggleGroup(btn.getAttribute('data-group'));
      });
    });
    if (this.allButton) {
      this.allButton.addEventListener('click', function (e) {
        e.preventDefault();
        self.setAll(!self.allVisible());
      });
    }
  };

  window.EmsColumnGroups = {
    init: function (cfg) {
      try { return new Controller(cfg || {}); }
      catch (e) { if (window.console) console.error('EmsColumnGroups init failed', e); return null; }
    }
  };
})(window, document);
