<?php
/**
 * فهرسُ قاموس مصفوفة الالتزامات — CON-02 §4 (نمط includes/roles.php · ADR-07)
 * ───────────────────────────────────────────────────────────────────────────
 * مصدرٌ واحدٌ للبنود التسعة وأطرافها وآثارها، تقرؤه شاشةُ المصفوفة ولوحةُ
 * الإسناد اليومي وخدمةُ الإسناد وحزمةُ الاختبار — فلا تتفرّق التسمياتُ ولا
 * تنجرف قائمةٌ عن أخرى.
 *
 * ⚠️ **المفاتيحُ الإنجليزيةُ عقدٌ مع المخطط لا تفضيلُ تسمية**: هي حرفًا بحرف قيمُ
 * `contract_obligations.obligation_type` و`contract_hour_policies.obligation_type`
 * و`unit_time_log.obligation_type`. وتغييرُ مفتاحٍ هنا بلا هجرةٍ يجعل ENUM يبتلع
 * القيمةَ إلى `''` **صامتًا** (گوتشا موثّقة) — فلا يُمسّ مفتاحٌ إلا بهجرة.
 * والعربيةُ عرضٌ فقط.
 */

/** بنودُ §4 التسعة بترتيب الوثيقة — وهو ترتيبُ العرض في كل شاشة. */
$OBL_TYPES = array(
    'fuel'                => 'الوقودُ والزيوت',
    'access_road'         => 'الطريقُ والممرُّ وتهيئةُ الموقع',
    'loading_equipment'   => 'معداتُ التحميل / المادةُ المنقولة',
    'equipment_readiness' => 'جاهزيةُ المعدة والصيانة',
    'operators'           => 'المشغّلون والسائقون',
    'permits_safety'      => 'التصاريحُ والأمنُ والسلامة',
    'utilities'           => 'الماءُ والكهرباءُ ومرافقُ الموقع',
    'catering_camp'       => 'الإعاشةُ والمعسكر',
    'force_majeure'       => 'الظروفُ القاهرة',
);

/**
 * الطرفُ الملتزم — خماسيٌّ لا رباعيّ.
 * `none` أُضيفت في دفعة الأساس لأن §4 تعدّ «القاهرة» بندًا بينما §5-⑤ تقول
 * «لا يُفوتر ولا يُجزى أحد»؛ فـ`company` كانت ستقرأ «الشركةُ ملتزمةٌ بمنع الفيضان».
 */
$OBL_OBLIGORS = array(
    'client'   => 'العميل',
    'company'  => 'الشركة (نحن)',
    'supplier' => 'المورد',
    'operator' => 'المشغّل',
    'none'     => 'لا طرفَ ملتزمًا (قاهرة)',
);

/** أثرُ الإخلال على الفوترة — والافتراضُ `per_clause` أي «اقرأ البند» لا حكمًا مشتقًّا صامتًا. */
$OBL_EFFECTS = array(
    'billable_standby' => 'استعدادٌ مفوتر',
    'non_billable'     => 'غيرُ مفوتر',
    'per_clause'       => 'بحسب نصّ البند',
);

/** حالةُ الإجازة (ق-18) — ولا حالةَ «مرفوض»: الرفضُ نهايةُ مسودةٍ لا حالةٌ يعيش فيها صف. */
$OBL_STATES = array(
    'draft'    => 'مسودة',
    'approved' => 'مُجاز',
);

/** القائمةُ المجرّدة — للتحقق والاختبار (نمط EMS_ROLES_* في roles.php). */
if (!defined('EMS_OBLIGATION_TYPES')) {
    define('EMS_OBLIGATION_TYPES', array(
        'fuel', 'access_road', 'loading_equipment', 'equipment_readiness', 'operators',
        'permits_safety', 'utilities', 'catering_camp', 'force_majeure',
    ));
}
if (!defined('EMS_OBLIGORS')) {
    define('EMS_OBLIGORS', array('client', 'company', 'supplier', 'operator', 'none'));
}
