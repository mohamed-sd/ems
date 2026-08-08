<?php
/**
 * Risk/risk_dept_gov.php — مخاطر الحوكمة والالتزام (M-14 · الشاشة ٣٢)
 * ─────────────────────────────────────────────────────────────────────────
 * ظهورٌ نطاقيٌّ للمكوّن الواحد «مساحة مخاطر الإدارة» بزاوية الحوكمة والالتزام
 * (RU-10 · RU-11) — والسجلُّ مركزيٌّ واحد (RK-02). حقُّ الإدارة: قراءةٌ +
 * الإبلاغُ عن إشارة (risk.gov.raise) + دليلُ ضابطٍ تملكه (risk.gov.evidence).
 */
require_once __DIR__ . '/_risk_common.php';

/* تثبيتُ الزاوية على وحدة «الحوكمة والالتزام» من الهيكل الحي — لا رقمًا صلبًا */
$__u = null;
$__st = $conn->prepare("SELECT unit_id FROM org_units WHERE company_id = ? AND unit_code = 'governance' AND active = 1 LIMIT 1");
$__st->bind_param('i', $company_id);
$__st->execute();
if ($__row = $__st->get_result()->fetch_assoc()) { $__u = (int) $__row['unit_id']; }
$__st->close();
if ($__u !== null) {
    $_GET['unit'] = (string) $__u;
    if (!$RISK_FULL) { $RISK_ORG_UNIT = $__u; }
}

require __DIR__ . '/dept_risk_space.php';
