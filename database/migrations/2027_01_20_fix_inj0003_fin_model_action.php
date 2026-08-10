<?php
/**
 * 2027_01_20_fix_inj0003_fin_model_action.php
 * ═══════════════════════════════════════════════════════════════════════════
 * INJ-0003 (P0) — «نماذجُ التمويل: مخزنٌ واحدٌ لا مخزنان».
 * تسجيلُ فعلِ الحفظِ في قاموسِ الأفعالِ (CS-06) — وغيرُ المسجَّلِ يُحجب.
 *
 * ◆ ولا تُرحَّل صفوفُ ‎scr_fin_models‎ آليًّا إلى جدولِ المجال: أعمدةُ المجالِ
 *   محكومةٌ بـENUM والمخزنُ البينيُّ نصٌّ حرّ — فالترحيلُ الأعمى يبتلع القيمَ
 *   الغريبةَ صامتًا. الصفوفُ الموروثةُ **تُعلَن** ليُعاد إدخالُها بقيمٍ محكومة.
 */
if (PHP_SAPI !== 'cli') { exit(1); }
error_reporting(E_ALL & ~E_DEPRECATED);
mysqli_report(MYSQLI_REPORT_OFF);
require_once dirname(__DIR__, 2) . '/includes/env.php';

$db = new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'), ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($db->connect_errno) { fwrite(STDERR, 'اتصال المرحِّل فشل: ' . $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');

$code = 'fin.model.upsert';
$st = $db->prepare("SELECT COUNT(*) FROM nav09_action_map WHERE canonical_code = ?");
$st->bind_param('s', $code);
$st->execute();
$exists = (int) $st->get_result()->fetch_row()[0];
$st->close();

if ($exists === 0) {
    $st = $db->prepare("INSERT INTO nav09_action_map
          (canonical_code, label_ar, screen_title, canonical_file, actor_ar, writes_text, event_name,
           consumers_text, effect_text, reverse_text, live_code, state, guard_verified, guard_evidence,
           idempotency_verified, idempotency_evidence, uat_verified, uat_evidence, write_class, updated_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?, 'bound_page', 'yes', ?, 'yes', ?, 'pending', '', ?, NOW())");
    if (!$st) { throw new RuntimeException('prepare: ' . $db->error); }
    $label = 'حفظُ نموذجِ تمويلٍ أو تحديثُه';
    $screen = 'نماذج التمويل ومعالجتها';
    $file = 'fin_models.php';
    $actor = 'التمويل والملكية';
    $writes = 'financing_models';
    $event = 'FinancingModelUpserted';
    $consumers = 'التمويل · المالية · الحوكمة';
    $effect = 'النموذجُ يصير متاحًا فورًا في إنشاءِ عمليةِ التمويل — مخزنٌ واحدٌ لا مخزنان (INJ-0003)';
    $reverse = 'التعطيلُ (active=0) — والسجلُّ باقٍ، لا حذفَ (CS-08)';
    $live = 'page:Financing/fin_models.php';
    $guardEv = 'العقدُ السبعيُّ + حارسُ الشاشةِ فوقَ المعالج · والقيمُ المحكومةُ تُرفض خارجَ قائمةِ ENUM ويُعاد قراءةُ المكتوبِ للتثبّت';
    $idemEv = 'مفتاحٌ مركَّبٌ من رمزِ النموذج + ON DUPLICATE KEY UPDATE على المفتاحِ الطبيعيِّ model_code';
    $class = 'domain_write';
    $st->bind_param('ssssssssssssss', $code, $label, $screen, $file, $actor, $writes, $event,
        $consumers, $effect, $reverse, $live, $guardEv, $idemEv, $class);
    if (!$st->execute()) { throw new RuntimeException('insert: ' . $st->error); }
    $st->close();
    echo "[INJ-0003] سُجِّل الفعل {$code} ✔\n";
} else {
    echo "[INJ-0003] الفعلُ مسجَّلٌ سلفًا — يُتخطّى\n";
}

/* ── إعلانُ الصفوفِ الموروثةِ في المخزنِ البينيّ (تُعلَن ولا تُرحَّل عمياءَ) ── */
$has = (int) $db->query("SELECT COUNT(*) FROM information_schema.TABLES
                          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'scr_fin_models'")->fetch_row()[0];
if ($has) {
    $n = (int) $db->query("SELECT COUNT(*) FROM scr_fin_models")->fetch_row()[0];
    $dom = (int) $db->query("SELECT COUNT(*) FROM financing_models")->fetch_row()[0];
    echo "[INJ-0003] صفوفُ المخزنِ البينيِّ الموروثة: {$n} · جدولُ المجال: {$dom}\n";
    if ($n > 0) {
        echo "[INJ-0003] ⚠ الموروثُ يُعاد إدخالُه بقيمٍ محكومةٍ من الشاشة — ولا يُرحَّل آليًّا\n";
        echo "           (أعمدةُ المجالِ ENUM والمخزنُ نصٌّ حرّ — والترحيلُ الأعمى يبتلع الغريبَ صامتًا)\n";
    }
}

/* ── إثباتٌ وظيفيّ: العقدُ يجد الفعل ─────────────────────────────────────── */
require_once dirname(__DIR__, 2) . '/includes/post_contract.php';
if (ems_pc_action_registered($db, $code) !== true) {
    throw new RuntimeException('العقدُ لم يجد الفعل: ' . $code);
}
echo "[INJ-0003] الإثباتُ الوظيفي: العقدُ يجد الفعل ✔\n";
