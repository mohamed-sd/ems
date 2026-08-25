<?php
/**
 * includes/fin_event_source_guard.php — مرجعُ الحدثِ الماليِّ يُحَلُّ إلى صفٍّ حقيقي
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ INJ-0176
 *
 * **نصُّ القبول**: «POST لإنشاء حدثٍ ماليٍّ **بمرجعٍ نصيٍّ لا يقابله صفٌّ في جدول
 * المصدر** يُرفض 422 برمزٍ محكوم، **ولا يُنشأ أي صف**».
 *
 * والمقيسُ قبلَه: `source_ref` نصٌّ حرٌّ لا يُتحقَّق منه — فيُفتح حدثٌ ماليٌّ على
 * أمرِ صيانةٍ أو ورديةٍ **لا وجودَ لها**، ثم تُبنى عليه مروحةُ الأثرِ والقيدُ
 * والذمّة. وهو عينُ عيبِ الذمّةِ الذي عولج في `receivable_source_guard.php` —
 * فبُني هذا بالنمطِ نفسِه لأن **الحدثَ ليس ذمّةً**: جداولُ مصادرِه أخرى.
 *
 * ── ثلاثةُ ضوابط ────────────────────────────────────────────────────────────
 *   ① **سجلٌّ واحدٌ** لكلِّ إدارةٍ وجدولِها وعمودِ مرجعِها — لا شرطَ متناثرٌ في
 *      الشاشات، فتُنسى إدارةٌ ويبقى بابُها مفتوحًا.
 *   ② **الرقمُ والنصُّ كلاهما مقبولان** مرجعًا: كثيرٌ من المراجعِ معرّفاتٌ رقميةٌ
 *      وبعضُها أكوادٌ نصيّة — فيُجَسُّ العمودان.
 *   ③ **إدارةٌ بلا جدولٍ مُعرَّفٍ تُعلَن ولا تُمنع**: منعُ ما لا نملك جدولَه
 *      يوقف عملًا مشروعًا؛ والإعلانُ يجعل النقصَ مرئيًّا ليُسدّ.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (!function_exists('ems_fin_event_source_registry')) {
    /** الإدارةُ ⇐ جدولُها وعمودُ مرجعِها. `null` = لا جدولَ مُعرَّفٌ بعد. */
    function ems_fin_event_source_registry()
    {
        return array(
            'sales'       => array('table' => 'contracts',        'id_col' => 'id',  'ref_col' => 'contract_no'),
            'suppliers'   => array('table' => 'supplier_settlements', 'id_col' => 'id', 'ref_col' => 'settlement_no'),
            'workforce'   => array('table' => 'payroll_runs',     'id_col' => 'id',  'ref_col' => null),
            'procurement' => array('table' => 'proc_order',       'id_col' => 'id',  'ref_col' => 'code'),
            'warehouse'   => array('table' => 'proc_stock_move',  'id_col' => 'id',  'ref_col' => null),
            'maintenance' => array('table' => 'mnt_order',        'id_col' => 'id',  'ref_col' => 'order_no'),
            'projects'    => array('table' => 'project',          'id_col' => 'id',  'ref_col' => null),
            'revenue'     => array('table' => 'tax_invoices',     'id_col' => 'id',  'ref_col' => 'serial_no'),
            'assets'      => array('table' => 'equipments',       'id_col' => 'id',  'ref_col' => 'code'),
            'treasury'    => array('table' => 'fin_payments',     'id_col' => 'id',  'ref_col' => 'payment_no'),
        );
    }
}

if (!function_exists('ems_fin_event_resolve_source')) {
    /**
     * يحلُّ مرجعَ الحدثِ إلى صفٍّ قائمٍ في جدولِ إدارتِه.
     *
     * @return array{ok:bool, code:int, reason:string, source_doc_id:?int, declared:bool}
     */
    function ems_fin_event_resolve_source(\mysqli $conn, $companyId, $sourceModule, $sourceRef)
    {
        $out = array('ok' => false, 'code' => 422, 'reason' => '',
                     'source_doc_id' => null, 'declared' => false);
        $companyId = (int) $companyId;
        $sourceModule = (string) $sourceModule;
        $sourceRef = trim((string) $sourceRef);

        $reg = ems_fin_event_source_registry();
        if (!isset($reg[$sourceModule])) {
            $out['reason'] = 'إدارة مصدر غير معروفة — والحدث لا يفتح على إدارة لا سجل لها';
            return $out;
        }
        if ($sourceRef === '') {
            $out['reason'] = '422 مرجع المصدر إلزامي — لا حدث مالي بلا واقعة تسنده';
            return $out;
        }
        $def = $reg[$sourceModule];
        if (empty($def['table'])) {
            /* إدارةٌ بلا جدولٍ مُعرَّفٍ: تُعلَن ولا تُمنع */
            $out['ok'] = true; $out['code'] = 200; $out['declared'] = true;
            $out['reason'] = 'لا جدول مصدر معرف لهذه الإدارة بعد — معلن لا متحقق منه';
            return $out;
        }
        /* أموجودٌ الجدولُ أصلًا؟ جدولٌ غائبٌ لا يُدين مستخدمًا */
        $chk = $conn->query("SELECT 1 FROM information_schema.TABLES
                              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '"
                            . $conn->real_escape_string($def['table']) . "' LIMIT 1");
        if (!$chk || !$chk->fetch_row()) {
            $out['ok'] = true; $out['code'] = 200; $out['declared'] = true;
            $out['reason'] = 'جدول المصدر `' . $def['table'] . '` غير موجود — معلن لا متحقق منه';
            return $out;
        }

        /* أعمدةُ العزلِ ليست في كلِّ جدولٍ — فتُسأل البنيةُ لا تُفترض */
        $hasCompany = false;
        $c = $conn->query("SELECT 1 FROM information_schema.COLUMNS
                            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '"
                          . $conn->real_escape_string($def['table']) . "' AND COLUMN_NAME = 'company_id' LIMIT 1");
        if ($c && $c->fetch_row()) { $hasCompany = true; }

        $where = array();
        $isNum = ctype_digit($sourceRef);
        if ($isNum) { $where[] = '`' . $def['id_col'] . '` = ' . (int) $sourceRef; }
        if (!empty($def['ref_col'])) {
            $where[] = '`' . $def['ref_col'] . "` = '" . $conn->real_escape_string($sourceRef) . "'";
        }
        if (!$where) {
            $out['reason'] = '422 المرجع لا يطابق معرفا رقميا، ولا عمود كود في `' . $def['table'] . '`';
            return $out;
        }
        $sql = 'SELECT `' . $def['id_col'] . '` FROM `' . $def['table'] . '` WHERE ('
             . implode(' OR ', $where) . ')'
             . ($hasCompany ? ' AND company_id = ' . $companyId : '') . ' LIMIT 1';
        $r = $conn->query($sql);
        if ($r && ($row = $r->fetch_row())) {
            $out['ok'] = true; $out['code'] = 200; $out['source_doc_id'] = (int) $row[0];
            return $out;
        }
        $out['reason'] = '422 المرجع «' . mb_substr($sourceRef, 0, 40) . '» لا يقابله صف في `'
                       . $def['table'] . '`' . ($hasCompany ? ' ضمن نطاق شركتك' : '')
                       . ' — ولا حدث مالي على واقعة لا وجود لها';
        return $out;
    }
}
