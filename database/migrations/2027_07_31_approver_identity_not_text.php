<?php
/**
 * 2027_07_31_approver_identity_not_text.php — اسمُ المعتمِدِ يُقرأ من السلسلةِ لا يُكتب
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ نصُّ الطلب (ثامنًا-٣): «30 جدولًا يُكتب فيها اسمُ المعتمِدِ يدويًّا —
 *   **الاسمُ يجب أن يُقرأ من سلسلةِ الاعتمادِ لا أن يُكتبَ نصًّا**».
 *
 * ◆ **والمقيسُ يطابق المُعلَنَ هنا: 30 عمودًا بالضبط** في 30 جدولَ أساسٍ
 *   (`approver_name` × 29 · `approved_by_name` × 1) — وهذا البندُ الوحيدُ من
 *   الثلاثةِ الذي أعادَ الفاحصُ رقمَه كما هو (`tools/pkg4_measure.php` · AC-L8).
 *
 * ◆ **وما كشفه القياسُ أخطرُ من العدد**: النصوصُ المكتوبةُ **لا تُشير إلى أحد**.
 *   469 صفًّا يحمل اسمًا؛ منها **376 بذرةٌ** (`is_seed=1`) بأسماءٍ لا وجودَ لها
 *   في `users` ولا في `employees` («سارة الفاتح — مدير الموقع»…)، **والشخصُ
 *   الواحدُ يحمل صفتَين مختلفتَين** في جدولَين («أروى عثمان — مدير الأسطول»
 *   و«أروى عثمان — أمين المخزن»)، **وبعضُها ليس شخصًا أصلًا** («قرار شركة
 *   منفذة — ق-15» · «إكمال M-16 — 2026-08-08»). فالعمودُ يحمل ثلاثةَ معانٍ
 *   في نوعٍ واحد: شخصًا وصفةً ومرجعَ قرار. **وما لا يُحلُّ إلى هويةٍ لا يُدقَّق.**
 *
 * ◆ **فلا يُملأ ما لا مصدرَ له**: التعبئةُ الرجعيةُ مستحيلةٌ لأن الأشخاصَ غيرُ
 *   موجودين — **ولا يُخترع ربطٌ ليبدوَ العمودُ ممتلئًا**. والعلاجُ أمامي:
 *   ① يُضاف `approver_user_id` مفتاحًا أجنبيًّا إلى `users` — فللهويةِ بيتٌ.
 *   ② **قيدٌ أماميٌّ بمرساةٍ زمنية**: كلُّ صفٍّ يُنشأ من الآن ويحمل اسمًا **يجب
 *      أن يحمل هوية**. والقديمُ معفًى صراحةً بالمرساةِ — **لا يُحذف ولا يُزوَّر**.
 *      (والمرساةُ لازمة: قيدٌ عاديٌّ يرفضه الصفُّ القائمُ فلا يُنشأ أصلًا — وقد
 *       جُرِّب في جولةِ التلوثِ ثلاثَ مراتٍ وفشلَ ثلاثًا قبلَ هذه الحيلة.)
 *   ③ ويُعلَن المتبقّي باسمِه: النصُّ البذرةُ يبقى مرئيًّا `legacy` لا مغسولًا.
 *
 * ◆ **و`timesheet_approvals` سليمٌ سلفًا** فلا يُمَسّ: 85 صفًّا من 85 تحمل
 *   `approved_by`، **وصفرُ صفٍّ يخالف نصُّه هويتَه** — فنصُّه مرآةٌ لا تأليف.
 *
 * ◆ **فخُّ مارياDB الذي كلّف جولةً كاملة**: عمودٌ في **مفتاحٍ أجنبيٍّ يغيّرُه**
 *   (`ON DELETE SET NULL` أو `CASCADE`) **لا يقبل قيدَ CHECK يشير إليه** —
 *   والرسالةُ مضلِّلةٌ تمامًا: «Function or expression 'approver_user_id'
 *   cannot be used in the CHECK clause». فأُضيفت 29 عمودًا و29 مفتاحًا ثم
 *   **سقطت 29 قيدًا** ولو صُدِّقت الرسالةُ حرفًا لظُنَّ العمودُ نفسُه ممنوعًا.
 *   وكُشف بالتجريب: إسقاطُ المفتاحِ يُقبل القيدَ فورًا، و`RESTRICT` و`NO ACTION`
 *   يتعايشان معه و`CASCADE` لا. **فصار المفتاحُ `RESTRICT`** — وهو الأصحُّ
 *   معنًى أيضًا: **مَن اعتمدَ لا يُحذف**، فهويتُه أثرُ تدقيقٍ لا حقلُ راحة.
 *   وهو قريبُ فخِّ `AUTO_INCREMENT` الممنوعِ في CHECK — **عائلةٌ واحدة**.
 * ◆ صفرُ فقد · لا حذفَ مدمّر · ولا يُعاد تشغيلُ ما طُبِّق (كلُّ خطوةٍ تُجَسُّ قبلها).
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
if ($conn->connect_errno) { exit("تعذّر: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

/** مرساةُ الزمن: ما أُنشئ قبلها معفًى، وما بعدها ملزَم. لحظةُ سريانِ القيد. */
$ANCHOR = '2026-08-19 00:00:00';

function has_col(mysqli $c, string $t, string $col): bool {
    $st = $c->prepare("SELECT 1 FROM information_schema.COLUMNS
                        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
    $st->bind_param('ss', $t, $col); $st->execute();
    $n = $st->get_result()->num_rows; $st->close(); return $n > 0;
}
function has_chk(mysqli $c, string $t, string $name): bool {
    $st = $c->prepare("SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
                        WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=?
                          AND CONSTRAINT_NAME=? AND CONSTRAINT_TYPE='CHECK'");
    $st->bind_param('ss', $t, $name); $st->execute();
    $n = $st->get_result()->num_rows; $st->close(); return $n > 0;
}

/* الجداولُ المقيسةُ الآن — لا قائمةٌ مؤلَّفةٌ في الملف */
$tables = array();
$r = $conn->query("SELECT c.TABLE_NAME, c.COLUMN_NAME
                     FROM information_schema.COLUMNS c
                     JOIN information_schema.TABLES t
                       ON t.TABLE_SCHEMA=c.TABLE_SCHEMA AND t.TABLE_NAME=c.TABLE_NAME
                    WHERE c.TABLE_SCHEMA=DATABASE()
                      AND c.COLUMN_NAME IN ('approver_name','approved_by_name')
                      AND t.TABLE_TYPE='BASE TABLE'
                    ORDER BY c.TABLE_NAME");
while ($r && ($x = $r->fetch_assoc())) { $tables[$x['TABLE_NAME']] = $x['COLUMN_NAME']; }

echo "══ هويةُ المعتمِدِ بدل نصِّه ══\n";
echo "  الجداولُ المقيسة: " . count($tables) . "\n";

$addedCol = 0; $addedFk = 0; $fixedFk = 0; $addedChk = 0; $skipped = array(); $errs = 0;

foreach ($tables as $t => $col) {
    /* ما يحمل هويةً سلفًا لا يُمَسُّ — لا يُضاف عمودٌ ثانٍ لنفسِ المعنى */
    $idCol = null;
    foreach (array('approver_user_id', 'approved_by', 'approver_id') as $cand) {
        if (has_col($conn, $t, $cand)) { $idCol = $cand; break; }
    }

    if ($idCol === null) {
        if (!$conn->query("ALTER TABLE `{$t}`
              ADD `approver_user_id` INT(11) NULL DEFAULT NULL
                  COMMENT 'هويةُ المعتمِدِ من سلسلةِ الاعتماد — والاسمُ يُقرأ منها لا يُكتب'")) {
            echo "  ✘ {$t}: عمود — {$conn->error}\n"; $errs++; continue;
        }
        $addedCol++; $idCol = 'approver_user_id';

        /* `RESTRICT` لا `SET NULL`: الأخيرُ يغيّرُ العمودَ فيمنع القيدَ (انظر الترويسة)،
           والمعنى أصحُّ — مَن اعتمدَ لا يُحذف. */
        if ($conn->query("ALTER TABLE `{$t}`
              ADD CONSTRAINT `fk_{$t}_approver` FOREIGN KEY (`approver_user_id`)
                  REFERENCES `users`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT")) {
            $addedFk++;
        }
    } else {
        if ($idCol !== 'approver_user_id') { $skipped[$t] = "يحمل هويةً سلفًا (`{$idCol}`)"; }
    }

    /* ══ إصلاحُ مفتاحٍ سبقَ إنشاؤه بفعلٍ يغيّرُ العمود ══════════════════════
       تشغيلٌ سابقٌ لهذه الهجرةِ أنشأ 29 مفتاحًا بـ`SET NULL` قبلَ أن يُكشفَ
       الفخُّ — فتُصلَح هنا لا تُترك. **وهجرةٌ تُصلح أثرَ نفسِها أصدقُ من
       هجرةٍ ثانيةٍ تُخفيه.** ═══════════════════════════════════════════════ */
    $fkName = "fk_{$t}_approver";
    $st = $conn->prepare("SELECT DELETE_RULE, UPDATE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS
                            WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=? AND CONSTRAINT_NAME=?");
    $st->bind_param('ss', $t, $fkName); $st->execute();
    $fk = $st->get_result()->fetch_assoc(); $st->close();
    if ($fk && ($fk['DELETE_RULE'] !== 'RESTRICT' || $fk['UPDATE_RULE'] !== 'RESTRICT')) {
        if ($conn->query("ALTER TABLE `{$t}` DROP FOREIGN KEY `{$fkName}`")
            && $conn->query("ALTER TABLE `{$t}` ADD CONSTRAINT `{$fkName}`
                   FOREIGN KEY (`approver_user_id`) REFERENCES `users`(`id`)
                   ON DELETE RESTRICT ON UPDATE RESTRICT")) { $fixedFk++; }
        else { echo "  ✘ {$t}: تعذّر إصلاحُ المفتاح — {$conn->error}
"; $errs++; }
    }

    /* القيدُ الأماميُّ — بمرساةٍ زمنيةٍ كي لا يرفضَه الصفُّ القائم */
    $chk = "chk_{$t}_approver_identity";
    if (strlen($chk) > 64) { $chk = 'chk_apid_' . substr(md5($t), 0, 20); }
    if (has_chk($conn, $t, $chk)) { continue; }
    if (!has_col($conn, $t, 'created_at')) {
        /* ══ بلا `created_at` — والمرساةُ لا تلزمُ إن كان القائمُ كلُّه سليمًا ══
           المرساةُ حيلةٌ لإعفاءِ صفوفٍ مخالفة. فإن لم يوجد **صفٌّ واحدٌ** يحمل
           اسمًا بلا هوية، فالقيدُ العاري يُقبل. وهذا حالُ `timesheet_approvals`:
           85 من 85 تحمل `approved_by`. **فيُجَسُّ الواقعُ قبلَ الاستسلام.** */
        $bad = $conn->query("SELECT COUNT(*) FROM `{$t}`
                              WHERE `{$col}` IS NOT NULL AND TRIM(`{$col}`) <> ''
                                AND (`{$idCol}` IS NULL OR `{$idCol}` = 0)");
        $badN = $bad ? (int) $bad->fetch_row()[0] : -1;
        if ($badN !== 0) {
            $skipped[$t] = (isset($skipped[$t]) ? $skipped[$t] . ' · ' : '')
                . "بلا `created_at` و{$badN} صفًّا مخالفًا — لا مرساةَ ولا قيدٌ عارٍ";
            continue;
        }
        $sqlBare = "ALTER TABLE `{$t}` ADD CONSTRAINT `{$chk}` CHECK (
                        `{$col}` IS NULL OR TRIM(`{$col}`) = ''
                        OR (`{$idCol}` IS NOT NULL AND `{$idCol}` <> 0) )";
        if ($conn->query($sqlBare)) { $addedChk++; }
        else { echo "  ✘ {$t}: قيدٌ عارٍ — {$conn->error}
"; $errs++; }
        continue;
    }
    $sql = "ALTER TABLE `{$t}` ADD CONSTRAINT `{$chk}` CHECK (
                `created_at` < '{$ANCHOR}'
                OR `{$col}` IS NULL OR TRIM(`{$col}`) = ''
                OR `{$idCol}` IS NOT NULL )";
    if (!$conn->query($sql)) { echo "  ✘ {$t}: قيد — {$conn->error}\n"; $errs++; continue; }
    $addedChk++;
}

echo "  أُضيف عمودُ هوية: {$addedCol} · مفتاحٌ أجنبيّ: {$addedFk} · أُصلحَ مفتاحٌ سابقٌ إلى RESTRICT: {$fixedFk} · قيدٌ أماميّ: {$addedChk}
";
foreach ($skipped as $t => $why) { echo "  ◆ {$t}: {$why}\n"; }

/* ══ الشاهدُ الحيّ: القيدُ يرفض فعلًا ══════════════════════════════════════ */
$probe = null;
foreach ($tables as $t => $col) {
    if (has_chk($conn, $t, "chk_{$t}_approver_identity")) { $probe = array($t, $col); break; }
}
if ($probe) {
    list($t, $col) = $probe;
    $cols = array(); $r = $conn->query("SHOW COLUMNS FROM `{$t}`");
    while ($r && ($x = $r->fetch_assoc())) { $cols[$x['Field']] = $x; }
    $set = "`{$col}`='اختبارُ قيدٍ — يجب أن يُرفض', `created_at`=NOW()";
    foreach ($cols as $f => $d) {
        if ($d['Null'] === 'NO' && $d['Default'] === null && $d['Extra'] === ''
            && !in_array($f, array($col, 'created_at'), true)) {
            $v = (strpos($d['Type'], 'int') !== false || strpos($d['Type'], 'decimal') !== false) ? '0' : "''";
            $set .= ", `{$f}`={$v}";
        }
    }
    $ok = @$conn->query("INSERT INTO `{$t}` SET {$set}");
    if ($ok) {
        echo "  ✘ الشاهدُ السلبيُّ سقط: صفٌّ باسمٍ بلا هويةٍ **قُبل** في `{$t}` — القيدُ خامل\n";
        $conn->query("DELETE FROM `{$t}` WHERE `{$col}`='اختبارُ قيدٍ — يجب أن يُرفض'");
        $errs++;
    } else {
        $e = $conn->error;
        $mine = (stripos($e, 'chk_') !== false && stripos($e, 'approver') !== false)
             || stripos($e, "chk_{$t}_approver_identity") !== false;
        echo "  " . ($mine ? '✔' : '◆') . " الشاهدُ السلبيُّ على `{$t}`: رُفض — "
           . ($mine ? 'بقيدِنا بعينِه' : 'بقيدٍ آخرَ لا بقيدِنا (فلا يُحسب شاهدًا)') . "\n";
        if (!$mine) { echo "      نصُّ الرفض: " . mb_substr($e, 0, 120) . "\n"; }
    }
}

/* ══ المقامُ المُعلَن ══════════════════════════════════════════════════════ */
$q = $conn->query("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                    WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_TYPE='CHECK'
                      AND CONSTRAINT_NAME LIKE 'chk\\_%\\_approver\\_identity'");
$live = $q ? (int) $q->fetch_row()[0] : 0;
echo "  القيودُ الأماميةُ الحيّة: {$live} من " . count($tables) . "\n";
echo ($errs === 0 ? "✔ تمّ\n" : "✘ أخفقَ {$errs}\n");
exit($errs === 0 ? 0 : 1);
