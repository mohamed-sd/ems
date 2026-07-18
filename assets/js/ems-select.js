/* ════════════════════════════════════════════════════════════════════════
   ems-select.js — قائمة منسدلة مخصّصة لفورمات EMS
   ------------------------------------------------------------------------
   Progressive enhancement لكل <select> داخل (.allforms / .ems-form):
   يُبقي الـ <select> الأصلي مخفيّاً (يظلّ مصدر القيمة ويُرسَل مع الفورم)،
   ويبني فوقه واجهة منسّقة: زرّ يعرض القيمة + قائمة عناصر منسّقة.
   عند الاختيار يُحدّث الـ <select> ويُطلق change/input (لا تنكسر المعالجات).

   ─ القائمة تُنقَل إلى <body> عند الفتح (portal) وتُوضَع بـ position:fixed، كي
     تظهر فوق كل شيء (تتجاوز كل سياقات التكديس و overflow:hidden — مثل الجداول)،
     ثم تُعاد إلى مكانها عند الإغلاق. ──────────────────────────────────────────

   التنسيق في assets/css/ems-forms.css (.emsf-select-*).
   لتعطيله على select معيّن: data-no-emsselect.
══════════════════════════════════════════════════════════════════════════ */
(function () {
  'use strict';

  var FORM_SCOPE = ['.allforms', '.ems-form'];
  var openWraps = [];

  // عندما تبلغ الخيارات هذا العدد (أو أكثر) يظهر صندوق البحث تلقائياً داخل القائمة.
  // للإجبار: data-emsf-search على الـ<select>. للتعطيل: data-no-emsf-search.
  var SEARCH_MIN_OPTIONS = 7;

  function getMenu(wrap) { return wrap._emsfMenu; }

  // هل نعرض صندوق بحثٍ لهذه القائمة؟ (سمة صريحة تتقدّم على العدد التلقائي)
  function shouldSearch(sel) {
    if (sel.hasAttribute('data-no-emsf-search')) return false;
    var attr = sel.getAttribute('data-emsf-search');
    if (attr === 'off' || attr === 'false' || attr === '0') return false;
    if (attr !== null) return true;
    return sel.options.length >= SEARCH_MIN_OPTIONS;
  }

  // تطبيعٌ عربيٌّ متسامح: حروف صغيرة + إزالة التشكيل/التطويل + توحيد الألف/الياء/التاء المربوطة
  function normText(s) {
    s = (s == null ? '' : String(s)).toLowerCase();
    s = s.replace(/[ً-ْٰـ]/g, '');   // تشكيل + تطويل
    s = s.replace(/[آأإ]/g, 'ا');    // آ أ إ → ا
    s = s.replace(/ى/g, 'ي');                  // ى → ي
    s = s.replace(/ة/g, 'ه');                  // ة → ه
    return s.trim();
  }

  function visibleOptions(menu) {
    return Array.prototype.filter.call(
      menu.querySelectorAll('.emsf-select-option'),
      function (it) { return it.style.display !== 'none'; }
    );
  }

  function clearActive(menu) {
    menu.querySelectorAll('.emsf-select-option.is-active').forEach(function (it) {
      it.classList.remove('is-active');
    });
  }

  function moveActive(menu, dir) {
    var vis = visibleOptions(menu);
    if (!vis.length) return;
    var cur = menu.querySelector('.emsf-select-option.is-active');
    var idx = cur ? vis.indexOf(cur) : -1;
    idx += dir;
    if (idx < 0) idx = vis.length - 1;
    if (idx >= vis.length) idx = 0;
    clearActive(menu);
    vis[idx].classList.add('is-active');
    vis[idx].scrollIntoView({ block: 'nearest' });
  }

  // تصفية عناصر القائمة حسب نصّ البحث (يُخفي النائب أثناء البحث، ويُظهر «لا نتائج» عند الخلوّ)
  function filterMenu(wrap) {
    var menu = getMenu(wrap);
    if (!menu) return;
    var input = menu.querySelector('.emsf-select-search-input');
    var q = input ? normText(input.value) : '';
    var visible = 0;
    menu.querySelectorAll('.emsf-select-option').forEach(function (it) {
      var match = q === '' ? true : normText(it.textContent).indexOf(q) !== -1;
      if (q !== '' && it.classList.contains('is-placeholder')) match = false;
      it.style.display = match ? '' : 'none';
      if (match) visible++;
    });
    var empty = menu.querySelector('.emsf-select-empty');
    if (empty) empty.style.display = (visible === 0) ? '' : 'none';
    clearActive(menu);
  }

  function closeAll(except) {
    openWraps.slice().forEach(function (w) {
      if (w !== except) closeWrap(w);
    });
  }

  function buildMenu(wrap, sel) {
    var menu = getMenu(wrap);
    if (!menu) return;
    menu.innerHTML = '';

    // ترويسة البحث (تظهر فقط للقوائم الطويلة) — مثبّتة أعلى القائمة أثناء التمرير
    if (shouldSearch(sel)) {
      var header = document.createElement('div');
      header.className = 'emsf-select-search';
      var input = document.createElement('input');
      input.type = 'text';
      input.className = 'emsf-select-search-input';
      input.setAttribute('placeholder', '🔍 ابحث…');
      input.setAttribute('autocomplete', 'off');
      input.setAttribute('dir', 'auto');
      // منع إغلاق القائمة أو تسرّب الأحداث عند التفاعل مع الحقل
      input.addEventListener('click', function (e) { e.stopPropagation(); });
      input.addEventListener('input', function () { filterMenu(wrap); });
      input.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowDown') { e.preventDefault(); moveActive(menu, 1); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); moveActive(menu, -1); }
        else if (e.key === 'Enter') {
          e.preventDefault();
          var active = menu.querySelector('.emsf-select-option.is-active');
          if (!active) { var vis = visibleOptions(menu); active = vis.length ? vis[0] : null; }
          if (active) active.click();
        } else if (e.key === 'Escape') {
          e.stopPropagation();
          closeWrap(wrap);
        }
      });
      header.appendChild(input);
      menu.appendChild(header);
    }

    Array.prototype.forEach.call(sel.options, function (opt, i) {
      var item = document.createElement('div');
      item.className = 'emsf-select-option';
      item.setAttribute('role', 'option');
      item.dataset.index = i;
      item.textContent = opt.textContent;
      if (opt.disabled) item.classList.add('is-disabled');
      if (opt.value === '') item.classList.add('is-placeholder');
      if (i === sel.selectedIndex) item.classList.add('is-selected');
      item.addEventListener('click', function (e) {
        e.stopPropagation();
        if (opt.disabled) return;
        if (sel.selectedIndex !== i) {
          sel.selectedIndex = i;
          sel.dispatchEvent(new Event('input', { bubbles: true }));
          sel.dispatchEvent(new Event('change', { bubbles: true }));
        }
        refresh(wrap, sel);
        closeWrap(wrap);
      });
      menu.appendChild(item);
    });

    // حالة «لا نتائج» (مخفيّة حتى تُفرِغ التصفيةُ القائمة)
    var empty = document.createElement('div');
    empty.className = 'emsf-select-empty';
    empty.textContent = 'لا نتائج مطابقة';
    empty.style.display = 'none';
    menu.appendChild(empty);
  }

  function refresh(wrap, sel) {
    var trigger = wrap.querySelector('.emsf-select-trigger');
    var menu = getMenu(wrap);
    var opt = sel.options[sel.selectedIndex];
    var txt = opt ? opt.textContent.trim() : '';
    if (trigger) {
      trigger.querySelector('.emsf-select-text').textContent = txt;
      trigger.classList.toggle('is-placeholder', !opt || opt.value === '');
      trigger.classList.toggle('is-disabled', sel.disabled);
    }
    if (menu) menu.querySelectorAll('.emsf-select-option').forEach(function (it) {
      it.classList.toggle('is-selected', parseInt(it.dataset.index, 10) === sel.selectedIndex);
    });
  }

  // تموضع القائمة (fixed) أسفل الزرّ، أو فوقه إن لم تكفِ المساحة بالأسفل
  function positionMenu(wrap) {
    var trigger = wrap.querySelector('.emsf-select-trigger');
    var menu = getMenu(wrap);
    if (!trigger || !menu) return;
    var r = trigger.getBoundingClientRect();
    menu.style.position = 'fixed';
    menu.style.left = r.left + 'px';
    menu.style.width = r.width + 'px';
    menu.style.right = 'auto';
    var menuH = menu.offsetHeight || 260;
    var spaceBelow = window.innerHeight - r.bottom;
    if (spaceBelow < menuH + 12 && r.top > spaceBelow) {
      menu.style.top = 'auto';
      menu.style.bottom = (window.innerHeight - r.top + 6) + 'px';
    } else {
      menu.style.bottom = 'auto';
      menu.style.top = (r.bottom + 6) + 'px';
    }
  }

  function openWrap(wrap, sel) {
    refresh(wrap, sel);
    closeAll(wrap);
    var menu = getMenu(wrap);
    var trigger = wrap.querySelector('.emsf-select-trigger');
    if (!menu) return;
    wrap.classList.add('is-open');
    if (trigger) trigger.setAttribute('aria-expanded', 'true');
    // نقل القائمة إلى <body> كي تتجاوز كل سياقات التكديس / overflow
    if (menu.parentNode !== document.body) document.body.appendChild(menu);
    menu.classList.add('is-open');
    // إعادة ضبط البحث عند كل فتح ثم تركيز الحقل (إن وُجد)
    var searchInput = menu.querySelector('.emsf-select-search-input');
    if (searchInput) {
      searchInput.value = '';
      filterMenu(wrap);
    }
    positionMenu(wrap);
    var selItem = menu.querySelector('.emsf-select-option.is-selected');
    if (selItem) selItem.scrollIntoView({ block: 'nearest' });
    if (searchInput) {
      setTimeout(function () {
        try { searchInput.focus({ preventScroll: true }); } catch (e) { searchInput.focus(); }
      }, 0);
    }
    if (openWraps.indexOf(wrap) === -1) openWraps.push(wrap);
    // إعادة التموضع أثناء التمرير/تغيير الحجم
    wrap._emsfReposition = function () { positionMenu(wrap); };
    window.addEventListener('scroll', wrap._emsfReposition, true);
    window.addEventListener('resize', wrap._emsfReposition);
  }

  function closeWrap(wrap) {
    var menu = getMenu(wrap);
    var trigger = wrap.querySelector('.emsf-select-trigger');
    wrap.classList.remove('is-open');
    if (trigger) trigger.setAttribute('aria-expanded', 'false');
    if (menu) {
      menu.classList.remove('is-open');
      // إرجاع القائمة إلى داخل الغلاف
      if (menu.parentNode !== wrap) wrap.appendChild(menu);
      menu.style.position = '';
      menu.style.top = '';
      menu.style.bottom = '';
      menu.style.left = '';
      menu.style.right = '';
      menu.style.width = '';
    }
    if (wrap._emsfReposition) {
      window.removeEventListener('scroll', wrap._emsfReposition, true);
      window.removeEventListener('resize', wrap._emsfReposition);
      wrap._emsfReposition = null;
    }
    var idx = openWraps.indexOf(wrap);
    if (idx !== -1) openWraps.splice(idx, 1);
  }

  function enhance(sel) {
    if (!sel || sel.dataset.emsfSelect === '1') return;
    if (sel.multiple || sel.hasAttribute('data-no-emsselect')) return;
    sel.dataset.emsfSelect = '1';

    var wrap = document.createElement('div');
    wrap.className = 'emsf-select-wrap';
    sel.parentNode.insertBefore(wrap, sel);
    wrap.appendChild(sel);

    // عنصر <div> (وليس <button>) كي لا يطاله محرّك تنسيق الأزرار العام
    var trigger = document.createElement('div');
    trigger.className = 'emsf-select-trigger';
    trigger.setAttribute('role', 'button');
    trigger.setAttribute('tabindex', '0');
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');
    trigger.innerHTML =
      '<span class="emsf-select-text"></span>' +
      '<span class="emsf-select-arrow" aria-hidden="true">▾</span>';
    wrap.appendChild(trigger);

    var menu = document.createElement('div');
    menu.className = 'emsf-select-menu';
    menu.setAttribute('role', 'listbox');
    wrap.appendChild(menu);
    wrap._emsfMenu = menu;
    // النقر داخل القائمة (غير عنصر) لا يُغلقها
    menu.addEventListener('click', function (e) { e.stopPropagation(); });

    buildMenu(wrap, sel);
    refresh(wrap, sel);

    trigger.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      if (sel.disabled) return;
      if (wrap.classList.contains('is-open')) closeWrap(wrap);
      else openWrap(wrap, sel);
    });
    trigger.addEventListener('keydown', function (e) {
      if (sel.disabled) return;
      if (e.key === 'Enter' || e.key === ' ' || e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        if (!wrap.classList.contains('is-open')) openWrap(wrap, sel);
      } else if (e.key === 'Escape') {
        closeWrap(wrap);
      }
    });

    // إبقاء الواجهة متزامنة لو غُيّرت القيمة برمجياً (مع إطلاق change)
    sel.addEventListener('change', function () { refresh(wrap, sel); });

    // إعادة البناء لو تغيّرت العناصر (قوائم تُملأ عبر AJAX)
    try {
      var mo = new MutationObserver(function () { buildMenu(wrap, sel); refresh(wrap, sel); });
      mo.observe(sel, { childList: true });
    } catch (err) { /* MutationObserver غير مدعوم — تجاهل */ }

    watchFormVisibility(sel.closest('.allforms, .ems-form'));
  }

  // إعادة تحديث قوائم الفورم عند تغيّر ظهوره (تعبئة ثم إظهار في وضع التعديل)
  function watchFormVisibility(root) {
    if (!root || root.__emsfWatched) return;
    root.__emsfWatched = true;
    try {
      var mo = new MutationObserver(function () {
        root.querySelectorAll('.emsf-select-wrap').forEach(function (w) {
          var s = w.querySelector('select');
          if (s) refresh(w, s);
        });
      });
      mo.observe(root, { attributes: true, attributeFilter: ['class', 'style'] });
    } catch (err) { /* تجاهل */ }
  }

  function selectorList(suffix) {
    return FORM_SCOPE.map(function (s) { return s + ' ' + suffix; }).join(', ');
  }

  function initAll(root) {
    var scope = root || document;
    scope.querySelectorAll(selectorList('select')).forEach(enhance);
  }

  // إغلاق عند النقر خارج القائمة أو Escape
  document.addEventListener('click', function () { closeAll(null); });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' || e.keyCode === 27) closeAll(null);
  });

  if (document.readyState !== 'loading') initAll();
  else document.addEventListener('DOMContentLoaded', function () { initAll(); });

  // واجهة عامة لإعادة الفحص بعد إضافة فورمات/قوائم ديناميكياً
  window.EmsSelect = {
    init: initAll,
    refresh: function () {
      document.querySelectorAll('.emsf-select-wrap').forEach(function (w) {
        var s = w.querySelector('select');
        if (s) { buildMenu(w, s); refresh(w, s); }
      });
    }
  };
})();
