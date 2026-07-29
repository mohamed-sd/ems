<?php
/**
 * includes/journey_bar.php — مصيِّر شريط الرحلة الموحّد
 * ─────────────────────────────────────────────────────────────────────────
 * الدستور §5 · §9 · UX-01 §6: «العملية الواحدة رحلةٌ مستنديةٌ واحدة … شريطُ
 * مراحلَ أعلى شاشتها: المرحلةُ الحالية مضاءةٌ، والتاليةُ باسم صاحبها، والسجلُّ
 * تبويبٌ داخلي — فيرى المبتدئ أين هو وماذا بعد».
 *
 * مكوّنُ **عرضٍ خالص**: لا قاعدةَ بيانات ولا منطقَ إدارة. كلُّ إدارةٍ تبني
 * مصفوفتَها من بياناتها (بانيةُ الطلب المالي: finreq_journey في
 * FinRequests/_finreq_helpers.php) وتمرّرها هنا — فيخدم الشريطُ الطلبَ والبلاغَ
 * والوحدةَ بلا نسخٍ ثلاث.
 *
 * قاعدةُ عدم التلفيق نافذة: ما لا مصدرَ له لا يُعرض — مرحلةٌ بلا تاريخٍ تُعرض
 * بلا تاريخ، ومالكٌ غيرُ معروفٍ لا يُخترع له اسم.
 *
 * ── عقد المصفوفة ────────────────────────────────────────────────────────
 * array(
 *   'stages' => array(                       // مرتّبةً بترتيب الرحلة
 *      array(
 *        'label'  => 'اعتماد الإدارة',        // إلزامي — بلغة المهمة
 *        'status' => 'done|current|todo|off', // إلزامي
 *        'at'     => '2026-07-17 20:05:14',   // اختياري — لحظة إنجازها
 *        'owner'  => 'مدير إدارة الصيانة',    // اختياري — صاحبُها
 *        'note'   => 'نصٌّ قصير',              // اختياري
 *        'overdue'=> true,                    // اختياري — تجاوزت مهلتها
 *      ), …
 *   ),
 *   'next'   => array('label','owner','since','overdue'),   // اختياري
 *   'banner' => array('kind'=>'return|stop|done','title','text','meta',
 *                     'action'=>array('href','label')),      // اختياري
 * )
 */

if (!function_exists('ems_journey_ago')) {
    /** فارقٌ زمنيٌّ بالعربية من تاريخٍ إلى الآن — «منذ يومين» لا «2 days ago». */
    function ems_journey_ago($when)
    {
        if (empty($when)) { return ''; }
        $ts = is_numeric($when) ? intval($when) : strtotime((string) $when);
        if (!$ts) { return ''; }
        $d = time() - $ts;
        $future = $d < 0;
        $d = abs($d);
        if ($d < 60)      { $s = 'أقل من دقيقة'; }
        elseif ($d < 3600) { $n = (int) ($d / 60);   $s = ems_journey_plural($n, 'دقيقة', 'دقيقتين', 'دقائق', 'دقيقةً'); }
        elseif ($d < 86400) { $n = (int) ($d / 3600); $s = ems_journey_plural($n, 'ساعة', 'ساعتين', 'ساعات', 'ساعةً'); }
        elseif ($d < 2592000) { $n = (int) ($d / 86400); $s = ems_journey_plural($n, 'يوم', 'يومين', 'أيام', 'يومًا'); }
        else { $n = (int) ($d / 2592000); $s = ems_journey_plural($n, 'شهر', 'شهرين', 'أشهر', 'شهرًا'); }
        return ($future ? 'بعد ' : 'منذ ') . $s;
    }
}

if (!function_exists('ems_journey_plural')) {
    /**
     * تصريفُ العدد العربي على أربع صيغ:
     *   1 → مفردٌ بلا عدد (ساعة) · 2 → مثنًّى بلا عدد (ساعتين)
     *   3–10 → عددٌ + جمعُ قلة (5 ساعات) · 11+ → عددٌ + تمييزٌ مفردٌ منصوب (13 ساعةً)
     * والأخيرةُ هي التي يخطئ فيها التوليدُ الآلي عادةً فيقول «13 ساعة».
     */
    function ems_journey_plural($n, $one, $two, $many, $accusative = null)
    {
        if ($n <= 1) { return $one; }
        if ($n === 2) { return $two; }
        if ($n <= 10) { return $n . ' ' . $many; }
        return $n . ' ' . ($accusative !== null ? $accusative : $one);
    }
}

if (!function_exists('ems_journey_bar')) {
    /**
     * يطبع الشريط. يعيد false ولا يطبع شيئًا إن خلت المراحل — فلا إطارَ فارغ.
     * @param array $j مصفوفة العقد أعلاه
     */
    function ems_journey_bar(array $j)
    {
        $stages = isset($j['stages']) && is_array($j['stages']) ? $j['stages'] : array();
        if (!$stages) { return false; }
        $e = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };

        echo '<div class="ems-journey" role="group" aria-label="مسار المعاملة">' . "\n";
        echo '  <div class="ems-journey-track">' . "\n";

        $i = 0;
        foreach ($stages as $s) {
            $i++;
            $status = isset($s['status']) ? (string) $s['status'] : 'todo';
            if (!in_array($status, array('done', 'current', 'todo', 'off'), true)) { $status = 'todo'; }
            $cls = 'ems-jstep is-' . $status;
            if ($status === 'current' && !empty($s['overdue'])) { $cls .= ' is-overdue'; }

            // الرمز: منجَزةٌ صحٌّ، والحاليةُ رقمُها، وما بعدها رقمُه باهتًا
            $glyph = ($status === 'done') ? '✓' : (string) $i;

            $aria = $status === 'done' ? 'مكتملة' : ($status === 'current' ? 'المرحلة الحالية' : ($status === 'off' ? 'لم تُبلَغ' : 'لاحقة'));

            echo '    <div class="' . $cls . '" aria-label="' . $e($s['label'] . ' — ' . $aria) . '">' . "\n";
            echo '      <div class="ems-jdot" aria-hidden="true">' . $e($glyph) . '</div>' . "\n";
            echo '      <div>' . "\n";
            echo '        <div class="ems-jlabel">' . $e($s['label']) . '</div>' . "\n";

            $meta = array();
            if (!empty($s['owner'])) { $meta[] = '<span class="ems-jowner">' . $e($s['owner']) . '</span>'; }
            if (!empty($s['at']))    { $meta[] = $e(ems_journey_ago($s['at'])); }
            if (!empty($s['note']))  { $meta[] = $e($s['note']); }
            if ($meta) {
                echo '        <div class="ems-jmeta">' . implode(' · ', $meta) . '</div>' . "\n";
            }
            echo '      </div>' . "\n";
            echo '    </div>' . "\n";
        }

        echo '  </div>' . "\n";

        // ── الخطوة التالية وصاحبها ──────────────────────────────────────
        if (!empty($j['next']) && is_array($j['next'])) {
            $n = $j['next'];
            echo '  <div class="ems-journey-next">' . "\n";
            echo '    <i class="fa fa-hourglass-half" aria-hidden="true"></i>' . "\n";
            echo '    <span>الخطوة التالية:</span>' . "\n";
            echo '    <span class="ems-jnext-owner">' . $e($n['label']) . '</span>' . "\n";
            if (!empty($n['owner'])) { echo '    <span>— ' . $e($n['owner']) . '</span>' . "\n"; }
            if (!empty($n['since'])) {
                $c = !empty($n['overdue']) ? ' is-overdue' : '';
                $t = ems_journey_ago($n['since']);
                if (!empty($n['overdue'])) { $t .= ' · تجاوزت المهلة'; }
                echo '    <span class="ems-jage' . $c . '">(' . $e($t) . ')</span>' . "\n";
            }
            echo '  </div>' . "\n";
        }

        // ── لافتةٌ: إعادةٌ بسببها · توقفٌ · اكتمال ────────────────────────
        if (!empty($j['banner']) && is_array($j['banner'])) {
            $b = $j['banner'];
            $kind = isset($b['kind']) && in_array($b['kind'], array('return', 'stop', 'done'), true) ? $b['kind'] : 'stop';
            $icon = $kind === 'return' ? 'fa-rotate-left' : ($kind === 'done' ? 'fa-circle-check' : 'fa-circle-exclamation');
            echo '  <div class="ems-journey-banner is-' . $kind . '">' . "\n";
            echo '    <i class="fa ' . $icon . '" aria-hidden="true"></i>' . "\n";
            echo '    <span>' . "\n";
            if (!empty($b['title'])) { echo '      <span class="ems-jb-title">' . $e($b['title']) . '</span>' . "\n"; }
            if (!empty($b['text']))  { echo '      ' . $e($b['text']) . "\n"; }
            if (!empty($b['meta']))  { echo '      <span class="ems-jb-meta">— ' . $e($b['meta']) . '</span>' . "\n"; }
            echo '    </span>' . "\n";
            if (!empty($b['action']['href']) && !empty($b['action']['label'])) {
                echo '    <a class="ems-jb-action" href="' . $e($b['action']['href']) . '">' . $e($b['action']['label']) . '</a>' . "\n";
            }
            echo '  </div>' . "\n";
        }

        echo '</div>' . "\n";
        return true;
    }
}
