<?php
/**
 * 2027_01_19_fix_fn03_entitlement_actions.php
 * ═══════════════════════════════════════════════════════════════════════════
 * FIX-03 · FN-03 خطوة ③ (FIXC-0017) — «أضفْ فعلين محروسين: اعتمادُ استحقاقٍ
 * وردُّه بسببٍ محكوم».
 */
if (PHP_SAPI !== 'cli') { exit(1); }
error_reporting(E_ALL & ~E_DEPRECATED);
mysqli_report(MYSQLI_REPORT_OFF);
require_once dirname(__DIR__, 2) . '/includes/env.php';

$db = new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'), ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($db->connect_errno) { fwrite(STDERR, 'اتصال المرحِّل فشل: ' . $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');

$A = array(
    array('fin.entitlement.approve', 'اعتمادُ الاستحقاقِ المالي',
        'بوابة الاستحقاق المالي', 'entitlement_gate.php', 'المالية',
        'unit_effects · ems_business_events', 'EntitlementPosted',
        'لا Posted إلا باعتمادِ مديرِ الإدارةِ والماليةِ معًا وباعتمادين مستقلَّين — ثم حدثُ FES ويُملأ fin_event_ref',
        'حركةٌ عاكسةٌ بمرجعِ الأصل — ولا حذفَ لحدثٍ مالي'),
    array('fin.entitlement.reject', 'ردُّ الاستحقاقِ بسببٍ محكوم',
        'بوابة الاستحقاق المالي', 'entitlement_gate.php', 'المالية',
        'unit_effects · ems_business_events', 'EntitlementRejected',
        'الأثرُ يُنقل إلى «مردود» بسببٍ من قائمةٍ مغلقةٍ ومرجعِ رادِّه — والمقيَّدُ لا يُردّ بأثرٍ رجعيّ',
        'إعادةُ الاقتراحِ فعلٌ جديدٌ لا إلغاءُ الرد'),
);

$db->begin_transaction();
try {
    $n = 0;
    foreach ($A as $a) {
        list($code, $label, $screen, $file, $actor, $writes, $event, $effect, $reverse) = $a;
        $st = $db->prepare("SELECT COUNT(*) FROM nav09_action_map WHERE canonical_code = ?");
        $st->bind_param('s', $code);
        $st->execute();
        $exists = (int) $st->get_result()->fetch_row()[0];
        $st->close();
        if ($exists > 0) { echo "[FN-03] موجودٌ سلفًا: {$code}\n"; continue; }

        $guardEv = 'العقدُ السبعيُّ في includes/post_contract.php + حارسُ الشاشةِ فوقَ المعالج (RF-02) + سببُ الردِّ من قائمةٍ مغلقة';
        $idemEv  = 'مفتاحٌ مركَّبٌ من محتوى الطلبِ في ems_post_idempotency + idempotency_key في نشرِ الحقيقة (entitlement:pe / entitlement:reject)';
        $consumers = 'المالية · الحوكمة · المراجعة الداخلية';
        $class = 'domain_write';
        $live = 'page:Finance/entitlement_gate.php';

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
    echo "[FN-03] أفعالٌ مُسجَّلة: {$n}\n";
} catch (Throwable $e) {
    $db->rollback();
    throw $e;
}

require_once dirname(__DIR__, 2) . '/includes/post_contract.php';
foreach ($A as $a) {
    if (ems_pc_action_registered($db, $a[0]) !== true) {
        throw new RuntimeException('العقدُ لم يجد الفعل: ' . $a[0]);
    }
}
echo "[FN-03] الإثباتُ الوظيفي: العقدُ يجد الفعلين ✔\n";
