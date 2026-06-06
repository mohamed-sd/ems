/* ============================================================================
 * EmsDetailsModal — Unified Details / View Modal System (نظام نافذة العرض الموحّد)
 * ----------------------------------------------------------------------------
 * Single, reusable component for every "View / تفاصيل / Details / Preview"
 * button across the whole EMS. Pure vanilla JS (no jQuery dependency), loaded
 * globally from inheader.php. Styling lives in assets/css/ems.main.all.style.css
 * under the "UNIFIED DETAILS MODAL SYSTEM" section.
 *
 * Usage:
 *   EmsDetailsModal.open({
 *     title: 'تفاصيل العميل',
 *     icon : 'fas fa-user-tie',
 *     fields: [
 *       { label:'كود العميل', value:'EQ51546', icon:'fas fa-barcode' },
 *       { label:'اسم العميل', value:'شركة ...', icon:'fas fa-user', size:'lg' },
 *       { label:'الحالة', value:'نشط', icon:'fas fa-toggle-on', type:'status', tone:'active' },
 *     ],
 *     sections: [
 *       { title:'المشاريع المرتبطة', icon:'fas fa-folder-open',
 *         pills: [ {label:'المشاريع', value:5} ],
 *         table: { columns:['المشروع','الموردون'], rows:[[ 'أ', 3 ]] },
 *         empty: 'لا توجد بيانات' }
 *     ],
 *     actions: [
 *       { label:'تعديل', icon:'fas fa-edit', variant:'primary', onClick: fn },
 *       { label:'إغلاق', icon:'fas fa-times', variant:'secondary', close:true },
 *     ]
 *   });
 *
 * Field options:
 *   label  (string)  field title in the dark card head
 *   value  (string|number|HTML node)
 *   icon   (string)  Font Awesome classes — default 'fas fa-circle-info'
 *   type   'text' | 'status' | 'html'   (status renders a colored pill via tone)
 *   tone   'active' | 'inactive'        (only for type:'status')
 *   size   'sm' | 'md' | 'lg' | 'full'  (force a width; otherwise auto by length)
 *   html   true                          (treat value as raw HTML — trusted only)
 *
 * The component computes each card's grid span automatically from value length
 * and field type, then packs them with CSS grid-auto-flow:dense to avoid gaps.
 * ==========================================================================*/
(function (window, document) {
  'use strict';

  var ROOT_ID = 'emsUnifiedDetailsModal';
  var rootEl = null;        // overlay element
  var lastFocused = null;   // restore focus on close

  /* ----- tiny DOM helpers ------------------------------------------------ */
  function el(tag, cls, html) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (html != null) n.innerHTML = html;
    return n;
  }

  function esc(s) {
    if (s == null) return '';
    return String(s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  function iconHtml(cls) {
    return '<i class="' + esc(cls || 'fas fa-circle-info') + '"></i>';
  }

  /* ----- smart weight heuristic (التحدي الثاني) --------------------------
   * Returns a flex-weight class suffix (sm|md|lg|full) based on the value
   * length and field semantics. Combined with flex-grow in CSS, every row
   * stretches to fill 100% width — short codes/status stay compact, long
   * names/sectors/descriptions claim more room, and no gaps are left.       */
  function widthFor(field) {
    if (field.size) {
      switch (field.size) {
        case 'sm':   return 'sm';
        case 'md':   return 'md';
        case 'lg':   return 'lg';
        case 'full': return 'full';
      }
    }
    if (field.type === 'status') return 'sm';

    var v = field.value;
    var len = (v == null) ? 1 : String(v).replace(/<[^>]*>/g, '').trim().length;

    if (len <= 8)  return 'sm';   // codes, numbers, short flags
    if (len <= 20) return 'md';   // names, phones, short labels
    if (len <= 45) return 'lg';   // long names, sectors, emails
    return 'full';                // descriptions / very long text
  }

  /* ----- build a single detail card -------------------------------------- */
  function buildCard(field) {
    var card = el('div', 'ems-dcard ems-dcard--w-' + widthFor(field));

    var head = el('div', 'ems-dcard__head');
    head.appendChild(el('span', null, esc(field.label || '')));
    head.insertAdjacentHTML('beforeend', iconHtml(field.icon));
    card.appendChild(head);

    var valEl = el('div', 'ems-dcard__value');
    if (field.type === 'status') {
      var tone = field.tone === 'inactive' ? 'inactive' : (field.tone === 'active' ? 'active' : null);
      var faceText = (field.value == null || field.value === '') ? '-' : field.value;
      if (tone === 'active') {
        valEl.innerHTML = '<span class="status-active"><i class="fas fa-check-circle"></i> ' + esc(faceText) + '</span>';
      } else if (tone === 'inactive') {
        valEl.innerHTML = '<span class="status-inactive"><i class="fas fa-times-circle"></i> ' + esc(faceText) + '</span>';
      } else {
        valEl.textContent = faceText;
      }
    } else if (field.type === 'html' || field.html === true) {
      if (field.value instanceof window.Node) valEl.appendChild(field.value);
      else valEl.innerHTML = (field.value == null || field.value === '') ? '-' : field.value;
    } else {
      valEl.textContent = (field.value == null || field.value === '') ? '-' : field.value;
    }
    card.appendChild(valEl);
    return card;
  }

  /* ----- build a related section (pills + table) ------------------------- */
  function buildSection(section) {
    var wrap = el('div', 'ems-dsection');

    var title = el('div', 'ems-dsection__title');
    title.innerHTML = iconHtml(section.icon || 'fas fa-list') + '<span>' + esc(section.title || '') + '</span>';
    wrap.appendChild(title);

    if (section.pills && section.pills.length) {
      var pills = el('div', 'ems-dsection__pills');
      section.pills.forEach(function (p) {
        var pill = el('span', 'ems-dsection__pill');
        pill.innerHTML = esc(p.label) + ': <strong>' + esc(p.value) + '</strong>';
        pills.appendChild(pill);
      });
      wrap.appendChild(pills);
    }

    if (section.html) {
      var custom = el('div');
      if (section.html instanceof window.Node) custom.appendChild(section.html);
      else custom.innerHTML = section.html;
      wrap.appendChild(custom);
    }

    if (section.table) {
      var t = section.table;
      var tableWrap = el('div', 'ems-dtable-wrap');
      // 'no-datatable' + data-no-dt opt out of the global DataTables auto-init
      // in ui-unification.js so this stays a clean unified table.
      var table = el('table', 'ems-dtable no-datatable');
      table.setAttribute('data-no-dt', '1');

      var thead = el('thead');
      var hr = el('tr');
      (t.columns || []).forEach(function (c) { hr.appendChild(el('th', null, esc(c))); });
      thead.appendChild(hr);
      table.appendChild(thead);

      var tbody = el('tbody');
      var rows = t.rows || [];
      var colCount = (t.columns || []).length || 1;
      if (!rows.length) {
        var er = el('tr');
        var ec = el('td', 'ems-dtable__empty', esc(section.empty || 'لا توجد بيانات'));
        ec.colSpan = colCount;
        er.appendChild(ec);
        tbody.appendChild(er);
      } else {
        rows.forEach(function (row) {
          var tr = el('tr');
          (row || []).forEach(function (cell) {
            var td = el('td');
            if (cell && cell.html != null) td.innerHTML = cell.html;
            else td.textContent = (cell == null) ? '' : cell;
            tr.appendChild(td);
          });
          tbody.appendChild(tr);
        });
      }
      table.appendChild(tbody);
      tableWrap.appendChild(table);
      wrap.appendChild(tableWrap);
    }

    return wrap;
  }

  /* ----- ensure the singleton overlay exists ----------------------------- */
  function ensureRoot() {
    if (rootEl && document.body.contains(rootEl)) return rootEl;
    rootEl = el('div', 'ems-dmodal');
    rootEl.id = ROOT_ID;
    rootEl.setAttribute('role', 'dialog');
    rootEl.setAttribute('aria-modal', 'true');
    rootEl.innerHTML =
      '<div class="ems-dmodal__dialog" role="document">' +
        // Header container: title text pinned to the inline-start (right in
        // RTL), close button pinned to the inline-end (left in RTL).
        // The title is a sibling of the white surface and sits on a LOWER
        // layer, so the surface paints in front of it — the title appears to
        // emerge from behind the modal (its geometry is never touched).
        '<div class="ems-dmodal__title">' +
          '<span class="ems-dmodal__title-text"></span>' +
          '<button type="button" class="ems-dmodal__close" aria-label="إغلاق">&times;</button>' +
        '</div>' +
        '<div class="ems-dmodal__surface">' +
          '<div class="ems-dmodal__body"></div>' +
          '<div class="ems-dmodal__footer"></div>' +
        '</div>' +
      '</div>';
    document.body.appendChild(rootEl);

    // overlay click closes
    rootEl.addEventListener('click', function (e) {
      if (e.target === rootEl) EmsDetailsModal.close();
    });
    // close button
    rootEl.querySelector('.ems-dmodal__close').addEventListener('click', function () {
      EmsDetailsModal.close();
    });
    return rootEl;
  }

  function onKeydown(e) {
    if (e.key === 'Escape') EmsDetailsModal.close();
  }

  /* ----- public API ------------------------------------------------------ */
  var EmsDetailsModal = {
    open: function (opts) {
      opts = opts || {};
      var root = ensureRoot();
      lastFocused = document.activeElement;

      // title text (close button is a sibling inside the same header bar)
      var titleTextEl = root.querySelector('.ems-dmodal__title-text');
      titleTextEl.innerHTML = iconHtml(opts.icon || 'fas fa-eye') + '<span>' + esc(opts.title || 'تفاصيل') + '</span>';

      // body
      var body = root.querySelector('.ems-dmodal__body');
      body.innerHTML = '';

      var grid = el('div', 'ems-dmodal__grid');
      (opts.fields || []).forEach(function (f) { grid.appendChild(buildCard(f)); });
      body.appendChild(grid);

      (opts.sections || []).forEach(function (s) { body.appendChild(buildSection(s)); });

      // footer / actions
      var footer = root.querySelector('.ems-dmodal__footer');
      footer.innerHTML = '';
      var actions = opts.actions;
      if (!actions || !actions.length) {
        actions = [{ label: 'إغلاق', icon: 'fas fa-times', variant: 'secondary', close: true }];
      }
      actions.forEach(function (a) {
        var btn = el('button', 'ems-dbtn ems-dbtn--' + (a.variant || 'secondary'));
        btn.type = 'button';
        btn.innerHTML = (a.icon ? iconHtml(a.icon) + ' ' : '') + esc(a.label || '');
        btn.addEventListener('click', function (ev) {
          if (typeof a.onClick === 'function') a.onClick(ev);
          if (a.close !== false && (a.close === true || typeof a.onClick !== 'function')) {
            EmsDetailsModal.close();
          }
        });
        footer.appendChild(btn);
      });

      // show
      root.classList.add('is-open');
      document.body.style.overflow = 'hidden';
      document.addEventListener('keydown', onKeydown);
      root.querySelector('.ems-dmodal__body').scrollTop = 0;

      if (typeof opts.onOpen === 'function') opts.onOpen(root);
      return root;
    },

    /* Replace a section's content after open (e.g. async table loaded). */
    setSection: function (index, section) {
      if (!rootEl) return;
      var body = rootEl.querySelector('.ems-dmodal__body');
      var sections = body.querySelectorAll('.ems-dsection');
      var fresh = buildSection(section);
      if (sections[index]) body.replaceChild(fresh, sections[index]);
      else body.appendChild(fresh);
    },

    close: function () {
      if (!rootEl) return;
      rootEl.classList.remove('is-open');
      document.body.style.overflow = '';
      document.removeEventListener('keydown', onKeydown);
      if (lastFocused && typeof lastFocused.focus === 'function') {
        try { lastFocused.focus(); } catch (e) {}
      }
    },

    isOpen: function () {
      return !!(rootEl && rootEl.classList.contains('is-open'));
    }
  };

  window.EmsDetailsModal = EmsDetailsModal;
})(window, document);
