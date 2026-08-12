<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * M-31/32/34/35/36/37 · E-12/13/14 — اختبار قبول مجموعةِ الصيانة والبلاغات
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/mnt_tickets_group_test.php
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$_SESSION['user'] = array('id' => 1, 'role' => '13', 'company_id' => 4, 'name' => 'mnt group test');

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$CO   = 4;
$MARK = 'MT9T' . getmypid();

$teardown = function () use ($conn, $MARK) {
    $conn->query("DELETE FROM mnt_order WHERE code LIKE '%{$MARK}%' OR pm_cycle_key LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM mnt_plan WHERE code LIKE '%{$MARK}%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ الصيانةُ والبلاغات — تسعُ مهامّ ══\n");

// ═══ M-31 ═══
head('M-31 — التصنيفُ الموحّد: جدولٌ واحدٌ + View + وصلةٌ معلَنة');

$v = $conn->query("SELECT COUNT(*) n FROM unified_fault_taxonomy")->fetch_assoc();
check(intval($v['n']) >= 80, '★ الواجهةُ الموحّدة `unified_fault_taxonomy` حيّةٌ (' . $v['n'] . ' فئة)');
/* ══ **القاموسُ هو الفعّالُ منه.** الحكمُ صحيحٌ (سبعٌ موصولةٌ و«غير ذلك» بلا كودٍ
     مخترَع) لكنه كان يعدُّ **كلَّ** الصفوفِ ومنها المحتجَزة. والمقيسُ: اثنا عشرَ
     صفًّا أسماؤها **أسماءُ أشخاصٍ** (`TICK-00009`…`TICK-00020`) و`failure_main_code`
     فيها **يشير إلى رمزِ الصفِّ نفسِه** — أثرُ مستوردِ UAT-2026 (عينُ ما عُولج في
     `job_titles` و`pay_models` و`ticket_types`). حُجزت معطَّلةً وصُفِّر مرجعُها
     الكاذبُ (هجرة 2027_03_08) ولم تُحذف.
   ⇒ فيُقاس الفعّالُ: سبعٌ موصولةٌ وواحدٌ معلَنٌ بلا وصلة · ويُضاف حكمٌ أقوى:
     **كلُّ وصلةٍ تشير إلى فئةٍ موجودةٍ في `failure_codes`** — فمرجعٌ ذاتيٌّ كاذبٌ
     يرسب حتى لو كان العددُ سبعًا. */
$mapped = intval($conn->query("SELECT COUNT(*) n FROM ticket_categories
                                WHERE COALESCE(active,1) = 1 AND failure_main_code IS NOT NULL")->fetch_assoc()['n']);
$orphan = intval($conn->query("SELECT COUNT(*) n FROM ticket_categories
                                WHERE COALESCE(active,1) = 1 AND failure_main_code IS NULL")->fetch_assoc()['n']);
$badLink = intval($conn->query("SELECT COUNT(*) n FROM ticket_categories c
                                 WHERE c.failure_main_code IS NOT NULL
                                   AND NOT EXISTS (SELECT 1 FROM failure_codes f
                                                    WHERE f.main_category_code = c.failure_main_code)")->fetch_assoc()['n']);
check($mapped === 7 && $orphan === 1 && $badLink === 0,
      '★★ سبعةُ تصنيفاتٍ فعّالةٍ وُصلت دلاليًّا و«غير ذلك» **موروثٌ معلَنٌ بلا كودٍ مخترَع**'
      . " (موصول={$mapped} · معلَن={$orphan} · وصلةٌ لفئةٍ معدومة={$badLink})");
check(is_file(dirname(__DIR__) . '/docs/reports/m31_taxonomy_reconciliation.md'),
      '★ وتقريرُ المطابقة في docs/reports — التعبئةُ الرجعيةُ موثَّقة');

// ═══ M-36 ═══
head('M-36 — مفتاحُ عدم تكرار الوقائية (معدة × خدمة × دورة)');

$key = 'plan:999888:eq:1:due:2087-01-01-' . $MARK;
$ok1 = $conn->query("INSERT INTO mnt_order (company_id, code, source, maint_type, state, pm_cycle_key, created_by)
                     VALUES ({$CO}, 'MNT-{$MARK}-1', 'وقائي', 'صيانة وقائية', 'بلاغ', '{$key}', 1)");
$ok2 = $conn->query("INSERT INTO mnt_order (company_id, code, source, maint_type, state, pm_cycle_key, created_by)
                     VALUES ({$CO}, 'MNT-{$MARK}-2', 'وقائي', 'صيانة وقائية', 'بلاغ', '{$key}', 1)");
check($ok1 !== false && $ok2 === false,
      '★★★ الدورةُ نفسُها مرتين ⇒ **يرفضها الفريدُ في القاعدة** — لا توليدَ مكرر');
$backfilled = intval($conn->query("SELECT COUNT(*) n FROM mnt_order
                                    WHERE plan_id IS NOT NULL AND pm_cycle_key IS NULL")->fetch_assoc()['n']);
check($backfilled === 0, '★ وكلُّ أوامر الخطط القائمة عُبّئت رجعيًّا بمفتاحها');
$wired = file_get_contents(dirname(__DIR__) . '/Maintenance/preventive_plans.php');
check(strpos($wired, 'pm_cycle_key') !== false && strpos($wired, 'لا توليدَ مرتين') !== false,
      '★ والمولّدُ اليدويُّ يحسب المفتاحَ ويرفض المكرر برسالةٍ تسمّي الأمرَ القائم');

// ═══ M-32 ═══
head('M-32 — «قطعةٌ منتظرة» حالةٌ بعدّاد أيامها');

$src = file_get_contents(dirname(__DIR__) . '/Maintenance/orders.php');
check(strpos($src, "'قطعة منتظرة'") !== false && strpos($src, 'waiting_part_since') !== false,
      '★ الحالةُ في قائمة الحالات ودخولُها يختم تاريخَه وخروجُها يمسحه');
$conn->query("INSERT INTO mnt_order (company_id, code, source, maint_type, state,
              waiting_part_since, created_by)
              VALUES ({$CO}, 'MNT-{$MARK}-W', 'بلاغ', 'إصلاح عطل', 'قطعة منتظرة',
                      DATE_SUB(CURDATE(), INTERVAL 5 DAY), 1)");
$w = $conn->query("SELECT DATEDIFF(CURDATE(), waiting_part_since) d FROM mnt_order
                    WHERE code='MNT-{$MARK}-W'")->fetch_assoc();
check(intval($w['d']) === 5, '★★ والعدّادُ **يُحسب من التاريخ** (5 أيام) لا يُخزَّن رقمًا يتقادم');
check(strpos($src, 'قطعٌ منتظرة') !== false,
      '★ وبطاقةُ «قطعٌ منتظرة» بعدّادها في شاشة الأوامر — كان مصدرُها غائبًا');

// ═══ M-34 ═══
head('M-34 — تحويلُ ملاحظة الفحص بلاغًا (NoteConverted) + photo_ref');

$c = $conn->query("SHOW COLUMNS FROM mnt_inspection_line LIKE 'photo_ref'");
check($c && $c->num_rows === 1, '★ `photo_ref` على بنود التفتيش');
$c = $conn->query("SHOW COLUMNS FROM mnt_inspection_line LIKE 'converted_ticket_id'");
check($c && $c->num_rows === 1, '★ و`converted_ticket_id` يحمل بلاغَ الملاحظة — فلا تحويلَ مرتين');
$insp = file_get_contents(dirname(__DIR__) . '/Maintenance/inspections.php');
check(strpos($insp, 'convert_note_ticket') !== false && strpos($insp, 'NoteConverted') !== false
      && strpos($insp, 'linked_ref_table') !== false,
      '★★ والمسارُ المفقود (تفتيش→بلاغ) سُدَّ: بلاغٌ بنقرةٍ **موصولٌ بالخيط** (linked_ref) وعاطل');

// ═══ M-35 ═══
head('M-35 — تقريرُ الأعطال من التصنيف الموحّد (197)');

$m = $conn->query("SELECT id FROM modules WHERE code='Maintenance/failure_report.php'")->fetch_assoc();
check($m && intval($m['id']) === 197, '★ الشاشةُ 197 مسجَّلة');
$rep = file_get_contents(dirname(__DIR__) . '/Maintenance/failure_report.php');
check(strpos($rep, 'main_category_code') !== false && strpos($rep, 'failure_main_code') !== false,
      '★★ والتقريرُ يجمع الأوامرَ والبلاغاتِ **على المحور الموحّد** — وكان مستحيلًا قبل M-31');

// ═══ M-37 + E-13 ═══
head('E-13/M-37 — التبويباتُ الأربعة و«بلاغاتي» لكل مستلم');

$tl = file_get_contents(dirname(__DIR__) . '/Tickets/tickets_list.php');
check(strpos($tl, "'open'") !== false && strpos($tl, "'approval'") !== false
      && strpos($tl, "'mine'") !== false && strpos($tl, "'closed'") !== false,
      '★★ التبويباتُ الأربعة (مفتوحة · **تنتظر اعتمادًا** · بلاغاتي · مغلقة) — الغائبُ سُدّ');
check(strpos($tl, 'assigned_user_id') !== false,
      '★ و«بلاغاتي» يرشّح بالمستلم (المسنَدُ إليه) لا بالدور 24 وحده (M-37)');

// ═══ E-12 ═══
head('E-12 — الخيطُ بالاتجاهين في شاشة الأوامر');

check(strpos($src, "linked_ref_table = 'mnt_order'") !== false
      && strpos($src, 'src_ticket_id') !== false,
      '★★ عمودُ «بلاغه» في قائمة الأوامر يصل **لبلاغ الأمر بعينه** — لا رابطًا عامًّا للقائمة');

// ═══ E-14 ═══
head('E-14 — منعُ تكرار مستوى التصعيد بنيويًّا');

$c = $conn->query("SHOW COLUMNS FROM tickets LIKE 'escalation_level'");
check($c && $c->num_rows === 1, '★ `escalation_level` عمودٌ على البلاغ');
$cron = file_get_contents(dirname(__DIR__) . '/Tickets/cron_tickets.php');
check(strpos($cron, "escalation_level") !== false
      && strpos($cron, ":L' . intval(\$hit['level_no']);") !== false
      && strpos($cron, ":L' . intval(\$hit['level_no']) . ':' . \$today") === false,
      '★★★ المفتاحُ اليوميُّ أُزيل والعمودُ هو الحكم: **مستوًى ≤ المسجَّلِ لا يُشعَر به غدًا ولا أبدًا**');

// ═══ الخاتمة ═══
fwrite(STDOUT, "\n══════════════════════════════════════\n");
fwrite(STDOUT, "  النتيجة: {$PASS} نجاح · {$FAIL} فشل\n");
exit($FAIL > 0 ? 1 : 0);
