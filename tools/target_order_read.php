<?php
/**
 * T-01 · قارئُ «دليل الشاشات — الترتيب المستهدف» المشترك (update0007)
 * ─────────────────────────────────────────────────────────────────────
 * المصدر: docs/sources/TARGET_ORDER.xlsx — المرجعُ النافذُ للبنية
 * (NAV-02 v7: «المرجعُ النافذ: ملفُّ الترتيب المستهدف»).
 * ٢٢ ورقة: 16 إدارةً · مصفوفةُ العرض (18) · السجلُّ الفريد (19) ·
 * الترحيل (20) · كلُّ الظهورات (21) · الملخص (22).
 */

/** يقرأ ورقةً برقمها (1-based) مصفوفةَ صفوفٍ بخلايا مرقّمةٍ بعمودها */
function target_sheet($idx)
{
    static $zip = null, $cache = array();
    if (isset($cache[$idx])) return $cache[$idx];
    if ($zip === null) {
        $zip = new ZipArchive();
        if ($zip->open(dirname(__DIR__) . '/docs/sources/TARGET_ORDER.xlsx') !== true) {
            fwrite(STDERR, "لا ملفَّ ترتيبٍ مستهدف\n"); exit(1);
        }
    }
    $sx = $zip->getFromName("xl/worksheets/sheet$idx.xml");
    if (!$sx) return $cache[$idx] = array();
    $d = new DOMDocument(); $d->loadXML($sx);
    $rows = array();
    foreach ($d->getElementsByTagName('row') as $row) {
        $cells = array();
        foreach ($row->getElementsByTagName('c') as $c) {
            $ref = $c->getAttribute('r'); preg_match('/^([A-Z]+)/', $ref, $mm);
            $col = 0; foreach (str_split($mm[1]) as $ch) $col = $col * 26 + (ord($ch) - 64);
            $val = '';
            if ($c->getAttribute('t') === 'inlineStr') {
                foreach ($c->getElementsByTagName('t') as $n) $val .= $n->textContent;
            } else {
                $v = $c->getElementsByTagName('v')->item(0); $val = $v ? $v->textContent : '';
            }
            $cells[$col - 1] = $val;
        }
        $rows[] = $cells;
    }
    return $cache[$idx] = $rows;
}

/** أوراقُ الإدارات: الاسم ← رقمُ الورقة (بترتيب المصنّف المقيس) */
function target_dept_sheets()
{
    return array(
        'التشغيل' => 2, 'إدارة الموقع' => 3, 'الصيانة' => 4, 'النقل والترحيل' => 5,
        'سلسلة الإمداد — المشتريات' => 6, 'سلسلة الإمداد — المخازن' => 7,
        'المبيعات والعقود' => 8, 'المالية' => 9, 'التمويل والملكية' => 10,
        'الأسطول' => 11, 'الموردون' => 12, 'الموارد البشرية' => 13,
        'الحوكمة والالتزام' => 14, 'مركز البلاغات' => 15, 'مساحة عملي' => 16,
        'المشغّلون والقوى التشغيلية' => 17,
    );
}

/** الإدارةُ المستهدفة ← دورُها المالك في النظام (الأدوارُ الحقيقيةُ المقيسة) */
function target_dept_role($dept)
{
    $map = array(
        'التشغيل' => 1, 'إدارة الموقع' => 6, 'الصيانة' => 13, 'النقل والترحيل' => 23,
        'سلسلة الإمداد — المشتريات' => 16, 'سلسلة الإمداد — المخازن' => 25,
        'المبيعات والعقود' => 12, 'المالية' => 17, 'التمويل والملكية' => 26,
        'الأسطول' => 3, 'الموردون' => 2, 'الموارد البشرية' => 4,
        'الحوكمة والالتزام' => 15, 'مركز البلاغات' => 24,
        'المشغّلون والقوى التشغيلية' => 27, 'مساحة عملي' => 0, // فوق الإدارات — لا دورَ مالكًا
    );
    return isset($map[$dept]) ? $map[$dept] : null;
}

/** كلُّ ظهورٍ من ورقة «كل الظهورات» (21) بحقوله المسماة */
function target_appearances()
{
    $rows = target_sheet(21);
    $out = array();
    foreach (array_slice($rows, 2) as $r) {
        if (trim($r[1] ?? '') === '') continue;
        $out[] = array(
            'dept'    => trim($r[1]), 'layer' => trim($r[2] ?? ''), 'head' => trim($r[3] ?? ''),
            'group'   => trim($r[4] ?? ''), 'name' => trim($r[5] ?? ''), 'v3_name' => trim($r[6] ?? ''),
            'type'    => trim($r[7] ?? ''), 'file' => trim($r[8] ?? ''), 'route' => trim($r[9] ?? ''),
            'owner_dept' => trim($r[12] ?? ''), 'owner_view' => trim($r[13] ?? ''),
            'scope'   => trim($r[14] ?? ''), 'mother' => trim($r[15] ?? ''),
            'display' => trim($r[16] ?? ''), 'action' => trim($r[17] ?? ''),
            'source'  => trim($r[19] ?? ''),
        );
    }
    return $out;
}

/** صفوفُ مصفوفة العرض (18) */
function target_view_rows()
{
    $rows = target_sheet(18);
    $out = array();
    foreach (array_slice($rows, 2) as $r) {
        if (trim($r[1] ?? '') === '') continue;
        $out[] = array(
            'screen' => trim($r[1]), 'dept' => trim($r[2] ?? ''), 'role_kind' => trim($r[3] ?? ''),
            'scope' => trim($r[4] ?? ''), 'angle' => trim($r[5] ?? ''),
            'columns' => trim($r[6] ?? ''), 'filters' => trim($r[7] ?? ''),
        );
    }
    return $out;
}

/* تشغيلٌ مباشرٌ = تقريرُ قراءةٍ سريع */
if (PHP_SAPI === 'cli' && basename($_SERVER['SCRIPT_NAME'] ?? '') === 'target_order_read.php') {
    $ap = target_appearances();
    $vr = target_view_rows();
    $uq = target_sheet(19);
    echo "الظهورات: " . count($ap) . " · صفوفُ العرض: " . count($vr) . " · الفريدة: " . (count($uq) - 2) . "\n";
    $src = array();
    foreach ($ap as $a) { $k = mb_strpos($a['source'], '★') !== false ? '★جديد' : 'قائم'; $src[$k] = ($src[$k] ?? 0) + 1; }
    foreach ($src as $k => $v) echo "  $k: $v\n";
}
