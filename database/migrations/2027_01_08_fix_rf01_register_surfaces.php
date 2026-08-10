<?php
/**
 * 2027_01_08_fix_rf01_register_surfaces.php
 * ═══════════════════════════════════════════════════════════════════════════
 * FIX-01 · RF-01 خطوة ③+④ — تسجيلُ الأسطحِ الأربعينَ غيرِ المسجَّلةِ في modules
 * **قبلَ** قلبِ الحارسِ إلى الفشلِ المغلق.
 *
 * ◆ الحكم (FIXA-0017/FIXA-0018 · RSK-F1): «شغّلْ مسحًا يحصر الشاشاتِ الأربعينَ
 *   ويسجّلها في modules قبلَ نشرِ الإصلاح — ولا تنشرِ الإصلاحَ قبلَ التسجيل،
 *   فالمنعُ الفوريُّ يُعطّل أربعينَ شاشةً حية».
 *
 * ◆ مبدأُ الترحيل: **لا توسيعَ ولا تضييق**. الشاشةُ التي تُقرأ اليومَ بفرعِ
 *   الغيابِ المفتوحِ (= يراها كلُّ من يبلغها) تُمنح غدًا لمن كان يبلغها فعلًا:
 *   أدوارُ الشاشاتِ **المُحيلةِ** إليها (رابطٌ في ملفٍّ مسجَّل) واتحادُ أدوارِ
 *   صفوفِ التنقلِ التي تشير إليها. فالمرجعُ سلوكٌ مقيسٌ لا تخمين.
 *
 * ◆ عطالة: يُعاد تشغيلُه بلا أثرٍ مضاعف (INSERT ... WHERE NOT EXISTS).
 */

require_once dirname(dirname(__DIR__)) . '/config.php';
require_once dirname(dirname(__DIR__)) . '/tools/fix_lib.php';

/** @var mysqli $conn */
$db   = $conn;
$ROOT = dirname(dirname(__DIR__));

$q = static function ($s) use ($db) { return "'" . $db->real_escape_string((string) $s) . "'"; };
$one = static function ($sql) use ($db) {
    $r = $db->query($sql);
    if (!$r) { throw new RuntimeException('SQL: ' . $db->error . ' — ' . $sql); }
    $x = $r->fetch_row();
    return $x ? $x[0] : null;
};

/* ── ① الأسطحُ غيرُ المسجَّلة ─────────────────────────────────────────── */
$unregistered = array();
foreach (fix_surface_files($ROOT) as $rel) {
    if (fix_resolve_module_id($db, $rel) === null) { $unregistered[] = $rel; }
}
sort($unregistered);
echo "[RF-01] أسطحٌ غيرُ مسجَّلة: " . count($unregistered) . "\n";
if (!$unregistered) { echo "[RF-01] لا شيءَ يُسجَّل — الحالةُ نظيفة.\n"; return; }

/* ── ② خريطةُ المُحيلين: أيُّ ملفٍّ حيٍّ يرتبط بهذا السطح؟ ───────────── */
$allPhp = fix_php_files($ROOT);
$refIndex = array(); // basename => [مسارُ المُحيل, ...]
foreach ($allPhp as $rel) {
    $src = (string) @file_get_contents($ROOT . '/' . $rel);
    if ($src === '') { continue; }
    foreach ($unregistered as $target) {
        $bn = basename($target);
        if ($rel === $target) { continue; }
        if (strpos($src, $bn) === false) { continue; }
        $refIndex[$target][] = $rel;
    }
}

/* ── ③ أدوارُ كلِّ سطحٍ: من المُحيلين ومن صفوفِ التنقل ───────────────── */
$plan = array();
foreach ($unregistered as $target) {
    $roles = array();

    // (أ) أدوارُ صفوفِ التنقلِ التي تشير إلى هذا السطحِ صراحةً (المصدرُ الأدقّ).
    $like = '%' . basename($target);
    $rs = $db->query("SELECT DISTINCT role_id FROM nav_items
                       WHERE route = " . $q($target) . "
                          OR route = " . $q('../' . $target) . "
                          OR route LIKE " . $q($like) . "
                          OR route LIKE " . $q($like . '?%') . "
                          OR route LIKE " . $q($like . '#%'));
    while ($rs && ($r = $rs->fetch_assoc())) { $roles[(int) $r['role_id']] = true; }

    // (ب) أدوارُ الشاشاتِ المُحيلةِ إليه — اتحادُ مانحي القراءةِ لموديولاتها.
    foreach ($refIndex[$target] ?? array() as $refRel) {
        $mid = fix_resolve_module_id($db, $refRel);
        if ($mid === null) { continue; }
        $rs = $db->query("SELECT DISTINCT rp.role_id FROM role_permissions rp
                           JOIN modules m ON m.id = rp.module_id
                          WHERE m.code = (SELECT code FROM modules WHERE id = " . (int) $mid . ")
                            AND rp.can_view = 1");
        while ($rs && ($r = $rs->fetch_assoc())) { $roles[(int) $r['role_id']] = true; }
    }

    $plan[$target] = array_keys($roles);
}

/* ── ④ التسجيل: صفُّ موديولٍ لكلِّ سطحٍ + منحُ القراءةِ لأدوارِه ──────── */
$db->begin_transaction();
try {
    $newModules = 0; $newGrants = 0; $orphans = array();
    foreach ($plan as $target => $roles) {
        $name = fix_screen_title($ROOT . '/' . $target, $target);
        // مالكُ الشاشة: أولُ دورٍ يملك موديولَ الشاشةِ المُحيلةِ الأولى — أو NULL.
        $owner = null;
        foreach ($refIndex[$target] ?? array() as $refRel) {
            $mid = fix_resolve_module_id($db, $refRel);
            if ($mid === null) { continue; }
            $o = $one("SELECT owner_role_id FROM modules WHERE id = " . (int) $mid);
            if ($o !== null && (int) $o > 0) { $owner = (int) $o; break; }
        }

        $exists = (int) $one("SELECT COUNT(*) FROM modules WHERE code = " . $q($target));
        if ($exists === 0) {
            $db->query("INSERT INTO modules (name, code, owner_role_id, group_id, is_link, is_quick, icon, display_order)
                        VALUES (" . $q($name) . ", " . $q($target) . ", "
                        . ($owner === null ? 'NULL' : (int) $owner) . ", NULL, 0, 0, 'fa fa-file-lines', 900)");
            if ($db->errno) { throw new RuntimeException('modules INSERT: ' . $db->error . ' — ' . $target); }
            $newModules++;
        }
        $mid = (int) $one("SELECT id FROM modules WHERE code = " . $q($target) . " ORDER BY id ASC LIMIT 1");

        if (!$roles) { $orphans[] = $target; }
        foreach ($roles as $rid) {
            if ($rid <= 0) { continue; }
            $has = (int) $one("SELECT COUNT(*) FROM role_permissions WHERE role_id = " . (int) $rid . " AND module_id = " . $mid);
            if ($has > 0) { continue; }
            // القراءةُ وحدَها: الكتابةُ تُمنح بقرارٍ لا بترحيل (الفشلُ مغلقٌ افتراضًا).
            $db->query("INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
                        VALUES (" . (int) $rid . ", " . $mid . ", 1, 0, 0, 0)");
            if ($db->errno) { throw new RuntimeException('role_permissions INSERT: ' . $db->error); }
            $newGrants++;
        }
    }
    $db->commit();
    echo "[RF-01] موديولاتٌ مُنشأة: {$newModules} · منحُ قراءةٍ مُنشأة: {$newGrants}\n";
    if ($orphans) {
        echo "[RF-01] ⚠ أسطحٌ بلا مُحيلٍ ولا صفِّ تنقلٍ (سُجِّلت بلا منحة — تُمنح بقرار):\n";
        foreach ($orphans as $o) { echo "         · {$o}\n"; }
    }
} catch (Throwable $e) {
    $db->rollback();
    throw $e;
}
