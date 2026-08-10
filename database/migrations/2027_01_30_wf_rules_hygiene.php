<?php
/**
 * 2027_01_30 — سلاليمُ الاعتماد: نصوصُ الملاحظاتِ تُعطَّل مُعلَنةً لا تُمحى
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ العطلُ المقيس (معمارية v21 §11-② — أخطرُ دَينٍ مُعلَنٍ فيها):
 *   `approval_workflow_rules` فيه **23 صفًّا نشطًا، عشرون منها محتواها نصُّ
 *   ملاحظةِ UAT** حُشر في خاناتِ `entity_type`/`action`/`role_required`:
 *     entity_type = «وفق المعتمد في محضر الإدارة · UAT-2026-0001»
 *   فهي **لا تُطابِق أيَّ نوعِ كيانٍ حيّ** — تشغل السجلَّ ولا تحكم شيئًا،
 *   وتُخفي الحقيقةَ: أن الأنواعَ الجاريةَ فعلًا **بلا قواعدَ أصلًا**.
 *
 * ◆ والتلوّثُ يعبر جدولين: أربعةُ صفوفٍ في `approval_requests` نوعُها نصُّ
 *   ملاحظةٍ كذلك — **ولا تُمسّ هنا**: تلك **سجلاتُ وقائع** (لها تواريخُ
 *   وحمولاتٌ ومنفّذون)، وتعديلُ سجلِّ واقعةٍ تلفيقٌ لا تنظيف. تُعلَن في
 *   المِسبار ولا تُكتب من جديد.
 *
 * ◆ **ولا حذفَ** (CS-08): `is_active = 0` مع بصمةٍ في `action` تُعلن السبب،
 *   فيبقى الصفُّ مقروءًا ويُعرف لماذا عُطِّل. الإعلانُ لا المحو — سابقةُ
 *   `legacy_no_ref` في M-11.
 *
 * ◆ والمعيارُ **مقيسٌ لا تقديريّ**: رمزُ الكيانِ لاتينيٌّ بلا فراغٍ وطولُه ≤40.
 *   وكلُّ صفٍّ يُعطَّل تُطبَع خانتُه قبلَه — فلا يُعطَّل شيءٌ بلا شاهد.
 *
 * ◆ مُتحمِّلٌ للتكرار: المعطَّلُ سلفًا يُتخطّى.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);   // گوتشا: بلا config.php ينفُذ افتراضُ الرمي
require_once dirname(__DIR__, 2) . '/includes/env.php';

$db = @new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'),
                  ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($db->connect_errno) { fwrite(STDERR, 'اتصال: ' . $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');

/** أرمزُ كيانٍ هو أم نصُّ ملاحظة؟ */
function wf_is_code($s)
{
    $s = trim((string) $s);
    return $s !== '' && mb_strlen($s) <= 40 && strpos($s, ' ') === false
        && preg_match('/^[A-Za-z0-9_:\-]+$/', $s) === 1;
}

echo "① فحصُ صفوفِ القواعد:\n";
$rs = $db->query('SELECT id, entity_type, action, role_required, step_order, is_active
                    FROM approval_workflow_rules ORDER BY id');
if (!$rs) { fwrite(STDERR, 'قراءة: ' . $db->error . "\n"); exit(1); }
$rows = array();
while ($x = $rs->fetch_assoc()) { $rows[] = $x; }
echo '    صفوفٌ: ' . count($rows) . "\n";

$toKill = array(); $keep = array();
foreach ($rows as $r) {
    if (wf_is_code($r['entity_type']) && wf_is_code($r['action'])) { $keep[] = $r; }
    else { $toKill[] = $r; }
}
echo '    قواعدُ برمزٍ حقيقيّ: ' . count($keep) . ' · نصوصُ ملاحظاتٍ: ' . count($toKill) . "\n";
foreach ($keep as $r) {
    echo '      ✔ يبقى #' . $r['id'] . ' — ' . $r['entity_type'] . ':' . $r['action']
       . ' خطوة ' . $r['step_order'] . ' دور ' . $r['role_required'] . "\n";
}

echo "\n② التعطيلُ مُعلَنًا (لا حذف):\n";
$done = 0; $skipped = 0;
foreach ($toKill as $r) {
    if ((int) $r['is_active'] === 0) { $skipped++; continue; }
    echo '      ✘ #' . str_pad($r['id'], 4) . ' entity_type=«' . mb_substr($r['entity_type'], 0, 46) . "»\n";
    $st = $db->prepare("UPDATE approval_workflow_rules
                           SET is_active = 0,
                               action = CONCAT(LEFT(action, 60), ' [معطَّل: نصُّ ملاحظةٍ في خانةِ رمز · 2027_01_30]'),
                               updated_at = NOW()
                         WHERE id = ?");
    if (!$st) { fwrite(STDERR, 'prepare: ' . $db->error . "\n"); exit(1); }
    $st->bind_param('i', $r['id']);
    if (!$st->execute()) { fwrite(STDERR, 'update #' . $r['id'] . ': ' . $st->error . "\n"); exit(1); }
    $st->close();
    $done++;
}
echo '    عُطِّل: ' . $done . ' · معطَّلٌ سلفًا: ' . $skipped . "\n";

/* ══ إثباتٌ وظيفيّ ═════════════════════════════════════════════════════ */
echo "\n③ الإثبات:\n";
$rs = $db->query('SELECT entity_type, action FROM approval_workflow_rules WHERE is_active = 1');
$bad = 0; $good = 0;
while ($rs && ($x = $rs->fetch_assoc())) {
    if (wf_is_code($x['entity_type']) && wf_is_code($x['action'])) { $good++; } else { $bad++; }
}
echo '    قواعدٌ نشطةٌ برمزٍ حقيقيّ: ' . $good . ' · بنصِّ ملاحظة: ' . $bad . "\n";
if ($bad !== 0) { fwrite(STDERR, "بقي {$bad} صفًّا نشطًا بنصِّ ملاحظة\n"); exit(1); }
if ($good === 0) { fwrite(STDERR, "صفرُ قاعدةٍ نشطةٍ — لا يُترك السجلُّ فارغًا\n"); exit(1); }

/* والصفوفُ الملوَّثةُ في سجلِّ الوقائعِ تُعلَن ولا تُمسّ */
$rs = $db->query("SELECT COUNT(*) FROM approval_requests
                   WHERE entity_type LIKE '% %' OR CHAR_LENGTH(entity_type) > 40");
$dirty = $rs ? (int) $rs->fetch_row()[0] : -1;
$rs = $db->query("SELECT COUNT(*) FROM approval_requests ar
                   WHERE NOT EXISTS (SELECT 1 FROM approval_steps s WHERE s.request_id = ar.id)");
$stepless = $rs ? (int) $rs->fetch_row()[0] : -1;
echo "    ◆ يُعلَن ولا يُمسّ (سجلُّ وقائعَ لا سجلُّ قواعد):\n";
echo "        طلباتٌ نوعُها نصُّ ملاحظة: {$dirty}\n";
echo "        طلباتٌ بلا أيِّ خطوةِ سلّم: {$stepless} — والمحرّكُ صار يرفض اعتمادَها\n";

echo "\n  الشاهد: php tools/fix_wf_rules_probe.php\n";
exit(0);
