<?php
/**
 * 2028_05_22_perm03_cap_ruling_and_cleanup.php — حكمُ بندِ السقفِ وكنسُ المسبار
 * ═══════════════════════════════════════════════════════════════════════════
 * ثلاثةُ أشقٍّ كشفتها **المراجعةُ العكسيّة** على جولةِ PERM-03 نفسِها:
 *
 * ① **بندُ `cap` قارئٌ بلا مستهلك.** ادّعيتُ «الأنواعُ الخمسةُ منفَّذةٌ بإنفاذٍ
 *    حقيقيّ» — والمقيس: `ems_profile_caps()` **لا يُنادى من ملفِّ إنتاجٍ واحد**.
 *    ولا يُترك ادّعاءٌ بلا تصحيح: يُسجَّل الحكمُ صراحةً بأنّ البندَ **مربوطٌ
 *    ومقروءٌ وغيرُ نافذٍ في قرارِ الفتح** — وهو عينُ ما حكم به المالكُ في ق-٤
 *    على `gov_authority_limits` نفسِها. فالاتّساقُ مع حكمٍ قائمٍ لا التفافٌ عليه.
 *
 * ② **تناقضٌ بين سجلَّين حاكمَين — يُسمّى ولا يُحسَم بيدي.**
 *    `fin_approval_types` APR-2 يجعل الدورَ 18 صاحبَ الاعتمادِ الموازنيّ،
 *    و`gov_authority_limits` LIMIT-01 يُدرج `fin.approve.budget` في **ممنوعاتِ**
 *    الدورِ 18 بينما نصُّ منعِه «اعتمادُ طلبِ الإدارةِ النهائي» (وهو APR-1).
 *    والقرينةُ أنّ LIMIT-02 وLIMIT-03 يقابل كلٌّ منهما فعلًا واحدًا بمنعٍ واحد،
 *    فـLIMIT-01 وحدَه يُدرج فعلَين لمنعٍ واحد. ⇒ **الأرجحُ فائضُ مفردةٍ في
 *    السجلّ**، وتصحيحُه قرارُ صاحبِ السجلِّ لا قرارُ منفِّذ. ولهذا **لا يُوصَل
 *    `cap` إنفاذًا اليوم**: إنفاذُه على سجلٍّ متناقضٍ يمنع صاحبَ الاختصاصِ من
 *    اختصاصِه.
 *
 * ③ **بقيّةُ مسبارٍ في السجلّ**: قالبُ `UAT-DEMO-01` المتقاعدُ من تجربةِ القبولِ
 *    في المتصفّح. يُنزَع — **وسطورُ أثرِه تبقى**: السجلُّ يحفظ أنَّ مسبارًا جرى
 *    ومتى وبأيِّ سبب، وذاك تاريخٌ صحيحٌ لا يُمحى.
 *
 * التشغيل: php database/migrations/2028_05_22_perm03_cap_ruling_and_cleanup.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit('connect fail: ' . $conn->connect_error . "\n"); }
$conn->set_charset('utf8mb4');
$one = function ($s) use ($conn) { $r = @$conn->query($s); return $r ? (int) ($r->fetch_row()[0] ?? 0) : -1; };

echo "══ PERM-03 · حكمُ بندِ السقفِ وكنسُ المسبار ══════════════════════════\n";

/* ═══ ① الحكمُ المسجَّلُ لبندِ السقف ═══════════════════════════════════════ */
$rulings = array(
    array(
        'layer_key' => 'profile_item_cap',
        'ruling' => 'not_in_force',
        'in_force_scope' => 'الربط والقراءة: القالب يعلن الحدود التي تحكم الدور وتعرضها الشاشة',
        'not_in_force_scope' => 'قرار فتح الشاشة وقرار تنفيذ الفعل',
        'reason' => 'قارئ بلا مستهلك انتاجي، والسجل الحاكم فيه تناقض مسمى (LIMIT-01) '
                  . 'فانفاذه يمنع صاحب الاختصاص من اختصاصه. متسق مع حكم ق-٤ على السجل نفسه.',
        'doc_ref' => 'PERM-03-REVERSE-AUDIT',
        'decided_by' => 'منفذ PERM-03 بمراجعة عكسية',
    ),
    array(
        'layer_key' => 'gov_authority_limits_LIMIT-01',
        'ruling' => 'not_in_force',
        'in_force_scope' => 'لا مجال — صف متناقض يرفع للمالك',
        'not_in_force_scope' => 'كل قرار حي',
        'reason' => 'يدرج fin.approve.budget في ممنوعات الدور 18 ونص منعه «اعتماد طلب الادارة '
                  . 'النهائي» وهو APR-1، والدور 18 صاحب APR-2 في سجل الانواع. فائض مفردة مرجح.',
        'doc_ref' => 'PERM-03-REVERSE-AUDIT',
        'decided_by' => 'منفذ PERM-03 بمراجعة عكسية',
    ),
);
foreach ($rulings as $r) {
    $k = $conn->real_escape_string($r['layer_key']);
    if ($one("SELECT COUNT(*) FROM perm01_layer_ruling WHERE layer_key = '{$k}'") > 0) {
        printf("   = حكمٌ قائمٌ سلفًا: %s\n", $r['layer_key']);
        continue;
    }
    $st = $conn->prepare("INSERT INTO perm01_layer_ruling
          (layer_key, ruling, in_force_scope, not_in_force_scope, reason, doc_ref, decided_by, decided_at)
          VALUES (?,?,?,?,?,?,?,NOW())");
    $st->bind_param('sssssss', $r['layer_key'], $r['ruling'], $r['in_force_scope'],
                    $r['not_in_force_scope'], $r['reason'], $r['doc_ref'], $r['decided_by']);
    $st->execute(); $st->close();
    printf("   + حكمٌ مسجَّل: %-34s %s\n", $r['layer_key'], $r['ruling']);
}

/* ═══ ② كنسُ بقيّةِ المسبار ═══════════════════════════════════════════════ */
echo "\n══ كنسُ بقايا المسابر ═══════════════════════════════════════════════\n";
$gone = 0;
$q = $conn->query("SELECT profile_id, profile_code, state FROM gov_role_profiles
                    WHERE profile_code LIKE 'UAT-%' OR profile_code LIKE 'ZZTEST%'");
$targets = array();
while ($q && ($x = $q->fetch_assoc())) { $targets[] = $x; }
foreach ($targets as $t) {
    $pid = (int) $t['profile_id'];
    $held = $one("SELECT COUNT(*) FROM gov_authority_grants WHERE profile_id = {$pid} AND revoked_at IS NULL");
    if ($held > 0) {
        printf("   ⛔ %s له %d حاملًا حيًّا — لا يُنزع\n", $t['profile_code'], $held);
        continue;
    }
    $conn->query("DELETE FROM gov_authority_grants WHERE profile_id = {$pid}");
    $conn->query("DELETE FROM gov_profile_items WHERE profile_id = {$pid}");
    $conn->query("DELETE FROM gov_profile_activation_approval WHERE profile_id = {$pid}");
    $conn->query("DELETE FROM gov_role_profiles WHERE profile_id = {$pid}");
    printf("   - نُزع %s (كان %s) — وسطورُ أثرِه باقيةٌ تاريخًا\n", $t['profile_code'], $t['state']);
    $gone++;
}
if (!$targets) { echo "   = لا بقايا\n"; }

/* ═══ ③ الشاهد ═══════════════════════════════════════════════════════════ */
echo "\n══ الشاهد ══════════════════════════════════════════════════════════\n";
printf("   بقايا مسابرَ في السجلّ: %d\n",
    $one("SELECT COUNT(*) FROM gov_role_profiles WHERE profile_code LIKE 'UAT-%' OR profile_code LIKE 'ZZTEST%'"));
printf("   أحكامُ طبقاتٍ مسجَّلة: %d\n", $one("SELECT COUNT(*) FROM perm01_layer_ruling"));
printf("   سطورُ أثرٍ محفوظة: %d\n", $one("SELECT COUNT(*) FROM perm_change_log"));
$cov = $one("SELECT COUNT(DISTINCT u.id) FROM users u
              JOIN gov_authority_grants g ON g.user_id=u.id AND g.revoked_at IS NULL
              JOIN gov_role_profiles pr ON pr.profile_id=g.profile_id AND pr.state='active'
             WHERE u.is_deleted=0 AND u.status='active' AND u.company_id=4");
printf("   التغطية: %d من %d\n", $cov, $one("SELECT COUNT(*) FROM users WHERE is_deleted=0 AND status='active' AND company_id=4"));

require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn);
echo "\n✔ تمّ — والادّعاءُ صار مطابقًا للمقيس.\n";
