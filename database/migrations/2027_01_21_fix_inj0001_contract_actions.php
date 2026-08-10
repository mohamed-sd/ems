<?php
/**
 * 2027_01_21_fix_inj0001_contract_actions.php
 * ═══════════════════════════════════════════════════════════════════════════
 * INJ-0001 (P0) — «آلةُ حالةِ العقدِ بُنيت كاملةً ومستدعيها الوحيدُ شاشةُ القمة».
 * تسجيلُ فعلَي نقلِ الحالةِ في قاموسِ الأفعال (CS-06) — وغيرُ المسجَّلِ يُحجب.
 */
if (PHP_SAPI !== 'cli') { exit(1); }
error_reporting(E_ALL & ~E_DEPRECATED);
mysqli_report(MYSQLI_REPORT_OFF);
require_once dirname(__DIR__, 2) . '/includes/env.php';

$db = new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'), ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($db->connect_errno) { fwrite(STDERR, 'اتصال المرحِّل فشل: ' . $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');

$A = array(
    array('contract.submit_negotiation', 'رفعُ العقدِ للتفاوض',
        'contracts · contract_events', 'ContractSubmittedForNegotiation',
        'مسودة ← تفاوض عبر ContractStateMachine — والانتقالُ محكومٌ بجدولِ التحولاتِ لا برأي الشاشة',
        'العودةُ إلى مسودةٍ انتقالٌ معلَنٌ في الآلةِ (تفاوض ← مسودة) لا حذف'),
    array('contract.approve', 'اعتمادُ العقدِ ضمنَ السقف',
        'contracts · contract_events', 'ContractApproved',
        'تفاوض ← معتمد بحارسِ سقفٍ يقارن قيمةَ العقدِ بسقفِ المخوَّلِ ويُصعّد عند التجاوز — والمعتمدُ يظهر فورًا في قائمةِ توقيعِ القمة',
        'العودةُ إلى التفاوضِ انتقالٌ معلَنٌ في الآلة (معتمد ← تفاوض) — ولا حذفَ لحالة'),
);

$db->begin_transaction();
try {
    $n = 0;
    foreach ($A as $a) {
        list($code, $label, $writes, $event, $effect, $reverse) = $a;
        $st = $db->prepare("SELECT COUNT(*) FROM nav09_action_map WHERE canonical_code = ?");
        $st->bind_param('s', $code);
        $st->execute();
        $exists = (int) $st->get_result()->fetch_row()[0];
        $st->close();
        if ($exists > 0) { echo "[INJ-0001] موجودٌ سلفًا: {$code}\n"; continue; }

        $screen = 'عقود العملاء';
        $file   = 'contracts.php';
        $actor  = 'المبيعات والعقود';
        $consumers = 'الرئاسة التنفيذية · المالية · الحوكمة';
        $live   = 'page:Contracts/contracts.php';
        $guardEv = 'العقدُ السبعيُّ في includes/post_contract.php + حارسُ الشاشة + حارسُ السقفِ في ContractApprovalService (فشلٌ مغلق: لا سقفَ معرَّفٌ ⇒ تصعيدٌ لا سماح)';
        $idemEv  = 'مفتاحٌ مركَّبٌ من (العقد × الحالةِ الهدف) + عطالةُ آلةِ الحالةِ نفسِها (الانتقالُ إلى الحالةِ ذاتِها لا شيء)';
        $class   = 'domain_write';

        $st = $db->prepare("INSERT INTO nav09_action_map
              (canonical_code, label_ar, screen_title, canonical_file, actor_ar, writes_text, event_name,
               consumers_text, effect_text, reverse_text, live_code, state, guard_verified, guard_evidence,
               idempotency_verified, idempotency_evidence, uat_verified, uat_evidence, write_class, updated_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?, 'bound_page', 'yes', ?, 'yes', ?, 'pending', '', ?, NOW())");
        if (!$st) { throw new RuntimeException('prepare: ' . $db->error); }
        $st->bind_param('ssssssssssssss', $code, $label, $screen, $file, $actor, $writes, $event,
            $consumers, $effect, $reverse, $live, $guardEv, $idemEv, $class);
        if (!$st->execute()) { throw new RuntimeException('insert: ' . $st->error); }
        $st->close();
        $n++;
    }
    $db->commit();
    echo "[INJ-0001] أفعالٌ مُسجَّلة: {$n}\n";
} catch (Throwable $e) {
    $db->rollback();
    throw $e;
}

require_once dirname(__DIR__, 2) . '/includes/post_contract.php';
foreach ($A as $a) {
    if (ems_pc_action_registered($db, $a[0]) !== true) { throw new RuntimeException('العقدُ لم يجد: ' . $a[0]); }
}
echo "[INJ-0001] الإثباتُ الوظيفي: العقدُ يجد الفعلين ✔\n";

/* ── إعلانُ حالةِ سقوفِ السلطة (شرطُ عملِ الاعتماد) ────────────────────── */
$caps = (int) $db->query("SELECT COUNT(*) FROM fin_authority_caps WHERE active = 1")->fetch_row()[0];
echo "[INJ-0001] سقوفُ السلطةِ النافذة: {$caps}\n";
if ($caps === 0) {
    echo "[INJ-0001] ⚠ لا سقفَ معرَّفٌ — فكلُّ اعتمادٍ **يُصعَّد** (فشلٌ مغلق: غيابُ الحدِّ ليس إذنًا).\n";
    echo "           تعريفُ السقوفِ قرارُ مالكٍ لا كود — يُرفع في سجلِّ القرارات.\n";
}
