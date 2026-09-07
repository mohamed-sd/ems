<?php
/**
 * 2028_06_06 — هويّةٌ واحدةٌ للشاشة · دمجُ المكرَّرِ وقفلُ `UNIQUE(modules.code)`
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **العطبُ المقيس**: `modules` كان يحمل **سبعةَ أكوادٍ مكرَّرة** في 25 صفًّا —
 *   `main/project_users.php` وحدَه بعشرِ هويّات. والنسخُ تفترق في `owner_role_id`
 *   لا في الشاشة: أي أنَّ الجدولَ يخلط **هويّةَ الشاشة** بـ**موضعِها في قائمةِ
 *   دور** — وللثاني جدولُه (`nav_items`) بحقولِه كاملةً.
 * ⛔ **وأثرُه المقيسُ سابقًا**: 31.4٪ من أزواجِ (شاشةٍ مكرَّرةٍ × دور) تُعطي
 *   **حكمًا متناقضًا** بحسبِ النسخةِ التي يلتقطها المُحلِّل — وحلُّ الهويّةِ
 *   يقع بمطابقةٍ نصّيّةٍ تقريبيّةٍ (`LIKE '%/basename'`) بخمسِ مراحلِ ترجيح.
 *   (‏وحادثتانِ موثَّقتانِ في `permissions_helper.php`: لوحةٌ حُجبت عن كلِّ
 *   الأدوارِ إلّا واحدًا · وشاشةٌ حُرست بصلاحيّةِ شاشةٍ تشاركها الاسم.)
 *
 * ◆ **والدمجُ يُبقي أدنى معرِّفٍ لكلِّ كود** ويُعيد إليه تبعاتِه:
 *     · `role_permissions` — تُدمج الأعلى صلاحيةً ثمَّ يُحذف المكرَّر
 *     · `nav_items`        — يُعاد توجيهُ `module_id`
 *   ثمَّ يُسقَط الصفُّ الزائد. **و`owner_role_id` المتعدِّدُ يسقط عمدًا**: تعدُّدُ
 *   المالكِ في جدولِ الهويّةِ هو عينُ الخلطِ المُزال — والظهورُ لكلِّ دورٍ من
 *   `nav_items` و`nav_workspace_placements` لا من مالكِ الوحدة.
 *
 * ⚠ **الأثرُ المقيسُ على المستخدمين قبلَ التثبيت** (لقطتانِ رابطًا برابط):
 *     · المُصيَّرُ الحاكم (`navarch_render` — كلُّ الـ80): **صفرُ فقد**
 *     · دورا 34 و35 (الوحيدانِ اللذانِ يُصيَّر لهما `getDynamicNavLinks`):
 *       47 و46 رابطًا **قبل وبعد — صفرُ فقد**
 *     · و32 رابطًا سقطت من **مخرَجِ دالّةٍ يُهمَل** لمن هم على المسارِ الموحَّد.
 *
 * ⛔ **والقفلُ هو الغرضُ لا الدمج**: بلا `UNIQUE(code)` يعود التكرارُ بأوّلِ
 *   إدراج، ويبقى المرضُ مرصودًا باختبارٍ بدل أن يكون **مستحيلًا في القاعدة**.
 *
 * ◆ **ومُعاوَدة**: تشغيلٌ ثانٍ يجد صفرَ مكرَّرٍ وقفلًا قائمًا فيُعلن ذلك ويخرج.
 * ⛔ **ولا DDL داخلَ معاملة**: `ALTER` يُحدث التزامًا ضمنيًّا في MariaDB —
 *   فيُنفَّذ الدمجُ ويُحسم قبلَ القفلِ لا معه. (وقع مقيسًا: محاكاةٌ ظُنَّ أنّها
 *   مرتدّةٌ فالتُزمت لأنَّ `ALTER` سبقها ضمنيًّا بالالتزام.)
 *
 * التشغيل: php database/migrate.php up
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$t0 = microtime(true);

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

/* ── ① سجلُّ الأثر: ما دُمج ومتى وإلى أين ─────────────────────────────────── */
$conn->query("CREATE TABLE IF NOT EXISTS `modules_identity_merge` (
  `code`        VARCHAR(190) NOT NULL,
  `kept_id`     INT NOT NULL,
  `dropped_ids` VARCHAR(255) NOT NULL,
  `owners_lost` VARCHAR(255) NOT NULL DEFAULT '',
  `rp_removed`  INT NOT NULL DEFAULT 0,
  `nav_moved`   INT NOT NULL DEFAULT 0,
  `merged_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* ── ② الدمج — خارجَ أيِّ معاملةٍ تحوي DDL ────────────────────────────────── */
$dups = array();
$q = $conn->query("SELECT LOWER(TRIM(code)) c, MIN(id) keep, GROUP_CONCAT(id ORDER BY id) ids,
                          GROUP_CONCAT(DISTINCT owner_role_id ORDER BY owner_role_id) owners
                     FROM modules WHERE code IS NOT NULL AND code <> ''
                    GROUP BY LOWER(TRIM(code)) HAVING COUNT(*) > 1");
while ($q && ($x = $q->fetch_assoc())) { $dups[] = $x; }

echo "  ◦ أكوادٌ مكرَّرةٌ وُجدت: " . count($dups) . "\n";

$merged = 0;
foreach ($dups as $d) {
    $keep = (int) $d['keep'];
    $ids  = array_map('intval', explode(',', $d['ids']));
    $drop = array_values(array_diff($ids, array($keep)));
    if (!$drop) { continue; }
    $in = implode(',', $drop);

    /* أعلى صلاحيةٍ تُرفَع إلى الباقي — فلا يُنتزع حقٌّ بالدمج */
    $conn->query("UPDATE role_permissions r
                    JOIN (SELECT role_id, MAX(can_view) v, MAX(can_add) a,
                                 MAX(can_edit) e, MAX(can_delete) dl
                            FROM role_permissions WHERE module_id IN ({$keep},{$in})
                           GROUP BY role_id) s ON s.role_id = r.role_id
                     SET r.can_view=s.v, r.can_add=s.a, r.can_edit=s.e, r.can_delete=s.dl
                   WHERE r.module_id = {$keep}");
    $conn->query("INSERT IGNORE INTO role_permissions
                    (role_id, module_id, can_view, can_add, can_edit, can_delete)
                  SELECT role_id, {$keep}, can_view, can_add, can_edit, can_delete
                    FROM role_permissions WHERE module_id IN ({$in})");
    $conn->query("DELETE FROM role_permissions WHERE module_id IN ({$in})");
    $rpRemoved = $conn->affected_rows;
    $conn->query("UPDATE nav_items SET module_id = {$keep} WHERE module_id IN ({$in})");
    $navMoved = $conn->affected_rows;
    $conn->query("DELETE FROM modules WHERE id IN ({$in})");

    $st = $conn->prepare("INSERT INTO `modules_identity_merge`
            (code, kept_id, dropped_ids, owners_lost, rp_removed, nav_moved)
            VALUES (?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE dropped_ids=VALUES(dropped_ids), rp_removed=VALUES(rp_removed),
                                    nav_moved=VALUES(nav_moved), merged_at=NOW()");
    $dropStr = implode(',', $drop); $own = (string) $d['owners'];
    $st->bind_param('sissii', $d['c'], $keep, $dropStr, $own, $rpRemoved, $navMoved);
    $st->execute(); $st->close();
    $merged++;
    echo "     ✔ {$d['c']} → id={$keep} · أُسقط " . count($drop) . " · rp-{$rpRemoved} · nav→{$navMoved}\n";
}
echo "  ✔ أكوادٌ دُمجت: {$merged}\n";

/* ── ③ القفل — بعدَ الدمجِ لا معه ─────────────────────────────────────────── */
$has = false;
$r = $conn->query("SHOW INDEX FROM `modules` WHERE Column_name='code' AND Non_unique=0");
if ($r && $r->num_rows > 0) { $has = true; }

if ($has) {
    echo "  ◦ قفلُ UNIQUE(code) قائمٌ سلفًا — لا تغيير\n";
} else {
    $left = $conn->query("SELECT COUNT(*) c FROM (SELECT LOWER(TRIM(code)) k FROM modules
                            WHERE code IS NOT NULL AND code<>'' GROUP BY 1 HAVING COUNT(*)>1) x")->fetch_assoc();
    if ((int) $left['c'] > 0) {
        exit("⛔ ما زال {$left['c']} كودًا مكرَّرًا — لا يُقفَل على حالٍ لم يُصفَّ\n");
    }
    if ($conn->query("ALTER TABLE `modules` ADD UNIQUE KEY `uq_modules_code` (`code`)")) {
        echo "  ✔ **قُفل UNIQUE(modules.code)** — عودةُ التكرارِ صارت مستحيلةً في القاعدة\n";
    } else {
        exit("✘ تعذّر القفل: {$conn->error}\n");
    }
}

require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
