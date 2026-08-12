<?php
/**
 * 2027_03_16 — دوريةُ تسعيرٍ **يوميةٌ**: قرارُ المالك 2026-08-12
 * ═══════════════════════════════════════════════════════════════════════════
 * **نصُّ القرار** (جوابًا على «ما مصدرُ مؤشراتِ الأسعار؟»):
 *   «مصدرُها **التحديثُ الوقتيُّ للأسعارِ من الإدارةِ المالية** لكلِّ عمليةٍ
 *    بشكلِ تسعيرٍ لكلِّ معاملةٍ، مع **إمكانيةِ تحديدِ السعرِ لليومِ بشكلٍ يوميّ**.»
 *
 * ⇒ فلا مؤشرَ خارجيًّا منشورًا يُقرأ منه (وهو ما كان المحرِّكُ يفترضه)، بل
 *   الإدارةُ الماليةُ **هي** المصدر. وهذا يلزمه دوريةٌ لم تكن موجودة: الدوريّاتُ
 *   كانت `monthly · quarterly · semiannual · annual` — **ولا يوميّ**، فلم يكن
 *   ممكنًا أصلًا التعبيرُ عن «سعرِ اليوم».
 *
 * ── وحكمُ السريانِ في اليوميِّ يختلف عمدًا، ويُدوَّن هنا لا في ذاكرةِ أحد ──────
 * الدوريّاتُ الخشنةُ تُراجَع فترةً **ويسري أثرُها بعدها** (`nextPeriodStart`) —
 * لئلا تُعاد فوترةُ فترةٍ جرت وقائعُها بسعرِها القائمِ يومَها. ولو طُبِّق ذلك على
 * اليوميِّ لصار «سعرُ اليوم» ساريًا **غدًا** — وهو نقضُ القرارِ نصًّا.
 * فسريانُ اليوميِّ **يومُه نفسُه**، و«لا رجعية» تبقى محفوظةً بآليةٍ أخرى:
 * `effectivePrice()` يختار آخرَ مراجعةٍ معتمَدةٍ سريانُها ≤ يومِ الواقعة، فواقعةُ
 * أمسِ تبقى بسعرِ أمس ولو سُجِّل اليومَ سعرٌ جديد.
 *
 * ◆ ولا يُمَسُّ صفٌّ قائم: الإضافةُ إلى ENUM تُوسّع المدى ولا تُبدّل قيمةً.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
require_once dirname(__DIR__, 2) . '/includes/env.php';

$db = @new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'),
                  ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($db->connect_errno) { fwrite(STDERR, 'اتصال: ' . $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');

/* ── ① توسيعُ ENUM الدورية ─────────────────────────────────────────────────── */
$cur = '';
$r = $db->query("SHOW COLUMNS FROM contract_price_terms LIKE 'periodicity'");
if ($r && ($x = $r->fetch_assoc())) { $cur = (string) $x['Type']; }
if ($cur === '') { fwrite(STDERR, "لا عمودَ periodicity\n"); exit(1); }

if (strpos($cur, "'daily'") !== false) {
    echo "── ① 'daily' موجودةٌ سلفًا في المدى — لا تغيير\n";
} else {
    /* ◆ **الترتيبُ مقصود**: `daily` أولًا فالمدى يقرأ من الأدقِّ إلى الأخشن،
         ولا يُعتمد على الترتيبِ في أيِّ مقارنةٍ — القيمُ تُقارَن بنصِّها. */
    $ok = $db->query("ALTER TABLE contract_price_terms
        MODIFY COLUMN periodicity ENUM('daily','monthly','quarterly','semiannual','annual')
        NOT NULL DEFAULT 'quarterly'
        COMMENT 'دوريةُ المراجعة — daily سريانُه يومُه نفسُه بقرارِ المالك 2026-08-12'");
    if ($ok === false) { fwrite(STDERR, '① فشل: ' . $db->error . "\n"); exit(1); }
    echo "── ① أُضيفت 'daily' إلى مدى الدورية\n";
}

/* ── ② جسٌّ: المدى يقبل اليوميَّ ويردُّ ما خارجَه ───────────────────────────── */
$term = $db->query("SELECT id, periodicity FROM contract_price_terms LIMIT 1");
$term = $term ? $term->fetch_assoc() : null;
if ($term === null) {
    echo "── ② لا بندَ تسعيرٍ قائمًا — الجسُّ لا يُشغَّل، وهذا يُعلَن لا يُخفى\n";
} else {
    $tid = (int) $term['id'];
    $was = (string) $term['periodicity'];
    /* الموجب: اليوميُّ يُقبَل */
    $db->query("UPDATE contract_price_terms SET periodicity = 'daily' WHERE id = {$tid}");
    $got = $db->query("SELECT periodicity p FROM contract_price_terms WHERE id = {$tid}");
    $got = $got ? (string) $got->fetch_assoc()['p'] : '';
    $posOk = ($got === 'daily');
    /* السالب: قيمةٌ خارجَ المدى — و`sql_mode` خاويةٌ فالـENUM يبتلعها '' صامتًا،
       فالحكمُ على **القيمةِ الناتجةِ** لا على رمزِ الخطإ. */
    $db->query("UPDATE contract_price_terms SET periodicity = 'hourly' WHERE id = {$tid}");
    $bad = $db->query("SELECT periodicity p FROM contract_price_terms WHERE id = {$tid}");
    $bad = $bad ? (string) $bad->fetch_assoc()['p'] : 'X';
    $negOk = ($bad !== 'hourly');
    /* رَدُّ ما كان */
    $db->query("UPDATE contract_price_terms SET periodicity = '"
               . $db->real_escape_string($was) . "' WHERE id = {$tid}");
    $back = $db->query("SELECT periodicity p FROM contract_price_terms WHERE id = {$tid}");
    $back = $back ? (string) $back->fetch_assoc()['p'] : '';
    echo "── ② اليوميُّ يُقبَل: " . ($posOk ? '✔' : '✘ (صار «' . $got . '»)') . "\n";
    echo "── ② وما خارجَ المدى يُرفَض: " . ($negOk ? '✔ (صار «' . $bad . '»)' : '✘ مرَّ «hourly»') . "\n";
    echo "── ② ورُدَّ البندُ إلى «" . $back . "» (كان «" . $was . "»)\n";
    if (!$posOk || !$negOk || $back !== $was) {
        fwrite(STDERR, "الجسُّ لم يكتمل — راجِع قبل الالتزام\n"); exit(1);
    }
}

echo "\n✅ «سعرُ اليوم» صار قابلًا للتعبيرِ عنه — وسريانُه يومُه نفسُه بقرارِ المالك.\n";
exit(0);
