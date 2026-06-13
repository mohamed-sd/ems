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

  function getMenu(wrap) { return wrap._emsfMenu; }

  function closeAll(except) {
    openWraps.slice().forEach(function (w) {
      if (w !== except) closeWrap(w);
    });
  }

  function buildMenu(wrap, sel) {
    var menu = getMenu(wrap);
    if (!menu) return;
    menu.innerHTML = '';
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
    positionMenu(wrap);
    var selItem = menu.querySelector('.emsf-select-option.is-selected');
    if (selItem) selItem.scrollIntoView({ block: 'nearest' });
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
