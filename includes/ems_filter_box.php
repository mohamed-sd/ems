<?php
/**
 * includes/ems_filter_box.php — صندوقُ الفلترةِ المعياريُّ المشترَك
 * ═══════════════════════════════════════════════════════════════════════════
 * **حكمُ المالك 2026-08-27**: يُبنى `Canonical Shared Filter Component` **مرّةً
 * واحدة** ⛔ **لا مئةً وسبعَ نسخٍ متشابهة**، والقبولُ
 * `ELIGIBLE_LIST_SURFACES_WITHOUT_CANONICAL_FILTER = 0`.
 *
 * ◆ **والبنيةُ ليست اختراعًا**: هي البنيةُ التي توثّقها `ems-filters.css` حرفًا
 *   (‏`.filter` ← `.filter-title` ← `.filter-body` ← `.filter-field` ×N ←
 *   `.filter-actions`)، **وقيمُها مقيسةٌ من شاشةِ العملاءِ قبل النقل** لا مُجتهَدٌ
 *   فيها. فالمكوّنُ **يُصدر المألوفَ** ولا يفرض جديدًا.
 *
 * ◆ **ووضعان لأنَّ للشاشاتِ حالَين**:
 *   ① `fields` — الشاشةُ تعرف فلاترَها فتُصرّح بها.
 *   ② `for` — الشاشةُ قائمةٌ مُصيَّرة، **فتُشتقُّ الفلاترُ من أعمدةِ جدولِها هي**.
 *      وهذا ما يجعل التبنّيَ في مئةٍ وسبعِ شاشةٍ سطرًا واحدًا لكلٍّ **بلا
 *      اختراعِ حقولٍ لا تعرفها الشاشة**.
 *
 * ⛔ **ولا يُصيَّر صندوقٌ لجدولٍ غائب**: الوضعُ ② يفحص وجودَ الجدولِ في المتصفّح
 *   ويخفي نفسَه إن غاب — **فصندوقٌ يفلتر العدمَ أسوأُ من غيابِه**.
 *
 * ◆ والوصولُ مضمونٌ بالنمطِ نفسِه الذي يضمن ورقةَ الجداول (`table_design.php`):
 *   `appendChild` على عقدةٍ موجودةٍ **ينقلها** فتصير آخرَ ورقةٍ يقينًا.
 *
 * ⚠ **وقيدٌ من الورقةِ لا من المكوّن**: مُحدِّداتُ `ems-filters.css` كلُّها مقيَّدةٌ
 *   بـ`body.ems-site .main` — **فشاشةٌ خارجَ هذه القشرةِ تُصيِّر الترميزَ بلا نمط**.
 *   وقد ضلَّلني ذلك في أوّلِ فحصٍ: ظننتُ الصندوقَ مكسورًا **وكان فحصي بلا قشرة**.
 *   ⇒ فالشاشاتُ ذاتُ القشرةِ المستقلّةِ (‏`admin/`) تحتاج صنفَ القشرةِ أو مدَّ
 *      نطاقِ الورقة ⛔ **ولا يُحلُّ ذلك بنسخِ الأنماطِ في الشاشة**.
 *
 * الاستعمال:
 *     require_once __DIR__ . '/../includes/ems_filter_box.php';
 *     ems_filter_box(array('for' => '#myTable'));            // من أعمدةِ الجدول
 *     ems_filter_box(array('fields' => array(                // أو بتصريحٍ
 *         array('name' => 'q',    'label' => 'بحث',  'type' => 'text'),
 *         array('name' => 'from', 'label' => 'من',   'type' => 'date'),
 *     ), 'method' => 'get'));
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (!function_exists('ems_filter_box_assets')) {
    /** يضمن وصولَ ورقةِ الفلاترِ ومحرِّكِها — مرّةً واحدةً في الصفحة. */
    function ems_filter_box_assets()
    {
        if (defined('EMS_FILTER_BOX_EMITTED')) { return; }
        define('EMS_FILTER_BOX_EMITTED', 1);
        $f    = dirname(__DIR__) . '/assets/css/ems-filters.css';
        $href = '/ems/assets/css/ems-filters.css' . (is_file($f) ? ('?v=' . filemtime($f)) : '');
        ?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>" data-ems-filter-box>
<script>
(function () {
    var HREF = <?php echo json_encode($href, JSON_UNESCAPED_SLASHES); ?>;
    function enforce() {
        var head = document.head; if (!head) { return; }
        var link = head.querySelector('link[data-ems-filter-box]')
                || head.querySelector('link[rel="stylesheet"][href*="ems-filters.css"]');
        if (!link) {
            link = document.createElement('link');
            link.rel = 'stylesheet'; link.href = HREF;
            link.setAttribute('data-ems-filter-box', '');
            head.appendChild(link);
        } else {
            link.setAttribute('data-ems-filter-box', '');
            head.appendChild(link);              /* نقلٌ إلى الذيلِ لا نسخ */
        }
        /* نسخٌ مكرّرةٌ أدرجتها قشرتان معًا — تُزال الزائدة */
        var all = head.querySelectorAll('link[rel="stylesheet"][href*="ems-filters.css"]');
        for (var i = 0; i < all.length; i++) { if (all[i] !== link) { all[i].parentNode.removeChild(all[i]); } }
    }
    enforce();
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', enforce); }
})();

/* ═══ محرِّكُ الوضعِ ②: الفلاترُ من أعمدةِ الجدولِ نفسِه ═══════════════════════
   ◆ **ولا يُخترَع حقلٌ لا تعرفه الشاشة**: كلُّ ضابطٍ هنا مشتقٌّ من رأسِ عمودٍ
     مُصيَّرٍ فعلًا. وعمودٌ قِيَمُه محدودةٌ يصير قائمةً منسدلة، وما عداه يُفلتَر
     بالبحثِ العامّ. ⛔ والصندوقُ يخفي نفسَه إن غاب الجدول. */
window.emsFilterBox = window.emsFilterBox || function (boxId, sel) {
    var box = document.getElementById(boxId);
    if (!box) { return; }
    var table = document.querySelector(sel);
    if (!table) { box.style.display = 'none'; return; }   /* لا فلترةَ للعدم */

    var body = box.querySelector('.filter-body');
    var head = table.tHead, tb = table.tBodies[0];
    if (!head || !tb) { box.style.display = 'none'; return; }
    var ths = head.rows.length ? head.rows[head.rows.length - 1].cells : [];
    var rows = function () { return Array.prototype.slice.call(tb.rows); };

    /* ① البحثُ العامُّ — يفلتر كلَّ نصِّ الصفّ */
    var wrap = document.createElement('div');
    wrap.className = 'filter-field';
    wrap.innerHTML = '<label>بحث</label><input type="search" class="ems-fb-q" placeholder="ابحث في القائمة">';
    body.appendChild(wrap);

    /* ② قوائمُ منسدلةٌ للأعمدةِ **المصنِّفة** — والإشارةُ التكرارُ لا العدد.
       ⚠ **نسختي الأولى صنعت قائمةً من عمودِ المعرِّف**: تسعةُ صفوفٍ فيها تسعةُ
         أرقامٍ فريدةٍ مرَّت على حدِّ «≤12»، **فصار كلُّ معرِّفٍ خيارًا** — وقائمةٌ
         بكلِّ القيمِ ليست فلترةً. ⇒ **والعمودُ المصنِّفُ تتكرَّر قيمُه**: فيُشترَط
         أن يكون المميَّزُ **نصفَ عددِ الصفوفِ فأقلَّ**، فيسقط المعرِّفُ والتاريخُ
         ويبقى ما يصنّف فعلًا. */
    var sels = [];
    var total = rows().length;
    for (var i = 0; i < ths.length; i++) {
        var vals = {}, n = 0, r = rows();
        for (var j = 0; j < r.length; j++) {
            var cell = r[j].cells[i]; if (!cell) { continue; }
            var v = (cell.textContent || '').trim();
            if (v === '' || v.length > 40) { continue; }
            if (!vals[v]) { vals[v] = 1; n++; }
            if (n > 12) { break; }               /* عمودٌ حرُّ القيمِ لا يصلح قائمةً */
        }
        if (n < 2 || n > 12) { continue; }
        if (total >= 4 && n > Math.floor(total / 2)) { continue; }   /* معرِّفٌ لا تصنيف */
        var lab = (ths[i].textContent || '').trim();
        if (lab === '' || lab.length > 30) { continue; }
        var f = document.createElement('div');
        f.className = 'filter-field';
        var opts = '<option value="">الكل</option>';
        Object.keys(vals).sort().forEach(function (v) {
            opts += '<option value="' + v.replace(/"/g, '&quot;') + '">' + v + '</option>';
        });
        f.innerHTML = '<label>' + lab + '</label><select data-col="' + i + '">' + opts + '</select>';
        body.appendChild(f);
        sels.push(f.querySelector('select'));
    }

    /* ③ الأفعال */
    var act = document.createElement('div');
    act.className = 'filter-actions';
    act.innerHTML = '<button type="button" class="ems-fb-reset" title="إعادة ضبط">↺</button>';
    body.appendChild(act);

    var q = box.querySelector('.ems-fb-q');
    function apply() {
        var t = (q.value || '').trim().toLowerCase();
        rows().forEach(function (row) {
            var ok = (t === '' || (row.textContent || '').toLowerCase().indexOf(t) !== -1);
            for (var k = 0; ok && k < sels.length; k++) {
                var want = sels[k].value;
                if (want === '') { continue; }
                var cell = row.cells[parseInt(sels[k].getAttribute('data-col'), 10)];
                ok = cell && (cell.textContent || '').trim() === want;
            }
            row.style.display = ok ? '' : 'none';
        });
    }
    q.addEventListener('input', apply);
    sels.forEach(function (s) { s.addEventListener('change', apply); });
    box.querySelector('.ems-fb-reset').addEventListener('click', function () {
        q.value = ''; sels.forEach(function (s) { s.value = ''; }); apply();
    });
};
</script>
        <?php
    }
}

if (!function_exists('ems_filter_box')) {
    /**
     * يُصيِّر صندوقَ الفلترةِ المعياريَّ.
     *
     * @param array $o  `for` مُحدِّدُ جدولٍ (‏وضعُ الاشتقاق) · أو `fields` تصريحًا ·
     *                  `title` عنوانُ الصندوق · `method` و`action` للنموذج.
     */
    function ems_filter_box(array $o = array())
    {
        ems_filter_box_assets();
        static $seq = 0;
        $seq++;
        $id    = 'emsFilterBox' . $seq;
        $title = isset($o['title']) ? $o['title'] : 'فلاتر البحث';
        $for   = isset($o['for']) ? (string) $o['for'] : '';
        $flds  = isset($o['fields']) && is_array($o['fields']) ? $o['fields'] : array();
        $h     = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
        ?>
<div class="filter" id="<?php echo $h($id); ?>">
    <div class="filter-title"><?php echo $h($title); ?></div>
    <div class="filter-body">
<?php if ($flds) { ?>
        <form method="<?php echo $h(isset($o['method']) ? $o['method'] : 'get'); ?>"
              action="<?php echo $h(isset($o['action']) ? $o['action'] : ''); ?>">
<?php   foreach ($flds as $f) {
            $t = isset($f['type']) ? $f['type'] : 'text';
            $n = isset($f['name']) ? $f['name'] : '';
            $v = isset($_GET[$n]) ? $_GET[$n] : (isset($f['value']) ? $f['value'] : ''); ?>
            <div class="filter-field">
                <label for="fb_<?php echo $h($n); ?>"><?php echo $h(isset($f['label']) ? $f['label'] : $n); ?></label>
<?php       if ($t === 'select') { ?>
                <select id="fb_<?php echo $h($n); ?>" name="<?php echo $h($n); ?>">
                    <option value="">الكل</option>
<?php           foreach ((isset($f['options']) ? $f['options'] : array()) as $ov => $ol) { ?>
                    <option value="<?php echo $h($ov); ?>"<?php echo ((string) $ov === (string) $v ? ' selected' : ''); ?>><?php echo $h($ol); ?></option>
<?php           } ?>
                </select>
<?php       } else { ?>
                <input type="<?php echo $h($t); ?>" id="fb_<?php echo $h($n); ?>"
                       name="<?php echo $h($n); ?>" value="<?php echo $h($v); ?>">
<?php       } ?>
            </div>
<?php   } ?>
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">تطبيق</button>
                <button type="reset" class="btn btn-secondary" title="إعادة ضبط">↺</button>
            </div>
        </form>
<?php } ?>
    </div>
</div>
<?php if ($for !== '') { ?>
<script>
(function () {
    var run = function () { window.emsFilterBox(<?php echo json_encode($id); ?>, <?php echo json_encode($for); ?>); };
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', run); } else { run(); }
})();
</script>
<?php }
    }
}
