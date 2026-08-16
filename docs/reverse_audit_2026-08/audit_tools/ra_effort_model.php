<?php
/**
 * ra_effort_model.php — نموذجُ الصعوبةِ والجهدِ · مصدرٌ واحدٌ لا يتفرَّق
 * ═══════════════════════════════════════════════════════════════════════════
 * كلُّ أداةٍ تُقدِّرُ جهدًا تُضمِّنُ هذا الملف. تعريفانِ للمقياسِ الواحدِ يتفرّقانِ حتمًا.
 * ◆ السلَّم: XS<نصف يوم · S يوم–يومان · M ٣–٥ · L ٦–١٠ · XL >١٠ أو تغييرٌ معماري
 * ◆ الأيامُ نقطةُ منتصفِ المدى، و«يوم-شخص» لا «سريع» ولا «أسبوعان».
 */
declare(strict_types=1);

const RA_DAYS = ['XS' => 0.4, 'S' => 1.5, 'M' => 4.0, 'L' => 8.0, 'XL' => 14.0];
const RA_ORDER = ['XS' => 0, 'S' => 1, 'M' => 2, 'L' => 3, 'XL' => 4];

/** صعوبةُ كلِّ نوعِ فجوةٍ — مبنيةٌ على ما يلزمُ فعلًا لإغلاقِه */
const RA_CX_BY_KIND = [
    'Missing Screen'          => 'L',   // شاشةٌ كاملةٌ بحارسٍ وخدمةٍ واختبار
    'Event/Integration Gap'   => 'M',   // ناشرٌ ومستهلكٌ وعطالةٌ وعكس
    'Governance Gap'          => 'M',
    'Risk/Governance Gap'     => 'M',
    'Data Mismatch'           => 'M',
    'Export/Import Gap'       => 'M',
    'Wrong Workflow'          => 'M',
    'Permission Gap'          => 'S',   // منحةٌ وحارسٌ واختبارٌ سلبي
    'Missing Evidence'        => 'S',   // كتابةُ شاهدٍ — لا تمسُّ المنتج
    'Not Testable'            => 'S',   // تهيئةُ مسبارٍ أو حسابِ دور
    'Broken Button'           => 'S',   // ربطُ معالجٍ ومسارٍ مسجَّل
    'Duplicate Screen'        => 'S',   // دمجٌ وتحويلٌ وإزالةُ رابط
    'Wrong Owner'             => 'S',   // نقلُ ملكيةٍ وتصحيحُ منحةٍ ومجموعة
    'Wrong Label'             => 'XS',  // نصٌّ
    'Wrong Sidebar Placement' => 'XS',  // موضعٌ في المصدرِ الموحد
    'UX/UI Defect'            => 'XS',
];

/** الصعوبةُ النهائيةُ لبندٍ: نوعُه، ثم درجةٌ أعلى إن كان P0 (الحاجبُ يلزمُه شاهدٌ ومراجعة) */
function ra_complexity(string $kind, string $severity): string {
    $c = RA_CX_BY_KIND[$kind] ?? 'M';
    if ($severity === 'P0' && RA_ORDER[$c] < RA_ORDER['XL']) {
        $c = array_search(RA_ORDER[$c] + 1, RA_ORDER, true);
    }
    return $c;
}

function ra_days(string $kind, string $severity): float {
    return RA_DAYS[ra_complexity($kind, $severity)];
}
