<?php
/**
 * tools/uxui_live_vs_matrix.php — تقريرُ النظامِ الحيِّ مقابلَ مصفوفةِ التنقلِ المعيارية
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ يقرأ **النصَّ المُصيَّرَ** لا جدولَ التنقل: يستدعي المُصيِّرَ الحيَّ
 *   renderUnifiedNavigationV2 بجلسةِ مستخدمٍ حقيقيٍّ لكلِّ دورٍ جذريٍّ في شركةِ
 *   «ايكوبيشن» (company_id=4) — فتسري بواباتُ المنحِ الفردية (Financing/…)
 *   وترشيحُ الصلاحياتِ كما تسري على المستخدمِ الفعلي.
 * ◆ المرجع: docs/uxui_matrix_20260818.csv — تصديرٌ حرفيٌّ لورقةِ «مصفوفة
 *   التنقل المعيارية» (359 صفًّا) من دفترِ التدقيقِ الشامل UXUI_MASTER_AUDIT-9
 *   (sha256 أوله 96d42a26149be74f49eab56a9bec6b9f).
 * ◆ التطبيع: يسقط "../" والاستعلامُ ?view= والمرساةُ #n — فالمنظرُ المحفوظُ
 *   والتكرارُ المقصودُ يعودان لصفِّ مسارِهما الأمّ (العقد التقني بند 7).
 *
 * التشغيل (قراءةٌ فقط — لا يكتب في القاعدة):
 *   php tools/uxui_live_vs_matrix.php                 الملخّصُ والفجوتان
 *   php tools/uxui_live_vs_matrix.php --tsv=<path>    + إثباتُ المواضعِ كاملًا TSV
 *   php tools/uxui_live_vs_matrix.php --md=<path>     + التقريرُ Markdown
 *   php tools/uxui_live_vs_matrix.php --roles=1,6     حصرُ الأدوار (الافتراضي: 19 الجذرية)
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
$_SERVER['SCRIPT_NAME'] = '/ems/main/dashboard.php';
$_SERVER['REQUEST_URI'] = $_SERVER['SCRIPT_NAME'];
$_SERVER['REQUEST_METHOD'] = 'GET';
require_once $ROOT . '/config.php';
require_once $ROOT . '/includes/unified_nav.php';
while (ob_get_level() > 0) { ob_end_clean(); }
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

$args = array();
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z]+)(?:=(.*))?$/', $a, $m)) { $args[$m[1]] = isset($m[2]) ? $m[2] : '1'; }
}

/* ── الأدوارُ الجذريةُ التسعةَ عشرَ — نفسُ نطاقِ دليلِ السايدبارِ الحيّ (899 موضعًا) ── */
$ROOT_ROLES = array(1,2,3,4,5,6,9,12,13,15,16,17,23,25,26,27,28,32,33);
if (!empty($args['roles'])) { $ROOT_ROLES = array_map('intval', explode(',', $args['roles'])); }

$COMPANY = 4; // «ايكوبيشن»

/* ── مصفوفةُ المرجع ── */
$csvPath = $ROOT . '/docs/uxui_matrix_20260818.csv';
if (!is_file($csvPath)) { fwrite(STDERR, "لا مصفوفة: $csvPath\n"); exit(2); }
$fh = fopen($csvPath, 'r');
$hdr = fgetcsv($fh);
$matrix = array();          // key = المسارُ المطبَّع صغيرًا
while (($r = fgetcsv($fh)) !== false) {
    $row = array_combine($hdr, $r);
    $matrix[mb_strtolower(trim($row['route']))] = $row;
}
fclose($fh);

/* ── اسمُ كلِّ دورٍ ومستخدمُه الحيُّ الأول في الشركة ── */
$roleNames = array(); $roleUsers = array();
$res = mysqli_query($conn, "SELECT id, name FROM roles");
while ($x = mysqli_fetch_assoc($res)) { $roleNames[(int)$x['id']] = $x['name']; }
$res = mysqli_query($conn, "SELECT CAST(role AS UNSIGNED) r, MIN(id) uid FROM users WHERE company_id = {$COMPANY} GROUP BY CAST(role AS UNSIGNED)");
while ($x = mysqli_fetch_assoc($res)) { $roleUsers[(int)$x['r']] = (int)$x['uid']; }

/* ── تصييرُ دورٍ واحدٍ والتقاطُ (المجموعة · الاسم · الرابط) بالترتيب ── */
function uxui_render_role($conn, $roleId, $uid) {
    $_SESSION['user'] = array('id' => $uid, 'role' => (string)$roleId, 'company_id' => 4, 'name' => 'uxui-probe');
    /* حارسُ التكرارِ static لكلِّ عملية — وهنا نُصيِّر 19 دورًا في عمليةٍ واحدة:
       بلا تصفيرٍ يبتلع الحارسُ ما طُبع لدورٍ سابقٍ فيتناقص العدُّ دورًا بعد دور */
    if (function_exists('ems_nav_mark_printed')) { ems_nav_mark_printed('', true); }
    $chats = '<li><a href="../chats/index.php" id="sidebarChatLink"><i class="fa fa-comments"></i>'
           . '<span class="sidebar-link-text">المراسلات</span></a></li>' . "\n";
    ob_start();
    $ok = renderUnifiedNavigationV2($conn, (string)$roleId, '../', array(), $chats);
    $html = ob_get_clean();
    if (!$ok) { return array(); } // دورٌ بلا عناصر — لا مواضع
    $positions = array(); $group = '— خارج التبويب';
    if (preg_match_all('/<span class="nav-group-name">(?<g>[^<]*)<\/span>|<a\b[^>]*href="(?<h>[^"]*)"[^>]*>(?<in>.*?)<\/a>/us', $html, $mm, PREG_SET_ORDER)) {
        foreach ($mm as $m) {
            if (isset($m['g']) && $m['g'] !== '') { $group = trim(html_entity_decode($m['g'], ENT_QUOTES, 'UTF-8')); continue; }
            $href = trim($m['h']);
            $inner = preg_replace('/<span[^>]*nav-count-badge[^>]*>.*?<\/span>/us', '', $m['in']);
            $label = trim(html_entity_decode(strip_tags($inner), ENT_QUOTES, 'UTF-8'));
            $label = preg_replace('/\s+/u', ' ', $label);
            $positions[] = array('group' => $group, 'label' => $label, 'href' => $href);
        }
    }
    return $positions;
}

/* ── التطبيع ── */
function uxui_norm($href) {
    $r = preg_replace('~^(\.\./)+~', '', trim($href));
    $r = preg_replace('/[?#].*$/u', '', $r);
    return $r;
}

/* ── المسح ── */
$all = array();          // كلُّ المواضع
$liveRoutes = array();   // route_lc => تجميعةُ ظهوره
foreach ($ROOT_ROLES as $rid) {
    $uid = isset($roleUsers[$rid]) ? $roleUsers[$rid] : 0;
    $pos = uxui_render_role($conn, $rid, $uid);
    $seq = 0;
    foreach ($pos as $p) {
        $seq++;
        $norm = uxui_norm($p['href']);
        $lc = mb_strtolower($norm);
        $hit = isset($matrix[$lc]) ? $matrix[$lc] : null;
        $all[] = array(
            'role_id' => $rid, 'role' => isset($roleNames[$rid]) ? $roleNames[$rid] : ('#'.$rid), 'uid' => $uid,
            'seq' => $seq, 'group' => $p['group'], 'label' => $p['label'], 'href' => $p['href'],
            'route' => $norm, 'matrix_n' => $hit ? $hit['n'] : '', 'status' => $hit ? $hit['status'] : 'NO_ROW',
            'canonical' => $hit ? $hit['canonical_ar'] : '',
        );
        if (!isset($liveRoutes[$lc])) { $liveRoutes[$lc] = array('route'=>$norm,'roles'=>array(),'labels'=>array(),'groups'=>array(),'count'=>0); }
        $liveRoutes[$lc]['roles'][$rid] = true;
        $liveRoutes[$lc]['labels'][$p['label']] = true;
        $liveRoutes[$lc]['groups'][$p['group']] = true;
        $liveRoutes[$lc]['count']++;
    }
}

/* ── الفجوتان ── */
$liveNoRow = array();    // حيٌّ بلا صف
foreach ($liveRoutes as $lc => $info) { if (!isset($matrix[$lc])) { $liveNoRow[$lc] = $info; } }
$matrixNotLive = array();// صفٌّ لا يظهر حيًّا (ضمن نطاقِ الأدوارِ الممسوحة)
foreach ($matrix as $lc => $row) { if (!isset($liveRoutes[$lc])) { $matrixNotLive[] = $row; } }

/* ── الملخّص ── */
$byRole = array();
foreach ($all as $p) { $byRole[$p['role_id']] = isset($byRole[$p['role_id']]) ? $byRole[$p['role_id']] + 1 : 1; }
$matchedRoutes = count($liveRoutes) - count($liveNoRow);
$sum = array(
    'roles_scanned' => count($ROOT_ROLES),
    'positions' => count($all),
    'unique_live_routes' => count($liveRoutes),
    'live_routes_with_row' => $matchedRoutes,
    'live_routes_no_row' => count($liveNoRow),
    'matrix_rows' => count($matrix),
    'matrix_rows_not_live' => count($matrixNotLive),
);
echo "UXUI-LIVE-VS-MATRIX|" . json_encode($sum, JSON_UNESCAPED_UNICODE) . "\n";
foreach ($ROOT_ROLES as $rid) {
    echo "  role {$rid} (" . (isset($roleNames[$rid]) ? $roleNames[$rid] : '?') . "): " . (isset($byRole[$rid]) ? $byRole[$rid] : 0) . " موضعًا\n";
}

/* ── TSV: إثباتُ المواضع ── */
if (!empty($args['tsv'])) {
    $f = fopen($args['tsv'], 'w');
    fwrite($f, "role_id\trole\tuid\tseq\tgroup\tlabel\thref\troute\tmatrix_n\tstatus\tcanonical\n");
    foreach ($all as $p) {
        fwrite($f, implode("\t", array($p['role_id'],$p['role'],$p['uid'],$p['seq'],$p['group'],$p['label'],$p['href'],$p['route'],$p['matrix_n'],$p['status'],$p['canonical'])) . "\n");
    }
    fclose($f);
    echo "TSV ⇐ {$args['tsv']} (" . count($all) . " موضعًا)\n";
}

/* ── Markdown: التقرير ── */
if (!empty($args['md'])) {
    $L = array();
    $L[] = '# تقريرُ النظامِ الحيِّ مقابلَ مصفوفةِ التنقلِ المعيارية';
    $L[] = '';
    $L[] = '· التاريخ: ' . date('Y-m-d H:i') . ' · المرجع: `docs/uxui_matrix_20260818.csv` (359 صفًّا من UXUI_MASTER_AUDIT-9)';
    $L[] = '· المصدرُ الحي: تصييرُ `renderUnifiedNavigationV2` بجلسةِ أولِ مستخدمٍ حقيقيٍّ لكلِّ دورٍ في شركةِ ايكوبيشن (co4) — لا قراءةَ جداول.';
    $L[] = '· أمرُ الإنتاج: `php tools/uxui_live_vs_matrix.php --md=<هذا الملف> --tsv=docs/uxui_live_positions.tsv`';
    $L[] = '';
    $L[] = '| المؤشر | العدد |';
    $L[] = '|---|---|';
    $L[] = '| الأدوارُ الممسوحة | ' . $sum['roles_scanned'] . ' |';
    $L[] = '| مواضعُ الظهورِ المُصيَّرة | ' . $sum['positions'] . ' |';
    $L[] = '| المساراتُ الحيّةُ الفريدة | ' . $sum['unique_live_routes'] . ' |';
    $L[] = '| منها له صفٌّ في المصفوفة | ' . $sum['live_routes_with_row'] . ' |';
    $L[] = '| **حيٌّ بلا صفٍّ في المصفوفة** | **' . $sum['live_routes_no_row'] . '** |';
    $L[] = '| صفوفُ المصفوفة | ' . $sum['matrix_rows'] . ' |';
    $L[] = '| صفٌّ لا يظهر حيًّا في الأدوارِ الممسوحة | ' . $sum['matrix_rows_not_live'] . ' |';
    $L[] = '';
    $L[] = '## ① المساراتُ الحيّةُ التي **لا صفَّ لها** في المصفوفة (' . count($liveNoRow) . ')';
    $L[] = '';
    if (empty($liveNoRow)) { $L[] = '**صفر — كلُّ مسارٍ حيٍّ له صفٌّ معياريّ.**'; }
    else {
        $L[] = '| المسار | مواضعُه | الأدوار | الأسماءُ الظاهرة | التبويبات |';
        $L[] = '|---|---|---|---|---|';
        foreach ($liveNoRow as $info) {
            $L[] = '| `' . $info['route'] . '` | ' . $info['count'] . ' | ' . implode('·', array_keys($info['roles'])) . ' | ' . implode(' ⁄ ', array_keys($info['labels'])) . ' | ' . implode(' ⁄ ', array_keys($info['groups'])) . ' |';
        }
    }
    $L[] = '';
    $L[] = '## ② صفوفُ المصفوفةِ التي لا تظهر حيًّا في الأدوارِ الممسوحة (' . count($matrixNotLive) . ')';
    $L[] = '';
    $L[] = '◆ ليست فجوةَ بناءٍ بالضرورة: منها ما يظهر لأدوارٍ فرعيةٍ أو بمنحٍ خاصة أو شاشاتُ تفصيلٍ تُفتح من داخلِ الشاشاتِ لا من السايدبار.';
    $L[] = '';
    if (!empty($matrixNotLive)) {
        $L[] = '| # | المسار | الاسمُ المعياري | الحالة |';
        $L[] = '|---|---|---|---|';
        foreach ($matrixNotLive as $row) {
            $L[] = '| ' . $row['n'] . ' | `' . $row['route'] . '` | ' . $row['canonical_ar'] . ' | ' . $row['status'] . ' |';
        }
    }
    $L[] = '';
    $L[] = '## ③ مواضعُ الظهورِ لكلِّ دور';
    $L[] = '';
    $L[] = '| الدور | الاسم | جلسةُ المستخدم | المواضع |';
    $L[] = '|---|---|---|---|';
    foreach ($ROOT_ROLES as $rid) {
        $L[] = '| ' . $rid . ' | ' . (isset($roleNames[$rid]) ? $roleNames[$rid] : '?') . ' | uid=' . (isset($roleUsers[$rid]) ? $roleUsers[$rid] : 0) . ' | ' . (isset($byRole[$rid]) ? $byRole[$rid] : 0) . ' |';
    }
    $L[] = '';
    file_put_contents($args['md'], implode("\n", $L) . "\n");
    echo "MD ⇐ {$args['md']}\n";
}
