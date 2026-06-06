<?php
/**
 * Column — تعريف عمود واحد ضمن كيان قابل للاستيراد/التصدير.
 *
 * جزء من إطار Excel الموحّد. كل عمود يصف: الحقل في قاعدة البيانات، التسمية
 * العربية المعروضة، النوع، قواعد التحقق، وقيمة مثال للنموذج.
 *
 * @package App\Services\Excel
 */

declare(strict_types=1);

namespace App\Services\Excel;

class Column
{
    /** الأنواع المدعومة. */
    public const TYPE_STRING = 'string';
    public const TYPE_INT    = 'int';
    public const TYPE_FLOAT  = 'float';
    public const TYPE_DATE   = 'date';
    public const TYPE_EMAIL  = 'email';
    public const TYPE_PHONE  = 'phone';
    public const TYPE_ENUM   = 'enum';

    /** @var string اسم العمود في قاعدة البيانات. */
    public $field;

    /** @var string التسمية العربية المعروضة في رأس الجدول/النموذج. */
    public $label;

    /** @var string النوع (أحد ثوابت TYPE_*). */
    public $type = self::TYPE_STRING;

    /** @var bool هل الحقل مطلوب؟ */
    public $required = false;

    /** @var bool هل يجب أن تكون القيمة فريدة (ضمن نطاق الشركة)؟ */
    public $unique = false;

    /** @var array القيم المقبولة عندما يكون النوع enum. */
    public $enum = [];

    /** @var mixed القيمة الافتراضية عند غياب القيمة. */
    public $default = null;

    /** @var string|null مثال يُعرض في النموذج. */
    public $example = null;

    /** @var int عرض العمود في Excel. */
    public $width = 20;

    /** @var array|null مفتاح أجنبي: ['table'=>..., 'column'=>..., 'scoped'=>bool]. */
    public $foreignKey = null;

    /** @var bool هل يُدرَج هذا العمود عند الاستيراد؟ (التصدير قد يعرض أعمدة محسوبة). */
    public $importable = true;

    /** @var bool هل يظهر في التصدير؟ */
    public $exportable = true;

    /** @var string|null تعبير SQL مخصّص للتصدير (مثل DATE_FORMAT). */
    public $exportExpr = null;

    /** @var string|null ملاحظة إرشادية للمستخدم. */
    public $hint = null;

    /**
     * @var array|null إعداد «بحث/Lookup»: يحوّل قيمة مقروءة (اسم/كود) يدخلها
     * المستخدم إلى مفتاح أجنبي يُخزَّن في عمود آخر، مع إعادة كتابة الاسم القانوني
     * في حقل هذا العمود نفسه. مفاتيحه:
     *   'table'      => اسم الجدول المرجعي (مثل 'clients').
     *   'idColumn'   => عمود المعرف في الجدول المرجعي (افتراضي 'id').
     *   'storeIdIn'  => عمود قاعدة البيانات الذي يُخزَّن فيه المعرف (مثل 'client_id').
     *   'matchBy'    => أعمدة المطابقة بالترتيب (مثل ['client_code','client_name']).
     *   'nameColumn' => عمود الاسم القانوني المُعاد (مثل 'client_name').
     *   'scoped'     => bool هل يُقيَّد البحث بنطاق الشركة (company_id)؟
     *   'softDelete' => string|null عمود الحذف الناعم للاستبعاد (مثل 'is_deleted').
     */
    public $lookup = null;

    /**
     * @param string $field
     * @param string $label
     * @param array  $options مفاتيح اختيارية تطابق خصائص الصنف.
     */
    public function __construct(string $field, string $label, array $options = [])
    {
        $this->field = $field;
        $this->label = $label;

        foreach ($options as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }

        // ضبط افتراضي معقول لعرض الأعمدة النصية الطويلة.
        if (!isset($options['width'])) {
            if ($this->type === self::TYPE_EMAIL || in_array($this->field, ['name', 'client_name', 'full_name'], true)) {
                $this->width = 32;
            }
        }
    }

    /** نص الرأس في النموذج (يضيف إشارة «مطلوب/فريد» وقائمة قيم enum). */
    public function templateHeader(): string
    {
        $parts = [$this->label];
        $flags = [];
        if ($this->required) {
            $flags[] = 'مطلوب';
        }
        if ($this->unique) {
            $flags[] = 'فريد';
        }
        if ($flags) {
            $parts[] = '(' . implode(' - ', $flags) . ')';
        }
        if ($this->type === self::TYPE_ENUM && $this->enum) {
            $parts[] = '(' . implode(' / ', $this->enum) . ')';
        }
        return implode("\n", $parts);
    }
}
