<?php
/**
 * EntityDefinition — وصف كيان قابل للاستيراد/التصدير عبر إطار Excel الموحّد.
 *
 * يجمع كل ما يحتاجه الإطار للتعامل مع جدول معيّن: العنوان، الجدول، الأعمدة،
 * نطاق الشركة، عمود الحذف الناعم، رمز الوحدة للصلاحيات، وقيم إدراج إضافية.
 *
 * @package App\Services\Excel
 */

declare(strict_types=1);

namespace App\Services\Excel;

class EntityDefinition
{
    /** @var string المفتاح الفريد للكيان (يُستخدم في الرابط: ?entity=...). */
    public $key;

    /** @var string عنوان معروض (مثل: «العملاء»). */
    public $title;

    /** @var string اسم جدول قاعدة البيانات. */
    public $table;

    /** @var Column[] */
    public $columns = [];

    /** @var bool هل يُطبَّق عزل الشركة (company_id)؟ */
    public $companyScoped = true;

    /** @var string اسم عمود معرف الشركة في الجدول. */
    public $companyColumn = 'company_id';

    /** @var string|null عمود الحذف الناعم (يُفلتر في التصدير ويُضاف 0 عند الإدراج). */
    public $softDeleteColumn = 'is_deleted';

    /** @var string|null عمود المُنشئ (يُملأ بمعرف المستخدم عند الإدراج). */
    public $createdByColumn = 'created_by';

    /** @var string رمز الوحدة لفحص الصلاحيات (يُمرَّر إلى check_page_permissions). */
    public $moduleCode;

    /** @var string عمود الترتيب في التصدير. */
    public $exportOrderBy = 'id ASC';

    /** @var int الحد الأقصى لعدد الصفوف في الاستيراد الواحد. */
    public $maxRows = 2000;

    /** @var array ملاحظات إرشادية تظهر في ورقة التعليمات بالنموذج. */
    public $instructions = [];

    /**
     * @param string  $key
     * @param string  $title
     * @param string  $table
     * @param Column[] $columns
     * @param array   $options
     */
    public function __construct(string $key, string $title, string $table, array $columns, array $options = [])
    {
        $this->key        = $key;
        $this->title      = $title;
        $this->table      = $table;
        $this->columns    = $columns;
        $this->moduleCode = $key;

        foreach ($options as $opt => $value) {
            if (property_exists($this, $opt)) {
                $this->$opt = $value;
            }
        }
    }

    /** @return Column[] الأعمدة القابلة للاستيراد فقط، بالترتيب. */
    public function importColumns(): array
    {
        return array_values(array_filter($this->columns, static function (Column $c) {
            return $c->importable;
        }));
    }

    /** @return Column[] الأعمدة القابلة للتصدير فقط، بالترتيب. */
    public function exportColumns(): array
    {
        return array_values(array_filter($this->columns, static function (Column $c) {
            return $c->exportable;
        }));
    }

    /** عثور على عمود باسم الحقل. */
    public function column(string $field): ?Column
    {
        foreach ($this->columns as $c) {
            if ($c->field === $field) {
                return $c;
            }
        }
        return null;
    }
}
