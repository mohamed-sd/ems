<?php
/**
 * 2027_09_08_component_version_and_action_finality.php
 *   بصمةُ الإصدار · وحالةُ الأفعالِ النهائية · وقرارُ الرسائلِ الميتة
 *   — INJ-FIX-01 · GAP-26 و GAP-32
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **GAP-26**: «بصمةُ الشجرةِ العاملةِ ≠ آخرِ إصدارٍ مسجَّل». وسُجِّل `ux-1.5.0`
 *   في 10:37 ثم مضت عشرُ التزاماتٍ فانحرفت البصمةُ — **وهذا ما يجب أن يحدث**:
 *   السجلُّ يقيس الانحرافَ ولا يمنعه. فيُسجَّل إصدارٌ جديدٌ بقائمةِ ملفاتِه،
 *   ويُوسَم سابقُه `SUPERSEDED` — **فلا يبقى إصداران يدّعيان الحاضر**.
 *
 * ◆ **GAP-32 ①** — ستٌّ وعشرون رسالةً ميتة: `CONSUMER_RETIRED` خمسٌ وعشرون
 *   و`NO_SUB` واحدة. **وكلتاهما غيرُ قابلةٍ للتسليمِ بنيويًّا**: مستهلكٌ متقاعدٌ
 *   لا يستقبل، واشتراكٌ غيرُ موجودٍ لا يوجَّه إليه. فالقرارُ **مكتوبٌ لكلٍّ**
 *   ولا تُترك «بلا قرار». ولا تُحذف: الرسالةُ الميتةُ شاهدٌ على ما جرى.
 *
 * ◆ **GAP-32 ②** — مئةٌ وثلاثَ عشرةَ فعلًا بحارسٍ معلَّق. ويُحسم منها **الواحدُ
 *   والثلاثون التي شاشتُها غيرُ مبنيةٍ** (`state = declared_unbuilt`) إلى
 *   `n_a` بحجّةٍ لا تُردّ: **لا يُتحقَّق حارسُ فعلٍ على شاشةٍ لا توجد**.
 *   ◆ **والاثنتان والثمانون الباقيةُ لا تُقلَب بجرَّةِ قلم** — تحقُّقُ الحارسِ
 *     عملٌ لكلِّ فعلٍ على حِدة، وقلبُها آليًّا **يُنتج إغلاقًا كاذبًا** وهو أسوأُ
 *     من بقائها معلَّقة. فتبقى مُعلَنةً بمقيسِها.
 *
 * التشغيل:  php database/migrations/2027_09_08_component_version_and_action_finality.php
 * الرجوع :  php database/migrations/2027_09_08_component_version_and_action_finality.php --revert
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$TAG = 'ux-1.6.0';

if (in_array('--revert', $argv, true)) {
    $conn->query("DELETE FROM `gov_component_versions` WHERE `version_tag` = '{$TAG}'");
    echo "↺ حُذف إصدارُ {$TAG} ({$conn->affected_rows})\n";
    $conn->query("UPDATE `gov_component_versions` SET `state`='DRAFT'
                   WHERE `version_tag`='ux-1.5.0' AND `state`='SUPERSEDED'");
    echo "↺ أُعيد ux-1.5.0 إلى DRAFT\n";
    $conn->query("DROP TABLE IF EXISTS `gov_dead_letter_rulings`");
    $conn->query("UPDATE `nav09_action_map` SET `guard_verified`='pending', `guard_evidence`=NULL
                   WHERE `guard_verified`='n_a' AND `guard_evidence` LIKE 'GAP-32%'");
    echo "↺ أُعيد {$conn->affected_rows} فعلًا إلى pending\n";
    exit(0);
}

/* ══ ① GAP-26 — بصمةُ الشجرةِ الآن ════════════════════════════════════════ */
$q = $conn->query("SELECT `files_json`, `fingerprint`, `version_tag` FROM `gov_component_versions`
                    ORDER BY `id` DESC LIMIT 1");
$prev = $q ? $q->fetch_assoc() : null;
$files = $prev ? array_keys((array) json_decode((string) $prev['files_json'], true)) : array();
if (!$files) { exit("✘ الإصدارُ السابقُ بلا قائمةِ ملفات — لا تُقاس البصمةُ على مجهول\n"); }

$now = array(); $missing = array();
foreach ($files as $rel) {
    $abs = $ROOT . '/' . $rel;
    if (!is_file($abs)) { $missing[] = $rel; continue; }
    $now[$rel] = hash('sha256', (string) file_get_contents($abs));
}
if ($missing) { echo "  ⚠ ملفاتٌ في القائمةِ لا وجودَ لها: " . implode(' · ', $missing) . "\n"; }
ksort($now);
$fp = hash('sha256', json_encode($now));

echo "① بصمةُ الشجرةِ الآن : {$fp}\n";
echo "   آخرُ إصدارٍ مسجَّل  : {$prev['version_tag']} = {$prev['fingerprint']}\n";
if ($fp === $prev['fingerprint']) {
    echo "   ✔ مطابقةٌ — لا يُسجَّل إصدارٌ بلا تغيير\n";
} else {
    $changed = array();
    $old = (array) json_decode((string) $prev['files_json'], true);
    foreach ($now as $k => $v) { if (!isset($old[$k]) || $old[$k] !== $v) { $changed[] = $k; } }
    echo "   ◆ **انحرفت** — ملفاتٌ تغيّرت: " . count($changed) . "\n";
    foreach (array_slice($changed, 0, 6) as $ch) { echo "      · {$ch}\n"; }

    $note = 'INJ-FIX-01 · GAP-26 — إصدارٌ بعدَ جولةِ إغلاقِ الفجوات (GAP-03·08·09·12·19·20·21·22·28)'
          . ' · وسابقُه SUPERSEDED فلا يدّعي إصداران الحاضرَ معًا';
    $js = json_encode($now, JSON_UNESCAPED_SLASHES);
    $st = $conn->prepare("INSERT INTO `gov_component_versions`
            (`version_tag`,`fingerprint`,`files_json`,`state`,`note`,`created_at`)
            VALUES (?,?,?, 'DRAFT', ?, NOW())");
    $st->bind_param('ssss', $TAG, $fp, $js, $note);
    if (!$st->execute()) { exit("✘ تعذّر التسجيل: {$st->error}\n"); }
    $st->close();
    $conn->query("UPDATE `gov_component_versions` SET `state`='SUPERSEDED'
                   WHERE `version_tag` <> '{$TAG}' AND `state`='DRAFT'");
    echo "   ✔ سُجِّل {$TAG} · ووُسم {$conn->affected_rows} سابقًا SUPERSEDED\n";
}

/* ══ ② GAP-32 ① — قرارٌ مكتوبٌ لكلِّ رسالةٍ ميتة ══════════════════════════ */
$conn->query("CREATE TABLE IF NOT EXISTS `gov_dead_letter_rulings` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `fail_code`   VARCHAR(32)  NOT NULL,
    `messages`    INT UNSIGNED NOT NULL,
    `ruling`      VARCHAR(32)  NOT NULL COMMENT 'UNDELIVERABLE_BY_DESIGN | RETRY | INVESTIGATE',
    `owner_role`  VARCHAR(64)  NOT NULL,
    `reason`      VARCHAR(400) NOT NULL,
    `decided_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_fail` (`fail_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='GAP-32 — قرارٌ ومالكٌ وسببٌ لكلِّ صنفِ رسالةٍ ميتة'");

$RULE = array(
    'CONSUMER_RETIRED' => array('UNDELIVERABLE_BY_DESIGN', 'هندسة النظم',
        'المستهلكُ متقاعدٌ فلا يستقبل — والرسالةُ غيرُ قابلةٍ للتسليمِ بنيويًّا لا عرَضًا. تبقى شاهدًا ولا تُحذف ولا يُعاد تسليمُها'),
    'NO_SUB' => array('UNDELIVERABLE_BY_DESIGN', 'هندسة النظم',
        'لا اشتراكَ لهذا الحدثِ وقتَ نشرِه فلا وجهةَ يوجَّه إليها — وإعادةُ التسليمِ بلا مشترِكٍ عبثٌ. تبقى شاهدًا'),
);
$st = $conn->prepare("INSERT INTO `gov_dead_letter_rulings`
        (`fail_code`,`messages`,`ruling`,`owner_role`,`reason`) VALUES (?,?,?,?,?)
        ON DUPLICATE KEY UPDATE `messages`=VALUES(`messages`), `ruling`=VALUES(`ruling`),
            `owner_role`=VALUES(`owner_role`), `reason`=VALUES(`reason`)");
$q = $conn->query("SELECT COALESCE(NULLIF(`fail_code`,''),'(فارغ)') fc, COUNT(*) n
                     FROM `ems_event_deliveries` WHERE `state`='dlq' GROUP BY fc");
$ruled = 0; $unruled = array();
while ($q && $x = $q->fetch_assoc()) {
    if (!isset($RULE[$x['fc']])) { $unruled[] = $x['fc'] . '=' . $x['n']; continue; }
    $r = $RULE[$x['fc']];
    $n = (int) $x['n'];
    $st->bind_param('sisss', $x['fc'], $n, $r[0], $r[1], $r[2]);
    if ($st->execute()) { $ruled += $n; }
}
$st->close();
$dlq = (int) $conn->query("SELECT COUNT(*) FROM `ems_event_deliveries` WHERE `state`='dlq'")->fetch_row()[0];
echo "② الرسائلُ الميتة: {$dlq} · محكومةٌ بقرارٍ ومالكٍ وسبب: {$ruled}\n";
if ($unruled) { echo "   ◆ **بلا حكمٍ بعد**: " . implode(' · ', $unruled) . "\n"; }

/* ══ ③ GAP-32 ② — حالةٌ نهائيةٌ لِما شاشتُه غيرُ مبنية ══════════════════ */
$ev = 'GAP-32 · لا يُتحقَّق حارسُ فعلٍ على شاشةٍ غيرِ مبنية (state=declared_unbuilt) — '
    . 'ويعود إلى pending فورَ بنائِها';
$st = $conn->prepare("UPDATE `nav09_action_map`
                         SET `guard_verified`='n_a', `guard_evidence`=?
                       WHERE `guard_verified`='pending' AND `state`='declared_unbuilt'");
$st->bind_param('s', $ev);
$st->execute();
echo "③ حُسم بـn_a (شاشةٌ غيرُ مبنية): {$st->affected_rows} فعلًا\n";
$st->close();

$q = $conn->query("SELECT `guard_verified`, COUNT(*) n FROM `nav09_action_map`
                    GROUP BY `guard_verified` ORDER BY n DESC");
$tot = 0; $pend = 0;
echo "───────────────────────────────────────────────────────────────\n";
while ($q && $x = $q->fetch_assoc()) {
    printf("   %-10s %d\n", $x['guard_verified'], $x['n']);
    $tot += (int) $x['n'];
    if ($x['guard_verified'] === 'pending') { $pend = (int) $x['n']; }
}
printf("④ المقام %d · **معلَّقٌ بعد: %d** — ولا تُقلَب آليًّا: تحقُّقُ الحارسِ عملٌ لكلِّ فعلٍ\n", $tot, $pend);
echo "   على حِدة، وقلبُها بجرَّةِ قلمٍ **يُنتج إغلاقًا كاذبًا** أسوأَ من التعليق.\n";
