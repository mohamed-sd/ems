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
            /* ── حقولُ ورقةِ الدليلِ المدموجةُ (الدفعة 2 · 2026-09-07) ──
               تُصدَّر ولا تُستورَد: مصدرُها الشاشةُ لا ملفُّ الرفع. */
            new Column('client_no', 'رقم العميل', ['importable' => false]),
            new Column('legal_name', 'الاسم القانوني', ['importable' => false]),
            new Column('short_name', 'الاسم المختصر', ['importable' => false]),
            new Column('classification_basis', 'أساس التصنيف', ['importable' => false]),
            new Column('client_state', 'حالة العميل', ['importable' => false]),
            new Column('sector', 'القطاع', ['importable' => false]),
            new Column('country', 'الدولة', ['importable' => false]),
            new Column('city_or_region', 'المدينة/المنطقة', ['importable' => false]),
            new Column('registration_no', 'رقم التسجيل', ['importable' => false]),
            new Column('account_owner', 'مالك الحساب', ['importable' => false]),
            new Column('recognition_source', 'مصدر التعرف', ['importable' => false]),
            new Column('priority_degree', 'درجة الأولوية', ['importable' => false, 'type' => Column::TYPE_FLOAT]),
            new Column('credit_rating', 'التصنيف الائتماني', ['importable' => false]),
            new Column('credit_limit_usd', 'حد الائتمان ($)', ['importable' => false]),
            new Column('credit_limit_sdg', 'حد الائتمان (ج.س)', ['importable' => false]),
            new Column('default_payment_terms', 'شروط الدفع الافتراضية', ['importable' => false]),
            new Column('first_deal_date', 'تاريخ أول تعامل', ['importable' => false, 'type' => Column::TYPE_DATE]),
            new Column('contracts_count', 'عدد العقود', ['importable' => false, 'type' => Column::TYPE_FLOAT]),
            new Column('active_contracts', 'العقود الجارية', ['importable' => false]),
            new Column('last_exec_activity', 'آخر نشاط تنفيذي', ['importable' => false]),
            new Column('dealing_models', 'نماذج التعامل', ['importable' => false]),
            new Column('notes', 'ملاحظات', ['importable' => false]),
            new Column('service_types', 'أنواع الخدمات', ['importable' => false]),
            new Column('dealt_currencies', 'العملات المتعامل بها', ['importable' => false]),
            new Column('source_billing_frequency', 'دورية الفوترة بالمصدر', ['importable' => false]),
            new Column('client_data_evidence_level', 'مستوى حجية بيانات العميل', ['importable' => false, 'type' => Column::TYPE_FLOAT]),
        ], [
            'moduleCode'   => 'clients',
            'instructions' => [
                'احذف صفوف الأمثلة قبل رفع الملف.',
                'كود العميل يجب أن يكون فريدا — أي كود مكرر سيستبعد.',
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
                'hint'       => 'اختياري: المورد الذي يعمل معه المشغل — أدخل اسمه أو كوده (مثل SUP-0001).',
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
            /* ── حقولُ ورقةِ الدليلِ المدموجةُ (جولة 2026-09-07) ──
               تُصدَّر ولا تُستورَد: مصدرُها الشاشةُ لا ملفُّ الرفع. */
            new Column('employee_no', 'رقم الموظف', ['importable' => false]),
            new Column('national_id', 'الرقم الوطني', ['importable' => false]),
            new Column('type', 'النوع', ['importable' => false]),
            new Column('marital_status', 'الحالة الاجتماعية', ['importable' => false]),
            new Column('commencement_date', 'تاريخ المباشرة', ['importable' => false, 'type' => Column::TYPE_DATE]),
            new Column('is_operational_operator', 'مشغل تشغيلي؟', ['importable' => false, 'type' => Column::TYPE_INT]),
            new Column('workforce_operator_code', 'كود المشغل بالقوى', ['importable' => false]),
            new Column('contact_details', 'بيانات التواصل', ['importable' => false]),
            new Column('emergency_contact', 'شخص الطوارئ', ['importable' => false]),
            new Column('creator_name', 'المنشئ', ['importable' => false]),
            new Column('reviewer', 'المراجع', ['importable' => false]),
            new Column('approver', 'المعتمد', ['importable' => false]),
            new Column('data_state', 'حالة البيانات', ['importable' => false]),
            new Column('source_ref', 'مرجع المصدر', ['importable' => false]),
        ], [
            /* ⛔ **كودٌ قصيرٌ يُحَلُّ إلى بابٍ لا يحرسه أحد**: كان `'drivers'`،
                 و`check_page_permissions` تحلُّه بمطابقةٍ فضفاضةٍ إلى **أقصرِ**
                 كودٍ يحوي «drivers» — فوقع على `movement/add_drivers.php`، وهي
                 **شاشةٌ ميتة**: صفرُ قالبٍ نافذٍ يمنحها · صفرُ موضعٍ في ورقةِ
                 الدليل · صفرُ رابطٍ نشِط. فحارسُ التصديرِ كان يسأل عن صلاحيةِ
                 شاشةٍ لا يملكها أحدٌ بسند.
               ◆ **والمالكُ الحقيقيُّ شاشةُ الجدولِ نفسِه**: الكيانُ يقرأ
                 `employees`، و`Employees/employees.php` حيّةٌ ومحكومةٌ (في ورقةِ
                 الدليلِ وبرابطَين نشِطَين) ومملوكةٌ للموارد البشريّةِ صاحبةِ
                 الحقِّ بنصِّ `SEN-002`. فالمسارُ الكاملُ يُعلَن ولا يُترك للمطابقة. */
            'moduleCode'      => 'Employees/employees.php',
            'softDeleteColumn' => null,
            'createdByColumn'  => null,
            'instructions'    => [
                'الحقول المطلوبة: اسم السائق + رقم الهاتف.',
                'المورد والمشروع اختياريان — إن أدخلتهما (اسما أو كودا) يربط المشغل تلقائيا، وإن لم يوجد أي منهما يرفض الصف.',
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
                'hint'       => 'أدخل اسم المورد كما هو مسجل أو كوده (مثل SUP-0001).',
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
                'hint'       => 'أدخل اسم النوع كما هو مسجل في أنواع المعدات (مثل: حفار) أو رمز الشكل.',
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
            // N-21: أعمدة المالك نُزعت من التصدير — «لا بُعد مالك في أي تقرير تشغيلي ولا في التصدير»
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
                'المورد: أدخل اسم المورد كما هو مسجل أو كوده — سيربط تلقائيا، وإن لم يوجد يرفض الصف.',
                'نوع المعدة: أدخل اسم النوع كما هو مسجل في «أنواع المعدات» (مثل: حفار).',
                'كود المعدة يجب أن يكون فريدا. الحالة: 1=نشط، 0=غير نشط.',
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
                'hint'       => 'أدخل اسم العميل كما هو مسجل أو كود العميل (مثل C001).',
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
            /* ── حقولُ ورقةِ الدليلِ المدموجةُ (جولة 2026-09-07) ──
               تُصدَّر ولا تُستورَد: مصدرُها الشاشةُ لا ملفُّ الرفع. */
            new Column('project_no', 'رقم المشروع', ['importable' => false]),
            new Column('client_no', 'رقم العميل', ['importable' => false]),
            new Column('client_name_search', 'اسم العميل (بحث)', ['importable' => false]),
            new Column('description', 'الوصف', ['importable' => false]),
            new Column('execution_scope_location', 'الموقع (نطاق تنفيذ)', ['importable' => false]),
            new Column('sector', 'القطاع', ['importable' => false]),
            new Column('project_state', 'حالة المشروع', ['importable' => false]),
            new Column('start_date', 'تاريخ البداية', ['importable' => false, 'type' => Column::TYPE_DATE]),
            new Column('commercial_officer', 'المسؤول التجاري', ['importable' => false]),
            new Column('estimated_value_usd', 'القيمة التقديرية ($)', ['importable' => false]),
            new Column('estimated_value_sdg', 'القيمة التقديرية (ج.س)', ['importable' => false]),
            new Column('contracts_count', 'عدد العقود', ['importable' => false, 'type' => Column::TYPE_FLOAT]),
            new Column('notes', 'ملاحظات', ['importable' => false]),
            new Column('client_side_project_code', 'كود المشروع لدى العميل', ['importable' => false]),
            new Column('region_state', 'الإقليم/الولاية', ['importable' => false]),
            new Column('client_project_sequence', 'تسلسل مشروع العميل', ['importable' => false, 'type' => Column::TYPE_FLOAT]),
            new Column('service_type', 'نوع الخدمة', ['importable' => false]),
            new Column('work_model', 'نموذج العمل', ['importable' => false]),
            new Column('naming_and_bounds_basis', 'أساس التسمية والحدود', ['importable' => false]),
            new Column('grouping_rule', 'قاعدة التجميع', ['importable' => false]),
            new Column('evidence_level', 'مستوى الحجية', ['importable' => false, 'type' => Column::TYPE_FLOAT]),
        ], [
            'moduleCode'   => 'projects',
            'instructions' => [
                'الحقول المطلوبة: اسم المشروع + العميل + الموقع + القيمة الإجمالية.',
                'العميل: أدخل اسم العميل كما هو مسجل في النظام أو كوده (مثل C001). سيربط المشروع تلقائيا بالعميل.',
                'إن لم يكن العميل موجودا مسبقا في شاشة العملاء، سيرفض الصف — أضف العميل أولا.',
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
                'hint'       => 'أدخل اسم النوع كما هو مسجل في أنواع المعدات (مثل: حفار).',
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
                'نوع المعدة: أدخل اسم النوع كما هو مسجل في أنواع المعدات.',
                'المورد الافتراضي اختياري — إن أدخلته (اسما أو كودا) يربط تلقائيا.',
            ],
        ]);

        // ─────────────────────────── ملف الافتراضات المالية والإهلاك (Depreciation Profile) ───────────────────────────
        $defs['fleet_depreciation_profiles'] = new EntityDefinition('fleet_depreciation_profiles', 'ملف الإهلاك المالي', 'fleet_depreciation_profile', [
            new Column('code', 'الكود', ['width' => 14, 'importable' => false, 'example' => 'DEP-001']),
            new Column('asset_category', 'فئة الأصل', ['required' => true, 'width' => 24, 'example' => 'حفار 22ط جديد']),
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
                'نسبة التخريد بين 0 و 1 (مثل 0.08). الكود والحالة تدار من الشاشة.',
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
            'instructions'     => ['نوع المعدة كود رقمي (1=حفار، 2=قلاب، 3=خرامة).', 'الكود الكامل يجب أن يكون فريدا.'],
        ]);

        // ─────────────────────────── التشغيل/الحركات (Operations) ───────────────────────────
        $defs['operations'] = new EntityDefinition('operations', 'حركات التشغيل', 'operations', [
            new Column('equipment', 'المعدة (كود أو اسم)', [
                'required'   => true,
                'width'      => 24,
                'example'    => 'EQP-0001',
                'hint'       => 'أدخل كود المعدة أو اسمها كما هو مسجل في شاشة المعدات.',
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
                'hint'       => 'أدخل اسم المشروع كما هو مسجل أو كوده (مثل PRJ-0001).',
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
                'hint'       => 'أدخل اسم المورد كما هو مسجل أو كوده (مثل SUP-0001).',
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
                'المعدة/المشروع/المورد: أدخل الكود أو الاسم المقروء — سيربط تلقائيا بالسجل الصحيح، وإن لم يوجد يرفض الصف.',
                'رقم العقد: أدخل رقم العقد كما يظهر في شاشة العقود (لا يوجد له كود/اسم مقروء بعد).',
                'الحقول المطلوبة: المعدة + فئة المعدة + المشروع/العقد/المورد + بداية ونهاية التشغيل + البيان + الأيام.',
            ],
        ]);

        // ─────────────────────────── ساعات العمل (Timesheet) ───────────────────────────
        $defs['timesheet'] = new EntityDefinition('timesheet', 'ساعات العمل', 'timesheet', [
            new Column('operator', 'المشغل', ['required' => true, 'width' => 22, 'example' => 'محمد علي']),
            new Column('employee_id', 'السائق (اسم أو كود)', [
                'required'   => true,
                'width'      => 22,
                'example'    => 'محمد أحمد علي',
                'hint'       => 'أدخل اسم السائق كما هو مسجل أو كوده (مثل DRV-0001).',
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
            /* ── حقولُ ورقةِ الدليلِ المدموجةُ (جولة 2026-09-07) ──
               تُصدَّر ولا تُستورَد: مصدرُها الشاشةُ لا ملفُّ الرفع. */
            new Column('record_uid', 'معرف السجل', ['importable' => false]),
            new Column('operating_day', 'يوم التشغيل', ['importable' => false, 'type' => Column::TYPE_DATE]),
            new Column('equipment_code', 'كود المعدة', ['importable' => false]),
            new Column('equipment_type', 'نوع المعدة', ['importable' => false]),
            new Column('submitting_supplier', 'المورد المقدم', ['importable' => false]),
            new Column('project_contract_unit', 'وحدة عقد المشروع', ['importable' => false]),
            new Column('annual_container_key', 'مفتاح الحاوية السنوية', ['importable' => false]),
            new Column('monthly_container_no', 'رقم الحاوية الشهرية', ['importable' => false]),
            new Column('slot_code_and_occupancy', 'كود الخانة ونوع الإشغال', ['importable' => false]),
            new Column('outside_contract_window_flag', 'وسم خارج النافذة التعاقدية', ['importable' => false]),
            new Column('current_location', 'الموقع الحالي', ['importable' => false]),
            new Column('work_zone', 'منطقة العمل', ['importable' => false]),
            new Column('operator_code', 'كود المشغل', ['importable' => false]),
            new Column('work_model', 'نموذج العمل', ['importable' => false]),
            new Column('default_change_reason', 'سبب تعديل الافتراضي', ['importable' => false]),
            new Column('available_hours', 'الساعات المتاحة', ['importable' => false, 'type' => Column::TYPE_FLOAT]),
            new Column('executed_quantity', 'الكمية المنفذة', ['importable' => false, 'type' => Column::TYPE_FLOAT]),
            new Column('measure_unit', 'وحدة القياس', ['importable' => false]),
            new Column('meter_start_of_shift', 'قراءة العداد أول الوردية', ['importable' => false]),
            new Column('meter_end_of_shift', 'قراءة العداد آخر الوردية', ['importable' => false]),
            new Column('meter_hours', 'ساعات العداد', ['importable' => false, 'type' => Column::TYPE_FLOAT]),
            new Column('actual_total', 'إجمالي الفعلي', ['importable' => false, 'type' => Column::TYPE_FLOAT]),
            new Column('standby_total', 'إجمالي الاستعداد', ['importable' => false, 'type' => Column::TYPE_FLOAT]),
            new Column('downtime_total', 'إجمالي التوقف', ['importable' => false, 'type' => Column::TYPE_FLOAT]),
            new Column('capacity_exceeded', 'تجاوز الطاقة؟', ['importable' => false, 'type' => Column::TYPE_INT]),
            new Column('override_reason', 'سبب التجاوز', ['importable' => false]),
            new Column('field_reference', 'المرجع الميداني', ['importable' => false]),
            new Column('sync_state', 'حالة المزامنة', ['importable' => false]),
            new Column('record_state', 'حالة السجل', ['importable' => false]),
            new Column('creator_name', 'المنشئ', ['importable' => false]),
            new Column('reviewer', 'المراجع', ['importable' => false]),
            new Column('approver', 'المعتمد', ['importable' => false]),
            new Column('data_state', 'حالة البيانات', ['importable' => false]),
            new Column('source_ref', 'مرجع المصدر', ['importable' => false]),
        ], [
            'moduleCode'       => 'Timesheet/timesheet.php',
            'softDeleteColumn' => null,
            'createdByColumn'  => 'user_id',
            'instructions'     => [
                'السائق: أدخل اسم السائق كما هو مسجل أو كوده — سيربط تلقائيا بمعرف السائق، وإن لم يوجد يرفض الصف.',
                'المشغل: أدخل رقم حركة التشغيل المرتبطة (لا يوجد له اسم/كود مقروء بعد).',
                'الحقول المطلوبة: المشغل + السائق + الوردية + التاريخ + نوع الكشف.',
                'الورديات: D (نهار) / N (ليل).',
            ],
        ]);

        // ─────────────────────────── عقود المشاريع (Contracts) ───────────────────────────
        $defs['contracts'] = new EntityDefinition('contracts', 'عقود المشاريع', 'contracts', [
            new Column('project_id', 'المشروع (اسم أو كود)', [
                'required'   => true,
                'width'      => 24,
                'example'    => 'مشروع طريق الإنقاذ الغربي',
                'hint'       => 'أدخل اسم المشروع كما هو مسجل أو كوده (مثل PRJ-0001).',
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
            /* ── حقولُ ورقةِ الدليلِ المدموجةُ (جولة 2026-09-07) ──
               تُصدَّر ولا تُستورَد: مصدرُها الشاشةُ لا ملفُّ الرفع. */
            new Column('contract_code', 'كود العقد', ['importable' => false]),
            new Column('client_no', 'رقم العميل', ['importable' => false]),
            new Column('client_name_search', 'اسم العميل (بحث)', ['importable' => false]),
            new Column('project_no', 'رقم المشروع', ['importable' => false]),
            new Column('system_contract_no', 'رقم العقد بالمنظومة', ['importable' => false]),
            new Column('company_sequence', 'تسلسل الشركة', ['importable' => false, 'type' => Column::TYPE_FLOAT]),
            new Column('document_signature', 'توقيع الوثيقة', ['importable' => false]),
            new Column('contractual_start', 'البداية التعاقدية', ['importable' => false, 'type' => Column::TYPE_DATE]),
            new Column('contractual_end', 'النهاية التعاقدية', ['importable' => false, 'type' => Column::TYPE_DATE]),
            new Column('execution_start', 'البداية التنفيذية', ['importable' => false, 'type' => Column::TYPE_DATE]),
            new Column('execution_end', 'النهاية التنفيذية', ['importable' => false, 'type' => Column::TYPE_DATE]),
            new Column('commitment_cycles_count', 'عدد دورات الالتزام (التجديدات)', ['importable' => false, 'type' => Column::TYPE_FLOAT]),
            new Column('current_contracted_units', 'الوحدات المتعاقدة الحالية', ['importable' => false]),
            new Column('current_monthly_capacity', 'السعة الشهرية الحالية', ['importable' => false, 'type' => Column::TYPE_FLOAT]),
            new Column('evidence_source', 'الحجية/مصدر التوثيق', ['importable' => false]),
            new Column('currency', 'العملة', ['importable' => false]),
            new Column('pricing_basis_as_stated', 'أساس التسعير (كما ورد)', ['importable' => false]),
            new Column('unit_price_as_stated', 'سعر الوحدة (كما ورد)', ['importable' => false]),
            new Column('tax', 'الضريبة', ['importable' => false, 'type' => Column::TYPE_FLOAT]),
            new Column('payment_and_billing', 'الدفع والفوترة', ['importable' => false]),
            new Column('deposit_or_advance', 'الوديعة/الدفعة المقدمة', ['importable' => false]),
            new Column('maintenance_and_spares', 'الصيانة وقطع الغيار', ['importable' => false]),
            new Column('transport_and_packing', 'النقل والتعبئة', ['importable' => false]),
            new Column('commercial_officer', 'المسؤول التجاري', ['importable' => false]),
            new Column('notes', 'ملاحظات', ['importable' => false]),
            new Column('provided_service_type', 'نوع الخدمة المقدمة', ['importable' => false]),
            new Column('contracting_unit_basis', 'أساس التعاقد (الوحدة)', ['importable' => false]),
            new Column('contract_signing_place', 'مكان إبرام العقد (مؤكد)', ['importable' => false]),
            new Column('historically_likely_place', 'المكان المرجح تاريخيا', ['importable' => false]),
            new Column('likely_place_basis', 'أساس المكان المرجح', ['importable' => false]),
            new Column('likely_place_evidence_level', 'مستوى حجية المكان المرجح', ['importable' => false, 'type' => Column::TYPE_FLOAT]),
            new Column('source_state_as_stated', 'الحالة كما وردت بالمصدر', ['importable' => false]),
            new Column('state_change_basis', 'أساس تعديل الحالة', ['importable' => false]),
            new Column('billing_unit', 'وحدة الفوترة', ['importable' => false]),
            new Column('min_quantity', 'الحد الأدنى (كمية)', ['importable' => false, 'type' => Column::TYPE_FLOAT]),
            new Column('min_level_frequency', 'دورية الحد الأدنى', ['importable' => false]),
            new Column('guaranteed_quantity', 'الكمية المضمونة', ['importable' => false, 'type' => Column::TYPE_FLOAT]),
            new Column('billing_threshold', 'عتبة الفوترة', ['importable' => false, 'type' => Column::TYPE_FLOAT]),
            new Column('shortage_bearer', 'متحمل العجز', ['importable' => false]),
            new Column('shortage_rule', 'قاعدة العجز', ['importable' => false]),
            new Column('price_structure', 'بنية السعر', ['importable' => false]),
            new Column('price_versions_count', 'عدد النسخ/المكونات السعرية', ['importable' => false, 'type' => Column::TYPE_FLOAT]),
            new Column('pricing_ref', 'مرجع تسعيري', ['importable' => false]),
        ], [
            'moduleCode'   => 'Contracts/contracts.php',
            'instructions' => [
                'الحقول المطلوبة: المشروع + تاريخ توقيع العقد.',
                'المشروع: أدخل اسم المشروع كما هو مسجل أو كوده — سيربط العقد تلقائيا بالمشروع، وإن لم يوجد يرفض الصف.',
            ],
        ]);

        // ─────────────────────────── عقود الموردين (Supplier Contracts) ───────────────────────────
        $defs['supplier_contracts'] = new EntityDefinition('supplier_contracts', 'عقود الموردين', 'supplierscontracts', [
            new Column('supplier_id', 'المورد (اسم أو كود)', [
                'required'   => true,
                'width'      => 24,
                'example'    => 'مؤسسة المعدات الحديثة',
                'hint'       => 'أدخل اسم المورد كما هو مسجل أو كوده (مثل SUP-0001).',
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
                'hint'       => 'أدخل اسم المشروع كما هو مسجل أو كوده (مثل PRJ-0001).',
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
                'المورد والمشروع: أدخل الاسم أو الكود المقروء — سيربط العقد تلقائيا، وإن لم يوجد أي منهما يرفض الصف.',
            ],
        ]);

        // ─────────────────────────── عقود السائقين (Driver Contracts) ───────────────────────────
        $defs['driver_contracts'] = new EntityDefinition('driver_contracts', 'عقود السائقين', 'drivercontracts', [
            new Column('employee_id', 'السائق (اسم أو كود)', [
                'required'   => true,
                'width'      => 24,
                'example'    => 'محمد أحمد علي',
                'hint'       => 'أدخل اسم السائق كما هو مسجل أو كوده (مثل DRV-0001).',
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
                'hint'       => 'أدخل اسم المشروع كما هو مسجل أو كوده (مثل PRJ-0001).',
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
                'السائق والمشروع: أدخل الاسم أو الكود المقروء — سيربط العقد تلقائيا، وإن لم يوجد أي منهما يرفض الصف.',
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
                'hint'   => 'اتركه فارغا ليولده النظام تلقائيا، أو أدخل الرقم التاريخي كما هو.',
            ]),
            new Column('ticket_type', 'نوع البلاغ', [
                'required' => true, 'width' => 26, 'example' => 'بلاغ عطل / طلب صيانة',
                'hint'     => 'اسم النوع كما هو في شاشة «أنواع البلاغات والتوجيه» — يسند الإدارة المالكة تلقائيا.',
                'lookup'   => [
                    'table' => 'ticket_types', 'idColumn' => 'id', 'storeIdIn' => 'ticket_type_id',
                    'matchBy' => ['code', 'name'], 'nameColumn' => 'name',
                    'scoped' => false, 'softDelete' => null,
                ],
                'exportExpr' => "(SELECT tt.name FROM ticket_types tt WHERE tt.id = tickets.ticket_type_id)",
            ]),
            new Column('category', 'التصنيف الفني', [
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
                'hint'   => 'اتركه فارغا ليأخذ الإدارة المالكة من نوع البلاغ.',
                'lookup' => [
                    'table' => 'roles', 'idColumn' => 'id', 'storeIdIn' => 'owner_role_id',
                    'matchBy' => ['name'], 'nameColumn' => 'name',
                    'scoped' => false, 'softDelete' => null,
                ],
                'exportExpr' => "(SELECT r.name FROM roles r WHERE r.id = tickets.owner_role_id)",
            ]),
            new Column('reporting_person', 'المبلغ', ['required' => true, 'width' => 24, 'example' => 'علي حمدان']),
            new Column('reporter_contact', 'رقم المبلغ', ['type' => Column::TYPE_PHONE, 'width' => 18, 'example' => '0920001001']),
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
            /* ── حقولُ ورقةِ الدليلِ المدموجةُ (الدفعة 2 · 2026-09-07) ──
               تُصدَّر ولا تُستورَد: مصدرُها الشاشةُ لا ملفُّ الرفع. */
            new Column('report_no', 'رقم البلاغ', ['importable' => false]),
            new Column('registration_time', 'وقت التسجيل', ['importable' => false, 'type' => Column::TYPE_DATE]),
            new Column('registration_channel', 'قناة التسجيل', ['importable' => false]),
            new Column('reporter_uid', 'Reporter_ID', ['importable' => false]),
            new Column('reporter_name', 'Reporter_Name', ['importable' => false]),
            new Column('reporter_department', 'Reporter_Department', ['importable' => false]),
            new Column('reporter_entity', 'Reporter_Entity', ['importable' => false]),
            new Column('subject_type', 'Subject_Type', ['importable' => false]),
            new Column('subject_uid', 'Subject_ID', ['importable' => false]),
            new Column('subject_name', 'Subject_Name', ['importable' => false]),
            new Column('subject_owning_department', 'Subject_Owning_Department', ['importable' => false]),
            new Column('nature', 'الطبيعة', ['importable' => false]),
            new Column('priority_level', 'الأولوية', ['importable' => false, 'type' => Column::TYPE_FLOAT]),
            new Column('confidentiality_level', 'مستوى السرية', ['importable' => false, 'type' => Column::TYPE_FLOAT]),
            new Column('report_description', 'وصف البلاغ', ['importable' => false]),
            new Column('attachments', 'المرفقات', ['importable' => false]),
            new Column('ticket_owner', 'Ticket_Owner', ['importable' => false]),
            new Column('assigned_department', 'Assigned_Department', ['importable' => false]),
            new Column('resolution_owner', 'Resolution_Owner', ['importable' => false]),
            new Column('processing_deadline', 'مهلة المعالجة', ['importable' => false]),
            new Column('report_state', 'حالة البلاغ', ['importable' => false]),
            new Column('creator_name', 'المنشئ', ['importable' => false]),
            new Column('data_state', 'حالة البيانات', ['importable' => false]),
            new Column('source_ref', 'مرجع المصدر', ['importable' => false]),
            new Column('line_uid', 'معرف السطر', ['importable' => false]),
            new Column('offer_scope', 'نطاق العرض', ['importable' => false]),
            new Column('registration_date', 'تاريخ التسجيل', ['importable' => false, 'type' => Column::TYPE_DATE]),
            new Column('report_subject', 'محل البلاغ', ['importable' => false]),
            new Column('entity_created_in_our_dept', 'الكيان المنشأ في إدارتنا', ['importable' => false]),
            new Column('sla_deadline', 'مهلة SLA', ['importable' => false]),
            new Column('remaining_or_delay', 'المتبقي/التأخير', ['importable' => false]),
            new Column('escalation_level', 'مستوى التصعيد', ['importable' => false]),
            new Column('awaiting_verification', 'ينتظر تحققا؟', ['importable' => false, 'type' => Column::TYPE_INT]),
            new Column('report_state', 'حالة البلاغ', ['importable' => false]),
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

                // ② رقم التذكرة: فارغٌ ⇒ يولّده الخادم من سلطة الترقيم الواحدة
                //    (TicketNumber فوق ems_sequences — ذرّيٌّ وذاتي الشفاء).
                if (empty($data['ticket_no'])) {
                    require_once dirname(__DIR__) . '/Tickets/TicketNumber.php';
                    $data['ticket_no'] = \App\Services\Tickets\TicketNumber::allocateUnique(
                        $conn, (int) $ctx['companyId']
                    );
                }

                return $data;
            },
            // نطاق الرؤية — نفسُ قاعدة الشاشة: مدير البلاغات والمدير الأعلى
            // يصدّران الكل؛ وغيرهما ما وُجِّه لدورِه أو لمرؤوسيه أو ما أبلغ عنه.
            'exportRowScope'   => static function (array $ctx): array {
                if ($ctx['isSuperAdmin'] || (string) $ctx['role'] === '24') {
                    return ['sql' => '', 'params' => [], 'types' => ''];
                }
                $roleId = (int) $ctx['role'];

                /* ◆ النطاقُ **يُقرأ من مالكِه ولا يُعاد بناؤه هنا**: كانت هذه
                     الدالةُ تمشي شجرةَ الأدوارِ بنسخةٍ ثانيةٍ من المنطق، فحين
                     أُغلق الصعودُ في الشاشة (2026-08-17) كان التصديرُ سيبقى
                     صاعدًا — أي بابٌ خلفيٌّ يُخرج ما تحجبه الشاشة. تعريفٌ واحدٌ
                     في `tkt_visible_owner_role_ids` يقرأه الاثنان. */
                require_once dirname(__DIR__, 3) . '/Tickets/tkt_helpers.php';
                $ids = tkt_visible_owner_role_ids($roleId);
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
                'الحقول المطلوبة: نوع البلاغ + المبلغ + تاريخ البلاغ + وصف المشكلة.',
                'نوع البلاغ: أدخل اسمه كما في شاشة «أنواع البلاغات والتوجيه» — وهو يسند الإدارة المالكة تلقائيا إن تركت خانة الإدارة فارغة.',
                'رقم التذكرة: اتركه فارغا ليولده النظام، أو أدخل رقما تاريخيا (فريدا) لاستيراد كشوف سابقة.',
                'المعدة والمشروع: أدخل الكود أو الاسم المسجل — الصف يقبل حتى لو تركا فارغين.',
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
            new Column('transferred_by_name', 'نفذ التحويل', ['importable' => false, 'width' => 22,
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

        // ═══════════════════════════════════════════════════════════════════
        // ح-09 · كياناتُ المبيعات السبعة — كانت الوحدةُ كلُّها بلا مخرجٍ إلى
        // Excel: عشرُ شاشاتٍ بصفر زرِّ تصدير، فلا يُخرج مديرُ المبيعات مسارَه
        // ولا عروضَه إلى الإدارة. الأعمدةُ وقوائمُ ENUM مطابقةٌ للمخطط الحيّ.
        // ═══════════════════════════════════════════════════════════════════

        // ─────────────────────────── الفرص البيعية ───────────────────────────
        $defs['opportunities'] = new EntityDefinition('opportunities', 'الفرص البيعية', 'opportunities', [
            new Column('opp_code', 'كود الفرصة', ['required' => true, 'unique' => true, 'width' => 18, 'example' => 'OPP-0001']),
            new Column('title', 'عنوان الفرصة', ['required' => true, 'width' => 35, 'example' => 'توريد معدات حفر']),
            new Column('client_id', 'العميل (اسم أو كود)', ['width' => 26, 'example' => 'CLT-0001', 'lookup' => [
                'table' => 'clients', 'idColumn' => 'id', 'storeIdIn' => 'client_id',
                'matchBy' => ['client_code', 'client_name'], 'nameColumn' => 'client_name',
                'scoped' => true, 'softDelete' => 'is_deleted']]),
            new Column('stage', 'المرحلة', ['type' => Column::TYPE_ENUM, 'width' => 16,
                'enum' => ['جديدة', 'قيد الدراسة', 'مؤهلة', 'عرض مقدم', 'تفاوض', 'فوز', 'خسارة', 'مستبعدة'], 'default' => 'جديدة']),
            new Column('source', 'المصدر', ['width' => 16, 'example' => 'مناقصة']),
            new Column('sector_category', 'تصنيف القطاع', ['width' => 18, 'example' => 'تعدين']),
            new Column('state_region', 'الولاية/المنطقة', ['width' => 18, 'example' => 'نهر النيل']),
            new Column('revenue_model', 'نموذج الإيراد', ['type' => Column::TYPE_ENUM, 'enum' => ['hourly', 'ton', 'meter', 'mixed'], 'example' => 'hourly']),
            new Column('expected_revenue', 'الإيراد المتوقع', ['type' => Column::TYPE_FLOAT, 'example' => '250000']),
            new Column('currency', 'العملة', ['type' => Column::TYPE_ENUM, 'enum' => ['USD', 'SDG'], 'default' => 'USD', 'width' => 10]),
            new Column('probability', 'احتمال الفوز ٪', ['type' => Column::TYPE_FLOAT, 'example' => '40']),
            new Column('expected_close_date', 'تاريخ الإغلاق المتوقع', ['type' => Column::TYPE_DATE, 'example' => '2026-10-01']),
            new Column('attractiveness', 'الجاذبية', ['type' => Column::TYPE_ENUM, 'enum' => ['منخفضة', 'متوسطة', 'عالية']]),
            new Column('strategy_fit', 'التوافق الاستراتيجي', ['type' => Column::TYPE_ENUM, 'enum' => ['منخفض', 'متوسط', 'عالي']]),
            new Column('win_reason', 'سبب الفوز', ['width' => 26]),
            new Column('lost_reason', 'سبب الخسارة', ['width' => 26]),
            new Column('notes', 'ملاحظات', ['width' => 30]),
            new Column('created_at', 'تاريخ الإضافة', ['type' => Column::TYPE_DATE, 'importable' => false, 'exportExpr' => "DATE_FORMAT(created_at, '%Y-%m-%d')"]),
        ], [
            'moduleCode'   => 'Opportunities/opportunities.php',
            'instructions' => [
                'احذف صفوف الأمثلة قبل رفع الملف.',
                'كود الفرصة فريد داخل شركتك — أحرف وأرقام و - أو _ فقط.',
                'المرحلة «فوز» تستلزم سبب فوز، و«خسارة»/«مستبعدة» تستلزم سبب خسارة.',
                'الإيراد المتوقع لا يكون سالبا.',
            ],
        ]);

        // ─────────────────────────── عروض الأسعار ───────────────────────────
        $defs['quotations'] = new EntityDefinition('quotations', 'عروض الأسعار', 'quotations', [
            new Column('quotation_code', 'كود العرض', ['required' => true, 'unique' => true, 'width' => 18, 'example' => 'QUO-0001']),
            new Column('client_id', 'العميل (اسم أو كود)', ['width' => 26, 'example' => 'CLT-0001', 'lookup' => [
                'table' => 'clients', 'idColumn' => 'id', 'storeIdIn' => 'client_id',
                'matchBy' => ['client_code', 'client_name'], 'nameColumn' => 'client_name',
                'scoped' => true, 'softDelete' => 'is_deleted']]),
            new Column('opportunity_id', 'الفرصة (كود)', ['width' => 20, 'example' => 'OPP-0001', 'lookup' => [
                'table' => 'opportunities', 'idColumn' => 'id', 'storeIdIn' => 'opportunity_id',
                'matchBy' => ['opp_code'], 'nameColumn' => 'opp_code',
                'scoped' => true, 'softDelete' => 'is_deleted']]),
            new Column('amount_total', 'إجمالي العرض', ['type' => Column::TYPE_FLOAT, 'example' => '120000']),
            new Column('currency', 'العملة', ['type' => Column::TYPE_ENUM, 'enum' => ['USD', 'SDG'], 'default' => 'USD', 'width' => 10]),
            new Column('validity_date', 'تاريخ الصلاحية', ['type' => Column::TYPE_DATE, 'example' => '2026-12-31']),
            new Column('payment_terms', 'شروط الدفع', ['width' => 26]),
            new Column('state', 'الحالة', ['type' => Column::TYPE_ENUM, 'enum' => ['مسودة', 'مقدم', 'مقبول', 'مرفوض'], 'default' => 'مسودة', 'width' => 14]),
            new Column('notes', 'ملاحظات', ['width' => 30]),
            new Column('created_at', 'تاريخ الإضافة', ['type' => Column::TYPE_DATE, 'importable' => false, 'exportExpr' => "DATE_FORMAT(created_at, '%Y-%m-%d')"]),
            /* ── حقولُ ورقةِ الدليلِ المدموجةُ (الدفعة 2 · 2026-09-07) ──
               تُصدَّر ولا تُستورَد: مصدرُها الشاشةُ لا ملفُّ الرفع. */
            new Column('internal_offer_no', 'رقم العرض الداخلي', ['importable' => false]),
            new Column('opportunity_no', 'رقم الفرصة', ['importable' => false]),
            new Column('request_no', 'رقم الطلب', ['importable' => false]),
            new Column('client_no', 'رقم العميل', ['importable' => false]),
            new Column('client_name_search', 'اسم العميل (بحث)', ['importable' => false]),
            new Column('project_no', 'رقم المشروع', ['importable' => false]),
            new Column('official_offer_no', 'رقم العرض الرسمي', ['importable' => false]),
            new Column('date_basis', 'أساس التاريخ', ['importable' => false]),
            new Column('work_model', 'نموذج العمل', ['importable' => false]),
            new Column('validity_period', 'مدة السريان', ['importable' => false]),
            new Column('payment_or_billing_terms', 'شروط الدفع/الفوترة', ['importable' => false]),
            new Column('offer_state', 'حالة العرض', ['importable' => false]),
            new Column('client_response', 'رد العميل', ['importable' => false]),
            new Column('decision_state', 'حالة القرار', ['importable' => false]),
            new Column('decision_date', 'تاريخ القرار', ['importable' => false, 'type' => Column::TYPE_DATE]),
            new Column('offer_value_usd', 'قيمة العرض ($)', ['importable' => false]),
            new Column('offer_value_sdg', 'قيمة العرض (ج.س)', ['importable' => false]),
            new Column('resulting_contract_ref', 'مرجع العقد الناتج', ['importable' => false]),
            new Column('source_commitment_cycle_key', 'مفتاح دورة الالتزام المصدر', ['importable' => false]),
            new Column('evidence_level', 'مستوى الحجية', ['importable' => false, 'type' => Column::TYPE_FLOAT]),
            new Column('residual_value_basis', 'أساس القيمة الرجعية', ['importable' => false]),
            new Column('event_no', 'رقم الواقعة', ['importable' => false]),
            new Column('record_type', 'نوع السجل', ['importable' => false]),
            new Column('offer_no', 'رقم العرض', ['importable' => false]),
            new Column('contract_ref', 'مرجع العقد', ['importable' => false]),
            new Column('new_commitment_cycle', 'دورة الالتزام الجديدة', ['importable' => false]),
            new Column('previous_commitment_cycle', 'دورة الالتزام السابقة', ['importable' => false]),
            new Column('comparison_scope', 'نطاق المقارنة', ['importable' => false]),
            new Column('date_value', 'التاريخ', ['importable' => false, 'type' => Column::TYPE_FLOAT]),
            new Column('change_type', 'نوع التغيير', ['importable' => false]),
            new Column('commercial_impact', 'الأثر التجاري', ['importable' => false]),
            new Column('reference_document', 'الوثيقة المرجعية', ['importable' => false]),
            new Column('reason_or_evidence', 'السبب/الدليل', ['importable' => false]),
            new Column('requesting_party', 'الطرف الطالب', ['importable' => false]),
            new Column('source_commitment_cycle_key', 'مفتاح دورة الالتزام المصدر', ['importable' => false]),
            new Column('evidence_level', 'مستوى الحجية', ['importable' => false]),
            new Column('residual_value_basis', 'أساس القيمة الرجعية', ['importable' => false]),
        ], [
            'moduleCode'   => 'Clients/quotations.php',
            'instructions' => ['كود العرض فريد داخل شركتك.', 'الفرصة والعميل يطابقان بالكود أو الاسم.'],
        ]);

        // ─────────────────────────── المناقصات ───────────────────────────
        $defs['tenders'] = new EntityDefinition('tenders', 'المناقصات', 'tenders', [
            new Column('tender_code', 'كود المناقصة', ['required' => true, 'unique' => true, 'width' => 18, 'example' => 'TND-0001']),
            new Column('name', 'اسم المناقصة', ['required' => true, 'width' => 35, 'example' => 'مناقصة تخريم حكومية']),
            new Column('authority_id', 'الجهة (عميل)', ['width' => 26, 'example' => 'CLT-0001', 'lookup' => [
                'table' => 'clients', 'idColumn' => 'id', 'storeIdIn' => 'authority_id',
                'matchBy' => ['client_code', 'client_name'], 'nameColumn' => 'client_name',
                'scoped' => true, 'softDelete' => 'is_deleted']]),
            new Column('opportunity_id', 'الفرصة (كود)', ['width' => 20, 'example' => 'OPP-0001', 'lookup' => [
                'table' => 'opportunities', 'idColumn' => 'id', 'storeIdIn' => 'opportunity_id',
                'matchBy' => ['opp_code'], 'nameColumn' => 'opp_code',
                'scoped' => true, 'softDelete' => 'is_deleted']]),
            new Column('closing_date', 'تاريخ الإقفال', ['type' => Column::TYPE_DATE, 'example' => '2026-11-30']),
            new Column('participation_state', 'حالة المشاركة', ['type' => Column::TYPE_ENUM, 'enum' => ['إعداد', 'مقدمة', 'مسحوبة'], 'default' => 'إعداد', 'width' => 14]),
            new Column('result', 'النتيجة', ['type' => Column::TYPE_ENUM, 'enum' => ['قيد التقييم', 'فوز', 'خسارة', 'إلغاء'], 'width' => 14]),
            new Column('result_reason', 'سبب النتيجة', ['width' => 26]),
            new Column('notes', 'ملاحظات', ['width' => 30]),
            new Column('created_at', 'تاريخ الإضافة', ['type' => Column::TYPE_DATE, 'importable' => false, 'exportExpr' => "DATE_FORMAT(created_at, '%Y-%m-%d')"]),
        ], [
            'moduleCode'   => 'Clients/tenders.php',
            'instructions' => ['كود المناقصة فريد داخل شركتك.', 'الجهة عميل مسجل في سجل العملاء.'],
        ]);

        // ─────────────────────────── كتالوج الخدمات ───────────────────────────
        $defs['products'] = new EntityDefinition('products', 'كتالوج الخدمات وبنود البيع', 'products', [
            new Column('product_code', 'كود البند', ['required' => true, 'unique' => true, 'width' => 18, 'example' => 'PRD-0001']),
            new Column('name', 'اسم البند', ['required' => true, 'width' => 32, 'example' => 'ساعة حفار 320']),
            new Column('product_type', 'النوع', ['type' => Column::TYPE_ENUM, 'enum' => ['خدمة', 'معدة', 'مادة'], 'default' => 'خدمة', 'width' => 12]),
            new Column('revenue_model', 'نموذج الإيراد', ['type' => Column::TYPE_ENUM, 'enum' => ['hourly', 'ton', 'meter'], 'example' => 'hourly']),
            new Column('default_uom', 'وحدة القياس', ['width' => 14, 'example' => 'ساعة']),
            new Column('standard_price', 'السعر القياسي', ['type' => Column::TYPE_FLOAT, 'example' => '150']),
            new Column('currency', 'العملة', ['type' => Column::TYPE_ENUM, 'enum' => ['USD', 'SDG'], 'default' => 'USD', 'width' => 10]),
            new Column('description', 'الوصف', ['width' => 30]),
            new Column('created_at', 'تاريخ الإضافة', ['type' => Column::TYPE_DATE, 'importable' => false, 'exportExpr' => "DATE_FORMAT(created_at, '%Y-%m-%d')"]),
        ], [
            'moduleCode'   => 'Clients/products.php',
            'instructions' => ['كود البند فريدٌ داخل شركتك.'],
        ]);

        // ─────────────────────────── قوائم التسعير ───────────────────────────
        $defs['pricelists'] = new EntityDefinition('pricelists', 'قوائم التسعير', 'pricelists', [
            new Column('pricelist_code', 'كود القائمة', ['required' => true, 'unique' => true, 'width' => 18, 'example' => 'PL-0001']),
            new Column('name', 'اسم القائمة', ['required' => true, 'width' => 32, 'example' => 'تسعيرة 2026 — تعدين']),
            new Column('revenue_model', 'نموذج الإيراد', ['type' => Column::TYPE_ENUM, 'enum' => ['hourly', 'ton', 'meter'], 'example' => 'hourly']),
            new Column('base_price', 'السعر الأساس', ['type' => Column::TYPE_FLOAT, 'example' => '150']),
            new Column('currency', 'العملة', ['type' => Column::TYPE_ENUM, 'enum' => ['USD', 'SDG'], 'default' => 'USD', 'width' => 10]),
            new Column('distance_factor', 'معامل المسافة', ['type' => Column::TYPE_FLOAT, 'example' => '1.0']),
            new Column('shift_factor', 'معامل الوردية', ['type' => Column::TYPE_FLOAT, 'example' => '1.0']),
            new Column('volume_factor', 'معامل الحجم', ['type' => Column::TYPE_FLOAT, 'example' => '1.0']),
            new Column('duration_factor', 'معامل المدة', ['type' => Column::TYPE_FLOAT, 'example' => '1.0']),
            new Column('notes', 'ملاحظات', ['width' => 30]),
            new Column('created_at', 'تاريخ الإضافة', ['type' => Column::TYPE_DATE, 'importable' => false, 'exportExpr' => "DATE_FORMAT(created_at, '%Y-%m-%d')"]),
        ], [
            'moduleCode'   => 'Clients/pricelists.php',
            'instructions' => ['كود القائمة فريد داخل شركتك.', 'المعاملات نسب — 1.0 تعني بلا تعديل.'],
        ]);

        // ─────────────────────────── المخاطر التجارية ───────────────────────────
        $defs['commercial_risks'] = new EntityDefinition('commercial_risks', 'المخاطر التجارية', 'commercial_risks', [
            new Column('risk_code', 'كود المخاطرة', ['required' => true, 'unique' => true, 'width' => 18, 'example' => 'RSK-0001']),
            new Column('name', 'اسم المخاطرة', ['required' => true, 'width' => 32, 'example' => 'تأخر تحصيل عميل']),
            new Column('risk_type', 'النوع', ['type' => Column::TYPE_ENUM, 'enum' => ['عميل', 'موقع', 'تمويل', 'تحصيل', 'تشغيل', 'موردون'], 'width' => 14]),
            new Column('severity', 'الشدة', ['type' => Column::TYPE_ENUM, 'enum' => ['منخفضة', 'متوسطة', 'عالية'], 'width' => 12]),
            new Column('state', 'الحالة', ['type' => Column::TYPE_ENUM, 'enum' => ['مفتوح', 'تحت المعالجة', 'مغلق'], 'default' => 'مفتوح', 'width' => 16]),
            new Column('mitigation', 'إجراء التخفيف', ['width' => 32]),
            new Column('notes', 'ملاحظات', ['width' => 30]),
            new Column('created_at', 'تاريخ الإضافة', ['type' => Column::TYPE_DATE, 'importable' => false, 'exportExpr' => "DATE_FORMAT(created_at, '%Y-%m-%d')"]),
        ], [
            'moduleCode'   => 'Clients/commercial_risks.php',
            'instructions' => ['كود المخاطرة فريدٌ داخل شركتك.'],
        ]);

        // ─────────────────────────── الأنشطة التجارية ───────────────────────────
        $defs['activities'] = new EntityDefinition('activities', 'الأنشطة التجارية', 'activities', [
            new Column('activity_code', 'كود النشاط', ['required' => true, 'unique' => true, 'width' => 18, 'example' => 'ACT-0001']),
            new Column('activity_type', 'نوع النشاط', ['width' => 18, 'example' => 'زيارة عميل']),
            new Column('subject', 'الموضوع', ['required' => true, 'width' => 32, 'example' => 'زيارة موقع المنجم']),
            new Column('activity_date', 'تاريخ النشاط', ['type' => Column::TYPE_DATE, 'example' => '2026-08-01']),
            new Column('outcome', 'المخرج', ['width' => 32]),
            new Column('notes', 'ملاحظات', ['width' => 30]),
            new Column('created_at', 'تاريخ الإضافة', ['type' => Column::TYPE_DATE, 'importable' => false, 'exportExpr' => "DATE_FORMAT(created_at, '%Y-%m-%d')"]),
        ], [
            'moduleCode'   => 'Clients/activities.php',
            'instructions' => ['كود النشاط فريد داخل شركتك.', 'الربط بالفرصة/العميل يدار من الشاشة لا من الملف.'],
        ]);

        return $defs;
    }
}
