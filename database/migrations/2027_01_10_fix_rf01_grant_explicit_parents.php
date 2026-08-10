<?php
/**
 * 2027_01_10_fix_rf01_grant_explicit_parents.php
 * ═══════════════════════════════════════════════════════════════════════════
 * FIX-01 · RF-01 خطوة ③-ج — منحُ الأسطحِ السبعةِ الباقيةِ بأبٍ مُعلَنٍ لكلٍّ.
 *
 * ◆ لماذا لم يبلغها الإغلاقُ الانتقاليّ: مُحيلُها ليس ملفَّ سطحٍ مسجَّلًا —
 *   بل رابطٌ ثابتٌ في القشرة (insidebar.php:336) أو نقطةُ AJAX
 *   (Timesheet/get_timesheet_data.php) أو حارسُ بوابةٍ
 *   (SupplierPortalGuard.php). فالمصدرُ الآليُّ صمت، والصمتُ لا يُقرأ منحًا.
 *
 * ◆ الحكم: لكلِّ سطحٍ **أبٌ مُصرَّحٌ به بالاسم** ومنحُه = منحُ أبيه قراءةً.
 *   وهذا ليس اختراعًا: الأبُ هو الشاشةُ التي يُبلَغ منها فعلًا في القشرةِ
 *   الحية — والمرجعُ سلوكٌ مقيسٌ لا تخمين.
 *
 * ◆ لا كتابةَ تُمنح هنا: القراءةُ وحدَها. صلاحيةُ الفعلِ تُمنح بقرارٍ مستقلٍّ
 *   من مدير الصلاحيات (الفشلُ مغلقٌ افتراضًا — CS-02).
 */

require_once dirname(dirname(__DIR__)) . '/config.php';
require_once dirname(dirname(__DIR__)) . '/tools/fix_lib.php';

/** @var mysqli $conn */
$db = $conn;

$q   = static function ($s) use ($db) { return "'" . $db->real_escape_string((string) $s) . "'"; };
$one = static function ($sql) use ($db) {
    $r = $db->query($sql);
    if (!$r) { throw new RuntimeException('SQL: ' . $db->error . ' — ' . $sql); }
    $x = $r->fetch_row();
    return $x ? $x[0] : null;
};

/** الابنُ ⇐ أبوه المُعلَن (الشاشةُ التي يُبلَغ منها في القشرةِ الحية). */
$PARENTS = array(
    // رابطُ «التقارير» الثابتُ في القشرةِ يجاور «تقارير العقود» في الفرعِ نفسِه.
    'Reports/new_reports.php'        => 'Reports/reports.php',
    // بطاقاتُ لوحةِ التقاريرِ الثلاث — تُفتح من new_reports.php حصرًا.
    'Reports/equipments_reports.php' => 'Reports/new_reports.php',
    'Reports/projects_reports.php'   => 'Reports/new_reports.php',
    'Reports/timesheet_reports.php'  => 'Reports/new_reports.php',
    // تفاصيلُ المورد وعقودُه — تُفتح من قائمةِ الموردين وقائمةِ عقودهم.
    'Suppliers/suppliers_details.php'      => 'Suppliers/suppliers.php',
    'Suppliers/showcontractsuppliers.php'  => 'Suppliers/supplierscontracts.php',
    // شاشةُ قبول/رفض التايم شيت — تُفتح من قائمةِ التايم شيت.
    'Timesheet/aprovment.php'        => 'Timesheet/timesheet.php',
);

$db->begin_transaction();
try {
    $granted = 0;
    // الترتيبُ مهمّ: الأبُ يُمنح قبلَ ابنِه (new_reports قبلَ بطاقاتِه الثلاث).
    foreach ($PARENTS as $child => $parent) {
        $cmid = fix_resolve_module_id($db, $child);
        if ($cmid === null) { echo "[RF-01c] ⚠ ابنٌ غيرُ مسجَّل: {$child}\n"; continue; }
        $roles = array();
        $rs = $db->query("SELECT DISTINCT rp.role_id FROM role_permissions rp
                           JOIN modules m ON m.id = rp.module_id
                          WHERE m.code = " . $q($parent) . " AND rp.can_view = 1");
        while ($rs && ($r = $rs->fetch_assoc())) { $roles[] = (int) $r['role_id']; }
        if (!$roles) { echo "[RF-01c] ⚠ أبٌ بلا منحة: {$parent} (ابنه {$child})\n"; continue; }
        foreach ($roles as $rid) {
            if ($rid <= 0) { continue; }
            if ((int) $one("SELECT COUNT(*) FROM role_permissions WHERE role_id = {$rid} AND module_id = " . (int) $cmid) > 0) { continue; }
            $db->query("INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
                        VALUES ({$rid}, " . (int) $cmid . ", 1, 0, 0, 0)");
            if ($db->errno) { throw new RuntimeException('grant: ' . $db->error); }
            $granted++;
        }
        echo "[RF-01c] {$child} ← {$parent} · أدوار " . count($roles) . "\n";
    }
    $db->commit();
    echo "[RF-01c] منحُ قراءةٍ مُنشأة: {$granted}\n";
} catch (Throwable $e) {
    $db->rollback();
    throw $e;
}
