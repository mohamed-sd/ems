<?php
/**
 * portal/ceo_org_decisions.php — قرارات الهيكل التنظيمي (EX-CEO · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: قرارٌ هيكليٌّ واحد — تغييرُ بنيةٍ دائمةٍ أو شبهِ دائمة
 * المالك: مساحة الرئيس التنفيذي · مصدرُ الحقيقة: ceo_org_decisions
 * الأصل: ورقةُ «مساحة الرئيس التنفيذي» — السطح «قرارات الهيكل التنظيمي»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'portal/ceo_org_decisions.php',
    'screen'     => 'ceo_org_decisions',
    'table'      => 'ceo_org_decisions',
    'title'      => 'قرارات الهيكل التنظيمي',
    'icon'       => 'fa fa-gavel',
    'nature'     => 'document',
    'doc'        => '01 · الدليل المعماري · قرارات الهيكل التنظيمي',
    'intro'      => 'سجلُّ القراراتِ التي تغيّر بنيةَ المنظّمةِ تغييرًا دائمًا أو شبهَ دائم — وحبّتُه قرارٌ واحد. وخريطةُ الهيكلِ وسجلُّ التكليفاتِ سطحان آخران بحبّتَين أُخريَين.',
    'rule'       => 'القرارُ يسري من تاريخِ النفاذِ لا من تاريخِ التوقيع · وأثرُه على الأدوارِ والصلاحيّاتِ يُكتب هنا ويُطبَّق من شاشةِ الصلاحيّات (§13: إسقاطُ قرارٍ لا حقيقةٌ موازية)',
    'empty_hint' => 'لا قرارَ هيكليٌّ مسجَّلٌ بعد',
    'order'       => 'decision_date DESC, id DESC',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.ceo_org_decisions.register',
            'label' => 'تسجيلُ قرارات الهيكل التنظيمي',
            'rule'  => 'القرارُ يسري من تاريخِ النفاذِ لا من تاريخِ التوقيع · وأثرُه على الأدوارِ والصلاحيّاتِ يُكتب هنا ويُطبَّق من شاشةِ الصلاحيّات (§13: إسقاطُ قرارٍ لا حقيقةٌ موازية)',
            'fields' => array(
                'decision_date' => 'تاريخ القرار',
                'change_desc' => 'وصف التغيير',
                'change_reason' => 'مبرر التغيير',
                'effective_date' => 'تاريخ النفاذ',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('decision_date', 'change_desc', 'change_reason', 'effective_date');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('ceo_org_decisions', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                $nid = (int) $conn->insert_id;
                if ($nid > 0) {
                    $code = 'ORG-' . str_pad((string) $nid, 5, '0', STR_PAD_LEFT);
                    try {
                        ems_tenant_db()->update('ceo_org_decisions',
                            array('decision_no' => $code), array('id' => $nid));
                    } catch (\Throwable $t) { /* السطرُ مسجَّلٌ ومفتاحُه يُستكمل بمرورٍ لاحق */ }
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في ceo_org_decisions');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
