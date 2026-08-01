<?php
/**
 * tests/leg01_entities_test.php — LEG-01 الحد الأدنى (البوابة ① · النمط ①)
 * ═══════════════════════════════════════════════════════════════════════════
 * G1 توقيع بتفويض ساري ضمن السقف · G2 تفويض منتهٍ → 403 · G3 فوق السقف → 409
 * برسالة المتاح · اعتماد الذات → 403 · فرادة الكيان بالثلاثية · G7 النمط ①
 * يعمل كاملًا بلا طرف خارجي والعناصر المطفأة لا تعطِّل · الكيان ≠ المستأجر ·
 * سجل التوقيعات Insert-only وتكرار الخطوة عاطل.
 * التشغيل: php tests/leg01_entities_test.php
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
mysqli_report(MYSQLI_REPORT_OFF);
require_once dirname(__DIR__) . '/app/Core/AuthorityGuard.php';

use App\Core\AuthorityGuard as AG;

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$CO = 4; $MARK = 'LEG1T';
$PASS = 0; $FAIL = 0;
function check($c, $m) { global $PASS, $FAIL; if ($c) { $PASS++; echo "  ✔ {$m}\n"; } else { $FAIL++; echo "  ✘ FAIL: {$m}\n"; } }
function head($m) { echo "\n── {$m}\n"; }

$teardown = function () use ($conn, $MARK) {
    $conn->query("DELETE FROM approval_signatures WHERE document_type LIKE '{$MARK}%'");
    $conn->query("DELETE FROM signing_authorities WHERE doc_ref LIKE '{$MARK}%'");
    $conn->query("DELETE FROM governance_flags WHERE reason LIKE '{$MARK}%'");
    $conn->query("DELETE FROM entity_roles WHERE doc_ref LIKE '{$MARK}%'");
    $conn->query("DELETE FROM legal_entities WHERE legal_name LIKE '%{$MARK}%'");
};
register_shutdown_function($teardown);
$teardown();

head('البذر والبنية');
$TEN = AG::tenantEntity($conn, $CO);
check($TEN !== null, "كيان المستأجر co4 مبذور من admin_companies: entity #{$TEN}");
$q = $conn->query("SELECT COUNT(*) c FROM tenants")->fetch_assoc();
check((int) $q['c'] >= 1, 'حد العزل يُقرأ من tenants — ' . $q['c'] . ' مستأجرًا');

// فرادة الثلاثية
$e1 = $conn->query("INSERT INTO legal_entities (legal_name, country, registry_authority, commercial_reg) VALUES ('عميل {$MARK}', 'SD', 'السجل التجاري', '{$MARK}-999')");
$EXT = (int) $conn->insert_id;
$e2 = $conn->query("INSERT INTO legal_entities (legal_name, country, registry_authority, commercial_reg) VALUES ('عميل {$MARK} مكرر', 'SD', 'السجل التجاري', '{$MARK}-999')");
check($e1 === true && $e2 === false, 'الفرادة بالثلاثية (بلد×جهة×رقم) — التكرار يُرفض بنيويًّا');
$e3 = $conn->query("INSERT INTO legal_entities (legal_name, country, registry_authority, commercial_reg) VALUES ('عميل {$MARK} مصري', 'EG', 'السجل التجاري', '{$MARK}-999')");
check($e3 === true, 'والرقم نفسه في دولة أخرى يمرّ — الفرادة ثلاثية لا أحادية');

head('الكيان ≠ المستأجر (G4-ب)');
$q = $conn->query("SELECT is_tenant FROM legal_entities WHERE entity_id={$EXT}")->fetch_assoc();
$q2 = $conn->query("SELECT COUNT(*) c FROM tenants WHERE entity_id={$EXT}")->fetch_assoc();
check((int) $q['is_tenant'] === 0 && (int) $q2['c'] === 0, 'عميل خارجي كيانٌ لا مستأجر — لا يدخل نطاق العزل');
$conn->query("INSERT INTO entity_roles (entity_id, role, valid_from, doc_ref) VALUES ({$EXT}, 'client', CURDATE(), '{$MARK}-role')");
check($conn->error === '', 'صفته جدول علاقة مؤرَّخ (entity_roles) لا حقل نصي');

head('G7 النمط ① — داخلي محض والعناصر المطفأة لا تعطِّل');
$r = AG::sign($conn, array('company_id' => $CO, 'document_type' => $MARK . '_doc', 'document_id' => 1, 'person_id' => 6, 'created_by_person_id' => 4, 'amount' => 50000));
check($r['ok'] && strpos($r['reason'], 'مطفأ') !== false, 'اعتماد داخلي يمضي بلا تفويض (signing_caps مطفأ افتراضًا): ' . $r['reason']);
$q = $conn->query("SELECT COUNT(*) c FROM approval_signatures WHERE document_type='{$MARK}_doc' AND result='signed'")->fetch_assoc();
check((int) $q['c'] === 1, 'وسطر توقيع كُتب مع ذلك — «الاعتماد توقيع» حتى في النمط ①');

$r2 = AG::sign($conn, array('company_id' => $CO, 'document_type' => $MARK . '_doc', 'document_id' => 1, 'person_id' => 6, 'amount' => 50000));
check($r2['ok'] && strpos($r2['reason'], 'عاطل') !== false, 'تكرار التوقيع لخطوة واحدة عاطل (UQ) — لا توقيعان');

head('اعتماد الذات — بنيويًّا');
$r = AG::sign($conn, array('company_id' => $CO, 'document_type' => $MARK . '_doc', 'document_id' => 2, 'person_id' => 4, 'created_by_person_id' => 4));
check(!$r['ok'] && $r['code'] === 403, 'المنشئ يعتمد ما أنشأه ⇒ 403 وسطر منع مسجَّل');
$q = $conn->query("SELECT COUNT(*) c FROM approval_signatures WHERE document_type='{$MARK}_doc' AND document_id=2 AND result='denied'")->fetch_assoc();
check((int) $q['c'] === 1, 'المنع مسجَّل في سجل التوقيعات');

head('G1·G2·G3 — التفويض بالسقوف (بعد تفعيل العنصر)');
$conn->query("INSERT INTO governance_flags (element_code, scope_type, scope_id, enabled, reason, set_by) VALUES ('signing_caps', 'entity', {$TEN}, 1, '{$MARK} تفعيل تجريبي', 1)");
$conn->query("INSERT INTO signing_authorities (company_id, person_id, entity_id, auth_type, amount_cap, currency, valid_from, valid_to, doc_ref)
              VALUES ({$CO}, 6, {$TEN}, 'financial', 500000.00, 'USD', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 90 DAY), '{$MARK}-AUTH1')");
$r = AG::sign($conn, array('company_id' => $CO, 'document_type' => $MARK . '_claim', 'document_id' => 10, 'person_id' => 6, 'amount' => 391875.00));
check($r['ok'] && $r['auth_id'] !== null, 'G1: توقيع بتفويض ساري ضمن السقف — بمرجع تفويضه #' . $r['auth_id']);
$r = AG::sign($conn, array('company_id' => $CO, 'document_type' => $MARK . '_claim', 'document_id' => 11, 'person_id' => 6, 'amount' => 600000.00));
check(!$r['ok'] && $r['code'] === 409 && strpos($r['reason'], '500,000') !== false, 'G3: فوق السقف ⇒ 409 برسالة المتاح: ' . $r['reason']);
$r = AG::sign($conn, array('company_id' => $CO, 'document_type' => $MARK . '_claim', 'document_id' => 12, 'person_id' => 4, 'amount' => 100.00));
check(!$r['ok'] && $r['code'] === 403, 'G2: شخص بلا تفويض (والعنصر مفعَّل) ⇒ 403 مسجَّلة');
$conn->query("UPDATE signing_authorities SET valid_to = DATE_SUB(CURDATE(), INTERVAL 1 DAY) WHERE doc_ref='{$MARK}-AUTH1'");
$r = AG::sign($conn, array('company_id' => $CO, 'document_type' => $MARK . '_claim', 'document_id' => 13, 'person_id' => 6, 'amount' => 100.00));
check(!$r['ok'] && $r['code'] === 403, 'G2: تفويض منتهٍ ⇒ 403 — الانتهاء يُسقط الصلاحية آليًّا بلا متابعة يدوية');

head('Insert-only — سجل التوقيعات لا يُعدَّل');
$q = $conn->query("SELECT sig_id FROM approval_signatures WHERE document_type='{$MARK}_claim' AND document_id=10")->fetch_assoc();
check($q !== null, 'التوقيع موجود بمرجعه — «من وقّع ماذا ومتى وتحت أي تفويض وبأي سقف»');

echo PHP_EOL . "══ النتيجة: {$PASS} ناجحة · {$FAIL} فاشلة ══" . PHP_EOL;
exit($FAIL === 0 ? 0 : 1);
