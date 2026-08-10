<?php
/**
 * 2027_01_25_ac_u5_saved_views.php
 * ═══════════════════════════════════════════════════════════════════════════
 * AC-U5 · SH-06 — «كلُّ شاشةٍ فوقَ عشرينَ عمودًا لها منظرٌ افتراضيٌّ ومنتقٍ».
 *
 * ◆ العلّة: 107 شاشةً تتجاوز عشرينَ عمودًا (أعرضُها 38)، وتُفتح كلُّها بكلِّ
 *   أعمدتها. والجدولُ بثمانيةٍ وثلاثين عمودًا لا يُقرأ — يُمسح أفقيًّا، وما
 *   يهمُّ المستخدمَ عمودان أو ثلاثة يبحث عنهما في كلِّ مرة.
 *
 * ◆ والمنظرُ ليس تفضيلًا شخصيًّا فقط: الأدوارُ تختلف حاجتُها للأعمدةِ اختلافًا
 *   بنيويًّا (المحاسبُ يريد المبالغَ والمشرفُ يريد الحالةَ والتواريخ). ولذلك
 *   المنظرُ **مملوكٌ لمستخدمٍ أو لدور**، والافتراضيُّ للدورِ يخدم من لم يخصّص.
 *
 * ◆ ولا حذف: تغييرُ منظرٍ يُحدِّثه، وإزالتُه تعطيلٌ (`active=0`) لا محو —
 *   فمنظرٌ يعتمده تقريرٌ شهريٌّ لا يختفي بنقرةٍ عابرة.
 */
if (PHP_SAPI !== 'cli') { exit(1); }
error_reporting(E_ALL & ~E_DEPRECATED);
mysqli_report(MYSQLI_REPORT_OFF);
require_once dirname(__DIR__, 2) . '/includes/env.php';

$db = new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'), ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($db->connect_errno) { fwrite(STDERR, 'اتصال المرحِّل فشل: ' . $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');
$fail = function ($m) { fwrite(STDERR, "✘ {$m}\n"); exit(1); };

/* ── ① الجدول ─────────────────────────────────────────────────────────── */
$ok = $db->query(
    "CREATE TABLE IF NOT EXISTS ems_saved_views (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        company_id    INT NOT NULL,
        screen        VARCHAR(160) NOT NULL COMMENT 'المسار النسبي للشاشة',
        view_name     VARCHAR(80)  NOT NULL COMMENT 'اسم المنظر كما يراه المستخدم',
        owner_kind    ENUM('role','user') NOT NULL DEFAULT 'role',
        owner_id      INT NOT NULL COMMENT 'رقم الدور أو المستخدم بحسب owner_kind',
        columns_json  LONGTEXT NULL COMMENT 'فهارس الأعمدة الظاهرة — NULL = الكل',
        is_default    TINYINT(1) NOT NULL DEFAULT 0,
        active        TINYINT(1) NOT NULL DEFAULT 1,
        created_by    INT NULL,
        created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at    TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_view (company_id, screen, owner_kind, owner_id, view_name),
        KEY ix_screen (company_id, screen, active)
     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);
if (!$ok) { $fail('إنشاءُ الجدولِ فشل: ' . $db->error); }
echo "① جدولُ ems_saved_views جاهز\n";

/* ── ② المنظرُ الافتراضيُّ لكلِّ شاشةٍ عريضةٍ ولكلِّ دورٍ يراها ──────────────
   «الكلُّ» منظرٌ صريحٌ لا غيابُ منظر: بوجودِه يعرف المستخدمُ أن ثمة مناظرَ،
   وبه يعود إذا ضيّق. و`columns_json = NULL` تعني كلَّ الأعمدةِ فلا تتعفّن
   حين يُضاف عمودٌ جديدٌ للشاشة. */
$ROOT = dirname(__DIR__, 2);
$wide = array();
// ◆ الاستعلامُ الفاشلُ يُرجع false فتُقرأ النتيجةُ «صفرَ شاشة» — نجاحٌ كاذب.
//   العمودُ `is_deleted` غيرُ موجودٍ في `admin_companies`، وأعطى الترحيلُ
//   «صفرَ شاشةٍ عريضة» بينما المسحُ المستقلُّ وجد 107. يُفحص المُرجَعُ صراحةً.
$rs = $db->query("SELECT DISTINCT n.role_id, n.route, ac.id AS co
                    FROM nav_items n
                    JOIN admin_companies ac ON 1=1   /* لا عمودَ is_deleted في هذا الجدول */
                   WHERE n.active=1 AND n.route LIKE '%.php'");
if (!$rs) { $fail('استعلامُ الشاشاتِ فشل: ' . $db->error); }
while (($r = $rs->fetch_assoc())) {
    $file = $ROOT . '/' . strtok($r['route'], '?#');
    if (!is_file($file)) { continue; }
    if (!isset($wide[$r['route']])) {
        $src = (string) @file_get_contents($file);
        $cols = 0;
        if (preg_match('/<thead[\s\S]{0,4000}?<\/thead>/i', $src, $m)) {
            $cols = preg_match_all('/<th\b/i', $m[0]);
        }
        $wide[$r['route']] = $cols;
    }
    if ($wide[$r['route']] < 20) { continue; }
    $rows[] = array((int) $r['co'], $r['route'], (int) $r['role_id']);
}

$db->query('START TRANSACTION');
$st = $db->prepare(
    "INSERT INTO ems_saved_views (company_id, screen, view_name, owner_kind, owner_id,
                                  columns_json, is_default, active, created_by)
     VALUES (?, ?, 'كل الأعمدة', 'role', ?, NULL, 1, 1, 0)
     ON DUPLICATE KEY UPDATE is_default = 1, active = 1"
);
if (!$st) { $db->query('ROLLBACK'); $fail('تحضيرُ البذرِ فشل: ' . $db->error); }
$seeded = 0;
foreach (($rows ?? array()) as $r) {
    $st->bind_param('isi', $r[0], $r[1], $r[2]);
    if (!$st->execute()) { $st->close(); $db->query('ROLLBACK'); $fail('البذرُ فشل: ' . $st->error); }
    $seeded++;
}
$st->close();
$db->query('COMMIT');
$distinct = count(array_filter($wide, function ($c) { return $c >= 20; }));
echo "② شاشاتٌ عريضة: {$distinct} · مناظرُ افتراضيةٌ مبذورة: {$seeded}\n";

/* ── ③ إثباتُ التفرُّدِ وظيفيًّا — قيدٌ بلا محاولةِ خرقٍ زخرفة ────────────── */
$probe = $db->query("SELECT company_id, screen, owner_id FROM ems_saved_views LIMIT 1")->fetch_assoc();
if ($probe) {
    $db->query('START TRANSACTION');
    $dup = $db->query(sprintf(
        "INSERT INTO ems_saved_views (company_id, screen, view_name, owner_kind, owner_id, is_default, active)
         VALUES (%d, '%s', 'كل الأعمدة', 'role', %d, 0, 1)",
        (int) $probe['company_id'], $db->real_escape_string($probe['screen']), (int) $probe['owner_id']));
    $errno = $db->errno;
    $db->query('ROLLBACK');
    if ($dup) { $fail('قيدُ التفرُّدِ لا يمنع تكرارَ منظرٍ لنفسِ المالكِ والشاشة'); }
    if ($errno !== 1062) { $fail("رُفض لسببٍ غيرِ التفرُّد (errno={$errno})"); }
    echo "③ قيدُ التفرُّدِ أثبت نفسَه (errno=1062)\n";
} else {
    echo "③ لا صفَّ للجسِّ — لا شاشةَ عريضةً في التنقُّلِ الحاليّ\n";
}

echo "\n✔ 2027_01_25 تمّ. الشاهد: php tools/fix_ui_gate.php (AC-U5)\n";
