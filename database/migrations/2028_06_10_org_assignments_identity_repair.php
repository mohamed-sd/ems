<?php
/**
 * 2028_06_10 — هويّةُ «التكليفات التنظيمية» تُصالَح مع ملفِّها الحيّ
 * ═══════════════════════════════════════════════════════════════════════════
 * ⛔ **العطبُ المقيس**: الوحدة 216 رمزُها `admin/org_assignments.php`
 *   — **وهذا الملفُّ غيرُ موجودٍ على القرص**. والشاشةُ الحيّةُ
 *   `main/org_assignments.php`: هي مسارُ البنودِ الستّةِ في `nav_items`، وهي
 *   وحدَها المسجَّلةُ في `nav_canonical`. فالشاشةُ **تُعرَض بمسارٍ وتُحرَس
 *   برمزٍ آخر** — وهو عينُ «بابٍ واحدٍ بهويّتَين».
 *
 * ◆ **ولم يظهر الأثرُ إلّا بالتصيير**: البندُ كان مُعلَنًا في `nav_items` ولا
 *   موضعَ حاكمًا له، فلم يُصيَّر قطُّ. ولمّا وُضِع في DEP-18 صار مسارًا
 *   مُصيَّرًا **يعجز عن الحلِّ إلى وحدة**، فرسّب `perm01_render_vs_guard`
 *   (‏غيرُ محلول = 9 = ثلاثةُ مستخدمين × ثلاثةِ أسطح).
 *
 * ⭐ **والعلاجُ في الهويّةِ لا في الموضع**: يُصحَّح الرمزُ إلى المسارِ الحيِّ
 *   في **ثلاثةِ سجلّاتٍ معًا** وإلّا انفرط الوصل:
 *     `modules.code` (1) · `gov_profile_items.item_ref` (6) ·
 *     `nav_items.permission_code` (6)
 *   ⛔ **ولا يتغيّر حكمٌ واحد**: `module_id` هي هي (216)، و`role_permissions`
 *     لا تُمَسّ (‏ستةُ أدوارٍ: 1 · 5 · 6 · 9 · 15 · 27 بـ`can_view=1`).
 *     المتغيّرُ **اسمُ الهويّةِ لا مَن يملكها**.
 *
 * ◆ **وصفرُ تصادم**: لا وحدةَ أخرى تحمل `main/org_assignments.php` رمزًا.
 * ◆ **مُعاوَدة**: تشغيلٌ ثانٍ يجد الرمزَ مصالَحًا فيخرج. التشغيل:
 *   php database/migrate.php up
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__, 2) . '/includes/env.php';

$conn = new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'),
                   ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($conn->connect_errno) { fwrite(STDERR, "اتصال المرحِّل فشل\n"); exit(1); }
$conn->set_charset('utf8mb4');

const OLDC = 'admin/org_assignments.php';
const NEWC = 'main/org_assignments.php';
const MOD  = 216;

$one = function ($sql) use ($conn) { $r = $conn->query($sql); $x = $r ? $r->fetch_row() : null; return $x ? $x[0] : null; };
$q = function ($sql) use ($conn) {
    if (!$conn->query($sql)) { fwrite(STDERR, "فشل: {$conn->error}\n"); exit(1); }
    return $conn->affected_rows;
};

echo "── مصالحةُ هويّةِ «التكليفات التنظيمية» ──\n";

/* ⛔ الملفُّ الحيُّ شرطٌ — ولا تُصالَح هويّةٌ إلى مسارٍ لا ملفَّ له */
if (!is_file(dirname(__DIR__, 2) . '/' . NEWC)) {
    fwrite(STDERR, "الملفُّ " . NEWC . " غيرُ موجود — أوقفتُ المصالحة\n"); exit(1);
}
if ((int) $one("SELECT COUNT(*) FROM modules WHERE code='" . NEWC . "'") > 0) {
    echo "  الرمزُ مصالَحٌ سلفًا — لا عمل\n"; exit(0);
}
$clash = (int) $one("SELECT COUNT(*) FROM modules WHERE code='" . NEWC . "' AND id<>" . MOD);
if ($clash > 0) { fwrite(STDERR, "تصادمُ رمز — أوقفتُ المصالحة\n"); exit(1); }

$conn->begin_transaction();
try {
    $a = $q("UPDATE modules SET code='" . NEWC . "' WHERE id=" . MOD . " AND code='" . OLDC . "'");
    $b = $q("UPDATE gov_profile_items SET item_ref='" . NEWC . "' WHERE item_ref='" . OLDC . "'");
    $c = $q("UPDATE nav_items SET permission_code='" . NEWC . "' WHERE permission_code='" . OLDC . "'");
    echo "  modules={$a} · gov_profile_items={$b} · nav_items.permission_code={$c}\n";

    /* ═══ تحقُّقٌ داخلَ المعاملة — والوصلُ يُقاس لا يُفترَض ═══ */
    $left = (int) $one("SELECT (SELECT COUNT(*) FROM modules WHERE code='" . OLDC . "')
                             + (SELECT COUNT(*) FROM gov_profile_items WHERE item_ref='" . OLDC . "')
                             + (SELECT COUNT(*) FROM nav_items WHERE permission_code='" . OLDC . "')");
    $perm = (int) $one("SELECT COUNT(*) FROM role_permissions WHERE module_id=" . MOD . " AND can_view=1");
    $bind = (int) $one("SELECT COUNT(*) FROM gov_profile_items i
                          JOIN modules m ON m.code=i.item_ref
                         WHERE i.item_ref='" . NEWC . "'");
    echo "  بقايا الرمزِ الميّت={$left} · أدوارٌ ترى الوحدة={$perm} (المرجع 6) · بنودٌ تصل لوحدتها={$bind} (المرجع 6)\n";
    if ($left !== 0 || $perm !== 6 || $bind !== 6) { throw new RuntimeException('التحقُّقُ لم يطابق'); }
    $conn->commit();
} catch (Throwable $t) {
    $conn->rollback();
    fwrite(STDERR, "ارتدَّت المعاملةُ: " . $t->getMessage() . "\n"); exit(1);
}

echo "اكتملت المصالحة — الهويّةُ صارت مسارَ الملفِّ الحيّ\n";
exit(0);
