<?php
/**
 * 2027_10_12_frd_nav001_cycle_source_clean.php
 *   FR-NAV-001 · CHG-NAV-DERIVE-01 — كنسُ الصيغِ الممنوعةِ من **مصدرِ الدورة**
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المطلب** (الدفتر · GAP-60 · P1): «كنسُ الصيغِ الممنوعةِ من مصدرِ الدورةِ
 *   قبلَ تفعيلِ الاشتقاق» · ومعيارُ قبولِه: «فحصٌ **على المصدرِ لا المخرَجِ**
 *   يُخرج صفرَ صيغة» · واختبارُه السالب: «صيغةٌ ممنوعةٌ باقيةٌ ← يُرسِّب التفعيل».
 *
 * ◆ **ولماذا المصدرُ لا المخرَج**: الحظرُ نُفِّذ سلفًا في التنقّلِ والسجلِّ
 *   الكنسيِّ بصفرِ إصابة — ولم يُنفَّذ في `gov_screen_cycle` الذي **يُشتقُّ منه**
 *   التنقّل. فالبوابةُ خضراءُ اليومَ وتنقلب حمراءَ لحظةَ تفعيلِ الاشتقاق.
 *
 * ◆ **والتحويلُ نحويٌّ لا اختراعَ فيه**: الفعلُ يصير **مصدرَه** (نتفاوض ⇐
 *   التفاوض) — وهو ما تطلبه سياسةُ التسمية: «المجموعةُ اسمٌ مؤسسيٌّ اسميّ».
 *   واللفظُ المتقاعدُ «الحاويات» يأخذ **بديلَه الكنسيَّ المسجَّل** في
 *   `nav_canonical` («إسناد المعدات للوحدات») — لا اسمًا من عندي.
 *
 * ◆ **ولا حذفَ ولا فقد**: تعديلُ نصٍّ في مكانِه، والقيمةُ السابقةُ محفوظةٌ في
 *   `gov_cycle_name_log` فالرجوعُ يعيدها حرفًا. والعدُّ قبلًا وبعدًا يُصالَح.
 *
 * التشغيل:  php database/migrations/2027_10_12_frd_nav001_cycle_source_clean.php
 * الرجوع :  php database/migrations/2027_10_12_frd_nav001_cycle_source_clean.php --revert
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
require_once __DIR__ . '/_ledger.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$conn->query("CREATE TABLE IF NOT EXISTS `gov_cycle_name_log` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `row_id` INT NOT NULL,
    `field` VARCHAR(32) NOT NULL,
    `old_value` VARCHAR(255) NOT NULL,
    `new_value` VARCHAR(255) NOT NULL,
    `requirement_id` VARCHAR(32) NOT NULL,
    `changed_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`), KEY `k_row` (`row_id`), KEY `k_req` (`requirement_id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$REQ = 'FR-NAV-001';

/* ── الرجوع: تُعاد كلُّ قيمةٍ من سجلِّها ─────────────────────────────────── */
if (in_array('--revert', $argv, true)) {
    $n = 0;
    $r = $conn->query("SELECT `row_id`,`field`,`old_value` FROM `gov_cycle_name_log`
                        WHERE `requirement_id` = '{$REQ}' ORDER BY `id` DESC");
    while ($r && $x = $r->fetch_assoc()) {
        $st = $conn->prepare("UPDATE `gov_screen_cycle` SET `{$x['field']}` = ? WHERE `id` = ?");
        $st->bind_param('si', $x['old_value'], $x['row_id']);
        if ($st->execute()) { $n += $st->affected_rows; }
        $st->close();
    }
    $conn->query("DELETE FROM `gov_cycle_name_log` WHERE `requirement_id` = '{$REQ}'");
    echo "↺ أُعيد {$n} صفًّا إلى نصِّه السابق\n";
    exit(0);
}

/* ── خريطةُ التحويلِ النحويّ ───────────────────────────────────────────── */
$GROUP = array(
    'نتفاوض ونسعّر'            => 'التفاوض والتسعير',
    'نتابع تنفيذَه'            => 'متابعة التنفيذ',
    'نفوتر ونحصّل'             => 'الفوترة والتحصيل',
    'نوقّع العقد'              => 'توقيع العقد',
    'نحاسبه ونصرف'             => 'المحاسبة والصرف',
    'نسجّل ما حدث اليوم'       => 'تسجيل اليومية',
    'نتحقق من قدرته'           => 'التحقق من القدرة',
    'نصرف من العهدة'           => 'الصرف من العهدة',
    'نعطيه حصتَه'              => 'إسناد الحصة',
    'نتابع التنفيذ'            => 'متابعة التنفيذ التعاقدي',
    'نجدّد أو نُقفل'           => 'التجديد والإقفال',
    'نوقّع عقدَه'              => 'توقيع عقد المورد',
    'نوزّع الحاويات'           => 'إسناد المعدات للوحدات',   /* البديلُ الكنسيُّ المسجَّل */
    'نطابق ونراجع'             => 'المطابقة والمراجعة',
    'نقيّمه أو نُنهي عقدَه'    => 'التقييم وإنهاء العقد',
    'نبدأ من العميل'           => 'فتح العميل',
    'نبدأ من المورد'           => 'تسجيل المورد',
    'نرفع لليومِ التالي'       => 'الرفع لليوم التالي',
    'نحسم حالةَ التوقف'        => 'حسم حالة التوقف',
);
/* ◆ واللفظُ المتقاعدُ في أسماءِ المراحلِ أيضًا — والمخرَجُ نظيفٌ فلا يُقاس وحدَه */
$STAGE = array(
    'توزيع الحصص والحاويات' => 'توزيع الحصص وإسناد المعدات',
);

/* ── ① العدُّ قبلًا — §تاسعًا: قبل ⇐ تنفيذ ⇐ بعد ⇐ مصالحة ─────────────── */
function cnt(mysqli $c, $sql) { $r = @$c->query($sql); return $r ? (int) $r->fetch_row()[0] : -1; }
$VERB = "(`group_name` REGEXP '^ن[^ ]+' OR `group_name` LIKE 'نحن%')";
$RET  = "(`group_name` LIKE '%الحاويات%' OR `stage_name` LIKE '%الحاويات%'
       OR `group_name` LIKE '%الخانات%'  OR `stage_name` LIKE '%الخانات%')";
$total0 = cnt($conn, "SELECT COUNT(*) FROM `gov_screen_cycle`");
$verb0  = cnt($conn, "SELECT COUNT(*) FROM `gov_screen_cycle` WHERE {$VERB}");
$ret0   = cnt($conn, "SELECT COUNT(*) FROM `gov_screen_cycle` WHERE {$RET}");
printf("① قبل: صفوفٌ=%d · بمجموعةٍ فعلية=%d · بلفظٍ متقاعد=%d\n", $total0, $verb0, $ret0);

/* ── ② التنفيذ ──────────────────────────────────────────────────────── */
$log = $conn->prepare("INSERT INTO `gov_cycle_name_log`
        (`row_id`,`field`,`old_value`,`new_value`,`requirement_id`,`changed_at`)
        VALUES (?,?,?,?,?,NOW())");
$changed = 0;
foreach (array(array('group_name', $GROUP), array('stage_name', $STAGE)) as $pair) {
    list($field, $map) = $pair;
    foreach ($map as $old => $new) {
        $sel = $conn->prepare("SELECT `id` FROM `gov_screen_cycle` WHERE `{$field}` = ?");
        $sel->bind_param('s', $old); $sel->execute();
        $res = $sel->get_result();
        $ids = array();
        while ($res && $x = $res->fetch_row()) { $ids[] = (int) $x[0]; }
        $sel->close();
        if (!$ids) { continue; }
        foreach ($ids as $rid) {
            /* خمسةُ وسائطَ ⇐ خمسةُ حروف — و`NOW()` في نصِّ الاستعلامِ لا وسيطًا */
            $log->bind_param('issss', $rid, $field, $old, $new, $REQ);
            $log->execute();
        }
        $st = $conn->prepare("UPDATE `gov_screen_cycle` SET `{$field}` = ? WHERE `{$field}` = ?");
        $st->bind_param('ss', $new, $old);
        $st->execute();
        $changed += $st->affected_rows;
        printf("   %-12s «%s» ⇐ «%s» · %d صفًّا\n", $field, $new, $old, $st->affected_rows);
        $st->close();
    }
}
$log->close();

/* ── ③ العدُّ بعدًا والمصالحة ──────────────────────────────────────────── */
$total1 = cnt($conn, "SELECT COUNT(*) FROM `gov_screen_cycle`");
$verb1  = cnt($conn, "SELECT COUNT(*) FROM `gov_screen_cycle` WHERE {$VERB}");
$ret1   = cnt($conn, "SELECT COUNT(*) FROM `gov_screen_cycle` WHERE {$RET}");
printf("\n② بعد: صفوفٌ=%d · بمجموعةٍ فعلية=%d · بلفظٍ متقاعد=%d · عُدِّل=%d\n",
       $total1, $verb1, $ret1, $changed);
printf("③ مصالحةُ المقام: قبل=%d ⇐ بعد=%d ⇒ %s (صفرُ حذفٍ وصفرُ إضافة)\n",
       $total0, $total1, $total0 === $total1 ? '✔ مطابق' : '✘ **فرق**');
if ($total0 !== $total1) { exit("⛔ اختلَّ المقام — راجعْ قبلَ الالتزام\n"); }

/* ◆ **الباقي بعدَ الكنسِ يُعلَن لا يُطمَس**: ما بقي أسماءٌ تبدأ بالنونِ وليست
 *   أفعالًا (ناقلُ الأحداث · نماذجُ العمل · نماذجُ التمويل) — والقياسُ
 *   التالي يميّزها بقائمةٍ بيضاءَ معلَنةٍ لا بالنمطِ وحدَه. */
$r = $conn->query("SELECT DISTINCT `group_name` FROM `gov_screen_cycle` WHERE {$VERB} ORDER BY 1");
$left = array();
while ($r && $x = $r->fetch_row()) { $left[] = $x[0]; }
if ($left) {
    echo "④ باقٍ بالنمطِ (أسماءٌ لا أفعال — تُعلَن ولا تُكنَس): " . implode(' · ', $left) . "\n";
}

ems_migration_recorded(__FILE__, $conn, 0);
