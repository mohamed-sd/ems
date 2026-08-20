<?php
/**
 * 2027_08_05_effect_map.php — خريطةُ الأثرِ · الخطوةُ الثانية (ثامنًا)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ نصُّ الطلب: «**اكتب خريطةَ الأثر** — لكلِّ واقعةٍ فيها: من يُنتجها · وأيُّ
 *   إداراتٍ تستهلك أثرَها · وأيُّ مستندٍ يُنتجه كلُّ مستهلِك»، و«**لا تُغلق
 *   مساحةٌ حتى تُنشئ واقعةً حقيقيةً فيها وتُثبت وصولَ أثرِها إلى كلِّ مستهلِك**».
 *   والقاعدةُ في سطر: **من يُنتج الواقعةَ يملك شاشتَها · ومن يستهلك أثرَها
 *   يملك مستندَه هو.**
 *
 * ◆ **والخريطةُ تُقاس من الحيِّ لا تُؤلَّف**: 63 زوجًا حيًّا (وحدةٌ · مفتاحُ حدث)
 *   من `ems_business_events` نفسِه — لا من قائمةٍ مكتوبةٍ تتقادم.
 *
 * ◆ **والمستهلِكُ ثلاثةُ أحوالٍ لا حالان** — والثالثُ هو الخطر:
 *   ① `MEASURED` — أثرُه واصلٌ **الآن**: صفوفٌ مرتبطةٌ بـ`correlation_id`.
 *   ② `DECLARED_ACTIVE` — مسجَّلٌ في `event_consumers` ونشِط، ولمّا يصل أثرٌ بعد
 *      (لأن الواقعةَ لم تقعْ بعدُ، لا لأن السلسلةَ مكسورة).
 *   ③ **`DECLARED_INACTIVE`** — **مسجَّلٌ ومعطَّل**: النظامُ يَعِد بمستهلِكٍ
 *      لا يستهلك. **وهذا مستهلِكٌ مقطوعٌ قائمٌ قبلَ أن نعزل شيئًا** — فلو
 *      أُغلقت مساحةٌ الآن ونُسب انقطاعُه إلى العزلِ لكان الاتهامُ خطأً،
 *      ولو مُرَّ عليه لظُنَّ العزلُ سليمًا وهو فوقَ عطبٍ سابق.
 *      **فالخريطةُ تُثبِّت الحالَ قبلَ العزلِ ليُنسب كلُّ عطبٍ إلى بابِه.**
 *
 * ◆ **ووحدةُ الإنتاجِ تُترجَم إلى إدارةٍ بجدولٍ صريحٍ مكتوبٍ هنا** — لأن
 *   `source_module` مفردةٌ تقنيةٌ و«المساحة» مفردةُ عمل، ولا يصحُّ الخلطُ
 *   بينهما ضمنًا. وما لا ترجمةَ له يُسجَّل `—` ولا يُخمَّن.
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

/* وحدةُ الإنتاجِ ⇒ المساحةُ المالكة — تسمياتُ المساحاتِ كما في لقطةِ الحالِ حرفًا */
$MOD2SPACE = array(
    'movement'    => 'ادارة التشغيل',
    'projects'    => 'ادارة التشغيل',
    'capacity'    => 'ادارة التشغيل',
    'workforce'   => 'القوى التشغيلية',
    'sales'       => 'ادارة المبيعات',
    'suppliers'   => 'ادارة الموردين',
    'procurement' => 'إدارة المشتريات',
    'finance'     => 'إدارة المالية',
    'treasury'    => 'إدارة المالية',
    'revenue'     => 'إدارة المالية',
    'assets'      => 'ادارة الاسطول',
    'maintenance' => 'ادارة الصيانة',
    'risk'        => 'إدارة المخاطر',
    'system'      => '—',
);

$conn->query("CREATE TABLE IF NOT EXISTS `gov_effect_map` (
    `id`             INT(11) NOT NULL AUTO_INCREMENT,
    `event_key`      VARCHAR(80)  NOT NULL,
    `producer_mod`   VARCHAR(40)  NOT NULL COMMENT 'وحدةُ الإنتاجِ التقنية',
    `producer_space` VARCHAR(80)  NOT NULL COMMENT 'المساحةُ المالكةُ للواقعة',
    `fact_rows`      INT(11)      NOT NULL DEFAULT 0 COMMENT 'وقائعُ حيةٌ بهذا المفتاح',
    `consumer_key`   VARCHAR(80)  NOT NULL COMMENT 'المستهلِك',
    `consumer_space` VARCHAR(80)  NOT NULL DEFAULT '—',
    `consumer_doc`   VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'المستندُ الذي يُنتجه المستهلِك',
    `evidence`       ENUM('MEASURED','DECLARED_ACTIVE','DECLARED_INACTIVE') NOT NULL,
    `evidence_n`     INT(11)      NOT NULL DEFAULT 0 COMMENT 'صفوفُ الأثرِ الواصلةِ فعلًا',
    `note`           VARCHAR(255) NOT NULL DEFAULT '',
    `updated_at`     DATETIME NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_gem` (`event_key`, `consumer_key`),
    KEY `ix_gem_prod` (`producer_space`),
    KEY `ix_gem_ev` (`evidence`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='ثامنًا-٢ خريطةُ الأثر — من يُنتج · من يستهلك · وأيُّ مستندٍ يُنتجه'");

$conn->query("DELETE FROM gov_effect_map");

$ins = $conn->prepare(
    "INSERT INTO gov_effect_map
        (event_key, producer_mod, producer_space, fact_rows, consumer_key, consumer_space,
         consumer_doc, evidence, evidence_n, note)
     VALUES (?,?,?,?,?,?,?,?,?,?)
     ON DUPLICATE KEY UPDATE
        fact_rows=VALUES(fact_rows), evidence=VALUES(evidence),
        evidence_n=VALUES(evidence_n), note=VALUES(note)"
);
if (!$ins) { exit("تعذّر التحضير: {$conn->error}\n"); }

/* ① الوقائعُ الحيةُ ومنتِجوها */
$facts = array();
$r = $conn->query("SELECT source_module, event_key, COUNT(*) n
                     FROM ems_business_events GROUP BY source_module, event_key");
while ($r && ($x = $r->fetch_assoc())) {
    $facts[$x['event_key']][] = array('mod' => (string) $x['source_module'], 'n' => (int) $x['n']);
}

/* ② الأثرُ المقيسُ: إسقاطٌ ماليٌّ مرتبطٌ بـcorrelation_id */
$measured = array();
$r = $conn->query("SELECT e.event_key, COUNT(DISTINCT f.id) n
                     FROM ems_business_events e
                     JOIN fin_financial_events f ON f.correlation_id = e.correlation_id
                    GROUP BY e.event_key");
while ($r && ($x = $r->fetch_assoc())) { $measured[$x['event_key']] = (int) $x['n']; }

/* ③ المستهلِكون المُعلَنون */
$declared = array();
$r = $conn->query("SELECT event_name, consumer_key, produces, active FROM event_consumers");
while ($r && ($x = $r->fetch_assoc())) {
    $declared[$x['event_name']][] = array(
        'k' => (string) $x['consumer_key'],
        'p' => (string) $x['produces'],
        'a' => (int) $x['active'],
    );
}

$rows = 0; $mN = 0; $daN = 0; $diN = 0;
foreach ($facts as $key => $producers) {
    foreach ($producers as $pr) {
        $mod   = $pr['mod'];
        $space = isset($MOD2SPACE[$mod]) ? $MOD2SPACE[$mod] : '—';
        $n     = $pr['n'];

        /* المستهلِكُ المقيس: الماليةُ تُنتج «واقعةً ماليةً» من الحقيقة */
        if (isset($measured[$key])) {
            $ck = 'fin_projection'; $cs = 'إدارة المالية'; $cd = 'fin_financial_events — واقعةٌ ماليةٌ مرتبطةٌ بالحقيقة';
            $ev = 'MEASURED'; $en = $measured[$key]; $note = 'أثرٌ واصلٌ الآن — مقيسٌ بربطِ correlation_id لا بقراءةِ شيفرة';
            $ins->bind_param('sssissssis', $key, $mod, $space, $n, $ck, $cs, $cd, $ev, $en, $note);
            if ($ins->execute()) { $rows++; $mN++; }
        }

        /* المستهلِكون المُعلَنون — والمعطَّلُ يُعلَن معطَّلًا لا يُطوى */
        if (isset($declared[$key])) {
            foreach ($declared[$key] as $d) {
                $ck = $d['k'];
                $cs = ($ck === 'governance_watch') ? 'إدارة المخاطر' : '—';
                $cd = ($d['p'] === 'notify') ? 'إشعارُ حوكمةٍ — لا مستندَ مالي'
                    : ($d['p'] === 'write' ? 'كتابةٌ في دفترِ المستهلِك' : (string) $d['p']);
                $ev = $d['a'] ? 'DECLARED_ACTIVE' : 'DECLARED_INACTIVE';
                $en = 0;
                $note = $d['a'] ? 'مسجَّلٌ ونشِطٌ — ولمّا يُقَسْ له أثرٌ واصل'
                                : '**مسجَّلٌ ومعطَّل: النظامُ يَعِد بمستهلِكٍ لا يستهلك — قطعٌ قائمٌ قبلَ أيِّ عزل**';
                $ins->bind_param('sssissssis', $key, $mod, $space, $n, $ck, $cs, $cd, $ev, $en, $note);
                if ($ins->execute()) { $rows++; if ($d['a']) { $daN++; } else { $diN++; } }
            }
        }
    }
}
$ins->close();

echo "══ خريطةُ الأثر — الخطوةُ الثانية ══\n";
echo "  أزواجُ (واقعة × مستهلِك) المسجَّلة: {$rows}\n";
echo "    · أثرٌ **مقيسٌ واصل**: {$mN}\n";
echo "    · مُعلَنٌ نشِطٌ بلا أثرٍ مقيسٍ بعد: {$daN}\n";
echo "    · **مُعلَنٌ ومعطَّل: {$diN}** ← مستهلِكٌ مقطوعٌ **قائمٌ قبلَ العزل**\n";

$q = $conn->query("SELECT COUNT(DISTINCT event_key) FROM gov_effect_map");
echo "  وقائعُ مغطّاةٌ بالخريطة: " . ($q ? $q->fetch_row()[0] : 0) . "\n";

echo "\n  ┌ الوقائعُ بحسبِ المساحةِ المنتِجة (المقامُ الذي يلزم إثباتُه عند إغلاقِها)\n";
$q = $conn->query("SELECT producer_space, COUNT(DISTINCT event_key) k, SUM(evidence='MEASURED') m,
                          SUM(evidence='DECLARED_INACTIVE') d
                     FROM gov_effect_map GROUP BY producer_space ORDER BY k DESC");
while ($q && ($x = $q->fetch_assoc())) {
    printf("  │ %-22s وقائع=%-3d أثرٌ مقيس=%-3d معطَّل=%d\n",
           $x['producer_space'], $x['k'], $x['m'], $x['d']);
}
echo "  └────────────────────────────────────────────────────\n";

if ($diN > 0) {
    echo "\n  ◆ **قبلَ أن يُغلَق شيء**: {$diN} مستهلِكًا مُعلَنًا معطَّلًا. وهؤلاء\n";
    echo "    **قطعٌ سابقٌ للعزل** — فلا يُنسب إليه، ولا يُمرُّ عليه فيُظنَّ العزلُ\n";
    echo "    سليمًا وهو فوقَ عطب. وأثقلُها:\n";
    $q = $conn->query("SELECT event_key, consumer_key FROM gov_effect_map
                        WHERE evidence='DECLARED_INACTIVE' ORDER BY event_key LIMIT 8");
    while ($q && ($x = $q->fetch_assoc())) { echo "      · {$x['event_key']} ⇐ {$x['consumer_key']}\n"; }
}
exit($rows > 0 ? 0 : 1);
