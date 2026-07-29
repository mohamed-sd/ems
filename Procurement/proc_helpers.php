<?php
/**
 * Procurement/proc_helpers.php — دوال مساعدة مشتركة لوحدة المشتريات (proc_*).
 *
 * ملاحظات معمارية:
 *   • دوال نقية قابلة لإعادة الاستخدام فقط — إقلاع الجلسة/الصلاحيات يبقى داخل كل
 *     صفحة (نفس نمط وحدة الصيانة) لتفادي مشاكل ترتيب session_start/الإخراج.
 *   • قوائم المعدات/المشاريع تُقرأ من الجداول القائمة قراءةً فقط (لا كتابة، لا FK)
 *     ⇒ لا تأثير على النظام الحالي.
 *
 * @package EMS\Procurement
 */

if (!function_exists('proc_ctx')) {
    /** سياق المستخدم الحالي من الجلسة. */
    function proc_ctx()
    {
        $role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
        return array(
            'role'          => $role,
            'is_super'      => ($role === EMS_ROLE_SUPER_ADMIN),
            'company_id'    => isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0,
            'user_id'       => isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0,
        );
    }
}

if (!function_exists('proc_page_perms')) {
    /** حل صلاحيات صفحة المشتريات (super admin يملك الكل). */
    function proc_page_perms($conn, $code, $is_super)
    {
        $p = check_page_permissions($conn, $code);
        return array(
            'can_view'   => $is_super ? true : $p['can_view'],
            'can_add'    => $is_super ? true : $p['can_add'],
            'can_edit'   => $is_super ? true : $p['can_edit'],
            'can_delete' => $is_super ? true : $p['can_delete'],
        );
    }
}

if (!function_exists('proc_scope')) {
    /** شرط عزل الشركة لعمود معطى. */
    function proc_scope($col, $is_super, $company_id)
    {
        return $is_super ? '1=1' : ($col . ' = ' . intval($company_id));
    }
}

if (!function_exists('proc_gate')) {
    /**
     * K9-M1: بوابة العزل للوحدة — سياق الجلسة؛ وis_super يمرّ عبر القناة
     * الشرعية forAllTenants (يحفظ سلوكه القائم: رؤية عابرة مسجَّلة).
     * وسائط $conn/$company_id في الدوال أدناه بقيت للتوافق أثناء الدفعة —
     * البوابة مصدر الوصول الوحيد فعليًا.
     */
    function proc_gate($is_super = false)
    {
        $gate = ems_tenant_db();
        return $is_super ? $gate->forAllTenants('proc helpers super view') : $gate;
    }
}

if (!function_exists('proc_gen_code')) {
    /** توليد كود تسلسلي بسيط لكل شركة، مثل PRC-ITM-0001 (عبر البوابة). */
    function proc_gen_code($conn, $table, $prefix, $company_id)
    {
        // includeDeleted يطابق العدّ الأصلي (كان يعدّ كل صفوف الشركة بما فيها المؤرشف)
        $n = proc_gate(false)->count($table, array('includeDeleted' => true));
        return $prefix . '-' . str_pad((string)($n + 1), 4, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('proc_msg_banner')) {
    /** شريط رسالة النجاح/الخطأ من msg في الـ query string. */
    function proc_msg_banner()
    {
        if (empty($_GET['msg'])) {
            return;
        }
        $isSuccess = strpos($_GET['msg'], '✅') !== false;
        echo '<div class="success-message ' . ($isSuccess ? 'is-success' : 'is-error') . '">';
        echo '<i class="fas ' . ($isSuccess ? 'fa-check-circle' : 'fa-exclamation-circle') . '"></i> ';
        echo htmlspecialchars($_GET['msg']);
        echo '</div>';
    }
}

if (!function_exists('proc_options_from_rows')) {
    /** بناء <option> من صفوف (id + label جاهزان) — بديل التنفيذ بالنص الخام. */
    function proc_options_from_rows(array $rows, $selected = 0, $placeholder = '— اختر —')
    {
        $out = '<option value="">' . htmlspecialchars($placeholder) . '</option>';
        $selected = intval($selected);
        foreach ($rows as $r) {
            $id  = intval($r['id']);
            $lbl = isset($r['label']) ? (string)$r['label'] : '';
            $sel = ($id === $selected) ? ' selected' : '';
            $out .= '<option value="' . $id . '"' . $sel . '>' . htmlspecialchars($lbl) . '</option>';
        }
        return $out;
    }
}

if (!function_exists('proc_items_options')) {
    /** قائمة أصناف الكتالوج (proc_item) — عبر البوابة، والتسمية في PHP (مطابقة CONCAT الأصلية). */
    function proc_items_options($conn, $is_super, $company_id, $selected = 0)
    {
        $rows = proc_gate($is_super)->select('proc_item', array(
            'columns' => array('id', 'code', 'name'),
            'where'   => array('status' => 1),
            'orderBy' => 'name ASC',
        ));
        foreach ($rows as &$r) {
            $code = (string) $r['code'];
            $r['label'] = ($code === '' ? '' : $code . ' — ') . $r['name'];
        }
        unset($r);
        return proc_options_from_rows($rows, $selected, '— اختر صنفاً —');
    }
}

if (!function_exists('proc_suppliers_options')) {
    /** قائمة الموردين التشغيليين (proc_supplier) — عبر البوابة. */
    function proc_suppliers_options($conn, $is_super, $company_id, $selected = 0)
    {
        $rows = proc_gate($is_super)->select('proc_supplier', array(
            'columns' => array('id', 'name'),
            'where'   => array('status' => 1),
            'orderBy' => 'name ASC',
        ));
        foreach ($rows as &$r) { $r['label'] = $r['name']; }
        unset($r);
        return proc_options_from_rows($rows, $selected, '— اختر مورداً —');
    }
}

if (!function_exists('proc_warehouses_options')) {
    /** قائمة المخازن (proc_warehouse) — عبر البوابة. */
    function proc_warehouses_options($conn, $is_super, $company_id, $selected = 0)
    {
        $rows = proc_gate($is_super)->select('proc_warehouse', array(
            'columns' => array('id', 'name', 'type'),
            'where'   => array('status' => 1),
            'orderBy' => 'name ASC',
        ));
        foreach ($rows as &$r) { $r['label'] = $r['name'] . ' (' . $r['type'] . ')'; }
        unset($r);
        return proc_options_from_rows($rows, $selected, '— اختر مخزناً —');
    }
}

if (!function_exists('proc_lookup_names')) {
    /** أسماء قيم مرجعية حسب النوع (proc_lookup) — عبر البوابة. */
    function proc_lookup_names($conn, $is_super, $company_id, $type)
    {
        $rows = proc_gate($is_super)->select('proc_lookup', array(
            'columns' => array('name'),
            'where'   => array('type' => (string) $type, 'is_active' => 1),
            'orderBy' => 'name ASC',
        ));
        $out = array();
        foreach ($rows as $r) { $out[] = $r['name']; }
        return $out;
    }
}

if (!function_exists('proc_equipment_options')) {
    /** قائمة المعدات — قراءة فقط من equipments عبر البوابة (لا كتابة). */
    function proc_equipment_options($conn, $is_super, $company_id, $selected = 0)
    {
        // equipments بلا عمود is_deleted؛ يستخدم status (افتراضي 1) — كالأصل.
        $rows = proc_gate($is_super)->select('equipments', array(
            'columns'  => array('id', 'code', 'name'),
            'whereRaw' => 'COALESCE(status,1)=1',
            'orderBy'  => 'id DESC',
        ));
        foreach ($rows as &$r) {
            $code = (string) $r['code'];
            $base = ($code === '') ? ('#' . intval($r['id'])) : $code;
            $name = (string) $r['name'];
            $r['label'] = $base . ($name === '' ? '' : ' — ' . $name);
        }
        unset($r);
        return proc_options_from_rows($rows, $selected, '— بلا معدة —');
    }
}

if (!function_exists('proc_project_options')) {
    /** قائمة المشاريع — قراءة فقط من project عبر البوابة (لا كتابة). */
    function proc_project_options($conn, $is_super, $company_id, $selected = 0)
    {
        $rows = proc_gate($is_super)->select('project', array(
            'columns' => array('id', 'name'),
            'orderBy' => 'name ASC',
        ));
        foreach ($rows as &$r) { $r['label'] = $r['name']; }
        unset($r);
        return proc_options_from_rows($rows, $selected, '— بلا مشروع —');
    }
}

// ── قوائم بيضاء للقيم الثابتة (enums منطقية على مستوى التطبيق) ──
if (!defined('PROC_CLASSIFICATIONS')) {
    define('PROC_CLASSIFICATIONS', 'وقائية,تصحيحية,رأسمالية,استهلاكية');
}
if (!function_exists('proc_classifications')) {
    function proc_classifications() { return explode(',', PROC_CLASSIFICATIONS); }
}
if (!function_exists('proc_need_sources')) {
    function proc_need_sources() { return array('خطة وقائية', 'أمر صيانة', 'نقص مخزون', 'إعادة طلب'); }
}
if (!function_exists('proc_priorities')) {
    function proc_priorities() { return array('عادي', 'عاجل', 'حرج'); }
}
if (!function_exists('proc_request_states')) {
    function proc_request_states() { return array('مسودة', 'مقدَّم', 'اعتماد المشتريات', 'مراجعة مالية', 'معتمد مالياً', 'محوَّل لأمر شراء', 'مغلق', 'مرفوض'); }
}
if (!function_exists('proc_order_states')) {
    function proc_order_states() { return array('مسودة', 'مؤكَّد', 'استلام أولي', 'استلام نهائي', 'مطابَق', 'مغلق'); }
}

if (!function_exists('proc_order_expense_states')) {
    /**
     * الحالاتُ التي يصحّ عندها مصروفُ أمر الشراء (بوابةُ الأثر المالي).
     * ─────────────────────────────────────────────────────────────────────
     * قاعدةٌ محاسبيةٌ لا تفصيلٌ تقني: **الطلبُ ليس مصروفًا** — المصروفُ يُستحقّ
     * بوصول البضاعة. فأمرٌ «مسودة» أو «مؤكَّد» لا يولّد أثرًا ماليًّا أبدًا.
     * (المقيس قبل البوابة: 286,700 من 417,150 — ≈69٪ — سُجّلت على أوامرَ لم
     * تُستلم، منها مسودتان لم تُعتمدا.)
     * والهدفُ الأبعد في UX-09 §8.2 أدقّ: الاستحقاقُ من **المطابقة الثلاثية**
     * (أمر × استلام × فاتورة) — و«مطابَق» ضمن القائمة استعدادًا لها.
     */
    function proc_order_expense_states() { return array('استلام نهائي', 'مطابَق', 'مغلق'); }
}
if (!function_exists('proc_receipt_states')) {
    function proc_receipt_states() { return array('مستلَمة', 'قيد الترحيل', 'مسلَّمة للوجهة'); }
}
if (!function_exists('proc_issue_states')) {
    function proc_issue_states() { return array('مسودة', 'محجوز', 'مصروف', 'محمَّل التكلفة'); }
}
if (!function_exists('proc_custody_states')) {
    function proc_custody_states() { return array('مصروفة', 'إرجاع جزئي', 'مستهلكة', 'مُقفلة'); }
}
if (!function_exists('proc_material_natures')) {
    function proc_material_natures() { return array('قابل للتخزين', 'غير قابل للتخزين', 'خدمة ومصنعيات'); }
}
if (!function_exists('proc_destinations')) {
    function proc_destinations() { return array('مخزن', 'ورشة', 'مشروع', 'معدة'); }
}
if (!function_exists('proc_receipt_types')) {
    function proc_receipt_types() { return array('مخزن', 'مباشر للمعدة', 'مشروع', 'ورشة'); }
}
if (!function_exists('proc_currencies')) {
    function proc_currencies() { return array('SDG', 'USD'); }
}
if (!function_exists('proc_payment_times')) {
    function proc_payment_times() { return array('فوري', 'مؤجل', 'آجل 30', 'آجل 60', 'آجل 90'); }
}
if (!function_exists('proc_warehouse_types')) {
    function proc_warehouse_types() { return array('مخزن', 'ورشة', 'مباشر للآلية'); }
}
if (!function_exists('proc_lookup_types')) {
    function proc_lookup_types() { return array('فئة صنف', 'وحدة قياس', 'طبيعة مادة'); }
}

if (!function_exists('proc_sync_order_receipt')) {
    /**
     * مزامنةُ أمر الشراء مع واقع استلامه (UX-09 §5.1-② · §8.2).
     * ─────────────────────────────────────────────────────────────────────
     * الحالةُ تتبع الواقعة لا العكس: النسبةُ تُحسب من الكميات المستلَمة فعلًا،
     * وتواريخُ أول استلامٍ وآخره من سجله، ثم تتقدّم الحالةُ تبعًا لها:
     *     نسبة ≥ 100 → «استلام نهائي» · نسبة > 0 → «استلام أولي»
     * ولا تتراجع حالةٌ بلغت «مطابَق» أو «مغلق» (تقدُّمٌ لا نكوص).
     *
     * وعند بلوغ «استلام نهائي» يُنشر الأثرُ الماليُّ من منبعه (proc_publish_order_cost).
     *
     * @return array ('state' => الحالة بعد المزامنة، 'pct' => النسبة، 'publish' => نتيجة النشر)
     */
    function proc_sync_order_receipt($conn, $order_id, $uid = 0)
    {
        $order_id = intval($order_id);
        $out = array('state' => null, 'pct' => 0.0, 'publish' => 'none');
        if ($order_id <= 0) { return $out; }

        $gate = proc_gate(false);
        try {
            $po = $gate->selectOne('proc_order', array('where' => array('id' => $order_id)));
        } catch (\Throwable $t) {
            error_log('proc sync receipt #' . $order_id . ': ' . $t->getMessage());
            return $out;
        }
        if (!$po) { return $out; }
        $out['state'] = (string) $po['state'];

        // الكمياتُ من مصدرها — الاستلامُ عبر رأسه (proc_receipt_custody.order_id)
        $ordered = 0.0; $received = 0.0; $firstAt = null; $lastAt = null;
        try {
            $olr = $gate->scopedQuery(array('scope' => array('ol' => 'proc_order_line')),
                "SELECT COALESCE(SUM(ol.qty),0) s FROM proc_order_line ol WHERE {TENANT_SCOPE} AND ol.order_id = ?",
                array($order_id));
            $ordered = $olr ? (float) $olr[0]['s'] : 0.0;

            $rlr = $gate->scopedQuery(
                array('scope' => array('rc' => 'proc_receipt_custody'), 'enrich' => array('rl' => 'proc_receipt_line')),
                "SELECT COALESCE(SUM(rl.qty),0) s, MIN(rc.receipt_date) f, MAX(rc.receipt_date) l
                   FROM proc_receipt_custody rc
                   LEFT JOIN proc_receipt_line rl ON rl.custody_id = rc.id
                  WHERE {TENANT_SCOPE} AND rc.order_id = ? AND COALESCE(rc.is_deleted,0) = 0",
                array($order_id));
            if ($rlr) {
                $received = (float) $rlr[0]['s'];
                $firstAt  = $rlr[0]['f'];
                $lastAt   = $rlr[0]['l'];
            }
        } catch (\Throwable $t) {
            error_log('proc sync receipt qty #' . $order_id . ': ' . $t->getMessage());
            return $out;
        }

        $pct = ($ordered > 0) ? min(100.0, round($received / $ordered * 100, 2)) : 0.0;
        $out['pct'] = $pct;

        $data = array('received_pct' => $pct);
        if ($firstAt) { $data['first_receipt_at'] = $firstAt; }
        if ($pct >= 100 && $lastAt) { $data['final_receipt_at'] = $lastAt; }

        // تقدُّمُ الحالة — ولا نكوصَ عمّا بلغ المطابقة أو الإقفال
        $advanceable = array('مسودة', 'مؤكَّد', 'استلام أولي');
        if (in_array((string) $po['state'], $advanceable, true)) {
            if ($pct >= 100)   { $data['state'] = 'استلام نهائي'; }
            elseif ($pct > 0)  { $data['state'] = 'استلام أولي'; }
        }

        try {
            $gate->update('proc_order', $data, array('id' => $order_id));
            if (isset($data['state'])) { $out['state'] = $data['state']; }
        } catch (\Throwable $t) {
            error_log('proc sync receipt update #' . $order_id . ': ' . $t->getMessage());
            return $out;
        }

        if (in_array((string) $out['state'], proc_order_expense_states(), true)) {
            $out['publish'] = proc_publish_order_cost($conn, $order_id, $uid, 'receipt');
        }
        return $out;
    }
}

if (!function_exists('proc_match_tolerance')) {
    /**
     * حدُّ السماح في المطابقة الثلاثية (UX-09 §8.2 «ضمن السماح → Matched»).
     * ─────────────────────────────────────────────────────────────────────
     * **±2٪ أو 100 وحدةٍ من عملة الأمر — أيُّهما أصغر** (مقترحُ المنفِّذ باعتماد
     * المالك 2026-07-27). المنطق: النسبةُ وحدها تتساهل في الفواتير الكبيرة
     * (2٪ من مليون = 20 ألفًا تمرّ صامتة)، والمبلغُ وحده يتعنّت في الصغيرة —
     * فالأصغرُ منهما يحرس الطرفين. ويبقى رقمًا واحدًا موثَّقًا يُضبط من هنا.
     */
    function proc_match_tolerance($order_amount)
    {
        $pct = abs((float) $order_amount) * 0.02;
        return min($pct, 100.0);
    }
}

if (!function_exists('proc_match_invoice')) {
    /**
     * المطابقةُ الثلاثية: أمرُ الشراء × الاستلام × فاتورة المورد (UX-09 §8.2).
     * ─────────────────────────────────────────────────────────────────────
     * «لا استحقاقَ بلا مطابقة». تقارن الثلاثة وتحكم:
     *   · **ضمن السماح** → `matched` + **دَينُ المورد** في `fin_dues` بحدثه
     *   · **فوق السماح** → `var_pending` بفرقيه — **ولا دَين** حتى قرارٍ موثَّق
     *
     * **نموذج القيدين** (قرار المالك): الاستلامُ نشر **المصروف** («بضاعةٌ وصلت
     * ولم تُفوتَر»)، وهذه تفتح **الدَّين باسم المورد** فتقفله — فلا مصروفَ مضاعف.
     *
     * **هويةُ الطرف**: `party_type='proc_supplier'` يشير إلى `proc_supplier` —
     * لا إلى `suppliers` (سجلّان مختلفان بمعرّفاتٍ متصادمة؛ الخلطُ يقيّد الدَّين
     * على شركةٍ أخرى بلا خطأٍ ظاهر).
     *
     * العطالة: مفتاح `proc:invoice:{order_id}` — ففاتورةٌ تُسجَّل مرتين لا تُقيَّد مرتين.
     *
     * @return array ('status' => matched|var_pending|skipped|failed,
     *                'qty_var','price_var','tolerance','due_id','reason')
     */
    function proc_match_invoice($conn, $order_id, $invoice_no, $invoice_date, $invoice_amount, $uid = 0)
    {
        $order_id = intval($order_id);
        $out = array('status' => 'skipped', 'qty_var' => 0.0, 'price_var' => 0.0,
                     'tolerance' => 0.0, 'due_id' => null, 'reason' => '');
        if ($order_id <= 0) { $out['reason'] = 'معرّفٌ غير صالح'; return $out; }

        $invoice_no = trim((string) $invoice_no);
        $invoice_amount = (float) $invoice_amount;
        if ($invoice_no === '' || $invoice_amount <= 0) {
            $out['reason'] = 'رقمُ الفاتورة وقيمتُها إلزاميان';
            return $out;
        }

        $gate = proc_gate(false);
        try {
            $po = $gate->selectOne('proc_order', array('where' => array('id' => $order_id)));
        } catch (\Throwable $t) {
            error_log('proc match #' . $order_id . ': ' . $t->getMessage());
            $out['status'] = 'failed'; return $out;
        }
        if (!$po) { $out['reason'] = 'الأمرُ غير موجود'; return $out; }

        // الضلعُ الثاني شرطٌ: لا مطابقةَ قبل وصول البضاعة (نفسُ بوابة المصروف)
        if (!in_array((string) $po['state'], proc_order_expense_states(), true)) {
            $out['reason'] = 'لا مطابقةَ قبل الاستلام النهائي — الحالة: ' . $po['state'];
            return $out;
        }

        // فرقُ الكمية: المستلَمُ مقابل المطلوب · وفرقُ القيمة: الفاتورةُ مقابل الأمر
        $ordered = 0.0; $received = 0.0;
        try {
            $olr = $gate->scopedQuery(array('scope' => array('ol' => 'proc_order_line')),
                "SELECT COALESCE(SUM(ol.qty),0) s FROM proc_order_line ol WHERE {TENANT_SCOPE} AND ol.order_id = ?",
                array($order_id));
            $ordered = $olr ? (float) $olr[0]['s'] : 0.0;
            $rlr = $gate->scopedQuery(
                array('scope' => array('rc' => 'proc_receipt_custody'), 'enrich' => array('rl' => 'proc_receipt_line')),
                "SELECT COALESCE(SUM(rl.qty),0) s FROM proc_receipt_custody rc
                   LEFT JOIN proc_receipt_line rl ON rl.custody_id = rc.id
                  WHERE {TENANT_SCOPE} AND rc.order_id = ? AND COALESCE(rc.is_deleted,0) = 0",
                array($order_id));
            $received = $rlr ? (float) $rlr[0]['s'] : 0.0;
        } catch (\Throwable $t) {
            error_log('proc match qty #' . $order_id . ': ' . $t->getMessage());
            $out['status'] = 'failed'; return $out;
        }

        $orderAmount = (float) $po['total_amount'];
        $qtyVar   = round($received - $ordered, 4);
        $priceVar = round($invoice_amount - $orderAmount, 2);
        $tol      = proc_match_tolerance($orderAmount);

        $out['qty_var'] = $qtyVar;
        $out['price_var'] = $priceVar;
        $out['tolerance'] = round($tol, 2);

        $withinPrice = (abs($priceVar) <= $tol);
        $withinQty   = (abs($qtyVar) < 0.0001);      // الكميةُ لا سماحَ فيها — قطعةٌ ناقصةٌ نقصٌ
        $ok = ($withinPrice && $withinQty);

        $state = $ok ? 'matched' : 'var_pending';
        try {
            $gate->update('proc_order', array(
                'invoice_no'     => $invoice_no,
                'invoice_date'   => ($invoice_date !== '' && $invoice_date !== null) ? $invoice_date : null,
                'invoice_amount' => $invoice_amount,
                'match_state'    => $state,
                'matched_at'     => date('Y-m-d H:i:s'),
                'matched_by'     => intval($uid) > 0 ? intval($uid) : null,
            ), array('id' => $order_id));
        } catch (\Throwable $t) {
            error_log('proc match save #' . $order_id . ': ' . $t->getMessage());
            $out['status'] = 'failed'; return $out;
        }

        if (!$ok) {
            $out['status'] = 'var_pending';
            $out['reason'] = (!$withinQty ? 'فرقُ كميةٍ ' . $qtyVar . ' ' : '')
                           . (!$withinPrice ? 'فرقُ قيمةٍ ' . $priceVar . ' (السماح ' . round($tol, 2) . ')' : '');
            return $out;   // لا دَينَ حتى قرارٍ موثَّق
        }

        // ── مطابَقة: الدَّينُ يُفتح باسم المورد في سجله ────────────────────
        $out['status'] = 'matched';
        $docCompany = intval($po['company_id']);
        $currency   = (isset($po['currency']) && $po['currency'] !== '') ? $po['currency'] : 'SDG';

        require_once __DIR__ . '/../app/Core/EventPublisher.php';
        $conn->begin_transaction();
        try {
            \App\Core\EventPublisher::publish($conn, array(
                'event_key'         => 'payable.purchase.accrued',
                'category'          => 'financial',
                'source_module'     => 'procurement',
                'company_id'        => $docCompany,
                'entity_type'       => 'proc_order_invoice',
                'entity_id'         => $order_id,
                'occurred_at'       => gmdate('Y-m-d H:i:s'),
                'created_by'        => intval($uid) > 0 ? intval($uid) : 1,
                'idempotency_key'   => 'proc:invoice:' . $order_id,
                'legacy_event_type' => 'expense',
                'amount'            => $invoice_amount,
                'currency'          => $currency,
                'source_ref'        => $invoice_no,
                'project_id'        => (isset($po['project_id']) && intval($po['project_id']) > 0) ? intval($po['project_id']) : null,
                'notes'             => 'استحقاقُ فاتورة مورد ' . $invoice_no . ' — أمر ' . $po['code'],
                'payload'           => array(
                    'order_id'    => $order_id,
                    'order_code'  => (string) $po['code'],
                    'invoice_no'  => $invoice_no,
                    'qty_var'     => $qtyVar,
                    'price_var'   => $priceVar,
                    'tolerance'   => round($tol, 2),
                    'party_type'  => 'proc_supplier',
                    'party_ref'   => intval($po['supplier_id']),
                ),
            ));
            $conn->commit();
        } catch (\Throwable $t) {
            $conn->rollback();
            error_log('proc match publish #' . $order_id . ': ' . $t->getMessage());
            $out['status'] = 'failed';
            return $out;
        }

        // صفُّ الذمّة — بعطالته: فاتورةُ الأمر تُقيَّد مرةً واحدة
        try {
            $existing = $gate->selectOne('fin_dues', array(
                'columns'  => array('id'),
                'whereRaw' => "party_type = 'proc_supplier' AND due_type = 'purchase' AND period_ref = ?",
                'params'   => array('PO-' . $order_id),
            ));
            if ($existing) {
                $out['due_id'] = intval($existing['id']);
            } else {
                $out['due_id'] = intval($gate->insert('fin_dues', array(
                    'party_type'       => 'proc_supplier',
                    'party_ref'        => intval($po['supplier_id']),
                    'due_type'         => 'purchase',
                    'direction'        => 'credit',
                    'amount'           => $invoice_amount,
                    'currency'         => $currency,
                    'period_ref'       => 'PO-' . $order_id,
                    'settlement_state' => 'pending',
                    'created_by'       => intval($uid) > 0 ? intval($uid) : null,
                )));
            }
        } catch (\Throwable $t) {
            error_log('proc match due #' . $order_id . ': ' . $t->getMessage());
        }

        return $out;
    }
}

if (!function_exists('proc_publish_order_cost')) {
    /**
     * نشرُ الأثر المالي لأمر الشراء من منبعه (UX-09 §2 و§5.1-③ · FES §7 و§8).
     * ─────────────────────────────────────────────────────────────────────
     * «كلُّ أثرٍ ماليٍّ للمشتريات يولَّد من مستنده في منبعه … لا استيرادَ لاحقًا»
     * (UX-09 §2). وكان يُسحب بزرٍّ في شاشة الاستيراد **بلا بوابةِ حالة** — فينشر
     * مصروفًا عن مسوداتٍ وأوامرَ لم تصل بضاعتُها.
     *
     * **البوابة**: `proc_order_expense_states()` — لا أثرَ قبل الاستلام النهائي.
     * **العطالة**: مفتاحُ الاستيراد نفسُه `proc:order:{id}` — فالقناتان لا تتضاعفان.
     * **لا يرمي أبدًا**: الاستلامُ حقيقةٌ تشغيليةٌ لا تُفقد لتعثّرِ نشرٍ مالي.
     *
     * الأبعادُ المنقولة: المشروع (العمود المضاف — كان مفقودًا فكان المصروفُ بلا
     * مشروع) والمعادلُ الموحّد في الحمولة (FES §3.3).
     *
     * @return string published | duplicate | skipped | failed
     */
    function proc_publish_order_cost($conn, $order_id, $uid = 0, $channel = 'receipt')
    {
        $order_id = intval($order_id);
        if ($order_id <= 0) { return 'skipped'; }

        try {
            $row = proc_gate(false)->selectOne('proc_order', array('where' => array('id' => $order_id)));
        } catch (\Throwable $t) {
            error_log('proc publish cost #' . $order_id . ': ' . $t->getMessage());
            return 'failed';
        }
        if (!$row) { return 'skipped'; }

        // البوابةُ الثلاثية: حالةٌ يصحّ عندها المصروف · مبلغٌ موجب · كودٌ ومستأجر
        if (!in_array((string) $row['state'], proc_order_expense_states(), true)) { return 'skipped'; }
        $amount = (float) (isset($row['total_amount']) ? $row['total_amount'] : 0);
        $code   = isset($row['code']) ? trim((string) $row['code']) : '';
        $docCompany = intval(isset($row['company_id']) ? $row['company_id'] : 0);
        if ($amount <= 0 || $code === '' || $docCompany <= 0) { return 'skipped'; }

        $fx   = (float) (isset($row['fx_rate']) && $row['fx_rate'] > 0 ? $row['fx_rate'] : 1);
        $base = (isset($row['base_amount']) && $row['base_amount'] !== null)
                ? (float) $row['base_amount'] : round($amount * $fx, 2);

        require_once __DIR__ . '/../app/Core/EventPublisher.php';
        $conn->begin_transaction();
        try {
            $res = \App\Core\EventPublisher::publish($conn, array(
                'event_key'         => 'expense.purchase.recorded',
                'category'          => 'financial',
                'source_module'     => 'procurement',
                'company_id'        => $docCompany,
                'entity_type'       => 'proc_order',
                'entity_id'         => $order_id,
                'occurred_at'       => gmdate('Y-m-d H:i:s'),
                'created_by'        => intval($uid) > 0 ? intval($uid) : 1,
                'idempotency_key'   => 'proc:order:' . $order_id,
                'legacy_event_type' => 'expense',
                'amount'            => $amount,
                'currency'          => (isset($row['currency']) && $row['currency'] !== null && $row['currency'] !== '') ? $row['currency'] : 'SDG',
                'source_ref'        => $code,
                'project_id'        => (isset($row['project_id']) && intval($row['project_id']) > 0) ? intval($row['project_id']) : null,
                'notes'             => 'تكلفة أمر شراء ' . $code,
                'payload'           => array(
                    'order_id'     => $order_id,
                    'order_code'   => $code,
                    'state'        => (string) $row['state'],
                    'fx_rate'      => $fx,
                    'base_amount'  => $base,
                    'tax_amount'   => (float) (isset($row['tax_amount']) ? $row['tax_amount'] : 0),
                    'received_pct' => (float) (isset($row['received_pct']) ? $row['received_pct'] : 0),
                    'channel'      => $channel,
                ),
            ));
            $conn->commit();
            $verdict = (is_array($res) && !empty($res['duplicate'])) ? 'duplicate' : 'published';
        } catch (\Throwable $t) {
            $conn->rollback();
            error_log('proc publish cost #' . $order_id . ' (' . $channel . '): ' . $t->getMessage());
            return 'failed';
        }

        // مرجعُ الحدث على الأمر — «قراءةً بمرجعه» (§5.1-③)
        try {
            $fe = proc_gate(false)->selectOne('fin_financial_events', array(
                'columns' => array('id'),
                'whereRaw' => "entity_type = 'proc_order' AND entity_id = ?",
                'params' => array($order_id),
            ));
            if ($fe && empty($row['event_id'])) {
                proc_gate(false)->update('proc_order', array('event_id' => intval($fe['id'])), array('id' => $order_id));
            }
        } catch (\Throwable $t) { /* المرجعُ زينةُ عرض — لا يُسقط النشر */ }

        return $verdict;
    }
}
