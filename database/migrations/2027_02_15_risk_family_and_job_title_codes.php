<?php
/**
 * 2027_02_15 — عائلةُ المخاطرِ في القاموس · وترميزُ مسمّياتِها · وحَجْرُ ما ليس مسمًّى
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **ثابتٌ حقيقيٌّ يسقط** (لا إحصاءٌ مجمَّد): حكمُ `sec_structure_test` أن كلَّ
 *   مسمًّى وظيفيٍّ **مرمَّزٌ بعائلتِه ومستواه**. والمقيس: 23 مسمًّى و**20 فقط
 *   مرمَّزة**. والثلاثةُ الباقيةُ أدوارُ المخاطرِ المضافةُ 2026-08-07/08:
 *   `JT_RISK_MGR` · `JT_RISK_SUP` · `JT_RISK_ANL`.
 *   ومسمًّى بلا عائلةٍ ومستوًى **لا يُشتَقُّ له قالبُ صلاحيات** ولا يدخل تقاريرَ
 *   الهيكل — فالنقصُ ليس تجميليًّا.
 *
 * ◆ **والسببُ الجذريّ: لا عائلةَ للمخاطرِ في `hr_dictionaries`.** ثلاثةَ عشرَ
 *   عائلةً (مالية · تمويل · أسطول · حوكمة · موارد بشرية · صيانة · مشغّلون ·
 *   تشغيل · مشتريات · مبيعات · بلاغات · نقل · مخازن) — **ولا مخاطر**.
 *
 * ◆ **والقرارُ يتبع سابقةً مسجَّلةً في النظامِ لا اجتهادًا**: `INJ-0059` أضاف
 *   `RISK` **بابًا تاسعًا** في القوائمِ وكتب رفضًا صريحًا لطيِّه تحت `GOV`:
 *   «بابُ الحوكمةِ مزدحمٌ ودفنُ المخاطرِ فيه يُخفي مجالًا أولَ الدرجة». فالمخاطرُ
 *   مجالٌ أولُ الدرجةِ **بقرارٍ سابق** — فتُضاف عائلةً لا تُدفن. والاتساقُ بين
 *   محورَي التنقُّلِ والهيكلِ شرطٌ: بابٌ مستقلٌّ وعائلةٌ مدفونةٌ تصنيفانِ
 *   متناقضانِ للشيءِ نفسِه.
 *
 * ◆ **والمستوياتُ مقيسةٌ من اسمِ المسمّى لا مُختارة** — والقاموسُ يحملها كلَّها:
 *     «مدير إدارة …» ⇒ `lvl_dept_mgr`  ·  «مشرف …» ⇒ `lvl_supervisor`
 *     «محلل …» ⇒ `lvl_officer` (أخصائيٌّ بدرجةِ مسؤولٍ لا منفِّذ)
 *
 * ◆ **وحَجْرُ ما ليس مسمًّى**: كشف جسُّ هذه الهجرةِ نفسِها أربعةَ صفوفٍ
 *   `JOB_-000NN` **اسمُها اسمُ شخصٍ** («مصعب الطاهر النعيم-0017») و
 *   `family_code` و`level_code` فيها **يساويان رمزَ الصفِّ نفسِه** — إشارةٌ إلى
 *   ذاتِها لا إلى القاموس. استيرادٌ معطوبٌ كتب أشخاصًا في جدولِ المسمّياتِ وحشا
 *   الرمزَ في ثلاثةِ أعمدة. وصفرُ مرجعٍ إليها (لا مفتاحَ أجنبيًّا · وصفرُ صفٍّ في
 *   `person_positions`).
 *   **فتُحجَر بالإعلانِ لا بالمحو**: تُطفأ وتُنزَع إشاراتُها الذاتيةُ ويُكتب
 *   سببُها في `description` — فتبقى الواقعةُ مرئيةً لمن يدقّق ولا تُفسد تقريرًا.
 *   (وحكمُ المالك: كلمةُ الحذفِ لا تُنفَّذ على أنها DELETE.)
 *   ⇒ والحكمُ الصحيحُ بعدها: «كلُّ مسمًّى **نشطٍ** مرمَّزٌ بعائلةٍ ومستوًى
 *     مسجَّلَين» — فالمحجورُ ليس مسمًّى يُطالَب بترميز.
 *
 * ◆ مُتحمِّلٌ للتكرار.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
require_once dirname(__DIR__, 2) . '/includes/env.php';

$db = @new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'),
                  ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($db->connect_errno) { fwrite(STDERR, 'اتصال: ' . $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');

echo "══ عائلةُ المخاطرِ وترميزُ مسمّياتِها ══\n";

/* ── ① العائلةُ في القاموس ─────────────────────────────────────────────── */
$has = (int) $db->query("SELECT COUNT(*) FROM hr_dictionaries
                          WHERE code = 'fam_risk' AND layer = 'family'")->fetch_row()[0];
if ($has) {
    echo "  ○ `fam_risk` مسجَّلةٌ سلفًا\n";
} else {
    if ($db->query("INSERT INTO hr_dictionaries (code, name_ar, layer, active)
                     VALUES ('fam_risk', 'المخاطر', 'family', 1)") === false) {
        fwrite(STDERR, '✘ ' . $db->error . "\n"); exit(1);
    }
    echo "  ✔ أُضيفت عائلةُ «المخاطر» (`fam_risk`)\n";
}

/* ── ② ترميزُ المسمّياتِ الثلاثة ───────────────────────────────────────── */
$MAP = array(
    'JT_RISK_MGR' => array('fam_risk', 'lvl_dept_mgr'),
    'JT_RISK_SUP' => array('fam_risk', 'lvl_supervisor'),
    'JT_RISK_ANL' => array('fam_risk', 'lvl_officer'),
);
foreach ($MAP as $tc => $fl) {
    $st = $db->prepare("UPDATE job_titles SET family_code = ?, level_code = ?
                         WHERE title_code = ? AND (family_code IS NULL OR level_code IS NULL)");
    $st->bind_param('sss', $fl[0], $fl[1], $tc);
    $st->execute();
    echo '  ' . ($st->affected_rows > 0 ? '✔ ' . $tc . ' ⇒ ' . $fl[0] . ' · ' . $fl[1]
                                        : '○ ' . $tc . ' مرمَّزٌ سلفًا أو غيرُ موجود') . "\n";
    $st->close();
}

/* ── ③ حَجْرُ ما ليس مسمًّى ────────────────────────────────────────────────── */
echo "\n── حَجْرُ ما ليس مسمًّى\n";
$mal = (int) $db->query("SELECT COUNT(*) FROM job_titles
                          WHERE title_code = family_code AND title_code = level_code")->fetch_row()[0];
if ($mal > 0) {
    $r = $db->query("SELECT title_code, name FROM job_titles
                      WHERE title_code = family_code AND title_code = level_code");
    while ($x = $r->fetch_assoc()) {
        echo '  ⚠ ' . str_pad((string) $x['title_code'], 16) . '«' . mb_substr((string) $x['name'], 0, 34) . "»\n";
    }
    $db->query("UPDATE job_titles
                   SET active = 0, family_code = NULL, level_code = NULL,
                       description = CONCAT(COALESCE(description, ''),
                         ' [محجورٌ 2027_02_15: استيرادٌ معطوبٌ — اسمُ شخصٍ في جدولِ المسمّيات ورمزٌ يشير إلى ذاته]')
                 WHERE title_code = family_code AND title_code = level_code");
    echo '  ✔ حُجر ' . $db->affected_rows . " صفًّا (يُطفأ ويُعلَن ولا يُمحى)\n";
} else {
    echo "  ○ لا صفَّ معطوبًا\n";
}

/* ── ④ الجسُّ: كلُّ مسمًّى **نشطٍ** مرمَّزٌ لا عددٌ منه ─────────────────────── */
echo "\n── جسُّ الثابت\n";
$tot = (int) $db->query('SELECT COUNT(*) FROM job_titles WHERE COALESCE(active,1) = 1')->fetch_row()[0];
$ok  = (int) $db->query("SELECT COUNT(*) FROM job_titles
                          WHERE COALESCE(active,1) = 1
                            AND title_code IS NOT NULL AND family_code IS NOT NULL
                            AND level_code IS NOT NULL")->fetch_row()[0];
echo "  مسمّياتٌ نشطة: {$tot} · مرمَّزةٌ كاملًا: {$ok}\n";
if ($ok < $tot) {
    $r = $db->query("SELECT title_code, name FROM job_titles
                      WHERE COALESCE(active,1) = 1
                        AND (title_code IS NULL OR family_code IS NULL OR level_code IS NULL)");
    while ($x = $r->fetch_assoc()) {
        echo '    ⚠ ' . str_pad((string) $x['title_code'], 18) . mb_substr((string) $x['name'], 0, 40) . "\n";
    }
}

$orphanF = (int) $db->query("SELECT COUNT(*) FROM job_titles j
                             WHERE COALESCE(j.active,1) = 1 AND j.family_code IS NOT NULL
                               AND NOT EXISTS (SELECT 1 FROM hr_dictionaries d
                                                WHERE d.code = j.family_code AND d.layer = 'family')")
                    ->fetch_row()[0];
echo '  ' . ($orphanF === 0 ? '✔' : '✘') . " مسمّياتٌ نشطةٌ تشير إلى عائلةٍ غيرِ مسجَّلة: {$orphanF}\n";
$orphanL = (int) $db->query("SELECT COUNT(*) FROM job_titles j
                             WHERE COALESCE(j.active,1) = 1 AND j.level_code IS NOT NULL
                               AND NOT EXISTS (SELECT 1 FROM hr_dictionaries d
                                                WHERE d.code = j.level_code AND d.layer = 'level')")
                    ->fetch_row()[0];
echo '  ' . ($orphanL === 0 ? '✔' : '✘') . " مسمّياتٌ نشطةٌ تشير إلى مستوًى غيرِ مسجَّل: {$orphanL}\n";

$r = $db->query("SELECT layer, COUNT(*) c FROM hr_dictionaries GROUP BY layer ORDER BY layer");
$parts = array();
while ($x = $r->fetch_assoc()) { $parts[] = $x['layer'] . '=' . $x['c']; }
echo '  القواميسُ بعده: ' . implode(' · ', $parts) . "\n";

$good = ($ok === $tot && $orphanF === 0 && $orphanL === 0);
echo "\n" . ($good
    ? "✅ كلُّ مسمًّى نشطٍ مرمَّزٌ بعائلةٍ ومستوًى مسجَّلَين — والمخاطرُ مجالٌ لا دَفينة.\n"
    : "⚠ راجِع أعلاه\n");
exit($good ? 0 : 1);
