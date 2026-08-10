/**
 * assets/js/ems-column-floor.js — حارسُ الحدِّ الأدنى للأعمدة (AC-U4 · SH-05/3)
 * ═══════════════════════════════════════════════════════════════════════════
 * الحكم: «صفرُ جدولٍ يبلغ صفرَ أعمدة» — وجدولٌ بلا أعمدةٍ ليس جدولًا بل فراغٌ
 * يظنُّه المستخدمُ عطلًا. والحكمُ يشترط معه: «حارسٌ يمنع النزولَ تحتَ حدٍّ أدنى
 * **ويبيّن السبب**» — فالمنعُ الصامتُ عطلٌ آخرُ لا علاج.
 *
 * لماذا «طبّقْ ثم قِسْ ثم تراجعْ» لا «احسبْ قبلَ التطبيق»؟
 *   لأن الأعمدةَ تُخفى بطريقين مختلفين (حذفٌ من DOM في DataTables · و
 *   `display:none` في المنتقي)، ولأن ثمّةَ أعمدةً خارجَ كلِّ مجموعة. فالحسابُ
 *   المسبقُ يلزمه نموذجٌ لكلِّ طريقٍ وكلِّ استثناء — والقياسُ بعدَ التطبيقِ يقيس
 *   ما يراه المستخدمُ فعلًا: كم خليةَ ترويسةٍ ظاهرة؟
 *
 * التراجعُ آمن: الحالةُ المتغيّرةُ رؤيةٌ محضةٌ لا بيانات.
 */
(function () {
    'use strict';

    var FLOOR_DEFAULT = 1;   // عمودٌ واحدٌ على الأقلّ — دون ذلك يختفي الجدولُ كلُّه

    /** خلايا الترويسةِ الظاهرةُ فعلًا في جدول — المقياسُ ما يراه المستخدم. */
    function visibleColumns(table) {
        if (!table) { return 0; }
        var head = table.tHead && table.tHead.rows.length ? table.tHead.rows[0] : null;
        if (!head && table.rows.length) { head = table.rows[0]; }
        if (!head) { return 0; }
        var n = 0;
        for (var i = 0; i < head.cells.length; i++) {
            var cell = head.cells[i];
            // العمودُ المحذوفُ من DOM ليس هنا أصلًا؛ والمخفيُّ بالنمطِ يُستبعَد.
            if (window.getComputedStyle(cell).display !== 'none') { n++; }
        }
        return n;
    }

    /** أقلُّ عددِ أعمدةٍ ظاهرةٍ عبرَ مجموعةِ جداول. */
    function minVisible(tables) {
        var lo = null;
        for (var i = 0; i < tables.length; i++) {
            var c = visibleColumns(tables[i]);
            if (lo === null || c < lo) { lo = c; }
        }
        return lo === null ? 0 : lo;
    }

    function explain(min, reason) {
        var text = 'لا يمكن إخفاءُ كلِّ الأعمدة: يبقى ' +
                   (min === 1 ? 'عمودٌ واحدٌ على الأقلّ' : (min + ' أعمدةٍ على الأقلّ')) +
                   ' وإلا صار الجدولُ فارغًا' + (reason ? ' (' + reason + ')' : '') + '.';
        if (window.EmsAlert && window.EmsAlert.warning) {
            window.EmsAlert.warning(text);
        } else if (window.console) {
            window.console.warn(text);
        }
    }

    /**
     * ينفّذ التغييرَ، فإن هبطت الأعمدةُ تحتَ الحدِّ تراجَع وبيّن السبب.
     * @returns {boolean} أبقيَ التغييرُ؟
     */
    function attempt(opts) {
        opts = opts || {};
        var tables = opts.tables || [];
        var min = typeof opts.min === 'number' ? opts.min : FLOOR_DEFAULT;
        if (typeof opts.apply !== 'function') { return false; }

        opts.apply();
        if (!tables.length || minVisible(tables) >= min) { return true; }

        if (typeof opts.revert === 'function') { opts.revert(); }
        explain(min, opts.reason || '');
        return false;
    }

    window.EmsColumnFloor = {
        FLOOR: FLOOR_DEFAULT,
        visibleColumns: visibleColumns,
        minVisible: minVisible,
        attempt: attempt
    };
}());
