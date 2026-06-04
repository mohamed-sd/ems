/* ════════════════════════════════════════════════════════════════════════
   ems-excel.js — مكوّن Excel الموحّد (الواجهة الأمامية)
   - أزرار التصدير/النموذج تعمل كروابط مباشرة إلى excel.php.
   - زر الاستيراد يفتح معالجاً متعدّد الخطوات: رفع → معاينة → تنفيذ.
   تعتمد فقط على fetch + DOM (بدون مكتبات). تتكامل مع توكن CSRF للنظام.
   الاستخدام: أي عنصر يحمل data-ems-excel-import="<entity>" يفتح المعالج.
════════════════════════════════════════════════════════════════════════ */
(function () {
  'use strict';

  var EMSExcel = {
    endpoint: (window.EMS_EXCEL_ENDPOINT || 'excel.php'),
    csrf: (window.EMS_EXCEL_CSRF || ''),
    state: { entity: '', title: '', file: null, token: '' }
  };

  function $(sel, ctx) { return (ctx || document).querySelector(sel); }
  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (m) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m];
    });
  }

  function modal() { return $('#emsExcelModal'); }

  function setStep(n) {
    var steps = modal().querySelectorAll('.ems-xl-steps li');
    steps.forEach(function (li, i) {
      li.classList.remove('active', 'done');
      if (i + 1 < n) li.classList.add('done');
      else if (i + 1 === n) li.classList.add('active');
    });
    modal().querySelectorAll('.ems-xl-pane').forEach(function (p) { p.classList.remove('is-active'); });
    var pane = modal().querySelector('.ems-xl-pane[data-step="' + n + '"]');
    if (pane) pane.classList.add('is-active');
  }

  function open(entity, title) {
    EMSExcel.state = { entity: entity, title: title || '', file: null, token: '' };
    var m = modal();
    $('.ems-xl-head h5 span', m).textContent = 'استيراد ' + (title || '');
    // روابط النموذج داخل المعالج
    m.querySelectorAll('[data-tpl-link]').forEach(function (a) {
      a.href = EMSExcel.endpoint + '?entity=' + encodeURIComponent(entity) + '&action=template';
    });
    resetUpload();
    $('#emsXlFoot').innerHTML = footUpload();
    setStep(1);
    m.classList.add('is-open');
    document.body.style.overflow = 'hidden';
  }

  function close() {
    modal().classList.remove('is-open');
    document.body.style.overflow = '';
  }

  function resetUpload() {
    EMSExcel.state.file = null;
    EMSExcel.state.token = '';
    var fn = $('#emsXlFilename'); if (fn) { fn.classList.remove('show'); fn.textContent = ''; }
    var inp = $('#emsXlFile'); if (inp) inp.value = '';
    showMsg('', '');
  }

  function showMsg(text, kind) {
    var box = $('#emsXlMsg');
    if (!box) return;
    if (!text) { box.classList.remove('show'); box.textContent = ''; return; }
    box.className = 'ems-xl-msg show ' + (kind || 'err');
    box.textContent = text;
  }

  // ── أزرار التذييل لكل خطوة ──
  function footUpload() {
    return '<button type="button" class="ems-xl-btn primary" id="emsXlPreviewBtn" disabled>' +
      '<i class="fas fa-search"></i> معاينة وتحقّق</button>' +
      '<button type="button" class="ems-xl-btn ghost" data-ems-xl-close>إلغاء</button>';
  }
  function footPreview(canCommit, validCount) {
    var commit = '<button type="button" class="ems-xl-btn primary" id="emsXlCommitBtn"' + (canCommit ? '' : ' disabled') +
      '><i class="fas fa-database"></i> استيراد ' + validCount + ' سجلاً صحيحاً</button>';
    return commit +
      '<button type="button" class="ems-xl-btn ghost" id="emsXlBackBtn"><i class="fas fa-arrow-right"></i> رجوع</button>';
  }
  function footResult() {
    return '<button type="button" class="ems-xl-btn primary" data-ems-xl-close><i class="fas fa-check"></i> إغلاق</button>';
  }

  // ── المرحلة 1: اختيار الملف ──
  function onFile(file) {
    if (!file) return;
    EMSExcel.state.file = file;
    var fn = $('#emsXlFilename');
    fn.textContent = '📄 ' + file.name + ' (' + Math.ceil(file.size / 1024) + ' KB)';
    fn.classList.add('show');
    $('#emsXlPreviewBtn').disabled = false;
    showMsg('', '');
  }

  function request(action, formData) {
    return fetch(EMSExcel.endpoint + '?entity=' + encodeURIComponent(EMSExcel.state.entity) + '&action=' + action, {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': EMSExcel.csrf },
      body: formData
    }).then(function (r) {
      return r.json().catch(function () {
        throw new Error('استجابة غير صالحة من الخادم (' + r.status + ')');
      }).then(function (j) {
        if (!r.ok || j.success === false) {
          throw new Error(j && j.message ? j.message : 'حدث خطأ (' + r.status + ')');
        }
        return j;
      });
    });
  }

  // ── المرحلة 2: المعاينة ──
  function doPreview() {
    if (!EMSExcel.state.file) { showMsg('يرجى اختيار ملف أولاً', 'err'); return; }
    setStep(2);
    $('#emsXlPreviewArea').innerHTML = progressHtml('جاري قراءة الملف والتحقق من البيانات...');
    $('#emsXlFoot').innerHTML = '';

    var fd = new FormData();
    fd.append('excel_file', EMSExcel.state.file);
    fd.append('csrf_token', EMSExcel.csrf);

    request('import_preview', fd).then(function (res) {
      EMSExcel.state.token = res.token || '';
      renderPreview(res);
    }).catch(function (err) {
      $('#emsXlPreviewArea').innerHTML = '';
      $('#emsXlFoot').innerHTML = footUpload();
      setStep(1);
      bindUploadFoot();
      showMsg(err.message, 'err');
    });
  }

  function renderPreview(res) {
    var s = res.summary || { total: 0, valid: 0, invalid: 0, warnings: 0 };
    var html = '';
    html += '<div class="ems-xl-cards">';
    html += card('total', s.total, 'إجمالي الصفوف');
    html += card('valid', s.valid, 'صحيحة');
    html += card('invalid', s.invalid, 'بها أخطاء');
    html += card('warn', s.warnings, 'تحذيرات');
    html += '</div>';

    // جدول المعاينة
    if (res.sample && res.sample.length) {
      html += '<div class="ems-xl-table-wrap"><table class="ems-xl-table"><thead><tr>';
      html += '<th>#</th><th>الحالة</th>';
      (res.columns || []).forEach(function (c) { html += '<th>' + esc(c.label) + '</th>'; });
      html += '</tr></thead><tbody>';
      res.sample.forEach(function (row) {
        html += '<tr class="' + (row.valid ? '' : 'bad') + '">';
        html += '<td>' + esc(row.row) + '</td>';
        html += '<td><span class="ems-xl-rowflag ' + (row.valid ? 'ok' : 'no') + '">' + (row.valid ? 'صحيح' : 'خطأ') + '</span></td>';
        (res.columns || []).forEach(function (c) { html += '<td>' + esc(row.data ? row.data[c.field] : '') + '</td>'; });
        html += '</tr>';
      });
      html += '</tbody></table></div>';
    }

    // تقرير الأخطاء
    if (res.errors && res.errors.length) {
      html += '<div class="ems-xl-errors"><h6><i class="fas fa-triangle-exclamation"></i> تقرير الأخطاء (' + res.errors.length + ')' +
        ' <button type="button" class="ems-xl-btn ghost" id="emsXlDlErr" style="padding:4px 10px;font-size:12px"><i class="fas fa-download"></i> تنزيل التقرير</button></h6>';
      html += '<table><thead><tr><th>الصف</th><th>العمود</th><th>الخطأ</th><th>كيفية الإصلاح</th></tr></thead><tbody>';
      res.errors.forEach(function (e) {
        html += '<tr><td>' + esc(e.row) + '</td><td>' + esc(e.column) + '</td><td>' + esc(e.error) + '</td><td>' + esc(e.fix) + '</td></tr>';
      });
      html += '</tbody></table></div>';
    }

    $('#emsXlPreviewArea').innerHTML = html;
    $('#emsXlFoot').innerHTML = footPreview(s.valid > 0, s.valid);

    var commitBtn = $('#emsXlCommitBtn'); if (commitBtn) commitBtn.addEventListener('click', doCommit);
    var backBtn = $('#emsXlBackBtn'); if (backBtn) backBtn.addEventListener('click', function () {
      setStep(1); $('#emsXlFoot').innerHTML = footUpload(); bindUploadFoot();
    });
    var dlErr = $('#emsXlDlErr'); if (dlErr) dlErr.addEventListener('click', function () { downloadErrors(res.errors); });
    bindFootClose();
  }

  function downloadErrors(errors) {
    var rows = [['الصف', 'العمود', 'الخطأ', 'كيفية الإصلاح']];
    errors.forEach(function (e) { rows.push([e.row, e.column, e.error, e.fix]); });
    var csv = '﻿' + rows.map(function (r) {
      return r.map(function (c) { return '"' + String(c).replace(/"/g, '""') + '"'; }).join(',');
    }).join('\n');
    var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'تقرير_أخطاء_الاستيراد.csv';
    a.click();
    URL.revokeObjectURL(a.href);
  }

  // ── المرحلة 3: التنفيذ ──
  function doCommit() {
    setStep(3);
    $('#emsXlResultArea').innerHTML = progressHtml('جاري استيراد البيانات...');
    $('#emsXlFoot').innerHTML = '';
    animateBar();

    var fd = new FormData();
    fd.append('token', EMSExcel.state.token);
    fd.append('csrf_token', EMSExcel.csrf);

    request('import_commit', fd).then(function (res) {
      var hasFail = (res.failed || 0) > 0;
      var html = '<div class="ems-xl-result' + (hasFail ? ' has-fail' : '') + '">';
      html += '<i class="fas ' + (hasFail ? 'fa-circle-exclamation' : 'fa-circle-check') + '"></i>';
      html += '<h4>' + esc(res.message) + '</h4>';
      html += '<p>تمت الإضافة: <strong>' + (res.added || 0) + '</strong>' +
        (hasFail ? '، فشل: <strong>' + res.failed + '</strong>' : '') + '</p>';
      html += '</div>';
      $('#emsXlResultArea').innerHTML = html;
      $('#emsXlFoot').innerHTML = footResult();
      bindFootClose();
      document.dispatchEvent(new CustomEvent('ems-excel:imported', { detail: { entity: EMSExcel.state.entity, result: res } }));
    }).catch(function (err) {
      $('#emsXlResultArea').innerHTML = '<div class="ems-xl-result has-fail"><i class="fas fa-circle-xmark"></i><h4>فشل الاستيراد</h4><p>' + esc(err.message) + '</p></div>';
      $('#emsXlFoot').innerHTML = footResult();
      bindFootClose();
    });
  }

  function card(kind, n, label) {
    return '<div class="ems-xl-card ' + kind + '"><div class="n">' + esc(n) + '</div><div class="l">' + esc(label) + '</div></div>';
  }
  function progressHtml(text) {
    return '<div class="ems-xl-progress"><i class="fas fa-spinner fa-spin"></i><p>' + esc(text) + '</p>' +
      '<div class="ems-xl-bar"><span id="emsXlBar"></span></div></div>';
  }
  function animateBar() {
    var bar = $('#emsXlBar'); if (!bar) return;
    var w = 0;
    var t = setInterval(function () {
      w = Math.min(90, w + Math.random() * 18);
      bar.style.width = w + '%';
      if (w >= 90) clearInterval(t);
    }, 200);
  }

  function bindUploadFoot() {
    var pv = $('#emsXlPreviewBtn');
    if (pv) { pv.disabled = !EMSExcel.state.file; pv.addEventListener('click', doPreview); }
    bindFootClose();
  }
  function bindFootClose() {
    modal().querySelectorAll('[data-ems-xl-close]').forEach(function (b) { b.addEventListener('click', close); });
  }

  // ── التهيئة ──
  function init() {
    if (!modal()) return;

    // فتح المعالج من أي زر يحمل data-ems-excel-import
    document.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-ems-excel-import]');
      if (btn) {
        e.preventDefault();
        open(btn.getAttribute('data-ems-excel-import'), btn.getAttribute('data-ems-excel-title') || '');
      }
    });

    // إغلاق
    modal().addEventListener('click', function (e) {
      if (e.target === modal() || e.target.closest('.ems-xl-close')) close();
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && modal().classList.contains('is-open')) close(); });

    // منطقة الرفع
    var drop = $('#emsXlDrop'), inp = $('#emsXlFile');
    if (drop && inp) {
      drop.addEventListener('click', function () { inp.click(); });
      inp.addEventListener('change', function () { onFile(inp.files[0]); });
      ['dragover', 'dragenter'].forEach(function (ev) {
        drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.add('dragover'); });
      });
      ['dragleave', 'drop'].forEach(function (ev) {
        drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.remove('dragover'); });
      });
      drop.addEventListener('drop', function (e) { if (e.dataTransfer.files[0]) onFile(e.dataTransfer.files[0]); });
    }

    // ربط أزرار التذييل الأولى (تُعاد إنشاؤها عند فتح المعالج)
    modal().addEventListener('click', function (e) {
      if (e.target.closest('#emsXlPreviewBtn')) doPreview();
    });
  }

  window.EMSExcel = { open: open, close: close };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
