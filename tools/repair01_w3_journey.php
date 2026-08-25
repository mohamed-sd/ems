<?php
/**
 * tools/repair01_w3_journey.php — رحلةُ المفتاح (‏W03 §٦-أ)
 * ═══════════════════════════════════════════════════════════════════════════
 * **إنشاءُ كيانٍ أمٍّ بمفتاحٍ واحد ← قراءتُه من ثلاثةِ نطاقاتٍ مختلفةٍ بالمفتاحِ
 *   نفسِه ← محاولةُ إنشاءِ معرّفٍ بديلٍ للحقيقةِ نفسِها تُرفَض ← تعديلُ الأمِّ
 *   يظهر عند القارئينَ الثلاثةِ بلا نسخٍ محليّ.**
 *
 * ◆ **المفتاحُ المختار `Person_ID`** لأنّه المفتاحُ الذي قِيس له سجلٌّ ثانٍ حيٌّ
 *   (`persons`) — فالمحطّةُ الثالثةُ فيه **رفضٌ مُنفَّذٌ في القاعدة** لا دعوى.
 *
 * ◆ **والقبولُ يقيس الأثرَ التجاريَّ لا صفَّ الحدثِ المُنشَأ** (§46): عند كلِّ
 *   مستهلكٍ يُقاس رقمٌ يعنيه — ساعاتُ تشغيلٍ منسوبةٌ · تأهيلٌ ساري · رخصةٌ
 *   سارية — ويُشترط أن يبقى **متعلّقًا بالمفتاحِ نفسِه** بعدَ تعديلِ الأمّ.
 *
 * ◆ **ولا نسخةَ محليّة**: تُقاس **بنيةُ** جدولِ المستهلكِ — عمودُ اسمٍ يحمل نسخةً
 *   من اسمِ الأمِّ يُسقط المحطّة. فالقراءةُ بالانضمامِ لا بالنسخ.
 *
 * ◆ **والبياناتُ لا تبقى**: كلُّ ما تكتبه الرحلةُ داخلَ معاملةٍ تُرجَع؛ ودليلُها
 *   وحدَه يُكتب بعدَ الإرجاعِ في `repair01_w3_journey`.
 *
 * التشغيل: php tools/repair01_w3_journey.php
 * الخروج : 0 عبرت كلُّ المحطّات · 1 محطّةٌ لم تعبر
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/lib/repair01_w3_scan.php';
require_once $ROOT . '/config.php';
if (!isset($conn) || !($conn instanceof mysqli)) { exit("تعذّر الاتصال بالقاعدة\n"); }
$conn->set_charset('utf8mb4');
while (ob_get_level()) { ob_end_clean(); }

$esc = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };
$one = function ($sql) use ($conn) { return repair01_w3_one($conn, $sql); };

/* مُعرِّفُ الجولةِ يُمرَّر أو يُشتقُّ من ساعةِ القاعدة — لا من ساعةِ العميل */
$RUN = 'W3J-' . (string) $one("SELECT DATE_FORMAT(NOW(), '%Y%m%d%H%i%s')");
$MARK = '__w3_journey_' . $RUN . '__';

echo "═══════════ رحلةُ المفتاح — REPAIR01 · W03 ═══════════\n";
echo "المفتاح: Person_ID · الجولة: $RUN\n\n";

$ST = array();   /* [no, station, consumer, expected, measured, effect, passed] */
$add = function ($no, $station, $consumer, $expected, $measured, $effect, $passed) use (&$ST) {
    $ST[] = array($no, $station, $consumer, $expected, $measured, $effect, $passed ? 1 : 0);
};

$company = (int) $one("SELECT company_id FROM employees GROUP BY company_id ORDER BY COUNT(*) DESC LIMIT 1");
if ($company <= 0) { exit("✘ لا كيانَ ذا بياناتٍ — الرحلةُ لا تُشغَّل على قاعدةٍ فارغة\n"); }

$conn->query('START TRANSACTION');
$personId = 0; $ok = true;

/* ═══ المحطّةُ ① — إنشاءُ كيانٍ أمٍّ بمفتاحٍ واحد ═══════════════════════════ */
$nameV1 = $MARK . '-v1';
$insOk = $conn->query("INSERT INTO employees (company_id, name, employee_code, employee_type, status, is_workforce)
                       VALUES ($company, '" . $esc($nameV1) . "', '" . $esc('W3J-' . $RUN) . "', 'operator', 'active', 1)");
if ($insOk) { $personId = (int) $conn->insert_id; }
$pass1 = ($personId > 0);
$add(1, 'إنشاءُ كيانٍ أمٍّ بمفتاحٍ واحد', 'employees (المالك)',
     'صفٌّ واحدٌ في الجدولِ المالكِ يحمل Company_ID ومفتاحًا واحدًا',
     $pass1 ? "Person_ID=$personId · Company_ID=$company" : 'فشل الإدراج: ' . $conn->error,
     $pass1 ? 'مفتاحٌ أمٌّ صالحٌ للإسنادِ في كلِّ النطاقات' : '—', $pass1);
if (!$pass1) { $ok = false; }

/* ═══ المحطّةُ ② — قراءةٌ من ثلاثةِ نطاقاتٍ بالمفتاحِ نفسِه ════════════════ */
$consumers = array(
    array('code' => 'WRK', 'ar' => '13 القوى التشغيلية · worker_qualification',
          'table' => 'worker_qualification',
          'insert' => "INSERT INTO worker_qualification (company_id, employee_id, record_type, title, issuer, expiry_date, is_critical)
                       VALUES ($company, {ID}, 'license', 'رخصة تشغيل', 'المرور', DATE_ADD(CURDATE(), INTERVAL 200 DAY), 1)",
          'effect_sql' => "SELECT COUNT(*) FROM worker_qualification
                            WHERE employee_id = {ID} AND expiry_date >= CURDATE()",
          'effect_ar' => 'تأهيلٌ ساري',
          'read_sql' => "SELECT e.name FROM worker_qualification q JOIN employees e ON e.id = q.employee_id
                          WHERE q.employee_id = {ID} LIMIT 1"),
    array('code' => 'SITE', 'ar' => '12 إدارة الموقع · timesheet',
          'table' => 'timesheet',
          'insert' => "INSERT INTO timesheet (company_id, employee_id, shift, date, shift_hours, executed_hours, total_work_hours, status)
                       VALUES ($company, {ID}, 'D', CURDATE(), 12, 9, 9, 'draft')",
          'effect_sql' => "SELECT COALESCE(SUM(executed_hours), 0) FROM timesheet WHERE employee_id = {ID}",
          'effect_ar' => 'ساعةُ تشغيلٍ منسوبة',
          'read_sql' => "SELECT e.name FROM timesheet t JOIN employees e ON e.id = t.employee_id
                          WHERE t.employee_id = {ID} LIMIT 1"),
    array('code' => 'FLEET', 'ar' => '04 الأسطول والأصول · equipment_operators',
          'table' => 'equipment_operators',
          'insert' => "INSERT INTO equipment_operators (company_id, employee_id, license_number, license_type, license_expiry_date, status)
                       VALUES ($company, {ID}, 'W3J-" . $RUN . "', 'heavy', DATE_ADD(CURDATE(), INTERVAL 300 DAY), 1)",
          'effect_sql' => "SELECT COUNT(*) FROM equipment_operators
                            WHERE employee_id = {ID} AND status = 1 AND license_expiry_date >= CURDATE()",
          'effect_ar' => 'رخصةٌ سارية',
          'read_sql' => "SELECT e.name FROM equipment_operators o JOIN employees e ON e.id = o.employee_id
                          WHERE o.employee_id = {ID} LIMIT 1"),
);
$effectBefore = array();
foreach ($consumers as $c) {
    $sub = function ($s) use ($personId) { return str_replace('{ID}', (string) $personId, $s); };
    $wrote = $personId > 0 ? $conn->query($sub($c['insert'])) : false;
    $readName = $wrote ? (string) $one($sub($c['read_sql'])) : '';
    /* لا نسخةَ محليّة: أعمدةُ اسمٍ في جدولِ المستهلكِ تحمل نسخةً من اسمِ الأمّ */
    $copyCols = array();
    $rr = $conn->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS
                         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $esc($c['table']) . "'
                           AND COLUMN_NAME REGEXP '(person|employee|operator|worker)_?name|^name$'");
    while ($rr && $x = $rr->fetch_row()) { $copyCols[] = $x[0]; }
    $eff = $wrote ? (string) $one($sub($c['effect_sql'])) : '0';
    $effectBefore[$c['code']] = $eff;
    $pass = ($wrote && $readName === $nameV1 && count($copyCols) === 0 && (float) $eff > 0);
    $add(2, 'قراءةٌ من نطاقٍ آخرَ بالمفتاحِ نفسِه', $c['ar'],
         'الاسمُ يعود من الأمِّ بالانضمامِ · صفرُ عمودِ نسخةٍ محليّة · أثرٌ تجاريٌّ > 0',
         ($wrote ? "الاسم المقروء: " . ($readName === $nameV1 ? 'مطابقٌ للأمّ' : "«$readName» ≠ الأمّ")
                 : 'فشل الإسناد: ' . $conn->error)
           . ' · أعمدةُ نسخةٍ: ' . (count($copyCols) ? implode('،', $copyCols) : '0'),
         $c['effect_ar'] . ' = ' . $eff, $pass);
    if (!$pass) { $ok = false; }
}

/* ═══ المحطّةُ ③ — معرّفٌ بديلٌ للحقيقةِ نفسِها يُرفَض ══════════════════════ */
/* ③-أ صفُّ سجلٍّ ثانٍ بلا كيانٍ قانونيّ (‏DEC-OPEN-03) */
$r3a = @$conn->query("INSERT INTO persons (full_name, active, person_class)
                      VALUES ('" . $esc($MARK . '-alt-nocompany') . "', 1, 'IDENTITY_ONLY')");
$pass3a = ($r3a === false);
$add(3, 'معرّفٌ بديلٌ يُرفَض — بلا كيانٍ قانونيّ', 'persons (سجلٌّ ثانٍ)',
     'الإدراجُ يُردُّ من القاعدةِ لا من الشاشةِ وحدَها',
     $pass3a ? 'مُنع: ' . mb_substr((string) $conn->error, 0, 120) : '**مرّ — لا حارس**',
     $pass3a ? 'لا صفَّ في كيانٍ أمٍّ بلا Company_ID' : '—', $pass3a);
if (!$pass3a) { $ok = false; }

/* ③-ب صفُّ قوًى عاملةٍ يعرّف الإنسانَ نفسَه بمعرّفٍ مستقلٍّ بلا مفتاحِه الأمّ */
$r3b = @$conn->query("INSERT INTO persons (full_name, company_id, active, person_class)
                      VALUES ('" . $esc($nameV1) . "', $company, 1, 'WORKFORCE')");
$pass3b = ($r3b === false);
$add(3, 'معرّفٌ بديلٌ يُرفَض — إنسانٌ بلا مفتاحِه الأمّ', 'persons (سجلٌّ ثانٍ)',
     'صفُّ قوًى عاملةٍ بلا employee_id يُردّ — ولا يُنشأ معرّفٌ ثانٍ للحقيقةِ نفسِها',
     $pass3b ? 'مُنع: ' . mb_substr((string) $conn->error, 0, 120) : '**مرّ — معرّفٌ بديلٌ أُنشئ**',
     $pass3b ? 'الإنسانُ الواحدُ مفتاحٌ واحدٌ في كلِّ النطاقات' : '—', $pass3b);
if (!$pass3b) { $ok = false; }

/* ③-ج والوصلُ المشروعُ يمرّ — الحارسُ يمنع البديلَ لا يمنع السجلَّ الثاني */
$r3c = @$conn->query("INSERT INTO persons (full_name, company_id, employee_id, active, person_class, w3_link_rule)
                      VALUES ('" . $esc($nameV1) . "', $company, $personId, 1, 'WORKFORCE', 'JOURNEY_EXPLICIT_LINK')");
$pass3c = ($r3c !== false);
$add(3, 'الوصلُ المشروعُ يمرّ', 'persons (سجلٌّ ثانٍ موصول)',
     'صفُّ هويةٍ يحمل المفتاحَ الأمَّ يُقبل — فالحارسُ يمنع البديلَ لا السجلَّ',
     $pass3c ? 'قُبل بـemployee_id=' . $personId : 'رُدّ: ' . mb_substr((string) $conn->error, 0, 120),
     $pass3c ? 'السجلُّ الثاني تابعٌ لا موازٍ' : '—', $pass3c);
if (!$pass3c) { $ok = false; }

/* ═══ المحطّةُ ④ — تعديلُ الأمِّ يظهر عند الثلاثةِ بلا نسخٍ محليّ ═══════════ */
$nameV2 = $MARK . '-v2';
$upd = $personId > 0 ? $conn->query("UPDATE employees SET name = '" . $esc($nameV2) . "' WHERE id = $personId") : false;
foreach ($consumers as $c) {
    $sub = function ($s) use ($personId) { return str_replace('{ID}', (string) $personId, $s); };
    $readName = (string) $one($sub($c['read_sql']));
    $eff = (string) $one($sub($c['effect_sql']));
    $sameEffect = ((float) $eff === (float) $effectBefore[$c['code']] && (float) $eff > 0);
    $pass = ($upd && $readName === $nameV2 && $sameEffect);
    $add(4, 'تعديلُ الأمِّ يظهر عند القارئ بلا نسخٍ محليّ', $c['ar'],
         'الاسمُ الجديدُ يظهر بلا أيِّ كتابةٍ في جدولِ المستهلك · والأثرُ التجاريُّ يبقى معلَّقًا بالمفتاح',
         ($readName === $nameV2 ? 'الاسمُ تغيّر عند القارئ' : "لم يتغيّر: «$readName»")
           . ' · صفرُ UPDATE على ' . $c['table'],
         $c['effect_ar'] . ' قبل ' . $effectBefore[$c['code']] . ' ⇐ بعد ' . $eff
           . ($sameEffect ? ' (باقٍ ومعلَّقٌ بالمفتاح)' : ' (**تغيّر أو ضاع**)'), $pass);
    if (!$pass) { $ok = false; }
}

$conn->query('ROLLBACK');

/* التحقّقُ من الإرجاع: لا يبقى صفٌّ من الرحلةِ في الحيّ */
$left = (int) $one("SELECT COUNT(*) FROM employees WHERE name LIKE '" . $esc($MARK) . "%'")
      + (int) $one("SELECT COUNT(*) FROM persons WHERE full_name LIKE '" . $esc($MARK) . "%'");
$passClean = ($left === 0);
$add(5, 'الرحلةُ لا تترك أثرًا في الحيّ', 'المعاملةُ المُرجَعة',
     'صفرُ صفٍّ باقٍ من صفوفِ الرحلة', "الباقي: $left", 'قياسٌ بلا تلويثِ بيانات', $passClean);
if (!$passClean) { $ok = false; }

/* ═══ حفظُ الدليل — بعدَ الإرجاعِ لا داخلَه ═══════════════════════════════ */
$conn->query("DELETE FROM repair01_w3_journey WHERE run_id = '" . $esc($RUN) . "'");
foreach ($ST as $s) {
    $conn->query("INSERT INTO repair01_w3_journey
        (run_id, station_no, station, key_code, consumer, expected, measured, business_effect, passed)
        VALUES ('" . $esc($RUN) . "', " . (int) $s[0] . ", '" . $esc($s[1]) . "', 'Person_ID',
                '" . $esc($s[2]) . "', '" . $esc($s[3]) . "', '" . $esc($s[4]) . "',
                '" . $esc($s[5]) . "', " . (int) $s[6] . ")");
}

$passN = 0;
foreach ($ST as $s) {
    printf("  %s محطّة %d · %-46s %s\n", $s[6] ? '✔' : '✘', $s[0], mb_substr($s[2], 0, 44), mb_substr($s[4], 0, 70));
    printf("      الأثر: %s\n", mb_substr($s[5], 0, 100));
    if ($s[6]) { $passN++; }
}
echo str_repeat('─', 78) . "\n";
printf("رحلةُ المفتاح: %d/%d محطّة · الجولة %s\n", $passN, count($ST), $RUN);
echo 'الحكم: ' . ($ok ? "عبرت ✔\n" : "لم تعبر ✘\n");
exit($ok ? 0 : 1);
