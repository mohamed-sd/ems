<?php
/**
 * Governance/gov_reports.php — تقارير الحوكمة التسعة (M-14 §12)
 * ───────────────────────────────────────────────────────────────────────────
 * «لكل تقريرٍ مستفيدٌ واحدٌ ودوريةٌ ومصدرٌ ومعادلةٌ واختبارُ صحة — ولا يُنشر
 * مؤشرٌ لا يُتتبَّع إلى مصدره». التسعة نصًّا بدورياتها، كلٌّ من مصدره الحي:
 * سجلُّ الأمن (المحاولات · الاطّلاع الحساس) · المخزن البيني لشاشاته حتى
 * اللحاق (الاستثناءات · الوثائق · المراجعة · الحمايات · العقود · البصمات) ·
 * وحسابُ فصل الواجبات من مصدره الواحد (sod_map+sod_conflicts) لا نسخةً ثانية.
 * قراءةٌ خالصة — صفرُ كتابةٍ من هذه الشاشة (حدُّ الحوكمة: ترى ولا تفعل).
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once '../includes/permissions_helper.php';
require_once '../includes/sod_map.php';

$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$is_super_admin = (strval($_SESSION['user']['role'] ?? '') === '-1');
$uid            = intval($_SESSION['user']['id'] ?? 0);
if (!$is_super_admin && $company_id <= 0) { ems_gov_flash_redirect('../login.php', 'غير مصرح', 'GOV-PERM-403', ''); exit(); }

$__pp = check_page_permissions($conn, 'Governance/gov_reports.php');
if (!$is_super_admin && empty($__pp['can_view'])) {
    require_once __DIR__ . '/../includes/perm_explain_live.php';
    $__why = ems_deny_message($conn, intval($_SESSION['user']['role'] ?? 0), 'Governance/gov_reports.php');
    ems_gov_flash_redirect('../main/dashboard.php', $__why, 'GOV-INFO-200', '');
    exit();
}
$co = $is_super_admin && $company_id <= 0 ? 4 : $company_id;

/* ── قارئ ذيل سجل الأمن (ملف مسطّح) — بلا تحميل الملف كله ─────────────── */
function gov_security_tail($maxBytes = 800000)
{
    $f = __DIR__ . '/../logs/security.log';
    if (!is_file($f)) { return array(); }
    $size = filesize($f);
    $h = fopen($f, 'rb');
    if ($size > $maxBytes) { fseek($h, -$maxBytes, SEEK_END); fgets($h); }
    $out = array();
    while (($ln = fgets($h)) !== false) {
        if (preg_match('/^\[([\d\- :]+)\] \[([^\]]+)\] IP: (\S+) \| User: (.*?) \((\S+)\) \| Event: [^|]+\| Details: (.*?) \| UA:/u', $ln, $m)) {
            $out[] = array('ts' => $m[1], 'type' => $m[2], 'ip' => $m[3],
                           'user' => $m[4], 'uid' => $m[5], 'details' => $m[6]);
        }
    }
    fclose($h);
    return $out;
}
function gov_interim_rows(mysqli $c, $canonical, $co, $limit = 500)
{
    // الموجة ٢ (2026-08-06): الشاشة المحررة تُقرأ من جدولها الأصلي عبر محوّل
    // السجل (الحمولة بالتسميات نفسها) — وغير المسجَّلة تبقى على المخزن البيني.
    require_once __DIR__ . '/../includes/cmp03_local_store.php';
    $reg = cmp03_registry();
    if (isset($reg[$canonical])) {
        $rows = array();
        foreach (cmp03_store_rows($c, $canonical, intval($co), $limit) as $x) {
            $x['p'] = $x['payload'];
            $rows[] = $x;
        }
        return $rows;
    }
    $rows = array();
    $st = $c->prepare("SELECT id, payload, status, created_at FROM cmp03_screen_rows
                        WHERE canonical_file = ? AND company_id = ? ORDER BY id DESC LIMIT " . intval($limit));
    $st->bind_param('si', $canonical, $co);
    $st->execute();
    $rs = $st->get_result();
    while ($x = $rs->fetch_assoc()) {
        $x['p'] = json_decode((string) $x['payload'], true) ?: array();
        $rows[] = $x;
    }
    $st->close();
    return $rows;
}

$logRows = gov_security_tail();
$now = time();
$since7 = date('Y-m-d H:i:s', $now - 7 * 86400);
$since30 = date('Y-m-d H:i:s', $now - 30 * 86400);

/* ① المحاولات الممنوعة — أسبوعي */
$denials = array();
foreach ($logRows as $r) {
    if ($r['ts'] < $since7) { continue; }
    if (preg_match('/DENY|DENIED|BLOCKED|REFUSED|403|WOULD_DENY|SOD_GRANT_BLOCKED/i', $r['type'])) {
        $k = $r['user'] . '|' . $r['type'];
        if (!isset($denials[$k])) { $denials[$k] = array('user' => $r['user'], 'type' => $r['type'], 'n' => 0, 'last' => $r['ts'], 'sample' => $r['details']); }
        $denials[$k]['n']++;
        $denials[$k]['last'] = $r['ts'];
    }
}
usort($denials, function ($a, $b) { return $b['n'] - $a['n']; });

/* ⑥ الاطّلاع الحساس — شهري */
$sensitive = array();
foreach ($logRows as $r) {
    if ($r['type'] === 'SENSITIVE_READ' && $r['ts'] >= $since30) { $sensitive[] = $r; }
}

/* ② الاستثناءات القائمة */
$exceptions = array();
foreach (gov_interim_rows($conn, 'exceptions.php', $co) as $x) {
    if (in_array($x['status'], array('منتهٍ', 'منته', 'ملغى', 'مرفوض'), true)) { continue; }
    $exceptions[] = $x;
}

/* ⑤ الوثائق المنتهية والمشارفة (30 يومًا) */
$licenses = array('expired' => array(), 'soon' => array());
foreach (gov_interim_rows($conn, 'licenses_guarantees.php', $co) as $x) {
    $until = trim((string) ($x['p']['تاريخ الانتهاء'] ?? $x['p']['المدة إلى'] ?? ''));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}/', $until)) { continue; }
    $ts = strtotime(substr($until, 0, 10));
    if ($ts < $now) { $licenses['expired'][] = array($x, $until); }
    elseif ($ts < $now + 30 * 86400) { $licenses['soon'][] = array($x, $until); }
}

/* ③ تعارضات الواجبات — من المصدر الواحد (لا نسخة ثانية من المنطق) */
$sod = array('pairs' => 0, 'measurable' => 0, 'conflicted' => array());
$rr = mysqli_query($conn, "SELECT sod_id, conflict_code, name_ar, permission_a, permission_b, severity
                             FROM sod_conflicts WHERE active = 1");
$rolesAll = array();
$q = mysqli_query($conn, "SELECT id, name FROM roles");
while ($q && ($x = mysqli_fetch_assoc($q))) { $rolesAll[intval($x['id'])] = $x['name']; }
while ($rr && ($p = mysqli_fetch_assoc($rr))) {
    $sod['pairs']++;
    $A = ems_sod_split_codes($p['permission_a']);
    $B = ems_sod_split_codes($p['permission_b']);
    if (ems_sod_pair_grade(array_merge($A, $B)) === 'absent') { continue; }
    $sod['measurable']++;
    foreach ($rolesAll as $rid => $rname) {
        $held = ems_sod_codes_of_role($conn, $rid);
        $ok = true;
        foreach (array_merge($A, $B) as $cc) { if (empty($held[$cc])) { $ok = false; break; } }
        if ($ok) { $sod['conflicted'][] = array('pair' => $p['name_ar'], 'sev' => $p['severity'], 'role' => $rname); }
    }
}

/* ④ دورة المراجعة · ⑦ الحمايات · ⑧ البصمات · ⑨ العقود — من مخازنها البينية */
$review = gov_interim_rows($conn, 'access_review.php', $co, 200);
$guards = gov_interim_rows($conn, 'guards.php', $co, 200);
$stamps = gov_interim_rows($conn, 'release_stamp.php', $co, 5);
$contracts = gov_interim_rows($conn, 'ceo_contracts.php', $co, 300);
$gateFile = __DIR__ . '/../docs/RELEASE_GATE_LAST_ar.md';
$gateInfo = is_file($gateFile)
    ? array('at' => date('Y-m-d H:i', filemtime($gateFile)),
            'verdict' => (mb_strpos((string) file_get_contents($gateFile), '✔ عبور كامل') !== false ? '✔ عبور كامل' : '✘ متوقفة ببوابة'))
    : null;
$contractStats = array('total' => count($contracts), 'signed' => 0, 'registered' => 0);
foreach ($contracts as $x) {
    if (trim((string) ($x['p']['تاريخ التوقيع'] ?? '')) !== '') { $contractStats['signed']++; }
    if (mb_strpos((string) ($x['p']['سُجّل في السجل الموحَّد؟'] ?? ''), 'نعم') !== false) { $contractStats['registered']++; }
}
$guardStats = array();
foreach ($guards as $x) {
    $k = trim((string) ($x['p']['الصنف'] ?? $x['status'] ?? '—'));
    $guardStats[$k] = ($guardStats[$k] ?? 0) + 1;
}

$page_title = 'إيكوبيشن | تقارير الحوكمة';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($__pp) ? $__pp : null);
include '../inheader.php';
include '../insidebar.php';

function rpt_head($n, $title, $who, $cadence, $src)
{
    echo '<div class="card" style="margin-bottom:14px"><div class="card-header"><strong>'
       . $n . ' · ' . htmlspecialchars($title) . '</strong> '
       . '<span class="badge bg-secondary">' . htmlspecialchars($cadence) . '</span> '
       . '<small class="text-muted">المستفيد: ' . htmlspecialchars($who) . ' · المصدر: ' . htmlspecialchars($src) . '</small>'
       . '</div><div class="card-body" style="padding:10px 14px">';
}
?>
<div class="main ems-unified-page-shell" dir="rtl">
    <?php
    $header_title = 'تقارير الحوكمة التسعة';
    $header_icon = 'fa fa-scale-balanced';
    $header_actions = array();
    $header_back = false;
    include '../includes/page_header.php';
    require_once __DIR__ . '/../includes/screen_contract.php';
    ems_screen_about('تقارير الحوكمة التسعة بدورياتها من مصادرها الحية — أقرأ ما مُنع وما استُثني ومن اطّلع على الحساس.');

    ?>
    <p class="text-muted"><i class="fas fa-circle-info"></i> M-14 §12 نصًّا — قراءةٌ خالصةٌ من المصادر الحية،
        وما مصدرُه المخزنُ البينيُّ معلَّمٌ حتى اللحاق. «لا يُنشر مؤشرٌ لا يُتتبَّع إلى مصدره».</p>

    <?php rpt_head('①', 'المحاولات الممنوعة', 'الحوكمة والإدارة العليا', 'أسبوعي', 'سجل الأمن — آخر 7 أيام'); ?>
        <?php if (!$denials): ?><span class="text-muted">صفر محاولة مرفوضة في الأسبوع</span>
        <?php else: ?><table class="alltables display no-datatable" style="width:100%"><thead>
            <tr><th>المستخدم</th><th>الحماية/الحدث</th><th>التكرار</th><th>آخرها</th><th>عيّنة</th>
              <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
              <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
              </tr></thead><tbody>
            <?php foreach (array_slice($denials, 0, 15) as $d): ?>
                <tr><td><?php echo htmlspecialchars($d['user']); ?></td><td><code><?php echo htmlspecialchars($d['type']); ?></code></td>
                <td><strong><?php echo intval($d['n']); ?></strong><?php echo $d['n'] >= 5 ? ' <span class="badge bg-danger">متكرر — يُصعَّد</span>' : ''; ?></td>
                <td><?php echo htmlspecialchars($d['last']); ?></td>
                <td style="max-width:320px;white-space:normal;font-size:.8rem"><?php echo htmlspecialchars(mb_substr($d['sample'], 0, 90)); ?></td></tr>
            <?php endforeach; ?></tbody></table><?php endif; ?>
    </div></div>

    <?php rpt_head('②', 'الاستثناءات القائمة', 'الحوكمة', 'أسبوعي', 'شاشة الاستثناءات (بيني حتى اللحاق)'); ?>
        <strong><?php echo count($exceptions); ?></strong> استثناءً نافذًا
        <?php if ($exceptions): ?><table class="alltables display no-datatable" style="width:100%"><thead>
            <tr><th>الطلب</th><th>الحماية</th><th>المدة إلى</th><th>الحالة</th></tr></thead><tbody>
            <?php foreach (array_slice($exceptions, 0, 10) as $x): ?>
                <tr><td><?php echo htmlspecialchars((string) ($x['p']['رقم الطلب'] ?? $x['id'])); ?></td>
                <td style="white-space:normal"><?php echo htmlspecialchars((string) ($x['p']['الحماية المستثناة'] ?? '—')); ?></td>
                <td><?php echo htmlspecialchars((string) ($x['p']['المدة إلى'] ?? '—')); ?></td>
                <td><?php echo htmlspecialchars((string) $x['status']); ?></td></tr>
            <?php endforeach; ?></tbody></table><?php endif; ?>
        <div class="text-muted" style="font-size:.8rem;margin-top:6px">الانقضاء آليٌّ بالنبض الساعي (BR-GOV-05) — لا يمتد بالسكوت.</div>
    </div></div>

    <?php rpt_head('③', 'تعارضات الواجبات', 'الحوكمة والمراجع', 'شهري', 'sod_conflicts + الخريطة — حسابٌ حي'); ?>
        الأزواج: <?php echo intval($sod['pairs']); ?> · المقيسة: <?php echo intval($sod['measurable']); ?> ·
        <?php if (!$sod['conflicted']): ?><span class="badge bg-success">صفرُ دورٍ يجمع طرفي زوجٍ مقيس</span>
        <?php else: foreach ($sod['conflicted'] as $cf): ?>
            <div class="alert alert-danger" style="padding:6px 10px;margin:6px 0">⛔ <?php echo htmlspecialchars($cf['role'] . ' — ' . $cf['pair'] . ' (' . $cf['sev'] . ')'); ?></div>
        <?php endforeach; endif; ?>
        <div class="text-muted" style="font-size:.8rem;margin-top:6px">والحارس القبلي في مسار المنح (sod_guard) يمنع الدقيق ويبلّغ التقريبي.</div>
    </div></div>

    <?php rpt_head('④', 'دورة المراجعة الدورية', 'الحوكمة', 'ربع سنوي', 'شاشة المراجعة (بيني حتى اللحاق)'); ?>
        صفوف الدورة: <strong><?php echo count($review); ?></strong>
        <div class="text-muted" style="font-size:.8rem">القالب الدوري GOV-Q-ACCESS يولّد مهمتها — «والصمتُ سحبٌ لا إبقاء».</div>
    </div></div>

    <?php rpt_head('⑤', 'الوثائق النظامية المنتهية', 'الحوكمة والمالية', 'أسبوعي', 'التراخيص والكفالات (بيني حتى اللحاق)'); ?>
        <span class="badge bg-danger">منتهية: <?php echo count($licenses['expired']); ?></span>
        <span class="badge bg-warning text-dark">تنتهي خلال 30 يومًا: <?php echo count($licenses['soon']); ?></span>
        <?php foreach (array_slice(array_merge($licenses['expired'], $licenses['soon']), 0, 10) as $lx): ?>
            <div style="font-size:.85rem;margin-top:4px">· <?php echo htmlspecialchars((string) ($lx[0]['p']['اسم المستند'] ?? $lx[0]['p']['النوع'] ?? ('صف ' . $lx[0]['id'])) . ' — حتى ' . $lx[1]); ?></div>
        <?php endforeach; ?>
    </div></div>

    <?php rpt_head('⑥', 'سجل الاطّلاع الحساس', 'الحوكمة والإدارة العليا', 'شهري', 'سجل الأمن SENSITIVE_READ — آخر 30 يومًا'); ?>
        <?php if (!$sensitive): ?><span class="text-muted">صفر اطّلاعٍ حساسٍ مسجَّلٍ في الثلاثين يومًا</span>
        <?php else: ?><table class="alltables display no-datatable" style="width:100%"><thead>
            <tr><th>القارئ</th><th>متى</th><th>ماذا قرأ</th></tr></thead><tbody>
            <?php foreach (array_slice($sensitive, -15) as $s): ?>
                <tr><td><?php echo htmlspecialchars($s['user'] . ' (' . $s['uid'] . ')'); ?></td>
                <td><?php echo htmlspecialchars($s['ts']); ?></td>
                <td style="font-size:.82rem"><code><?php echo htmlspecialchars(mb_substr($s['details'], 0, 100)); ?></code></td></tr>
            <?php endforeach; ?></tbody></table><?php endif; ?>
        <div class="text-muted" style="font-size:.8rem;margin-top:6px">«القراءةُ على السرِّ فعلٌ يُساءل عنه» (BR-GOV-07) — القناة ems_log_sensitive_read.</div>
    </div></div>

    <?php rpt_head('⑦', 'حالة الحمايات', 'الحوكمة', 'شهري', 'تصنيف قواعد المنع (بيني حتى اللحاق)'); ?>
        <?php if (!$guardStats): ?><span class="text-muted">لا صفوفَ بعد</span>
        <?php else: foreach ($guardStats as $k => $n): ?>
            <span class="badge bg-secondary" style="margin:2px"><?php echo htmlspecialchars($k . ': ' . $n); ?></span>
        <?php endforeach; endif; ?>
        <div class="text-muted" style="font-size:.8rem;margin-top:6px">الأصناف الثلاثة: منعٌ مطلق · منعٌ باستثناء · تنبيهٌ مسجَّل — «لا حمايةَ بلا صنفٍ معلن».</div>
    </div></div>

    <?php rpt_head('⑧', 'بصمات الإصدارات', 'الحوكمة والتقنية', 'لكل إصدار', 'شاشة البصمة + شهادة البوابات'); ?>
        <?php if ($gateInfo): ?>
            <div>آخرُ عبورٍ لبوابات التسليم الخمس: <strong><?php echo htmlspecialchars($gateInfo['verdict']); ?></strong>
                <small class="text-muted">(<?php echo htmlspecialchars($gateInfo['at']); ?> · docs/RELEASE_GATE_LAST_ar.md)</small></div>
        <?php endif; ?>
        <div>صفوف البصمات: <strong><?php echo count($stamps); ?></strong> (آخر 5)</div>
        <div class="text-muted" style="font-size:.8rem">«لا نشرَ بلا بصمةٍ وتقريرِ اكتمال» (BR-GOV-08).</div>
    </div></div>

    <?php rpt_head('⑨', 'الالتزام التعاقدي', 'الحوكمة والمالية', 'شهري', 'سجل توقيع العقود (بيني حتى اللحاق)'); ?>
        العقود: <strong><?php echo $contractStats['total']; ?></strong> ·
        موقَّعة: <strong><?php echo $contractStats['signed']; ?></strong> ·
        في السجل الموحَّد: <strong><?php echo $contractStats['registered']; ?></strong>
        <?php if ($contractStats['signed'] > $contractStats['registered']): ?>
            <span class="badge bg-warning text-dark">فجوة قيدٍ في السجل الموحَّد: <?php echo $contractStats['signed'] - $contractStats['registered']; ?></span>
        <?php endif; ?>
        <div class="text-muted" style="font-size:.8rem">وحارس BR-CEO-02 يمنع التوقيع بملاحظةٍ حرجةٍ مفتوحة.</div>
    </div></div>
</div>
