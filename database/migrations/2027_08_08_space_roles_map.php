<?php
/**
 * 2027_08_08_space_roles_map.php — الدورُ ⇄ مساحةُ العمل (شرطُ رابعًا)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ نصُّ الطلب (رابعًا): «المنعُ مقيَّدٌ بسياقِ المساحة: `user + active_scope +
 *   route` لا `user + route`. فمن يملك الشاشةَ في إدارتِها المالكةِ يبلغها
 *   بتبديلِ المساحة، **ومنعُها عالميًّا يكسر صلاحيةً مشروعة**».
 *
 * ◆ **ولا وجودَ لـ`active_scope` في النظامِ اليوم**: البحثُ في الشيفرةِ الحيةِ
 *   عن `active_scope`/`workspace_scope`/مبدِّلِ مساحةٍ أعطى **صفرًا**. فالمنعُ
 *   المقيَّدُ بالسياقِ **يلزمه بناءُ السياقِ أولًا** — وهذه أولى لبِناتِه.
 *
 * ◆ **والربطُ يُقاس ولا يُؤلَّف**: يُصيَّر سايدبارُ كلِّ دورٍ جذريٍّ حيًّا
 *   (`uxp_render_role`) وتُطابَق مجموعةُ مساراتِه بمجموعةِ مساراتِ كلِّ مساحةٍ
 *   في اللقطة، والمساحةُ الفائزةُ **أعلاها تقاطعًا نسبيًّا**. فلا يُنسب دورٌ
 *   إلى مساحةٍ بالاسمِ المتشابهِ بل **بما يراه فعلًا**.
 *
 * ◆ ويُسجَّل معه **مقياسُ الثقة** (نسبةُ التقاطع): فربطٌ بثقةٍ 40٪ ليس كربطٍ
 *   بـ95٪، **وإخفاءُ الفرقِ يُسوّي المؤكَّدَ بالمظنون**.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = dirname(dirname(__DIR__));
/* ◆ `session_bootstrap` قبلَ `config` — والترتيبُ ليس ذوقًا: بدونه ماتت هذه
     الهجرةُ **صامتةً برمز 255 بلا سطرِ خطأٍ واحد**، لأن `config` يضبط الإبلاغَ
     ويبتلع مخرَجَ CLI. **وموتٌ صامتٌ أخطرُ من خطأٍ صريح.** */
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
require_once $ROOT . '/includes/unified_nav.php';
require_once $ROOT . '/includes/uxui_nav_probe.php';
require_once $ROOT . '/includes/status_display.php';
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
while (ob_get_level()) { ob_end_clean(); }
$conn = isset($GLOBALS['conn']) ? $GLOBALS['conn'] : null;
if (!$conn) { exit("لا اتصال\n"); }
mysqli_set_charset($conn, 'utf8mb4');

/* ══ اتصالانِ لا واحد — وهذا سببُ الموتِ الصامتِ الثاني ══════════════════
   ◆ التصييرُ يلزمه اتصالُ التطبيقِ (`ems_app`) لتسريَ بواباتُ المنحِ كما تسري
     على المستخدم. **و`ems_app` بلا صلاحيةِ DDL** — فسقطَ `CREATE TABLE`
     صامتًا، ثم أعادَ `prepare` قيمةَ false، ثم مات `bind_param` برمز 255
     **بلا سطرِ خطأٍ واحد**. والدرسُ مسجَّلٌ في هذا المستودعِ من قبل.
   ◆ فالكتابةُ باتصالِ المُرحِّلِ والقراءةُ/التصييرُ باتصالِ التطبيق. */
require_once $ROOT . '/includes/env.php';
$mh = ems_env('DB_HOST'); $mp = 3306;
if (strpos($mh, ':') !== false) { list($mh, $mp) = explode(':', $mh); $mp = (int) $mp; }
$mu = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$mpw = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$mig = new mysqli($mh, $mu, $mpw, ems_env('DB_NAME'), $mp);
if ($mig->connect_errno) { exit("تعذّر اتصالُ المُرحِّل: {$mig->connect_error}\n"); }
$mig->set_charset('utf8mb4');

if (!mysqli_query($mig, "CREATE TABLE IF NOT EXISTS `gov_space_roles` (
    `role_id`     INT(11)      NOT NULL,
    `space_ar`    VARCHAR(80)  NOT NULL,
    `overlap_pct` DECIMAL(5,2) NOT NULL DEFAULT 0 COMMENT 'ثقةُ الربطِ — نسبةُ تقاطعِ المسارات',
    `matched`     SMALLINT     NOT NULL DEFAULT 0,
    `role_routes` SMALLINT     NOT NULL DEFAULT 0,
    `basis`       VARCHAR(190) NOT NULL DEFAULT '',
    `updated_at`  DATETIME NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`role_id`),
    KEY `ix_gsr_space` (`space_ar`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='رابعًا — الدورُ ⇄ مساحتُه، مقيسًا بتقاطعِ المساراتِ المُصيَّرة'")) {
    exit("✘ تعذّر إنشاءُ الجدول: " . $mig->error . "\n");
}

/* مساراتُ كلِّ مساحةٍ من اللقطة */
$spaceRoutes = array();
$r = mysqli_query($conn, "SELECT space_ar, route FROM gov_space_appearances");
while ($r && ($x = mysqli_fetch_assoc($r))) {
    $spaceRoutes[$x['space_ar']][mb_strtolower($x['route'])] = 1;
}

$ins = mysqli_prepare($mig, "INSERT INTO gov_space_roles
        (role_id, space_ar, overlap_pct, matched, role_routes, basis)
     VALUES (?,?,?,?,?,?)
     ON DUPLICATE KEY UPDATE space_ar=VALUES(space_ar), overlap_pct=VALUES(overlap_pct),
        matched=VALUES(matched), role_routes=VALUES(role_routes), basis=VALUES(basis)");

echo "══ الدورُ ⇄ مساحتُه ══\n";
$n = 0; $weak = 0;
foreach (uxp_root_roles() as $roleId) {
    if (function_exists('ems_nav_mark_printed')) { ems_nav_mark_printed('', true); }
    $items = uxp_render_role($conn, $roleId);
    $rr = array();
    foreach ($items as $it) {
        $h = uxp_norm(isset($it['href']) ? $it['href'] : '');
        if ($h !== '' && substr($h, -4) === '.php') { $rr[mb_strtolower($h)] = 1; }
    }
    if (!$rr) { echo "  ◆ دور {$roleId}: سايدبارٌ فارغٌ — لا يُربط\n"; continue; }

    $best = ''; $bestPct = 0; $bestHit = 0;
    foreach ($spaceRoutes as $space => $set) {
        $hit = count(array_intersect_key($rr, $set));
        $pct = $hit / max(1, count($rr));
        if ($pct > $bestPct) { $bestPct = $pct; $best = $space; $bestHit = $hit; }
    }
    if ($best === '') { echo "  ◆ دور {$roleId}: لا مساحةَ تطابقه\n"; continue; }

    $pct = round($bestPct * 100, 2);
    $rc  = count($rr);
    $basis = "تقاطعُ {$bestHit} من {$rc} مسارًا مُصيَّرًا";
    mysqli_stmt_bind_param($ins, 'isdiis', $roleId, $best, $pct, $bestHit, $rc, $basis);
    mysqli_stmt_execute($ins);
    $n++;
    $flag = ($pct < 60) ? '  ◆ ثقةٌ منخفضةٌ' : '';
    if ($pct < 60) { $weak++; }
    printf("  دور %-3d ⇒ %-24s ثقة=%5.1f٪ (%d/%d)%s\n", $roleId, mb_substr($best, 0, 24), $pct, $bestHit, $rc, $flag);
}
mysqli_stmt_close($ins);

echo "\n  رُبط: {$n} دورًا" . ($weak ? " · منها **{$weak} بثقةٍ دونَ 60٪** تُعلَن ولا تُطمَس" : '') . "\n";
$q = mysqli_query($mig, "SELECT COUNT(DISTINCT space_ar) FROM gov_space_roles");
echo "  مساحاتٌ مغطّاة: " . ($q ? mysqli_fetch_row($q)[0] : 0) . " من " . count($spaceRoutes) . "\n";
exit($n > 0 ? 0 : 1);
