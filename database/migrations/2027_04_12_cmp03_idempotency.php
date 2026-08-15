<?php
/**
 * 2027_04_12_cmp03_idempotency.php
 * ═══════════════════════════════════════════════════════════════════════════
 * مفتاحُ منعِ التكرارِ يصير قيدًا في القاعدة — ⇐ INJ-0252 · INJ-0140 · INJ-0424
 *
 * نصُّ القبول (0252): «إرسالُ نفسِ النموذج مرتين يُنتج صفًّا واحدًا **ويعيد
 * مرجعَ الأثر الأول**؛ واستعلامُ الصفوف المتطابقة في جداول `scr_*` يُرجع صفرًا».
 *
 * ── ما كان ────────────────────────────────────────────────────────────────
 * `cmp03_store_insert` — **القناةُ الوحيدةُ** لكتابةِ جداولِ الشاشاتِ الاثنتين
 * والأربعين — تبني `INSERT` مباشرًا بلا مفتاحٍ ولا فحصِ وجود. فتحديثُ الصفحةِ
 * بعد الحفظِ (أو نافذةٌ ثانية) يُنشئ صفًّا مطابقًا جديدًا.
 * والعمودُ المعروضُ «مفتاح منع التكرار» كان يُشتقُّ من المعرِّفِ **بعد** الإدراج
 * (`CMP03-<id>`) — فهو **نتيجةٌ لا مفتاح**، ويختلف بين النسختين المكرَّرتين
 * فلا يمنع شيئًا.
 *
 * ── ولماذا جدولٌ واحدٌ لا عمودٌ في كلِّ جدول ───────────────────────────────
 * اثنانِ وأربعونَ جدولًا تعني اثنينِ وأربعينَ عمودًا واثنينِ وأربعينَ قيدًا
 * تتفرّق مع أوّلِ جدولٍ جديد. والسجلُّ الواحدُ يحرسها كلَّها بقيدٍ واحد،
 * ويحمل **مرجعَ الأثرِ الأول** ليُعاد عند التكرار — وهو نصُّ القبولِ حرفًا.
 *
 * ◆ **والمنعُ في القاعدةِ لا في التطبيق (CS-11)**: `UNIQUE(company_id, idem_key)`.
 *   فحصٌ في PHP يُهزم بطلبين متزامنين — والقيدُ لا يُهزم.
 * ◆ ولا يحمل الجدولُ بيانةَ عملٍ: مفتاحٌ ومرجعٌ ووقتٌ — فلا يصير مخزنًا ثانيًا.
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
$u = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_USER') : ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_PASS') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

echo "══ مفتاحُ منعِ التكرارِ يصير قيدًا في القاعدة ══\n\n";

$ok = $conn->query(
    "CREATE TABLE IF NOT EXISTS `cmp03_idempotency` (
        `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `company_id`     INT NOT NULL COMMENT 'عزلُ الشركات — المفتاحُ لا يعبر الكيانات',
        `idem_key`       CHAR(40) NOT NULL COMMENT 'sha1 للفاعلِ والشاشةِ والحمولةِ المعنوية',
        `canonical_file` VARCHAR(80) NOT NULL COMMENT 'الشاشةُ التي كتبت',
        `target_table`   VARCHAR(64) NOT NULL COMMENT 'جدولُ الأثر',
        `row_id`         BIGINT UNSIGNED NULL COMMENT 'مرجعُ الأثرِ الأولِ — يُعاد عند التكرار',
        `created_by`     INT NULL,
        `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_cmp03_idem` (`company_id`, `idem_key`),
        KEY `ix_cmp03_idem_row` (`target_table`, `row_id`)
     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
       COMMENT='INJ-0252: مفتاحُ منعِ تكرارِ كتابةِ شاشاتِ CMP-03 — قيدٌ لا فحصٌ في التطبيق'");
if (!$ok) { exit("✘ تعذّر الإنشاء: {$conn->error}\n"); }
echo "  ✔ cmp03_idempotency — UNIQUE(company_id, idem_key)\n";

/* ── وقيدٌ فريدٌ على مفاتيحِ العملِ في شاشاتِ القمة (INJ-0424) ───────────── */
echo "\n── ورقمُ المستندِ لا يتكرر في شاشاتِ القمة (INJ-0424)\n";
$UNIQ = array(
    array('exec_approvals', 'uq_exec_appr_no', 'request_no'),
    array('exec_contract_signings', 'uq_exec_sign_no', 'contract_no'),
    array('exec_project_charters', 'uq_exec_charter_no', 'decision_no'),
    array('exec_decisions', 'uq_exec_decision_no', 'decision_no'),
);
foreach ($UNIQ as $x) {
    list($t, $idx, $col) = $x;
    $r = $conn->query("SHOW INDEX FROM `{$t}` WHERE Key_name = '{$idx}'");
    if ($r && $r->num_rows > 0) { echo "  · {$t}.{$idx} قائمٌ سلفًا\n"; continue; }
    /* ◆ لا يُفرض قيدٌ على بيانةٍ تخالفه: يُقاس التكرارُ أولًا ويُعلَن */
    $d = $conn->query("SELECT COUNT(*) FROM (SELECT company_id, `{$col}` FROM `{$t}`
                        WHERE `{$col}` IS NOT NULL AND `{$col}` <> ''
                        GROUP BY 1,2 HAVING COUNT(*) > 1) x");
    $dups = ($d && ($y = $d->fetch_row())) ? (int) $y[0] : -1;
    if ($dups !== 0) {
        echo "  ⚠ {$t}: {$dups} مفتاحًا مكرَّرًا في البيانةِ القائمة — لا يُفرض القيدُ فوقها ويُعلَن\n";
        continue;
    }
    if (!$conn->query("ALTER TABLE `{$t}` ADD UNIQUE KEY `{$idx}` (`company_id`, `{$col}`)")) {
        echo "  ✘ {$t}.{$idx}: {$conn->error}\n";
        continue;
    }
    echo "  ✔ {$t} · UNIQUE(company_id, {$col}) — «حفظُ صفَّين برقمِ الطلبِ نفسِه يُرفض»\n";
}

echo "\n✔ تمّت\n";
