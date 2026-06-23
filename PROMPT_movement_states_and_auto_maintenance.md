# برومت تنفيذ موحّد: حالات الآليات + أمر الصيانة التلقائي + إشعار الأوامر المفتوحة

**التاريخ:** 2026-06-23
**الصفحات المتأثرة:**
`movement/movement_operations.php` · `Oprators/oprators.php` · `Maintenance/orders.php` · مايجريشن واحد + endpoint عدّاد

> هذا البرومت ثلاثة أجزاء مترابطة. نفّذها بالترتيب. كل جزء بسيط ومستقل في الاختبار.

---

## الفكرة باختصار

1. **الحالة:** لكل آلية نشطة في الموقع حالة واحدة من ثلاث: **تعمل / جاهزة / معطلة**. نفصلها عن «الدور»
   (أساسي/احتياطي) الذي يبقى ثابتًا للتقارير.
2. **التلقائي:** عند تحويل آلية إلى **«معطلة»** من صفحة الحركة، يُفتح **أمر صيانة تلقائي** فورًا.
3. **الإشعار:** في صفحة أوامر الصيانة، أيقونة جرس تُظهر عدد الأوامر التلقائية المفتوحة، مع تمييز كل أمر
   تلقائي بشارة `auto` بجانب اسم المعدة.

---

## القواعد الثابتة (لا تُغيّرها)

| الموضوع | القاعدة |
|--------|---------|
| **الدور** (أساسي/احتياطي) | يُحدَّد مرة واحدة عند الإضافة من `Oprators/oprators.php` فقط، وثابت لا يتغيّر. صفحة الحركة لا تلمسه. |
| **الحالة** (تعمل/جاهزة/معطلة) | تُدار من `movement/movement_operations.php` فقط، عبر عمود جديد `op_state`. للتشغيلات النشطة فقط. |
| **دورة الحياة** (نشط/منتهي) | عمود `status` كما هو (1 نشط / 0 منتهي). المنتهي = تاريخ فقط، بلا حالة. |
| **الحالة الابتدائية** | كل معدة جديدة تبدأ **«جاهزة»** حتى يحوّلها مدير الحركة إلى «تعمل». |
| **التبديل** | يدوي منفرد: كل صف يُغيَّر وحده، بلا تبديل تلقائي بين آليتين. |
| **بعد الصيانة** | عند إغلاق أمر الصيانة تعود الآلية إلى **«جاهزة»** (لا «تعمل»). |
| **منع التكرار** | لا يُفتح أمر تلقائي ثانٍ لمعدة لها أمر تلقائي مفتوح بالفعل. |

---

# الجزء 1 — ضبط الحالات

## 1.1 قاعدة البيانات
أنشئ `database/migrations/2026_06_23_states_and_auto_orders.sql`:
```sql
-- (أ) عمود الحالة التشغيلية في التشغيلات
ALTER TABLE operations
  ADD COLUMN op_state ENUM('تعمل','جاهزة','معطلة') NOT NULL DEFAULT 'جاهزة'
  COMMENT 'حالة الآلية النشطة — تُدار من صفحة الحركة فقط' AFTER status;

-- ترحيل البيانات: المعطّلة سابقًا تأخذ الحالة الجديدة، والدور يُسترجع من العمود الالتفافي
UPDATE operations
SET op_state = 'معطلة',
    equipment_category = COALESCE(NULLIF(prev_equipment_category,''), 'أساسي')
WHERE equipment_category = 'متعطل';

UPDATE operations
SET op_state = 'جاهزة'
WHERE status = 1 AND equipment_category <> 'متعطل';

-- (ب) علم الأمر التلقائي في أوامر الصيانة (يُستخدم في الجزء 2)
ALTER TABLE mnt_order
  ADD COLUMN is_auto TINYINT(1) NOT NULL DEFAULT 0
  COMMENT 'أمر صيانة أُنشئ تلقائيًا من صفحة الحركة' AFTER source;

CREATE INDEX idx_mnt_order_auto_open
  ON mnt_order (company_id, equipment_id, project_id, is_auto, state);
```
> خذ نسخة احتياطية، ثم طبّق المايجريشن يدويًا على `localhost`.

## 1.2 `movement/movement_operations.php`
**أ) أوقف الكتابة في الدور:**
- في `save_single_operation` (≈ سطر 133): لا تكتب `equipment_category` إطلاقًا.
- في `set_equipment_maintenance` (≈ سطر 432): احذف تحديث `equipment_category='متعطل'` وحفظ
  `prev_equipment_category` (≈ 469–485)، واستبدلها بتحديث الحالة:
  ```php
  mysqli_query($conn, "UPDATE operations SET op_state = 'معطلة'
                        WHERE equipment = $equipment_id AND project_id = $selected_project_id
                          AND status = 1 $operations_company_scope_inline");
  ```
  (أبقِ تحديث `equipments.availability_status='تحت الصيانة'` كما هو.)

**ب) أضِف معالجَين جديدين** (بنفس نمط `try/catch` و`json` القائم):
- `set_op_state` — لتبديل **تعمل ⇄ جاهزة** يدويًا (امنع «معطلة» من هنا):
  ```php
  $new = in_array($_POST['op_state'] ?? '', ['تعمل','جاهزة'], true) ? $_POST['op_state'] : null;
  // UPDATE operations SET op_state = ? WHERE id = ? AND project_id = ? AND status = 1 (+ نطاق الشركة)
  ```
- `restore_from_maintenance` — لانتقال **معطلة → جاهزة**:
  ```php
  // UPDATE operations SET op_state='جاهزة' WHERE equipment=? AND project_id=? AND status=1
  // UPDATE equipments SET availability_status='قيد الاستخدام', availability_state='متوفرة' WHERE id=?
  ```
  استخدم prepared statements والتزم بعزل `company_id`.

**ج) الاستعلام:** أضِف `o.op_state` إلى أعمدة `$operations_sql` (≈ سطر 511).

**د) العرض (≈ 1205–1279):**
- احذف منطق `$is_under_maint` والشارة المزدوجة (≈ 1211، 1263).
- للصف **النشط**: شارة واحدة من `op_state` بثلاثة ألوان: تعمل=أخضر، جاهزة=كهرماني، معطلة=أحمر.
- للصف **المنتهي**: لا شارة حالة (تواريخ فقط).
- الأزرار (نشط + `$can_edit`):
  - محدّد/زر لتبديل **تعمل ⇄ جاهزة** ← `set_op_state`.
  - زر **«صيانة»** يظهر إن لم تكن الحالة «معطلة» ← يحوّلها إلى معطلة (الجزء 2).
  - زر **«إرجاع من الصيانة»** يظهر فقط إن الحالة «معطلة» ← `restore_from_maintenance`.
  - زر **«إنهاء»** كما هو.

## 1.3 `Oprators/oprators.php`
- أزِل خيار **«متعطل»** من قائمة الفئة (≈ سطر 708) — تبقى فقط «أساسي» و«احتياطي».
- جدولا «أساسية/احتياطية» يظلّان مقسَّمين بالدور (`equipment_category`) ⇒ مستقرّان مهما تغيّرت الحالة.

---

# الجزء 2 — أمر الصيانة التلقائي

العمود `is_auto` أُنشئ في الجزء 1.1. الآن داخل `set_equipment_maintenance` في صفحة الحركة، **بعد**
تحويل الحالة إلى «معطلة» مباشرةً، أضِف إنشاء أمر تلقائي مع منع التكرار:

```php
require_once __DIR__ . '/../Maintenance/mnt_helpers.php';

// منع التكرار: هل يوجد أمر تلقائي مفتوح لنفس المعدة/المشروع؟
$dup = mysqli_query($conn, "SELECT 1 FROM mnt_order
    WHERE equipment_id=$equipment_id AND project_id=$selected_project_id AND company_id=$company_id
      AND is_auto=1 AND state IN ('بلاغ','تنفيذ','فحص') AND COALESCE(is_deleted,0)=0 LIMIT 1");

if (!$dup || mysqli_num_rows($dup) === 0) {
    $code = mnt_next_code($conn, 'mnt_order', 'MNT', $company_id);
    $src = 'بلاغ'; $st = 'بلاغ';   // المصدر الدلالي + الحالة الابتدائية (مفتوح)
    if ($ins = mysqli_prepare($conn,
        "INSERT INTO mnt_order (company_id, code, equipment_id, project_id, source, is_auto, state, created_by)
         VALUES (?, ?, ?, ?, ?, 1, ?, ?)")) {
        mysqli_stmt_bind_param($ins, 'isiissi',
            $company_id, $code, $equipment_id, $selected_project_id, $src, $st, $current_user_id);
        mysqli_stmt_execute($ins);
        mysqli_stmt_close($ins);
    }
}
```
ملاحظات: تأكد أن اسم متغير المستخدم الحالي مطابق للملف (مثل `$current_user_id`). يُفضَّل لفّ
(تحويل الحالة + إنشاء الأمر) في `START TRANSACTION` / `COMMIT` لضمان الذرّية.

**شارة `auto` في `Maintenance/orders.php`** — عدّل خلية اسم المعدة (≈ سطر 802):
```php
echo "<td>" . htmlspecialchars((string)($row['eq_name'] ?? '-'));
if (!empty($row['is_auto'])) echo " <span class='mnt-auto-badge'>auto</span>";
echo "</td>";
```
(الاستعلام يبدأ بـ `SELECT o.*` فيأتي `is_auto` تلقائيًا.) وأضِف التنسيق:
```css
.mnt-auto-badge{display:inline-block;margin-inline-start:6px;padding:1px 7px;font-size:11px;
  font-weight:700;border-radius:6px;background:#6d28d9;color:#fff;letter-spacing:.5px;}
```

---

# الجزء 3 — إشعار الأوامر المفتوحة (في `orders.php`)

**أ) endpoint** `Maintenance/get_open_orders_count.php` (انسخ بنية `get_breakdown_count.php`):
```php
$sql = "SELECT COUNT(*) c FROM mnt_order
        WHERE company_id = $company_id AND is_auto = 1
          AND state IN ('بلاغ','تنفيذ','فحص') AND COALESCE(is_deleted,0)=0";
// أعِد JSON: {"count": N}
```
> العدّاد هنا للأوامر **التلقائية المفتوحة** (الواردة من صفحة الحركة). لعدّ كل المفتوحة: احذف شرط `is_auto=1`.

**ب) أيقونة الجرس** في رأس `orders.php`:
```html
<span class="mnt-bell" title="أوامر مفتوحة">
  <i class="fas fa-bell"></i>
  <span class="mnt-bell-badge" id="openOrdersBadge" style="display:none">0</span>
</span>
```
```js
function refreshOpenOrdersBadge(){
  fetch('get_open_orders_count.php',{headers:{'X-Requested-With':'XMLHttpRequest'}})
   .then(r=>r.json()).then(d=>{ var b=document.getElementById('openOrdersBadge');
     if(d.count>0){b.textContent=d.count;b.style.display='inline-block';} else {b.style.display='none';} })
   .catch(()=>{});
}
refreshOpenOrdersBadge(); setInterval(refreshOpenOrdersBadge, 60000);
```
أضِف تنسيقًا بسيطًا للجرس وشارة حمراء صغيرة متّسقة مع الصفحة.

---

## الاختبار (نفّذه كاملًا قبل الإغلاق)

1. **المايجريشن:** العمودان أُضيفا؛ كل صف كان «متعطل» صار `op_state='معطلة'` ودوره (أساسي/احتياطي) سليم.
2. **الإضافة:** معدة جديدة من صفحة التشغيل تظهر في الحركة بحالة **«جاهزة»**.
3. **التبديل:** جاهزة→تعمل و تعمل→جاهزة، كلٌّ على حدة، بلا تأثير على صف آخر.
4. **التعطيل + التلقائي:** زر «صيانة» ⇒ الحالة «معطلة» + `availability_status='تحت الصيانة'` + **أمر صيانة جديد**
   بشارة `auto`، والدور لم يتغيّر.
5. **منع التكرار:** ضغط «صيانة» ثانيةً لنفس المعدة لا يُنشئ أمرًا جديدًا. بعد إغلاق الأمر يُسمح بإنشاء جديد.
6. **العودة:** «إرجاع من الصيانة» (أو إغلاق الأمر) ⇒ المعدة تعود **«جاهزة»** و`availability_status='قيد الاستخدام'`.
7. **الإشعار:** عدّاد الجرس يطابق عدد الأوامر التلقائية المفتوحة، يختفي عند الصفر، ويتحدّث بعد فتح/إغلاق أمر.
8. **المنتهية:** التشغيل المنتهي بلا شارة حالة.
9. **العزل والأمان:** كل التحديثات ضمن `company_id`، عبر prepared statements، إخراج آمن، بلا mojibake.

---

## ملخّص الملفات

| الملف | التعديل |
|------|---------|
| `database/migrations/2026_06_23_states_and_auto_orders.sql` | **جديد** — `op_state` + ترحيل + `is_auto` + فهرس |
| `movement/movement_operations.php` | إيقاف كتابة الدور؛ `op_state`؛ معالجا `set_op_state` و`restore_from_maintenance`؛ إنشاء الأمر التلقائي؛ شارة + أزرار العرض |
| `Maintenance/orders.php` | شارة `auto` + أيقونة جرس + JS العدّاد |
| `Maintenance/get_open_orders_count.php` | **جديد** — عدّاد JSON |
| `Oprators/oprators.php` | إزالة خيار «متعطل» من الفئة |

**ملاحظة:** عمود `prev_equipment_category` يصبح مهجورًا — أوقف الكتابة إليه دون حذفه فورًا.
