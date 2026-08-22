<?php
/**
 * 2027_10_27_injfrd66_xc01_target_names.php
 *   XC-01 — أسماءُ البنودِ من الجدولِ المستهدف: اشتقاقًا وبملكيةٍ متحقَّقة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المعيار** (INJ-FRD-01 · XC-01): «القائمةُ المُصيَّرةُ مطابقةٌ للجدولِ
 *   المستهدفِ بندًا بندًا». وهذه الهجرةُ تُنفِّذ **محورَ الاسمِ** وحدَه —
 *   ومحورُ المجموعةِ مفصولٌ عنه لأنه قرارُ بنيةٍ لا تسمية (انظر أدناه).
 *
 * ◆ **الاسمُ يُرسَم من طبقةٍ رابعةٍ لا من `nav_items`** — قُرئ الترتيبُ من
 *   `includes/unified_nav.php`:
 *       ① variant (# أو ?)  → nav_items.label_ar
 *       ② nav_canonical status=APPROVED → canonical_ar   ← **الحاكمُ الأعلى**
 *       ③ nav_canonical_current → cur_label
 *       ④ وإلا → nav_items.label_ar
 *   وجولةٌ سابقةٌ حدَّثت `nav_items` وحدَه في تسعةِ مساراتٍ فبدا العملُ منجزًا
 *   وهو غيرُ ظاهرٍ البتة. **فالكتابةُ هنا في الطبقاتِ الثلاثِ معًا** وإلا
 *   اصطُنع توأمُ القديم.
 *
 * ◆ **ومن يملك التسمية؟** لا يُعاد اسمُ سطحٍ إلا إذا كان `owner_dept` هو
 *   إدارةَ الوثيقةِ التي تطلب التغيير — وسابقةُ هجرة `2027_10_06` قضت بأنَّ
 *   «وثيقةَ إدارةٍ **لا تملك** تسميةَ سطحٍ مشترك»، وأنَّ تسميتَها لبندٍ في
 *   مجموعتِها **وصفٌ لدورِه** لا اسمٌ كنسيٌّ له. فالأسطحُ الشخصيةُ المركزية
 *   (المهامُّ · الاعتماداتُ · البلاغات) **تُستثنى بالسابقةِ المقيَّدة**.
 *
 * ◆ **وما لا مالكَ معتمَدًا له يُحجَز**: صفٌّ بحالة PENDING لا يُعاد اسمُه —
 *   ذلك انتحالٌ لقرارِ مالكٍ لم يُتَّخذ. و`Reports/reports.php` أوضحُ مثال:
 *   الوثيقةُ تطلبه «تقاريرَ الموردين» للدورِ ٢ و«التقاريرَ والتحليلات» للدور
 *   ١٢ — **اسمانِ لمسارٍ واحد**، وهو بعينِه سببُ حالتِه PENDING_OWNER_MERGE.
 *
 * ◆ **وإعادةُ التسميةِ تُرسِّب كلَّ التزامٍ في كلِّ الجلسات** ما لم يُسجَّل
 *   الاسمُ القديمُ في `old_names` لصفٍّ حالتُه APPROVED — فبوابةُ
 *   `uxui_preserve_check --gate` في `pre-commit` تقارن كلَّ سايدبارٍ بلقطةِ
 *   `docs/uxui_live_positions.tsv` ولا تقبل تغيُّرَ اسمٍ بغيرِ ذلك.
 *
 * التشغيل:  php database/migrations/2027_10_27_injfrd66_xc01_target_names.php
 * الرجوع :  php database/migrations/2027_10_27_injfrd66_xc01_target_names.php --revert
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

$conn->query("CREATE TABLE IF NOT EXISTS `injfrd66_xc01_backup` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `route` VARCHAR(160) NOT NULL,
    `canonical_ar` VARCHAR(255) NULL,
    `old_names` TEXT NULL,
    `status` VARCHAR(40) NULL,
    `decision_state` VARCHAR(40) NULL,
    `application_state` VARCHAR(40) NULL,
    `decision_source` TEXT NULL,
    `items_json` TEXT NULL,
    `current_json` TEXT NULL,
    `at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

/* ── الرجوع ───────────────────────────────────────────────────────────── */
if (in_array('--revert', $argv, true)) {
    $n = 0;
    $res = $conn->query("SELECT * FROM `injfrd66_xc01_backup` ORDER BY id DESC");
    while ($res && ($r = $res->fetch_assoc())) {
        /* صفُّ تسويةٍ نزوليةٍ فقط (canonical_ar = NULL) — السجلُّ المعتمَدُ لم
           يُمسّ عند التطبيق، فلا يُمسُّ عند الرجوعِ وإلا مُحي اسمُه المعتمَد */
        if ($r['canonical_ar'] !== null) {
            $st = $conn->prepare("UPDATE `nav_canonical` SET `canonical_ar`=?, `old_names`=?, `status`=?,
                                     `decision_state`=?, `application_state`=?, `decision_source`=?
                                   WHERE LOWER(`route`)=LOWER(?)");
            $st->bind_param('sssssss', $r['canonical_ar'], $r['old_names'], $r['status'],
                $r['decision_state'], $r['application_state'], $r['decision_source'], $r['route']);
            $st->execute(); $n += $st->affected_rows; $st->close();
        }

        foreach (json_decode((string) $r['items_json'], true) ?: array() as $row) {
            $st = $conn->prepare("UPDATE `nav_items` SET `label_ar`=? WHERE `id`=?");
            $st->bind_param('si', $row['label_ar'], $row['id']); $st->execute(); $st->close();
        }
        foreach (json_decode((string) $r['current_json'], true) ?: array() as $row) {
            $st = $conn->prepare("UPDATE `nav_canonical_current` SET `cur_label`=? WHERE `role_id`=? AND `route`=?");
            $st->bind_param('sis', $row['cur_label'], $row['role_id'], $row['route']); $st->execute(); $st->close();
        }
    }
    $conn->query("TRUNCATE `injfrd66_xc01_backup`");
    echo "↺ أُعيد {$n} صفًّا كنسيًّا إلى اسمِه قبلَ التغيير\n";
    exit(0);
}

/* ── إدارةُ كلِّ وثيقةٍ — من يملك التسمية ───────────────────────────────── */
$DOC_DEPT = array(
    'INJ-SAL-ALIGN-01' => 'المبيعات والعقود',
    'INJ-SUP-ALIGN-01' => 'إدارة الموردين',
);
/* استثناءٌ مُعلَنٌ واحد: سطحٌ تملكه المبيعاتُ وتطلب الموردون اسمَه المؤسسي —
   والمتطلبانِ يتفقان على نزعِ الصيغةِ الفنية (SAL-15 «صفر بندِ تنقّلٍ بلفظٍ
   فني» · SUP-05 «باسمه المؤسسي لا الفني») فالمالكُ نفسُه يطلبه. */
$CROSS_OWNED = array('contracts/contract_coverage.php' => 'SAL-15 + SUP-05');

$rows = $conn->query(
    "SELECT t.doc_code, t.role_id, t.item_ar, t.route, t.group_ar,
            k.id AS kid, k.canonical_ar, k.old_names, k.owner_dept, k.status,
            k.decision_state, k.application_state, k.decision_source
       FROM `gov_target_nav` t
       LEFT JOIN `nav_canonical` k ON LOWER(k.route) = LOWER(t.route)
      ORDER BY t.role_id, t.group_no, t.item_no"
);

$renamed = 0; $already = 0; $exempt = array(); $heldRows = array();
$seen = array();

while ($rows && ($r = $rows->fetch_assoc())) {
    $routeLc = strtolower($r['route']);
    $label   = "{$r['item_ar']}  ({$r['route']})";

    if ($r['kid'] === null) {
        $exempt[] = "{$label} — لا صفَّ كنسيًّا: سطحٌ مشتركٌ اسمُه موحَّدٌ عبرَ الأدوار (سابقةُ 2027_10_06)";
        continue;
    }
    /* ── مطابقٌ سلفًا في السجلِّ المعتمَد — لكنَّ الطبقتَين الأدنى قد تتفرَّقان ──
       ◆ كشفَه الشاهدُ ②: `Governance/gov_dept_sup.php` اسمُه المعتمَدُ مطابقٌ
         للمستهدفِ و`cur_label` فيه «حوكمةُ الموردين». ولا يظهر ذلك في التصيير
         لأنَّ المعتمَدَ يغلب — **وهو بعينِه ما يجعله خطرًا**: طبقةٌ متفرّقةٌ
         صامتةٌ تنتظر أن يتغيَّر ترتيبُ الأسبقيةِ فتطفو. فالمطابقةُ تُسوّى
         نزولًا ولا تُترك. */
    if ((string) $r['canonical_ar'] === (string) $r['item_ar']) {
        $already++;
        $q = $conn->query("SELECT COUNT(*) c FROM `nav_canonical_current`
                            WHERE LOWER(route)=LOWER('" . $conn->real_escape_string($r['route']) . "')
                              AND cur_label <> '" . $conn->real_escape_string($r['item_ar']) . "'");
        $divCur = $q ? (int) $q->fetch_assoc()['c'] : 0;
        $q = $conn->query("SELECT COUNT(*) c FROM `nav_items`
                            WHERE LOWER(route)=LOWER('" . $conn->real_escape_string($r['route']) . "')
                              AND label_ar <> '" . $conn->real_escape_string($r['item_ar']) . "'");
        $divItm = $q ? (int) $q->fetch_assoc()['c'] : 0;
        if ($divCur === 0 && $divItm === 0) { continue; }

        $items = array(); $curs = array();
        $q = $conn->query("SELECT id, label_ar FROM `nav_items` WHERE LOWER(route)=LOWER('" . $conn->real_escape_string($r['route']) . "')");
        while ($q && ($x = $q->fetch_assoc())) { $items[] = $x; }
        $q = $conn->query("SELECT role_id, route, cur_label FROM `nav_canonical_current` WHERE LOWER(route)=LOWER('" . $conn->real_escape_string($r['route']) . "')");
        while ($q && ($x = $q->fetch_assoc())) { $curs[] = $x; }
        $ij = json_encode($items, JSON_UNESCAPED_UNICODE);
        $cj = json_encode($curs, JSON_UNESCAPED_UNICODE);
        /* canonical_ar = NULL ⇒ الرجوعُ يُعيد الطبقتَين ولا يمسُّ السجلَّ المعتمَد */
        $st = $conn->prepare("INSERT INTO `injfrd66_xc01_backup`
            (`route`,`canonical_ar`,`old_names`,`status`,`decision_state`,`application_state`,`decision_source`,`items_json`,`current_json`)
            VALUES (?,NULL,?,?,?,?,?,?,?)");
        $st->bind_param('ssssssss', $r['route'], $r['old_names'], $r['status'],
            $r['decision_state'], $r['application_state'], $r['decision_source'], $ij, $cj);
        $st->execute(); $st->close();

        $st = $conn->prepare("UPDATE `nav_canonical_current` SET `cur_label`=? WHERE LOWER(`route`)=LOWER(?)");
        $st->bind_param('ss', $r['item_ar'], $r['route']); $st->execute(); $st->close();
        $st = $conn->prepare("UPDATE `nav_items` SET `label_ar`=? WHERE LOWER(`route`)=LOWER(?)");
        $st->bind_param('ss', $r['item_ar'], $r['route']); $st->execute(); $st->close();
        printf("   ⇊ %-46s سُوِّيت الطبقتانِ الأدنى على «%s» (current:%d · items:%d)\n",
            $r['route'], $r['item_ar'], $divCur, $divItm);
        continue;
    }

    /* الملكية: إدارةُ الوثيقةِ أو استثناءٌ مُعلَن */
    $ownerDoc = $DOC_DEPT[$r['doc_code']] ?? null;
    $owns = ($ownerDoc !== null && $r['owner_dept'] === $ownerDoc) || isset($CROSS_OWNED[$routeLc]);
    if (!$owns) {
        $exempt[] = "{$label} — يملكه «{$r['owner_dept']}» لا «{$ownerDoc}»: وثيقةُ إدارةٍ لا تملك تسميةَ سطحٍ مشترك";
        continue;
    }
    if ($r['status'] !== 'APPROVED') {
        $heldRows[] = "{$label} — حالتُه {$r['status']}: إعادةُ تسميةِ صفٍّ غيرِ معتمَدٍ انتحالٌ لقرارِ مالك";
        continue;
    }
    if (isset($seen[$routeLc])) {
        $heldRows[] = "{$label} — المسارُ نفسُه طُلب باسمٍ آخرَ لدورٍ آخر: اسمانِ لمسارٍ واحد";
        continue;
    }
    $seen[$routeLc] = true;

    /* حفظُ الحالةِ قبلَ التغيير — بما فيها الطبقتانِ الأدنى */
    $items = array(); $curs = array();
    $q = $conn->query("SELECT id, label_ar FROM `nav_items` WHERE LOWER(route)=LOWER('" . $conn->real_escape_string($r['route']) . "')");
    while ($q && ($x = $q->fetch_assoc())) { $items[] = $x; }
    $q = $conn->query("SELECT role_id, route, cur_label FROM `nav_canonical_current` WHERE LOWER(route)=LOWER('" . $conn->real_escape_string($r['route']) . "')");
    while ($q && ($x = $q->fetch_assoc())) { $curs[] = $x; }

    $ij = json_encode($items, JSON_UNESCAPED_UNICODE);
    $cj = json_encode($curs, JSON_UNESCAPED_UNICODE);
    $st = $conn->prepare("INSERT INTO `injfrd66_xc01_backup`
        (`route`,`canonical_ar`,`old_names`,`status`,`decision_state`,`application_state`,`decision_source`,`items_json`,`current_json`)
        VALUES (?,?,?,?,?,?,?,?,?)");
    $st->bind_param('sssssssss', $r['route'], $r['canonical_ar'], $r['old_names'], $r['status'],
        $r['decision_state'], $r['application_state'], $r['decision_source'], $ij, $cj);
    $st->execute(); $st->close();

    /* ① السجلُّ المعتمَد — والاسمُ القديمُ يدخل old_names وإلا رسّبت البوابة */
    $oldNames = trim((string) $r['old_names']);
    $parts = array_filter(array_map('trim', preg_split('~[/·]+~u', $oldNames)));
    if (!in_array((string) $r['canonical_ar'], $parts, true)) { $parts[] = (string) $r['canonical_ar']; }
    $newOld = implode(' · ', $parts);
    $src = "INJ-FRD-01 · XC-01 — جدولُ التنقّلِ المستهدف gov_target_nav ({$r['doc_code']})";

    $st = $conn->prepare("UPDATE `nav_canonical`
                             SET `canonical_ar`=?, `old_names`=?, `status`='APPROVED',
                                 `decision_state`='APPROVED', `application_state`='DEPLOYED',
                                 `decision_source`=?
                           WHERE `id`=?");
    $st->bind_param('sssi', $r['item_ar'], $newOld, $src, $r['kid']);
    $st->execute(); $st->close();

    /* ② و③ الطبقتانِ الأدنى — وإلا اصطُنع توأمُ القديم */
    $st = $conn->prepare("UPDATE `nav_canonical_current` SET `cur_label`=? WHERE LOWER(`route`)=LOWER(?)");
    $st->bind_param('ss', $r['item_ar'], $r['route']); $st->execute(); $st->close();
    $st = $conn->prepare("UPDATE `nav_items` SET `label_ar`=? WHERE LOWER(`route`)=LOWER(?)");
    $st->bind_param('ss', $r['item_ar'], $r['route']); $st->execute(); $st->close();

    $renamed++;
    printf("   ✔ %-46s «%s» ← «%s»\n", $r['route'], $r['item_ar'], $r['canonical_ar']);
}

printf("\nالحصيلة: %d أُعيدت تسميتُه · %d مطابقٌ سلفًا · %d مُستثنًى · %d محجوزًا\n\n",
    $renamed, $already, count($exempt), count($heldRows));

if ($exempt) { echo "مُستثنًى بسابقةٍ مقيَّدةٍ في القاعدة:\n"; foreach ($exempt as $e) { echo "   ○ {$e}\n"; } echo "\n"; }
if ($heldRows) { echo "محجوزٌ بسببِه المكتوب:\n"; foreach ($heldRows as $h) { echo "   ⏸ {$h}\n"; } echo "\n"; }

ems_migration_recorded(__FILE__, $conn, 0);
echo "✔ اكتمل محورُ الاسم — والقيمُ السابقةُ في injfrd66_xc01_backup للرجوع\n";
