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
            new Column('driver_status', 'الحالة', ['type' => Column::TYPE_ENUM, 'enum' => ['نشط', 'متوقف'], 'default' => 'نشط', 'width' => 14]),
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
            new Column('driver', 'السائق (اسم أو كود)', [
                'required'   => true,
                'width'      => 22,
                'example'    => 'محمد أحمد علي',
                'hint'       => 'أدخل اسم السائق كما هو مسجّل أو كوده (مثل DRV-0001).',
                // بحث/Lookup: يحوّل اسم/كود السائق إلى معرفه ويخزّنه في نفس العمود (driver يخزّن معرف السائق).
                'lookup'     => [
                    'table'     => 'employees',
                    'idColumn'  => 'id',
                    'storeIdIn' => 'driver',
                    'matchBy'   => ['driver_code', 'name'],
                    'nameColumn' => 'name',
                    'scoped'    => true,
                ],
                'exportExpr' => "(SELECT d.name FROM employees d WHERE d.id = timesheet.driver)",
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
            new Column('driver_id', 'السائق (اسم أو كود)', [
                'required'   => true,
                'width'      => 24,
                'example'    => 'محمد أحمد علي',
                'hint'       => 'أدخل اسم السائق كما هو مسجّل أو كوده (مثل DRV-0001).',
                'lookup'     => [
                    'table'     => 'employees',
                    'idColumn'  => 'id',
                    'storeIdIn' => 'driver_id',
                    'matchBy'   => ['driver_code', 'name'],
                    'nameColumn' => 'name',
                    'scoped'    => true,
                ],
                'exportExpr' => "(SELECT d.name FROM employees d WHERE d.id = drivercontracts.driver_id)",
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
            'moduleCode'       => 'Drivers/drivercontracts.php',
            'softDeleteColumn' => null,
            'createdByColumn'  => null,
            'instructions'     => [
                'الحقول المطلوبة: السائق + المشروع + تاريخ توقيع العقد.',
                'السائق والمشروع: أدخل الاسم أو الكود المقروء — سيُربط العقد تلقائياً، وإن لم يوجد أيٌّ منهما يُرفض الصف.',
            ],
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
