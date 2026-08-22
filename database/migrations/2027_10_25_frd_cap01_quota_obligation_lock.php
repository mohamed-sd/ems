<?php
/**
 * 2027_10_25_frd_cap01_quota_obligation_lock.php
 *   قفلُ الهرم — «لا حصةَ بلا التزامِ نوعِ معدةٍ في عقدِ عميلٍ نافذ» (CAP-01 §8.2)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **البند ٢-١ من INJ-EXEC-CLOSE-01.** كانت القاعدةُ مكتوبةً في ثلاثةِ مواضعَ
 *   — الوثيقةِ، وتعليقِ `SupplierContractService`، وملصقِ الحقلِ في الشاشة —
 *   ومنفَّذةً في **صفر**: فحصُ الخدمةِ مشروطٌ فيُتخطّى بحذفِ الحقل · والحقلُ بلا
 *   `required` و«غير مرتبط» خيارُه الأول · و**صفرُ مفتاحٍ أجنبيٍّ وصفرُ قادح** ·
 *   و**٦ من ٦ بنودٍ حيةٍ تخالف القاعدة**.
 *
 * ◆ **وما تفعله هذه الهجرةُ وما لا تفعله** — والفرقُ مقصودٌ لا نقص:
 *   ① **مفتاحٌ أجنبيّ** `fk_sup_line_obligation` — فمرجعٌ لا يقابله التزامٌ
 *      قائمٌ **يستحيل بنيويًّا**، ولا يُحذَف التزامٌ تحتَ حصةٍ تشير إليه.
 *   ② **تحاول** قادحَين `trg_sup_line_obl_ins` و`trg_sup_line_obl_upd` يردّانِ
 *      الصفَّ الجديدَ إن كان المرجعُ `NULL` — **وقد رُدَّ إنشاؤُهما بامتيازِ خادم**:
 *      `SUPER` مطلوبٌ مع `log_bin=ON` و`log_bin_trust_function_creators=OFF`،
 *      و`ems_migrator` له ALL PRIVILEGES على المخطَّطِ ولا SUPER عامًّا. فالحالُ
 *      `BLOCKED_ENVIRONMENT` **مُعلَنًا لا مُدَّعًى**، والنافذةُ الانتقاليةُ مفتوحةٌ
 *      حتى `NOT NULL` — وهي أقوى من قادحٍ ولا تلزمها امتيازات.
 *   ③ ولا تُجعل الخانةُ `NOT NULL`، ولا تُملأ الصفوفُ الستةُ القائمة —
 *      **لأن ملأَها اختراعُ واقعةِ عمل**: البندُ 1 له **ثلاثةُ التزاماتٍ مرشَّحة**
 *      على عقدِ عميلِه، والبندُ 5 نموذجُ عملِه `meter` بينما الالتزامُ الوحيدُ
 *      على عقدِه `daily_availability_hours` — فربطُهما يخترع قياسًا لم يُتَّفق
 *      عليه. ⇒ **قرارُ مالكٍ لكلِّ صفٍّ على حدة** (`NEEDS_GOVERNING_SOURCE`)،
 *      وبعده تُجعل الخانةُ `NOT NULL` في هجرةٍ تالية.
 *
 * ◆ **والمبدأ: الإنفاذُ لا ينتظر قرارَ المالكِ ليبدأ، والتصحيحُ لا يُخمَّن
 *   ليُستعجَل.** فما أمكن قفلُه اليومَ قُفل (الخدمةُ والواجهةُ والمفتاحُ الأجنبيّ)،
 *   وما لزمه قرارٌ أو امتيازٌ **أُعلن مفتوحًا بمقدارِه ومالكِه** ولم يُدَّعَ إغلاقُه.
 *
 * التشغيل:  php database/migrations/2027_10_25_frd_cap01_quota_obligation_lock.php
 * الرجوع :  php database/migrations/2027_10_25_frd_cap01_quota_obligation_lock.php --revert
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

$FK  = 'fk_sup_line_obligation';
$TI  = 'trg_sup_line_obl_ins';
$TU  = 'trg_sup_line_obl_upd';
$MSG = 'لا حصةَ بلا التزام — contract_obligation_ref مطلوبٌ لكلِّ بندِ حصةٍ جديد (CAP-01 §8.2)';

$hasFk = function () use ($conn, $FK) {
    $r = $conn->query("SELECT COUNT(*) FROM information_schema.table_constraints
                        WHERE constraint_schema = DATABASE()
                          AND table_name = 'supplier_contract_lines'
                          AND constraint_name = '{$FK}'");
    return $r ? (int) $r->fetch_row()[0] > 0 : false;
};

if (in_array('--revert', $argv, true)) {
    $conn->query("DROP TRIGGER IF EXISTS `{$TI}`");
    $conn->query("DROP TRIGGER IF EXISTS `{$TU}`");
    if ($hasFk()) { $conn->query("ALTER TABLE `supplier_contract_lines` DROP FOREIGN KEY `{$FK}`"); }
    echo "↺ رُفع قفلُ الهرم — القادحانِ والمفتاحُ الأجنبيّ\n";
    exit(0);
}

$t0 = microtime(true);

/* ── ① صفرُ مرجعٍ يتيمٍ قبلَ المفتاح — وإلا رفضته القاعدةُ ونحن لا ندري لماذا ── */
$orph = 0;
$r = $conn->query("SELECT COUNT(*) FROM `supplier_contract_lines` l
                    WHERE l.`contract_obligation_ref` IS NOT NULL
                      AND NOT EXISTS (SELECT 1 FROM `contract_commitments` c
                                       WHERE c.`id` = l.`contract_obligation_ref`)");
if ($r) { $orph = (int) $r->fetch_row()[0]; }
if ($orph > 0) {
    exit("⛔ **{$orph} مرجعًا يتيمًا** — يُحسم قبلَ المفتاحِ الأجنبيّ ولا يُحذَف صامتًا\n");
}
echo "① مراجعُ يتيمة: 0 — الطريقُ سالكٌ للمفتاح\n";

/* ── ② المفتاحُ الأجنبيّ — المرجعُ الموجودُ يجب أن يكون حقيقيًّا ────────────── */
if (!$hasFk()) {
    $ok = $conn->query("ALTER TABLE `supplier_contract_lines`
        ADD CONSTRAINT `{$FK}` FOREIGN KEY (`contract_obligation_ref`)
        REFERENCES `contract_commitments` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE");
    if (!$ok) { exit("⛔ تعذّر إنشاءُ المفتاح: " . $conn->error . "\n"); }
    echo "② أُنشئ {$FK} — ولا يُحذَف التزامٌ تحتَ حصةٍ تشير إليه (RESTRICT)\n";
} else {
    echo "② {$FK} قائمٌ سلفًا\n";
}

/* ── ③ القادحان — الجديدُ يُردُّ والقديمُ يبقى ────────────────────────────── */
/* ◆ **ويُجرَّبان لا يُفترضان**، وإن رُدَّ الإنشاءُ لامتيازِ خادمٍ **أُعلن ذلك
 *   ولم تُدَّعَ حراسةٌ لا وجودَ لها**. فالقاعدةُ هنا: ما لم يُثبَت أثرُه لا يُقيَّد. */
$trgOk = false; $trgErr = '';
$conn->query("DROP TRIGGER IF EXISTS `{$TI}`");
$conn->query("DROP TRIGGER IF EXISTS `{$TU}`");
$mk = function ($name, $when) use ($conn, $MSG) {
    $sql = "CREATE TRIGGER `{$name}` BEFORE {$when} ON `supplier_contract_lines`
            FOR EACH ROW
            BEGIN
              IF NEW.`contract_obligation_ref` IS NULL THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$MSG}';
              END IF;
            END";
    return (bool) @$conn->query($sql);
};
if ($mk($TI, 'INSERT')) {
    $trgOk = $mk($TU, 'UPDATE');
    if (!$trgOk) { $trgErr = $conn->error; $conn->query("DROP TRIGGER IF EXISTS `{$TI}`"); }
} else {
    $trgErr = $conn->error;
}
if ($trgOk) {
    echo "③ أُنشئ القادحانِ {$TI} و{$TU} — والالتفافُ باستعلامٍ مباشرٍ يُردُّ من القاعدة\n";
} else {
    echo "③ ⛔ **القادحانِ محجوبانِ بامتيازِ خادمٍ لا بتصميم** — BLOCKED_ENVIRONMENT\n";
    echo "     السبب: " . mb_substr($trgErr, 0, 90) . "\n";
    echo "     المقيس: `ems_migrator` له ALL PRIVILEGES على المخطَّطِ **ولا SUPER عامًّا**،\n";
    echo "     و`log_bin=ON` و`log_bin_trust_function_creators=OFF` — وكلاهما إعدادُ خادمٍ\n";
    echo "     لا يُغيَّر من هجرة. **ولا يُدَّعى قفلٌ لا يعمل.**\n";
    echo "     ◆ والقفلُ البنيويُّ التامُّ **لا يحتاج قادحًا أصلًا**: بعدَ حسمِ الصفوفِ\n";
    echo "       القائمةِ تُجعل الخانةُ `NOT NULL` — وهو أقوى من قادحٍ ولا يلزمه امتياز.\n";
    echo "       فالقادحُ جسرٌ للنافذةِ الانتقاليةِ وحدَها، وغيابُه يُبقيها مفتوحةً **مُعلَنةً**.\n";
}

/* ── ④ جسٌّ وظيفيٌّ — الحالُ يُقاس لا يُفترض ─────────────────────────────── */
/* ◆ **والرفضُ يُنسَب إلى علّتِه**: أولُ صيغةٍ لهذا الجسِّ أدرجت `('hour','ساعة')`
 *   على عقدٍ قائمٍ فرُدَّت — **لا لغيابِ المرجعِ بل لتصادمِ `uq_sup_line_model_unit`**
 *   (contract_id · work_model · unit). فكان «رُدَّ» خضرةً كاذبةً تنسب الحراسةَ
 *   إلى قاعدةٍ لم تعمل. فالوحدةُ الآن **موسومةٌ فريدة** فلا يتصادم المفتاح،
 *   ونصُّ الخطأِ يُفحص: تصادمُ مفتاحٍ ليس حراسةَ هرم. */
$dbRefuses = false; $probeErr = '';
$UNIT = 'وحدةُ جسٍّ ' . substr(sha1((string) $t0), 0, 8);
$row = $conn->query("SELECT `company_id`, `contract_id` FROM `supplier_contract_lines` LIMIT 1");
if ($row && ($x = $row->fetch_assoc())) {
    $uq = $conn->real_escape_string($UNIT);
    $ins = @$conn->query("INSERT INTO `supplier_contract_lines`
        (`company_id`, `contract_id`, `work_model`, `unit`, `unit_price`)
        VALUES ({$x['company_id']}, {$x['contract_id']}, 'hour', '{$uq}', 1)");
    if ($ins === false) {
        $probeErr = $conn->error;
        /* تصادمُ المفتاحِ الفريدِ ليس حراسةَ الهرم — ولا يُحسب لها */
        $dbRefuses = (stripos($probeErr, 'Duplicate entry') === false);
    } else {
        @$conn->query("DELETE FROM `supplier_contract_lines` WHERE `id` = " . (int) $conn->insert_id);
    }
}
echo '④ الجسُّ الوظيفيّ — إدراجٌ مباشرٌ بلا مرجع: '
   . ($dbRefuses ? "**رُدَّ** — «" . mb_substr($probeErr, 0, 60) . "»"
                 : '**مرَّ** — والنافذةُ الانتقاليةُ مفتوحةٌ مُعلَنة') . "\n";
/* ── ⑤ الصفوفُ الستةُ — تُعلَن ولا تُخمَّن ────────────────────────────────── */
$legacy = array();
$r = $conn->query("SELECT l.`id`, h.`client_contract_id`, l.`work_model`,
                          (SELECT COUNT(*) FROM `contract_commitments` c
                            WHERE c.`contract_ref` = h.`client_contract_id`
                              AND COALESCE(c.`is_deleted`, 0) = 0) AS cands
                     FROM `supplier_contract_lines` l
                     JOIN `supplier_contracts` h ON h.`id` = l.`contract_id`
                    WHERE l.`contract_obligation_ref` IS NULL
                    ORDER BY l.`id`");
while ($r && ($x = $r->fetch_assoc())) { $legacy[] = $x; }
if ($legacy) {
    echo "\n⑤ **صفوفٌ قائمةٌ بلا مرجع — قرارُ مالكٍ لكلِّ صفّ** (NEEDS_GOVERNING_SOURCE):\n";
    foreach ($legacy as $l) {
        printf("   · البند %-3d · عقدُ العميل %-4d · نموذجُ العمل %-6s · التزاماتٌ مرشَّحةٌ على عقدِه: %d\n",
               $l['id'], $l['client_contract_id'], $l['work_model'], $l['cands']);
    }
    echo "   ◆ **لا تُملأ باجتهادٍ**: مرشَّحٌ واحدٌ ليس قرارًا، ونموذجُ عملٍ يخالف\n";
    echo "     قياسَ الالتزامِ يخترع واقعةً لم يُتَّفق عليها. وبعدَ الحسمِ تُجعل\n";
    echo "     الخانةُ `NOT NULL` في هجرةٍ تالية.\n";
}

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
echo "\n◆ **قفلُ الهرم — ما قُفل وما بقي مفتوحًا مُعلَنًا**:\n";
echo "   ✔ الخدمةُ ترد ٤٢٢ على الغياب — `SupplierContractService::saveLine`\n";
echo "   ✔ والحقلُ `required` وخيارُ «غير مرتبط» أُسقط من الشاشة\n";
echo "   ✔ ومفتاحٌ أجنبيٌّ يمنع مرجعًا وهميًّا — {$FK}\n";
echo '   ' . ($trgOk ? '✔' : '⛔') . ' وقفلُ القاعدةِ على `NULL`: '
   . ($trgOk ? 'قادحان يردّانِ الإدراجَ والتعديل'
             : '**محجوبٌ بامتيازِ خادم** — و`NOT NULL` بعدَ قرارِ المالك') . "\n";
echo '   ⛔ و' . count($legacy) . " صفًّا قائمًا بلا مرجع — **قرارُ مالكٍ لكلِّ صفّ**\n";
echo "\n◆ **ولا يُدَّعى إغلاقُ البند**: قُفل ما أمكن قفلُه، وأُعلن الباقي بمقدارِه ومالكِه.\n";
