<?php
/**
 * tools/specs/ceo21_org_decisions.php — مواصفةُ بناءِ `CEO-21` قرارات الهيكل التنظيمي
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الهدفُ كان غيرَ مبنيّ**: اسمُه كان موضوعًا على `admin/org_structure.php`
 *   وهي **خريطةُ هيكلٍ لا سجلُّ قرارات** — صفرٌ من ثلاثةَ عشرَ حقلًا. و§10:
 *   «هدفُ `CURRENT_RELEASE` لا تُعلَن الإدارةُ مكتملةً قبلَ بنائه».
 *
 * ◆ **والمفاتيحُ بترتيبِ الورقةِ حرفًا** (‏18 حقلًا · `seq` 1..18) — والمولِّدُ
 *   **يردُّ المواصفةَ إن اختلف العدد**، وهو حارسُ «الحقولُ بترتيبِ دورتِها
 *   المستنديّة» بالبناءِ لا بالفحص.
 *
 * ◆ **وثلاثُ حبّاتٍ ثلاثُ أسطح**: القرارُ (هنا) · الوحدةُ (`org_units`) ·
 *   التكليفُ (`org_assignments`). ⛔ ولا يُمَسُّ جدولٌ منها.
 */
return array(
    'dept'    => 'EX-CEO',
    'dept_ar' => 'مساحة الرئيس التنفيذي',
    'unit'    => 'مساحة الرئيس التنفيذي',
    'role_id' => 9,
    'dir'     => 'portal',
    'neighbor_route' => 'portal/exec_contract_registry.php',
    'screens' => array(

array(
    'code' => 'ceo_org_decisions', 'file' => 'ceo_org_decisions.php', 'placement_id' => 1891,
    'surface' => 'قرارات الهيكل التنظيمي', 'title' => 'قرارات الهيكل التنظيمي',
    'icon' => 'fa fa-gavel',
    'table' => 'ceo_org_decisions', 'table_mode' => 'extend', 'nature' => 'document',
    'group' => 'القرار والحوكمة العليا', 'sort_no' => 3,
    'grain' => 'قرارٌ هيكليٌّ واحد — تغييرُ بنيةٍ دائمةٍ أو شبهِ دائمة',
    'target_ref' => 'EX-CEO·21·قرارات الهيكل التنظيمي',
    'intro' => 'سجلُّ القراراتِ التي تغيّر بنيةَ المنظّمةِ تغييرًا دائمًا أو شبهَ دائم — '
             . 'وحبّتُه قرارٌ واحد. وخريطةُ الهيكلِ وسجلُّ التكليفاتِ سطحان آخران بحبّتَين أُخريَين.',
    'rule'  => 'القرارُ يسري من تاريخِ النفاذِ لا من تاريخِ التوقيع · وأثرُه على الأدوارِ '
             . 'والصلاحيّاتِ يُكتب هنا ويُطبَّق من شاشةِ الصلاحيّات (§13: إسقاطُ قرارٍ لا حقيقةٌ موازية)',
    'empty' => 'لا قرارَ هيكليٌّ مسجَّلٌ بعد',
    'order' => 'decision_date DESC, id DESC',
    'pk_code' => array('decision_no', 'ORG'),
    'create' => array('decision_date', 'change_desc', 'change_reason', 'effective_date'),
    /* ترتيبُ الورقةِ حرفًا: 1..18 */
    'keys' => array('decision_no', 'decision_date', 'decision_kind', 'affected_unit',
                    'change_desc', 'change_reason', 'admin_vp_review', 'roles_perms_impact',
                    'effective_date', 'decision_doc', 'decision_state', 'created_by',
                    'created_at', 'reviewer_name', 'approved_by', 'approved_on',
                    'data_state', 'src_ref'),
    'types' => array('decision_date' => 'DATE NULL DEFAULT NULL',
                     'effective_date' => 'DATE NULL DEFAULT NULL',
                     'approved_on' => 'DATE NULL DEFAULT NULL'),
),

),
);
