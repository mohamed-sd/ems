<?php
/**
 * includes/restricted_view.php — المنظرُ المقيَّدُ **خدمةٌ في الخادم** (سادسًا)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ نصُّ الطلب: «نقطةُ نهايةٍ تعيد `row_scope × field_allowlist` **وحدَه** ·
 *   ونطاقُ الصفِّ **يُحقن في شرطِ الاستعلامِ لا يُرشَّح بعد الجلب** · والتصديرُ
 *   **من الخدمةِ نفسِها** · والفحصُ **يقرأ الحمولةَ لا الشاشة**».
 *
 * ◆ **والعلّةُ التي يُغلقها**: «إخفاءُ العمودِ ليس منعًا». فلو أُرسلت الشاشةُ
 *   الأمُّ كاملةً ثم أُخفيت أعمدةٌ في المتصفح، **قُرئ المحظورُ من أدواتِ المتصفحِ
 *   ولو لم يُعرض** — وثبتَ في هذا المستودعِ من قبلُ أن عمودًا مخفيًّا وصلَ إلى
 *   ملفِّ التصدير.
 *
 * ◆ **وتقييدُ الحقلِ بلا تقييدِ الصفِّ انكشافٌ بثوبٍ آخر**: مديرُ موقعٍ يرى رقمَ
 *   العقدِ وتاريخَ سريانِه **لكلِّ عقودِ الشركة** بدل عقودِ موقعِه. فالنطاقان
 *   إلزاميّانِ معًا، ولا يُبنى استعلامٌ بلا شرطِ الصفّ.
 *
 * ◆ **والحقولُ الحساسةُ محجوبةٌ بنيويًّا** مهما أُعلنت في قائمة: السعرُ والهامشُ
 *   والتكلفةُ والأجرُ الفرديُّ وشروطُ الدفعِ **لا تخرج من مالكِها ولو في منظرٍ
 *   مقيَّد** — فتُطرح من القائمةِ قبلَ بناءِ الاستعلامِ لا بعدَه.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (!function_exists('rv_conn')) {
    function rv_conn() { return isset($GLOBALS['conn']) && $GLOBALS['conn'] ? $GLOBALS['conn'] : null; }
}

if (!function_exists('rv_sensitive_patterns')) {
    /**
     * أنماطُ الحقولِ المحجوبةِ بنيويًّا — **حاجزٌ فوقَ القائمةِ لا بديلٌ عنها**.
     * ◆ تُطبَّق على اسمِ العمودِ نفسِه: فحقلٌ اسمُه `unit_price` محجوبٌ ولو أدرجَه
     *   مؤلِّفُ المنظرِ سهوًا. **والسهوُ في قائمةٍ يدويةٍ مسألةُ وقتٍ لا احتمال.**
     */
    function rv_sensitive_patterns() {
        return array('price', 'cost', 'margin', 'salary', 'wage', 'rate_', 'payment_terms',
                     'discount', 'profit', 'net_amount', 'unit_rate', 'tariff');
    }
}

if (!function_exists('rv_is_sensitive')) {
    function rv_is_sensitive($col) {
        $c = mb_strtolower(trim((string) $col));
        foreach (rv_sensitive_patterns() as $p) { if (strpos($c, $p) !== false) { return true; } }
        return false;
    }
}

if (!function_exists('rv_definition')) {
    /** تعريفُ منظرٍ مقيَّدٍ من السجل — أو null. */
    function rv_definition($viewKey) {
        $conn = rv_conn();
        if (!$conn) { return null; }
        $st = mysqli_prepare($conn, "SELECT view_key, source_table, owner_dept_ar, consumer_space,
                                            purpose_ar, row_scope_col, field_allowlist, allow_export, active
                                       FROM gov_restricted_views WHERE view_key = ? AND active = 1 LIMIT 1");
        if (!$st) { return null; }
        mysqli_stmt_bind_param($st, 's', $viewKey);
        mysqli_stmt_execute($st);
        $res = mysqli_stmt_get_result($st);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($st);
        return $row ?: null;
    }
}

if (!function_exists('rv_fetch')) {
    /**
     * الحمولةُ المقيَّدة — **الحقولُ المسموحةُ فقط والصفوفُ المنطاقةُ فقط**.
     *
     * @return array{ok:bool,code:string,msg:string,fields:array,rows:array,dropped:array}
     */
    function rv_fetch($viewKey, $scopeValue, $limit = 200) {
        $out = array('ok' => false, 'code' => 'RV-404', 'msg' => '', 'fields' => array(),
                     'rows' => array(), 'dropped' => array());
        $conn = rv_conn();
        if (!$conn) { $out['code'] = 'RV-500'; $out['msg'] = 'لا اتصال'; return $out; }

        $d = rv_definition($viewKey);
        if (!$d) { $out['msg'] = 'منظرٌ غيرُ مسجَّلٍ أو معطَّل: ' . $viewKey; return $out; }

        /* أعمدةُ المصدرِ الحقيقيةُ — فلا يُبنى استعلامٌ باسمِ عمودٍ لا وجودَ له */
        $real = array();
        $st = mysqli_prepare($conn, "SELECT COLUMN_NAME FROM information_schema.COLUMNS
                                      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
        if ($st) {
            mysqli_stmt_bind_param($st, 's', $d['source_table']);
            mysqli_stmt_execute($st);
            $r = mysqli_stmt_get_result($st);
            while ($r && ($x = mysqli_fetch_row($r))) { $real[mb_strtolower($x[0])] = $x[0]; }
            mysqli_stmt_close($st);
        }
        if (!$real) { $out['code'] = 'RV-500'; $out['msg'] = 'جدولُ المصدرِ مجهول'; return $out; }

        /* ① قائمةُ الحقولِ — ثم **الحاجزُ البنيويُّ فوقَها** */
        $want = array_filter(array_map('trim', explode(',', (string) $d['field_allowlist'])));
        $fields = array();
        foreach ($want as $f) {
            $k = mb_strtolower($f);
            if (!isset($real[$k])) { $out['dropped'][] = $f . ' (لا وجودَ له)'; continue; }
            if (rv_is_sensitive($k)) { $out['dropped'][] = $f . ' (**محجوبٌ بنيويًّا**)'; continue; }
            $fields[] = $real[$k];
        }
        if (!$fields) { $out['code'] = 'RV-422'; $out['msg'] = 'لا حقلَ مسموحًا بعدَ الحجبِ البنيوي'; return $out; }

        /* ② نطاقُ الصفِّ **في شرطِ الاستعلام** — ولا منظرَ بلا نطاقِ صفّ */
        $scopeCol = mb_strtolower(trim((string) $d['row_scope_col']));
        if ($scopeCol === '' || !isset($real[$scopeCol])) {
            $out['code'] = 'RV-422';
            $out['msg'] = 'لا نطاقَ صفٍّ صالحٌ — **وتقييدُ الحقلِ بلا تقييدِ الصفِّ انكشافٌ بثوبٍ آخر**';
            return $out;
        }
        $co = isset($_SESSION['user']['company_id']) ? (int) $_SESSION['user']['company_id'] : 0;
        $hasCo = isset($real['company_id']);

        $cols = '`' . implode('`,`', $fields) . '`';
        $sql  = "SELECT {$cols} FROM `{$d['source_table']}` WHERE `{$real[$scopeCol]}` = ?"
              . ($hasCo ? " AND `company_id` = ?" : '')
              . " LIMIT " . max(1, min(1000, (int) $limit));
        $st = mysqli_prepare($conn, $sql);
        if (!$st) { $out['code'] = 'RV-500'; $out['msg'] = mysqli_error($conn); return $out; }
        if ($hasCo) { mysqli_stmt_bind_param($st, 'si', $scopeValue, $co); }
        else { mysqli_stmt_bind_param($st, 's', $scopeValue); }
        mysqli_stmt_execute($st);
        $res = mysqli_stmt_get_result($st);
        while ($res && ($row = mysqli_fetch_assoc($res))) { $out['rows'][] = $row; }
        mysqli_stmt_close($st);

        $out['ok'] = true; $out['code'] = 'RV-200';
        $out['fields'] = $fields;
        $out['msg'] = 'منظرٌ مقيَّدٌ: ' . count($fields) . ' حقلًا · ' . count($out['rows']) . ' صفًّا في النطاق';
        return $out;
    }
}

if (!function_exists('rv_export')) {
    /**
     * التصديرُ **من الخدمةِ نفسِها** — لا من الشاشةِ الأمّ.
     * ◆ فالعمودُ المخفيُّ لا يبلغ ملفَّ التصديرِ لأنه **لم يُجلب أصلًا**.
     */
    function rv_export($viewKey, $scopeValue, $limit = 1000) {
        $d = rv_definition($viewKey);
        if (!$d) { return array('ok' => false, 'code' => 'RV-404', 'csv' => ''); }
        if ((int) $d['allow_export'] !== 1) {
            return array('ok' => false, 'code' => 'RV-403',
                         'msg' => 'التصديرُ غيرُ مسموحٍ لهذا المنظر', 'csv' => '');
        }
        $r = rv_fetch($viewKey, $scopeValue, $limit);
        if (!$r['ok']) { return array('ok' => false, 'code' => $r['code'], 'msg' => $r['msg'], 'csv' => ''); }
        $csv = implode(',', $r['fields']) . "\n";
        foreach ($r['rows'] as $row) {
            $line = array();
            foreach ($r['fields'] as $f) {
                $v = isset($row[$f]) ? (string) $row[$f] : '';
                $line[] = (strpos($v, ',') !== false || strpos($v, '"') !== false)
                    ? '"' . str_replace('"', '""', $v) . '"' : $v;
            }
            $csv .= implode(',', $line) . "\n";
        }
        return array('ok' => true, 'code' => 'RV-200', 'csv' => $csv, 'fields' => $r['fields']);
    }
}
