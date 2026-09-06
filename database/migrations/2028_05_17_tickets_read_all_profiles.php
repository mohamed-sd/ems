<?php
/**
 * 2028_05_17_tickets_read_all_profiles.php — قراءةُ البلاغاتِ في كلِّ قالبٍ نافذ
 * ═══════════════════════════════════════════════════════════════════════════
 * **حكمُ المالك**: «كلُّ الأدوارِ تقرأ البلاغات — فكرةُ البلاغاتِ أن ترفعَ
 * الإدارةُ بلاغًا وتتابعَه حتى يُقفَل».
 *
 * ◆ **مراجعةُ الشاشاتِ قبلَ الكتابة** (ما تحتاجه الدورةُ من طرفِ المُبلِّغ):
 *   ① `tickets_list.php` صندوقٌ مبوَّبٌ: مفتوحة · تنتظر اعتمادا · بلاغاتي ·
 *      مغلقة · موجهة لإدارتي — وهو نفسُه ما كان `dept_inbox.php` قبلَ دمجِه.
 *   ② `ticket_form.php` وجهةُ النقرِ من القائمة، **وإنشاءُ البلاغِ فيها متاحٌ
 *      لكلِّ مستخدمٍ مسجَّلٍ بتصميمِ الشاشةِ نفسِها** — فهي بابُ الرفعِ أيضًا.
 *   ③ `my_tickets.php` بلاغاتي، قارئةٌ محضةٌ بلا فعلٍ كاتب.
 *   ④ `dept_inbox.php` محوِّلٌ إلى تبويبِ «موجهة» — يُمنح كيلا يُردَّ رابطٌ محفوظ.
 *   ⑤ `ticket_contextual_open.php` الرفعُ من موضعِ المشكلة (٢٣ من ٣٢ سلفًا).
 *
 * ◆ **والعزلُ لا ينخرم بهذا المنح** — مقيسٌ في `tickets_list.php:69-76`:
 *   غيرُ المديرِ الأعلى وغيرِ دورِ البلاغاتِ يُقيَّد بـ
 *   `owner_role_id IN (شجرة دوره) OR reporter_user_id = هو OR created_by = هو`،
 *   وتبويبُ «موجهة لإدارتي» معرِّفاتُه خرجت من `scopedQuery` بوحدتِه التنظيميّة،
 *   و`{TENANT_SCOPE}` نافذٌ فوقَ ذلك كلِّه. **فكلُّ دورٍ يرى بلاغاتِه لا بلاغاتِ
 *   غيرِه**، وهذا عينُ ما طلبه الحكم.
 *
 * ⛔ **قراءةٌ لا تحرير**: الصفوفُ الجديدةُ `can_add=can_edit=can_delete=0`.
 *   وانتقالاتُ الحالةِ في `ticket_form.php` محروسةٌ بـ`$can_manage` التي تشترط
 *   `(المدير الأعلى أو دور البلاغات) AND can_edit` — فالممنوحُ قراءةً لا ينقل حالةً.
 *
 * ⛔ **ولا يُدهَس صفٌّ قائم**: يُدرَج الناقصُ وحدَه، فلا تُخفَّض راياتُ دورٍ
 *   يملك تحريرًا اليوم (دورُ البلاغاتِ ومن معه).
 *
 * ◆ **والتجميدُ لا يشمل هذا المسار**: قادحاه على `gov_authority_grants` (منحة
 *   جديدة) و`gov_role_profiles` (تفعيل قالب) — ولا قادحَ على `gov_profile_items`.
 *   فهذا تعديلُ بنودِ قالبٍ نافذٍ قائم، لا إصدارُ منحةٍ ولا تفعيلُ قالب.
 *
 * التشغيل: php database/migrations/2028_05_17_tickets_read_all_profiles.php
 * العكس:   php database/migrations/2028_05_17_tickets_read_all_profiles_down.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit('connect fail: ' . $conn->connect_error . "\n"); }
$conn->set_charset('utf8mb4');
$one = function ($s) use ($conn) { $r = $conn->query($s); return $r ? (int) ($r->fetch_row()[0] ?? 0) : -1; };

$MARK = 'tkt_read_all:2028_05_17';
$SCREENS = array(
    'Tickets/tickets_list.php'            => 'صندوق البلاغات المبوب',
    'Tickets/ticket_form.php'             => 'ورقة البلاغ وبابُ رفعِه',
    'Tickets/my_tickets.php'              => 'بلاغاتي',
    'Tickets/dept_inbox.php'              => 'محول الى تبويب موجهة',
    'Tickets/ticket_contextual_open.php'  => 'الرفع من موضع المشكلة',
);

$cover = function ($code) use ($conn) {
    $c = $conn->real_escape_string($code);
    $r = $conn->query("SELECT COUNT(DISTINCT p.profile_id) FROM gov_profile_items i
                         JOIN gov_role_profiles p ON p.profile_id = i.profile_id AND p.state = 'active'
                        WHERE i.item_ref = '{$c}' AND i.allow = 1");
    return $r ? (int) ($r->fetch_row()[0] ?? 0) : -1;
};

$TOTAL = $one("SELECT COUNT(*) FROM gov_role_profiles WHERE state = 'active'");
echo "══ ① قبلَ الكتابة — القوالبُ النافذةُ {$TOTAL} ══════════════════════════\n";
$before = array();
foreach ($SCREENS as $code => $why) {
    $before[$code] = $cover($code);
    printf("   %-38s %2d من %d\n", $code, $before[$code], $TOTAL);
}
printf("   %-38s %2d من %d  (مرجعٌ لا يُمَسّ)\n", 'Portal/my_reports.php', $cover('Portal/my_reports.php'), $TOTAL);

/* ── ② الإدراجُ للناقصِ وحدَه ─────────────────────────────────────────────── */
echo "\n══ ② الكتابة — قراءةٌ فقط، والقائمُ لا يُمَسّ ═══════════════════════════\n";
$ins = $conn->prepare(
    "INSERT INTO gov_profile_items
       (company_id, profile_id, item_kind, item_ref, allow, can_add, can_edit, can_delete, seeded_from)
     SELECT p.company_id, p.profile_id, 'screen', ?, 1, 0, 0, 0, ?
       FROM gov_role_profiles p
      WHERE p.profile_id = ? AND p.state = 'active'");
$added = 0;
foreach ($SCREENS as $code => $why) {
    $c = $conn->real_escape_string($code);
    $rs = $conn->query(
        "SELECT p.profile_id FROM gov_role_profiles p
          WHERE p.state = 'active'
            AND NOT EXISTS (SELECT 1 FROM gov_profile_items i
                             WHERE i.profile_id = p.profile_id
                               AND i.item_kind = 'screen' AND i.item_ref = '{$c}')");
    $n = 0;
    while ($rs && ($row = $rs->fetch_assoc())) {
        $pid = (int) $row['profile_id'];
        $ins->bind_param('ssi', $code, $MARK, $pid);
        $ins->execute();
        $n += $ins->affected_rows;
    }
    $added += $n;
    printf("   %-38s صفوفٌ مضافة: %d\n", $code, $n);
}
$ins->close();
printf("   المجموع: %d\n", $added);

/* ── ③ الشواهدُ ───────────────────────────────────────────────────────────── */
echo "\n══ ③ بعدَ الكتابة ════════════════════════════════════════════════════\n";
$ok = true;
foreach ($SCREENS as $code => $why) {
    $now = $cover($code);
    if ($now !== $TOTAL) { $ok = false; }
    printf("   %-38s %2d من %d %s\n", $code, $now, $TOTAL, $now === $TOTAL ? '' : ' ناقص');
}

/* ⓐ ولا رايةَ كتابةٍ وُلدت من هذه الجولة */
$c = $conn->real_escape_string($MARK);
$wr = $one("SELECT COUNT(*) FROM gov_profile_items
             WHERE seeded_from = '{$c}' AND (can_add = 1 OR can_edit = 1 OR can_delete = 1)");
printf("   رايات كتابة وُلدت هنا: %d (المستهدف صفر)\n", $wr);

/* ⓑ والحكمُ الحيُّ من دالّةِ القرارِ نفسِها لدورٍ كان ممنوعًا */
require_once $ROOT . '/includes/permissions_helper.php';
$mid = $one("SELECT id FROM modules WHERE code = 'Tickets/tickets_list.php' LIMIT 1");
$t = ems_permission_trace($conn, $mid, 889);
printf("   المستخدم 889 (دور 15): يقرأ=%s · يحرر=%s\n",
       !empty($t['perms']['can_view']) ? 'نعم' : 'لا',
       !empty($t['perms']['can_edit']) ? 'نعم' : 'لا');
if (empty($t['perms']['can_view']) || !empty($t['perms']['can_edit'])) { $ok = false; }

/* ⓒ ضابطٌ سالبٌ: دورُ البلاغاتِ يحتفظ بتحريرِه ولم يُخفَّض */
$keep = $one("SELECT COUNT(*) FROM gov_profile_items i
               JOIN gov_role_profiles p ON p.profile_id = i.profile_id AND p.state = 'active'
              WHERE i.item_ref = 'Tickets/tickets_list.php' AND i.can_edit = 1");
printf("   قوالبُ تحتفظ بتحرير صندوق البلاغات: %d (كان 1)\n", $keep);
if ($keep < 1) { $ok = false; }

if (!$ok) { exit("\n⛔ الشاهدُ لم يتحقّق — لا تلتزمْ قبلَ المراجعة.\n"); }

require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn);
echo "\n✔ تمّ — والحكمُ مقروءٌ من دالّةِ القرارِ لا من الجدول.\n";
