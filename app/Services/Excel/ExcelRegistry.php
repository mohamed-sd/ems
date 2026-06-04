<?php
/**
 * ExcelRegistry — السجلّ المركزي لتعريفات الكيانات (مصدر الحقيقة الوحيد).
 *
 * لإضافة دعم Excel لشاشة جديدة: عرّف الكيان هنا فقط — التصدير والنموذج
 * والاستيراد والتحقق تعمل تلقائياً عبر إطار العمل الموحّد.
 *
 * @package App\Services\Excel
 */

declare(strict_types=1);

namespace App\Services\Excel;

class ExcelRegistry
{
    /** @var EntityDefinition[]|null */
    private static $definitions = null;

    /** @return EntityDefinition[] */
    public static function all(): array
    {
        if (self::$definitions === null) {
            self::$definitions = self::build();
        }
        return self::$definitions;
    }

    /** الحصول على تعريف كيان بالمفتاح، أو null إن لم يكن مسجّلاً. */
    public static function get(string $key): ?EntityDefinition
    {
        $all = self::all();
        return $all[$key] ?? null;
    }

    /** تسجيل/استبدال تعريف كيان برمجياً (للتوسّع المستقبلي). */
    public static function register(EntityDefinition $definition): void
    {
        self::all();
        self::$definitions[$definition->key] = $definition;
    }

    /** @return EntityDefinition[] */
    private static function build(): array
    {
        $defs = [];

        // ─────────────────────────── العملاء (Clients) ───────────────────────────
        $defs['clients'] = new EntityDefinition('clients', 'العملاء', 'clients', [
            new Column('client_code', 'كود العميل', ['required' => true, 'unique' => true, 'width' => 18, 'example' => 'CLT-0001']),
            new Column('client_name', 'اسم العميل', ['required' => true, 'width' => 35, 'example' => 'شركة النيل للمقاولات']),
            new Column('entity_type', 'نوع الكيان', ['example' => 'حكومي']),
            new Column('sector_category', 'تصنيف القطاع', ['width' => 25, 'example' => 'بنية تحتية']),
            new Column('phone', 'رقم الهاتف', ['type' => Column::TYPE_PHONE, 'example' => '+249123456789']),
            new Column('email', 'البريد الإلكتروني', ['type' => Column::TYPE_EMAIL, 'example' => 'nile@example.com']),
            new Column('whatsapp', 'واتساب', ['type' => Column::TYPE_PHONE, 'example' => '+249123456789']),
            new Column('status', 'الحالة', ['type' => Column::TYPE_ENUM, 'enum' => ['نشط', 'متوقف'], 'default' => 'نشط', 'width' => 14]),
            new Column('created_at', 'تاريخ الإضافة', ['type' => Column::TYPE_DATE, 'importable' => false, 'exportExpr' => "DATE_FORMAT(created_at, '%Y-%m-%d')"]),
        ], [
            'moduleCode'   => 'clients',
            'instructions' => [
                'احذف صفوف الأمثلة قبل رفع الملف.',
                'كود العميل يجب أن يكون فريداً — أي كود مكرر سيُستبعد.',
                'الحقول المطلوبة: كود العميل + اسم العميل.',
            ],
        ]);

        // ─────────────────────────── الموردون (Suppliers) ───────────────────────────
        $defs['suppliers'] = new EntityDefinition('suppliers', 'الموردون', 'suppliers', [
            new Column('supplier_code', 'كود المورد', ['unique' => true, 'width' => 18, 'example' => 'SUP-0001']),
            new Column('name', 'اسم المورد', ['required' => true, 'width' => 32, 'example' => 'مؤسسة المعدات الحديثة']),
            new Column('supplier_type', 'نوع المورد', ['type' => Column::TYPE_ENUM, 'enum' => ['فرد', 'شركة', 'وسيط', 'مالك', 'جهة حكومية'], 'example' => 'شركة']),
            new Column('dealing_nature', 'طبيعة التعامل', ['width' => 25, 'example' => 'تأجير معدات']),
            new Column('phone', 'رقم الهاتف', ['type' => Column::TYPE_PHONE, 'required' => true, 'example' => '+249912345678']),
            new Column('phone_alternative', 'هاتف بديل', ['type' => Column::TYPE_PHONE]),
            new Column('email', 'البريد الإلكتروني', ['type' => Column::TYPE_EMAIL]),
            new Column('commercial_registration', 'السجل التجاري', ['width' => 22]),
            new Column('contact_person_name', 'اسم المسؤول', ['width' => 25]),
            new Column('contact_person_phone', 'هاتف المسؤول', ['type' => Column::TYPE_PHONE]),
            new Column('full_address', 'العنوان', ['width' => 30]),
            new Column('created_at', 'تاريخ الإضافة', ['type' => Column::TYPE_DATE, 'importable' => false, 'exportExpr' => "DATE_FORMAT(created_at, '%Y-%m-%d')"]),
        ], [
            'moduleCode'   => 'suppliers',
            'instructions' => ['الحقول المطلوبة: اسم المورد + رقم الهاتف.'],
        ]);

        // ─────────────────────────── السائقون (Drivers) ───────────────────────────
        $defs['drivers'] = new EntityDefinition('drivers', 'السائقون', 'drivers', [
            new Column('driver_code', 'كود السائق', ['unique' => true, 'width' => 18, 'example' => 'DRV-0001']),
            new Column('name', 'اسم السائق', ['required' => true, 'width' => 32, 'example' => 'محمد أحمد علي']),
            new Column('phone', 'رقم الهاتف', ['type' => Column::TYPE_PHONE, 'required' => true, 'example' => '+249912345678']),
            new Column('phone_alternative', 'هاتف بديل', ['type' => Column::TYPE_PHONE]),
            new Column('identity_type', 'نوع الهوية', ['example' => 'رقم وطني']),
            new Column('identity_number', 'رقم الهوية', ['width' => 22]),
            new Column('license_number', 'رقم الرخصة', ['width' => 20]),
            new Column('license_type', 'نوع الرخصة', ['example' => 'درجة أولى']),
            new Column('license_expiry_date', 'انتهاء الرخصة', ['type' => Column::TYPE_DATE, 'example' => '2027-01-01']),
            new Column('skill_level', 'مستوى المهارة', ['example' => 'خبير']),
            new Column('email', 'البريد الإلكتروني', ['type' => Column::TYPE_EMAIL]),
            new Column('address', 'العنوان', ['width' => 30]),
            new Column('driver_status', 'الحالة', ['type' => Column::TYPE_ENUM, 'enum' => ['نشط', 'متوقف'], 'default' => 'نشط', 'width' => 14]),
        ], [
            'moduleCode'      => 'drivers',
            'softDeleteColumn' => null,
            'createdByColumn'  => null,
            'instructions'    => ['الحقول المطلوبة: اسم السائق + رقم الهاتف.'],
        ]);

        // ─────────────────────────── المعدات (Equipments) ───────────────────────────
        $defs['equipments'] = new EntityDefinition('equipments', 'المعدات', 'equipments', [
            new Column('code', 'كود المعدة', ['required' => true, 'unique' => true, 'width' => 18, 'example' => 'EQP-0001']),
            new Column('name', 'اسم المعدة', ['required' => true, 'width' => 30, 'example' => 'حفار هيدروليكي']),
            new Column('type', 'النوع', ['required' => true, 'example' => 'حفار']),
            new Column('manufacturer', 'الصانع', ['example' => 'Caterpillar']),
            new Column('model', 'الموديل', ['example' => '320D']),
            new Column('manufacturing_year', 'سنة الصنع', ['type' => Column::TYPE_INT, 'example' => '2019']),
            new Column('serial_number', 'الرقم التسلسلي', ['width' => 22]),
            new Column('chassis_number', 'رقم الشاسيه', ['width' => 22]),
            new Column('equipment_condition', 'الحالة الفنية', ['example' => 'في حالة جيدة']),
            new Column('current_location', 'الموقع الحالي', ['width' => 25]),
            new Column('daily_rental_price', 'سعر التأجير اليومي', ['type' => Column::TYPE_FLOAT, 'example' => '1500']),
        ], [
            'moduleCode'       => 'equipments',
            'softDeleteColumn' => null,
            'createdByColumn'  => null,
            'instructions'     => [
                'الحقول المطلوبة: كود المعدة + اسم المعدة + النوع.',
                'ربط المعدة بالمورد يتم لاحقاً من شاشة المعدات.',
            ],
        ]);

        // ─────────────────────────── المشاريع (Projects) ───────────────────────────
        $defs['projects'] = new EntityDefinition('projects', 'المشاريع', 'project', [
            new Column('project_code', 'كود المشروع', ['unique' => true, 'width' => 18, 'example' => 'PRJ-0001']),
            new Column('name', 'اسم المشروع', ['required' => true, 'width' => 32, 'example' => 'مشروع طريق الإنقاذ الغربي']),
            new Column('client', 'العميل', ['required' => true, 'width' => 28, 'example' => 'الهيئة العامة للطرق']),
            new Column('location', 'الموقع', ['required' => true, 'width' => 25, 'example' => 'ولاية الخرطوم']),
            new Column('category', 'التصنيف', ['example' => 'بنية تحتية']),
            new Column('state', 'الولاية', ['example' => 'الخرطوم']),
            new Column('region', 'المنطقة', ['example' => 'أمدرمان']),
            new Column('total', 'القيمة الإجمالية', ['required' => true, 'example' => '1000000']),
        ], [
            'moduleCode'   => 'projects',
            'instructions' => ['الحقول المطلوبة: اسم المشروع + العميل + الموقع + القيمة الإجمالية.'],
        ]);

        // ─────────────────────────── أنواع المعدات (Equipment Types) ───────────────────────────
        $defs['equipment_types'] = new EntityDefinition('equipment_types', 'أنواع المعدات', 'equipments_types', [
            new Column('form', 'كود الشكل', ['required' => true, 'width' => 14, 'example' => '1', 'hint' => 'رمز رقمي للشكل']),
            new Column('type', 'اسم النوع', ['required' => true, 'width' => 28, 'example' => 'حفار']),
            new Column('status', 'الحالة', ['type' => Column::TYPE_ENUM, 'enum' => ['active', 'inactive'], 'default' => 'active', 'width' => 14]),
            new Column('created_at', 'تاريخ الإضافة', ['type' => Column::TYPE_DATE, 'importable' => false, 'exportExpr' => "DATE_FORMAT(created_at, '%Y-%m-%d')"]),
        ], [
            'moduleCode'       => 'equipments_types',
            'companyScoped'    => false,
            'softDeleteColumn' => null,
            'createdByColumn'  => null,
            'instructions'     => ['الحقول المطلوبة: كود الشكل + اسم النوع.'],
        ]);

        // ─────────────────────────── أكواد الأعطال (Failure Codes) ───────────────────────────
        $defs['failure_codes'] = new EntityDefinition('failure_codes', 'أكواد الأعطال', 'failure_codes', [
            new Column('equipment_type', 'نوع المعدة (كود)', ['type' => Column::TYPE_INT, 'required' => true, 'width' => 16, 'example' => '1']),
            new Column('event_type_code', 'كود نوع الحدث', ['required' => true, 'width' => 16, 'example' => 'E01']),
            new Column('event_type_name', 'اسم نوع الحدث', ['required' => true, 'width' => 24, 'example' => 'عطل ميكانيكي']),
            new Column('main_category_code', 'كود التصنيف الرئيسي', ['required' => true, 'width' => 18, 'example' => 'M01']),
            new Column('main_category_name', 'التصنيف الرئيسي', ['required' => true, 'width' => 24, 'example' => 'المحرك']),
            new Column('sub_category', 'التصنيف الفرعي', ['required' => true, 'width' => 24, 'example' => 'نظام التبريد']),
            new Column('failure_detail', 'تفصيل العطل', ['required' => true, 'width' => 30, 'example' => 'ارتفاع حرارة الماء']),
            new Column('full_code', 'الكود الكامل', ['required' => true, 'unique' => true, 'width' => 20, 'example' => '1-E01-M01']),
            new Column('status', 'الحالة', ['type' => Column::TYPE_INT, 'default' => 1, 'width' => 12]),
        ], [
            'moduleCode'       => 'Equipments/manage_failure_codes.php',
            'companyScoped'    => false,
            'softDeleteColumn' => null,
            'createdByColumn'  => null,
            'instructions'     => ['نوع المعدة كود رقمي (1=حفار، 2=قلاب، 3=خرامة).', 'الكود الكامل يجب أن يكون فريداً.'],
        ]);

        // ─────────────────────────── التشغيل/الحركات (Operations) ───────────────────────────
        $defs['operations'] = new EntityDefinition('operations', 'حركات التشغيل', 'operations', [
            new Column('equipment', 'المعدة', ['required' => true, 'width' => 24, 'example' => 'EQP-0001']),
            new Column('equipment_type', 'نوع المعدة', ['width' => 18, 'example' => 'حفار']),
            new Column('equipment_category', 'فئة المعدة', ['required' => true, 'width' => 16, 'example' => 'أساسي']),
            new Column('project_id', 'رقم المشروع', ['required' => true, 'width' => 14, 'example' => '12', 'foreignKey' => ['table' => 'project', 'column' => 'id', 'scoped' => true]]),
            new Column('contract_id', 'رقم العقد', ['required' => true, 'width' => 14, 'example' => '5']),
            new Column('supplier_id', 'رقم المورد', ['required' => true, 'width' => 14, 'example' => '3']),
            new Column('start', 'بداية التشغيل', ['required' => true, 'width' => 18, 'example' => '2026-01-01']),
            new Column('end', 'نهاية التشغيل', ['required' => true, 'width' => 18, 'example' => '2026-01-31']),
            new Column('reason', 'البيان', ['required' => true, 'width' => 28, 'example' => 'تشغيل بالمشروع']),
            new Column('days', 'عدد الأيام', ['required' => true, 'width' => 12, 'example' => '30']),
            new Column('shift_type', 'نوبة العمل', ['type' => Column::TYPE_ENUM, 'enum' => ['B', 'D', 'N'], 'default' => 'B', 'width' => 12]),
            new Column('total_equipment_hours', 'إجمالي ساعات المعدة', ['type' => Column::TYPE_FLOAT, 'width' => 18]),
            new Column('shift_hours', 'ساعات النوبة', ['type' => Column::TYPE_FLOAT, 'width' => 14]),
        ], [
            'moduleCode'       => 'Oprators/oprators.php',
            'softDeleteColumn' => null,
            'createdByColumn'  => null,
            'instructions'     => ['الحقول المطلوبة: المعدة + فئة المعدة + رقم المشروع/العقد/المورد + بداية ونهاية التشغيل + البيان + الأيام.'],
        ]);

        // ─────────────────────────── ساعات العمل (Timesheet) ───────────────────────────
        $defs['timesheet'] = new EntityDefinition('timesheet', 'ساعات العمل', 'timesheet', [
            new Column('operator', 'المشغّل', ['required' => true, 'width' => 22, 'example' => 'محمد علي']),
            new Column('driver', 'المعدة/السائق', ['required' => true, 'width' => 22, 'example' => 'EQP-0001']),
            new Column('shift', 'الوردية', ['required' => true, 'width' => 12, 'example' => 'D']),
            new Column('date', 'التاريخ', ['required' => true, 'width' => 16, 'example' => '2026-01-15']),
            new Column('type', 'نوع الكشف (كود)', ['required' => true, 'width' => 16, 'example' => '1', 'hint' => '1=حفار، 2=قلاب، 3=خرامة']),
            new Column('shift_hours', 'ساعات الوردية', ['type' => Column::TYPE_FLOAT, 'width' => 14, 'example' => '10']),
            new Column('executed_hours', 'الساعات المنفذة', ['type' => Column::TYPE_FLOAT, 'width' => 14]),
            new Column('total_work_hours', 'إجمالي ساعات العمل', ['type' => Column::TYPE_FLOAT, 'width' => 16]),
        ], [
            'moduleCode'       => 'Timesheet/timesheet.php',
            'softDeleteColumn' => null,
            'createdByColumn'  => 'user_id',
            'instructions'     => ['الحقول المطلوبة: المشغّل + المعدة + الوردية + التاريخ + نوع الكشف.', 'الورديات: D (نهار) / N (ليل).'],
        ]);

        // ─────────────────────────── عقود المشاريع (Contracts) ───────────────────────────
        $defs['contracts'] = new EntityDefinition('contracts', 'عقود المشاريع', 'contracts', [
            new Column('project_id', 'رقم المشروع', ['type' => Column::TYPE_INT, 'required' => true, 'width' => 14, 'example' => '12', 'foreignKey' => ['table' => 'project', 'column' => 'id', 'scoped' => true]]),
            new Column('contract_signing_date', 'تاريخ توقيع العقد', ['type' => Column::TYPE_DATE, 'required' => true, 'width' => 18, 'example' => '2026-01-01']),
            new Column('contract_duration_months', 'مدة العقد (أشهر)', ['type' => Column::TYPE_INT, 'width' => 14, 'example' => '12']),
            new Column('contract_duration_days', 'مدة العقد (أيام)', ['type' => Column::TYPE_INT, 'width' => 14, 'example' => '365']),
            new Column('first_party', 'الطرف الأول', ['width' => 26]),
            new Column('second_party', 'الطرف الثاني', ['width' => 26]),
            new Column('price_currency_contract', 'عملة العقد', ['width' => 14, 'example' => 'SDG']),
            new Column('paid_contract', 'قيمة العقد', ['width' => 16, 'example' => '1000000']),
            new Column('created_at', 'تاريخ الإنشاء', ['type' => Column::TYPE_DATE, 'importable' => false, 'exportExpr' => "DATE_FORMAT(created_at, '%Y-%m-%d')"]),
        ], [
            'moduleCode'   => 'Contracts/contracts.php',
            'instructions' => ['الحقول المطلوبة: رقم المشروع + تاريخ توقيع العقد.', 'رقم المشروع يجب أن يكون موجوداً مسبقاً في النظام.'],
        ]);

        // ─────────────────────────── عقود الموردين (Supplier Contracts) ───────────────────────────
        $defs['supplier_contracts'] = new EntityDefinition('supplier_contracts', 'عقود الموردين', 'supplierscontracts', [
            new Column('supplier_id', 'رقم المورد', ['type' => Column::TYPE_INT, 'required' => true, 'width' => 14, 'example' => '3', 'foreignKey' => ['table' => 'suppliers', 'column' => 'id', 'scoped' => true]]),
            new Column('project_id', 'رقم المشروع', ['type' => Column::TYPE_INT, 'required' => true, 'width' => 14, 'example' => '12', 'foreignKey' => ['table' => 'project', 'column' => 'id', 'scoped' => true]]),
            new Column('contract_signing_date', 'تاريخ توقيع العقد', ['type' => Column::TYPE_DATE, 'required' => true, 'width' => 18, 'example' => '2026-01-01']),
            new Column('equip_type', 'نوع المعدة', ['width' => 18]),
            new Column('first_party', 'الطرف الأول', ['width' => 26]),
            new Column('second_party', 'الطرف الثاني', ['width' => 26]),
            new Column('created_at', 'تاريخ الإنشاء', ['type' => Column::TYPE_DATE, 'importable' => false, 'exportExpr' => "DATE_FORMAT(created_at, '%Y-%m-%d')"]),
        ], [
            'moduleCode'       => 'Suppliers/supplierscontracts.php',
            'softDeleteColumn' => null,
            'createdByColumn'  => null,
            'instructions'     => ['الحقول المطلوبة: رقم المورد + رقم المشروع + تاريخ توقيع العقد.', 'رقم المورد ورقم المشروع يجب أن يكونا موجودين مسبقاً.'],
        ]);

        // ─────────────────────────── عقود السائقين (Driver Contracts) ───────────────────────────
        $defs['driver_contracts'] = new EntityDefinition('driver_contracts', 'عقود السائقين', 'drivercontracts', [
            new Column('driver_id', 'رقم السائق', ['type' => Column::TYPE_INT, 'required' => true, 'width' => 14, 'example' => '7', 'foreignKey' => ['table' => 'drivers', 'column' => 'id', 'scoped' => true]]),
            new Column('project_id', 'رقم المشروع', ['type' => Column::TYPE_INT, 'required' => true, 'width' => 14, 'example' => '12', 'foreignKey' => ['table' => 'project', 'column' => 'id', 'scoped' => true]]),
            new Column('contract_signing_date', 'تاريخ توقيع العقد', ['type' => Column::TYPE_DATE, 'required' => true, 'width' => 18, 'example' => '2026-01-01']),
            new Column('first_party', 'الطرف الأول', ['width' => 26]),
            new Column('second_party', 'الطرف الثاني', ['width' => 26]),
            new Column('created_at', 'تاريخ الإنشاء', ['type' => Column::TYPE_DATE, 'importable' => false, 'exportExpr' => "DATE_FORMAT(created_at, '%Y-%m-%d')"]),
        ], [
            'moduleCode'       => 'Drivers/drivercontracts.php',
            'softDeleteColumn' => null,
            'createdByColumn'  => null,
            'instructions'     => ['الحقول المطلوبة: رقم السائق + رقم المشروع + تاريخ توقيع العقد.'],
        ]);

        // ─────────────────────────── سجل النشاطات (Activity Logs — تصدير فقط) ───────────────────────────
        $defs['activity_logs'] = new EntityDefinition('activity_logs', 'سجل النشاطات', 'activity_logs', [
            new Column('created_at', 'التاريخ والوقت', ['importable' => false, 'width' => 20, 'exportExpr' => "DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s')"]),
            new Column('role_name', 'الدور', ['importable' => false, 'width' => 18]),
            new Column('screen_name', 'الشاشة', ['importable' => false, 'width' => 22]),
            new Column('module_name', 'الوحدة', ['importable' => false, 'width' => 18]),
            new Column('action_type', 'نوع العملية', ['importable' => false, 'width' => 16]),
            new Column('button_name', 'الإجراء', ['importable' => false, 'width' => 18]),
            new Column('record_id', 'رقم السجل', ['importable' => false, 'width' => 12]),
            new Column('ip_address', 'عنوان IP', ['importable' => false, 'width' => 16]),
            new Column('http_method', 'الطريقة', ['importable' => false, 'width' => 10]),
            new Column('response_status', 'الاستجابة', ['importable' => false, 'width' => 12]),
        ], [
            'moduleCode'       => 'ActivityLogs/activity_logs.php',
            'softDeleteColumn' => null,
            'createdByColumn'  => null,
            'exportOrderBy'    => 'created_at DESC',
            'instructions'     => ['هذا الكيان للتصدير فقط (سجل تدقيق).'],
        ]);

        return $defs;
    }
}
