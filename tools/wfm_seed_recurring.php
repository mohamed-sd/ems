<?php
/**
 * tools/wfm_seed_recurring.php — القوالب الدورية المنصوصة (SRC-08 · M-14/M-00 §12)
 * ───────────────────────────────────────────────────────────────────────────
 * «المهمةُ الدوريةُ تتولد بدوريتها من قالبها» — تُبذر قوالبُ الدورات التي
 * نصّت عليها الوثائق (لا مخترعة): تقارير M-14 §12 الدورية بدورياتها نصًّا،
 * ومتابعة DEC-01 ⑦، ومراجعة قواعد التوجيه (WF-07 «تُراجَع دوريًّا»).
 * idempotent بمفتاح (company, code) — والجدولة تبدأ غدًا صباحًا.
 * الاستعمال: php tools/wfm_seed_recurring.php [--company=4]
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$CO = 4;
foreach ($argv as $a) { if (strpos($a, '--company=') === 0) { $CO = intval(substr($a, 10)); } }

/* [code, title, owner_role, freq, priority, deliverable, evidence, تفاصيل/سند] */
$TPL = array(
array('GOV-W-DENIALS', 'تقرير المحاولات الممنوعة الأسبوعي', 15, 'weekly', 'P2',
      'تقرير المحاولات المرفوضة بالمستخدم والحماية والتكرار', 'التقرير مرفوعًا في شاشة تقارير الحوكمة',
      'M-14 §12: «المحاولات الممنوعة — أسبوعي» · تكرارُ المنع يكشف حاجةَ استثناءٍ أو خطأ تصنيفٍ أو محاولةَ تجاوز'),
array('GOV-W-EXCEPTIONS', 'مراجعة الاستثناءات القائمة الأسبوعية', 15, 'weekly', 'P2',
      'كشف الاستثناءات النافذة بمُددها وعدد استعمالها ودرجتها', 'الكشف مرفوعًا وقراراتُ الإنهاء المبكر إن لزم',
      'M-14 §12: «الاستثناءات القائمة — أسبوعي» · الاستثناء المتكرر يعني حمايةً أو عمليةً خاطئة'),
array('GOV-W-LICENSES', 'كشف الوثائق النظامية المنتهية والمشارفة', 15, 'weekly', 'P1',
      'التراخيص والكفالات بمُددها وأثر انتهائها', 'الكشف والتنبيهات المرسلة قبل المدة',
      'M-14 §12: «الوثائق النظامية المنتهية — أسبوعي» · الانتهاء يمنع ما عُلّق عليه'),
array('GOV-M-SOD', 'مسح تعارضات الواجبات الشهري', 15, 'monthly', 'P2',
      'الحسابات الجامعة بين واجبين وحالة فصلها', 'مخرج tools/sod_sweep.php محفوظًا بتاريخه',
      'M-14 §12: «تعارضات الواجبات — شهري» — والحارس القبلي قائم والمسح يلتقط المتسرب'),
array('GOV-M-SENSITIVE', 'تقرير سجل الاطّلاع الحساس الشهري', 15, 'monthly', 'P2',
      'من قرأ بيانًا مقيَّدًا ومتى ومن أي شاشة', 'التقرير مرفوعًا للإدارة العليا',
      'M-14 §12: «سجل الاطّلاع الحساس — شهري» (قناة SENSITIVE_READ)'),
array('GOV-Q-ACCESS', 'دورة المراجعة الدورية للصلاحيات', 15, 'quarterly', 'P1',
      'تأكيد كل مديرٍ حاجةَ فريقه — والصمتُ سحبٌ', 'نسبة التأكيد والمسحوب آليًّا والمعطَّل',
      'M-14 §12: «دورة المراجعة — ربع سنوي» · BR-GOV: الحساب الخامل لا يبقى بابًا مفتوحًا'),
array('CEO-M-DECISIONS', 'متابعة القرارات العليا الشهرية', 9, 'monthly', 'P2',
      'القرارات بمكلَّفيها ومهلها وحالة تنفيذها', 'المحضر بمتابعاته في سجل القرارات',
      'M-00 §12: «القرارات العليا ومتابعتها — شهري» · القرار بلا متابعة يُنسى'),
array('OPS-W-CHAIN', 'متابعة مؤشر سلسلة الوحدة الأسبوعية (DEC-01 ⑦)', 1, 'weekly', 'P1',
      'العالق وأقدمه ونسبة تحويل الأسبوع — والحجة لفك الاختناق', 'قراءة المؤشر وقرارات الدفع',
      'DEC-01 ⑦ · كرونا EMS_E02_Chain* يغذيان الرقم وهذه مهمةُ القراءة والقرار'),
array('GOV-Q-ROUTES', 'مراجعة قواعد التوجيه الدورية', 15, 'quarterly', 'P3',
      'قواعد request_routes بنسب إعادة التوجيه اليدوي', 'محضر المراجعة وتعديلات القواعد',
      'WF-07: «القاعدة الخاطئة توصل العنصر لغير أهله — ولذلك تُراجَع دوريًّا»'),
);

$ins = $conn->prepare("INSERT INTO task_templates
    (company_id, code, title, details, org_unit_id, owner_role_id, priority, deliverable, evidence_required, active, created_by)
    VALUES (?,?,?,?,1,?,?,?,?,1,0)
    ON DUPLICATE KEY UPDATE title=VALUES(title), details=VALUES(details), owner_role_id=VALUES(owner_role_id),
      priority=VALUES(priority), deliverable=VALUES(deliverable), evidence_required=VALUES(evidence_required), active=1");
$n = 0;
foreach ($TPL as $t) {
    $ins->bind_param('isssisss', $CO, $t[0], $t[1], $t[7], $t[2], $t[4], $t[5], $t[6]);
    if ($ins->execute()) { $n++; } else { fwrite(STDOUT, "✘ {$t[0]}: {$ins->error}\n"); }
}
$ins->close();
fwrite(STDOUT, "قوالب: {$n}/" . count($TPL) . "\n");

/* الجدولة: غدًا 06:00 ثم بدوريتها — ولا صفَّ جدولةٍ مكرر */
$r = mysqli_query($conn, "SELECT tt.id, tt.code FROM task_templates tt WHERE tt.company_id = {$CO} AND tt.active = 1");
$made = 0;
$freqOf = array();
foreach ($TPL as $t) { $freqOf[$t[0]] = $t[3]; }
while ($r && ($x = mysqli_fetch_assoc($r))) {
    $tid = intval($x['id']);
    $freq = $freqOf[$x['code']] ?? 'monthly';
    $q = mysqli_query($conn, "SELECT id FROM recurring_tasks WHERE company_id = {$CO} AND template_id = {$tid} LIMIT 1");
    if ($q && mysqli_fetch_row($q)) { continue; }
    $next = date('Y-m-d 06:00:00', strtotime('+1 day'));
    mysqli_query($conn, "INSERT INTO recurring_tasks (company_id, template_id, freq, day_key, next_run_at, active, created_by)
                         VALUES ({$CO}, {$tid}, '" . mysqli_real_escape_string($conn, $freq) . "', 1, '{$next}', 1, 0)");
    $made++;
}
fwrite(STDOUT, "جدولات: +{$made}\n✔ اكتمل البذر\n");
