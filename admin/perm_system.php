<?php
/**
 * admin/perm_system.php — نظام الصلاحيات الجديد (SEC-01) · لوحة المدير الأعلى
 * ───────────────────────────────────────────────────────────────────────────
 * المنصةُ تحمل اليوم نظامَي صلاحياتٍ متوازيين:
 *   ① الحيُّ    — role_permissions (دور × شاشة × أربع رايات) وهو **الذي يمنع**.
 *   ② الجديد   — SEC-01 (شخص × صفة × نطاق × سقف × مدة) وهو الذي **يفسّر**
 *                ويفصل الواجبات وينتهي آليًّا.
 * وهذه الشاشة مرآةُ المسار من ① إلى ② بمراحله الست، ومقياسُ ما ينقص لكلٍّ —
 * قرائيةٌ بحتة (SELECT فقط): لا تقلب علمًا ولا تكتب صفًّا. القلبُ من .env
 * بيد المالك بعد أن يثبت الظلُّ صفرَ فرقٍ أربعةَ عشرَ يومًا (E-04 SEC-019).
 */
require_once __DIR__ . '/includes/auth.php';
super_admin_require_login();

$admin        = super_admin_current();
$page_title   = 'نظام الصلاحيات الجديد';
$current_page = 'perm-system';

require_once dirname(__DIR__) . '/config.php';
$ps_conn = $GLOBALS['conn'];
@mysqli_set_charset($ps_conn, 'utf8mb4');

/** عدّادٌ آمن — الجدولُ الغائبُ يعيد null لا خطأً */
function ps_one($conn, $sql) {
    $r = @mysqli_query($conn, $sql);
    if (!$r) { return null; }
    $x = mysqli_fetch_assoc($r);
    return $x ? (int) reset($x) : 0;
}
function ps_rows($conn, $sql, $limit = 50) {
    $out = array();
    $r = @mysqli_query($conn, $sql);
    if (!$r) { return $out; }
    while (($x = mysqli_fetch_assoc($r)) && count($out) < $limit) { $out[] = $x; }
    return $out;
}

// ── العَلَم الحاكم ─────────────────────────────────────────────────────────
$ps_source = 'legacy';
if (function_exists('ems_env')) { $ps_source = strtolower((string) ems_env('EMS_PERM_SOURCE', 'legacy')); }
$ps_derived = ($ps_source === 'derived');

// ── أرقام النظامين ────────────────────────────────────────────────────────
$liveRows    = (int) ps_one($ps_conn, "SELECT COUNT(*) FROM role_permissions");
$liveRoles   = (int) ps_one($ps_conn, "SELECT COUNT(DISTINCT role_id) FROM role_permissions");
$liveModules = (int) ps_one($ps_conn, "SELECT COUNT(*) FROM modules");

$tpl      = (int) ps_one($ps_conn, "SELECT COUNT(*) FROM permission_templates");
$tplPub   = (int) ps_one($ps_conn, "SELECT COUNT(*) FROM permission_template_versions WHERE state='published'");
$tplItems = (int) ps_one($ps_conn, "SELECT COUNT(*) FROM template_permissions");
$effective = (int) ps_one($ps_conn, "SELECT COUNT(*) FROM effective_permissions");

// ── جسرُ الوصول: من يستطيع النظامُ الجديدُ أن يحلَّ صلاحيتَه اليوم؟ ────────
$usersActive = (int) ps_one($ps_conn, "SELECT COUNT(*) FROM users WHERE status='active'");
$usersPos    = (int) ps_one($ps_conn, "SELECT COUNT(*) FROM users WHERE status='active' AND position_id IS NOT NULL AND position_id > 0");
$persons     = (int) ps_one($ps_conn, "SELECT COUNT(*) FROM persons");
$positions   = (int) ps_one($ps_conn, "SELECT COUNT(*) FROM person_positions");
$titlesTpl   = (int) ps_one($ps_conn, "SELECT COUNT(*) FROM job_titles WHERE template_id IS NOT NULL");
$titlesAll   = (int) ps_one($ps_conn, "SELECT COUNT(*) FROM job_titles");

// ── ميزانُ الظل ───────────────────────────────────────────────────────────
$diffs      = (int) ps_one($ps_conn, "SELECT COUNT(*) FROM perm_shadow_diffs");
$diffsLast  = ps_rows($ps_conn, "SELECT MAX(at) m FROM perm_shadow_diffs", 1);
$lastDiffAt = isset($diffsLast[0]['m']) ? $diffsLast[0]['m'] : null;
$streak     = ($diffs === 0) ? null : (int) ps_one($ps_conn, "SELECT DATEDIFF(CURDATE(), DATE(MAX(at))) FROM perm_shadow_diffs");

// ── المراحل الست (E-04 SEC-019) ───────────────────────────────────────────
$phases = array(
    array('①', 'القديم وحده يقرر',        true,                          'العلم legacy — وهو حالنا'),
    array('②', 'ملء القوالب من الواقع',   $tplItems > 0,                 $tplItems . ' بندا في ' . $tplPub . ' نسخة منشورة'),
    array('③', 'تشغيل الظل والمقارنة',    $diffs > 0 || $usersPos > 0,   'يحتاج جسر وصول للمستخدمين'),
    array('④', 'صفر فرق أربعة عشر يوما', ($diffs === 0 && $usersPos > 0), 'لم يبدأ العداد بعد'),
    array('⑤', 'قلب العلم إلى derived',  $ps_derived,                   'بيد المالك في .env'),
    array('⑥', 'القديم قراءة ثم تقاعد',   false,                         'بعد القلب واستقراره'),
);

// ── تفصيل القوالب ─────────────────────────────────────────────────────────
$tplBreak = ps_rows($ps_conn,
    "SELECT t.tpl_kind, COUNT(DISTINCT t.tpl_id) tpls, COUNT(tp.tp_id) items
       FROM permission_templates t
       LEFT JOIN permission_template_versions v ON v.tpl_id = t.tpl_id AND v.state='published'
       LEFT JOIN template_permissions tp ON tp.template_version_id = v.ver_id
      GROUP BY t.tpl_kind ORDER BY FIELD(t.tpl_kind,'relation','family','level','title','assignment')");

$topTitles = ps_rows($ps_conn,
    "SELECT t.key_code, COUNT(tp.tp_id) items
       FROM permission_templates t
       JOIN permission_template_versions v ON v.tpl_id = t.tpl_id AND v.state='published'
       JOIN template_permissions tp ON tp.template_version_id = v.ver_id
      WHERE t.tpl_kind='title'
      GROUP BY t.tpl_id ORDER BY items DESC LIMIT 12");

// ── آخر الفروق (إن وُجدت) ─────────────────────────────────────────────────
$diffRows = $diffs > 0
    ? ps_rows($ps_conn, "SELECT user_id, module_code, action, legacy_decision, derived_decision, detail, at
                           FROM perm_shadow_diffs ORDER BY at DESC LIMIT 10")
    : array();

$health = ($tplItems > 0 && $usersPos > 0) ? 'ok' : ($tplItems > 0 ? 'warn' : 'err');

require_once __DIR__ . '/includes/layout_head.php';
?>

<div class="card">
    <div class="card-h">
        <i class="fas fa-shield-halved"></i> المصدر الحاكم الآن:
        <strong style="color:<?php echo $ps_derived ? 'var(--green,#27ae60)' : 'var(--blue,#2980b9)'; ?>">
            <?php echo $ps_derived ? 'النظام الجديد (derived)' : 'النظام القديم (legacy)'; ?>
        </strong>
    </div>
    <div class="card-b" style="font-size:.88rem;line-height:1.9">
        <p>ما دام العلم <code>EMS_PERM_SOURCE=legacy</code> فالنظام الجديد <strong>مرآة لا تقرر شيئا</strong> —
           والذي يمنع ويسمح هو <code>role_permissions</code> وحده. القلب قرار مالك في <code>.env</code>،
           ولا يتخذ قبل أن يثبت الظل <strong>صفر فرق أربعة عشر يوما</strong>.</p>
    </div>
</div>

<div class="stat-grid" style="margin-top:16px">
    <div class="stat-card hex-stat-card hex-stat-blue"><div class="stat-row">
        <div><div class="stat-val"><?php echo number_format($liveRows); ?></div><div class="stat-lbl">القديم: منح الأدوار (<?php echo $liveRoles; ?> دورا)</div></div>
        <div class="stat-ico"><i class="fas fa-key"></i></div>
    </div></div>
    <div class="stat-card hex-stat-card <?php echo $tplItems > 0 ? 'hex-stat-green' : 'hex-stat-red'; ?>"><div class="stat-row">
        <div><div class="stat-val"><?php echo number_format($tplItems); ?></div><div class="stat-lbl">الجديد: بنود القوالب</div></div>
        <div class="stat-ico"><i class="fas fa-layer-group"></i></div>
    </div></div>
    <div class="stat-card hex-stat-card hex-stat-blue"><div class="stat-row">
        <div><div class="stat-val"><?php echo number_format($tplPub); ?></div><div class="stat-lbl">نسخ منشورة (من <?php echo $tpl; ?> قالبا)</div></div>
        <div class="stat-ico"><i class="fas fa-file-signature"></i></div>
    </div></div>
    <div class="stat-card hex-stat-card <?php echo $usersPos > 0 ? 'hex-stat-green' : 'hex-stat-red'; ?>"><div class="stat-row">
        <div><div class="stat-val"><?php echo $usersPos . '/' . $usersActive; ?></div><div class="stat-lbl">مستخدمون يصلهم الجديد</div></div>
        <div class="stat-ico"><i class="fas fa-link-slash"></i></div>
    </div></div>
    <div class="stat-card hex-stat-card <?php echo $diffs === 0 ? 'hex-stat-green' : 'hex-stat-orange'; ?>"><div class="stat-row">
        <div><div class="stat-val"><?php echo number_format($diffs); ?></div><div class="stat-lbl">فروق الظل المسجلة</div></div>
        <div class="stat-ico"><i class="fas fa-code-compare"></i></div>
    </div></div>
    <div class="stat-card hex-stat-card hex-stat-blue"><div class="stat-row">
        <div><div class="stat-val"><?php echo number_format($effective); ?></div><div class="stat-lbl">صلاحيات فعالة محسوبة</div></div>
        <div class="stat-ico"><i class="fas fa-calculator"></i></div>
    </div></div>
</div>

<div class="card" style="margin-top:16px">
    <div class="card-h"><i class="fas fa-road"></i> المراحل الست — أين نقف</div>
    <div class="card-b" style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;font-size:.88rem">
            <thead><tr style="text-align:right;border-bottom:2px solid #ddd">
                <th style="padding:8px;width:60px">المرحلة</th><th style="padding:8px">الوصف</th>
                <th style="padding:8px;width:90px">الحال</th><th style="padding:8px">القياس</th>
            </tr></thead>
            <tbody>
            <?php foreach ($phases as $p): ?>
                <tr style="border-bottom:1px solid #eee">
                    <td style="padding:8px;font-size:1.1rem"><?php echo e($p[0]); ?></td>
                    <td style="padding:8px"><?php echo e($p[1]); ?></td>
                    <td style="padding:8px;font-weight:800;color:<?php echo $p[2] ? '#27ae60' : '#c0392b'; ?>">
                        <?php echo $p[2] ? '✔ تمت' : '— لم تبدأ'; ?></td>
                    <td style="padding:8px;color:#666"><?php echo e($p[3]); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card" style="margin-top:16px">
    <div class="card-h" style="color:<?php echo $usersPos > 0 ? 'var(--green,#27ae60)' : 'var(--red,#c0392b)'; ?>">
        <i class="fas fa-bridge"></i> جسر الوصول — العائق الحاكم
    </div>
    <div class="card-b" style="font-size:.88rem;line-height:1.9">
        <p>النظام الجديد يصل إلى القالب هكذا:
           <strong>مستخدم ← شخص ← موقع وظيفي ← مسمى ← قالب</strong>. وهذه حلقاته اليوم:</p>
        <table style="width:100%;border-collapse:collapse;margin-top:8px">
            <tbody>
            <?php
            $bridge = array(
                array('مستخدمون نشطون لهم موقع وظيفي', $usersPos, $usersActive),
                array('أشخاص مسجلون', $persons, $usersActive),
                array('مواقع وظيفية مسجلة', $positions, $usersActive),
                array('مسميات لها قالب', $titlesTpl, $titlesAll),
            );
            foreach ($bridge as $b):
                $ok = ($b[2] > 0 && $b[1] >= $b[2]);
            ?>
                <tr style="border-bottom:1px solid #eee">
                    <td style="padding:6px"><?php echo e($b[0]); ?></td>
                    <td style="padding:6px;width:120px;font-weight:800;color:<?php echo $ok ? '#27ae60' : '#c0392b'; ?>">
                        <?php echo $b[1] . ' / ' . $b[2]; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($usersPos === 0): ?>
        <p style="margin-top:10px;color:#c0392b"><strong>الجسر مقطوع:</strong>
           لا مستخدم واحد يصله النظام الجديد — فالقوالب الممتلئة لا يقرؤها أحد،
           ولا يمكن تشغيل الظل ولا بدء عداد الأربعة عشر يوما حتى يبنى.</p>
        <?php endif; ?>
    </div>
</div>

<div class="card" style="margin-top:16px">
    <div class="card-h"><i class="fas fa-layer-group"></i> القوالب بطبقاتها</div>
    <div class="card-b" style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;font-size:.88rem">
            <thead><tr style="text-align:right;border-bottom:2px solid #ddd">
                <th style="padding:8px">الطبقة</th><th style="padding:8px">قوالب</th><th style="padding:8px">بنود منشورة</th>
            </tr></thead>
            <tbody>
            <?php
            $kindLabel = array('relation' => 'نوع العلاقة', 'family' => 'العائلة الوظيفية',
                'level' => 'المستوى التنظيمي', 'title' => 'المسمى', 'assignment' => 'التكليف');
            foreach ($tplBreak as $k): ?>
                <tr style="border-bottom:1px solid #eee">
                    <td style="padding:8px"><?php echo e($kindLabel[$k['tpl_kind']] ?? $k['tpl_kind']); ?></td>
                    <td style="padding:8px"><?php echo (int) $k['tpls']; ?></td>
                    <td style="padding:8px;font-weight:700"><?php echo number_format((int) $k['items']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($topTitles): ?>
        <div style="margin-top:12px;font-weight:700">أثقل قوالب المسمى</div>
        <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:6px">
            <?php foreach ($topTitles as $t): ?>
                <span style="padding:4px 10px;border:1px solid #ddd;border-radius:14px;font-size:.82rem">
                    <?php echo e($t['key_code']); ?> · <strong><?php echo (int) $t['items']; ?></strong></span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="card" style="margin-top:16px">
    <div class="card-h"><i class="fas fa-code-compare"></i> ميزان الظل</div>
    <div class="card-b" style="font-size:.88rem;line-height:1.9">
        <?php if ($diffs === 0): ?>
            <p><strong>لا فرق مسجلا</strong> — والعداد لم يبدأ بعد لأن الظل لا يعمل
               (يحتاج جسر الوصول أعلاه). أول تشغيل للظل يبدأ القياس.</p>
        <?php else: ?>
            <p>آخر فرق: <strong><?php echo e($lastDiffAt); ?></strong> ·
               سلسلة الصفر حتى اليوم: <strong><?php echo (int) $streak; ?></strong> يوما (المطلوب 14).</p>
            <table style="width:100%;border-collapse:collapse;margin-top:8px;font-size:.84rem">
                <thead><tr style="text-align:right;border-bottom:2px solid #ddd">
                    <th style="padding:6px">المستخدم</th><th style="padding:6px">الشاشة</th><th style="padding:6px">الفعل</th>
                    <th style="padding:6px">القديم</th><th style="padding:6px">الجديد</th><th style="padding:6px">التفصيل</th>
                </tr></thead>
                <tbody>
                <?php foreach ($diffRows as $d): ?>
                    <tr style="border-bottom:1px solid #eee">
                        <td style="padding:6px">u<?php echo (int) $d['user_id']; ?></td>
                        <td style="padding:6px"><?php echo e($d['module_code']); ?></td>
                        <td style="padding:6px"><?php echo e($d['action']); ?></td>
                        <td style="padding:6px"><?php echo $d['legacy_decision'] ? 'سمح' : 'منع'; ?></td>
                        <td style="padding:6px"><?php echo $d['derived_decision'] ? 'سمح' : 'منع'; ?></td>
                        <td style="padding:6px;color:#666"><?php echo e(mb_substr((string) $d['detail'], 0, 60)); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<div class="card" style="margin-top:16px">
    <div class="card-h"><i class="fas fa-circle-info"></i> من أين تتحكم</div>
    <div class="card-b" style="font-size:.86rem;line-height:2">
        <p><strong>المفتاح الحاكم:</strong> <code>EMS_PERM_SOURCE</code> في <code>.env</code> —
           <code>legacy</code> (القديم يقرر · حالنا) أو <code>derived</code> (الجديد يقرر).
           <strong>لا تقلبه قبل صفر فرق أربعة عشر يوما.</strong></p>
        <p><strong>إعادة بناء القوالب من الواقع:</strong>
           <code>php tools/perm_templates_build.php --diff</code> للمعاينة ثم <code>--apply</code>.
           آمن الإعادة، ويرفض التطبيق إن اختلف اتحاد الطبقات عن صلاحيات الدور بندا واحدا.</p>
        <p><strong>الصلاحيات الفعلية اليوم:</strong> شاشة «صلاحيات الأدوار» في لوحة الشركة —
           هي الحاكمة ما دام العلم <code>legacy</code>.</p>
        <p><strong>تفسير أي منع:</strong> شاشة «تفسير مصدر الصلاحية» (الحوكمة) تعرض السلسلة
           خطوة خطوة: أالشاشة مسجلة؟ أللدور صلاحية؟ أالرابط مربوط؟</p>
        <p><strong>هذه الشاشة قرائية بحتة</strong> — لا تكتب صفا ولا تقلب علما.</p>
    </div>
</div>

<?php require_once __DIR__ . '/includes/layout_foot.php'; ?>
