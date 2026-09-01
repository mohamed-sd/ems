<?php
/**
 * includes/u13_screen_kit_cols.php — دوالُّ عقدِ الأعمدةِ لعُدّةِ `u13`
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **لماذا فُصلت**: `u13_screen_kit.php` **قالبٌ يُضمَّن** — يُصيِّر صفحةً فور
 *   تضمينِه ويخرج إن لم يجدْ `$U13`. فأيُّ أداةِ بناءٍ أو قياسٍ تحتاج قاعدةَ
 *   «الحقلُ بلا صنفٍ لا يُصيَّر» كانت مضطرّةً إلى **نسخِ القائمةِ عندها** —
 *   وقائمتان تنجرفان. فالدوالُّ هنا **مصبٌّ واحد**: تضمّنها العُدّةُ وتضمّنها
 *   الأداةُ، ولا نسخةَ ثانية.
 *
 * ⛔ ولا سلوكَ جديدًا هنا: النصُّ منقولٌ كما هو من العُدّةِ بحرفِه.
 */

if (!function_exists('u13_hidden_cols')) {

/** الحقولُ التقنيةُ التي لا تُعرض ولو صُنِّفت. */
function u13_hidden_cols()
{
    return array('id', 'company_id', 'created_by', 'updated_by', 'is_deleted',
                 'deleted_at', 'deleted_by', 'doc_ref', 'sort_order', 'steps_json', 'dims_json');
}

/**
 * أعمدةُ الشاشةِ من عقدِ التصنيف — مرتَّبةً بترتيبِ الجدولِ الحقيقي.
 * @return array<string,array{label:string,dc:string,sensitive:int}>
 */
function u13_columns(\mysqli $conn, $screenCode, $table)
{
    $cls = array();
    $st = $conn->prepare("SELECT field_key, label_ar, dc_code, is_sensitive
                            FROM gov_field_class WHERE screen_code = ? AND active = 1");
    if ($st) {
        $st->bind_param('s', $screenCode);
        $st->execute();
        $rs = $st->get_result();
        while ($r = $rs->fetch_assoc()) { $cls[$r['field_key']] = $r; }
        $st->close();
    }
    /* الترتيبُ ترتيبُ الجدولِ — فالمراجعُ يقرأ الصفَّ كما بُني لا كما صُنِّف. */
    $out = array();
    $hidden = u13_hidden_cols();
    $q = $conn->prepare("SELECT COLUMN_NAME c FROM information_schema.COLUMNS
                          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION");
    if ($q) {
        $q->bind_param('s', $table);
        $q->execute();
        $rs = $q->get_result();
        while ($x = $rs->fetch_assoc()) {
            $c = $x['c'];
            if (in_array($c, $hidden, true)) { continue; }
            if (!isset($cls[$c])) { continue; }        // ◆ بلا صنفٍ = لا يُصيَّر
            $out[$c] = array('label' => $cls[$c]['label_ar'] !== '' ? $cls[$c]['label_ar'] : $c,
                             'dc' => $cls[$c]['dc_code'], 'sensitive' => (int) $cls[$c]['is_sensitive']);
        }
        $q->close();
    }
    return $out;
}

/** المناظرُ (CM-09) مشتقةً من الصنفِ — لا قائمةً تُكتب لكلِّ شاشة. */
function u13_views(array $cols)
{
    $v = array(
        'DC-1' => array('key' => 'oper',   'label' => 'التشغيل والتخصيص',  'cols' => array()),
        'DC-2' => array('key' => 'fin',    'label' => 'الأثر المالي',        'cols' => array()),
        'DC-3' => array('key' => 'legal',  'label' => 'المراجعة القانونية', 'cols' => array()),
        'DC-4' => array('key' => 'credit', 'label' => 'المراجعة الائتمانية', 'cols' => array()),
    );
    foreach ($cols as $k => $c) { if (isset($v[$c['dc']])) { $v[$c['dc']]['cols'][] = $k; } }
    foreach ($v as $dc => $x) { if (!$x['cols']) { unset($v[$dc]); } }
    return $v;
}

/** تنسيقُ قيمةِ خلية — والفارغُ «—» بوسمِ الحوكمة. */
function u13_cell($val, $col)
{
    $v = trim((string) $val);
    if ($v === '' || $v === '0000-00-00' || $v === '0000-00-00 00:00:00') { return '—'; }
    if (in_array($col, array('is_partial', 'readonly', 'evidence_accepted', 'onerous', 'needs_cap',
                             'cancellable', 'recognition_candidate', 'frozen', 'has_conflict',
                             'needs_action', 'needs_doc', 'is_sensitive', 'posts_entry'), true)) {
        return ((int) $v === 1) ? 'نعم' : 'لا';
    }
    if (preg_match('~^-?\d+\.\d{2,6}$~', $v)) { return number_format((float) $v, 2); }
    return mb_strlen($v) > 160 ? (mb_substr($v, 0, 158) . '…') : $v;
}

} // function_exists
