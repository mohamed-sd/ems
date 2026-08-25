<?php
/**
 * includes/receivable_source_guard.php — INJ-0036: لا ذمّةَ إلا على مستندٍ معتمَد
 * ═══════════════════════════════════════════════════════════════════════════
 * الحكم: «كلُّ ذمّةٍ تتولد من واقعةٍ (مستخلصٍ أو فاتورةٍ) اجتازت سلسلةَ اعتمادها.»
 * والقبول: «إنشاءُ ذمّةِ عميلٍ بمرجعٍ لا يقابله مستندٌ معتمدٌ **يُرفض 422**؛
 *           وكلُّ ذمّةٍ **تفتح فاتورتَها بنقرة**.»
 *
 * ◆ **القاعدةُ تحرس البنية** (`chk_recv_source_doc`: لا صفَّ بلا مفتاح)،
 *   **وهذا يحرس المعنى**: أن المفتاحَ يشير إلى مستندٍ **قائمٍ · لهذه الشركةِ ·
 *   وبحالةٍ معتمَدة**. فالقيدُ وحدَه يقبل مفتاحًا يشير إلى فاتورةٍ ملغاة.
 *
 * ◆ ولا يُخترع سجل: `invoice ⇐ tax_invoices` · `statement ⇐ fin_client_statements`،
 *   وكلاهما قائمٌ في القاعدة. ونوعٌ ثالثٌ يُرفض ولا يُخمَّن.
 *
 * ◆ والردُّ **رمزٌ وسببٌ** لا `false` مجرَّد — فالمنعُ الصامتُ يُقرأ عطلًا.
 */

if (!function_exists('ems_receivable_source_registry')) {
    /** سجلُّ المستنداتِ المقبولةِ لكلِّ نوعِ ذمّة — مصدرٌ واحدٌ لا يُكرَّر. */
    function ems_receivable_source_registry()
    {
        return array(
            'invoice' => array(
                'table' => 'tax_invoices', 'ref_col' => 'serial_no',
                'state_col' => 'state', 'state_ok' => array('issued'),
                'label' => 'فاتورة ضريبية صادرة',
                /* البابُ **مقيسٌ لا مخترَع**: `Contracts/tax_invoices.php` يقرأ
                   `?open=<id>` (السطر 67) ⇒ النقرةُ تفتح الفاتورةَ بعينها. */
                'open'  => '/ems/Contracts/tax_invoices.php?open=',
            ),
            'statement' => array(
                'table' => 'fin_client_statements', 'ref_col' => 'stmt_code',
                'state_col' => 'state', 'state_ok' => array('issued'),
                'label' => 'كشف حساب عميل صادر',
                /* ◆ **لا شاشةَ لكشوفِ العملاء اليوم**: `fin_client_statements`
                     لا يمسُّه إلا خدمةٌ وأداةٌ، ولا ملفَّ عرضٍ في `Finance/`.
                     فالبابُ `null` **إعلانًا** — ورابطٌ مخترَعٌ يقود إلى 404
                     أسوأُ من غيابه، لأنه يُقرأ «موجودٌ» وهو ليس كذلك. */
                'open'  => null,
            ),
        );
    }
}

if (!function_exists('ems_receivable_resolve_source')) {
    /**
     * يحلُّ مرجعَ المستندِ النصيَّ إلى **مفتاحٍ حقيقي**، أو يردُّ 422 بسببه.
     *
     * @return array{ok:bool,code:int,reason:string,source_doc_id:?int,label:string}
     */
    function ems_receivable_resolve_source(mysqli $conn, $companyId, $docType, $docRef)
    {
        $out = array('ok' => false, 'code' => 422, 'reason' => '',
                     'source_doc_id' => null, 'label' => '');
        $docType = (string) $docType;
        $docRef  = trim((string) $docRef);
        $companyId = (int) $companyId;

        $reg = ems_receivable_source_registry();
        if (!isset($reg[$docType])) {
            $out['reason'] = 'نوع مستند غير معروف — والذمة لا تفتح على نوع لا سجل له';
            return $out;
        }
        if ($docRef === '') {
            $out['reason'] = 'مرجع المستند إلزامي — لا ذمة بلا مستند يقابلها (INJ-0036)';
            return $out;
        }
        if ($companyId <= 0) {
            $out['reason'] = 'شركة الذمة غير محددة — ولا يقرأ الغياب إذنا';
            return $out;
        }

        $m = $reg[$docType];
        /* أسماءُ الجداولِ والأعمدةِ من السجلِّ أعلاه لا من مُدخَلِ المستخدم */
        $sql = "SELECT id, `{$m['state_col']}` AS st FROM `{$m['table']}`
                 WHERE `{$m['ref_col']}` = ? AND company_id = ? LIMIT 1";
        $st = $conn->prepare($sql);
        if (!$st) {
            error_log('ems_receivable_resolve_source: prepare — ' . $conn->error);
            return array('ok' => false, 'code' => 500, 'source_doc_id' => null, 'label' => '',
                         'reason' => 'تعذرت قراءة سجل المستندات — ولا يقرأ الفشل إذنا');
        }
        $st->bind_param('si', $docRef, $companyId);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();

        if (!$row) {
            $out['reason'] = 'لا يقابل هذا المرجع (' . $m['label'] . ') أي مستند في هذه الشركة — '
                           . 'والذمة أثر لواقعة لا مصدر لها (INJ-0036 · 422)';
            return $out;
        }
        if (!in_array((string) $row['st'], $m['state_ok'], true)) {
            $out['reason'] = 'المستند موجود لكن حالته «' . $row['st'] . '» — ولا تفتح ذمة '
                           . 'إلا على مستند اجتاز سلسلة اعتماده (INJ-0036 · 422)';
            return $out;
        }

        $out['ok'] = true; $out['code'] = 200;
        $out['source_doc_id'] = (int) $row['id'];
        $out['label'] = $m['label'];
        return $out;
    }
}

if (!function_exists('ems_receivable_source_link')) {
    /**
     * رابطُ فتحِ المستندِ بنقرة — الشقُّ الثاني من اختبارِ القبول.
     * والموروثُ (`legacy_no_ref`) يعود `null` **معلنًا** لا رابطًا كاذبًا.
     */
    function ems_receivable_source_link($docType, $sourceDocId)
    {
        $reg = ems_receivable_source_registry();
        $sourceDocId = (int) $sourceDocId;
        if ($sourceDocId <= 0 || !isset($reg[(string) $docType])) { return null; }
        $open = $reg[(string) $docType]['open'];
        if ($open === null) { return null; }   // نوعٌ لا شاشةَ له — يُعلَن لا يُخترع
        return $open . $sourceDocId;
    }
}
