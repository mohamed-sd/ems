<?php
/**
 * 2027_08_21_sensitive_fields_enforcement_unify.php
 *   إدخالُ التسعةَ عشرَ حقلًا حساسًا في الإنفاذ — INJ-FIX-01 · الموجة أ ② · GAP-10
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **السببُ الجذريّ — شرطُ إنفاذٍ حرفيٌّ وقاموسان لقيمةٍ واحدة**:
 *   كلُّ أبوابِ الإنفاذِ تشترط `status = 'معتمد'` حرفًا:
 *     · `FieldGovernor::sensitiveFieldsOf()`  — حجبُ العمودِ في الخادم
 *     · `ExcelService` (ارتدادُ الحاكمِ الغائب) — استبعادُ العمودِ من التصدير
 *     · `excel.php` §export                    — قيدُ الاطّلاعِ قبلَ بثِّ الملف
 *   و`tools/u12_setup.php:175` و`tools/u12_analysis_register.php:200` كتبتا
 *   تسعةَ عشرَ صفًّا بـ`status='active'` — **قيمةٌ ثانيةٌ لمعنًى واحد**. فالشرطُ
 *   لا يطابقها، فتمرُّ الحقولُ بلا حجبٍ وبلا سجلِّ اطّلاع: هامشٌ وأرصدةٌ وربحٌ
 *   تشغيليٌّ وتدفقٌ نقديٌّ تُعرض وتُصدَّر لمن لا يملكها.
 *   ◆ والقاموسُ مزدوجٌ في ثلاثةِ أعمدةٍ لا عمودٍ واحد:
 *       `status`           : `معتمد` مقابل `active`
 *       `exportable_flag`  : `لا`/`نعم` مقابل `0`
 *       `log_views_flag`   : `نعم`/`لا` مقابل `1`
 *     و`sensitiveFieldsOf` تقرأ الرايتَين بـ`mb_strpos(…, 'نعم')` — فـ`'1'`
 *     تُقرأ «لا يُسجَّل الاطّلاع». فتصحيحُ `status` وحدَه يُدخلها الإنفاذَ
 *     **ويُبقي سجلَّ الاطّلاعِ صامتًا**. ثلاثةُ أعمدةٍ تُوحَّد معًا أو لا تُوحَّد.
 *
 * ◆ **والاعتمادُ لا يكفي وحدَه — وإلا صار الإصلاحُ فقدًا**: الحقلُ المعتمدُ
 *   «لا يُصدَّر» **بلا سياسةِ منحٍ يُحجب عن الجميع** إلا المديرَ الأعلى (فشلٌ
 *   مغلقٌ معلَنٌ في `FieldGovernor` ⑧). والتسعةَ عشرَ **بلا سياسةٍ واحدة**
 *   (`sensitive_field_policies` = ٦ صفوفٍ لا يخصُّها منها شيء). فاعتمادُها بلا
 *   منحٍ يحجبها عن المدير الماليِّ نفسِه. ⇐ تُصدَر سياسةُ منحٍ لكلِّ صفٍّ،
 *   **مقروءةً من إعلانِ الصفِّ نفسِه** (`from_visible_to`) لا من رأيٍ جديد.
 *
 * ◆ **وحدُّ التفويض — دورٌ لم يُسمَّ لا يُخمَّن**: الإعلانُ يقول «المدير المالي
 *   والحوكمة» و«المدير المالي والنائب المالي». وفي `roles` أدوارٌ تطابق الأولَ
 *   بالاسم (32 المدير المالي · 17 إدارة المالية · 19 مدير الإدارة المالية)،
 *   **ولا دورَ اسمُه «الحوكمة» ولا «النائب المالي»**. فيُمنح المطابِقُ يقينًا
 *   ويُترك المشتبَهُ — والنقصُ في المنحِ منعٌ (آمن) والزيادةُ فيه تسريبٌ (خطر).
 *   وقد سُجِّل مقابلُ الدورَين `BLOCKED_OWNER_INPUT` في دفترِ الاستقبال.
 *
 * ◆ **وقيدٌ يمنع العودة** — لا تصحيحُ صفوفٍ فحسب: `CHECK` على `status` يرفض
 *   أيَّ قيمةٍ خارجَ القاموسِ المعلَن، فلا تعود أداةُ بذرٍ تكتب قاموسًا ثانيًا.
 *   وأُصلحت الأداتان الكاتبتان في المصدرِ أيضًا — فالقيدُ يمنع والمصدرُ لا يحاول.
 *
 * ◆ **ولا يُمسُّ الخمسةَ عشرَ المعتمَدةُ سلفًا** ولا تُغيَّر سياسةٌ قائمة.
 *
 * التشغيل:  php database/migrations/2027_08_21_sensitive_fields_enforcement_unify.php
 * الرجوع :  php database/migrations/2027_08_21_sensitive_fields_enforcement_unify.php --revert
 * الشاهد :  php tests/injfix01_sensitive_fields_nine_channels_proof.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';

$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$revert = in_array('--revert', $argv, true);

/** القاموسُ المعلَنُ لحالةِ السياسة — وهو ما يفرضه القيد. */
$STATUS_VOCAB = array('مسودة', 'معتمد', 'ملغاة');

/** الأدوارُ المطابِقةُ يقينًا لإعلانِ `from_visible_to` — بمعرِّفاتِها في `roles`. */
$GRANT_ROLES = array('32', '17', '19');   // المدير المالي · إدارة المالية · مدير الإدارة المالية

$CHECK = 'chk_scr_sensitive_fields_status';

/* ══════════════════════════ الرجوع ══════════════════════════════════════ */
if ($revert) {
    $conn->query("ALTER TABLE `scr_sensitive_fields` DROP CONSTRAINT `{$CHECK}`");
    echo "↺ القيد: " . ($conn->errno ? "لم يُحذف ({$conn->error})" : "حُذف") . "\n";

    $conn->query("DELETE FROM `sensitive_field_policies` WHERE `field_code` IN (
                    SELECT CONCAT(`table_name`,'.',`field_name`) FROM (
                      SELECT `table_name`,`field_name` FROM `scr_sensitive_fields`
                       WHERE `no_policy` LIKE 'U12-%') t)");
    echo "↺ سياساتُ المنح: حُذف {$conn->affected_rows}\n";

    $conn->query("UPDATE `scr_sensitive_fields`
                     SET `status` = 'active', `status_label` = NULL,
                         `exportable_flag` = '0', `log_views_flag` = '1'
                   WHERE `no_policy` LIKE 'U12-%'");
    echo "↺ الصفوف: أُعيدت {$conn->affected_rows} إلى قاموسِها السابق\n";
    exit(0);
}

/* ══════════════════════════ ① جردُ ما سيُمسّ ═══════════════════════════ */
$targets = array();
$r = $conn->query("SELECT `id`,`no_policy`,`table_name`,`field_name`,`from_visible_to`
                     FROM `scr_sensitive_fields`
                    WHERE `status` <> 'معتمد' ORDER BY `id`");
while ($r && $x = $r->fetch_assoc()) { $targets[] = $x; }
echo "خارجَ الإنفاذِ قبل: " . count($targets) . " صفًّا\n";
if (!$targets) { echo "↺ عطالة: لا صفَّ خارجَ الإنفاذ — لم يُغيَّر شيء\n"; }

/* ══ ② توحيدُ القاموسِ في الأعمدةِ الثلاثةِ معًا ══════════════════════════
   ولا تُمسُّ رايةُ التصدير قيمةً: `'0'` تعني «لا» و`'1'` تعني «نعم» — تُترجَم
   لا تُقلَب. فالمعنى محفوظٌ والقاموسُ وحدَه يتوحّد. */
$conn->query("UPDATE `scr_sensitive_fields`
                 SET `exportable_flag` = CASE WHEN `exportable_flag` IN ('1','نعم') THEN 'نعم' ELSE 'لا' END,
                     `log_views_flag`  = CASE WHEN `log_views_flag`  IN ('1','نعم') THEN 'نعم' ELSE 'لا' END
               WHERE `status` <> 'معتمد'");
echo "② القاموس: وُحِّدت رايتا التصديرِ والاطّلاعِ في {$conn->affected_rows} صفًّا\n";

$conn->query("UPDATE `scr_sensitive_fields`
                 SET `status` = 'معتمد', `status_label` = 'معتمد'
               WHERE `status` <> 'معتمد'");
$approved = $conn->affected_rows;
echo "② الاعتماد: أُدخل {$approved} صفًّا في الإنفاذ\n";

/* ══ ③ سياسةُ منحٍ لكلِّ صفٍّ — مقروءةٌ من إعلانِ الصفِّ لا من رأي ═════════
   `masking_rule='full'` لأن الإعلانَ يقول «يُحجب من الخادم» — لا إخفاءَ عرض.
   و`classification='pricing'` لأن الحقولَ ماليةُ قيمةٍ ومجالُ منحِها `pricing`؛
   وقد قِيس أن `sensitive_access_grants` **فارغ**، فمسارُ المنحِ الفرديِّ عاطلٌ
   اليومَ والحكمُ واقعٌ على قائمةِ الأدوارِ وحدَها. */
$ins = $conn->prepare(
    "INSERT INTO `sensitive_field_policies` (`field_code`,`classification`,`masking_rule`,`allowed_roles_json`)
     SELECT ?, 'pricing', 'full', ? FROM DUAL
      WHERE NOT EXISTS (SELECT 1 FROM `sensitive_field_policies` WHERE `field_code` = ?)");
$rolesJson = json_encode($GRANT_ROLES);
$made = 0; $skipped = 0;
foreach ($targets as $t) {
    $code = $t['table_name'] . '.' . $t['field_name'];
    $ins->bind_param('sss', $code, $rolesJson, $code);
    if (!$ins->execute()) { exit("✘ فشلَ إدراجُ سياسةِ «{$code}»: " . $ins->error . "\n"); }
    if ($conn->affected_rows > 0) { $made++; } else { $skipped++; }
}
$ins->close();
echo "③ سياساتُ المنح: أُنشئت {$made} · قائمةٌ سلفًا {$skipped} · الأدوار " . $rolesJson . "\n";

/* ══ ③-ب الحقلُ المأذونُ بالتصديرِ بلا أهليةٍ مُعلَنة ═══════════════════════
   ◆ **مقيسٌ حيًّا**: `suppliers.tax_number` رايتُه `exportable='نعم'` وإعلانُه
     «المالية والحوكمة» — **وبلا سياسةِ أهلية**. وبعدَ أن صار «قابلٌ للتصدير»
     شرطًا لازمًا لا كافيًا في `FieldGovernor::exportableColumns` (فقد كان
     يتخطّى فحصَ الأهليةِ كلَّه فيُصدَّر لكلِّ دور)، يصير بلا سياسةٍ محجوبًا
     عن **الماليةِ نفسِها** — فيتحوّل سدُّ التسريبِ إلى فقد.
   ⇐ فتُصدَر أهليتُه من إعلانِه هو، لا من رأيٍ جديد. */
$r = $conn->query("SELECT CONCAT(`table_name`,'.',`field_name`) code
                     FROM `scr_sensitive_fields`
                    WHERE `status` = 'معتمد' AND `exportable_flag` = 'نعم'");
$exportables = array();
while ($r && $x = $r->fetch_assoc()) { $exportables[] = $x['code']; }
$madeB = 0;
foreach ($exportables as $code) {
    $st = $conn->prepare(
        "INSERT INTO `sensitive_field_policies` (`field_code`,`classification`,`masking_rule`,`allowed_roles_json`)
         SELECT ?, 'pricing', 'none', ? FROM DUAL
          WHERE NOT EXISTS (SELECT 1 FROM `sensitive_field_policies` WHERE `field_code` = ?)");
    $st->bind_param('sss', $code, $rolesJson, $code);
    if (!$st->execute()) { exit("✘ فشلَ إدراجُ أهليةِ «{$code}»: " . $st->error . "\n"); }
    if ($conn->affected_rows > 0) { $madeB++; }
    $st->close();
}
echo "③-ب أهليةُ المأذونِ بالتصدير: " . count($exportables) . " حقلًا مأذونًا · أُنشئت {$madeB} سياسة\n";

/* ══ ③-ج أهليةُ `employees.phone` — الحقلُ الذي تبثُّه الواجهةُ البرمجية ═════
   ◆ **مقيسٌ حيًّا**: ثلاثةُ متحكِّمات (`board` · `operations` · `timesheet`)
     تُرجع `employees.phone` كاملًا. والصفُّ مسجَّلٌ حساسًا (SEN-002) بإعلانِ
     إخفاءٍ «آخر 3 أرقام لغير المخول» — **وبلا سياسةِ أهلية**. و`SensitiveFieldGuard`
     يُمرِّر ما لا سياسةَ له («حقلٌ غيرُ مصنَّف»)، فالوصلُ بالحارسِ وحدَه لا يحجب
     شيئًا ما لم تُصدَر أهليتُه.
   ◆ **والتقنيعُ جزئيٌّ لا كاملٌ** — نصُّ الإعلانِ لا اجتهادُ المنفِّذ.
   ◆ **وحدُّ التفويض**: الإعلانُ «إدارته المباشرة والموارد». و«الموارد البشرية»
     دورٌ مطابقٌ يقينًا (4)، و«إدارته المباشرة» **علاقةٌ لا دورٌ ثابت** فلا
     تُترجَم إلى رقمٍ بالتخمين — سُجِّلت `BLOCKED_OWNER_INPUT`. والنقصُ في
     المنحِ تقنيعٌ (آمن) والزيادةُ فيه تسريبٌ (خطر). */
$phonePol = json_encode(array('4'));
$st = $conn->prepare(
    "INSERT INTO `sensitive_field_policies` (`field_code`,`classification`,`masking_rule`,`allowed_roles_json`)
     SELECT 'employees.phone', 'personal', 'partial', ? FROM DUAL
      WHERE NOT EXISTS (SELECT 1 FROM `sensitive_field_policies` WHERE `field_code` = 'employees.phone')");
$st->bind_param('s', $phonePol);
if (!$st->execute()) { exit("✘ فشلَ إدراجُ أهليةِ employees.phone: " . $st->error . "\n"); }
echo "③-ج أهليةُ employees.phone: " . ($conn->affected_rows > 0 ? "أُنشئت (تقنيعٌ جزئيّ · أدوار {$phonePol})" : "قائمةٌ سلفًا") . "\n";
$st->close();

/* ══ ④ القيدُ الذي يمنع عودةَ القاموسِ الثاني ══════════════════════════════ */
$exists = $conn->query("SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
                         WHERE CONSTRAINT_SCHEMA = DATABASE()
                           AND TABLE_NAME = 'scr_sensitive_fields'
                           AND CONSTRAINT_NAME = '{$CHECK}'");
if ($exists && $exists->num_rows > 0) {
    echo "④ القيد: قائمٌ سلفًا — لم يُكرَّر\n";
} else {
    $list = "'" . implode("','", $STATUS_VOCAB) . "'";
    $conn->query("ALTER TABLE `scr_sensitive_fields`
                    ADD CONSTRAINT `{$CHECK}` CHECK (`status` IN ({$list}))");
    if ($conn->errno) { exit("✘ فشلَ القيد: {$conn->error}\n"); }
    echo "④ القيد: أُضيف — status ∈ ({$list})\n";
}

/* ══ ⑤ استيثاقٌ فوريّ ══════════════════════════════════════════════════════ */
$row = $conn->query("SELECT COUNT(*) FROM `scr_sensitive_fields` WHERE `status` <> 'معتمد'")->fetch_row();
echo "───────────────────────────────────────────────────────────────\n";
echo "خارجَ الإنفاذِ بعد: {$row[0]}\n";
$row = $conn->query("SELECT COUNT(*) FROM `scr_sensitive_fields` WHERE `log_views_flag` NOT IN ('نعم','لا')
                        OR `exportable_flag` NOT IN ('نعم','لا')")->fetch_row();
echo "رايةٌ خارجَ القاموس: {$row[0]}\n";
echo "الشاهد: php tests/injfix01_sensitive_fields_nine_channels_proof.php\n";
