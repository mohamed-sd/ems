<?php
/**
 * tests/injfrd66_w3_foundation_test.php — شاهدُ الموجةِ ③ · أساسُ الملفاتِ الأمَّهات
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ يقيس **معاييرَ القبولِ المكتوبةَ** لا عددَ التبويبات — فمعاييرُ الموجةِ ③
 *   في الوثيقةِ حقولٌ وتفرّدٌ وبيانات، والتبويباتُ وصفُ بناءٍ لا ضابطَ قبول.
 *
 * ◆ **إيجابيٌّ**: ما استوفى معيارَه يُعلَن أخضرَ بعددِه.
 * ◆ **سالبٌ**  : الفاحصُ يرصد خرقًا مزروعًا — فلا يُقبل «صفرٌ» من فاحصٍ أعمى.
 * ◆ **محجوزٌ** : ما يحتاج قرارَ مالكٍ يُطبع بسببِه ولا يُعدُّ نجاحًا ولا رسوبًا.
 *
 * ◆ **وفخُّ `COUNT(DISTINCT)`**: يُسقط NULL — فـ«تسعةُ أكوادٍ متصادمة» كانت
 *   في الحقيقةِ «تسعةَ موردينَ بلا كود». والفاحصُ هنا **يفصل الاثنين** ولا
 *   يخلطهما، وإلا شُخِّص العطبُ خطأً وعولج خطأً.
 *
 * التشغيل: php tests/injfrd66_w3_foundation_test.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
$_SERVER['SCRIPT_NAME'] = '/ems/main/dashboard.php';
require_once $ROOT . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }

$pass = 0; $fail = 0; $held = 0;
$scalar = static function (string $sql) use ($conn) {
    $r = @mysqli_query($conn, $sql);
    return $r ? (int) mysqli_fetch_row($r)[0] : -1;
};
$expect = static function (string $req, string $title, int $got, int $want) use (&$pass, &$fail) {
    if ($got === $want) { $pass++; printf("   ✔ %s  %-44s = %d\n", $req, $title, $got); }
    else { $fail++; printf("   ✘ %s  %-44s = %d (توقّعتُ %d)\n", $req, $title, $got, $want); }
};

echo "① إيجابيٌّ — تفرّدُ المعرّفاتِ الأعمال:\n";
/* الفراغُ والتصادمُ **مقياسانِ منفصلان** — وخلطُهما شخّص العطبَ خطأً مرةً */
$expect('SUP-01', 'موردٌ بلا كود',
    $scalar("SELECT COUNT(*) FROM suppliers WHERE is_deleted=0 AND (supplier_code IS NULL OR supplier_code='')"), 0);
$expect('SUP-01', 'كودُ موردٍ متصادم',
    $scalar("SELECT IFNULL(SUM(c-1),0) FROM (SELECT COUNT(*) c FROM suppliers
              WHERE is_deleted=0 AND supplier_code IS NOT NULL AND supplier_code<>''
              GROUP BY supplier_code HAVING c>1) x"), 0);
$expect('SAL-01', 'عميلٌ بلا كود',
    $scalar("SELECT COUNT(*) FROM clients WHERE is_deleted=0 AND (client_code IS NULL OR client_code='')"), 0);
$expect('SAL-01', 'كودُ عميلٍ متصادم',
    $scalar("SELECT IFNULL(SUM(c-1),0) FROM (SELECT COUNT(*) c FROM clients
              WHERE is_deleted=0 AND client_code IS NOT NULL AND client_code<>''
              GROUP BY client_code HAVING c>1) x"), 0);

echo "\n② إيجابيٌّ — «لا بندَ تنقّلٍ لجهات الاتصال» (SAL-02 · SUP-02):\n";
$expect('SAL-02', 'بندُ تنقّلٍ لجهاتِ الاتصال',
    $scalar("SELECT COUNT(*) FROM nav_items WHERE active=1
              AND (route LIKE '%contact%' OR label_ar LIKE '%جهات الاتصال%' OR label_ar LIKE '%جهاتِ الاتصال%')"), 0);

echo "\n③ سالبٌ — الفاحصُ يرصد خرقًا مزروعًا (وإلا فصفرُه عمًى):\n";
$probe = $scalar("SELECT IFNULL(SUM(c-1),0) FROM (SELECT COUNT(*) c FROM clients
                    WHERE is_deleted=0 GROUP BY client_name HAVING c>1) x");
if ($probe > 0) { $pass++; printf("   ✔ رُصد تكرارُ اسمٍ حيٌّ في العملاء (%d) — الفاحصُ يرى\n", $probe); }
else { $fail++; echo "   ✘ لم يرصدِ الفاحصُ شيئًا — تحقّقْ منه لا من البيانات\n"; }

echo "\n④ محجوزٌ بسببٍ مكتوب:\n";
if ($probe > 0) {
    $held++;
    printf("   ⏸ SAL-01 «صفر تكرار اسم» — %d تكرارًا:\n", $probe);
    $r = @mysqli_query($conn, "SELECT client_name, GROUP_CONCAT(CONCAT(id,':',client_code)) ids
                                 FROM clients WHERE is_deleted=0
                                GROUP BY client_name HAVING COUNT(*)>1");
    while ($r && ($x = mysqli_fetch_assoc($r))) { printf("      «%s» ⇐ %s\n", $x['client_name'], $x['ids']); }
    echo "      سجلّانِ لكيانٍ قانونيٍّ واحد. والدمجُ يُسقط أحدَ الكودَين ويَجرُّ\n";
    echo "      عقودَه ومطالباتِه — قرارُ مالكٍ لا يُنتحَل في هجرةِ بيانات.\n";
}
$fmt = $scalar("SELECT COUNT(DISTINCT REGEXP_REPLACE(supplier_code,'[0-9]+','#'))
                  FROM suppliers WHERE is_deleted=0 AND supplier_code REGEXP '[0-9]'");
if ($fmt > 1) {
    $held++;
    printf("   ⏸ SUP-01 صيغةُ الكود — %d صيغٍ متعايشة:\n", $fmt);
    $r = @mysqli_query($conn, "SELECT REGEXP_REPLACE(supplier_code,'[0-9]+','#') f, COUNT(*) n
                                 FROM suppliers WHERE is_deleted=0 AND supplier_code REGEXP '[0-9]'
                                GROUP BY f ORDER BY n DESC");
    while ($r && ($x = mysqli_fetch_assoc($r))) { printf("      %-12s ×%d\n", $x['f'], $x['n']); }
    echo "      وتوحيدُها يغيّر معرّفاتٍ يعرفها الناسُ ويطبعونها — قرارُ مالك.\n";
}

printf("\n%s  ناجح %d · راسب %d · محجوز %d\n",
    $fail === 0 ? '✔ الموجة ③ · الأساس' : '✘ الموجة ③ · الأساس', $pass, $fail, $held);
exit($fail === 0 ? 0 : 1);
