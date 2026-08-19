<?php
/**
 * 2027_07_16_clients_optional_profile_fields.php — أعمدةُ ملفِّ العميلِ الاختيارية
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الواقعةُ المقيسة**: `Clients/clients.php` يحمل **13 رأسَ عمودٍ محقونًا**
 *   (`data-fn`) من وثيقةِ الأعمدة (SCN-325: «سجل العملاء … 18 عمودًا، وأولُها
 *   كود العميل · الاسم القانوني الكامل · الشكل النظامي · بلد التسجيل · رقم
 *   السجل التجاري · الرقم الضريبي»)، و`clients` **لا تحمل عشرةً منها إطلاقًا**.
 *   فكانت كلُّ خليةٍ فيها «—» وكلُّ رأسٍ موسومًا «بلا مصدر» في منتقي الأعمدة.
 *   (مسجَّلٌ سلفًا في `docs/fix_2026-08/SALES_DEMO_DATA_FILL_2026-08-13.md` §٦-أ:
 *    «فربطُها يلزمه **هجرةُ مخطَّطٍ**» — وهذه هي.)
 *
 * ◆ **قرارُ المالك (2026-08-19)**: هذه العشرةُ ليست حقولًا إلزاميةً بل **بياناتٌ
 *   إضافيةٌ تُستكمَل لاحقًا**. فالحدُّ الأدنى لإضافةِ عميلٍ يبقى كما هو، وهذه
 *   تُملأ متى شاء المستخدم. ⇒ فكلُّها **NULL DEFAULT NULL بلا استثناء**، ولا
 *   قيدَ NOT NULL ولا افتراضَ نصيًّا — لأن «فارغٌ» هنا حالةٌ صحيحةٌ لا نقص.
 *
 * ◆ **ثلاثةٌ من الثلاثةَ عشرَ لا تُنشأ**: `البريد` عمودُه `email` قائمٌ، و`سجّله`
 *   مصدرُه `created_by`، و`تاريخ التسجيل` مصدرُه `created_at`. فهذه **مخالفةُ
 *   ربطٍ لا مخالفةُ مخطَّط** — عولجت في الشاشةِ لا هنا. وإنشاءُ عمودٍ لها كان
 *   سيزدوج المصدرَ ويفتح بابَ تضاربٍ.
 *
 * ◆ idempotent بالنمطِ الحارس: `information_schema` قبلَ كلِّ `ADD COLUMN`
 *   (MySQL/MariaDB لا يدعم `ADD COLUMN IF NOT EXISTS` بصيغةٍ محمولة).
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
$DB = ems_env('DB_NAME');

/* ── العشرةُ بترتيبِ وثيقةِ الأعمدة · [عمود, تعريف, تعليق, بعدَ أيِّ عمود] ──
   ◆ التعليقُ ليس زينةً: `SHOW FULL COLUMNS` هو ما يقرؤه مولِّدُ الوثائقِ
     وأدواتُ الجرد، والتسميةُ العربيةُ فيه **هي مفتاحُ المطابقةِ مع رأسِ العمود
     المحقون** — فلو اختلف حرفٌ انكسر الربطُ صامتًا. */
$COLS = array(
    array('legal_name',           "VARCHAR(255) NULL DEFAULT NULL", 'الاسم القانوني الكامل', 'client_name'),
    array('legal_form',           "VARCHAR(100) NULL DEFAULT NULL", 'الشكل النظامي',        'legal_name'),
    array('registration_country', "VARCHAR(100) NULL DEFAULT NULL", 'بلد التسجيل',          'legal_form'),
    array('commercial_reg_no',    "VARCHAR(100) NULL DEFAULT NULL", 'رقم السجل التجاري',    'registration_country'),
    array('tax_id',               "VARCHAR(100) NULL DEFAULT NULL", 'الرقم الضريبي',        'commercial_reg_no'),
    array('registered_address',   "VARCHAR(500) NULL DEFAULT NULL", 'العنوان المسجَّل',      'tax_id'),
    array('contact_person',       "VARCHAR(255) NULL DEFAULT NULL", 'جهة الاتصال',          'registered_address'),
    array('contact_title',        "VARCHAR(150) NULL DEFAULT NULL", 'المنصب',               'contact_person'),
    array('client_classification',"VARCHAR(100) NULL DEFAULT NULL", 'تصنيف العميل',         'contact_title'),
    array('importance_tier',      "VARCHAR(50)  NULL DEFAULT NULL", 'شريحة الأهمية',        'client_classification'),
);

/* ── الأعمدةُ القائمةُ الآن ─────────────────────────────────────────────── */
function xf_has_col($conn, $DB, $table, $col) {
    $st = $conn->prepare("SELECT COUNT(*) c FROM information_schema.COLUMNS
                           WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    if (!$st) { return false; }
    $st->bind_param('sss', $DB, $table, $col);
    $st->execute();
    $c = (int) $st->get_result()->fetch_assoc()['c'];
    $st->close();
    return $c > 0;
}

if (!xf_has_col($conn, $DB, 'clients', 'client_name')) {
    exit("⛔ جدولُ `clients` غيرُ متوقَّعٍ — لا عمودَ `client_name`. أُوقفت الهجرة.\n");
}

$added = 0; $already = 0; $failed = array();
foreach ($COLS as $c) {
    list($name, $ddl, $comment, $after) = $c;
    if (xf_has_col($conn, $DB, 'clients', $name)) { $already++; continue; }
    /* موضعُ العمودِ بعدَ سابقِه — وإن لم يُنشأ سابقُه بعدُ فبعدَ `client_name`
       (لا يفشل الترتيبُ الهجرةَ: الموضعُ تجميلٌ والوجودُ هو المطلوب). */
    $afterOk = xf_has_col($conn, $DB, 'clients', $after) ? $after : 'client_name';
    $sql = "ALTER TABLE `clients` ADD COLUMN `{$name}` {$ddl} "
         . "COMMENT '" . $conn->real_escape_string($comment) . "' AFTER `{$afterOk}`";
    if ($conn->query($sql)) { $added++; }
    else { $failed[] = $name . ' — ' . $conn->error; }
}

/* ── فهرسانِ تشغيليّان: البحثُ بالسجلِّ التجاريِّ وبالرقمِ الضريبيِّ فعلٌ متكرّر ──
   ◆ **ولا فهرسَ فريدًا**: الرقمُ الضريبيُّ قد يتكرّر بين شركاتٍ مختلفةٍ في
     قاعدةٍ متعدّدةِ المستأجرين، والفريدُ هنا كان سيرفض إدخالًا مشروعًا. */
function xf_has_idx($conn, $DB, $table, $idx) {
    $st = $conn->prepare("SELECT COUNT(*) c FROM information_schema.STATISTICS
                           WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?");
    if (!$st) { return true; }
    $st->bind_param('sss', $DB, $table, $idx);
    $st->execute();
    $c = (int) $st->get_result()->fetch_assoc()['c'];
    $st->close();
    return $c > 0;
}
$idx = 0;
foreach (array('idx_clients_commercial_reg' => 'commercial_reg_no',
               'idx_clients_tax_id'         => 'tax_id') as $iname => $icol) {
    if (xf_has_idx($conn, $DB, 'clients', $iname)) { continue; }
    if (!xf_has_col($conn, $DB, 'clients', $icol)) { continue; }
    if ($conn->query("ALTER TABLE `clients` ADD INDEX `{$iname}` (`{$icol}`)")) { $idx++; }
}

/* ── الشاهدُ المُشغَّل: يُقرأ من القاعدةِ بعدَ العملِ لا من نيّةِ الملف ───────── */
$now = array();
$r = $conn->query("SELECT COLUMN_NAME, IS_NULLABLE, COLUMN_TYPE, COLUMN_COMMENT
                     FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = '" . $conn->real_escape_string($DB) . "'
                      AND TABLE_NAME = 'clients'");
while ($r && ($x = $r->fetch_assoc())) { $now[$x['COLUMN_NAME']] = $x; }

echo "════ أعمدةُ ملفِّ العميلِ الاختيارية ════\n";
echo "  المطلوبُ: " . count($COLS) . " · أُنشئ: {$added} · كان قائمًا: {$already} · فهارس: +{$idx}\n";
foreach ($failed as $f) { echo "  ⚠ {$f}\n"; }

$ok = 0; $bad = array();
foreach ($COLS as $c) {
    $n = $c[0];
    if (!isset($now[$n]))                     { $bad[] = "{$n}: غيرُ موجود"; continue; }
    if ($now[$n]['IS_NULLABLE'] !== 'YES')    { $bad[] = "{$n}: ليس NULL — والاختياريُّ لا يكون إلزاميًّا"; continue; }
    if (trim($now[$n]['COLUMN_COMMENT']) === ''){ $bad[] = "{$n}: بلا تسميةٍ عربيةٍ في التعليق"; continue; }
    $ok++;
}
echo "  المُثبَتُ حيًّا: {$ok}/" . count($COLS) . " عمودًا (NULL + تسميةٌ عربية)\n";
foreach ($bad as $b) { echo "  ✗ {$b}\n"; }

/* ثلاثةٌ لا تُنشأ — تُعلَن كي لا يُقرأ نقصُها نقصًا */
echo "  ولم تُنشأ 3 أعمدةٍ عمدًا (مصدرُها قائم): `email` ⇐ البريد · "
   . "`created_by` ⇐ سجّله · `created_at` ⇐ تاريخ التسجيل\n";

if ($bad || $failed) { echo "✗ لم تكتمل\n"; exit(1); }
echo "✔ العشرةُ قائمةٌ كلُّها اختياريةً — والحدُّ الأدنى لإضافةِ عميلٍ لم يتغيّر\n";
