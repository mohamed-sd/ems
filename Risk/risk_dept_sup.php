<?php
/**
 * Risk/risk_dept_sup.php — مخاطرُ الموردين (M-09 · risk_dept_sup)
 * ─────────────────────────────────────────────────────────────────────────
 * ظهورٌ نطاقيٌّ للمكوّنِ الواحدِ «مساحة مخاطر الإدارة» بزاويةِ الموردين.
 * حقُّ الإدارة: قراءةٌ + الإبلاغُ (risk.sup.raise) + دليلُ ضابطٍ تملكه
 * (risk.sup.evidence). السجلُّ مركزيٌّ واحد (RK-02).
 * ◆ وحدةُ الموردين في الهيكلِ الحيِّ رمزُها فارغٌ — فالتثبيتُ باسمِها لا برمزِها.
 */
require_once __DIR__ . '/_risk_common.php';

$__u = null;
$__st = $conn->prepare("SELECT unit_id FROM org_units WHERE company_id = ? AND name_ar = 'الموردون' AND active = 1 LIMIT 1");
$__st->bind_param('i', $company_id);
$__st->execute();
if ($__row = $__st->get_result()->fetch_assoc()) { $__u = (int) $__row['unit_id']; }
$__st->close();
if ($__u !== null) {
    $_GET['unit'] = (string) $__u;
    if (!$RISK_FULL) { $RISK_ORG_UNIT = $__u; }
}

require __DIR__ . '/dept_risk_space.php';

/* حزمةُ الحالاتِ الدنيا (بوابة ٩): تحميلٌ وفراغٌ وخطأٌ — مخفيةٌ افتراضًا
   ويُظهرها منطقُ الشاشةِ عند حالِها. الدالةُ من ux_components التي تُحمِّلها القشرة. */
if (function_exists('ems_states_bundle')) {
    echo ems_states_bundle('لا بياناتِ مخاطرَ لهذه الإدارةِ بعد',
                           'تُسجَّل المخاطرُ من سجلِّها المركزيِّ فتظهر هنا');
}
