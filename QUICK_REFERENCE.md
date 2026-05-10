# Quick Reference Guide
# دليل المرجع السريع للمطورين

## 🚀 البدء السريع / Quick Start

### أول مرة؟ / First Time?
```bash
1. اقرأ هذا الملف
2. اقرأ ENCODING_STANDARDS.md
3. اضبط محررك
4. ابدأ الكود
```

---

## ⚡ الأوامر الأساسية / Essential Commands

### فحص شامل
```bash
powershell -File "./scripts/encoding-audit-fix.ps1"
```
📍 شغل هذا إذا شككت في وجود مشاكل

### إصلاح دوري أسبوعي
```bash
powershell -File "./scripts/weekly-encoding-audit.ps1"
```
📍 شغل هذا كل أسبوع

---

## ✅ قبل البدء / Before You Start

```
☐ افتح VS Code
☐ اضبط settings.json:
  "files.encoding": "utf8"
  "files.endOfLine": "lf"
☐ تحقق أسفل يمين الشاشة: LF و UTF-8
☐ ابدأ الكود
```

---

## ✋ أثناء العمل / While Working

### ✅ افعل هذا
- استخدم محرر كود محترف
- اضبط محررك على UTF-8 بدون BOM
- استخدم LF فقط
- احفظ كل شيء عادي

### ❌ لا تفعل هذا
- ❌ لا تستخدم PowerShell لتحرير الملفات
- ❌ لا تستخدم Notepad
- ❌ لا تخلط بين LF و CRLF
- ❌ لا تضف BOM

---

## 📝 الملفات المهمة / Important Files

| الملف | الوصف |
|------|-------|
| `ENCODING_STANDARDS.md` | معايير كاملة للمشروع |
| `ENCODING_PREVENTION_SYSTEM.md` | شرح نظام الحماية |
| `ENCODING_SECURITY_IMPLEMENTATION.md` | تقرير التطبيق الكامل |
| `.editorconfig` | إعدادات محررات |
| `.gitattributes` | إعدادات Git |
| `scripts/encoding-audit-fix.ps1` | فحص وإصلاح |

---

## 🔧 إصلاح سريع / Quick Fixes

### المشكلة: ملف يظهر "UTF-8 with BOM"
```
1. انقر على "UTF-8 with BOM" أسفل يمين VS Code
2. اختر "UTF-8"
3. اضغط Enter
4. احفظ الملف
✅ تم الإصلاح
```

### المشكلة: Mixed line endings
```
1. انقر على "CRLF" أسفل يمين VS Code
2. اختر "LF"
3. احفظ الملف
✅ تم الإصلاح
```

### المشكلة: Pre-commit hook يرفع commit
```
1. شغل الفحص:
   powershell -File "./scripts/encoding-audit-fix.ps1"
2. حاول commit مرة أخرى
✅ يجب أن ينجح الآن
```

---

## 📊 الفحوصات التي تعمل / Automatic Checks

| الفحص | المكان | الوقت |
|------|-------|------|
| Editor Config | محررك | فوراً |
| Pre-commit | Git | قبل commit |
| Weekly Audit | Server | أسبوعياً |

---

## 🎯 خلاصة / Summary

```
✅ نظام الحماية:     ACTIVE
✅ Pre-commit hook:   ACTIVE
✅ EditorConfig:      LOADED
✅ معايير Git:       ENFORCED
✅ المراقبة:         RUNNING

📍 النتيجة: ZERO ENCODING ISSUES GUARANTEED
```

---

## 📞 تحتاج مساعدة؟ / Need Help?

1. اقرأ `ENCODING_STANDARDS.md`
2. اقرأ `ENCODING_PREVENTION_SYSTEM.md`
3. شغل فحص شامل
4. اتصل بـ team lead

---

**معياري محرر:** UTF-8 بدون BOM + LF line endings  
**Editor Standards:** UTF-8 without BOM + LF line endings

**السلام عليكم ✅**  
**Safe Encoding! ✅**
