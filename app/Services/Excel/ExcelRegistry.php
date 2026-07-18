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
        $defs['drivers'] = new EntityDefinition('drivers', 'السائقون', 'employees', [
            new Column('employee_code', 'كود السائق', ['unique' => true, 'width' => 18, 'example' => 'DRV-0001']),
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
            // المورد الذي يعمل معه المشغّل (اختياري) — يُحوّل الاسم/الكود إلى supplier_id.
            new Column('supplier_id', 'المورد (اسم أو كود)', [
                'width'      => 24,
                'example'    => 'مؤسسة المعدات الحديثة',
                'hint'       => 'اختياري: المورد الذي يعمل معه المشغّل — أدخل اسمه أو كوده (مثل SUP-0001).',
                'lookup'     => [
                    'table'      => 'suppliers',
                    'idColumn'   => 'id',
                    'storeIdIn'  => 'supplier_id',
                    'matchBy'    => ['supplier_code', 'name'],
                    'nameColumn' => 'name',
                    'scoped'     => true,
                    'softDelete' => 'is_deleted',
                ],
                'exportExpr' => "(SELECT s.name FROM suppliers s WHERE s.id = employees.supplier_id)",
            ]),
            // المشروع المرتبط (اختياري) — يُحوّل الاسم/الكود إلى project_id.
            new Column('project_id', 'المشروع المرتبط (اسم أو كود)', [
                'width'      => 24,
                'example'    => 'مشروع طريق الإنقاذ الغربي',
                'hint'       => 'اختياري: المشروع المرتبط — أدخل اسمه أو كوده (مثل PRJ-0001).',
                'lookup'     => [
                    'table'      => 'project',
                    'idColumn'   => 'id',
                    'storeIdIn'  => 'project_id',
                    'matchBy'    => ['project_code', 'name'],
                    'nameColumn' => 'name',
                    'scoped'     => true,
                    'softDelete' => 'is_deleted',
                ],
                'exportExpr' => "(SELECT p.name FROM project p WHERE p.id = employees.project_id)",
            ]),
            new Column('employee_status', 'الحالة', ['type' => Column::TYPE_ENUM, 'enum' => ['نشط', 'متوقف'], 'default' => 'نشط', 'width' => 14]),
        ], [
            'moduleCode'      => 'drivers',
            'softDeleteColumn' => null,
            'createdByColumn'  => null,
            'instructions'    => [
                'الحقول المطلوبة: اسم السائق + رقم الهاتف.',
                'المورد والمشروع اختياريان — إن أدخلتهما (اسماً أو كوداً) يُربط المشغّل تلقائياً، وإن لم يوجد أيٌّ منهما يُرفض الصف.',
            ],
        ]);

        // ─────────────────────────── المعدات (Equipments) ───────────────────────────
        // ترتيب الأعمدة مطابق للنموذج القديم (A→AF) لضمان توافق الملفات القديمة موضعياً.
        $defs['equipments'] = new EntityDefinition('equipments', 'المعدات', 'equipments', [
            new Column('code', 'كود المعدة', ['required' => true, 'unique' => true, 'width' => 15, 'example' => 'EQP-0001']),
            new Column('suppliers', 'المورد (اسم أو كود)', [
                'required'   => true,
                'width'      => 22,
                'example'    => 'مؤسسة المعدات الحديثة',
                'hint'       => 'أدخل اسم المورد كما هو مسجّل أو كوده (مثل SUP-0001).',
                // بحث/Lookup: يحوّل اسم/كود المورد إلى معرفه ويخزّنه في عمود suppliers.
                'lookup'     => [
                    'table'      => 'suppliers',
                    'idColumn'   => 'id',
                    'storeIdIn'  => 'suppliers',
                    'matchBy'    => ['supplier_code', 'name'],
                    'nameColumn' => 'name',
                    'scoped'     => true,
                    'softDelete' => 'is_deleted',
                ],
                'exportExpr' => "(SELECT s.name FROM suppliers s WHERE s.id = equipments.suppliers)",
            ]),
            new Column('type', 'نوع المعدة (اسم أو كود)', [
                'required'   => true,
                'width'      => 18,
                'example'    => 'حفار',
                'hint'       => 'أدخل اسم النوع كما هو مسجّل في أنواع المعدات (مثل: حفار) أو رمز الشكل.',
                // بحث/Lookup: يحوّل اسم النوع إلى معرفه في equipments_types ويخزّنه في عمود type.
                'lookup'     => [
                    'table'      => 'equipments_types',
                    'idColumn'   => 'id',
                    'storeIdIn'  => 'type',
                    'matchBy'    => ['type', 'form'],
                    'nameColumn' => 'type',
                    'scoped'     => false,
                ],
                'exportExpr' => "(SELECT et.type FROM equipments_types et WHERE et.id = equipments.type)",
            ]),
            new Column('name', 'اسم المعدة', ['required' => true, 'width' => 22, 'example' => 'حفار كاتربيلر 320']),
            new Column('serial_number', 'رقم المعدة/التسلسلي', ['width' => 20, 'example' => 'EXC-2024-001']),
            new Column('chassis_number', 'رقم الهيكل', ['width' => 20, 'example' => 'CAT320-ABC123456']),
            new Column('manufacturer', 'الماركة/الشركة المصنعة', ['width' => 20, 'example' => 'كاتربيلر']),
            new Column('model', 'الموديل/الطراز', ['width' => 15, 'example' => '320D']),
            new Column('manufacturing_year', 'سنة الصنع', ['type' => Column::TYPE_INT, 'width' => 12, 'example' => '2018']),
            new Column('import_year', 'سنة الاستيراد', ['type' => Column::TYPE_INT, 'width' => 12, 'example' => '2020']),
            new Column('equipment_condition', 'حالة المعدة', ['width' => 20, 'default' => 'في حالة جيدة', 'example' => 'في حالة جيدة']),
            new Column('operating_hours', 'ساعات التشغيل', ['type' => Column::TYPE_INT, 'width' => 15, 'example' => '5400']),
            new Column('engine_condition', 'حالة المحرك', ['width' => 15, 'default' => 'جيدة', 'example' => 'جيدة']),
            new Column('tires_condition', 'حالة الإطارات', ['width' => 15, 'default' => 'N/A', 'example' => 'N/A']),
            new Column('actual_owner_name', 'اسم المالك الفعلي', ['width' => 20, 'example' => 'محمد علي أحمد']),
            new Column('owner_type', 'نوع المالك', ['width' => 18, 'example' => 'مالك فردي']),
            new Column('owner_phone', 'رقم هاتف المالك', ['width' => 18, 'example' => '+249912345678']),
            new Column('owner_supplier_relation', 'علاقة المالك بالمورد', ['width' => 25, 'example' => 'تابع للمورد (مملوكة للمورد نفسه)']),
            new Column('license_number', 'رقم الترخيص', ['width' => 18, 'example' => 'VEH-2024-12345']),
            new Column('license_authority', 'جهة الترخيص', ['width' => 18, 'example' => 'المرور']),
            new Column('license_expiry_date', 'تاريخ انتهاء الترخيص', ['type' => Column::TYPE_DATE, 'width' => 18, 'example' => '2025-12-31']),
            new Column('inspection_certificate_number', 'رقم شهادة الفحص', ['width' => 18, 'example' => 'INS-2024-001']),
            new Column('last_inspection_date', 'تاريخ آخر فحص', ['type' => Column::TYPE_DATE, 'width' => 15, 'example' => '2024-06-15']),
            new Column('current_location', 'الموقع الحالي', ['width' => 20, 'example' => 'منجم الذهب الشرقي']),
            new Column('availability_status', 'حالة التوفر', ['width' => 18, 'default' => 'متاحة للعمل', 'example' => 'متاحة للعمل']),
            new Column('estimated_value', 'القيمة المقدرة (دولار)', ['type' => Column::TYPE_FLOAT, 'width' => 18, 'example' => '150000']),
            new Column('daily_rental_price', 'سعر التأجير اليومي (دولار)', ['type' => Column::TYPE_FLOAT, 'width' => 20, 'example' => '500']),
            new Column('monthly_rental_price', 'سعر التأجير الشهري (دولار)', ['type' => Column::TYPE_FLOAT, 'width' => 20, 'example' => '10000']),
            new Column('insurance_status', 'التأمين/الضمان', ['width' => 18, 'example' => 'مؤمن بالكامل']),
            new Column('last_maintenance_date', 'تاريخ آخر صيانة', ['type' => Column::TYPE_DATE, 'width' => 15, 'example' => '2024-05-10']),
            new Column('general_notes', 'ملاحظات عامة', ['width' => 30, 'example' => 'معدة موثوقة، تحتاج صيانة دورية']),
            new Column('status', 'الحالة', ['type' => Column::TYPE_INT, 'default' => 1, 'width' => 14, 'example' => '1', 'hint' => '1=نشط، 0=غير نشط']),
        ], [
            'moduleCode'       => 'equipments',
            'softDeleteColumn' => null,
            'createdByColumn'  => null,
            'instructions'     => [
                'الحقول المطلوبة: كود المعدة + المورد + نوع المعدة + اسم المعدة.',
                'المورد: أدخل اسم المورد كما هو مسجّل أو كوده — سيُربط تلقائياً، وإن لم يوجد يُرفض الصف.',
                'نوع المعدة: أدخل اسم النوع كما هو مسجّل في «أنواع المعدات» (مثل: حفار).',
                'كود المعدة يجب أن يكون فريداً. الحالة: 1=نشط، 0=غير نشط.',
                'احذف صفوف الأمثلة قبل رفع الملف.',
            ],
        ]);

        // ─────────────────────────── المشاريع (Projects) ───────────────────────────
        $defs['projects'] = new EntityDefinition('projects', 'المشاريع', 'project', [
            new Column('project_code', 'كود المشروع', ['unique' => true, 'width' => 18, 'example' => 'PRJ-0001']),
            new Column('name', 'اسم المشروع', ['required' => true, 'width' => 32, 'example' => 'مشروع طريق الإنقاذ الغربي']),
            new Column('client', 'العميل (اسم أو كود)', [
                'required'   => true,
                'width'      => 28,
                'example'    => 'شركة بايناتس',
                'hint'       => 'أدخل اسم العميل كما هو مسجّل أو كود العميل (مثل C001).',
                // بحث/Lookup: يحوّل الاسم/الكود إلى client_id ويربط المشروع بالعميل.
                'lookup'     => [
                    'table'      => 'clients',
                    'idColumn'   => 'id',
                    'storeIdIn'  => 'client_id',
                    'matchBy'    => ['client_code', 'client_name'],
                    'nameColumn' => 'client_name',
                    'scoped'     => true,
                    'softDelete' => 'is_deleted',
                ],
                // التصدير يعرض الاسم المرتبط فعلياً (عبر client_id) مع رجوع للنص المخزّن.
                'exportExpr' => "COALESCE((SELECT c.client_name FROM clients c WHERE c.id = project.client_id), project.client)",
            ]),
            new Column('location', 'الموقع', ['required' => true, 'width' => 25, 'example' => 'ولاية الخرطوم']),
            new Column('category', 'التصنيف', ['example' => 'بنية تحتية']),
            new Column('state', 'الولاية', ['example' => 'الخرطوم']),
            new Column('region', 'المنطقة', ['example' => 'أمدرمان']),
            new Column('total', 'القيمة الإجمالية', ['required' => true, 'example' => '1000000']),
        ], [
            'moduleCode'   => 'projects',
            'instructions' => [
                'الحقول المطلوبة: اسم المشروع + العميل + الموقع + القيمة الإجمالية.',
                'العميل: أدخل اسم العميل كما هو مسجّل في النظام أو كوده (مثل C001). سيُربط المشروع تلقائياً بالعميل.',
                'إن لم يكن العميل موجوداً مسبقاً في شاشة العملاء، سيُرفض الصف — أضِف العميل أولاً.',
            ],
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

        // ─────────────────────────── سجل النوع والموديل (Fleet Model Master) ───────────────────────────
        $defs['fleet_models'] = new EntityDefinition('fleet_models', 'سجل النوع والموديل', 'fleet_model', [
            new Column('code', 'كود الموديل', ['required' => true, 'unique' => true, 'width' => 16, 'example' => 'CAT-320']),
            new Column('manufacturer', 'الصانع', ['width' => 20, 'example' => 'Caterpillar']),
            new Column('model_name', 'اسم/رقم الموديل', ['required' => true, 'width' => 22, 'example' => '320 GC']),
            // نوع المعدة — يُحوّل اسم النوع إلى معرفه في equipments_types ويخزّنه في equipment_type_id.
            new Column('equipment_type_id', 'نوع المعدة (اسم أو كود)', [
                'width'      => 18,
                'example'    => 'حفار',
                'hint'       => 'أدخل اسم النوع كما هو مسجّل في أنواع المعدات (مثل: حفار).',
                'lookup'     => [
                    'table'      => 'equipments_types',
                    'idColumn'   => 'id',
                    'storeIdIn'  => 'equipment_type_id',
                    'matchBy'    => ['type', 'form'],
                    'nameColumn' => 'type',
                    'scoped'     => false,
                ],
                'exportExpr' => "(SELECT et.type FROM equipments_types et WHERE et.id = fleet_model.equipment_type_id)",
            ]),
            new Column('operating_category', 'فئة التشغيل', ['width' => 16, 'example' => 'حفر']),
            new Column('fuel_type', 'نوع الوقود', ['width' => 14, 'example' => 'ديزل']),
            new Column('std_capacity', 'السعة القياسية', ['type' => Column::TYPE_FLOAT, 'width' => 14, 'example' => '1.2']),
            new Column('std_capacity_uom', 'وحدة القياس', ['width' => 14, 'example' => 'م³']),
            new Column('tech_reference', 'مرجع فني', ['width' => 20]),
            // المورد الافتراضي — إدخال نصّي حرّ (غير مربوط بجدول الموردين).
            new Column('default_supplier_name', 'المورد الافتراضي', [
                'width'   => 24,
                'example' => 'مؤسسة المعدات الحديثة',
                'hint'    => 'اختياري: اسم المورد الافتراضي (إدخال يدوي).',
            ]),
            new Column('status', 'الحالة', ['type' => Column::TYPE_ENUM, 'enum' => ['active', 'inactive'], 'default' => 'active', 'width' => 14]),
            new Column('created_at', 'تاريخ الإضافة', ['type' => Column::TYPE_DATE, 'importable' => false, 'exportExpr' => "DATE_FORMAT(created_at, '%Y-%m-%d')"]),
        ], [
            'moduleCode'       => 'Equipments/fleet_models.php',
            'companyScoped'    => true,
            'softDeleteColumn' => 'is_deleted',
            'createdByColumn'  => 'created_by',
            'instructions'     => [
                'الحقول المطلوبة: كود الموديل + اسم/رقم الموديل.',
                'نوع المعدة: أدخل اسم النوع كما هو مسجّل في أنواع المعدات.',
                'المورد الافتراضي اختياري — إن أدخلته (اسماً أو كوداً) يُربط تلقائياً.',
            ],
        ]);

        // ─────────────────────────── ملف الافتراضات المالية والإهلاك (Depreciation Profile) ───────────────────────────
        $defs['fleet_depreciation_profiles'] = new EntityDefinition('fleet_depreciation_profiles', 'ملف الإهلاك المالي', 'fleet_depreciation_profile', [
            new Column('code', 'الكود', ['width' => 14, 'importable' => false, 'example' => 'DEP-001']),
            new Column('asset_category', 'فئة الأصل', ['required' => true, 'width' => 24, 'example' => 'حفّار 22ط جديد']),
            new Column('brand', 'الماركة', ['width' => 18]),
            new Column('method', 'الطريقة', ['type' => Column::TYPE_ENUM, 'enum' => ['uop', 'sl'], 'default' => 'uop', 'width' => 12, 'hint' => 'uop=بالساعة · sl=بالسنوات']),
            new Column('useful_life', 'العمر الإنتاجي', ['type' => Column::TYPE_FLOAT, 'required' => true, 'width' => 16, 'example' => '15000']),
            new Column('salvage_pct', 'نسبة التخريد', ['type' => Column::TYPE_FLOAT, 'required' => true, 'width' => 14, 'example' => '0.08', 'hint' => 'بين 0 و 1']),
            new Column('state', 'الحالة', ['type' => Column::TYPE_ENUM, 'enum' => ['draft', 'approved'], 'default' => 'draft', 'width' => 12, 'importable' => false]),
            new Column('notes', 'ملاحظات', ['width' => 28]),
            new Column('created_at', 'تاريخ الإضافة', ['type' => Column::TYPE_DATE, 'importable' => false, 'exportExpr' => "DATE_FORMAT(created_at, '%Y-%m-%d')"]),
        ], [
            'moduleCode'       => 'Equipments/fleet_depreciation_profiles.php',
            'companyScoped'    => true,
            'softDeleteColumn' => 'is_deleted',
            'createdByColumn'  => 'created_by',
            'instructions'     => [
                'الحقول المطلوبة: فئة الأصل + العمر الإنتاجي + نسبة التخريد.',
                'الطريقة: uop (بالساعة التشغيلية) أو sl (زمني بالسنوات).',
                'نسبة التخريد بين 0 و 1 (مثل 0.08). الكود والحالة تُدار من الشاشة.',
            ],
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
            new Column('equipment', 'المعدة (كود أو اسم)', [
                'required'   => true,
                'width'      => 24,
                'example'    => 'EQP-0001',
                'hint'       => 'أدخل كود المعدة أو اسمها كما هو مسجّل في شاشة المعدات.',
                // بحث/Lookup: يحوّل كود/اسم المعدة إلى معرفها ويخزّنه في نفس العمود (equipment يخزّن المعرف).
                'lookup'     => [
                    'table'     => 'equipments',
                    'idColumn'  => 'id',
                    'storeIdIn' => 'equipment',
                    'matchBy'   => ['code', 'name'],
                    'nameColumn' => 'name',
                    'scoped'    => true,
                ],
                'exportExpr' => "(SELECT e.code FROM equipments e WHERE e.id = operations.equipment)",
            ]),
            new Column('equipment_type', 'نوع المعدة', ['width' => 18, 'example' => 'حفار']),
            new Column('equipment_category', 'فئة المعدة', ['required' => true, 'width' => 16, 'example' => 'أساسي']),
            new Column('project_id', 'المشروع (اسم أو كود)', [
                'required'   => true,
                'width'      => 24,
                'example'    => 'مشروع طريق الإنقاذ الغربي',
                'hint'       => 'أدخل اسم المشروع كما هو مسجّل أو كوده (مثل PRJ-0001).',
                'lookup'     => [
                    'table'     => 'project',
                    'idColumn'  => 'id',
                    'storeIdIn' => 'project_id',
                    'matchBy'   => ['project_code', 'name'],
                    'nameColumn' => 'name',
                    'scoped'    => true,
                    'softDelete' => 'is_deleted',
                ],
                'exportExpr' => "(SELECT p.name FROM project p WHERE p.id = operations.project_id)",
            ]),
            new Column('contract_id', 'رقم العقد', ['required' => true, 'width' => 14, 'example' => '5']),
            new Column('supplier_id', 'المورد (اسم أو كود)', [
                'required'   => true,
                'width'      => 24,
                'example'    => 'مؤسسة المعدات الحديثة',
                'hint'       => 'أدخل اسم المورد كما هو مسجّل أو كوده (مثل SUP-0001).',
                'lookup'     => [
                    'table'     => 'suppliers',
                    'idColumn'  => 'id',
                    'storeIdIn' => 'supplier_id',
                    'matchBy'   => ['supplier_code', 'name'],
                    'nameColumn' => 'name',
                    'scoped'    => true,
                    'softDelete' => 'is_deleted',
                ],
                'exportExpr' => "(SELECT s.name FROM suppliers s WHERE s.id = operations.supplier_id)",
            ]),
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
            'instructions'     => [
                'المعدة/المشروع/المورد: أدخل الكود أو الاسم المقروء — سيُربط تلقائياً بالسجل الصحيح، وإن لم يوجد يُرفض الصف.',
                'رقم العقد: أدخل رقم العقد كما يظهر في شاشة العقود (لا يوجد له كود/اسم مقروء بعد).',
                'الحقول المطلوبة: المعدة + فئة المعدة + المشروع/العقد/المورد + بداية ونهاية التشغيل + البيان + الأيام.',
            ],
        ]);

        // ─────────────────────────── ساعات العمل (Timesheet) ───────────────────────────
        $defs['timesheet'] = new EntityDefinition('timesheet', 'ساعات العمل', 'timesheet', [
            new Column('operator', 'المشغّل', ['required' => true, 'width' => 22, 'example' => 'محمد علي']),
            new Column('employee_id', 'السائق (اسم أو كود)', [
                'required'   => true,
                'width'      => 22,
                'example'    => 'محمد أحمد علي',
                'hint'       => 'أدخل اسم السائق كما هو مسجّل أو كوده (مثل DRV-0001).',
                // بحث/Lookup: يحوّل اسم/كود السائق إلى معرفه ويخزّنه في نفس العمود (driver يخزّن معرف السائق).
                'lookup'     => [
                    'table'     => 'employees',
                    'idColumn'  => 'id',
                    'storeIdIn' => 'employee_id',
                    'matchBy'   => ['employee_code', 'name'],
                    'nameColumn' => 'name',
                    'scoped'    => true,
                ],
                'exportExpr' => "(SELECT d.name FROM employees d WHERE d.id = timesheet.employee_id)",
            ]),
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
            'instructions'     => [
                'السائق: أدخل اسم السائق كما هو مسجّل أو كوده — سيُربط تلقائياً بمعرف السائق، وإن لم يوجد يُرفض الصف.',
                'المشغّل: أدخل رقم حركة التشغيل المرتبطة (لا يوجد له اسم/كود مقروء بعد).',
                'الحقول المطلوبة: المشغّل + السائق + الوردية + التاريخ + نوع الكشف.',
                'الورديات: D (نهار) / N (ليل).',
            ],
        ]);

        // ─────────────────────────── عقود المشاريع (Contracts) ───────────────────────────
        $defs['contracts'] = new EntityDefinition('contracts', 'عقود المشاريع', 'contracts', [
            new Column('project_id', 'المشروع (اسم أو كود)', [
                'required'   => true,
                'width'      => 24,
                'example'    => 'مشروع طريق الإنقاذ الغربي',
                'hint'       => 'أدخل اسم المشروع كما هو مسجّل أو كوده (مثل PRJ-0001).',
                'lookup'     => [
                    'table'     => 'project',
                    'idColumn'  => 'id',
                    'storeIdIn' => 'project_id',
                    'matchBy'   => ['project_code', 'name'],
                    'nameColumn' => 'name',
                    'scoped'    => true,
                    'softDelete' => 'is_deleted',
                ],
                'exportExpr' => "(SELECT p.name FROM project p WHERE p.id = contracts.project_id)",
            ]),
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
            'instructions' => [
                'الحقول المطلوبة: المشروع + تاريخ توقيع العقد.',
                'المشروع: أدخل اسم المشروع كما هو مسجّل أو كوده — سيُربط العقد تلقائياً بالمشروع، وإن لم يوجد يُرفض الصف.',
            ],
        ]);

        // ─────────────────────────── عقود الموردين (Supplier Contracts) ───────────────────────────
        $defs['supplier_contracts'] = new EntityDefinition('supplier_contracts', 'عقود الموردين', 'supplierscontracts', [
            new Column('supplier_id', 'المورد (اسم أو كود)', [
                'required'   => true,
                'width'      => 24,
                'example'    => 'مؤسسة المعدات الحديثة',
                'hint'       => 'أدخل اسم المورد كما هو مسجّل أو كوده (مثل SUP-0001).',
                'lookup'     => [
                    'table'     => 'suppliers',
                    'idColumn'  => 'id',
                    'storeIdIn' => 'supplier_id',
                    'matchBy'   => ['supplier_code', 'name'],
                    'nameColumn' => 'name',
                    'scoped'    => true,
                    'softDelete' => 'is_deleted',
                ],
                'exportExpr' => "(SELECT s.name FROM suppliers s WHERE s.id = supplierscontracts.supplier_id)",
            ]),
            new Column('project_id', 'المشروع (اسم أو كود)', [
                'required'   => true,
                'width'      => 24,
                'example'    => 'مشروع طريق الإنقاذ الغربي',
                'hint'       => 'أدخل اسم المشروع كما هو مسجّل أو كوده (مثل PRJ-0001).',
                'lookup'     => [
                    'table'     => 'project',
                    'idColumn'  => 'id',
                    'storeIdIn' => 'project_id',
                    'matchBy'   => ['project_code', 'name'],
                    'nameColumn' => 'name',
                    'scoped'    => true,
                    'softDelete' => 'is_deleted',
                ],
                'exportExpr' => "(SELECT p.name FROM project p WHERE p.id = supplierscontracts.project_id)",
            ]),
            new Column('contract_signing_date', 'تاريخ توقيع العقد', ['type' => Column::TYPE_DATE, 'required' => true, 'width' => 18, 'example' => '2026-01-01']),
            new Column('equip_type', 'نوع المعدة', ['width' => 18]),
            new Column('first_party', 'الطرف الأول', ['width' => 26]),
            new Column('second_party', 'الطرف الثاني', ['width' => 26]),
            new Column('created_at', 'تاريخ الإنشاء', ['type' => Column::TYPE_DATE, 'importable' => false, 'exportExpr' => "DATE_FORMAT(created_at, '%Y-%m-%d')"]),
        ], [
            'moduleCode'       => 'Suppliers/supplierscontracts.php',
            'softDeleteColumn' => null,
            'createdByColumn'  => null,
            'instructions'     => [
                'الحقول المطلوبة: المورد + المشروع + تاريخ توقيع العقد.',
                'المورد والمشروع: أدخل الاسم أو الكود المقروء — سيُربط العقد تلقائياً، وإن لم يوجد أيٌّ منهما يُرفض الصف.',
            ],
        ]);

        // ─────────────────────────── عقود السائقين (Driver Contracts) ───────────────────────────
        $defs['driver_contracts'] = new EntityDefinition('driver_contracts', 'عقود السائقين', 'drivercontracts', [
            new Column('employee_id', 'السائق (اسم أو كود)', [
                'required'   => true,
                'width'      => 24,
                'example'    => 'محمد أحمد علي',
                'hint'       => 'أدخل اسم السائق كما هو مسجّل أو كوده (مثل DRV-0001).',
                'lookup'     => [
                    'table'     => 'employees',
                    'idColumn'  => 'id',
                    'storeIdIn' => 'employee_id',
                    'matchBy'   => ['employee_code', 'name'],
                    'nameColumn' => 'name',
                    'scoped'    => true,
                ],
                'exportExpr' => "(SELECT d.name FROM employees d WHERE d.id = drivercontracts.employee_id)",
            ]),
            new Column('project_id', 'المشروع (اسم أو كود)', [
                'required'   => true,
                'width'      => 24,
                'example'    => 'مشروع طريق الإنقاذ الغربي',
                'hint'       => 'أدخل اسم المشروع كما هو مسجّل أو كوده (مثل PRJ-0001).',
                'lookup'     => [
                    'table'     => 'project',
                    'idColumn'  => 'id',
                    'storeIdIn' => 'project_id',
                    'matchBy'   => ['project_code', 'name'],
                    'nameColumn' => 'name',
                    'scoped'    => true,
                    'softDelete' => 'is_deleted',
                ],
                'exportExpr' => "(SELECT p.name FROM project p WHERE p.id = drivercontracts.project_id)",
            ]),
            new Column('contract_signing_date', 'تاريخ توقيع العقد', ['type' => Column::TYPE_DATE, 'required' => true, 'width' => 18, 'example' => '2026-01-01']),
            new Column('first_party', 'الطرف الأول', ['width' => 26]),
            new Column('second_party', 'الطرف الثاني', ['width' => 26]),
            new Column('created_at', 'تاريخ الإنشاء', ['type' => Column::TYPE_DATE, 'importable' => false, 'exportExpr' => "DATE_FORMAT(created_at, '%Y-%m-%d')"]),
        ], [
            'moduleCode'       => 'Employees/employee_contracts.php',
            'softDeleteColumn' => null,
            'createdByColumn'  => null,
            'instructions'     => [
                'الحقول المطلوبة: السائق + المشروع + تاريخ توقيع العقد.',
                'السائق والمشروع: أدخل الاسم أو الكود المقروء — سيُربط العقد تلقائياً، وإن لم يوجد أيٌّ منهما يُرفض الصف.',
            ],
        ]);

        // ─────────────────────────── البلاغات (Tickets) ───────────────────────────
        // الكيان الرئيسي لوحدة البلاغات. المراجع تُطابَق بالاسم أو الكود عبر
        // lookup (نفس نمط المشاريع مع العميل)، والحقول المحسوبة (مواعيد
        // الاستحقاق وأول إجراء) للتصدير فقط. ورقم التذكرة فريدٌ لكل شركة
        // ويُقبل من الملف — ما يتيح استيراد سجلّاتٍ سابقةٍ بأرقامها الأصلية —
        // وإن تُرك فارغًا يولّده الخادم.
        $defs['tickets'] = new EntityDefinition('tickets', 'البلاغات', 'tickets', [
            new Column('ticket_no', 'رقم التذكرة', [
                'unique' => true, 'width' => 16, 'example' => '26-07-1001',
                'hint'   => 'اتركه فارغًا ليولّده النظام تلقائيًّا، أو أدخل الرقم التاريخي كما هو.',
            ]),
            new Column('ticket_type', 'نوع البلاغ', [
                'required' => true, 'width' => 26, 'example' => 'بلاغ عطل / طلب صيانة',
                'hint'     => 'اسم النوع كما هو في شاشة «أنواع البلاغات والتوجيه» — يُسنِد الإدارة المالكة تلقائيًّا.',
                'lookup'   => [
                    'table' => 'ticket_types', 'idColumn' => 'id', 'storeIdIn' => 'ticket_type_id',
                    'matchBy' => ['code', 'name'], 'nameColumn' => 'name',
                    'scoped' => false, 'softDelete' => null,
                ],
                'exportExpr' => "(SELECT tt.name FROM ticket_types tt WHERE tt.id = tickets.ticket_type_id)",
            ]),
            new Column('category', 'التصنيف الفنّي', [
                'width' => 20, 'example' => 'المحرك',
                'lookup' => [
                    'table' => 'ticket_categories', 'idColumn' => 'id', 'storeIdIn' => 'category_id',
                    'matchBy' => ['code', 'name'], 'nameColumn' => 'name',
                    'scoped' => false, 'softDelete' => null,
                ],
                'exportExpr' => "(SELECT tc.name FROM ticket_categories tc WHERE tc.id = tickets.category_id)",
            ]),
            new Column('ticket_nature', 'الطبيعة', [
                'type' => Column::TYPE_ENUM, 'enum' => ['request', 'incident', 'recurring'],
                'default' => 'request', 'width' => 14, 'example' => 'incident',
            ]),
            new Column('stage', 'المرحلة', [
                'type' => Column::TYPE_ENUM,
                'enum' => ['new', 'classified', 'routed', 'in_progress', 'waiting', 'follow_up', 'done', 'closed', 'cancelled'],
                'default' => 'routed', 'width' => 14, 'example' => 'routed',
            ]),
            new Column('priority', 'الأولوية', [
                'type' => Column::TYPE_ENUM, 'enum' => ['normal', 'high', 'critical'],
                'default' => 'normal', 'width' => 12, 'example' => 'high',
            ]),
            new Column('business_impact', 'الوزن التشغيلي', [
                'type' => Column::TYPE_ENUM, 'enum' => ['production_critical', 'revenue', 'safety', 'admin'],
                'default' => 'admin', 'width' => 20, 'example' => 'production_critical',
            ]),
            new Column('owner_role', 'الإدارة المالكة', [
                'width' => 22, 'example' => 'ادارة الصيانة',
                'hint'   => 'اتركه فارغًا ليأخذ الإدارة المالكة من نوع البلاغ.',
                'lookup' => [
                    'table' => 'roles', 'idColumn' => 'id', 'storeIdIn' => 'owner_role_id',
                    'matchBy' => ['name'], 'nameColumn' => 'name',
                    'scoped' => false, 'softDelete' => null,
                ],
                'exportExpr' => "(SELECT r.name FROM roles r WHERE r.id = tickets.owner_role_id)",
            ]),
            new Column('reporting_person', 'المُبلِّغ', ['required' => true, 'width' => 24, 'example' => 'علي حمدان']),
            new Column('reporter_contact', 'رقم المُبلِّغ', ['type' => Column::TYPE_PHONE, 'width' => 18, 'example' => '0920001001']),
            new Column('call_date', 'تاريخ البلاغ', ['type' => Column::TYPE_DATE, 'required' => true, 'width' => 16, 'example' => '2026-07-16']),
            new Column('call_time', 'وقت البلاغ', ['width' => 12, 'example' => '07:45']),
            new Column('equipment', 'كود المعدة', [
                'width' => 18, 'example' => 'EQ-001',
                'lookup' => [
                    'table' => 'equipments', 'idColumn' => 'id', 'storeIdIn' => 'equipment_id',
                    'matchBy' => ['code', 'name'], 'nameColumn' => 'code',
                    'scoped' => true, 'softDelete' => null,
                ],
                'exportExpr' => "(SELECT e.code FROM equipments e WHERE e.id = tickets.equipment_id)",
            ]),
            new Column('project', 'المشروع', [
                'width' => 26, 'example' => 'مشروع المحجر الشرقي',
                'lookup' => [
                    'table' => 'project', 'idColumn' => 'id', 'storeIdIn' => 'project_id',
                    'matchBy' => ['project_code', 'name'], 'nameColumn' => 'name',
                    'scoped' => true, 'softDelete' => 'is_deleted',
                ],
                'exportExpr' => "(SELECT p.name FROM project p WHERE p.id = tickets.project_id)",
            ]),
            new Column('complaint', 'وصف المشكلة', ['required' => true, 'width' => 45, 'example' => 'توقف كامل لمحرك الحفار']),
            new Column('service_team', 'فريق المعالجة', [
                'type' => Column::TYPE_ENUM, 'enum' => ['internal', 'external_workshop'], 'width' => 18,
            ]),
            new Column('issue_status', 'حالة المعالجة', ['width' => 34]),
            new Column('close_date', 'تاريخ الإغلاق', ['type' => Column::TYPE_DATE, 'width' => 16]),
            new Column('close_time', 'وقت الإغلاق', ['width' => 12]),
            // ── محسوبة: تصدير فقط ──
            new Column('response_due_at', 'موعد الاستجابة', ['importable' => false, 'width' => 20, 'exportExpr' => "DATE_FORMAT(response_due_at, '%Y-%m-%d %H:%i')"]),
            new Column('resolution_due_at', 'موعد الإنجاز', ['importable' => false, 'width' => 20, 'exportExpr' => "DATE_FORMAT(resolution_due_at, '%Y-%m-%d %H:%i')"]),
            new Column('first_action_at', 'أول إجراء', ['importable' => false, 'width' => 20, 'exportExpr' => "DATE_FORMAT(first_action_at, '%Y-%m-%d %H:%i')"]),
            new Column('created_at', 'تاريخ الإنشاء', ['type' => Column::TYPE_DATE, 'importable' => false, 'exportExpr' => "DATE_FORMAT(created_at, '%Y-%m-%d %H:%i')"]),
        ], [
            'moduleCode'       => 'Tickets/tickets_list.php',
            'softDeleteColumn' => null,   // لا حذف ناعم في الوحدة — الإلغاء حالةٌ تُسجَّل
            'exportOrderBy'    => 'id DESC',
            // إكمالُ الحقول المشتقّة: التوجيه يتبع النوع، والرقم يُسنِده الخادم.
            'rowPrepare'       => static function (array $data, array $ctx): array {
                $conn = $ctx['conn'];

                // ① الإدارة المالكة: إن تُركت فارغةً تُؤخذ من نوع البلاغ.
                if (empty($data['owner_role_id']) && !empty($data['ticket_type_id'])) {
                    if ($st = mysqli_prepare($conn, 'SELECT owner_role_id FROM ticket_types WHERE id = ? LIMIT 1')) {
                        $tid = (int) $data['ticket_type_id'];
                        mysqli_stmt_bind_param($st, 'i', $tid);
                        mysqli_stmt_execute($st);
                        $rs = mysqli_stmt_get_result($st);
                        if ($rs && ($r = mysqli_fetch_assoc($rs))) {
                            $data['owner_role_id'] = (int) $r['owner_role_id'];
                        }
                        mysqli_stmt_close($st);
                    }
                }

                // ② رقم التذكرة: فارغٌ ⇒ يولّده الخادم بنفس آليّة الشاشة
                //    (ServerId فوق ems_sequences — لا COUNT+1 سِباقي).
                if (empty($data['ticket_no']) && class_exists('\\App\\Core\\ServerId')) {
                    $cid = (int) $ctx['companyId'];
                    $yy = date('y');
                    $data['ticket_no'] = \App\Core\ServerId::nextNo(
                        $conn, 'tickets:c' . $cid . ':y' . $yy, $yy . '-' . date('m'), 4
                    );
                }

                return $data;
            },
            // نطاق الرؤية — نفسُ قاعدة الشاشة: مدير البلاغات والمدير الأعلى
            // يصدّران الكل؛ وغيرهما ما وُجِّه لشجرة دوره أو ما أبلغ عنه بنفسه.
            'exportRowScope'   => static function (array $ctx): array {
                if ($ctx['isSuperAdmin'] || (string) $ctx['role'] === '24') {
                    return ['sql' => '', 'params' => [], 'types' => ''];
                }
                $roleId = (int) $ctx['role'];
                $conn   = $ctx['conn'];

                // شجرة الأدوار الحيّة: {دوري} ∪ أجدادي ∪ ذرّيّتي
                $parent = [];
                if ($res = @mysqli_query($conn, 'SELECT id, parent_role_id FROM roles')) {
                    while ($r = mysqli_fetch_assoc($res)) {
                        $parent[(int) $r['id']] = ($r['parent_role_id'] === null) ? null : (int) $r['parent_role_id'];
                    }
                    mysqli_free_result($res);
                }
                $visible = [$roleId => true];
                $cur = $roleId; $guard = 0;
                while (isset($parent[$cur]) && $parent[$cur] !== null && $guard < 10) {
                    $cur = $parent[$cur]; $visible[$cur] = true; $guard++;
                }
                $frontier = [$roleId]; $guard = 0;
                while ($frontier && $guard < 10) {
                    $next = [];
                    foreach ($parent as $id => $p) {
                        if ($p !== null && in_array($p, $frontier, true) && !isset($visible[$id])) {
                            $visible[$id] = true; $next[] = $id;
                        }
                    }
                    $frontier = $next; $guard++;
                }

                $ids = array_keys($visible);
                $ph = implode(',', array_fill(0, count($ids), '?'));
                $params = $ids;
                $types  = str_repeat('i', count($ids));
                $params[] = (int) $ctx['userId'];
                $params[] = (int) $ctx['userId'];
                $types .= 'ii';

                return [
                    'sql'    => "owner_role_id IN ($ph) OR reporter_user_id = ? OR created_by = ?",
                    'params' => $params,
                    'types'  => $types,
                ];
            },
            'instructions'     => [
                'احذف صفوف الأمثلة قبل رفع الملف.',
                'الحقول المطلوبة: نوع البلاغ + المُبلِّغ + تاريخ البلاغ + وصف المشكلة.',
                'نوع البلاغ: أدخل اسمه كما في شاشة «أنواع البلاغات والتوجيه» — وهو يُسنِد الإدارة المالكة تلقائيًّا إن تركت خانة الإدارة فارغة.',
                'رقم التذكرة: اتركه فارغًا ليولّده النظام، أو أدخل رقمًا تاريخيًّا (فريدًا) لاستيراد كشوف سابقة.',
                'المعدة والمشروع: أدخل الكود أو الاسم المسجَّل — الصف يُقبل حتى لو تُركا فارغين.',
            ],
        ]);

        // ──────────────── سجل انتقال الملكية (Ticket Transfers — تصدير فقط) ────────────────
        // سجلٌّ إلحاقيٌّ لا يُستورَد (نمط سجل النشاطات): مادةُ تحليل الاختناقات في إكسل.
        $defs['ticket_transfers'] = new EntityDefinition('ticket_transfers', 'سجل تحويلات البلاغات', 'ticket_transfers', [
            new Column('ticket_no', 'رقم التذكرة', ['importable' => false, 'width' => 16,
                'exportExpr' => "(SELECT t.ticket_no FROM tickets t WHERE t.id = ticket_transfers.ticket_id)"]),
            new Column('transfer_datetime', 'وقت التحويل', ['importable' => false, 'width' => 20,
                'exportExpr' => "DATE_FORMAT(transfer_datetime, '%Y-%m-%d %H:%i')"]),
            new Column('from_role', 'من إدارة', ['importable' => false, 'width' => 22,
                'exportExpr' => "(SELECT r.name FROM roles r WHERE r.id = ticket_transfers.from_role_id)"]),
            new Column('to_role', 'إلى إدارة', ['importable' => false, 'width' => 22,
                'exportExpr' => "(SELECT r.name FROM roles r WHERE r.id = ticket_transfers.to_role_id)"]),
            new Column('transferred_by_name', 'نفّذ التحويل', ['importable' => false, 'width' => 22,
                'exportExpr' => "(SELECT u.name FROM users u WHERE u.id = ticket_transfers.transferred_by)"]),
            new Column('reason', 'سبب التحويل', ['importable' => false, 'width' => 45]),
            new Column('notes', 'ملاحظات', ['importable' => false, 'width' => 30]),
            new Column('held_days', 'مدة الاحتجاز قبل التحويل (يوم)', ['importable' => false, 'width' => 26,
                'exportExpr' => "ROUND(TIMESTAMPDIFF(MINUTE, (SELECT t.created_at FROM tickets t WHERE t.id = ticket_transfers.ticket_id), transfer_datetime)/1440, 1)"]),
        ], [
            'moduleCode'       => 'Tickets/ticket_dashboard.php',
            'softDeleteColumn' => null,
            'createdByColumn'  => null,
            'exportOrderBy'    => 'id DESC',
            'instructions'     => ['سجلٌّ للتصدير فقط — لا يُحرَّر ولا يُستورَد.'],
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
