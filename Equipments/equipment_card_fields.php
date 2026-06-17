<?php
/**
 * كرت المعدة — حقول الهوية الأساسية (مشترك بين equipments.php و equipments_fleet.php)
 *
 * يوفّر:
 *  - القوائم الثابتة (source_type / meter_uom / meter_source / capacity_uom / acquisition_currency).
 *  - ems_render_equipment_card_fields(): عرض أقسام «الهوية والمصدر» و«العدّاد» في النموذج.
 *  - ems_save_equipment_card_fields(): حفظ الحقول الجديدة بـ Prepared Statement مع فحص db_table_has_column
 *    (توافق رجعي — لا يكتب إلا الأعمدة الموجودة فعلاً، وكلها NULL غير كاسرة).
 *
 *  ملاحظة: card_state يُضبط 'draft' للكرت الجديد فقط (is_new) عبر هذا المُوحِّد؛
 *  الاعتماد يتم لاحقاً عبر approve_card.php. لا حساب إهلاك هنا (مرحلة لاحقة).
 */

if (!function_exists('ems_equipment_card_static_lists')) {
    function ems_equipment_card_static_lists()
    {
        return [
            'source_type'          => ['ملك', 'مموَّل', 'حق استخدام', 'خدمة'],
            'meter_uom'            => ['ساعات', 'كيلومترات'],
            'meter_source'         => ['عداد المعدة', 'GPS', 'السجل اليومي', 'تقديري'],
            'capacity_uom'         => ['م³', 'طن', 'كيلوواط', 'حصان', 'لتر', 'متر', 'كجم'],
            'acquisition_currency' => ['USD', 'SDG', 'EUR', 'AED', 'SAR'],
        ];
    }
}

if (!function_exists('ems_equipment_card_columns_map')) {
    /** col => ['type'=>'s|d|date'] — مصدر الحقيقة لأعمدة الكرت القابلة للحفظ */
    function ems_equipment_card_columns_map()
    {
        return [
            'operating_category'   => 's',
            'origin_country'       => 's',
            'engine_no'            => 's',
            'plate_no'             => 's',
            'capacity'             => 'd',
            'capacity_uom'         => 's',
            'dimensions'           => 's',
            'source_type'          => 's',
            'entry_date'           => 'date',
            'acquisition_cost'     => 'd',
            'acquisition_currency' => 's',
            'opening_meter'        => 'd',
            'meter_uom'            => 's',
            'meter_source'         => 's',
        ];
    }
}

if (!function_exists('ems_save_equipment_card_fields')) {
    /**
     * يحفظ حقول الكرت الجديدة للمعدة $equipment_id.
     * @param bool   $is_new     true عند الإضافة (يضبط card_state='draft').
     * @param string $scope_sql  قيد إضافي مثل " AND company_id = 5" (أو '').
     */
    function ems_save_equipment_card_fields($conn, $equipment_id, $is_new, $scope_sql = '')
    {
        $equipment_id = intval($equipment_id);
        if ($equipment_id <= 0) return;

        $sets = [];
        $types = '';
        $vals = [];

        foreach (ems_equipment_card_columns_map() as $col => $t) {
            if (!db_table_has_column($conn, 'equipments', $col)) continue;
            $raw = isset($_POST[$col]) ? trim((string) $_POST[$col]) : '';
            if ($t === 'd') {
                $v = ($raw === '') ? null : (float) $raw;
                $types .= 'd';
            } elseif ($t === 'date') {
                $v = ($raw === '') ? null : $raw;
                $types .= 's';
            } else {
                $v = ($raw === '') ? null : $raw;
                $types .= 's';
            }
            $sets[] = "`$col` = ?";
            $vals[] = $v;
        }

        // الكرت الجديد يُنشأ كمسودة (الحوكمة الخفيفة)
        if ($is_new && db_table_has_column($conn, 'equipments', 'card_state')) {
            $sets[] = "`card_state` = ?";
            $types .= 's';
            $vals[] = 'draft';
        }

        if (empty($sets)) return;

        $sql = "UPDATE equipments SET " . implode(', ', $sets) . " WHERE id = ?" . $scope_sql;
        $types .= 'i';
        $vals[] = $equipment_id;

        $stmt = $conn->prepare($sql);
        if (!$stmt) return;

        $bind = [];
        $bind[] = $types;
        for ($k = 0; $k < count($vals); $k++) {
            $bind[] = &$vals[$k];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind);
        $stmt->execute();
    }
}

if (!function_exists('ems_render_equipment_card_fields')) {
    /**
     * يطبع أقسام حقول الكرت في النموذج.
     * @param array  $editData      بيانات المعدة عند التعديل (أو [] للإضافة).
     * @param string $section_class صنف ترويسة القسم ('form-section' أو 'form-section-header').
     */
    function ems_render_equipment_card_fields($editData, $section_class = 'form-section')
    {
        $lists = ems_equipment_card_static_lists();
        $g = function ($k) use ($editData) {
            return isset($editData[$k]) ? htmlspecialchars((string) $editData[$k], ENT_QUOTES, 'UTF-8') : '';
        };
        $opt = function ($value, $current) {
            $v = htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
            $sel = ((string) $value === (string) $current) ? ' selected' : '';
            return "<option value=\"$v\"$sel>$v</option>";
        };
        $sc = htmlspecialchars($section_class, ENT_QUOTES, 'UTF-8');
        ?>
        <!-- ============ قسم: الهوية والمصدر (كرت المعدة) ============ -->
        <div class="<?= $sc; ?>">
            <h6><i class="fas fa-id-badge"></i> الهوية والمصدر</h6>
        </div>

        <div>
            <label><i class="fas fa-layer-group"></i> الفئة التشغيلية
                <span class="ems-inherit-hint" style="font-size:11px;color:#16a34a;font-weight:700" title="تُملأ تلقائياً من الموديل وقابلة للتعديل">(موروثة من الموديل)</span>
            </label>
            <input type="text" name="operating_category" id="operating_category"
                   placeholder="مثال: حفر، تحميل، نقل" value="<?= $g('operating_category'); ?>" />
        </div>

        <div>
            <label><i class="fas fa-globe"></i> بلد الصنع</label>
            <input type="text" name="origin_country" id="origin_country" value="<?= $g('origin_country'); ?>" />
        </div>

        <div>
            <label><i class="fas fa-cog"></i> رقم الموتور</label>
            <input type="text" name="engine_no" id="engine_no" value="<?= $g('engine_no'); ?>" />
        </div>

        <div>
            <label><i class="fas fa-id-card-alt"></i> رقم اللوحة</label>
            <input type="text" name="plate_no" id="plate_no" value="<?= $g('plate_no'); ?>" />
        </div>

        <div>
            <label><i class="fas fa-weight-hanging"></i> السعة / القدرة / الحمولة
                <span class="ems-inherit-hint" style="font-size:11px;color:#16a34a;font-weight:700" title="تُملأ تلقائياً من الموديل وقابلة للتعديل">(موروثة)</span>
            </label>
            <input type="number" step="0.01" name="capacity" id="capacity" value="<?= $g('capacity'); ?>" />
        </div>

        <div>
            <label><i class="fas fa-ruler"></i> وحدة السعة</label>
            <select name="capacity_uom" id="capacity_uom">
                <option value="">-- اختر --</option>
                <?php foreach ($lists['capacity_uom'] as $o) echo $opt($o, $g('capacity_uom')); ?>
            </select>
        </div>

        <div>
            <label><i class="fas fa-vector-square"></i> المقاسات الفنية (طول·عرض·ارتفاع·وزن)</label>
            <input type="text" name="dimensions" id="dimensions" placeholder="مثال: 9.5م × 3م × 3.2م / 22طن" value="<?= $g('dimensions'); ?>" />
        </div>

        <div>
            <label><i class="fas fa-handshake"></i> نوع المصدر</label>
            <select name="source_type" id="source_type">
                <option value="">-- اختر --</option>
                <?php foreach ($lists['source_type'] as $o) echo $opt($o, $g('source_type')); ?>
            </select>
        </div>

        <div>
            <label><i class="fas fa-calendar-day"></i> تاريخ دخول المعدة</label>
            <input type="date" name="entry_date" id="entry_date" value="<?= $g('entry_date'); ?>" />
        </div>

        <div>
            <label><i class="fas fa-money-check-dollar"></i> تكلفة الشراء</label>
            <input type="number" step="0.01" name="acquisition_cost" id="acquisition_cost" value="<?= $g('acquisition_cost'); ?>" />
        </div>

        <div>
            <label><i class="fas fa-coins"></i> عملة التكلفة</label>
            <select name="acquisition_currency" id="acquisition_currency">
                <option value="">-- اختر --</option>
                <?php foreach ($lists['acquisition_currency'] as $o) echo $opt($o, $g('acquisition_currency')); ?>
            </select>
        </div>

        <!-- ============ قسم: العدّاد ============ -->
        <div class="<?= $sc; ?>">
            <h6><i class="fas fa-gauge-high"></i> العدّاد</h6>
        </div>

        <div>
            <label><i class="fas fa-gauge"></i> العدّاد الافتتاحي</label>
            <input type="number" step="0.01" name="opening_meter" id="opening_meter" value="<?= $g('opening_meter'); ?>" />
        </div>

        <div>
            <label><i class="fas fa-ruler-horizontal"></i> وحدة العدّاد</label>
            <?php $mu = $g('meter_uom'); if ($mu === '') $mu = 'ساعات'; ?>
            <select name="meter_uom" id="meter_uom">
                <?php foreach ($lists['meter_uom'] as $o) echo $opt($o, $mu); ?>
            </select>
        </div>

        <div>
            <label><i class="fas fa-satellite-dish"></i> مصدر العدّاد</label>
            <select name="meter_source" id="meter_source">
                <option value="">-- اختر --</option>
                <?php foreach ($lists['meter_source'] as $o) echo $opt($o, $g('meter_source')); ?>
            </select>
        </div>
        <?php
    }
}
