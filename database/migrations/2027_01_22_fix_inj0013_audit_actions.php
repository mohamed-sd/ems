<?php
/**
 * 2027_01_22_fix_inj0013_audit_actions.php
 * ═══════════════════════════════════════════════════════════════════════════
 * INJ-0013 (P0) — «من الشاشاتِ الستَّ عشرةَ في Audit/ شاشةٌ واحدةٌ فقط تحمل
 * أفعالًا، ودالةُ assertCycle بلا نداءات».
 *
 * تسجيلُ الأفعالِ التسعةِ في القاموس — وغيرُ المسجَّلِ **يُحجب** في عُدّةِ
 * التصيير (`u13_screen_kit` يفحص القاموسَ قبلَ تنفيذِ أيِّ فعل).
 * ◆ ثمانيةٌ من السجلِّ الجامعِ + إقرارُ الاستقلال: `assertCycle('engagement')`
 *   يشترطه (IAF-0009) فلا تُفتح مهمةٌ بدونه — وإغفالُه يجعل السلسلةَ مقطوعة.
 */
if (PHP_SAPI !== 'cli') { exit(1); }
error_reporting(E_ALL & ~E_DEPRECATED);
mysqli_report(MYSQLI_REPORT_OFF);
require_once dirname(__DIR__, 2) . '/includes/env.php';

$db = new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'), ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($db->connect_errno) { fwrite(STDERR, 'اتصال المرحِّل فشل: ' . $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');

$A = array(
    array('iaf.charter.approve', 'اعتمادُ ميثاقِ المراجعة', 'ميثاق المراجعة الداخلية', 'iaf_charter.php',
        'iaf_charter', 'CharterApproved', 'الميثاقُ المعتمدُ يفتح بابَ الكونِ الرقابيّ — ولا كونَ بلا ميثاق (IAF-0044)',
        'نسخةٌ جديدةٌ من الميثاقِ تُعتمد — ولا يُحذف السابق'),
    array('iaf.independence.declare', 'إقرارُ استقلالٍ وتعارضِ مصالح', 'إقرارات الاستقلال وتعارض المصالح', 'iaf_independence.php',
        'iaf_independence', 'IndependenceDeclared', 'الإقرارُ الساري شرطُ التكليفِ بمهمة (IAF-0009)',
        'إقرارٌ جديدٌ بتاريخٍ لاحق — والسابقُ يبقى في السجل'),
    array('iaf.universe.build', 'إدراجُ مجالٍ في الكونِ الرقابي', 'الكون الرقابي', 'iaf_universe.php',
        'iaf_universe', 'UniverseAreaAdded', 'المجالُ يصير قابلًا للتخطيط — ولا خطةَ بلا كون (IAF-0044)',
        'تعطيلُ المجالِ (active=0) — لا حذف'),
    array('iaf.plan.approve', 'اعتمادُ الخطةِ السنوية', 'خطة المراجعة السنوية', 'iaf_plan.php',
        'iaf_plan', 'AuditPlanApproved', 'الخطةُ المعتمدةُ تفتح بابَ فتحِ المهام — ولا مهمةَ بلا خطة (IAF-0044)',
        'خطةٌ لسنةٍ جديدةٍ — ولا تُمحى السابقة'),
    array('iaf.engagement.open', 'فتحُ مهمةِ مراجعة', 'مهام المراجعة', 'iaf_engagements.php',
        'iaf_engagements', 'EngagementOpened', 'المهمةُ المفتوحةُ تقبل أوراقَ العملِ والملاحظات — بخطةٍ معتمدةٍ وإقرارِ استقلالٍ سارٍ',
        'إقفالُ المهمةِ بحالةٍ معلَنة — لا حذف'),
    array('iaf.workpaper.attach', 'إرفاقُ ورقةِ عملٍ ببصمةِ دليل', 'أوراق العمل والأدلة', 'iaf_workpapers.php',
        'iaf_workpapers', 'WorkpaperAttached', 'نسخةُ دليلٍ ببصمةٍ **محسوبةٍ لا مُدخَلة** — فالمُدخَلةُ تُزوَّر (IAF-0037)',
        'تجميدُ الورقةِ (frozen=1) — ولا تُحذف'),
    array('iaf.finding.raise', 'رفعُ ملاحظةِ مراجعة', 'ملاحظات المراجعة', 'iaf_findings.php',
        'iaf_findings', 'FindingRaised', 'الملاحظةُ تفتح دورةَ الردِّ والمعالجةِ بمهلةٍ معلَنة — وللمراجعِ حصرًا (IAF-0025)',
        'إغلاقُ الملاحظةِ بدليلٍ مقبول — ولا حذف'),
    array('iaf.response.submit', 'ردُّ الإدارةِ على ملاحظة', 'ردود الإدارات على الملاحظات', 'iaf_responses.php',
        'iaf_findings', 'FindingResponded', 'الردُّ فعلُ الإدارةِ المُلاحَظِ عليها لا فعلُ المراجع — فصلُ واجباتٍ مُنفَذ',
        'ردٌّ مُحدَّثٌ يُسجَّل — والسابقُ في سجلِّ التدقيق'),
    array('iaf.actionplan.set', 'ضبطُ خطةِ المعالجةِ ومالكِها ومهلتِها', 'خطط المعالجة ومتابعتها', 'iaf_action_plans.php',
        'iaf_findings', 'ActionPlanSet', 'خطةُ معالجةٍ بمالكٍ ومهلةٍ — ولا خطةَ بلا ردِّ إدارةٍ سابق (IAF-0044)',
        'تحديثُ الخطةِ بمهلةٍ جديدةٍ معلَنة'),
);

$db->begin_transaction();
try {
    $n = 0; $u = 0;
    foreach ($A as $a) {
        list($code, $label, $screen, $file, $writes, $event, $effect, $reverse) = $a;
        $guardEv = 'عُدّةُ التصيير u13_screen_kit: CSRF ← فعلٌ معرَّفٌ للشاشة ← can_edit ← رمزٌ مسجَّلٌ في القاموس ← نداءُ الخدمة · و**assertCycle قبلَ كلِّ فعل** (IAF-0044)';
        $idemEv  = 'حراسةُ الترتيبِ نفسُها عطالةٌ: الفعلُ خارجَ موضعِه في السلسلةِ يُرفض 409 · والإدراجُ بمفتاحٍ طبيعيٍّ (ON DUPLICATE) حيث ينطبق';
        $consumers = 'المراجعة الداخلية · الجهة المشرفة · الحوكمة';
        $actor = 'المراجع الداخلي المستقل';
        $class = 'domain_write';
        $live  = 'page:Audit/' . $file;

        $st = $db->prepare("SELECT COUNT(*) FROM nav09_action_map WHERE canonical_code = ?");
        $st->bind_param('s', $code);
        $st->execute();
        $ex = (int) $st->get_result()->fetch_row()[0];
        $st->close();
        if ($ex > 0) { $u++; continue; }

        $st = $db->prepare("INSERT INTO nav09_action_map
              (canonical_code, label_ar, screen_title, canonical_file, actor_ar, writes_text, event_name,
               consumers_text, effect_text, reverse_text, live_code, state, guard_verified, guard_evidence,
               idempotency_verified, idempotency_evidence, uat_verified, uat_evidence, write_class, updated_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?, 'bound_page', 'yes', ?, 'yes', ?, 'pending', '', ?, NOW())");
        if (!$st) { throw new RuntimeException('prepare: ' . $db->error); }
        $st->bind_param('ssssssssssssss', $code, $label, $screen, $file, $actor, $writes, $event,
            $consumers, $effect, $reverse, $live, $guardEv, $idemEv, $class);
        if (!$st->execute()) { throw new RuntimeException('insert ' . $code . ': ' . $st->error); }
        $st->close();
        $n++;
    }
    $db->commit();
    echo "[INJ-0013] أفعالٌ مُسجَّلة: {$n} · موجودةٌ سلفًا: {$u}\n";
} catch (Throwable $e) {
    $db->rollback();
    throw $e;
}

require_once dirname(__DIR__, 2) . '/includes/post_contract.php';
$missing = array();
foreach ($A as $a) { if (ems_pc_action_registered($db, $a[0]) !== true) { $missing[] = $a[0]; } }
if ($missing) { throw new RuntimeException('أفعالٌ لم تُسجَّل: ' . implode('، ', $missing)); }
echo "[INJ-0013] الإثباتُ الوظيفي: القاموسُ يحمل الأفعالَ التسعةَ كلَّها ✔\n";
