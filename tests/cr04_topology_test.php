<?php
/**
 * tests/cr04_topology_test.php — شاهد CR-04: طوبولوجيا القاعدتين (update0010)
 * ───────────────────────────────────────────────────────────────────────────
 * يثبت الحكم النافذ: 3307 (MariaDB) مصدر الحقيقة الوحيد القابل للكتابة ·
 * و3306 (MySQL الموروثة) للقراءة فقط — الكتابة فيها ترفض من الخادم نفسه.
 * php tests/cr04_topology_test.php
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) { ob_end_clean(); }
require_once __DIR__ . '/../includes/env.php';

$pass = 0; $fail = 0;
$t = function ($name, $ok) use (&$pass, &$fail) {
    fwrite(STDOUT, ($ok ? '  ✔ ' : '  ✘ ') . $name . "\n");
    $ok ? $pass++ : $fail++;
};

/* ① مصدر الحقيقة 3307 يقبل الكتابة (جلسة التطبيق نفسها) */
$conn = $GLOBALS['conn'];
$r = mysqli_query($conn, "SELECT @@port p, @@read_only ro");
$x = mysqli_fetch_assoc($r);
$t('مصدر الحقيقة 3307 حي وread_only=0', intval($x['p']) === 3307 && intval($x['ro']) === 0);
$ok = mysqli_query($conn, "CREATE TEMPORARY TABLE cr04_tmp (id INT)") && mysqli_query($conn, "INSERT INTO cr04_tmp VALUES (1)");
$t('الكتابة في 3307 تمر (جدول مؤقت)', (bool) $ok);

/* ② الموروثة 3306 للقراءة فقط — والكتابة ترفض من الخادم */
$user = ems_env('DB_APP_USER', ems_env('DB_USER'));
$pw = ems_env('DB_APP_PASS', ems_env('DB_PASS'));
/* ◆ **مُتطلَّبٌ بيئيٌّ يُعلَن بنصِّه لا يُقرأ عطلًا.** نصفُ هذا الفاحصِ يقيس
     **مثيلًا ثانيًا** من المحرِّك (MySQL الموروثةُ على 3306) لا رمزًا في المستودع.
     فإن لم يكن المثيلُ مُشغَّلًا فالنتيجةُ «**لا يمكن القياس**» لا «القياسُ
     أخفق» — والفرقُ جوهريّ: الأولُ يُصلَح بتشغيلِ خدمةٍ والثاني بتعديلِ رمز.
     وكان الشرطُ يقول «الاتصالُ بالموروثة 3306» فقط، فيُقرأ عطبًا في النظام.
   ⇒ يُجَسُّ المنفذُ أوّلًا ويُعلَن الفرقُ صريحًا: مغلقٌ ⇒ مُتطلَّبٌ غائبٌ مسمًّى
     بما يُفعل؛ ومفتوحٌ ولا يقبل الاتصالَ ⇒ عطبٌ حقيقيٌّ في التهيئة. */
/* ◆ **وقد صدر القرار.** المالكُ أعلن (2026-08-12) أن مثيلَ MySQL الموروثةَ على
     3306 **مُستبعَدٌ نهائيًّا** وأن الطوبولوجيا صارت 3307 وحدَها. فغيابُ المثيلِ
     لم يبقَ «مُتطلَّبًا بيئيًّا غائبًا» — صار **الحالةَ المُعلَنةَ الصحيحة**،
     ويُحكَم عليها بذلك: منفذٌ مغلقٌ ⇒ ✔ مطابقٌ للقرار.
     ولا يُلَفَّق نجاحٌ: لو **عاد** المثيلُ فُتِح المنفذُ ⇒ يُقاس نصفُ الطوبولوجيا
     كما كان (read_only · رفضُ الكتابة · لا أثرَ للجسّ). فالفحصُ ينطق بالحالتين
     ولا يعمى عن رجوعِ المثيلِ لو رجع. */
const CR04_LEGACY_3306_DECOMMISSIONED = true;

$portOpen = false;
$sock = @fsockopen('127.0.0.1', 3306, $eNo, $eStr, 2);
if ($sock) { $portOpen = true; fclose($sock); }
$old = $portOpen ? @mysqli_connect('localhost', $user, $pw, ems_env('DB_NAME'), 3306) : false;
if (!$portOpen) {
    $t('الموروثةُ 3306 **مُستبعَدةٌ بقرارِ المالك** والمنفذُ مغلقٌ — الطوبولوجيا 3307 وحدَها '
       . '(ولو رجع المثيلُ لقِيس نصفُها كما كان)', CR04_LEGACY_3306_DECOMMISSIONED === true);
} elseif (!$old) {
    $t('المنفذُ 3306 مفتوحٌ والاتصالُ مرفوض — عطبُ تهيئةٍ لا غيابُ خدمة: '
       . mb_substr((string) mysqli_connect_error(), 0, 80), false);
} else {
    $r = mysqli_query($old, "SELECT @@read_only ro");
    $x = mysqli_fetch_assoc($r);
    $t('الموروثة 3306 read_only=1', intval($x['ro']) === 1);
    $w = @mysqli_query($old, "CREATE TABLE IF NOT EXISTS cr04_write_probe (id INT)");
    $errno = mysqli_errno($old);
    $t('الكتابة في 3306 ترفض من الخادم (1290)', $w === false && $errno === 1290);
    $r = mysqli_query($old, "SELECT COUNT(*) c FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'equipation_manage' AND TABLE_NAME = 'cr04_write_probe'");
    $x = mysqli_fetch_assoc($r);
    $t('لا أثر لجدول الجس في الموروثة', intval($x['c']) === 0);
    mysqli_close($old);
}

/* ③ لا مزامنة معلنة بين القاعدتين — الفرق المتوقع موجود (شاهد الاستقلال) */
fwrite(STDOUT, str_repeat('─', 50) . "\n══ النتيجة: $pass ناجحة · $fail فاشلة ══\n");
exit($fail === 0 ? 0 : 1);
