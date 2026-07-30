# H-05 — الموقع/المنجم كيانًا مستقلًّا في الهرم السباعي (بطاقةُ مواصفة — قيد التنفيذ)

> **المصدر الحاكم:** OPM-01 §2-③ (المرجع الجامع · النص مستخرَجٌ ومثبَّت أدناه) · **الجرد:** بطاقة ج-04 في [INVENTORY_WAVE123_ar.md](INVENTORY_WAVE123_ar.md)
> **الحالة:** المواصفةُ مكتوبة 2026-07-30 — التنفيذُ يبدأ منها (الموجة ② · 1).

## النصُّ الحاكم (مستخرجٌ حرفيًّا من الجامع)

- القاموس: «**الموقع/المنجم: مكانُ التنفيذ الفعليُّ الذي تُسجَّل فيه الوحدات — "المنجم" حالةٌ منه، ولا فرقَ في المعالجة**» (سطر 310).
- الهرم §2: «② المشروع: قد يضم منجمًا واحدًا أو عدةَ مناجمَ أو عدةَ مواقعِ تشغيل — **لا يُفترض موقعٌ واحد**» · «③ الموقع/المنجم: قد يكون له عقدٌ واحدٌ أو **عدةُ عقودٍ مستقلةٍ أو متتابعةٍ أو متوازية** — ولا يُفترض عقدٌ واحدٌ أبدًا» (سطور 2494–2497).

## ① المصير
كيانٌ جديدٌ يُبنى (لا شاشةَ قائمةً تُمسّ) — و`project` يبقى كما هو: **الموقعُ ابنُ المشروع** لا بديلُه.

## ② الواقع الحالي (من ج-04 مقيسًا)
لا جدولَ مواقع · `project` 23 عمودًا/19 صفًّا هو الموقعُ ضمنًا · `contracts.project_id` هو الربطُ الوحيد · جدولُ `mines` أُزيل تاريخيًّا (مسارُه الميت محفوظٌ في `get_mine_contracts.php`) · `rfq_lines` القادمة (H-21) تنتظر `site_id` نصًّا (UX-05 §8.2).

## ③ البنية المعتمدة (هجرة `2026_08_14_h05_sites_entity.sql`)

```sql
CREATE TABLE sites (
  id INT AUTO_INCREMENT PRIMARY KEY,
  company_id INT NOT NULL,                    -- عزلُ المستأجر (يسجَّل في TenantRegistry)
  project_id INT NOT NULL,                    -- FK → project (الهرم ②→③)
  name VARCHAR(190) NOT NULL,
  site_kind ENUM('mine','site') NOT NULL DEFAULT 'site',  -- «المنجمُ حالةٌ منه لا فرق» — تمييزٌ عرضيٌّ فقط؛ ENUM لاتيني عمدًا (گوتشا الترميز) والتعريب في الشاشة
  responsible_employee_id INT NULL,           -- مدخلُ E-07/H-03 (مسؤولُ الموقع)
  location_text VARCHAR(255) NULL, lat DECIMAL(10,7) NULL, lng DECIMAL(10,7) NULL,
  status TINYINT NOT NULL DEFAULT 1,
  is_default TINYINT NOT NULL DEFAULT 0,      -- موقعُ الترحيل الرجعي (المشروع كان الموقعَ ضمنًا)
  is_deleted TINYINT NOT NULL DEFAULT 0, deleted_at DATETIME NULL, deleted_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_site_name (company_id, project_id, name),
  KEY ix_sites_project (project_id),
  CONSTRAINT fk_sites_project FOREIGN KEY (project_id) REFERENCES project(id),
  CONSTRAINT fk_sites_resp FOREIGN KEY (responsible_employee_id) REFERENCES employees(id)
);
ALTER TABLE contracts ADD COLUMN site_id INT NULL AFTER project_id,
  ADD KEY ix_contracts_site (site_id),
  ADD CONSTRAINT fk_contracts_site FOREIGN KEY (site_id) REFERENCES sites(id);
```

**التعبئة الرجعية الصادقة (لا اختراع):** لكل مشروعٍ قائمٍ موقعٌ افتراضيٌّ واحدٌ باسم المشروع نفسِه (`is_default=1`) — لأن الواقعَ المقيس «المشروعُ هو الموقعُ ضمنًا»؛ وكلُّ عقدٍ يُربط بموقع مشروعِه الافتراضي. صفرُ تغييرٍ دلاليٍّ على القائم.

## ④/⑤ الاستمارة والتشغيل
شاشةُ إدارةٍ `Projects/sites.php` (سجلٌّ رئيسيٌّ بسيط: قائمةٌ بفلتر مشروعٍ + استمارة بالطبقات الثلاث: **يُستنتج** المشروعُ من السياق · **افتراضي** النوع site · **إلزامي** الاسمُ وحده) — موديولٌ جديدٌ تحت مجموعة المشاريع لمالكه الدور 1، وعرضٌ للتشغيل (3). serverSide غيرُ لازمٍ (عشرات الصفوف) لكن pageLength 50 يسري تلقائيًّا.

## ⑥ الحدث المالي
لا أثرَ ماليًّا مباشرًا — الموقعُ بُعدُ تسجيلٍ (الوحداتُ والقيودُ تصله عبر العقد/المشروع). مركزُ التكلفة يبقى بالمشروع (M-38) حتى قرارٍ لاحق.

## ⑦ المواصفة التنفيذية
- تسجيلُ `sites` في `TenantRegistry` (fail-closed 141→142) وإلا رفضت البوابةُ كلَّ قراءة.
- القراءةُ عبر `scopedQuery`/بوابة المستأجر حصرًا · الحذفُ ناعمٌ فقط.
- **التوافق الرجعي:** `contracts.site_id` NULL مسموحٌ (القديمُ يعمل بلا موقع حتى التعميم) — لا حارسَ إلزامٍ في هذه المرحلة؛ الإلزامُ يأتي مع H-03 (لا يُفتح موقعٌ ناقص).
- المستهلكون القادمون: N-03 (الالتزاماتُ لكل موقع) · H-03 (خطةُ غدِ الموقع) · H-21 (`rfq_lines.site_id`).

## ⑧ المتطلب النظامي
لا شيءَ خاصًّا — سجلٌّ داخلي.

## القبول
`tests/sites_entity_test.php`: البنيةُ بقيودها · التعبئةُ 19/19 مشروعًا بموقعه الافتراضي · عقودُ 10/10 مربوطة · عزلُ المستأجر · UNIQUE يمنع التكرار · الشاشةُ بفحص صلاحيةٍ وحارسِ فعل.
