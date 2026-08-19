<?php
/**
 * 2027_07_22_tkt_dept_routing.php — التوجيهُ يصل الإداراتِ لا يعود إلى المُرسِل
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المقيسُ قبلَه**: 13 نوعًا من 20 تُوجِّه مسارَها إلى `target_org_unit_code
 *   = 'tickets'` — أي إلى **مركزِ البلاغاتِ نفسِه**. فـ«طلب نقل وترحيل» يذهب
 *   للمركزِ لا للنقل، و«شكوى عميل» للمركزِ لا للمبيعات. وهذا **قرارُ توجيهٍ
 *   فارغٌ في ثوبِ قرار**: الطبقةُ مبنيّةٌ ومربوطةٌ بـ34 دورًا، والراوترُ يعمل،
 *   والقوالبُ مضبوطة — لكنّها تُعيد كلَّ شيءٍ إلى المُرسِل.
 *
 * ◆ **الأثرُ الحيُّ المقيس**: من 42 بلاغًا حقيقيًّا يصل **13 فقط** صندوقَ
 *   إدارة — **11 منها إلى مركزِ البلاغات** و2 إلى إدارات المواقع. وصفرٌ إلى
 *   المالية والأسطول والنقل والمشتريات والمخازن والموارد البشرية والمبيعات
 *   والموردين. و«صندوقُ بلاغاتِ الإدارة» فارغٌ تمامًا في 16 دورًا من 25.
 *
 * ◆ **والمفارقة**: الـ42 كلُّها لها `owner_role_id` صحيح — التوجيهُ موجودٌ في
 *   الرأس، وطبقةُ المساراتِ تُضيّعه بدل أن تحمله.
 *
 * ═══ ثلاثةُ قراراتٍ تُعلَن هنا لأنها تُقرأ لاحقًا ═══
 * ① **وحدة #15 «الموردون» بلا `unit_code`** — والراوترُ يحلُّ بالرمزِ لا
 *    بالمعرِّف، فلا شيءَ كان يمكن أن يصلها أبدًا. تُرمَّز `suppliers`.
 *
 * ② **لا تفريعَ بأثرٍ رجعي.** البلاغاتُ التاريخيةُ تحمل مسارَ `legacy` مردومًا
 *    (كلُّها أُنشئت في الثانيةِ نفسِها 2026-08-02 02:46:30 بأداةِ
 *    `tools/tkt_legacy_map.php`) و`org_unit_id = NULL`. فتحُ خمسةِ مساراتٍ
 *    عليها اليوم يصنع **عملًا وهميًّا على بلاغاتٍ أُغلقت** ويُفجّر مهلًا
 *    مكسورةً كاذبة. فتُرمَّم بأدنى تدخّل: وحدةٌ واحدةٌ مشتقّةٌ من مالكِها.
 *    والتفريعُ الخماسيُّ يسري على **الجديدِ وحدَه** عبر القوالبِ المصحَّحة.
 *
 * ③ **ولا تُحذف صفوفُ `legacy`**: مقيسٌ أنّ منها ما يرتبط بـ`ticket_holds`
 *    (2) و`ticket_effects` (2) بمفاتيحَ أجنبيةٍ معلَنة — والحذفُ يكسرها.
 *
 * ◆ افتراضٌ مُعلَن: «طلب تمويل / دفعة» ⇐ **التمويل والملكية (4)** لا المالية
 *   والخزينة (3). قلبُه صفٌّ واحدٌ في `ticket_type_workstreams` متى قرّر المالك.
 *
 * ◆ الهجرةُ **مُعادةُ التشغيلِ بلا أثرٍ مضاعف** (idempotent): كلُّ كتابةٍ
 *   مشروطةٌ بحالتِها القديمة، وتُقاس قبلَ وبعد.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

/** فحصُ المُرجَعِ إلزامي — mysqli هنا لا يرمي. */
function m_q($conn, $sql, $label)
{
    $r = $conn->query($sql);
    if ($r === false) { echo "  ✖ {$label}: {$conn->error}\n"; return false; }
    return $r;
}
function m_one($conn, $sql)
{
    $r = $conn->query($sql);
    if (!$r) { return null; }
    $row = $r->fetch_row();
    return $row ? $row[0] : null;
}

echo "═══ 2027_07_22 · توجيهُ البلاغاتِ إلى الإدارات ═══\n\n";

/* ── القياسُ قبل ───────────────────────────────────────────────────────── */
$TEST = "(t.complaint LIKE '%UATF%' OR t.complaint LIKE '%اختبار%'"
      . " OR t.complaint LIKE '%تحقق الإصلاح%' OR t.complaint LIKE '%UAT %')";
$sqlReach = "SELECT COUNT(*) FROM (SELECT t.id FROM tickets t
       LEFT JOIN ticket_workstreams w ON w.tk_id = t.id AND w.org_unit_id IS NOT NULL
      WHERE NOT {$TEST} GROUP BY t.id HAVING COUNT(w.ws_id) > 0) x";
$sqlUnits = "SELECT COUNT(DISTINCT w.org_unit_id) FROM ticket_workstreams w
       JOIN tickets t ON t.id = w.tk_id WHERE w.org_unit_id IS NOT NULL AND NOT {$TEST}";

$before_reach = (int) m_one($conn, $sqlReach);
$before_total = (int) m_one($conn, "SELECT COUNT(*) FROM tickets t WHERE NOT {$TEST}");
$before_units = (int) m_one($conn, $sqlUnits);
echo "قبل: يصل صندوقًا {$before_reach}/{$before_total} · إداراتٌ تستقبل {$before_units}\n\n";

/* ── ① ترميزُ وحدةِ «الموردون» ─────────────────────────────────────────── */
echo "① رمزُ وحدةِ «الموردون»\n";
$sup = m_q($conn, "SELECT unit_id, unit_code FROM org_units WHERE unit_id = 15", 'قراءة 15');
$suprow = $sup ? $sup->fetch_assoc() : null;
if ($suprow && trim((string) $suprow['unit_code']) === '') {
    $dup = (int) m_one($conn, "SELECT COUNT(*) FROM org_units WHERE unit_code = 'suppliers'");
    $ok = ($dup === 0) && m_q($conn, "UPDATE org_units SET unit_code = 'suppliers'
             WHERE unit_id = 15 AND (unit_code IS NULL OR unit_code = '')", 'ترميز 15');
    echo $ok ? "  ✔ وحدة 15 ⇐ 'suppliers' (كانت فارغةً فلا شيءَ يصلها)\n"
             : "  ⚠ تعذّر الترميز — الرمزُ مستعمَلٌ أو الكتابةُ مرفوضة\n";
} else {
    echo "  ○ مرمَّزةٌ سلفًا (" . ($suprow ? $suprow['unit_code'] : '?') . ")\n";
}

/* ── ② تصحيحُ قوالبِ التوجيه ───────────────────────────────────────────── */
echo "\n② قوالبُ التوجيه — 11 نوعًا يغادر مركزَ البلاغات\n";
$ROUTES = array(
    3  => array('transport',       'طلب نقل وترحيل'),
    4  => array('procurement_ops', 'طلب قطعة / شراء / صرف'),
    5  => array('financing',       'طلب تمويل / دفعة'),
    6  => array('operators',       'طلب مشغّل / تغطية / إجازة'),
    7  => array('fleet',           'طلب معدة / إتاحة / استبدال'),
    8  => array('governance',      'تفتيش دوري / تجديد ترخيص'),
    9  => array('suppliers',       'مورد: تأخر / خلاف / مستحقات'),
    10 => array('sales',           'شكوى عميل / نزاع تعاقدي'),
    11 => array('operators',       'دعم تشغيلي / تغطية وردية'),
    12 => array('movement',        'بلاغ سلامة / طوارئ'),
    15 => array('governance',      'بلاغ حوكمة وصلاحيات'),
);
$fixed = 0; $already = 0;
foreach ($ROUTES as $typeId => $def) {
    list($code, $label) = $def;
    $exists = (int) m_one($conn, "SELECT COUNT(*) FROM org_units WHERE unit_code = '"
        . $conn->real_escape_string($code) . "' AND active = 1");
    if ($exists === 0) {
        echo "  ✖ #{$typeId} {$label}: الرمزُ '{$code}' غائبٌ أو معطَّل — تُخطّى\n";
        continue;
    }
    // الشرطُ على القيمةِ القديمة يجعلها مُعادةَ التشغيلِ بلا أثرٍ مضاعف
    $ok = m_q($conn, "UPDATE ticket_type_workstreams
                         SET target_org_unit_code = '" . $conn->real_escape_string($code) . "'
                       WHERE ticket_type_id = " . (int) $typeId . "
                         AND target_org_unit_code = 'tickets'", "تحديث #{$typeId}");
    if ($ok === false) { continue; }
    if ($conn->affected_rows > 0) { $fixed++; echo "  ✔ #" . str_pad($typeId, 3) . " {$label} ⇐ {$code}\n"; }
    else { $already++; }
}
echo "  ⇐ صُحِّح {$fixed} · مطبَّقٌ سلفًا {$already}\n";
echo "  ○ يبقيان في المركزِ بحق: «معلومة أو إخطار» · «مقترح تحسين»\n";

/* ── ③ ترميمُ التاريخ (بلا تفريعٍ رجعي) ────────────────────────────────── */
echo "\n③ ترميمُ المساراتِ التاريخية — وحدةٌ واحدةٌ من مالكِ الرأس\n";
/** الدورُ ← وحدتُه — المصدرُ نفسُه الذي تقرأ به الشاشة. */
require_once $ROOT . '/Tickets/dept_inbox_map.php';

$rows = m_q($conn,
    "SELECT t.id, t.company_id, t.owner_role_id, t.stage,
            (SELECT COUNT(*) FROM ticket_workstreams w WHERE w.tk_id = t.id) ws_all,
            (SELECT COUNT(*) FROM ticket_workstreams w
              WHERE w.tk_id = t.id AND w.org_unit_id IS NOT NULL) ws_unit
       FROM tickets t
      WHERE NOT {$TEST}
     HAVING ws_unit = 0", 'البلاغاتُ غيرُ الواصلة');

$patched = 0; $created = 0; $skipped = 0;
while ($rows && ($t = $rows->fetch_assoc())) {
    $unit = ems_dept_unit_of_role((int) $t['owner_role_id']);
    if ($unit <= 0) { $skipped++; continue; }
    if ((int) $t['ws_all'] > 0) {
        // مسارٌ مردومٌ بلا وحدة ← تُحقن وحدتُه (ولا يُحذف: FK من holds/effects)
        $ok = m_q($conn, "UPDATE ticket_workstreams SET org_unit_id = " . (int) $unit . "
                           WHERE tk_id = " . (int) $t['id'] . " AND org_unit_id IS NULL",
                  "ترميم #{$t['id']}");
        if ($ok !== false && $conn->affected_rows > 0) { $patched++; }
    } else {
        // بلا مسارٍ إطلاقًا ← يُنشأ واحدٌ يعكس حالةَ رأسِه
        $st = in_array($t['stage'], array('closed', 'cancelled'), true) ? 'closed' : 'new';
        $ok = m_q($conn,
            "INSERT INTO ticket_workstreams
                (tk_id, workstream_type, seq_no, org_unit_id, mandatory, state,
                 activation_state, company_id)
             SELECT " . (int) $t['id'] . ", 'legacy', 1, " . (int) $unit . ", 1, '{$st}', 'opened', "
                . (int) $t['company_id'] . "
               FROM DUAL
              WHERE NOT EXISTS (SELECT 1 FROM ticket_workstreams w
                    WHERE w.tk_id = " . (int) $t['id'] . "
                      AND w.workstream_type = 'legacy' AND w.seq_no = 1)",
            "إنشاء #{$t['id']}");
        if ($ok !== false && $conn->affected_rows > 0) { $created++; }
    }
}
echo "  ✔ مسارٌ رُمِّمت وحدتُه = {$patched}\n";
echo "  ✔ مسارٌ أُنشئ لبلاغٍ بلا مسار = {$created}\n";
if ($skipped > 0) { echo "  ⚠ بلا وحدةٍ لدورِ مالكِه = {$skipped}\n"; }

/* ── القياسُ بعد ───────────────────────────────────────────────────────── */
$after_reach = (int) m_one($conn, $sqlReach);
$after_units = (int) m_one($conn, $sqlUnits);
$stray = (int) m_one($conn,
    "SELECT COUNT(*) FROM ticket_type_workstreams WHERE target_org_unit_code = 'tickets'");

echo "\n═══ النتيجة ═══\n";
echo "  يصل صندوقَ إدارة: {$before_reach}/{$before_total} ⇐ {$after_reach}/{$before_total}\n";
echo "  إداراتٌ تستقبل:    {$before_units} ⇐ {$after_units}\n";
echo "  قوالبُ ما تزال تعود للمركز: {$stray} (المتوقَّع 2)\n";
$conn->close();
