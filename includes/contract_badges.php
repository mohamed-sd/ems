<?php
/**
 * شاراتُ العقد — «التزامٌ ينكسر» و«عقدٌ ينتهي» (CON-02 §7-② · ق-21)
 * ═══════════════════════════════════════════════════════════════════════════
 * **اشتقاقٌ حيٌّ عند فتح الشاشة** (قرارُ المالك 2026-07-28): صفرُ عمودِ حالةٍ
 * يبيت وصفرُ مهمةٍ دوريةٍ تُصان — تُحتسب لحظةَ العرض فلا تكذب أبدًا.
 * ومدخلاتُها كلُّها حاضرةٌ ورخيصة.
 *
 * ── متى تُرفع شارةُ الانكسار (ق-21) ──────────────────────────────────────
 * **عند استحالة اللحاق حسابيًّا** — لا عند التأخر. وسقفُ الطاقة:
 *
 *     المعداتُ المتعاقدُ عليها × ساعاتِ الوردية × الأيامِ المتبقية
 *
 * وهي طاقةٌ نظريةٌ **متفائلةٌ عمدًا**: تفترض كلَّ معدةٍ تعمل كلَّ ساعةٍ من كل
 * يومٍ بلا عطلٍ ولا صيانةٍ ولا توقف. فإن قالت «مستحيل» فهو **يقينٌ لا ظنّ**،
 * وصفرُ إنذارٍ كاذب. وهذا بالضبط ما يجعل الشارةَ تُصدَّق حين تظهر.
 *
 * والوحداتُ غيرُ الزمنية (طن · متر · نقلة) لا سقفَ طاقةٍ لها من ساعات الوردية،
 * فلا تُرفع لها الشارةُ — **ولا يُخترع لها سقفٌ بمعامل تحويلٍ لا نصَّ له**.
 */

if (!function_exists('contract_capacity_badges')) {
    /**
     * @return array{
     *   breaking: array<int,array{label:string,detail:string}>,
     *   ending: ?array{days:int,end:string},
     * }
     */
    function contract_capacity_badges($gate, $contractId, $asOf = null)
    {
        $contractId = intval($contractId);
        $asOf = ($asOf === null) ? date('Y-m-d') : $asOf;
        $out = array('breaking' => array(), 'ending' => null);

        $rows = $gate->scopedQuery(array('scope' => array('c' => 'contracts')),
            "SELECT c.id, c.actual_start, c.actual_end, c.daily_work_hours, c.shift_contract
               FROM contracts c WHERE {TENANT_SCOPE} AND c.is_deleted = 0 AND c.id = ? LIMIT 1",
            array($contractId));
        if (empty($rows)) { return $out; }
        $c = $rows[0];

        // ── شارةُ «عقدٌ ينتهي» ──
        $end = $c['actual_end'];
        if ($end !== null && $end !== '' && $end !== '0000-00-00') {
            $days = (int) floor((strtotime($end) - strtotime($asOf)) / 86400);
            if ($days >= 0 && $days <= 60) {
                $out['ending'] = array('days' => $days, 'end' => $end);
            }
        }

        // ── سقفُ الطاقة المتبقية ──
        if ($end === null || $end === '' || strtotime($end) <= strtotime($asOf)) { return $out; }
        $remainingDays = (int) floor((strtotime($end) - strtotime($asOf)) / 86400);
        if ($remainingDays <= 0) { return $out; }

        $shiftHours = (float) $c['daily_work_hours'];
        if ($shiftHours <= 0) { return $out; }   // بلا ساعاتِ ورديةٍ لا سقفَ يُحتسب

        $eq = $gate->scopedQuery(array('scope' => array('ce' => 'contractequipments')),
            "SELECT COUNT(*) n FROM contractequipments ce
              WHERE {TENANT_SCOPE} AND ce.contract_id = ?", array($contractId));
        $equipCount = empty($eq) ? 0 : (int) $eq[0]['n'];
        if ($equipCount <= 0) { return $out; }

        $ceiling = $equipCount * $shiftHours * $remainingDays;

        // ── الالتزاماتُ الزمنيةُ الباقية ──
        $cms = $gate->scopedQuery(array('scope' => array('cm' => 'contract_commitments')),
            "SELECT cm.id, cm.commitment_type, cm.unit_type, cm.qty, cm.period
               FROM contract_commitments cm
              WHERE {TENANT_SCOPE} AND cm.is_deleted = 0 AND cm.contract_ref = ?
                AND cm.party_scope = 'client' AND cm.unit_type = 'hour'
                AND cm.period = 'contract'", array($contractId));

        foreach ($cms as $cm) {
            $done = $gate->scopedQuery(array('scope' => array('fe' => 'fin_financial_events')),
                "SELECT COALESCE(SUM(fe.quantity),0) q FROM fin_financial_events fe
                  WHERE {TENANT_SCOPE} AND fe.is_deleted = 0 AND fe.event_status = 'active'
                    AND fe.event_type = 'revenue' AND fe.contract_id = ? AND fe.unit = 'hour'",
                array($contractId));
            $achieved = empty($done) ? 0.0 : (float) $done[0]['q'];
            $left = (float) $cm['qty'] - $achieved;
            if ($left <= 0) { continue; }
            if ($left > $ceiling) {
                $out['breaking'][] = array(
                    'label' => 'التزام ينكسر',
                    'detail' => 'الباقي ' . number_format($left, 0) . ' ساعة، وسقف الطاقة المتبقية '
                        . number_format($ceiling, 0) . ' ساعة فقط ('
                        . $equipCount . ' معدة × ' . rtrim(rtrim(number_format($shiftHours, 1), '0'), '.')
                        . 'س × ' . $remainingDays . ' يوما) — اللحاق مستحيل حسابيا حتى بطاقة كاملة',
                );
            }
        }
        return $out;
    }
}

if (!function_exists('contract_badges_html')) {
    /**
     * تصييرُ الشارات — نصٌّ يشرح سببَه، لا لونٌ يُترك للتخمين.
     *
     * والمظهرُ يخرج **مع المكوِّن مرةً واحدةً في الطلب**: الشارةُ تُعرض في أكثر
     * من شاشة (الجزاءات · ملفُّ العقد)، ونسخُ قواعدها في كل شاشةٍ يجعل لونَ
     * الإنذار يفترق بين بابين — فيصير المظهرُ ملكَ المكوِّن لا ملكَ الشاشة.
     */
    function contract_badges_html(array $b)
    {
        static $styled = false;
        $h = '';
        if (!$styled && (!empty($b['breaking']) || $b['ending'] !== null)) {
            $styled = true;
            $h .= '<style>'
                . '.ems-badge-break{display:inline-block;padding:4px 12px;border-radius:20px;font-weight:800;'
                . 'background:#ffebee;color:#b71c1c;border:1px solid #ef9a9a;}'
                . '.ems-badge-end{display:inline-block;padding:4px 12px;border-radius:20px;font-weight:700;'
                . 'background:#fff8e1;color:#a06b00;border:1px solid #ffe082;}'
                . '.ems-badge-why{color:#666;}'
                . '.cd-badges,.pen-badges{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:12px;}'
                . '</style>';
        }
        foreach ($b['breaking'] as $x) {
            $h .= '<span class="ems-badge-break" title="' . htmlspecialchars($x['detail'], ENT_QUOTES, 'UTF-8') . '">'
                . '<i class="fas fa-triangle-exclamation"></i> ' . htmlspecialchars($x['label'], ENT_QUOTES, 'UTF-8')
                . '</span> <small class="ems-badge-why">' . htmlspecialchars($x['detail'], ENT_QUOTES, 'UTF-8') . '</small>';
        }
        if ($b['ending'] !== null) {
            $h .= '<span class="ems-badge-end"><i class="fas fa-hourglass-half"></i> عقد ينتهي خلال '
                . intval($b['ending']['days']) . ' يوما (' . htmlspecialchars($b['ending']['end'], ENT_QUOTES, 'UTF-8') . ')</span>';
        }
        return $h;
    }
}
