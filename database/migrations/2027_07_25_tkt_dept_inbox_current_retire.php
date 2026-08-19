<?php
/**
 * 2027_07_25_tkt_dept_inbox_current_retire.php — تقاعدُ الموضعِ الحيِّ للمدموج
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * ◆ **ما بقي بعد الدمج**: هجرةُ 2027_07_24 حوّلت صفوفَ `nav_items` الأربعةَ
 *   والثلاثين إلى `Tickets/tickets_list.php?tab=dept` ووحّدت اسمَها على
 *   «صندوقُ بلاغاتِ الإدارة» — وقِيس بعدَها: صفرُ صفٍّ يقصد المسارَ القديم.
 *
 * ◆ **ومع ذلك بقي الرابطُ القديمُ يُصيَّر**. والسببُ في جدولٍ ثانٍ:
 *   `nav_canonical_current` — لقطةُ «الموضعِ الحيِّ لكلِّ دور» المبذورةُ بهجرةِ
 *   2027_06_30 **قبل** الدمج. و`renderUnifiedNavigationV2` تصطنع من هذه
 *   اللقطةِ رابطًا للمسارِ الذي لا صفَّ تبعيةٍ له في الكتلةِ المعيارية:
 *
 *       foreach ($uxCurMap as $base => $cur) { … $properRoute = $uxMap[$base]['route'] … }
 *
 *   فظهر للمدموجِ **رابطٌ ثانٍ** بالاسمِ القديمِ «بلاغات إدارتي» في **14
 *   مجموعةً مختلفة** — وهو ما رسّبته بوابتا UXUI ②و③ («مسارٌ APPROVED
 *   باسمَين» · «بمجموعتَين»).
 *
 * ◆ **والعلاجُ حذفٌ لا تعديل**: تعديلُ اسمِ اللقطةِ يُبقي رابطًا ثانيًا يقصد
 *   مُحوِّلًا (302) بجانبِ الرابطِ الحقيقيّ — بابانِ لبابٍ واحد. والقياسُ يقول
 *   إن **كلَّ** دورٍ من السبعةَ عشرَ يحمل أصلًا صفَّ `nav_items` نشطًا يقصد
 *   التبويبَ المدموج، فالحذفُ لا ينزع وصولًا عن أحد.
 *
 * ◆ **حارسٌ قبل الكتابة**: إن وُجد دورٌ واحدٌ بلا صفِّ التبويبِ النشطِ تتوقف
 *   الهجرةُ ولا تحذف شيئًا — فالحذفُ لا يسبق إثباتَ البديل.
 *
 * ◆ ولا تُمَسُّ `finrequests/dept_inbox.php`: شاشةٌ حيّةٌ مستقلّةٌ لم تُدمج.
 *
 *   php database/migrations/2027_07_25_tkt_dept_inbox_current_retire.php
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

function q1($conn, $sql)
{
    $r = $conn->query($sql);
    if (!$r) { echo "  ✖ {$conn->error}\n"; return null; }
    $row = $r->fetch_row();
    return $row ? $row[0] : null;
}

$CUR = 'tickets/dept_inbox.php';                       // مفتاحُ اللقطةِ صغيرُ الحروف
$TAB = 'Tickets/tickets_list.php?tab=dept';            // الوجهةُ المدموجة

echo "═══ 2027_07_25 · تقاعدُ الموضعِ الحيِّ لصندوقِ بلاغاتِ الإدارة ═══\n\n";

/* ── القياسُ قبل ────────────────────────────────────────────────────────── */
$before = (int) q1($conn, "SELECT COUNT(*) FROM nav_canonical_current
                            WHERE route = '" . $conn->real_escape_string($CUR) . "'");
$names  = (int) q1($conn, "SELECT COUNT(DISTINCT cur_label) FROM nav_canonical_current
                            WHERE route = '" . $conn->real_escape_string($CUR) . "'");
$grps   = (int) q1($conn, "SELECT COUNT(DISTINCT cur_group) FROM nav_canonical_current
                            WHERE route = '" . $conn->real_escape_string($CUR) . "'");
echo "قبل: {$before} موضعًا حيًّا · {$names} اسمًا · {$grps} مجموعة\n\n";
if ($before === 0) { echo "○ لا شيءَ يُتقاعد — مُنفَّذةٌ سلفًا\n"; $conn->close(); exit(0); }

/* ── ① الحارس: لا يُحذف موضعٌ إلا وللدورِ بديلُه النشطُ مُثبَتٌ ────────── */
echo "① حارسُ البديل — لكلِّ دورٍ صفُّ تبويبٍ نشطٌ قبل الحذف\n";
$orphans = array();
$res = $conn->query("SELECT c.role_id,
                            (SELECT COUNT(*) FROM nav_items n
                              WHERE n.role_id = c.role_id
                                AND n.route = '" . $conn->real_escape_string($TAB) . "'
                                AND n.active = 1) AS has_tab
                       FROM nav_canonical_current c
                      WHERE c.route = '" . $conn->real_escape_string($CUR) . "'");
if (!$res) { exit("  ✖ {$conn->error}\n"); }
while ($r = $res->fetch_assoc()) {
    if ((int) $r['has_tab'] === 0) { $orphans[] = (int) $r['role_id']; }
}
if ($orphans) {
    exit("  ✖ توقّف: أدوارٌ بلا صفِّ التبويبِ النشط — " . implode('، ', $orphans)
       . "\n     الحذفُ ينزع عنها الوصول. أضِفْ صفَّها أولًا ثم أعِد.\n");
}
echo "  ✔ صفرُ دورٍ يفقد وصولًا — {$before} دورًا كلُّهم يحملون التبويبَ المدموج\n";

/* ── ② التقاعد ─────────────────────────────────────────────────────────── */
echo "\n② حذفُ الموضعِ الحيِّ للمسارِ المدموج\n";
$ok = $conn->query("DELETE FROM nav_canonical_current
                     WHERE route = '" . $conn->real_escape_string($CUR) . "'");
if ($ok === false) { exit("  ✖ {$conn->error}\n"); }
echo "  ✔ تقاعد {$conn->affected_rows} موضعًا\n";
echo "  ○ finrequests/dept_inbox.php لم تُمَسّ — شاشةٌ حيّةٌ لم تُدمج\n";

/* ── القياسُ بعد ───────────────────────────────────────────────────────── */
$after   = (int) q1($conn, "SELECT COUNT(*) FROM nav_canonical_current
                             WHERE route = '" . $conn->real_escape_string($CUR) . "'");
$tabRows = (int) q1($conn, "SELECT COUNT(*) FROM nav_items
                             WHERE route = '" . $conn->real_escape_string($TAB) . "' AND active = 1");
$tabName = (int) q1($conn, "SELECT COUNT(DISTINCT label_ar) FROM nav_items
                             WHERE route = '" . $conn->real_escape_string($TAB) . "' AND active = 1");
$finKept = (int) q1($conn, "SELECT COUNT(*) FROM nav_canonical_current
                             WHERE route = 'finrequests/dept_inbox.php'");

echo "\n═══ النتيجة ═══\n";
echo "  مواضعُ اللقطةِ للمسارِ المدموج: {$before} ⇐ {$after} (المتوقَّع 0)\n";
echo "  صفوفُ التبويبِ النشطة: {$tabRows} · أسماؤها المختلفة: {$tabName} (المتوقَّع 1)\n";
echo "  مواضعُ FinRequests المحفوظة: {$finKept}\n";
$conn->close();
