# Encoding Standards & Best Practices
# معايير التكويد وأفضل الممارسات

## عام / General

**الهدف:** ضمان عدم حدوث مشاكل التكويد والأسطر النهاية المختلطة في المشروع أبداً.  
**Goal:** Ensure zero encoding issues in the project going forward.

---

## معايير التكويد المطلوبة / Required Encoding Standards

### PHP Files
- **Encoding:** UTF-8 without BOM
- **Line Ending:** LF (Unix style) - NOT CRLF
- **File Header:** Must start with `<?php` with NO BOM
- **Indentation:** 4 spaces (never tabs)

```php
<?php
// Correct - starts with <?php, no BOM
namespace App;

class MyClass {
    public function myMethod() {
        // 4 spaces indentation
    }
}
```

### JavaScript Files
- **Encoding:** UTF-8 without BOM
- **Line Ending:** LF
- **Indentation:** 4 spaces

### CSS Files
- **Encoding:** UTF-8 without BOM
- **Line Ending:** LF
- **Indentation:** 4 spaces

### JSON Files
- **Encoding:** UTF-8 without BOM
- **Line Ending:** LF
- **Indentation:** 2 spaces

---

## Editor Configuration / إعدادات المحرر

### VS Code (Recommended)

**Step 1:** Install EditorConfig extension
- Install "EditorConfig for VS Code" by EditorConfig

**Step 2:** Configure settings.json
```json
{
    "files.encoding": "utf8",
    "files.endOfLine": "lf",
    "editor.insertSpaces": true,
    "editor.tabSize": 4,
    "[php]": {
        "editor.tabSize": 4,
        "editor.insertSpaces": true
    },
    "[javascript]": {
        "editor.tabSize": 4,
        "editor.insertSpaces": true
    },
    "[json]": {
        "editor.tabSize": 2,
        "editor.insertSpaces": true
    }
}
```

**Step 3:** Verify settings
- Open any PHP file
- Check bottom right: shows "CRLF" or "LF" and "UTF-8"
- Should show: "LF" and "UTF-8" (not "UTF-8 with BOM")

### PhpStorm/IntelliJ

**Settings > Editor > Code Style:**
- Line separator: Unix and macOS (\n)
- File encoding: UTF-8 without BOM

**Settings > Editor > File Encodings:**
- Global encoding: UTF-8
- Project encoding: UTF-8

### Other Editors
- Set file encoding to UTF-8 without BOM
- Set line endings to LF
- Set indentation to 4 spaces for code files

---

## Git Configuration / إعدادات Git

### Auto-normalize Line Endings
```bash
git config core.autocrlf false
git config core.safecrlf true
```

### Commit Line Endings Normalization
If you accidentally committed files with CRLF:
```bash
git add -A
git diff --cached -o /tmp/changes.patch
git checkout HEAD -- .
git apply /tmp/changes.patch
git add -A
git commit -m "Normalize line endings"
```

---

## Maintenance Scripts / السكريبتات المتاحة

### Full Encoding Audit & Fix
```bash
powershell -NoProfile -ExecutionPolicy Bypass -File "./scripts/encoding-audit-fix.ps1"
```

### Check Before Commit
The Git pre-commit hook runs automatically:
- Checks for UTF-8 BOM
- Checks for mixed line endings
- Rejects commit if issues found

To run manually:
```bash
.git\hooks\pre-commit.ps1
```

---

## Prevention Checklist / قائمة الوقاية

### Before Starting Work
- [ ] Set editor encoding to UTF-8 without BOM
- [ ] Set editor line ending to LF
- [ ] Verify .editorconfig is loaded in your editor
- [ ] Configure editor indentation to 4 spaces

### While Editing Files
- [ ] Don't mix tabs and spaces
- [ ] Don't use Windows PowerShell for file editing
- [ ] Use your code editor for all changes
- [ ] Avoid "Save As" with different encoding

### Before Committing
- [ ] Check git status
- [ ] Review line endings (should be LF only)
- [ ] Pre-commit hook will verify encoding
- [ ] If issues found, run the fix script

### After Committing
- [ ] Monitor CI/CD logs for encoding issues
- [ ] Report any encoding issues immediately

---

## Common Mistakes to AVOID / الأخطاء الشائعة الواجب تجنبها

### ❌ DON'T DO THIS

```bash
# PowerShell command that may add BOM
Get-Content file.php | Out-File file.php -Encoding UTF8

# Command that may change line endings
(Get-Content file.php) | Set-Content file.php

# Manual copy-paste without encoding check
```

### ✅ DO THIS INSTEAD

```bash
# Use the provided fix script
powershell -File "./scripts/encoding-audit-fix.ps1"

# Use your code editor for all changes
# Use git for version control
```

---

## Troubleshooting / استكشاف الأخطاء

### File Shows "UTF-8 with BOM" in Editor
1. Open file in VS Code
2. Click "UTF-8 with BOM" in bottom right
3. Select "UTF-8" (without BOM)
4. Press Enter and confirm
5. File should now be UTF-8 without BOM

### Mixed Line Endings Warning
1. Click "CRLF" or "CR" in bottom right
2. Select "LF"
3. Save file
4. Git will show as modified (this is correct)

### Pre-commit Hook Rejection
If commit is rejected due to encoding:
1. Run the fix script: `powershell -File "./scripts/encoding-audit-fix.ps1"`
2. Review changes: `git diff`
3. Stage changes: `git add -A`
4. Commit again: `git commit -m "Your message"`

---

## Team Guidelines / إرشادات الفريق

1. **Everyone must use VS Code or equivalent** with EditorConfig support
2. **Never use Notepad** to edit code files
3. **Never use Windows PowerShell** to modify files
4. **Always use the fix script** if encoding issues occur
5. **Report immediately** any encoding problems
6. **Review this document** before working on the project

---

## Files Involved / الملفات المتعلقة

- `.editorconfig` - Unified editor configuration
- `.gitattributes` - Git attributes for encoding/line endings
- `.git/hooks/pre-commit` - Pre-commit validation hook
- `.git/hooks/pre-commit.ps1` - Windows version of hook
- `scripts/encoding-audit-fix.ps1` - Full audit and fix script

---

## Monitoring / المراقبة

### Automated Checks
- ✅ Pre-commit hook runs automatically
- ✅ EditorConfig enforces standards
- ✅ Git attributes normalize on merge
- ✅ Weekly encoding audit (scheduled)

### Manual Checks
```bash
# Run anytime to check all files
powershell -File "./scripts/encoding-audit-fix.ps1"
```

---

## Contact / التواصل

If you encounter encoding issues:
1. Don't panic - run the fix script
2. Document the issue
3. Report to team lead
4. Review this document

---

**Last Updated:** $(date)  
**Version:** 1.0  
**Encoding:** UTF-8 without BOM  
**Line Endings:** LF
