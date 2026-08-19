<?php
/**
 * tests/tkt_dept_routing_test.php — البلاغُ يصل إدارتَه، والصندوقُ بابٌ واحد
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ العطبُ الذي يحرسه — وقد **وقع فعلًا** وعاش شهورًا بلا أن يُرى:
 *   طبقةُ مساراتِ الإداراتِ مبنيّةٌ (9 خدمات) ومربوطةٌ بـ34 دورًا، والراوترُ
 *   يعمل، والقوالبُ مضبوطة — لكنّ **13 نوعًا من 20 كانت تُوجِّه مسارَها إلى
 *   `target_org_unit_code = 'tickets'`، أي إلى مركزِ البلاغاتِ نفسِه**. فقرارُ
 *   التوجيهِ كان فارغًا في ثوبِ قرار، و«صندوقُ بلاغاتِ الإدارة» فارغًا تمامًا
 *   في 16 دورًا من 25 — من 42 بلاغًا حقيقيًّا كان يصل **13 فقط**، أحدَ عشرَ
 *   منها إلى المركزِ ذاتِه.
 *
 * ◆ وعطبٌ ثانٍ يستّرُ الأول: `TicketRouter` كان يُدرج المسارَ **بلا
 *   `company_id`** (25 صفًّا)، و`TenantDb::scopedQuery()` تحقن
 *   `ws.company_id = ?` — فالعمودُ الفارغُ يُسقط المسارَ **صامتًا** من أيِّ
 *   قارئٍ محكومٍ بالبوابة. والغيابُ يُقرأ «لا عمل» لا «عطل».
 *
 * ◆ ما يقيسه: القوالبَ · الوصولَ الحيَّ · الكيانَ · مواضعَ الإدراجِ في
 *   الشيفرة · وأنّ الصندوقَ صار بابًا واحدًا (تبويبًا) لا شاشتَين.
 *
 * ◆ ما لا يقيسه — مُعلَنٌ لا مسكوتٌ عنه: لا يفتح الشاشةَ في متصفّح، فلا يشهد
 *   أنّ التبويبَ يُصيَّر لمستخدمٍ حيّ؛ ذاك شأنُ فحصٍ وظيفيٍّ بجلسة.
 *
 *   php tests/tkt_dept_routing_test.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$pass = 0; $fail = 0;
function ck($label, $cond, $detail = '')
{
    global $pass, $fail;
    if ($cond) { $pass++; echo "  ✔ {$label}\n"; }
    else { $fail++; echo "  ✖ {$label}" . ($detail !== '' ? " — {$detail}" : '') . "\n"; }
}
function one($conn, $sql)
{
    $r = $conn->query($sql);
    if (!$r) { return null; }
    $row = $r->fetch_row();
    return $row ? $row[0] : null;
}

echo "═══ حارسُ توجيهِ البلاغاتِ إلى الإدارات ═══\n\n";

/* ── ① القوالبُ لا تعود إلى المُرسِل ───────────────────────────────────── */
echo "① قوالبُ التوجيه\n";
$stray = (int) one($conn,
    "SELECT COUNT(*) FROM ticket_type_workstreams WHERE target_org_unit_code = 'tickets'");
// «معلومة أو إخطار» و«مقترح تحسين» يبقيان في المركزِ بحقٍّ — وما زاد عيبٌ
ck('لا قالبَ يعود إلى مركزِ البلاغاتِ إلا الاثنان المشروعان',
   $stray <= 2, "المقيس={$stray} · المسموح=2");

$noTarget = (int) one($conn,
    "SELECT COUNT(*) FROM ticket_type_workstreams
      WHERE target_org_unit_code IS NULL OR target_org_unit_code = ''");
ck('لا قالبَ بلا وحدةٍ مستهدَفة', $noTarget === 0, "المقيس={$noTarget}");

$unresolved = (int) one($conn,
    "SELECT COUNT(*) FROM ticket_type_workstreams d
      WHERE NOT EXISTS (SELECT 1 FROM org_units o
            WHERE o.unit_code = d.target_org_unit_code AND o.active = 1)");
ck('كلُّ رمزِ وحدةٍ في القوالبِ يُحَلُّ إلى وحدةٍ نشطة',
   $unresolved === 0, "غيرُ محلولٍ={$unresolved}");

$blankCode = (int) one($conn,
    "SELECT COUNT(*) FROM org_units WHERE active = 1 AND (unit_code IS NULL OR unit_code = '')");
ck('لا وحدةَ نشطةٌ بلا رمز (الراوترُ يحلُّ بالرمز)',
   $blankCode === 0, "بلا رمزٍ={$blankCode}");

/* ── ② الوصولُ الحي ────────────────────────────────────────────────────── */
echo "\n② الوصولُ الحي\n";
$TEST = "(t.complaint LIKE '%UATF%' OR t.complaint LIKE '%اختبار%'"
      . " OR t.complaint LIKE '%تحقق الإصلاح%' OR t.complaint LIKE '%UAT %')";
$total = (int) one($conn, "SELECT COUNT(*) FROM tickets t WHERE NOT {$TEST}");
$reach = (int) one($conn,
    "SELECT COUNT(*) FROM (SELECT t.id FROM tickets t
       LEFT JOIN ticket_workstreams w ON w.tk_id = t.id AND w.org_unit_id IS NOT NULL
      WHERE NOT {$TEST} GROUP BY t.id HAVING COUNT(w.ws_id) > 0) x");
ck("كلُّ بلاغٍ حقيقيٍّ يصل صندوقَ إدارة ({$reach}/{$total})", $reach === $total);

$units = (int) one($conn,
    "SELECT COUNT(DISTINCT w.org_unit_id) FROM ticket_workstreams w
       JOIN tickets t ON t.id = w.tk_id WHERE w.org_unit_id IS NOT NULL AND NOT {$TEST}");
ck("البلاغاتُ موزَّعةٌ على أكثرَ من إدارتَين ({$units} إدارة)", $units > 2);

/* ── ③ الكيانُ المالك ──────────────────────────────────────────────────── */
echo "\n③ الكيانُ المالك\n";
$noCo = (int) one($conn, "SELECT COUNT(*) FROM ticket_workstreams WHERE company_id IS NULL");
ck('لا مسارَ بلا كيانٍ مالك (بوابةُ العزلِ تُسقطه صامتًا)',
   $noCo === 0, "بلا كيانٍ={$noCo}");

$mismatch = (int) one($conn,
    "SELECT COUNT(*) FROM ticket_workstreams w JOIN tickets t ON t.id = w.tk_id
      WHERE w.company_id <> t.company_id");
ck('كيانُ المسارِ يطابق كيانَ رأسِه', $mismatch === 0, "مخالفٌ={$mismatch}");

/* ── ④ مواضعُ الإدراجِ في الشيفرة (منعُ الارتداد) ─────────────────────── */
echo "\n④ مواضعُ الإدراجِ في الشيفرة\n";
$writers = array(
    'app/Services/Tickets/TicketRouter.php',
    'app/Services/Security/BreakGlassService.php',
);
foreach ($writers as $rel) {
    $src = @file_get_contents($ROOT . '/' . $rel);
    if ($src === false) { ck("قراءة {$rel}", false, 'الملفُّ غيرُ مقروء'); continue; }
    // كلُّ إدراجٍ في ticket_workstreams يجب أن يذكر company_id في قائمةِ أعمدتِه
    $bad = 0;
    if (preg_match_all('/INSERT\s+(?:IGNORE\s+)?INTO\s+ticket_workstreams\s*\(([^)]*)\)/is', $src, $m)) {
        foreach ($m[1] as $cols) {
            if (stripos($cols, 'company_id') === false) { $bad++; }
        }
    }
    ck("{$rel}: كلُّ إدراجٍ يكتب company_id", $bad === 0, "مواضعُ ناقصةٌ={$bad}");
}

/* ── ⑤ بابٌ واحدٌ لا شاشتان ────────────────────────────────────────────── */
echo "\n⑤ بابٌ واحدٌ للبلاغات\n";
$oldRows = (int) one($conn, "SELECT COUNT(*) FROM nav_items WHERE route = 'Tickets/dept_inbox.php'");
ck('لا صفَّ تنقلٍ يقصد الشاشةَ المستقلّة', $oldRows === 0, "المقيس={$oldRows}");

$newRows = (int) one($conn,
    "SELECT COUNT(*) FROM nav_items WHERE route = 'Tickets/tickets_list.php?tab=dept'");
ck("صفوفُ التنقلِ تقصد التبويبَ ({$newRows} صفًّا)", $newRows > 0);

$labels = (int) one($conn,
    "SELECT COUNT(DISTINCT label_ar) FROM nav_items WHERE route = 'Tickets/tickets_list.php?tab=dept'");
ck('اسمٌ واحدٌ لرابطٍ واحد', $labels === 1, "أسماءٌ={$labels}");

$listSrc = (string) @file_get_contents($ROOT . '/Tickets/tickets_list.php');
ck("tickets_list.php يُعرّف تبويبَ 'dept'", strpos($listSrc, "\$TABS['dept']") !== false);
ck("tickets_list.php يقرأ المعامَل ?open=", strpos($listSrc, "\$_GET['open']") !== false);

$inboxSrc = (string) @file_get_contents($ROOT . '/Tickets/dept_inbox.php');
ck('dept_inbox.php صار مُحوِّلًا لا شاشة',
   strpos($inboxSrc, 'tickets_list.php?tab=dept') !== false
   && strpos($inboxSrc, 'FROM ticket_workstreams') === false);

$redir = (int) one($conn,
    "SELECT COUNT(*) FROM nav_redirects WHERE old_route = 'Tickets/dept_inbox.php' AND active = 1");
ck('التحويلُ مقيَّدٌ في nav_redirects', $redir === 1, "المقيس={$redir}");

echo "\n═══ " . ($fail === 0 ? "نجح {$pass}/{$pass}" : "رسب {$fail} · نجح {$pass}") . " ═══\n";
$conn->close();
exit($fail === 0 ? 0 : 1);
