<?php
/**
 * فهرس ثوابت أحداث الإسناد — CON-02 §8 (نمط includes/roles.php · ADR-07)
 * ───────────────────────────────────────────────────────────────────────────
 * الأحداثُ الثلاثةُ التي تنصّ عليها الوثيقة لدورة إسناد زمن الوحدة. تُسجَّل
 * هنا **ثوابتَ فقط** في دفعة الأساس (2026-07-28): لا دالةَ نشرٍ ولا مستهلك —
 * فمنتِجُها (AttributionService · T-04) دفعةٌ لاحقة، ودالةُ نشرٍ بلا مُستدعٍ
 * كودٌ ميتٌ يُصان بلا فائدة.
 *
 * ⚠️ الصيغةُ ليست اجتهادًا في التسمية بل عقدٌ يفرضه الناشر:
 *   `EventPublisher::publish()` يرفض أي مفتاحٍ لا يطابق
 *       /^[a-z]+(\.[a-z_]+){1,2}$/
 *   (app/Core/EventPublisher.php:111). فأسماءُ الوثيقة بصيغة PascalCase —
 *   AttributionDecided · AttributionObjected · AttributionOverridden —
 *   **يرفضها الناقلُ بنيويًّا**، ولا يُكتشف ذلك إلا عند أول نشرٍ فعليّ.
 *   ولذلك المفاتيحُ منقّطةٌ هنا موافقةً لكل مفاتيح القاعدة الحية
 *   (revenue.unit.recognized · settlement.approved · workflow.state_changed).
 *   المقابلةُ بين اسم الوثيقة والمفتاح المنفَّذ موثّقةٌ في كل ثابتٍ أدناه.
 *
 * الاستعمالُ المنتظَر (دفعةُ AttributionService):
 *     require_once __DIR__ . '/../includes/attribution_events.php';
 *     EventPublisher::publish($conn, array('event_key' => EMS_EVT_ATTRIBUTION_DECIDED, ...));
 *
 * وهذا الملفُّ غيرُ مُحمَّلٍ من config.php عمدًا: لا مستهلكَ له اليوم، فلا
 * يُثقَّل كلُّ طلبٍ بقراءته. مَن يستعمله يطلبه صراحةً كما أعلاه.
 *
 * الحقلُ `category` في ems_business_events نصٌّ varchar(20) لا ENUM — فلا هجرةَ
 * لأيٍّ من هذه المفاتيح (تحقُّقٌ مقيسٌ على المخطط الحي 2026-07-28).
 */

/** قرارُ الإسناد وقع: زمنُ الواقعة نُسب إلى بند التزامٍ وطرفٍ ملتزم.
 *  اسمُ الوثيقة: AttributionDecided (CON-02 §8) */
const EMS_EVT_ATTRIBUTION_DECIDED = 'attribution.decided';

/** اعتراضٌ موثَّقٌ على إسنادٍ وقع — بسببه ومرجعه (§5: الاعتراضُ يُوثَّق ولا يُهمل).
 *  اسمُ الوثيقة: AttributionObjected (CON-02 §8) */
const EMS_EVT_ATTRIBUTION_OBJECTED = 'attribution.objected';

/** تجاوزُ إسنادٍ سابقٍ بقرارِ حسمٍ أعلى — يُسجَّل ولا يُمحى الأصل.
 *  اسمُ الوثيقة: AttributionOverridden (CON-02 §8) */
const EMS_EVT_ATTRIBUTION_OVERRIDDEN = 'attribution.overridden';

/**
 * عكسُ إسنادٍ **بعد أن ولّد مالًا** — سطرٌ عاكسٌ يُضاف والأصلُ يبقى (قرارُ
 * المالك 2026-07-28، الرابعُ خارج ثلاثة الوثيقة).
 *
 * ولمَ لا يُعاد استعمالُ `attribution.overridden`؟ لأن معناهما مختلف:
 *   • `overridden` = حُسم اعتراضٌ ببندٍ آخر (ق-25) — قرارٌ **لم يمسّ مالًا**.
 *   • `reversed`   = حكمٌ ماليٌّ قائمٌ أُبطل بقيدٍ عاكس — فيه `reverses_event_id`.
 * وخلطُهما يُعمي أيَّ تقريرٍ يسأل «كم قيدًا عُكس؟» عن جوابه.
 */
const EMS_EVT_ATTRIBUTION_REVERSED = 'attribution.reversed';

/**
 * حكمٌ على **كمية** الواقعة نفسِها لا على سطور زمنها (M-24 · CON-02 §3-④).
 *
 * ولمَ لا يُعاد استعمالُ `attribution.decided`؟ لأن المحكومَ مختلف: `decided`
 * ينسب **زمنًا** إلى بندِ التزام، وهذا يقرّر **هل الكميةُ المنجزةُ تُفوتر أصلًا**
 * («إعادةُ التنفيذ لعيبٍ لا تُفوتر»). وخلطُهما يُعمي سؤالَ «كم واقعةً مُنعت
 * فوترةُ كميتها ولماذا؟» عن جوابه — وهو سؤالُ مراجعةٍ ماليٌّ لا إداري.
 */
const EMS_EVT_ATTRIBUTION_QTY_RULED = 'attribution.qty_ruled';

/** الخمسةُ مجتمعةً — للتحقق والاختبار (نمط EMS_ROLES_* في roles.php). */
const EMS_EVT_ATTRIBUTION_ALL = array(
    EMS_EVT_ATTRIBUTION_DECIDED,
    EMS_EVT_ATTRIBUTION_OBJECTED,
    EMS_EVT_ATTRIBUTION_OVERRIDDEN,
    EMS_EVT_ATTRIBUTION_REVERSED,
    EMS_EVT_ATTRIBUTION_QTY_RULED,
);

/** تصنيفُ الأحداث الثلاثة في الناقل — تشغيليٌّ لا ماليّ: الإسنادُ قرارٌ يسبق
 *  المالَ وتستهلكه أحكامُ الأطراف **قبل** توليد آثار FES (§8 · T-06). */
const EMS_EVT_ATTRIBUTION_CATEGORY = 'operational';
