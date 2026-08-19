# بطاقةُ الكِيان — نظامُ تصميمٍ واحدٌ لكلِّ البطاقات

> **الحالة (2026-08-19):** مُنفَّذٌ في بطاقتَي **العميل** و**الموظف**.
> **الجاهز للتعميم:** بطاقةُ المشروعِ · المورِّدِ · المعدّةِ — بلا سطرِ CSS جديد.

---

## ١ · لماذا مكوّنٌ لا نسخةٌ لكلِّ شاشة

قبل هذه الجولةِ كانت البطاقتانِ تقولان الشيءَ نفسَه بمفرداتٍ مختلفة:

| المعنى | بطاقةُ العميلِ (كتلةُ `<style>` في الصفحة) | بطاقةُ الموظفِ (`.driver-profile-page` في الملفِّ العام) |
|---|---|---|
| لوحُ الهوية | `.profile-card` + `.identity-head` | `.identity-card` + `.id-grid` + `.photo-box` |
| بطاقةُ عدّاد | `.profile-card` + `.kpi` + `.label` | `.stat-card` + `.stat-value` + `.stat-label` |
| شريطُ عدّادات | `.cp-band` | `.stats-grid` |
| قسمُ محتوى | `.card` + `.card-header` + `.card-body` | `.section-card` + `.section-head` + `.section-body` |
| حقلٌ معنون | — (نصٌّ مفصولٌ بـ `\|`) | `.info-item` + `.label` + `.value` |
| شارةُ حالة | `.state-badge` · `.opp-stage` · `.tnd-badge` · `.cp-via` | `.driver-badge` · `.assignment-status` |

**١٣٣ سطرًا** داخلَ صفحةِ العميلِ و**٣٢٠ سطرًا** في `ems.main.all.style.css` — تصفان
بِنيةً واحدة. وحين تُطلب بطاقةُ المشروعِ تُنسخ إحداهما فتصير ثالثةً، ثم رابعةً
للمورِّد، وخامسةً للمعدّة. وهذا بعينِه **«تعدّدُ ملفاتِ التصميمِ واتساخُ الكود»**.

**بعد الجولة:** ملفٌّ واحدٌ + عُدّةٌ واحدة. والشاشةُ **تصف** بطاقتَها ولا **ترسمها**.

| الملف | الدور |
|---|---|
| `assets/css/ems-profile.css` | **المصدرُ الوحيدُ** لتصميمِ بطاقاتِ الكيانات |
| `includes/profile_kit.php` | العُدّةُ التي تُخرج الترميز — لا صنفَ يُكتب يدويًّا |
| `tests/profile_card_contract_test.php` | قيدُ العقدِ (ساكن) |
| `tests/profile_card_http_proof.php` | برهانُ التعشيشِ (حيٌّ على DOM) |

---

## ٢ · بطاقةٌ جديدةٌ في عشرِ دقائق

```php
require_once __DIR__ . '/../includes/profile_kit.php';
```

ثم على غلافِ الصفحة — **`.ems-profile` شرطُ انطباقِ الطبقةِ كلِّها**:

```php
<div class="main project-profile-page ems-profile ems-unified-page-shell">
```

### ① لوحُ الهوية

```php
echo ems_profile_hero(array(
    'name'   => $project['name'],
    'icon'   => 'fas fa-diagram-project',   // أو 'photo' => مسارُ صورة
    'status' => array('text' => 'قيد التنفيذ', 'tone' => 'ok', 'icon' => 'fas fa-circle-check'),
    'note'   => 'بطاقةُ المشروعِ داخلَ النظام',
    'chips'  => array(
        array('text' => $project['code'],   'icon' => 'fas fa-hashtag', 'mono' => true),
        array('text' => $project['client'], 'icon' => 'fas fa-building'),
    ),
    'facts'  => array(
        array('label' => 'الموقع',        'value' => $project['site']),
        array('label' => 'تاريخ البدء',   'value' => $project['start_date']),
        array('label' => 'مدير المشروع',  'value' => $project['manager']),
    ),
));
```

* الصورةُ الغائبةُ تصير **أيقونةَ الكِيان** لا مربّعًا رماديًّا صامتًا.
* القيمةُ الفارغةُ تُصيَّر «—» بصنفِ غيابٍ ظاهرٍ — **الغيابُ يُعلَن ولا يُلفَّق**.
* الكودُ بـ`'mono' => true` يُقرأ يسارًا-يمينًا داخلَ سياقٍ عربيّ.

### ② شريطُ المؤشرات

```php
echo ems_profile_stats(array(
    array('value' => 12, 'label' => 'المعدات في الموقع'),
    array('value' => 3,  'label' => 'تأخيرات', 'tone' => 'danger'),
    array('value' => number_format($h, 0), 'unit' => 'ساعة', 'label' => 'ساعات التشغيل', 'tone' => 'ok'),
    array('values' => $money_lines, 'label' => 'المستحق', 'variant' => 'money'),  // سطرٌ لكلِّ عملة
    array('value' => 5, 'label' => 'أوامر مفتوحة', 'href' => 'orders.php?p=' . $id),
));
```

| المفتاح | المعنى |
|---|---|
| `value` | القيمةُ — و**«0» قيمةٌ صحيحةٌ لا غياب** |
| `values` | مصفوفةُ أسطرٍ حين تتعدَّد العملات — **ولا تُجمع عملتانِ في رقمٍ أبدًا** |
| `label` | ما يقيسه الرقم (إلزامي) |
| `unit` | لاحقةٌ أصغرُ بعد الرقم: ساعة · سجل · يوم |
| `tone` | `ok` · `warn` · `danger` · `gold` · `muted` |
| `variant` | `money` أو `date` — يضبط حجمَ الرقمِ لا معناه |
| `href` | وجهةُ التعمّقِ — فيصير المؤشرُ رابطًا |

### ③ مجموعةٌ ← أقسام

المجموعةُ **محطّةٌ من رحلةِ الكِيان**، والقسمُ وحدةُ محتوًى داخلَها.
سطحُ المجموعةِ أهدأُ من سطحِ الأقسامِ فيها — فالتدرُّجُ يقول أيُّهما سياقٌ
وأيُّهما محتوى.

```php
<?php if ($has_execution): ?>
  <?php echo ems_profile_group_open(array(
      'title' => 'التنفيذ والتسليم',
      'icon'  => 'fas fa-helmet-safety',
      'meta'  => 'عقدٌ ← وحدةٌ ← تسليم',
  )); ?>

    <?php if (!empty($units)): ?>
      <?php echo ems_profile_section_open(array(
          'title' => 'وحداتُ المشروع',
          'icon'  => 'fas fa-truck',
          'note'  => 'الوحدةُ سطرُ التشغيلِ اليوميّ — والمعدّةُ قد تتغيّر عليها.',
      )); ?>
          <div class="table-container">
              <table class="display" id="projectUnitsTable"> … </table>
          </div>
      <?php echo ems_profile_section_close(); ?>
    <?php endif; ?>

  <?php echo ems_profile_group_close(); ?>
<?php endif; ?>
```

> **قاعدةٌ ملزمة:** المجموعةُ أو القسمُ **لا يُفتح أصلًا** إن خلا مصدرُه —
> لا عنوانَ فوقَ فراغ. وإن خلت البطاقةُ كلُّها فتُعلَن حالةُ فراغٍ **واحدةٌ**
> بسببِها وبابٍ يُفتح منها (`ems_state('empty', …)`)، لا بطاقةٌ صامتةٌ لا
> يدري قارئُها أفارغةٌ هي أم معطوبة.

### ④ الشارات

مكوّنٌ واحدٌ يخدم: حالةَ الكِيانِ · مرحلةَ الفرصةِ · نتيجةَ المناقصةِ · مصدرَ
الوصل. والفرقُ **نغمةٌ** لا قاعدةُ CSS جديدة.

```php
echo ems_profile_badge($row['state'], ems_profile_map($row['state'], array(
    'مسلَّم'  => 'ok',
    'متأخر'  => 'danger',
    'موقوف'  => 'neutral',
), 'warn'));
```

**النغماتُ ثمانٍ ولا تاسعةَ:**
`neutral` · `ok` · `warn` · `danger` · `info` · `gold` · `purple` · `cyan`
ونغمةٌ غيرُ معلنةٍ تسقط إلى `neutral` — لا تكسر الصفحةَ ولا تصمت بلا لون.

### ⑤ مكوّناتٌ أخرى جاهزة

| الاستعمال | الترميز |
|---|---|
| شبكةُ حقائقَ حرّة | `ems_profile_facts($pairs, $wide = true)` |
| لافتةٌ سطريةٌ داخلَ قسم | `ems_profile_note('نص', 'warn' \| 'info')` |
| صفُّ أفعال | `<div class="ems-profile__actions"> … </div>` |
| مستنداتٌ مصوَّرة | `__docs` › `__doc` › `__doc-empty` › `__doc-caption` |
| شبكةُ رسوم | `__charts` › `__chart` |
| خطٌّ زمنيّ | `__timeline` › `__timeline-item` › `__timeline-top` › `__timeline-meta` |
| لاحقةُ وحدةٍ في خلية | `<span class="ems-profile__unit">USD</span>` |

---

## ٣ · فخاخٌ مقيسةٌ حيًّا — لا تكرّرها

### ⚠️ ① وسمٌ فُتح `<section>` وأُغلق `</div>`

PHP لا تشتكي، والصفحةُ تُصيَّر بحالةِ **200**، والفحصُ الساكنُ يمرّ.
لكنّ المتصفحَ يُصحِّح التعشيشَ بطريقتِه: **خرجت ثلاثُ مجموعاتٍ من غلافِ
`.main` وصارت أبناءَ `<body>`** — و`<body>` شبكةٌ مرنة، فانكمش عرضُ المحتوى
إلى **48 بكسلًا** وامتدَّ الجسدُ إلى **3825**. صفحةٌ سليمةُ الحالةِ ومنهارةُ
الشكل.

**القيد:** `php tests/profile_card_http_proof.php مبيعات` — يقيس DOM المُصيَّر
لا المصدر. شغِّلْه بعد كلِّ بطاقةٍ جديدة.

### ⚠️ ② اسمُ الصنفِ قرارُ هندسةٍ لا ذوق

في النظامِ مُحدِّداتٌ عامةٌ تُطابق **بالسلسلةِ لا بالمعنى**:

```css
span[class*="badge-"]                                    /* ems-tables.css */
[class*="chip"] · [class*="tag"] · [class*="status"]     /* الطبقةُ العامةُ بـ!important */
```

صنفٌ اسمُه `…__badge--ok` يقع في شِباكِ الأول فيُمحى تصميمُه صامتًا
(**مقيسٌ**: الخلفيةُ صارت شفافةً والحدُّ برتقاليًّا). ولذلك سُمِّي المكوّنُ
**`__pill`** و**`__ident-item`** — لا `badge` ولا `chip` ولا `tag`.

القيدُ يمنع عودةَ الاسمِ الخاطئ: `tests/profile_card_contract_test.php` ③.

### ⚠️ ③ ترتيبُ التحميلِ لا يغلب الوزن

في `ems.main.all.style.css` طبقةٌ عنوانُها
«FINAL GLOBAL THEME STANDARDIZATION LAYER»:

```css
.ems-site .main span, … .ems-site .main h2 { color: var(--text-black); }
```

وزنُها **(0,2,1)** يغلب أيَّ مُحدِّدٍ بصنفٍ واحدٍ مهما تأخّر ترتيبُه.
فصُيِّرت نصوصُ المكوّنِ **سوداءَ صرفًا** رغم أنّ `ems-profile.css` تُحمَّل بعدَها.

**العلاج:** قسمُ «حارسِ التتالي» في آخرِ `ems-profile.css` — يرفع الوزنَ إلى
**(0,3,0)** بـ`.ems-site .ems-profile …` **بمقدارِ ما يلزم ولا يزيد**.
ولِمَ لا `!important`؟ لأنه يُنهي النقاشَ فيمنع أيَّ شاشةٍ من ضبطِ لونٍ لاحقًا.

> **إن أضفتَ عنصرًا جديدًا يحمل لونًا** (على `span`/`p`/`li`/`h1..h6`)
> فأضف سطرَه إلى الحارس، وإلا صُيِّر أسودَ صامتًا.

---

## ٤ · العقدُ المقيس

```bash
php tests/profile_card_contract_test.php
php tests/profile_card_http_proof.php مبيعات
```

| القيد | يمنع |
|---|---|
| صفرُ `<style>` وصفرُ `style=` في صفحةِ بطاقة | عودةَ التصميمِ إلى الصفحات |
| كلُّ لونٍ في `ems-profile.css` رمزٌ داخلَ `var()` | لوحةً ثانيةً خارجَ `design-tokens.css` |
| لا صنفَ يقع في شِباكِ `[class*=]` العامة | مَحوًا صامتًا للتصميم |
| مُخرَجُ العُدّةِ متوازنُ الوسوم | انهيارَ التعشيشِ في المتصفح |
| صفرُ عنصرٍ شاردٍ خارجَ `.ems-profile` (حيًّا) | العطبَ الذي وقع مرّتين |
| صفرُ قاعدةِ `.driver-profile-page` | نسخةً ثانيةً من البطاقة |

**عند إضافةِ بطاقةٍ جديدةٍ:** أضف مسارَها إلى `$CARDS` في الفاحصِ الساكن،
وإلى `$targets` في البرهانِ الحيّ. فالقيدُ يتوسّع مع النظامِ ولا يتخلّف عنه.

---

## ٥ · تغييرُ الشكلِ كلِّه من مكانٍ واحد

رموزُ الطبقةِ في رأسِ `ems-profile.css` (`--emsp-*`) **مشتقّةٌ** من
`design-tokens.css` لا لوحةٌ جديدة. فتغييرُ نصفِ قطرِ كلِّ بطاقةٍ في النظام،
أو لكنةِ العلامةِ، أو حبرِ العناوين — **سطرٌ واحدٌ**:

```css
:root {
    --emsp-radius:      var(--radius-lg, 12px);   /* نصفُ قطرِ كلِّ بطاقة */
    --emsp-accent:      var(--c-brand-gold);      /* لكنةُ العلامة */
    --emsp-anchor:      var(--c-brand-navy);      /* لونُ الأرقام */
    --emsp-shadow:      var(--shadow-sm);         /* ظلُّ كلِّ سطح */
}
```

لا ثلاثُ مئةِ قاعدةٍ متفرقة — ثمانيةُ أسطر.
