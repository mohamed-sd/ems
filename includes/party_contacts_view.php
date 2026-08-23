<?php
/**
 * includes/party_contacts_view.php — تصييرُ جهاتِ الاتصالِ والمفوَّضين
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **التصييرُ في عُدَّةٍ واحدةٍ كالمنطق**: الحقولُ واحدةٌ حرفًا في السطحَين
 *   (العميلُ والمورد)، ونسختانِ من النموذجِ **تتفرّقانِ بأوَّلِ تعديل** — تُصلَح
 *   إحداهما وتبقى الأخرى ولا شيءَ يُنبِّه. والفرقُ بينَ السطحَين **شريطُ
 *   التبويبِ والعنوانُ وحدَهما**، وهما ما يُمرَّر.
 *
 * ◆ **والشاشةُ تشرح المنعَ ولا تخترعه**: قيدُ `chk_pc_authority` في القاعدةِ هو
 *   الحكم — والرسالةُ هنا تقول **لماذا** لئلّا يُقرأ الرفضُ عطبًا.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (!function_exists('ems_pc_render')) {
    function ems_pc_render(array $rows, $partyLabel, $canEdit = true)
    {
        $tok   = ems_pc_token();
        $kinds = ems_pc_authority_kinds();
        $sts   = ems_pc_states();
        $edit  = isset($_GET['pc_edit']) ? (int) $_GET['pc_edit'] : 0;
        $cur   = null;
        foreach ($rows as $r) { if ((int) $r['id'] === $edit) { $cur = $r; break; } }
        $v = static function ($k) use ($cur) {
            return ($cur !== null && isset($cur[$k]) && $cur[$k] !== null) ? ems_pc_e($cur[$k]) : '';
        };
        /* رابطُ التعديلِ يحفظ معرِّفَ الطرفِ ولا يخترعه */
        $qs = array();
        foreach ($_GET as $k => $val) {
            if ($k === 'pc_edit' || !is_scalar($val)) { continue; }
            $qs[] = rawurlencode($k) . '=' . rawurlencode((string) $val);
        }
        $baseQs = implode('&', $qs);

        ob_start();
        ?>
        <div class="card"><div class="card-body">
            <p class="pc-note">
                <i class="fas fa-circle-info"></i>
                <strong>جهاتُ الاتصالِ والمفوَّضون لـ<?php echo ems_pc_e($partyLabel); ?></strong> —
                <strong>تبويبٌ في الملفِّ لا شاشةٌ مستقلة</strong>، فلا بندَ تنقّلٍ له.
                <br>
                و<strong>التفويضُ حجّيةٌ بمداه لا خانةُ تأشير</strong>: من يُوسَم مفوَّضًا بالتوقيع
                <strong>يلزمه صفةٌ ومدًى ومستندٌ مرجعيّ</strong> — والقاعدةُ ترفض ما دونَ ذلك،
                فلا يبقى في السجلِّ <strong>تفويضٌ مفتوح</strong>.
            </p>
        </div></div>

        <div class="card"><div class="card-header"><h5><i class="fa fa-address-book"></i>
            جهاتُ الاتصال — <?php echo count($rows); ?></h5></div>
        <div class="card-body"><div class="table-container">
            <table class="alltables display nowrap pc-table">
                <thead><tr>
                    <?php if ($canEdit): ?><th class="pc-actions-th">إجراءات</th><?php endif; ?>
                    <th>الاسم</th><th>الصفة</th><th>الهاتف</th><th>البريد</th>
                    <th>رئيسية</th><th>مفوَّضٌ بالتوقيع</th><th>صفةُ التفويض</th>
                    <th>مدى التفويض</th><th>المستند</th><th>من</th><th>إلى</th><th>الحالة</th>
                </tr></thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="<?php echo $canEdit ? 13 : 12; ?>" class="pc-empty">
                        لا جهةَ اتصالٍ مسجَّلةٌ بعد</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <?php if ($canEdit): ?>
                        <td>
                            <a class="btn btn-sm btn-outline-secondary"
                               href="?<?php echo ems_pc_e($baseQs); ?>&amp;pc_edit=<?php echo (int) $r['id']; ?>">تعديل</a>
                            <form method="post" class="pc-inline"
                                  onsubmit="return confirm('إزالةُ جهةِ الاتصال؟ (حذفٌ ناعمٌ يُبقي الأثر)');">
                                <input type="hidden" name="csrf_token" value="<?php echo ems_pc_e($tok); ?>">
                                <input type="hidden" name="pc_action" value="delete">
                                <input type="hidden" name="pc_id" value="<?php echo (int) $r['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">إزالة</button>
                            </form>
                        </td>
                        <?php endif; ?>
                        <td><?php echo ems_pc_e($r['contact_name']); ?></td>
                        <td><?php echo ems_pc_e($r['job_title']); ?></td>
                        <td><?php echo ems_pc_e($r['phone']);
                            echo $r['phone_alt'] ? ' / ' . ems_pc_e($r['phone_alt']) : ''; ?></td>
                        <td><?php echo ems_pc_e($r['email']); ?></td>
                        <td><?php echo ((int) $r['is_primary'] === 1)
                            ? '<span class="badge badge-success">رئيسية</span>' : ''; ?></td>
                        <td><?php echo ((int) $r['is_signatory'] === 1)
                            ? '<span class="badge badge-success">مفوَّض</span>' : ''; ?></td>
                        <td><?php echo ems_pc_e($r['authority_kind']); ?></td>
                        <td class="pc-wrap"><?php echo ems_pc_e($r['authority_scope']); ?></td>
                        <td><?php echo ems_pc_e($r['authority_doc_ref']); ?></td>
                        <td><?php echo ems_pc_e($r['authority_from']); ?></td>
                        <td><?php echo ems_pc_e($r['authority_to']); ?></td>
                        <td><?php echo ems_pc_e($r['state']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div></div></div>

        <?php if ($canEdit): ?>
        <div class="card"><div class="card-header"><h5><i class="fa fa-plus"></i>
            <?php echo $cur ? 'تعديلُ جهةِ اتصال' : 'إضافةُ جهةِ اتصال'; ?></h5></div>
        <div class="card-body">
            <form method="post" class="ems-form pc-form">
                <input type="hidden" name="csrf_token" value="<?php echo ems_pc_e($tok); ?>">
                <input type="hidden" name="pc_action" value="<?php echo $cur ? 'edit' : 'add'; ?>">
                <?php if ($cur): ?>
                    <input type="hidden" name="pc_id" value="<?php echo (int) $cur['id']; ?>">
                <?php endif; ?>
                <div class="pc-grid">
                    <div><label for="pc_name">الاسم *</label>
                        <input id="pc_name" class="form-control" type="text" name="contact_name"
                               maxlength="190" required value="<?php echo $v('contact_name'); ?>"></div>
                    <div><label for="pc_job">الصفةُ الوظيفية</label>
                        <input id="pc_job" class="form-control" type="text" name="job_title"
                               maxlength="120" value="<?php echo $v('job_title'); ?>"></div>
                    <div><label for="pc_ph">الهاتف</label>
                        <input id="pc_ph" class="form-control" type="text" name="phone"
                               maxlength="40" value="<?php echo $v('phone'); ?>"></div>
                    <div><label for="pc_ph2">هاتفٌ بديل</label>
                        <input id="pc_ph2" class="form-control" type="text" name="phone_alt"
                               maxlength="40" value="<?php echo $v('phone_alt'); ?>"></div>
                    <div><label for="pc_em">البريد</label>
                        <input id="pc_em" class="form-control" type="email" name="email"
                               maxlength="190" value="<?php echo $v('email'); ?>"></div>
                    <div><label for="pc_st">الحالة</label>
                        <select id="pc_st" class="form-control" name="state">
                            <?php foreach ($sts as $o): ?>
                            <option value="<?php echo ems_pc_e($o); ?>"<?php
                                echo ($cur && $cur['state'] === $o) ? ' selected' : ''; ?>><?php
                                echo ems_pc_e($o); ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="pc-check">
                        <input id="pc_pri" type="checkbox" name="is_primary" value="1"<?php
                            echo ($cur && (int) $cur['is_primary'] === 1) ? ' checked' : ''; ?>>
                        <label for="pc_pri">جهةُ الاتصالِ الرئيسية</label></div>
                    <div class="pc-check">
                        <input id="pc_sig" type="checkbox" name="is_signatory" value="1"<?php
                            echo ($cur && (int) $cur['is_signatory'] === 1) ? ' checked' : ''; ?>>
                        <label for="pc_sig">مفوَّضٌ بالتوقيع</label></div>
                </div>
                <fieldset class="pc-auth">
                    <legend>حجّيةُ التفويضِ ومداه — تلزم كلَّ مفوَّضٍ بالتوقيع</legend>
                    <div class="pc-grid">
                        <div><label for="pc_ak">صفةُ التفويض</label>
                            <select id="pc_ak" class="form-control" name="authority_kind">
                                <?php foreach ($kinds as $o): ?>
                                <option value="<?php echo ems_pc_e($o); ?>"<?php
                                    echo ($cur && $cur['authority_kind'] === $o) ? ' selected' : ''; ?>><?php
                                    echo ems_pc_e($o); ?></option>
                                <?php endforeach; ?>
                            </select></div>
                        <div><label for="pc_ad">المستندُ المرجعيّ</label>
                            <input id="pc_ad" class="form-control" type="text" name="authority_doc_ref"
                                   maxlength="120" value="<?php echo $v('authority_doc_ref'); ?>"></div>
                        <div><label for="pc_af">سارٍ من</label>
                            <input id="pc_af" class="form-control" type="date" name="authority_from"
                                   value="<?php echo $v('authority_from'); ?>"></div>
                        <div><label for="pc_at">سارٍ إلى</label>
                            <input id="pc_at" class="form-control" type="date" name="authority_to"
                                   value="<?php echo $v('authority_to'); ?>"></div>
                        <div class="pc-span2"><label for="pc_as">مدى التفويض</label>
                            <input id="pc_as" class="form-control" type="text" name="authority_scope"
                                   maxlength="300" placeholder="ما الذي يملك التوقيعَ عليه وبأيِّ حد"
                                   value="<?php echo $v('authority_scope'); ?>"></div>
                    </div>
                </fieldset>
                <div><label for="pc_note">ملاحظة</label>
                    <input id="pc_note" class="form-control" type="text" name="note"
                           maxlength="300" value="<?php echo $v('note'); ?>"></div>
                <div class="pc-submit">
                    <button type="submit" class="btn btn-primary"><?php
                        echo $cur ? 'حفظُ التعديل' : 'إضافة'; ?></button>
                </div>
            </form>
        </div></div>
        <?php endif; ?>

        <style>
        .pc-note{color:var(--c-4b5563, #4b5563);line-height:1.8;margin:0}
        .pc-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px}
        .pc-span2{grid-column:span 2}
        .pc-check{display:flex;align-items:center;gap:8px;padding-top:22px}
        .pc-auth{border:1px solid var(--c-d1d5db, #d1d5db);border-radius:8px;padding:12px 16px;margin:14px 0}
        .pc-auth legend{font-size:13px;font-weight:700;padding:0 6px}
        .pc-inline{display:inline}
        .pc-actions-th{width:130px}
        .pc-wrap{white-space:normal}
        .pc-empty{text-align:center;color:var(--c-6b7280, #6b7280)}
        .pc-submit{margin-top:12px}
        </style>
        <?php
        return (string) ob_get_clean();
    }
}
