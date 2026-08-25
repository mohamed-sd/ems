<?php
/**
 * includes/m00_exec_helpers.php — عونُ شاشات M-00 (الإدارة التنفيذية)
 * ───────────────────────────────────────────────────────────────────────────
 * مشتركات الشاشات الخمس بعد لحاقها بجداولها الأصلية (هجرة 2026_11_14):
 *   • m00_norm()          تطبيع عربي خفيف للمقارنات
 *   • m00_review_block()  حارس BR-CEO-02: ملاحظة المراجعة الحرجة المفتوحة —
 *     ملاحظات «مراجعة العقود» ما زالت في مخزنها البيني (شاشة تملكها المبيعات
 *     — ليست من سجلات M-00 الخمسة) فيُقرأ حيث هي أيًّا كان موضعها.
 */

if (!function_exists('m00_norm')) {
    function m00_norm($s) {
        $s = preg_replace('/\s+/u', ' ', trim((string) $s));
        $s = str_replace(array('أ', 'إ', 'آ'), 'ا', $s);
        $s = str_replace('ة', 'ه', $s);
        $s = str_replace('ى', 'ي', $s);
        return preg_replace('/[]/u', '', $s);
    }
}

if (!function_exists('m00_review_block')) {
    /**
     * BR-CEO-02: أتحجب ملاحظةٌ حرجةٌ مفتوحةٌ توقيعَ هذا العقد؟
     * تُفحص ملاحظات «مراجعة العقود وملاحظاتها» على رقم العقد نفسه:
     * «يحجب الاعتماد؟» مثبتةً وبلا إقفال ⇒ يُعاد رقمُ الملاحظة الحاجبة.
     * @return string|null رقم الملاحظة الحاجبة أو null إن خلا العقد
     */
    function m00_review_block(mysqli $conn, $company_id, $contractNo) {
        $contractNo = trim((string) $contractNo);
        if ($contractNo === '') { return null; }
        // الموجة ٢ (2026-08-06): «مراجعة العقود» تحررت من المخزن البيني إلى
        // جدولها الأصلي scr_contract_review — والقراءة عبر محوّل السجل
        // بحمولة التسميات نفسها فمنطق الحارس لم يتغير.
        require_once __DIR__ . '/cmp03_local_store.php';
        $block = null;
        foreach (cmp03_store_rows($conn, 'contract_review.php', intval($company_id)) as $rv) {
            $p = $rv['payload'];
            $sameContract = trim((string) ($p['العقد'] ?? '')) === $contractNo;
            $blocks = mb_strpos((string) ($p['يحجب الاعتماد؟'] ?? ''), 'نعم') !== false
                   || mb_strpos((string) ($p['يحجب الاعتماد؟'] ?? ''), 'يحجب') !== false;
            $open = trim((string) ($p['تاريخ الإقفال'] ?? '')) === ''
                 && !in_array(trim((string) ($p['الحالة'] ?? '')), array('مقفل', 'مقفلة', 'مغلقة', 'معالجة'), true);
            if ($sameContract && $blocks && $open) { $block = (string) ($p['رقم الملاحظة'] ?? '؟'); break; }
        }
        return $block;
    }
}
