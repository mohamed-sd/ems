<?php
/**
 * 2028_06_13 — حذفُ جدولَي إعادةِ كلمةِ المرورِ للوحةِ المنصّةِ المُلغاة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **السند**: لوحةُ `admin/` حُذفت نهائيًّا في `9d46779b` (2026-09-07) —
 *   33 ملفًّا · 11,639 سطرًا — **وأكّد المالكُ أنّ الحذفَ نهائيّ**.
 *   وهذان الجدولان كانا سطحَي `admin/forgot_password.php` و
 *   `admin/reset_password.php` حصرًا، وقد ذهبا معها.
 *
 * ◆ **والقياسُ قبلَ الفعلِ لكلٍّ منهما**:
 *     صفوف=0 · مفاتيحُ واردة=0 · قوادح=0 · مَشاهد=0
 *     وصفرُ تعاملٍ في المنتجِ **وفي العُدّةِ** (مسحُ 3,571 ملفًّا).
 *
 * ⛔ **و`super_admins` لم يُحذَف — وله سببٌ مسمًّى**: فيه صفٌّ حيٌّ
 *   (`#1 super · admin@enjaz.com`) و**ثلاثةُ مفاتيحَ واردة**، اثنان منها من
 *   جدولَين **حيَّين يكتب فيهما المنتج**: `admin_audit_log` (‏104 صفًّا تشير
 *   إلى `admin_id=1`) و`admin_subscription_requests`. وحذفُه يوجب إسقاطَ
 *   قيدَين على جدولَين حيَّين وتيتيمَ 104 من صفوفِ أثر — **قرارٌ يخصُّ
 *   المالكَ لا هجرةَ تنظيف**.
 *
 * ⛔ **وخمسةٌ من عائلةِ المنصّةِ حيّةٌ ولا تُمَسّ** (قِيست، ولم تمت بموتِ
 *   اللوحة): `admin_companies` (‏المثبِّتُ يكتبها و`api/bootstrap.php` يقرؤها)
 *   · `admin_audit_log` (`company/request_subscription.php`) ·
 *   `admin_subscription_requests` · `admin_subscription_plans` ·
 *   **`api_tokens`** (‏مصادقةُ تطبيقِ الجوّال — `api/bootstrap.php`).
 *
 * ◆ **والعكسُ يعيدهما ببنيتِهما حرفًا** — و`_down` يحمل `SHOW CREATE` المقيس.
 * التشغيل: php database/migrate.php up
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__, 2) . '/includes/env.php';

$conn = new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'),
                   ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($conn->connect_errno) { fwrite(STDERR, "اتصال المرحِّل فشل\n"); exit(1); }
$conn->set_charset('utf8mb4');
$DB  = ems_env('DB_NAME');
$one = function ($sql) use ($conn) { $r = $conn->query($sql); $x = $r ? $r->fetch_row() : null; return $x ? $x[0] : null; };

$TARGETS = array('super_admin_password_resets', 'company_user_password_resets');

echo "── حذفُ جدولَي إعادةِ كلمةِ المرورِ للوحةِ المُلغاة ──\n";
$dropped = 0;
foreach ($TARGETS as $t) {
    if ((int) $one("SELECT COUNT(*) FROM information_schema.TABLES
                     WHERE TABLE_SCHEMA='{$DB}' AND TABLE_NAME='{$t}'") === 0) {
        echo "  {$t}: محذوفٌ سلفًا\n"; continue;
    }
    /* ⛔ **الفحصُ يُعاد لحظةَ الفعلِ لا يُنقَل من تقرير** */
    $rows = (int) $one("SELECT COUNT(*) FROM `{$t}`");
    $inFk = (int) $one("SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
                         WHERE CONSTRAINT_SCHEMA='{$DB}' AND REFERENCED_TABLE_NAME='{$t}'");
    $trg  = (int) $one("SELECT COUNT(*) FROM information_schema.TRIGGERS
                         WHERE TRIGGER_SCHEMA='{$DB}' AND EVENT_OBJECT_TABLE='{$t}'");
    $vw   = (int) $one("SELECT COUNT(*) FROM information_schema.VIEWS
                         WHERE TABLE_SCHEMA='{$DB}' AND VIEW_DEFINITION LIKE '%{$t}%'");
    echo "  {$t}: صفوف={$rows} · واردة={$inFk} · قوادح={$trg} · مَشاهد={$vw}\n";
    if ($rows !== 0 || $inFk !== 0 || $trg !== 0 || $vw !== 0) {
        fwrite(STDERR, "  ✘ {$t} فيه مانعٌ — أوقفتُ حذفَه\n"); exit(1);
    }
    if (!$conn->query("DROP TABLE `{$t}`")) {
        fwrite(STDERR, "  ✘ حذفُ {$t} فشل: {$conn->error}\n"); exit(1);
    }
    echo "  ✔ حُذف {$t}\n";
    $dropped++;
}

$tot = (int) $one("SELECT COUNT(*) FROM information_schema.TABLES
                    WHERE TABLE_SCHEMA='{$DB}' AND TABLE_TYPE='BASE TABLE'");
echo "  المحذوف={$dropped} · الجداولُ الآن {$tot}\n";

/* ⛔ **وحارسٌ يمنع مفاجأةً**: الخمسةُ الحيّةُ يجب أن تبقى موجودةً */
foreach (array('admin_companies','admin_audit_log','admin_subscription_requests',
               'admin_subscription_plans','api_tokens','super_admins') as $keep) {
    if ((int) $one("SELECT COUNT(*) FROM information_schema.TABLES
                     WHERE TABLE_SCHEMA='{$DB}' AND TABLE_NAME='{$keep}'") !== 1) {
        fwrite(STDERR, "  ✘ الجدولُ الحيُّ {$keep} مفقود — راجِعْ\n"); exit(1);
    }
}
echo "  ✔ الستّةُ الباقيةُ من عائلةِ المنصّةِ سليمة\n";
exit(0);
