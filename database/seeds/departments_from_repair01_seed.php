<?php
/**
 * database/seeds/departments_from_repair01_seed.php — بذرةُ سجلِّ الإداراتِ المنصّيّ
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المصدرُ سجلٌّ حاكمٌ لا تأليفٌ يدويّ**: `repair01_departments` دفترُ الرموزِ
 *   المعياريّة، فتُنسَخ منه `canonical_code`→`code` و`name_ar`→`name` و
 *   `display_order` كما هي — ⛔ **ولا يُخترع كودٌ ولا يُترجَم اسم**.
 *
 * ◆ **سبعَ عشرةَ إدارةً لا واحدًا وعشرين**: `AMD-01` ملحق أ·٥ نصًّا — «سبعَ
 *   عشرةَ إدارةً `DEP-01`..`DEP-17` وأربعةٌ خارجَ التعداد: `EX-CEO` · `EX-DVP` ·
 *   `WS-MY` · `IAF`». والأربعةُ جذورُ ملاحةٍ ومساحةُ عملٍ لا إداراتٌ مسجَّلة،
 *   وقطاعُها `OUTSIDE` يقولها — فالمُرشِّحُ `sector <> 'OUTSIDE'` لا قائمةٌ صلبة.
 *
 * ⛔ **ولا تُبذَر قيمةٌ لا تُقاس**: يُقاس المصدرُ أوّلًا — فإن لم يُخرِج المُرشِّحُ
 *   سبعَ عشرةَ بالضبطِ رُدَّت البذرةُ قبل أيِّ كتابة (فمصدرٌ انزاح تعدادُه دفترٌ
 *   تغيّر حكمُه، لا صفوفٌ تُنسَخ بلا سؤال).
 *
 * ◆ **وتُقاس المفرداتُ بحارسِ الشاشةِ نفسِه**: `admin/departments.php` يشترط
 *   كودًا بين ٣ و٢٠ واسمًا بين ٣ و١٥٠ — فصفٌّ مبذورٌ يخالفها يظهر في الجدولِ
 *   ولا يُحفَظ عند أوّلِ تعديلٍ من الشاشة. تُفحَص قبل الكتابةِ لا بعدها.
 *
 * ◆ `notes` تبقى فارغةً — حقلُ مُشغِّلٍ لا حقلٌ مشتقّ.
 *
 * بياناتٌ فقط — لا DDL. وidempotent بـ`code` (المفتاحُ الفريد): يُعاد تشغيلُها
 * بلا أثرٍ مزدوج، ويُصحِّح اسمًا أو ترتيبًا انزاح في المصدر.
 *
 * التشغيل: php database/seeds/departments_from_repair01_seed.php
 * التجربةُ بلا كتابة: php database/seeds/departments_from_repair01_seed.php --dry-run
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';

$DRY = in_array('--dry-run', $argv, true);

$host = ems_env('DB_HOST');
$port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$db = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($db->connect_errno) { fwrite(STDERR, 'تعذّر الاتصال: ' . $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');

/* ① يُقاس المصدر — والتعدادُ شرطٌ سابقٌ لأيِّ كتابة ───────────────────────── */
$WANT = 17;
$src = array();
$rs = $db->query("SELECT canonical_code, name_ar, display_order, sector
                    FROM repair01_departments
                   WHERE sector <> 'OUTSIDE'
                   ORDER BY display_order, canonical_code");
if (!$rs) { fwrite(STDERR, 'تعذّر قراءةُ المصدر: ' . $db->error . "\n"); exit(1); }
while ($r = $rs->fetch_assoc()) { $src[] = $r; }

printf("◆ المصدرُ `repair01_departments` بمُرشِّحِ sector <> 'OUTSIDE': %d صفًّا (المطلوب %d)\n",
       count($src), $WANT);
if (count($src) !== $WANT) {
    fwrite(STDERR, "⛔ تعدادُ المصدرِ انزاح — رُدَّت البذرةُ قبل أيِّ كتابة.\n");
    exit(1);
}

/* ② تُفحَص المفرداتُ بحارسِ الشاشةِ نفسِه — قبل الكتابةِ لا بعدها ─────────── */
$bad = array();
foreach ($src as $r) {
    $cl = mb_strlen($r['canonical_code']);
    $nl = mb_strlen($r['name_ar']);
    if ($cl < 3 || $cl > 20)  { $bad[] = $r['canonical_code'] . ": كودٌ بطولِ $cl خارجَ [3,20]"; }
    if ($nl < 3 || $nl > 150) { $bad[] = $r['canonical_code'] . ": اسمٌ بطولِ $nl خارجَ [3,150]"; }
    if ($r['display_order'] === null) { $bad[] = $r['canonical_code'] . ': ترتيبُ عرضٍ NULL'; }
}
if ($bad) {
    fwrite(STDERR, "⛔ مفرداتٌ تخالف حارسَ الشاشة:\n  - " . implode("\n  - ", $bad) . "\n");
    exit(1);
}
echo "◆ المفرداتُ سبعَ عشرةَ كلُّها داخلَ حارسِ الشاشة (كود 3..20 · اسم 3..150 · ترتيبٌ غيرُ NULL)\n\n";

/* ③ الكتابةُ idempotent بـ`code` ─────────────────────────────────────────── */
$before = (int) $db->query('SELECT COUNT(*) FROM `departments`')->fetch_row()[0];

$ins = 0; $upd = 0; $same = 0;
$st = $db->prepare('INSERT INTO `departments` (`code`, `name`, `display_order`)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE `name` = VALUES(`name`),
                                            `display_order` = VALUES(`display_order`)');
if (!$st) { fwrite(STDERR, 'تعذّر التحضير: ' . $db->error . "\n"); exit(1); }

printf("%-4s %-9s %-34s %-6s %s\n", '#', 'الكود', 'الاسم', 'ترتيب', 'الأثر');
echo str_repeat('─', 74) . "\n";

$i = 0;
foreach ($src as $r) {
    $i++;
    $code = $r['canonical_code'];
    $name = $r['name_ar'];
    $ord  = (int) $r['display_order'];

    if ($DRY) {
        $cur = $db->query("SELECT `name`, `display_order` FROM `departments`
                            WHERE `code` = '" . $db->real_escape_string($code) . "'")->fetch_assoc();
        $eff = $cur === null ? 'سيُضاف'
             : (($cur['name'] === $name && (int) $cur['display_order'] === $ord) ? 'كما هو' : 'سيُحدَّث');
    } else {
        $st->bind_param('ssi', $code, $name, $ord);
        if (!$st->execute()) {
            fwrite(STDERR, "⛔ فشلت كتابةُ $code: " . $st->error . "\n");
            exit(1);
        }
        /* affected_rows: 1 = إضافة · 2 = تحديث · 0 = لا فرق */
        $ar = $st->affected_rows;
        if ($ar === 1)      { $ins++; $eff = 'أُضيف'; }
        elseif ($ar === 2)  { $upd++; $eff = 'حُدِّث'; }
        else                { $same++; $eff = 'كما هو'; }
    }
    printf("%-4d %-9s %-34s %-6d %s\n", $i, $code, $name, $ord, $eff);
}
$st->close();

$after = (int) $db->query('SELECT COUNT(*) FROM `departments`')->fetch_row()[0];
echo str_repeat('─', 74) . "\n";
if ($DRY) {
    printf("◆ تجربةٌ بلا كتابة — `departments` فيه %d صفًّا كما هو.\n", $after);
} else {
    printf("◆ `departments`: %d ← %d صفًّا · أُضيف %d · حُدِّث %d · بلا فرقٍ %d\n",
           $before, $after, $ins, $upd, $same);
}
$db->close();
