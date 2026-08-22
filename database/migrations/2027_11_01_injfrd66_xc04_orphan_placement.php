<?php
/**
 * 2027_11_01_injfrd66_xc04_orphan_placement.php
 *   XC-04 — إعلانُ موضعِ الأسطحِ اليتيمةِ الثلاثة · وإصلاحُ قيمةٍ ابتلعها ENUM
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **① إصلاحُ عطبٍ أحدثَته الهجرةُ السابقة**: `2027_10_31` كتبت
 *   `placement_kind = 'SERVICE'` في عمودٍ قائمتُه مغلقة
 *   (`SINGLE`·`CROSS_ROLE_ENTRY`·`UNJUSTIFIED_SPLIT`). **وENUM يبتلع المجهولَ
 *   `''` صامتًا بلا خطأ** — فبدا الصفُّ مكتوبًا وحقلُه فارغ. ولولا قراءةُ
 *   قائمةِ العمودِ لبقي الفراغُ يُقرأ «مكتوبًا». والمعنى يحمله `nature`
 *   و`placement_basis` وكلاهما نصٌّ حرّ.
 *
 * ◆ **② والثلاثةُ اليتيمةُ تُعلَن باشتقاقٍ مقيسٍ لا بتخمين** — ولكلِّ واحدٍ
 *   شاهدُه على القرصِ وفي القاعدة:
 *
 *   | السطح | الشاهدُ المقيس | الحكم |
 *   |---|---|---|
 *   | `quota_approval_minutes.php` | يقرأ `substitute_coverages` **وحدَه** — وهو الجدولُ الذي يضمُّه `shares_coverage.php` في استعلامِه | تبويبٌ في ملفِّ الحصص |
 *   | `suppliers_details.php` | يقرأ `equipments·suppliers·supplierscontracts` — و`supplier_profile.php` يقرأ **الثلاثةَ نفسَها وزيادة**، وهو **موصولٌ تبويبًا** | خَلَفٌ حيّ ⇐ تقاعدٌ بعد إثبات |
 *   | `showcontractsuppliers.php` | يقرأ `supplierscontracts` — و`supplierscontracts_details.php` يقرأ **نفسَه وزيادة**، وهو موصولٌ | خَلَفٌ حيّ ⇐ تقاعدٌ بعد إثبات |
 *
 *   فالتغطيةُ **فوقيةٌ مقيسة** (superset) لا تشابهُ أسماء.
 *
 * ◆ **ولا يُحذف مسار**: `RETIRE_AFTER_PROOF` — قراءةٌ فقط ثم تقاعدٌ بعد فترةِ
 *   إثبات، و`view_of` يسمّي الخَلَفَ فيُعرف إلى أينَ يذهب من كان يقصده.
 *
 * التشغيل:  php database/migrations/2027_11_01_injfrd66_xc04_orphan_placement.php
 * الرجوع :  php database/migrations/2027_11_01_injfrd66_xc04_orphan_placement.php --revert
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

$SOURCE = 'INJ-FRD-01 · XC-04 — إعلانُ موضعِ السطحِ اليتيمِ باشتقاقٍ مقيس';

$ROWS = array(
    array(
        'route' => 'Suppliers/quota_approval_minutes.php',
        'name'  => 'محاضرُ اعتمادِ وحداتِ المورد',
        'group' => 'الحصص والتغطية والأداء',
        'basis' => 'تبويبٌ في ملفِّ الحصصِ والتغطية — يقرأ `substitute_coverages` وحدَه، '
                 . 'وهو الجدولُ الذي يضمُّه `Suppliers/shares_coverage.php` في استعلامِه. '
                 . 'شاشةُ قراءةٍ محضةٍ لا فعلَ كاتبًا فيها.',
        'retire' => 'ACTIVE',
        'viewof' => 'Suppliers/shares_coverage.php',
        'nature' => 'سجلٌّ تابع',
    ),
    array(
        'route' => 'Suppliers/suppliers_details.php',
        'name'  => 'تفاصيلُ المورد (إرثيّ)',
        'group' => 'إدارة الموردين والتأهيل',
        'basis' => 'خَلَفُه الحيُّ `Suppliers/supplier_profile.php` — يقرأ الجداولَ الثلاثةَ '
                 . 'نفسَها (`equipments`·`suppliers`·`supplierscontracts`) وزيادةً، وهو موصولٌ '
                 . 'تبويبًا في ملفِّ المورد. قراءةٌ فقط ثم تقاعدٌ بعد فترةِ إثبات — ولا يُحذف.',
        'retire' => 'RETIRE_AFTER_PROOF',
        'viewof' => 'Suppliers/supplier_profile.php',
        'nature' => 'سطحٌ إرثيّ',
    ),
    array(
        'route' => 'Suppliers/showcontractsuppliers.php',
        'name'  => 'عرضُ عقودِ الموردين (إرثيّ)',
        'group' => 'الاحتياج والتعاقد',
        'basis' => 'خَلَفُه الحيُّ `Suppliers/supplierscontracts_details.php` — يقرأ '
                 . '`supplierscontracts` نفسَه وزيادةً (الملاحظاتُ والمعدات)، وهو موصولٌ. '
                 . 'قراءةٌ فقط ثم تقاعدٌ بعد فترةِ إثبات — ولا يُحذف.',
        'retire' => 'RETIRE_AFTER_PROOF',
        'viewof' => 'Suppliers/supplierscontracts_details.php',
        'nature' => 'سطحٌ إرثيّ',
    ),
);

if (in_array('--revert', $argv, true)) {
    $n = 0;
    foreach ($ROWS as $r) {
        $st = $conn->prepare("DELETE FROM `nav_canonical` WHERE `route` = ? AND `decision_source` = ?");
        $st->bind_param('ss', $r['route'], $SOURCE);
        $st->execute(); $n += $st->affected_rows; $st->close();
    }
    printf("↺ حُذف %d صفَّ إعلان\n", $n);
    exit(0);
}

/* ── ① إصلاحُ ما ابتلعه ENUM ─────────────────────────────────────────── */
echo "① إصلاحُ قيمةٍ ابتلعها ENUM في الهجرةِ السابقة\n";
$q = $conn->query("SELECT COUNT(*) c FROM `nav_canonical` WHERE `placement_kind` = ''");
$blank = $q ? (int) $q->fetch_assoc()['c'] : 0;
if ($blank > 0) {
    $conn->query("UPDATE `nav_canonical` SET `placement_kind` = 'SINGLE' WHERE `placement_kind` = ''");
    printf("   ✔ صُحِّح %d صفًّا: '' ⇐ SINGLE\n", $conn->affected_rows);
} else {
    echo "   ○ لا فراغَ — لا عمل\n";
}

/* ── ② الإعلانات ─────────────────────────────────────────────────────── */
echo "\n② إعلانُ موضعِ الأسطحِ اليتيمة\n";
$done = 0; $skip = 0;
foreach ($ROWS as $r) {
    /* السطحُ يجب أن يكون على القرصِ فعلًا — ولا يُعلَن موضعُ ما ليس مبنيًّا */
    if (!is_file($ROOT . '/' . $r['route'])) {
        printf("   ⚠ %s — ليس على القرصِ · يُتخطّى\n", $r['route']);
        $skip++; continue;
    }
    $q = $conn->query("SELECT id, IFNULL(placement_basis,'') pb FROM `nav_canonical`
                        WHERE LOWER(`route`) = LOWER('" . $conn->real_escape_string($r['route']) . "')");
    $cur = $q ? $q->fetch_assoc() : null;
    if ($cur && $cur['pb'] !== '') { printf("   ○ %s — مُعلَنٌ سلفًا\n", $r['route']); $skip++; continue; }

    if ($cur) {
        $st = $conn->prepare("UPDATE `nav_canonical`
                                 SET `placement_basis`=?, `decision_source`=?, `retirement_status`=?,
                                     `view_of`=?, `nature`=?
                               WHERE `id`=?");
        $st->bind_param('sssssi', $r['basis'], $SOURCE, $r['retire'], $r['viewof'], $r['nature'], $cur['id']);
    } else {
        $st = $conn->prepare(
            "INSERT INTO `nav_canonical`
                (`route`,`canonical_ar`,`group_name`,`status`,`decision_state`,`application_state`,
                 `placement_kind`,`placement_basis`,`decision_source`,`retirement_status`,`view_of`,`nature`)
             VALUES (?,?,?,'APPROVED','APPROVED','DEPLOYED','SINGLE',?,?,?,?,?)");
        $st->bind_param('ssssssss', $r['route'], $r['name'], $r['group'],
            $r['basis'], $SOURCE, $r['retire'], $r['viewof'], $r['nature']);
    }
    if (!$st->execute()) { fwrite(STDERR, "✘ {$r['route']}: {$conn->error}\n"); exit(1); }
    $st->close(); $done++;
    printf("   ✔ %-42s %-20s ⇐ %s\n", $r['route'], $r['retire'], $r['viewof']);
}

printf("\n③ الحصيلة: %d مُعلَنًا · %d متخطًّى\n", $done, $skip);

/* ── ④ تحقُّقٌ: لا قيمةَ مبتلعةٌ بقيت ────────────────────────────────── */
$q = $conn->query("SELECT COUNT(*) c FROM `nav_canonical` WHERE `placement_kind` = ''");
printf("④ صفوفٌ بـ placement_kind فارغ: %d\n", $q ? (int) $q->fetch_assoc()['c'] : -1);

ems_migration_recorded(__FILE__, $conn, 0);
echo "✔ اكتمل\n";
