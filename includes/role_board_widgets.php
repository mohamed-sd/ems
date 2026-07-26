<?php
/**
 * قالب عرض مكوّنات لوحة الدور ②-⑦ (UX-01 §5) — يُضمَّن بعد تجهيز:
 *   $rb_tasks · $rb_approvals · $rb_alerts · $rb_quick · $rb_recent
 *   $rb_pulse (labels/in/out) · $rb_pulse_title · $rb_pulse_series (اسما السلسلتين)
 * بنيةٌ واحدةٌ لكل اللوحات — «تختلف محتوياتُها لا بنيتُها».
 * تحميل chart.umd.min.js مسؤولية الصفحة المضمِّنة (بعد هذا القالب).
 */
if (!isset($rb_pulse_series)) { $rb_pulse_series = array('وارد', 'صادر'); }
?>
    <!-- ── المكوّنات ②③④⑤ — أربعةُ صناديقَ ثابتةِ البنية (UX-01 §5) ── -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:12px;margin-top:14px">

        <div class="card"><div class="card-body">
            <h5 style="margin:0 0 10px"><i class="fas fa-list-check"></i> مهامي</h5>
            <?php if (empty($rb_tasks)): ?>
                <p class="text-muted" style="font-size:13px;margin:0">لا مهامَّ عاجلةً الآن ✔</p>
            <?php else: foreach ($rb_tasks as $t): ?>
                <a href="<?php echo htmlspecialchars($t['href']); ?>" style="display:flex;justify-content:space-between;align-items:center;padding:8px 4px;border-bottom:1px solid #f1f5f9;text-decoration:none;color:inherit">
                    <span style="font-size:13px"><i class="<?php echo htmlspecialchars($t['icon']); ?>" style="opacity:.6;margin-left:6px"></i><?php echo htmlspecialchars($t['label']); ?></span>
                    <span class="badge badge-primary"><?php echo intval($t['count']); ?></span>
                </a>
            <?php endforeach; endif; ?>
        </div></div>

        <div class="card"><div class="card-body">
            <h5 style="margin:0 0 10px"><i class="fas fa-inbox"></i> موافقاتي</h5>
            <?php if (empty($rb_approvals)): ?>
                <p class="text-muted" style="font-size:13px;margin:0">لا شيءَ ينتظر اعتمادك ✔</p>
            <?php else: foreach ($rb_approvals as $a): ?>
                <a href="<?php echo htmlspecialchars($a['href']); ?>" style="display:flex;justify-content:space-between;align-items:center;padding:8px 4px;border-bottom:1px solid #f1f5f9;text-decoration:none;color:inherit">
                    <span style="font-size:13px"><i class="<?php echo htmlspecialchars($a['icon']); ?>" style="opacity:.6;margin-left:6px"></i><?php echo htmlspecialchars($a['label']); ?></span>
                    <span class="badge badge-warning"><?php echo intval($a['count']); ?></span>
                </a>
            <?php endforeach; endif; ?>
        </div></div>

        <div class="card"><div class="card-body">
            <h5 style="margin:0 0 10px"><i class="fas fa-bell"></i> التنبيهات</h5>
            <?php if (empty($rb_alerts)): ?>
                <p class="text-muted" style="font-size:13px;margin:0">لا متأخرَ ولا حرجَ الآن ✔</p>
            <?php else: foreach ($rb_alerts as $al): $rbTone = $al['tone'] === 'err' ? '#991b1b' : '#92400e'; ?>
                <a href="<?php echo htmlspecialchars($al['href']); ?>" style="display:flex;justify-content:space-between;align-items:center;padding:8px 4px;border-bottom:1px solid #f1f5f9;text-decoration:none;color:<?php echo $rbTone; ?>">
                    <span style="font-size:13px"><i class="fas fa-triangle-exclamation" style="margin-left:6px"></i><?php echo htmlspecialchars($al['label']); ?></span>
                    <span class="badge badge-danger"><?php echo intval($al['count']); ?></span>
                </a>
            <?php endforeach; endif; ?>
        </div></div>

        <div class="card"><div class="card-body">
            <h5 style="margin:0 0 10px"><i class="fas fa-bolt"></i> إنشاء سريع</h5>
            <?php if (empty($rb_quick)): ?>
                <p class="text-muted" style="font-size:13px;margin:0">—</p>
            <?php else: foreach ($rb_quick as $qk): ?>
                <a href="../<?php echo htmlspecialchars($qk['route']); ?>" class="btn-save" style="display:block;text-align:center;text-decoration:none;margin-bottom:8px;padding:8px">
                    <i class="<?php echo htmlspecialchars($qk['icon'] ?: 'fa fa-link'); ?>"></i> <?php echo htmlspecialchars($qk['label_ar']); ?>
                </a>
            <?php endforeach; endif; ?>
        </div></div>
    </div>

    <!-- ── المكوّنان ⑥⑦ — نبض الأداء + عملي الأخير ── -->
    <div style="display:grid;grid-template-columns:2fr 1fr;gap:12px;margin-top:14px">
        <div class="card"><div class="card-body">
            <h5 style="margin:0 0 10px"><i class="fas fa-wave-square"></i> <?php echo htmlspecialchars($rb_pulse_title ?? 'نبض الأداء (7 أيام)'); ?></h5>
            <canvas id="rbPulse" height="90"></canvas>
        </div></div>
        <div class="card"><div class="card-body">
            <h5 style="margin:0 0 10px"><i class="fas fa-clock-rotate-left"></i> عملي الأخير</h5>
            <?php if (empty($rb_recent)): ?>
                <p class="text-muted" style="font-size:13px;margin:0">لا نشاطَ مسجَّلًا بعد</p>
            <?php else: foreach ($rb_recent as $rc):
                $rcHref = preg_replace('#^.*?/ems/#', '../', (string)($rc['url'] ?? '')); if ($rcHref === '') { $rcHref = '#'; } ?>
                <a href="<?php echo htmlspecialchars($rcHref); ?>" style="display:block;padding:6px 4px;border-bottom:1px solid #f1f5f9;text-decoration:none;color:inherit;font-size:13px">
                    <?php echo htmlspecialchars((string)$rc['screen_name']); ?>
                    <span class="text-muted" style="float:left;font-size:11px"><?php echo htmlspecialchars(date('m/d H:i', strtotime((string)$rc['last_at']))); ?></span>
                </a>
            <?php endforeach; endif; ?>
        </div></div>
    </div>

    <script id="rbPulseInit">
    (function () {
        function draw() {
            if (typeof Chart === 'undefined') { return setTimeout(draw, 150); }
            new Chart(document.getElementById('rbPulse'), {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($rb_pulse['labels']); ?>,
                    datasets: [
                        { label: <?php echo json_encode($rb_pulse_series[0]); ?>, data: <?php echo json_encode($rb_pulse['in']); ?>,  backgroundColor: 'rgba(22,101,52,.75)' },
                        { label: <?php echo json_encode($rb_pulse_series[1]); ?>, data: <?php echo json_encode($rb_pulse['out']); ?>, backgroundColor: 'rgba(153,27,27,.7)' }
                    ]
                },
                options: { responsive: true, plugins: { legend: { position: 'bottom', rtl: true } }, scales: { y: { beginAtZero: true } } }
            });
        }
        draw();
    })();
    </script>
