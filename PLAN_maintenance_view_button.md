# خطة تنفيذ: زر «العرض» ونافذة التفاصيل الموحّدة في صفحات الصيانة الثلاث

**التاريخ:** 2026-06-21
**النطاق:** `Maintenance/orders.php` · `Maintenance/inspections.php` · `Maintenance/preventive_plans.php`
**الهدف:** إضافة زر «عرض» في عمود الإجراءات يفتح نافذة تعرض بيانات السجل كاملة لتصفّح ما أُدخل سابقاً، مع ربط ذلك بصلاحية العرض القادمة من صفحة صلاحيات المشروع.

---

## 1) الوضع الحالي (نتيجة الفحص)

| الصفحة | زر العرض + النافذة | ملاحظة |
|--------|--------------------|--------|
| `orders.php` | ✅ موجود وكامل | يُستخدم كمرجع للنمط |
| `inspections.php` | ❌ غير موجود | يلزم إضافته |
| `preventive_plans.php` | ❌ غير موجود | يلزم إضافته |

**البنية التحتية جاهزة عالمياً:**
- `EmsDetailsModal` يُحمَّل من `assets/js/ems-details-modal.js` عبر `inheader.php` (سطر 54، `defer`).
- الصفحات الثلاث جميعها تُضمّن `../inheader.php` ⇒ **لا حاجة لأي تضمين JS/CSS إضافي**.

**النمط المعتمد في `orders.php` (المرجع):**
1. لكل صف يُبنى متغيّر `$da` يحوي كل قيم الحقول كسمات `data-*` مع `htmlspecialchars(..., ENT_QUOTES)`.
2. يُطبع زرّ العرض أول عناصر `.action-btns`:
   ```php
   echo "<a href='javascript:void(0)' class='viewBtn action-btn view' $da title='عرض التفاصيل'><i class='fas fa-eye'></i></a>";
   ```
3. في الـ JS داخل `$(document).ready`:
   ```js
   $(document).on('click', '.viewBtn', function () {
       var d = $(this).data();
       EmsDetailsModal.open({ title: '…', icon: '…', fields: [ { label:'…', value:d.x, icon:'…' }, … ] });
   });
   ```
   خيارات الحقل: `type:'status'` لشارة الحالة، `size:'lg' | 'full'` للحقول الطويلة.

---

## 2) آلية الصلاحيات والتكامل مع صفحة صلاحيات المشروع

**كيف تعمل اليوم:**
- الصفحات الثلاث مسجّلة في جدول `modules` بالأكواد الكاملة (`Maintenance/orders.php` … إلخ) عبر مايجريشن `2026_06_20_maintenance_module.sql`.
- كل صفحة تستدعي `check_page_permissions($conn, 'Maintenance/<file>.php')` وتشتق `$can_view / $can_add / $can_edit / $can_delete`، وتعيد التوجيه إذا `!$can_view`.
- صفحة `main/project_users.php` (صلاحيات المشروع) هي التي تمنح/تمنع هذه الصلاحيات لكل دور.

**الثغرة الحالية (يجب إصلاحها ضمن هذه المهمة):**
زر التحرير (`edit`) يُطبع **دون فحص** `$can_edit` في الصفحات الثلاث؛ فقط الحذف محجوب بـ `$can_delete`. النتيجة أن مستخدماً صلاحيته «عرض فقط» (`can_view=1`, الباقي `0`) يصل للصفحة لكنه يرى زر تحرير لا يُفترض أن يملكه، وفي المقابل لا يجد أي إجراء «عرض» حقيقي.

**التكامل المطلوب:**
1. زر العرض يُتاح لكل من اجتاز فحص `$can_view` (وهو شرط الوصول للصفحة أصلاً) ⇒ يُطبع دائماً.
2. **حجب زر التحرير بـ `$can_edit`**:
   ```php
   if ($can_edit) {
       echo "<a href='inspections.php?id=" . intval($row['id']) . "' class='action-btn edit' title='فتح/تحرير'><i class='fas fa-pen-to-square'></i></a>";
   }
   ```
   بذلك يصبح لدور «العرض فقط» إجراء واحد فعّال = زر العرض، فيتصفّح البيانات كاملة دون تحرير أو حذف — وهو جوهر الربط بصلاحية العرض في صفحات المشروع.

> ملاحظة: `orders.php` يحتاج نفس التعديل (حجب edit بـ `$can_edit`) لأنه يطبعه حالياً دون شرط.

---

## 3) التعديلات على `inspections.php`

### 3.1 توسيع استعلام القائمة
استبدل الـ SELECT الحالي (يجلب 7 أعمدة) بآخر يجلب كل حقول العرض + اسم المشروع:
```php
$sql = "SELECT i.id, i.code, i.inspection_type, i.scheduled_date, i.completed_at,
               i.score, i.overall_result, i.tech_readiness_state,
               i.equipment_condition, i.engine_condition, i.notes, i.state,
               e.name AS equipment_name, p.name AS project_name, u.name AS inspector_name
          FROM mnt_inspection i
          LEFT JOIN equipments e ON e.id = i.equipment_id
          LEFT JOIN project p    ON p.id = i.project_id
          LEFT JOIN users u      ON u.id = i.inspector_id
         WHERE $company_scope_sql AND COALESCE(i.is_deleted,0)=0
         ORDER BY i.id DESC";
```

### 3.2 بناء `$da` + زر العرض + حجب التحرير
داخل حلقة `while`، قبل طباعة `<tr>`:
```php
$st = (string) $row['state'];
$da =
    "data-code='"        . htmlspecialchars((string)$row['code'], ENT_QUOTES) . "' " .
    "data-type='"        . htmlspecialchars((string)$row['inspection_type'], ENT_QUOTES) . "' " .
    "data-equipment='"   . htmlspecialchars((string)($row['equipment_name'] ?? ''), ENT_QUOTES) . "' " .
    "data-project='"     . htmlspecialchars((string)($row['project_name'] ?? ''), ENT_QUOTES) . "' " .
    "data-inspector='"   . htmlspecialchars((string)($row['inspector_name'] ?? ''), ENT_QUOTES) . "' " .
    "data-scheduled='"   . htmlspecialchars((string)($row['scheduled_date'] ?? ''), ENT_QUOTES) . "' " .
    "data-completed='"   . htmlspecialchars((string)($row['completed_at'] ?? ''), ENT_QUOTES) . "' " .
    "data-score='"       . htmlspecialchars((string)($row['score'] ?? ''), ENT_QUOTES) . "' " .
    "data-overall='"     . htmlspecialchars((string)($row['overall_result'] ?? ''), ENT_QUOTES) . "' " .
    "data-readiness='"   . htmlspecialchars((string)($row['tech_readiness_state'] ?? ''), ENT_QUOTES) . "' " .
    "data-eqcond='"      . htmlspecialchars((string)($row['equipment_condition'] ?? ''), ENT_QUOTES) . "' " .
    "data-engcond='"     . htmlspecialchars((string)($row['engine_condition'] ?? ''), ENT_QUOTES) . "' " .
    "data-notes='"       . htmlspecialchars((string)($row['notes'] ?? ''), ENT_QUOTES) . "' " .
    "data-state='"       . htmlspecialchars($st, ENT_QUOTES) . "'";
```
ثم عمود الإجراءات:
```php
echo "<td><div class='action-btns'>";
echo "<a href='javascript:void(0)' class='viewBtn action-btn view' $da title='عرض التفاصيل'><i class='fas fa-eye'></i></a>";
if ($can_edit) echo "<a href='inspections.php?id=" . intval($row['id']) . "' class='action-btn edit' title='فتح/تحرير'><i class='fas fa-pen-to-square'></i></a>";
if ($can_delete) echo "<a href='?delete_id=" . intval($row['id']) . "' class='action-btn delete' onclick='return confirm(\"حذف التفتيش؟\")' title='حذف'><i class='fas fa-trash-alt'></i></a>";
echo "</div></td>";
```

### 3.3 معالج العرض في الـ JS
داخل `$(document).ready`، بعد إعداد الـ DataTable (وقبل قوس الإغلاق):
```js
$(document).on('click', '.viewBtn', function () {
    var d = $(this).data();
    EmsDetailsModal.open({
        title: 'تفاصيل التفتيش',
        icon: 'fas fa-clipboard-check',
        fields: [
            { label:'المرجع', value:d.code, icon:'fas fa-hashtag' },
            { label:'الحالة', value:d.state, icon:'fas fa-flag', type:'status' },
            { label:'النوع', value:d.type, icon:'fas fa-list' },
            { label:'المعدة', value:d.equipment, icon:'fas fa-tractor' },
            { label:'المشروع', value:d.project, icon:'fas fa-folder-open' },
            { label:'الفاحص', value:d.inspector, icon:'fas fa-user-gear' },
            { label:'التاريخ المجدول', value:d.scheduled, icon:'fas fa-calendar' },
            { label:'تاريخ الإكمال', value:d.completed, icon:'fas fa-calendar-check' },
            { label:'التقييم', value:d.score, icon:'fas fa-star' },
            { label:'النتيجة العامة', value:d.overall, icon:'fas fa-clipboard-check' },
            { label:'الجاهزية الفنية', value:d.readiness, icon:'fas fa-gauge-high' },
            { label:'حالة المعدة', value:d.eqcond, icon:'fas fa-tractor' },
            { label:'حالة المحرك', value:d.engcond, icon:'fas fa-gears' },
            { label:'ملاحظات', value:d.notes, icon:'fas fa-note-sticky', size:'full' }
        ]
    });
});
```

---

## 4) التعديلات على `preventive_plans.php`

### 4.1 توسيع استعلام `$rows`
أضف للأعمدة المجلوبة: `scope, category_id (+ اسم الفئة)، tolerance، last_done_date، last_done_meter`، وضمّ جدول الفئات:
```php
// في الاستعلام الذي يبني $rows:
SELECT pl.id, pl.code, pl.name, pl.scope, pl.trigger_basis, pl.interval_value,
       pl.tolerance, pl.last_done_date, pl.last_done_meter,
       pl.next_due_date, pl.next_due_meter, pl.state,
       e.name AS equipment_name, ct.name AS category_name
  FROM mnt_plan pl
  LEFT JOIN equipments e       ON e.id  = pl.equipment_id
  LEFT JOIN equipments_types ct ON ct.id = pl.category_id
 WHERE <company_scope> AND COALESCE(pl.is_deleted,0)=0
 ORDER BY pl.id DESC
```

### 4.2 بناء `$da` + زر العرض + حجب التحرير
داخل `foreach ($rows as $row)`، قبل `<tr>`:
```php
$st = (string) $row['state'];
$da =
    "data-code='"      . htmlspecialchars((string)$row['code'], ENT_QUOTES) . "' " .
    "data-name='"      . htmlspecialchars((string)$row['name'], ENT_QUOTES) . "' " .
    "data-scope='"     . htmlspecialchars((string)($row['scope'] ?? ''), ENT_QUOTES) . "' " .
    "data-equipment='" . htmlspecialchars((string)($row['equipment_name'] ?? ''), ENT_QUOTES) . "' " .
    "data-category='"  . htmlspecialchars((string)($row['category_name'] ?? ''), ENT_QUOTES) . "' " .
    "data-basis='"     . htmlspecialchars((string)$row['trigger_basis'], ENT_QUOTES) . "' " .
    "data-interval='"  . htmlspecialchars((string)($row['interval_value'] ?? ''), ENT_QUOTES) . "' " .
    "data-tolerance='" . htmlspecialchars((string)($row['tolerance'] ?? ''), ENT_QUOTES) . "' " .
    "data-lastdate='"  . htmlspecialchars((string)($row['last_done_date'] ?? ''), ENT_QUOTES) . "' " .
    "data-lastmeter='" . htmlspecialchars((string)($row['last_done_meter'] ?? ''), ENT_QUOTES) . "' " .
    "data-duedate='"   . htmlspecialchars((string)($row['next_due_date'] ?? ''), ENT_QUOTES) . "' " .
    "data-duemeter='"  . htmlspecialchars((string)($row['next_due_meter'] ?? ''), ENT_QUOTES) . "' " .
    "data-state='"     . htmlspecialchars($st, ENT_QUOTES) . "'";
```
عمود الإجراءات:
```php
echo "<td><div class='action-btns'>";
echo "<a href='javascript:void(0)' class='viewBtn action-btn view' $da title='عرض التفاصيل'><i class='fas fa-eye'></i></a>";
if ($can_edit) echo "<a href='preventive_plans.php?id=" . intval($row['id']) . "' class='action-btn edit' title='فتح/تحرير'><i class='fas fa-pen-to-square'></i></a>";
if ($can_delete) echo "<a href='?delete_id=" . intval($row['id']) . "' class='action-btn delete' onclick='return confirm(\"حذف الخطة؟\")' title='حذف'><i class='fas fa-trash-alt'></i></a>";
echo "</div></td>";
```

### 4.3 معالج العرض في الـ JS
أضف داخل `$(document).ready` (الحالي بسيط ويُنشئ DataTable فقط):
```js
$(document).on('click', '.viewBtn', function () {
    var d = $(this).data();
    EmsDetailsModal.open({
        title: 'تفاصيل الخطة الوقائية',
        icon: 'fas fa-calendar-check',
        fields: [
            { label:'المرجع', value:d.code, icon:'fas fa-hashtag' },
            { label:'الحالة', value:d.state, icon:'fas fa-flag', type:'status' },
            { label:'اسم الخطة', value:d.name, icon:'fas fa-clipboard-list', size:'lg' },
            { label:'النطاق', value:d.scope, icon:'fas fa-layer-group' },
            { label:'المعدة', value:d.equipment, icon:'fas fa-tractor' },
            { label:'الفئة', value:d.category, icon:'fas fa-tags' },
            { label:'الأساس', value:d.basis, icon:'fas fa-sliders' },
            { label:'الفاصل', value:d.interval, icon:'fas fa-ruler' },
            { label:'السماحية', value:d.tolerance, icon:'fas fa-arrows-left-right' },
            { label:'آخر تنفيذ (تاريخ)', value:d.lastdate, icon:'fas fa-calendar' },
            { label:'آخر تنفيذ (عداد)', value:d.lastmeter, icon:'fas fa-gauge' },
            { label:'الاستحقاق القادم (تاريخ)', value:d.duedate, icon:'fas fa-calendar-day' },
            { label:'الاستحقاق القادم (عداد)', value:d.duemeter, icon:'fas fa-gauge-high' }
        ]
    });
});
```

---

## 5) التعديل على `orders.php`

زر العرض والنافذة موجودان وكاملان. التعديل الوحيد المطلوب للاتساق مع الصلاحيات:
```php
// حجب التحرير بصلاحية التعديل (حالياً يُطبع دون شرط):
if ($can_edit) {
    echo "<a href='orders.php?id=" . intval($row['id']) . "' class='action-btn edit' title='فتح/تحرير'><i class='fas fa-pen-to-square'></i></a>";
}
```

---

## 6) خطوات الاختبار (Verification)

1. **عرض البيانات:** فتح كل صفحة، الضغط على زر العين لعدة سجلات قديمة والتأكد من ظهور كل الحقول صحيحة (لا فراغات لأعمدة موجودة، لا «mojibake»).
2. **العزل (company_id):** التأكد أن النافذة لا تعرض إلا بيانات شركة المستخدم.
3. **تكامل الصلاحيات — أنشئ/استخدم دوراً صلاحيته «عرض فقط» على الصفحات الثلاث من `main/project_users.php`، ثم:**
   - يظهر زر العرض ويعمل. ✅
   - لا يظهر زر التحرير ولا الحذف. ✅
   - الوصول المباشر بـ `?id=` للتحرير محجوب أصلاً عبر فحوص الصفحة الداخلية (تأكيد إضافي).
4. **دور كامل الصلاحيات:** تظهر الأزرار الثلاثة معاً وتعمل.
5. **الترميز/الأمان:** كل القيم مارّة عبر `htmlspecialchars(ENT_QUOTES)` ⇒ لا XSS في سمات `data-*`.

---

## 7) ملخّص الملفات المتأثرة

| الملف | التعديل |
|-------|---------|
| `Maintenance/inspections.php` | توسيع SELECT + `$da` + زر العرض + حجب edit بـ can_edit + معالج JS |
| `Maintenance/preventive_plans.php` | توسيع `$rows` + `$da` + زر العرض + حجب edit بـ can_edit + معالج JS |
| `Maintenance/orders.php` | حجب edit بـ can_edit فقط (الباقي جاهز) |

**لا تغييرات في قاعدة البيانات** — الأعمدة المطلوبة كلها موجودة في `mnt_inspection` و`mnt_plan`، والصفحات مسجّلة مسبقاً في `modules`.
