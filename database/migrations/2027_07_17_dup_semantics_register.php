<?php
/**
 * 2027_07_17_dup_semantics_register.php — البعدُ الخامسُ الشقُّ الثاني (ف١٥-٣)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ نصُّ المواصفة (ف١٥-٤): «◆ صفرُ ازدواجِ معنًى — لا مسارَين مختلفَين يؤديان
 *   الوظيفةَ نفسَها · **٥٩ زوجًا للمراجعةِ · ٧ مرجَّحُ الدمج** · ◐ بانتظارِ
 *   الحكمِ البشريّ».
 *
 * ◆ ونصُّها في ف١٥-٣: «الاسمُ المختلفُ لا ينفي وحدةَ الوظيفة… **والحكمُ البشريُّ
 *   ثلاثةٌ: الوظيفةُ نفسُها / زاويةُ نظرٍ مختلفة / وظيفتان مستقلتان**».
 *
 * ◆ فيُنشأ سجلٌّ يحمل **الترجيحَ الآليَّ والحكمَ البشريَّ في عمودَين منفصلَين** —
 *   وخلطُهما هو عينُ ما تحاربه الوثيقة: ترجيحٌ آليٌّ يُقرأ حكمًا محسومًا.
 * ◆ **وقيدٌ يمنع إعلانَ قرارٍ بلا حكمٍ بشريّ**: صفٌّ بقرارٍ وبلا `human_verdict`
 *   مرفوضٌ بنيويًّا — فلا يُغلق البعدُ الخامسُ بترجيحِ آلة.
 * ◆ والمصدرُ: ورقةُ «فحص ازدواج المعنى» في `UXUI_MASTER_AUDIT` — 59 زوجًا من
 *   64,261 زوجًا محتمَلًا، بدرجةِ تشابهٍ محسوبةٍ ومفاهيمَ مشترَكةٍ معلَنة.
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

$ok = $conn->query("CREATE TABLE IF NOT EXISTS `gov_dup_semantics` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `similarity` DECIMAL(5,3) NOT NULL COMMENT 'درجةُ التشابهِ المحسوبة — مؤشِّرٌ لا حكم',
  `route_a` VARCHAR(190) NOT NULL,
  `label_a` VARCHAR(190) NOT NULL,
  `route_b` VARCHAR(190) NOT NULL,
  `label_b` VARCHAR(190) NOT NULL,
  `owner_dept` VARCHAR(120) DEFAULT NULL,
  `nature` VARCHAR(60) DEFAULT NULL,
  `shared_concepts` VARCHAR(190) DEFAULT NULL,
  `machine_hint` VARCHAR(190) DEFAULT NULL
      COMMENT 'الترجيحُ الآليّ — **اقتراحٌ لا حكم** (ف١٥-١ بند ٤)',
  `human_verdict` ENUM('SAME_FUNCTION','DIFFERENT_ANGLE','INDEPENDENT') DEFAULT NULL
      COMMENT 'الحكمُ البشريُّ الثلاثيُّ — ولا يُملأ آليًّا بحال',
  `human_by` VARCHAR(120) DEFAULT NULL,
  `decision` VARCHAR(190) DEFAULT NULL COMMENT 'دمجٌ · منظرٌ محفوظ · إبقاءٌ منفصلًا',
  `decided_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pair` (`route_a`, `route_b`),
  KEY `ix_verdict` (`human_verdict`),
  /* ◆ **قرارٌ بلا حكمٍ بشريٍّ مرفوضٌ بقيد** — فلا يُغلق البعدُ الخامسُ
       بترجيحِ آلةٍ مهما بدا مقنعًا (نصُّ ف١٥-١ بند ٤). */
  CONSTRAINT `chk_dupsem_human` CHECK (
      `decision` IS NULL OR (`human_verdict` IS NOT NULL AND `human_by` IS NOT NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='أزواجُ ازدواجِ المعنى — الترجيحُ الآليُّ والحكمُ البشريُّ عمودانِ لا عمود'");
if (!$ok) { exit("✗ {$conn->error}\n"); }

/* ── الحمولةُ من الوثيقةِ الثالثة — تُقرأ ولا تُؤلَّف ─────────────────────── */
$src = $ROOT . '/docs/uxui/dup_semantics_59.tsv';
if (!is_file($src)) {
    echo "◆ لا ملفَّ حمولةٍ في {$src} — يُنشأ الجدولُ فارغًا ويُملأ بأداةِ الاستيراد\n";
} else {
    $st = $conn->prepare("INSERT IGNORE INTO gov_dup_semantics
        (similarity, route_a, label_a, route_b, label_b, owner_dept, nature, shared_concepts, machine_hint)
        VALUES (?,?,?,?,?,?,?,?,?)");
    $n = 0;
    foreach (file($src, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $c = explode("\t", $line);
        if (count($c) < 9) { continue; }
        $sim = (float) $c[0];
        $st->bind_param('dssssssss', $sim, $c[1], $c[2], $c[3], $c[4], $c[5], $c[6], $c[7], $c[8]);
        if ($st->execute()) { $n++; }
    }
    echo "  استُورد: {$n} زوجًا\n";
}

$tot = (int) $conn->query("SELECT COUNT(*) c FROM gov_dup_semantics")->fetch_assoc()['c'];
$merge = (int) $conn->query("SELECT COUNT(*) c FROM gov_dup_semantics WHERE machine_hint LIKE '%دمج%'")->fetch_assoc()['c'];
$judged = (int) $conn->query("SELECT COUNT(*) c FROM gov_dup_semantics WHERE human_verdict IS NOT NULL")->fetch_assoc()['c'];
$chk = (int) $conn->query("SELECT COUNT(*) c FROM information_schema.CHECK_CONSTRAINTS
                            WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='gov_dup_semantics'")->fetch_assoc()['c'];
echo "════ سجلُّ ازدواجِ المعنى ════\n";
echo "  أزواج: {$tot} · مرجَّحُ الدمجِ آليًّا: {$merge} · محكومٌ بشريًّا: {$judged} · قيودُ CHECK: {$chk}\n";
echo "◆ الترجيحُ الآليُّ **لا يُغلق البعدَ الخامس** — والقيدُ يرفض قرارًا بلا حكمٍ بشريّ\n";
