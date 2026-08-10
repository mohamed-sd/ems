<?php
/**
 * 2027_01_09_fix_rf01_grant_closure.php
 * ═══════════════════════════════════════════════════════════════════════════
 * FIX-01 · RF-01 خطوة ③-ب — إغلاقُ المنحِ الانتقاليِّ للأسطحِ التسعةِ اليتيمة.
 *
 * ◆ گوتشا مرصودة: الترحيلُ الأولُ اشتقّ الأدوارَ من المُحيلين — و**المُحيلُ
 *   نفسُه كان غيرَ مسجَّلٍ ساعتَها** (Reports/new_reports.php يُحيل إلى ثلاثةِ
 *   تقاريرَ وهو نفسُه في الأربعين)، فعادت السلسلةُ خاويةً وسقطت تسعةُ أسطحٍ
 *   بلا منحة. الحلُّ إغلاقٌ انتقاليٌّ يُعاد حتى الثبات — لا قائمةٌ يدوية.
 *
 * ◆ ومصدرٌ ثانٍ للأدوارِ يفوت المُحيلَ الملفّيّ: الروابطُ **الثابتةُ** في
 *   القشرةِ (insidebar.php · includes/topbar.php) — تصل كلَّ دورٍ يبلغ فرعَها.
 *   فرابطُ التوبار (البحثُ الموحَّد) يُمنح لكلِّ دورٍ فعّالٍ صراحةً: الترشيحُ
 *   في نتائجِه لا في بابه.
 *
 * ◆ عطالة: يُعاد تشغيلُه بلا أثرٍ مضاعف.
 */

require_once dirname(dirname(__DIR__)) . '/config.php';
require_once dirname(dirname(__DIR__)) . '/tools/fix_lib.php';

/** @var mysqli $conn */
$db   = $conn;
$ROOT = dirname(dirname(__DIR__));

$q   = static function ($s) use ($db) { return "'" . $db->real_escape_string((string) $s) . "'"; };
$one = static function ($sql) use ($db) {
    $r = $db->query($sql);
    if (!$r) { throw new RuntimeException('SQL: ' . $db->error . ' — ' . $sql); }
    $x = $r->fetch_row();
    return $x ? $x[0] : null;
};

/* ── ① الأسطحُ المسجَّلةُ بلا منحةِ قراءةٍ واحدة ─────────────────────── */
// يُرجع  [المسارُ النسبي => معرّفُ الموديولِ المحلول]  لكلِّ سطحٍ بلا منحةِ قراءة.
// ◆ گوتشا: الحسابُ يجري على **الموديولِ المحلولِ** لا على مطابقةِ الكودِ
//   الحرفية — فسطحٌ يُحَلُّ بالذيلِ إلى موديولٍ كودُه اسمُ الملفِّ وحدَه كان
//   يُعَدُّ يتيمًا ثم يُبحث عن كودِه التامِّ فلا يوجد ⇒ module_id=0 ⇒ انفجارُ
//   مفتاحٍ أجنبيّ.
$findOrphans = static function () use ($db, $ROOT) {
    $out = array();
    foreach (fix_surface_files($ROOT) as $rel) {
        $mid = fix_resolve_module_id($db, $rel);
        if ($mid === null) { continue; }
        $n = (int) fix_one($db, "SELECT COUNT(*) FROM role_permissions rp
                                  JOIN modules m ON m.id = rp.module_id
                                 WHERE m.code = (SELECT code FROM modules WHERE id = " . (int) $mid . ")
                                   AND rp.can_view = 1");
        if ($n === 0) { $out[$rel] = (int) $mid; }
    }
    return $out;
};

/* ── ② فهرسُ المُحيلين (مرةً واحدةً — المسحُ ثمين) ──────────────────── */
$allPhp = fix_php_files($ROOT);
$srcCache = array();
$refsOf = static function ($target) use (&$srcCache, $allPhp, $ROOT) {
    $bn = basename($target);
    $hits = array();
    foreach ($allPhp as $rel) {
        if ($rel === $target) { continue; }
        if (!isset($srcCache[$rel])) { $srcCache[$rel] = (string) @file_get_contents($ROOT . '/' . $rel); }
        if ($srcCache[$rel] !== '' && strpos($srcCache[$rel], $bn) !== false) { $hits[] = $rel; }
    }
    return $hits;
};

/* ── ③ الإغلاقُ الانتقاليّ: أعِدْ حتى لا يتغيّر شيء ─────────────────── */
$db->begin_transaction();
try {
    $granted = 0;
    for ($pass = 1; $pass <= 6; $pass++) {
        $orphans = $findOrphans();
        if (!$orphans) { break; }
        $added = 0;
        foreach ($orphans as $target => $mid) {
            $mid = (int) $mid;
            if ($mid <= 0) { continue; }
            $roles = array();
            foreach ($refsOf($target) as $refRel) {
                $rmid = fix_resolve_module_id($db, $refRel);
                if ($rmid === null) { continue; }
                $rs = $db->query("SELECT DISTINCT rp.role_id FROM role_permissions rp
                                   JOIN modules m ON m.id = rp.module_id
                                  WHERE m.code = (SELECT code FROM modules WHERE id = " . (int) $rmid . ")
                                    AND rp.can_view = 1");
                while ($rs && ($r = $rs->fetch_assoc())) { $roles[(int) $r['role_id']] = true; }
            }
            foreach (array_keys($roles) as $rid) {
                if ($rid <= 0) { continue; }
                if ((int) $one("SELECT COUNT(*) FROM role_permissions WHERE role_id = " . (int) $rid . " AND module_id = " . $mid) > 0) { continue; }
                $db->query("INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
                            VALUES (" . (int) $rid . ", " . $mid . ", 1, 0, 0, 0)");
                if ($db->errno) { throw new RuntimeException('grant: ' . $db->error); }
                $granted++; $added++;
            }
        }
        echo "[RF-01b] جولة {$pass}: أيتامٌ " . count($orphans) . " · منحٌ جديدة {$added}\n";
        if ($added === 0) { break; }
    }

    /* ── ④ الأسطحُ العرضيةُ في القشرةِ الثابتة: تُمنح لكلِّ دورٍ فعّال ── */
    $crossCutting = array(
        'main/global_search.php', // رابطُ التوبارِ لكلِّ مستخدمٍ — والترشيحُ في النتائج
        'main/user_profile.php',  // ملفُّ المستخدمِ نفسِه
        'main/soon.php',          // لافتةُ «قريبًا» — وجهةُ الروابطِ غيرِ المبنية
    );
    foreach ($crossCutting as $rel) {
        $mid = fix_resolve_module_id($db, $rel);
        if ($mid === null) { continue; }
        $mid = (int) $mid;
        $rs = $db->query("SELECT id FROM roles");
        while ($rs && ($r = $rs->fetch_assoc())) {
            $rid = (int) $r['id'];
            if ((int) $one("SELECT COUNT(*) FROM role_permissions WHERE role_id = {$rid} AND module_id = {$mid}") > 0) { continue; }
            $db->query("INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
                        VALUES ({$rid}, {$mid}, 1, 0, 0, 0)");
            if ($db->errno) { throw new RuntimeException('cross grant: ' . $db->error); }
            $granted++;
        }
    }

    $db->commit();
    echo "[RF-01b] منحُ قراءةٍ مُنشأة: {$granted}\n";
    $left = array_keys($findOrphans());
    echo "[RF-01b] أسطحٌ باقيةٌ بلا منحة: " . count($left) . ($left ? ' → ' . implode(' · ', $left) : '') . "\n";
} catch (Throwable $e) {
    $db->rollback();
    throw $e;
}
