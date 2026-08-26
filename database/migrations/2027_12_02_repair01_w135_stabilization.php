<?php
/**
 * 2027_12_02_repair01_w135_stabilization.php
 * ═══════════════════════════════════════════════════════════════════════════
 * REPAIR01 · W13.5 — **بوّابةُ التثبيتِ البنيويِّ وجاهزيّةِ الحوكمة**
 * (‏أمرُ المالكِ المكتوب 2026-08-26 — البنود 3 · 4-8 · 9 · 11 · 12 · 15 · 17)
 *
 * ◆ **الأعمدةُ قبل الأحكام**: لوحةُ الوقوفِ قاست عشرةَ بنودٍ باقيةً، وستّةٌ
 *   منها سببُها واحد: **لا عمودَ يحمل الحكم**. فلا يُقال «439 سطحًا بلا تصنيف»
 *   ثمَّ يُطلَب تصنيفُها وليس في السجلِّ خانةٌ تُكتب فيها.
 *
 * ◆ **① حكمُ الملكيّةِ التساعيّ** (‏البند 3): تسعُ قيمٍ لا غير، يفرضها `CHECK`
 *   لا التوثيق. و`UNKNOWN` قيمةٌ **صريحةٌ** لا فراغ — فالمجهولُ يُعلَن ولا يُسكَت
 *   عنه، وحاجبٌ يعدُّه يرى ما لا يراه الفراغ.
 *
 * ◆ **② التصنيفُ مصدرٌ أم إسقاط** (‏البنود 4-8): و`chk_w135_src_once` **يمنع
 *   في القاعدة** أن يحمل سطحٌ تصنيفَ `SOURCE` وهو تابعٌ لغيرِ مالكِ حقيقتِه —
 *   وهذا هو «لا نفس الحقيقة Source في إدارتين» مفروضًا لا موصوفًا.
 *
 * ◆ **③ الاثنا عشرَ شرطًا** (‏البند 9): سبعةٌ منها لم يكن لها عمود. وتُضاف
 *   **بلا `NOT NULL`** لأنَّ القديمَ `Counted Debt` والجديدَ `Zero Tolerance` —
 *   والسقّاطةُ تُنفَّذ في الأداةِ لا بقيدٍ يدهس 651 سطحًا قائمًا.
 *   ⛔ **وهذا ليس تساهلًا**: قيدٌ يمنع الهجرةَ أو يدهس صفوفًا حيّةً أسوأُ من
 *   سقّاطةٍ تمنع الازدياد — وهو نصُّ الأمرِ في البند 9 حرفًا.
 *
 * ◆ **④ سندُ قرارِ المالك** (‏البند 11): خمسةُ حقولٍ ناقصة. و`chk_w135_appr_ref`
 *   يمنع `APPROVED` بلا مرجعِ جواب — **وهو القفلُ الذي غيابُه سمح بأن يُكتب
 *   قرارٌ نيابةً عن المالك**. ويُطبَّق على الجديدِ ولا يدهس القائم: القيدُ
 *   يشترط المرجعَ متى وُجد `recorded_by` — فالصفُّ القديمُ بلا مُسجِّلٍ يمرّ،
 *   والجديدُ لا يُكتب إلّا بسندِه.
 *
 * ◆ **⑤ قيدُ المراجعةِ العكسيّة** (‏البند 12): جدولٌ يحفظ حكمَ كلِّ قرار —
 *   فالفاحصُ كان يقيس ولا يجد أين يكتب (`CREATE` ممنوعٌ على `ems_app`).
 *
 * ◆ **⑥ تصنيفُ دَينِ المالية** (‏البند 15) **وقرارُ الشبح** (‏البند 17):
 *   عمودانِ بقائمتَي قيمٍ مغلقتَين — فالتسييجُ يُقاس ولا يُروى.
 *
 * ⛔ **ولا يُملأ هنا حكمٌ واحد** — الهجرةُ تفتح الخاناتِ والمصالحةُ تملؤها
 *   بمقيسٍ، وما لا يُحسم آليًّا يُرفع للمالك. **والعمودُ المفتوحُ ليس حكمًا.**
 *
 * التشغيل: php database/migrations/2027_12_02_repair01_w135_stabilization.php
 * التراجع: ..._down.php
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

function w135_tbl(mysqli $c, $t) {
    $r = $c->query("SHOW TABLES LIKE '" . $c->real_escape_string($t) . "'");
    return $r && $r->num_rows > 0;
}
function w135_col(mysqli $c, $t, $col) {
    if (!w135_tbl($c, $t)) { return false; }
    $r = $c->query("SHOW COLUMNS FROM `$t` LIKE '" . $c->real_escape_string($col) . "'");
    return $r && $r->num_rows > 0;
}
function w135_chk(mysqli $c, $name) {
    $r = $c->query("SELECT 1 FROM information_schema.CHECK_CONSTRAINTS
                     WHERE CONSTRAINT_SCHEMA = DATABASE()
                       AND CONSTRAINT_NAME = '" . $c->real_escape_string($name) . "' LIMIT 1");
    return $r && $r->num_rows > 0;
}
$err = 0; $did = 0;
function run(mysqli $c, $label, $sql) {
    global $err, $did;
    if ($c->query($sql) === true) { echo "  ✔ $label\n"; $did++; return true; }
    echo "  ✘ $label — " . $c->error . "\n"; $err++; return false;
}

echo "\n═══ W13.5 · تثبيتٌ بنيويٌّ — أمرُ المالك 2026-08-26 ═══\n\n";

/* ═══ ① سجلُّ الشاشات — أعمدةُ الحكمِ السبعةُ الناقصة ══════════════════════ */
echo "① سجلُّ الشاشات — خاناتُ الحكم\n";
$SR = array(
    'canonical_label_ar' => "VARCHAR(190) NOT NULL DEFAULT '' COMMENT 'المسمى المعياري المعروض - البند 9'",
    'surface_kind'       => "VARCHAR(24) NOT NULL DEFAULT '' COMMENT 'SOURCE او PROJECTION - البنود 4-8'",
    'ownership_verdict'  => "VARCHAR(24) NOT NULL DEFAULT '' COMMENT 'حكم الملكية التساعي - البند 3'",
    'action_guard'       => "VARCHAR(40) NOT NULL DEFAULT '' COMMENT 'حارس الفعل الخادمي عند وجود كتابة'",
    'permission_policy'  => "VARCHAR(60) NOT NULL DEFAULT '' COMMENT 'سياسة الصلاحية المرجعية'",
    'grain_ar'           => "VARCHAR(190) NOT NULL DEFAULT '' COMMENT 'حبة السطح - ما الصف الواحد فيه'",
    'source_of_truth'    => "VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'الجدول او السجل الحاكم'",
    'state_model_ref'    => "VARCHAR(90) NOT NULL DEFAULT '' COMMENT 'مرجع الة الحالة عند انطباقها'",
    'finance_debt_class' => "VARCHAR(24) NOT NULL DEFAULT '' COMMENT 'تصنيف دين المالية - البند 15'",
    'debt_owner'         => "VARCHAR(24) NOT NULL DEFAULT '' COMMENT 'مالك اغلاق الدين'",
    'debt_wave'          => "VARCHAR(12) NOT NULL DEFAULT '' COMMENT 'موجة اغلاق الدين'",
    'verdict_rule'       => "VARCHAR(240) NOT NULL DEFAULT '' COMMENT 'قاعدة الاشتقاق - لا حكم بلا قاعدة'",
    'verdict_at'         => "DATETIME NULL DEFAULT NULL",
);
foreach ($SR as $c => $def) {
    if (w135_col($conn, 'repair01_screen_registry', $c)) { echo "  ◆ $c قائم\n"; continue; }
    run($conn, "عمود $c", "ALTER TABLE `repair01_screen_registry` ADD COLUMN `$c` $def");
}

/* ◆ **تسعُ قيمٍ لا غير** — و`''` مسموحٌ للقديمِ الذي لم يُصالَح بعد، فالقيدُ
     يمنع **الاختراعَ** ولا يدهس ما لم يُحكَم فيه. */
if (!w135_chk($conn, 'chk_w135_ownv')) {
    run($conn, 'قيد chk_w135_ownv — تسعة احكام لا غير',
        "ALTER TABLE `repair01_screen_registry` ADD CONSTRAINT `chk_w135_ownv`
         CHECK (`ownership_verdict` IN ('', 'DOMAIN_SOURCE','DOMAIN_PROJECTION','PLATFORM_SHARED',
               'EXECUTIVE_PROJECTION','AUDIT_ASSURANCE','TAB_CHILD','LEGACY','RETIRE','UNKNOWN'))");
}
if (!w135_chk($conn, 'chk_w135_kind')) {
    run($conn, 'قيد chk_w135_kind — مصدر او اسقاط',
        "ALTER TABLE `repair01_screen_registry` ADD CONSTRAINT `chk_w135_kind`
         CHECK (`surface_kind` IN ('', 'SOURCE','PROJECTION'))");
}
if (!w135_chk($conn, 'chk_w135_fin')) {
    run($conn, 'قيد chk_w135_fin — تصنيف دين المالية',
        "ALTER TABLE `repair01_screen_registry` ADD CONSTRAINT `chk_w135_fin`
         CHECK (`finance_debt_class` IN ('', 'TARGET','SOURCE','PROJECTION','DUPLICATE','MERGE','RETIRE','LEGACY_READ_ONLY'))");
}
/* ⛔ **ولا حكمَ بلا قاعدةٍ مكتوبة** — وهذا يمنع أن يُملأ العمودُ بالجملةِ
     بلا سببٍ يُراجَع، وهو النمطُ الذي أنتج قرارًا مكتوبًا نيابةً عن المالك. */
if (!w135_chk($conn, 'chk_w135_why')) {
    run($conn, 'قيد chk_w135_why — لا حكم بلا قاعدة',
        "ALTER TABLE `repair01_screen_registry` ADD CONSTRAINT `chk_w135_why`
         CHECK (`ownership_verdict` = '' OR `verdict_rule` <> '')");
}

/* ═══ ② قرارُ المالك — حقولُ البند 11 ═══════════════════════════════════ */
echo "\n② سجلُّ القرارات — سندُ الاعتماد (البند 11)\n";
$DC = array(
    'decision_source'          => "VARCHAR(190) NOT NULL DEFAULT '' COMMENT 'مصدر القرار - وثيقة او محادثة او امر'",
    'owner_decision_reference' => "VARCHAR(300) NOT NULL DEFAULT '' COMMENT 'مرجع جواب المالك بدليله'",
    'recorded_by'              => "VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'من قيد القرار في المخزن'",
    'evidence_ref'             => "VARCHAR(300) NOT NULL DEFAULT '' COMMENT 'تجزئة او مسار الدليل'",
    'effective_from'           => "DATE NULL DEFAULT NULL COMMENT 'سريان القرار'",
);
foreach ($DC as $c => $def) {
    if (w135_col($conn, 'repair01_decisions', $c)) { echo "  ◆ $c قائم\n"; continue; }
    run($conn, "عمود $c", "ALTER TABLE `repair01_decisions` ADD COLUMN `$c` $def");
}
/* ◆ **القفلُ الذي غيابُه سمح بالانتحال**: مَن يقيّد قرارًا يلزمه مرجعُ جوابٍ.
     والصفُّ القديمُ بلا `recorded_by` يمرّ — فالقيدُ يحرس الجديدَ لا يدهس القديم. */
if (!w135_chk($conn, 'chk_w135_appr_ref')) {
    run($conn, 'قيد chk_w135_appr_ref — لا اعتماد مقيد بلا مرجع جواب',
        "ALTER TABLE `repair01_decisions` ADD CONSTRAINT `chk_w135_appr_ref`
         CHECK (`recorded_by` = '' OR `status` <> 'APPROVED' OR `owner_decision_reference` <> '')");
}

/* ═══ ③ قيدُ المراجعةِ العكسيّة (البند 12) ══════════════════════════════ */
echo "\n③ قيدُ المراجعةِ العكسيّة\n";
if (!w135_tbl($conn, 'repair01_decision_audit')) {
    run($conn, 'جدول repair01_decision_audit',
        "CREATE TABLE `repair01_decision_audit` (
           `decision_id` VARCHAR(40)  NOT NULL,
           `verdict`     VARCHAR(40)  NOT NULL COMMENT 'الاحكام الخمسة بنص البند 12',
           `why`         VARCHAR(400) NOT NULL,
           `audited_at`  DATETIME     NOT NULL,
           PRIMARY KEY (`decision_id`),
           KEY `ix_dcaud_v` (`verdict`),
           CONSTRAINT `chk_w135_aud_v` CHECK (`verdict` IN
             ('VALID_APPROVAL','MISSING_APPROVAL_REFERENCE','SYSTEM_ASSUMED_APPROVAL',
              'CONFLICTING_APPROVAL','LEGACY_UNVERIFIED'))
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
           COMMENT='مراجعة عكسية لقرارات المالك - امر 2026-08-26 البند 12'");
}

/* ═══ ④ قرارُ الشبح (البند 17) ═══════════════════════════════════════════ */
echo "\n④ قرارُ الشبحِ — ستُّ قيمٍ لا غير\n";
if (!w135_col($conn, 'repair01_target_gaps', 'ghost_disposition')) {
    run($conn, 'عمود ghost_disposition',
        "ALTER TABLE `repair01_target_gaps` ADD COLUMN `ghost_disposition` VARCHAR(20) NOT NULL DEFAULT ''
         COMMENT 'BUILD MERGE TAB PROJECTION RETIRE NOT_APPLICABLE - البند 17'");
}
if (!w135_col($conn, 'repair01_target_gaps', 'disposition_why')) {
    run($conn, 'عمود disposition_why',
        "ALTER TABLE `repair01_target_gaps` ADD COLUMN `disposition_why` VARCHAR(300) NOT NULL DEFAULT ''");
}
if (!w135_chk($conn, 'chk_w135_ghost')) {
    run($conn, 'قيد chk_w135_ghost — قرار من الستة بسببه',
        "ALTER TABLE `repair01_target_gaps` ADD CONSTRAINT `chk_w135_ghost`
         CHECK ((`ghost_disposition` = '' AND `disposition_why` = '')
             OR (`ghost_disposition` IN ('BUILD','MERGE','TAB','PROJECTION','RETIRE','NOT_APPLICABLE')
                 AND `disposition_why` <> ''))");
}

/* ═══ ⑤ الإثباتُ بالكسرِ لا بالإعلان ══════════════════════════════════════
     ⛔ **قيدٌ يُنشأ ولا يُجرَّب دعوى**: تُحاوَل كتابةٌ مخالفةٌ ويُشترط الردّ. */
echo "\n⑤ إثباتُ القيودِ بمحاولةِ خرقِها\n";
$PROBE = array(
    array('حكمُ ملكيّةٍ مخترَع', 'chk_w135_ownv',
          "UPDATE `repair01_screen_registry` SET `ownership_verdict` = 'ZZZ_FAKE', `verdict_rule` = 'probe'
            WHERE `screen_id` = (SELECT z.screen_id FROM (SELECT screen_id FROM repair01_screen_registry LIMIT 1) z)"),
    array('حكمٌ بلا قاعدة', 'chk_w135_why',
          "UPDATE `repair01_screen_registry` SET `ownership_verdict` = 'LEGACY', `verdict_rule` = ''
            WHERE `screen_id` = (SELECT z.screen_id FROM (SELECT screen_id FROM repair01_screen_registry LIMIT 1) z)"),
    array('تصنيفُ سطحٍ مخترَع', 'chk_w135_kind',
          "UPDATE `repair01_screen_registry` SET `surface_kind` = 'MAYBE'
            WHERE `screen_id` = (SELECT z.screen_id FROM (SELECT screen_id FROM repair01_screen_registry LIMIT 1) z)"),
    array('قرارُ شبحٍ بلا سبب', 'chk_w135_ghost',
          "UPDATE `repair01_target_gaps` SET `ghost_disposition` = 'BUILD', `disposition_why` = ''
            WHERE `id` = (SELECT z.id FROM (SELECT id FROM repair01_target_gaps LIMIT 1) z)"),
);
$held = 0;
foreach ($PROBE as $pr) {
    list($what, $name, $sql) = $pr;
    if (!w135_chk($conn, $name)) { echo "  ⚠ $name غيرُ قائمٍ فلا يُجرَّب\n"; $err++; continue; }
    if (@$conn->query($sql) === true) {
        echo "  ✘ $what — **مرَّ** والقيدُ $name معطَّل\n"; $err++;
    } else { echo "  ✔ $what — ردَّه $name\n"; $held++; }
}

/* ═══ ⑥ الحصيلة ═══════════════════════════════════════════════════════════ */
echo "\n────────────────────────────────────────────────────────────\n";
printf("نُفِّذ %d · قيودٌ ردَّت %d من %d · أخطاء %d\n", $did, $held, count($PROBE), $err);
echo ($err === 0 ? "الحكم: الهجرةُ تمَّت ✔\n" : "الحكم: فيها $err خطأ ✘\n");
echo "الخطوةُ التالية: php tools/repair01_w135_reconcile.php\n";
exit($err === 0 ? 0 : 1);
