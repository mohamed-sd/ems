<?php
/**
 * includes/exec_indicator_engine.php — محرّكُ مؤشِّراتِ القيادةِ المشترك
 * ───────────────────────────────────────────────────────────────────────────
 * **محرّكٌ واحدٌ للَّوحتين** (‏`CEO-01` و`VP-01` نصًّا: «نفسُ المحرّكِ العامِّ
 * وDefault Scope يتغيّر بالنائب») — Grain: **مؤشِّرٌ × محورٌ × نطاق**.
 *
 * ◆ **أحدَ عشرَ محورًا، وكلُّ مؤشِّرٍ يذكر معادلتَه ومصدرَه في صفِّه** —
 *   فلا رقمَ بلا قاعدةٍ (قاعدةُ الجسر: لا رقمَ بلا مصدر):
 *   - الحيُّ يُقاس من سجلِّه الحيِّ لحظةَ الطلب (عقودٌ · تمويلٌ · أصولٌ ·
 *     مخاطرُ · طلباتٌ · سياساتٌ · تسوياتُ موردين · إقفالاتُ تشغيل).
 *   - والدوريُّ من آخرِ لقطةِ إقفالِ فترةٍ معتمدةٍ (`exec_board_snapshots`)
 *     **باسمِ فترتِها**، واتجاهُه بمقارنةِ اللقطةِ التي قبلَها — لا يُعاد
 *     حسابُ المُقفَلِ ولا يُختلَق اتجاهٌ لِما لا سابقةَ له.
 *   - وكودُ المؤشِّرِ من كتالوجِ النِّسبِ المعتمدِ (`fin_ratio_targets`)
 *     حيث يُطابق — وما لا كودَ له بعدُ يقول «غير مفهرس بعد».
 * ◆ **مالكُ كلِّ محورٍ مسمًّى برمزِ وحدتِه** — به يُفصَل نطاقُ النائبِ
 *   (Default Scope) عن نطاقِ الشركةِ كلِّها، والرؤيةُ أوسعُ من الصلاحية.
 * ◆ قراءةٌ صِرفٌ `select` معزولًا والتجميعُ في الذاكرة — صفرُ حرفِ SQL
 *   على جدولِ مستأجِرٍ (GAP-29).
 */

if (!function_exists('ems_exec_indicator_axes')) {

    /**
     * يُرجع المحاورَ الأحدَ عشرَ: لكلِّ محورٍ (اسمٌ، رمزُ وحدةٍ مالكةٍ، اسمُ
     * مالكٍ، صفوفُ مؤشِّراتٍ) — والصفُّ: كود، اسم، قيمة، وحدة، مستهدف،
     * انحراف/اتجاه، حالة، رابطُ نزولٍ، آخرُ تحديث، معادلةٌ ومصدر.
     */
    function ems_exec_indicator_axes($gate, $conn)
    {
        $today = (string) ($conn->query('SELECT CURDATE()')->fetch_row()[0]);
        $thisMonth = substr($today, 0, 7);

        /* آخرُ لقطتَي إقفالٍ معتمدتَين — للدوريِّ واتجاهِه */
        $snapNew = null; $snapOld = null;
        try {
            $snaps = $gate->select('exec_board_snapshots', array('orderBy' => 'period DESC', 'limit' => 6));
            foreach ($snaps as $s0) {
                if ((string) $s0['status'] !== 'معتمد') { continue; }
                if ($snapNew === null) { $snapNew = $s0; }
                elseif ($snapOld === null) { $snapOld = $s0; break; }
            }
        } catch (\Throwable $t) { error_log('exec_engine snaps: ' . $t->getMessage()); }
        $sp = $snapNew !== null ? (string) $snapNew['period'] : '';
        $snapVal = function ($k) use ($snapNew) {
            return $snapNew !== null ? (string) $snapNew[$k] : 'لا لقطة اقفال معتمدة بعد';
        };
        /* الاتجاهُ للدوريِّ الرقميِّ وحدَه — وما لا سابقةَ له «بلا سابقة» */
        $trend = function ($k) use ($snapNew, $snapOld) {
            if ($snapNew === null || $snapOld === null) { return 'بلا سابقة تقاس'; }
            $a = (float) preg_replace('/[^0-9.\-]/', '', (string) $snapNew[$k]);
            $b = (float) preg_replace('/[^0-9.\-]/', '', (string) $snapOld[$k]);
            if ($a > $b) { return 'صاعد عن ' . $snapOld['period']; }
            if ($a < $b) { return 'نازل عن ' . $snapOld['period']; }
            return 'ثابت عن ' . $snapOld['period'];
        };

        /* كتالوجُ المؤشِّراتِ المعتمدُ — الكودُ والحدُّ حيث يُطابقُ اسمًا */
        $cat = array();
        try {
            foreach ($gate->select('fin_ratio_targets', array('limit' => 200)) as $c0) {
                if ((int) $c0['active'] !== 1) { continue; }
                $cat[(string) $c0['ratio_code']] = $c0;
            }
        } catch (\Throwable $t) { error_log('exec_engine catalog: ' . $t->getMessage()); }

        /* القياساتُ الحيّة */
        $liveContracts = 0; $liveContractsAll = 0;
        try {
            foreach ($gate->select('contracts', array('columns' => array('id', 'status'), 'limit' => 3000)) as $x0) {
                $liveContractsAll++;
                $st = (string) $x0['status'];
                if ($st !== 'closed' && $st !== 'cancelled' && $st !== 'terminated') { $liveContracts++; }
            }
        } catch (\Throwable $t) { error_log('exec_engine contracts: ' . $t->getMessage()); }

        $finActive = 0; $finOutByCur = array();
        try {
            foreach ($gate->select('financing_operations', array('limit' => 2000)) as $x0) {
                $st = (string) $x0['state'];
                if ($st === 'closed' || $st === 'final_closed' || $st === 'cancelled') { continue; }
                $finActive++;
                $cur = (string) ($x0['currency'] !== '' ? $x0['currency'] : 'SDG');
                $finOutByCur[$cur] = (isset($finOutByCur[$cur]) ? $finOutByCur[$cur] : 0) + (float) $x0['outstanding_balance'];
            }
        } catch (\Throwable $t) { error_log('exec_engine financing: ' . $t->getMessage()); }
        $fmtCur = function ($m0) {
            $o = array();
            foreach ($m0 as $cur => $v) { $o[] = number_format($v, 0) . ' ' . $cur; }
            return $o ? implode(' و', $o) : '0';
        };

        $nEquip = 0;
        try { $nEquip = count($gate->select('equipments', array('columns' => array('id'), 'limit' => 5000))); }
        catch (\Throwable $t) { error_log('exec_engine equip: ' . $t->getMessage()); }

        /* ◆ الإقفالُ الشهريُّ يُقرأ من سلطتِه `fin_financial_periods` لا من
           جدولِ محاضرِ الإقفالِ الشاشيِّ — فالحكمُ المسجَّلُ (GAP-24 ·
           gov_path_rulings) يسمّي ذاك NON_AUTHORITATIVE، وقارئُ إنتاجٍ جديدٌ
           له يعيد فتحَ المسربِ (رصدَته سقّاطةُ path_rulings) — ولا يُكتب
           اسمُه هنا حرفًا لأنَّ عدّادَ القرّاءِ يعدُّ الذِّكر. */
        $nCloseRows = 0;
        try {
            foreach ($gate->select('fin_financial_periods',
                array('columns' => array('period_type', 'state'), 'limit' => 5000)) as $x0) {
                if ((string) $x0['period_type'] !== 'month') { continue; }
                if (in_array((string) $x0['state'], array('soft_closed', 'closed', 'locked'), true)) { $nCloseRows++; }
            }
        }
        catch (\Throwable $t) { error_log('exec_engine close: ' . $t->getMessage()); }

        $riskOpen = 0; $riskCrit = 0;
        try {
            foreach ($gate->select('risk_register', array('columns' => array('state', 'current_level'), 'limit' => 5000)) as $x0) {
                if ((string) $x0['state'] === 'closed') { continue; }
                $riskOpen++;
                if ((string) $x0['current_level'] === 'حرج') { $riskCrit++; }
            }
        } catch (\Throwable $t) { error_log('exec_engine risk: ' . $t->getMessage()); }

        $reqPending = 0;
        try {
            foreach ($gate->select('fin_requests', array('columns' => array('state'), 'limit' => 5000)) as $x0) {
                $st = (string) $x0['state'];
                if ($st === 'draft' || $st === 'under_review' || $st === 'pending_approval') { $reqPending++; }
            }
        } catch (\Throwable $t) { error_log('exec_engine req: ' . $t->getMessage()); }

        $polActive = 0;
        try {
            foreach ($gate->select('dept_policies', array('columns' => array('state'), 'limit' => 2000)) as $x0) {
                $st = (string) $x0['state'];
                if ($st === 'active' || $st === 'approved') { $polActive++; }
            }
        } catch (\Throwable $t) { error_log('exec_engine pol: ' . $t->getMessage()); }

        $supStlMonth = 0;
        try {
            foreach ($gate->select('settlements', array('columns' => array('party_type', 'period_from', 'is_deleted'), 'limit' => 5000)) as $x0) {
                if ((string) $x0['party_type'] !== 'supplier') { continue; }
                if ((int) (isset($x0['is_deleted']) ? $x0['is_deleted'] : 0) === 1) { continue; }
                if (substr((string) $x0['period_from'], 0, 7) === $thisMonth) { $supStlMonth++; }
            }
        } catch (\Throwable $t) { error_log('exec_engine stl: ' . $t->getMessage()); }

        /* صفُّ مؤشِّرٍ — الكودُ من الكتالوجِ إن طابقَ الاسمُ وإلا «غير مفهرس بعد» */
        $mk = function ($name, $value, $unit, $target, $trendTxt, $status, $link, $updated, $src) use ($cat) {
            $code = 'غير مفهرس بعد';
            foreach ($cat as $rc => $c0) {
                if ((string) $c0['name_ar'] === $name) { $code = $rc; if ($target === '' && (string) $c0['limit_text'] !== '') { $target = (string) $c0['limit_text']; } break; }
            }
            return array('code' => $code, 'name' => $name, 'value' => $value, 'unit' => $unit,
                         'target' => $target !== '' ? $target : 'بلا مستهدف مسجل في الكتالوج',
                         'trend' => $trendTxt, 'status' => $status, 'link' => $link,
                         'updated' => $updated, 'src' => $src);
        };
        $liveAt = 'الان، لحظة الطلب';
        $snapAt = $sp !== '' ? ('لقطة اقفال ' . $sp) : 'لا لقطة معتمدة';

        return array(
            array('axis' => 'المحفظة والعقود', 'unit_code' => 'sales', 'owner' => 'المبيعات والعقود', 'items' => array(
                $mk('العقود السارية', number_format($liveContracts), 'عقد', '', 'بلا سابقة تقاس', $liveContracts > 0 ? 'نشط' : 'فارغ', 'Contracts/contracts.php', $liveAt, 'عد سجل العقود الذي حالته ليست اقفالا ولا الغاء'),
                $mk('قيمة المحفظة', $snapVal('portfolio_value'), 'بعملتها', '', $trend('portfolio_value'), 'دوري', 'Portal/ceo_contracts.php', $snapAt, 'من لقطة اقفال الفترة المعتمدة كما اقفلت'),
            )),
            array('axis' => 'الايراد والتحصيل', 'unit_code' => 'finance', 'owner' => 'المالية والخزينة', 'items' => array(
                $mk('الايراد المعترف به', $snapVal('recognized_revenue'), 'بعملته', '', $trend('recognized_revenue'), 'دوري', 'Finance/reports.php', $snapAt, 'من لقطة اقفال الفترة المعتمدة'),
                $mk('التحصيل', $snapVal('collection'), 'بعملته', '', $trend('collection'), 'دوري', 'Finance/reports.php', $snapAt, 'من لقطة اقفال الفترة المعتمدة'),
                $mk('الذمم المتأخرة', $snapVal('overdue_receivables'), 'بعملتها', '', $trend('overdue_receivables'), 'دوري', 'Finance/reports.php', $snapAt, 'من لقطة اقفال الفترة المعتمدة'),
            )),
            array('axis' => 'السيولة والتدفق', 'unit_code' => 'finance', 'owner' => 'المالية والخزينة', 'items' => array(
                $mk('التدفق النقدي المتوقع', $snapVal('expected_cashflow'), 'بعملته', '', $trend('expected_cashflow'), 'دوري', 'Finance/reports.php', $snapAt, 'من لقطة اقفال الفترة المعتمدة'),
            )),
            array('axis' => 'التمويل والملكية', 'unit_code' => 'financing', 'owner' => 'التمويل والملكية', 'items' => array(
                $mk('عمليات التمويل النشطة', number_format($finActive), 'عملية', '', 'بلا سابقة تقاس', $finActive > 0 ? 'نشط' : 'فارغ', 'Financing/fin_portfolio_board.php', $liveAt, 'عد سجل عمليات التمويل غير المقفلة وغير الملغاة'),
                $mk('الرصيد القائم بعملته', $fmtCur($finOutByCur), 'بعملته', '', 'بلا سابقة تقاس', 'نشط', 'Financing/fin_portfolio_board.php', $liveAt, 'مجموع الرصيد القائم في سجل العمليات لكل عملة على حدة'),
            )),
            array('axis' => 'الاصول والجاهزية', 'unit_code' => 'fleet', 'owner' => 'الاسطول', 'items' => array(
                $mk('الاصول المسجلة', number_format($nEquip), 'اصل', '', 'بلا سابقة تقاس', 'نشط', 'Equipments/equipments.php', $liveAt, 'عد سجل المعدات'),
                $mk('المعدات العاملة', $snapVal('working_equipment'), 'معدة', '', 'بلا سابقة تقاس', 'دوري', 'Fleet/readiness_board.php', $snapAt, 'من لقطة اقفال الفترة المعتمدة'),
                $mk('نسبة الجاهزية', $snapVal('readiness_pct'), 'نسبة', '', $trend('readiness_pct'), 'دوري', 'Fleet/readiness_board.php', $snapAt, 'من لقطة اقفال الفترة المعتمدة'),
            )),
            array('axis' => 'التشغيل والانتاج', 'unit_code' => 'ops', 'owner' => 'التشغيل', 'items' => array(
                $mk('الوحدات المعتمدة', $snapVal('approved_units'), 'وحدة', '', $trend('approved_units'), 'دوري', 'Timesheet/timesheet.php', $snapAt, 'من لقطة اقفال الفترة المعتمدة'),
                $mk('الفترات الشهرية المقفلة', number_format($nCloseRows), 'فترة', '', 'بلا سابقة تقاس', 'نشط', 'Operations/operations.php', $liveAt, 'عد الفترات الشهرية المقفلة من سجل الفترات المالية — السلطة المحكومة'),
            )),
            array('axis' => 'الربحية والهامش', 'unit_code' => 'finance', 'owner' => 'المالية والخزينة', 'items' => array(
                $mk('هامش الربح', $snapVal('margin_pct'), 'نسبة', '', $trend('margin_pct'), 'دوري', 'Finance/reports.php', $snapAt, 'من لقطة اقفال الفترة المعتمدة'),
            )),
            array('axis' => 'المخاطر', 'unit_code' => 'risk_mgmt', 'owner' => 'ادارة المخاطر', 'items' => array(
                $mk('المخاطر المفتوحة', number_format($riskOpen), 'خطر', '', 'بلا سابقة تقاس', $riskCrit > 0 ? 'فيه حرج' : 'تحت النظر', 'Risk/risk_register.php', $liveAt, 'عد سجل المخاطر غير المقفلة'),
                $mk('الحرجة منها', number_format($riskCrit), 'خطر', 'الصفر هو المامول', 'بلا سابقة تقاس', $riskCrit > 0 ? 'يستدعي القمة' : 'نظيف', 'Risk/risk_register.php', $liveAt, 'المخاطر المفتوحة التي مستواها حرج بمفردة السجل نفسه'),
            )),
            array('axis' => 'الاعتمادات والقرار', 'unit_code' => 'governance', 'owner' => 'الحوكمة والالتزام', 'items' => array(
                $mk('طلبات مالية معلقة', number_format($reqPending), 'طلب', '', 'بلا سابقة تقاس', $reqPending > 0 ? 'بانتظار قرار' : 'نظيف', 'FinRequests/requests_board.php', $liveAt, 'عد بوابة الطلبات المالية بحالات المسودة والمراجعة وانتظار الاعتماد'),
                $mk('اعتمادات معلقة بالفترة', $snapVal('pending_approvals'), 'اعتماد', '', 'بلا سابقة تقاس', 'دوري', 'Portal/ceo_approvals.php', $snapAt, 'من لقطة اقفال الفترة المعتمدة'),
            )),
            array('axis' => 'الالتزام والسياسات', 'unit_code' => 'governance', 'owner' => 'الحوكمة والالتزام', 'items' => array(
                $mk('سياسات سارية', number_format($polActive), 'سياسة', '', 'بلا سابقة تقاس', 'نشط', 'Governance/governance.php', $liveAt, 'عد سجل سياسات الادارات بحالتي السريان والاعتماد'),
            )),
            array('axis' => 'الموردون', 'unit_code' => 'suppliers', 'owner' => 'الموردون', 'items' => array(
                $mk('تسويات موردين هذا الشهر', number_format($supStlMonth), 'تسوية', '', 'بلا سابقة تقاس', 'نشط', 'Suppliers/supplier_targets.php', $liveAt, 'عد تسويات الموردين غير المحذوفة الواقعة في شهر يوم القاعدة'),
            )),
        );
    }

    /** نطاقُ نيابةِ المستخدمِ: رموزُ الوحداتِ التي ينوب فيها بصفةٍ سارية */
    function ems_exec_deputy_scope($gate, $empId)
    {
        $unitCodeById = array(); $scope = array();
        try { foreach ($gate->select('org_units', array('columns' => array('unit_id', 'unit_code'), 'limit' => 200)) as $u0) { $unitCodeById[(int) $u0['unit_id']] = (string) $u0['unit_code']; } }
        catch (\Throwable $t) { error_log('exec_engine units: ' . $t->getMessage()); }
        if ((int) $empId <= 0) { return $scope; }
        try {
            foreach ($gate->select('org_assignments', array('columns' => array('deputy_person_id', 'org_unit_id', 'state'), 'limit' => 2000)) as $a0) {
                if ((int) $a0['deputy_person_id'] !== (int) $empId) { continue; }
                if ((string) $a0['state'] !== 'active') { continue; }
                $uid0 = (int) $a0['org_unit_id'];
                if (isset($unitCodeById[$uid0])) { $scope[$unitCodeById[$uid0]] = true; }
            }
        } catch (\Throwable $t) { error_log('exec_engine scope: ' . $t->getMessage()); }
        return $scope;
    }
}
