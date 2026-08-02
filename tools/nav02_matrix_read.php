<?php
/**
 * قارئُ مصفوفة NAV-02 المشترك — يقرأ docs/sources/NAV-02_matrix.xlsx
 * (بلا مكتبة: ZipArchive + DOM · النصوصُ inline أو shared).
 */
function nav02_matrix_rows()
{
    static $rows = null;
    if ($rows !== null) return $rows;
    $z = new ZipArchive();
    if ($z->open(dirname(__DIR__) . '/docs/sources/NAV-02_matrix.xlsx') !== true) return array();
    $ss = array();
    $ssx = $z->getFromName('xl/sharedStrings.xml');
    if ($ssx) {
        $d = new DOMDocument(); $d->loadXML($ssx);
        foreach ($d->getElementsByTagName('si') as $si) { $t=''; foreach ($si->getElementsByTagName('t') as $n) $t .= $n->textContent; $ss[] = $t; }
    }
    $d2 = new DOMDocument(); $d2->loadXML($z->getFromName('xl/worksheets/sheet1.xml'));
    $all = array();
    foreach ($d2->getElementsByTagName('row') as $row) {
        $cells = array();
        foreach ($row->getElementsByTagName('c') as $c) {
            $ref = $c->getAttribute('r'); preg_match('/^([A-Z]+)/', $ref, $mm);
            $col = 0; foreach (str_split($mm[1]) as $ch) $col = $col*26 + (ord($ch)-64);
            $t = $c->getAttribute('t');
            if ($t === 'inlineStr') { $val=''; foreach ($c->getElementsByTagName('t') as $n) $val .= $n->textContent; }
            else { $v = $c->getElementsByTagName('v')->item(0); $val = $v ? $v->textContent : '';
                   if ($t === 's') $val = $ss[(int)$val] ?? ''; }
            $cells[$col-1] = $val;
        }
        $all[] = $cells;
    }
    return $rows = array_slice($all, 1);
}

/** الإدارةُ المالكة ← أدوارُها (نسخةُ المُطبِّق — تطابق dept_roles في أداة الدلتا) */
function dept_roles_apply($dept)
{
    $map = array(
        'التشغيل' => array(1),
        'المبيعات' => array(2, 12), 'المبيعات والعقود' => array(2, 12),
        'المالية' => array(3, 17, 18, 19, 20, 21, 22),
        'المشغّلون' => array(4), 'القوى العاملة' => array(4),
        'الحركة والتشغيل' => array(6, 5), 'الموقع' => array(6, 5),
        'الصيانة' => array(7),
        'الأسطول' => array(8),
        'الموارد البشرية' => array(10, 14),
        'الموردون' => array(11),
        'النقل' => array(13, 23), 'النقل والترحيل' => array(13, 23),
        'الحوكمة' => array(15), 'الصلاحيات' => array(15),
        'المشتريات' => array(16, 25), 'المشتريات والمخازن' => array(16, 25), 'المخازن' => array(16, 25),
        'البلاغات' => array(24), 'مركز البلاغات' => array(24),
        'التمويل' => array(26),
    );
    foreach ($map as $k => $v) if (mb_strpos($dept, $k) !== false) return $v;
    return array();
}
