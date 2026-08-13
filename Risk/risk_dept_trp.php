<?php
/**
 * Risk/risk_dept_trp.php — مخاطر النقل والترحيل
 * ─────────────────────────────────────────────────────────────────────────
 * ظهورٌ نطاقيٌّ للمكوّن الواحد «مساحة مخاطر الإدارة» بزاوية النقل والترحيل
 * (RU-05: مخاطر النقل والترحيل) — «مكوّنٌ واحدٌ يتغير نطاقُه وعنوانُه بحسب
 * الإدارة — لا ستَّ عشرةَ نسخةً ولا ستةَ عشرَ جدولًا» (INJAZ-UX-01 §4-3).
 * والسجلُّ مركزيٌّ واحد (RK-02) — حقُّ الإدارة قراءةٌ، والتعديلُ بطلبٍ لإدارة
 * المخاطر.
 */
require_once __DIR__ . '/_risk_common.php';

/* تثبيتُ الزاوية على وحدة «النقل والترحيل» من الهيكل الحي — لا رقمًا صلبًا */
$__u = null;
$__st = $conn->prepare("SELECT unit_id FROM org_units WHERE company_id = ? AND unit_code = 'transport' AND active = 1 LIMIT 1");
$__st->bind_param('i', $company_id);
$__st->execute();
if ($__row = $__st->get_result()->fetch_assoc()) { $__u = (int) $__row['unit_id']; }
$__st->close();
if ($__u !== null) {
    $_GET['unit'] = (string) $__u;              // للمحفظة الكاملة (الرئيس · المخاطر · السوبر)
    if (!$RISK_FULL) { $RISK_ORG_UNIT = $__u; } // ولغيرها زاويةُ الإدارة نفسُها
}

require __DIR__ . '/dept_risk_space.php';
