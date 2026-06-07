# دليل طبقة الـ API وتطبيق Flutter — مدير الحركة والتشغيل (EMS)

> توثيق ما بُني في 2026-06-06/07: طبقة REST API فوق نظام EMS + تطبيق Flutter جوّال
> لدور **مدير الحركة والتشغيل**. هذا الملف مرجع لفهم البنية وإعادة الإنتاج، وقالب
> لأي تطبيقات جوّال لاحقة على نفس النظام.

---

## 1. نظرة عامة

نظام EMS الأصلي: PHP + MySQL (mysqli)، عربي RTL، متعدّد الشركات (SaaS). أُضيف إليه:

| الجزء | المسار | الغرض |
|------|--------|-------|
| طبقة REST API | `api/` | تخدم شاشتي مدير الحركة والتشغيل بمصادقة توكن للجوال |
| تطبيق Flutter | `flutter_app/` | تطبيق عربي يستهلك الـ API (دخول + لوحة حيّة + حركة وتشغيل + خريطة) |
| ترحيل DB | `database/migrations/2026_06_06_create_api_tokens.sql` | جدول `api_tokens` |

**مبدأ جوهري:** كل الإضافات معزولة — لم يُمسّ أي ملف في النظام الويب القائم.

---

## 2. طبقة الـ API (`api/`)

### البنية
```
api/
├── .htaccess              توجيه كل المسارات إلى index.php + تمرير ترويسة Authorization
├── index.php              المتحكّم الأمامي والموجّه (switch على المورد + الطريقة)
├── bootstrap.php          تحميل config.php + ردود JSON + قراءة المدخلات + مصادقة التوكن + العزل + معالجة الأخطاء
├── controllers/
│   ├── auth.php           POST /login · POST /logout · GET /me
│   ├── board.php          GET /board (مكافئ map_page.php)
│   ├── operations.php     GET/POST /operations · PUT /operations/{id} (مكافئ movement_operations.php)
│   ├── drivers.php        POST /equipment-drivers · PUT /equipment-drivers/{id} · GET /drivers/available
│   └── lists.php          GET /contracts · /suppliers · /equipment-types · /equipments
└── README.md              توثيق كل endpoint بالتفصيل
```

### المصادقة (Bearer Token بدل الجلسة/CSRF)
- `POST /api/login` يتحقق بـ bcrypt (نفس منطق `login.php`)، يصدر توكناً عشوائياً (64 hex)،
  يخزّن **تجزئته sha256** في `api_tokens`، ويعيد التوكن الخام مرّة واحدة + بيانات المستخدم + المشروع.
- كل طلب محمي يرسل `Authorization: Bearer <token>`.
- **الحيلة المفتاحية:** بعد التحقق من التوكن، يملأ `api_require_auth()` المتغيّر `$_SESSION['user']`
  ببيانات المستخدم، فتعمل **دوال الصلاحيات وعزل الشركة الأصلية كما هي دون تعديلها**
  (`check_page_permissions`, `db_table_has_column`, ...). هذا ما جعل إعادة الاستخدام نظيفة.

### الردّ الموحّد
```json
{ "success": bool, "message": "نص عربي", "data": mixed }
```
- `Content-Type: application/json; charset=utf-8` مع `JSON_UNESCAPED_UNICODE`.
- `api_respond()` يمسح مخزن الإخراج (`ob_end_clean`) لتفادي مرشّح mojibake في config.php.
- أكواد HTTP صحيحة: 400/401/403/404/405/409/422/500.

### العزل (Multi-tenant)
- كل endpoint مقيّد بشركة المستخدم (`company_id`) ومشروعه (`project_id` من حساب المستخدم).
- السوبر أدمن (`role = -1`) فقط يمكنه تمرير `?project_id=`.
- منطق `board` يطابق `map_page.php` حرفياً (التجميع حسب المورّد + ساعات التايم شيت)،
  و`operations` يطابق `movement_operations.php` (تقسيم نهار/ليل، القواعد الصارمة).

### القواعد الصارمة المطبّقة (مطابقة للشاشات)
1. لا تشغيل مزدوج لمعدة → 409.
2. سائق واحد لا يكون نشطاً على أكثر من تعيين → الإضافة تُنهي تعييناته السابقة تلقائياً.
3. التحرير للسجلّات السارية فقط ومع `can_edit`.
4. تواريخ `YYYY-MM-DD` والنهاية بعد البداية → 422. النهاية المفتوحة = `2099-12-31` (تُعرض «مستمر»).

### كيف تضيف endpoint جديداً
1. أضِف دالة في الـ controller المناسب (أو ملفاً جديداً في `controllers/` و`require` في index.php).
2. أضِف حالة في `switch ($resource)` داخل `index.php`.
3. استعمل `api_require_auth()`، `api_resolve_project_id()`، `api_fetch_project()`، `api_movement_perms()`،
   ودوال المدخلات `api_str/api_int/api_float/api_validate_date`، والردود `api_ok/api_fail`.
4. كل الكتابات **Prepared Statements**.

### التحقق
```bash
php -l api/**/*.php
curl http://localhost/ems/api/                 # {success:true,...}
curl -X POST http://localhost/ems/api/login -H "Content-Type: application/json" -d '{"username":"..","password":".."}'
curl http://localhost/ems/api/board -H "Authorization: Bearer <token>"
```

---

## 3. تطبيق Flutter (`flutter_app/`)

### المتطلبات والبنية
- Flutter 3.27 / Dart 3.6. إدارة الحالة: **Provider** (خفيف، كافٍ لـ 3 شاشات).
- التبعيات: `dio` · `provider` · `flutter_secure_storage` · `flutter_map` + `latlong2` (OSM بلا مفتاح) · `intl`.
```
lib/
├── main.dart            حقن التبعيات + RTL/التعريب + بوّابة المصادقة (splash→login→shell)
├── core/               config (base_url) · theme (الهوية) · api_client (Dio) · secure_storage · formatters
├── models/             user · board · operation · lookups
├── repositories/       auth · board · operations
├── providers/          AuthProvider · BoardProvider · OperationsProvider
├── widgets/            atoms · app_header · sheet_scaffold · form_fields
└── screens/            splash · login · home_shell · board · operations · map · sheets/
```

### عنوان الـ API (مهم جداً)
يُضبط وقت البناء عبر `--dart-define=API_BASE_URL=...` (افتراضه في `lib/core/config.dart`):
- **محاكي أندرويد:** `http://10.0.2.2/ems/api` (يشير إلى localhost المضيف).
- **جهاز حقيقي:** `http://<IP-الكمبيوتر-على-الشبكة>/ems/api` (مثال `http://192.168.1.9/ems/api`).
- **ويب على نفس الجهاز:** `http://localhost/ems/api`.

### الهوية البصرية (إيكويبيشن)
- ذهبي `#F3BE00` (علامة/خلفيات/أزرار) + أسود `#121212` (نصوص فوق الذهبي) + أبيض/رمادي.
- ذهبي داكن `#9F8500` للنصوص الذهبية المقروءة على الأبيض.
- دلالات: أخضر=عامل/ساري `#1F8A5B`، أحمر=متوقف `#C0392B`، رمادي=منتهٍ.
- معرّفة في `lib/core/theme.dart` (`AppColors`)، و`colorScheme.onPrimary = أسود`.
- الشعار في `assets/images/`: `logo.png` (ملوّن للخلفيات الفاتحة)، `icon.png` (علامة سوداء للشريط الذهبي).
  المصدر: `ems/assets/images/{logo.png, icon.png, logo 3.png(أبيض)}`.

### أوامر البناء/التشغيل
```bash
cd flutter_app
flutter pub get
flutter analyze          # يجب أن يكون نظيفاً
flutter test

# ويب (مستضاف على Apache في مسار فرعي):
flutter build web --release --base-href=/ems/flutter_app/build/web/ --dart-define=API_BASE_URL=http://localhost/ems/api
#   → يُفتح على: http://localhost/ems/flutter_app/build/web/

# APK للجوال:
flutter build apk --release --dart-define=API_BASE_URL=http://192.168.1.9/ems/api
#   → الناتج: build/app/outputs/flutter-apk/app-release.apk
```

> على Windows استخدم PowerShell لتمرير `--base-href=/...` لأن Git Bash يشوّه المسارات (MSYS).
> `& "C:\Users\<user>\flutter\bin\flutter.bat" build web ...`

### إعداد أندرويد (`android/app/src/main/AndroidManifest.xml`)
- `<uses-permission android:name="android.permission.INTERNET"/>`.
- `android:usesCleartextTraffic="true"` على `<application>` (لأن الـ API على HTTP لا HTTPS).
- `android:label="مدير الحركة والتشغيل"`.

---

## 4. الدروس والمزالق (مهمة لأي تطبيق لاحق)

1. **دمج Dio للـ baseUrl نصّي بحت** (`baseUrl + path` بلا فاصل). إن كان baseUrl بلا `/`
   نهائية والمسار بلا `/` بادئة ينتج `.../apilogin` بدل `.../api/login` → 404 HTML →
   «استجابة غير صالحة». الحل في `api_client.dart`: بناء رابط مطلق بدمج آمن (شرطة واحدة)
   وتمريره كـ URL كامل + فكّ JSON دفاعياً إن أعاد الجسم نصّاً.
2. **HTTP على أندرويد:** أندرويد 9+ يمنع cleartext افتراضياً في الإصدار release → لازم
   `usesCleartextTraffic="true"` + صلاحية `INTERNET`.
3. **عنوان IP مدمج في الـ APK:** إن تغيّر IP الكمبيوتر (DHCP) يتوقف الاتصال → أعِد بناء الـ APK
   أو ثبّت IP ثابتاً. الهاتف والكمبيوتر على نفس Wi‑Fi، و**جدار حماية ويندوز** يجب أن يسمح بالمنفذ 80.
4. **بناء الويب في مسار فرعي:** اضبط `--base-href` ليطابق مسار الخدمة على Apache تماماً (مع شرطة نهائية).
5. **Service Worker لتطبيق الويب:** بعد كل إعادة بناء، اعمل **Ctrl+Shift+R** (أو Unregister) لتفادي الكاش القديم.
6. **تراخيص أندرويد:** `yes | flutter doctor --android-licenses` لقبولها دفعة واحدة.
7. **استضافة الـ APK للتنزيل المباشر:** انسخه إلى جذر الموقع (`ems/`) فيُنزَّل عبر
   `http://<IP>/ems/<name>.apk` (Apache يخدمه بنوع `application/vnd.android.package-archive`).
8. **مرشّح mojibake في config.php:** يضيف ترويسة text/html ومخزن إخراج — لردّ JSON نظيف،
   امسح المخازن (`ob_end_clean`) واضبط `application/json` قبل الإخراج.

---

## 5. التثبيت على الهاتف (ملخّص)
1. الكمبيوتر يشغّل XAMPP (Apache + MySQL)، والهاتف على نفس Wi‑Fi.
2. اسمح بالمنفذ 80 في جدار حماية ويندوز.
3. نزّل `http://<IP>/ems/equipation-movement.apk` على الهاتف وثبّته (مصادر غير معروفة).
4. سجّل الدخول بحساب EMS حقيقي؛ يُحمّل المشروع تلقائياً.
