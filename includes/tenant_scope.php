<?php
/**
 * includes/tenant_scope.php — نطاقُ الكيانِ من السياقِ لا من رقمٍ في الشيفرة
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ INJ-0203 · INJ-0408 · INJ-0425 · INJ-0579
 *
 * ── العلّة ────────────────────────────────────────────────────────────────
 * **أربعةٌ وعشرون سطحًا** تحمل احتياطيًّا مرمَّزًا للشركةِ رقم ٤:
 *     `$co = $company_id ?: 4;`
 *     `if ($company_id <= 0) { $company_id = 4; }`
 * وهي قيمةٌ من بيئةِ التطويرِ بقيت في الإنتاج. فحسابٌ بلا كيانٍ صالح — سوبر
 * أو حسابٌ ناقصٌ — **تُنسب أعمالُه إلى الكيانِ ٤ حتمًا**: يقرأ بياناتِه، وفي
 * شاشاتِ الإنشاءِ **يكتب فيه**.
 *
 * ── والقاعدةُ التي تحكم ──────────────────────────────────────────────────
 * «عزلُ الكيانات بنيويّ: يُشتقُّ النطاقُ من سياقِ الجلسةِ لا من قيمةٍ ثابتة،
 * وتُغلق البوابةُ مغلقةً عند غيابِ السياق» (ADR-02).
 * و`App\Core\TenantContext` **مبنيةٌ وتُرجع صفرًا عند الغياب** — والأسطحُ
 * تتجاوزها إلى الرقمِ الصلب. فالعيبُ تبنٍّ لا بناء.
 *
 * ── ولماذا ثلاثُ دوالَّ لا واحدة ─────────────────────────────────────────
 * لأنَّ الشاشاتِ صنفان، ونصُّ القبولِ يفرّق بينهما:
 *   • **كاتبةٌ** ⇒ «حسابٌ بلا شركةٍ صالحةٍ **لا يستطيع فتحَ الشاشة**» ⇒
 *     `ems_require_company()` تُغلق مغلقًا.
 *   • **قارئةٌ للسوبر** ⇒ «السوبر بلا كيانٍ **يرى منتقيَ كيانٍ لا أرقامًا**» ⇒
 *     `ems_scope_company()` تُرجع صفرًا، و`ems_company_picker()` تعرض المنتقي.
 *
 * ◆ والاختيارُ **يُتحقَّق من `admin_companies`** ولا يُصدَّق من العنوان: رقمٌ في
 *   `?co=` لا يصير نطاقًا حتى يُثبَت أنَّه كيانٌ قائم — وإلا كان البابُ مفتوحًا
 *   بمُعامَل.
 * ◆ وغيرُ السوبرِ **لا يختار**: نطاقُه جلستُه وحدَها مهما كتب في العنوان.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (!function_exists('ems_scope_is_super')) {
    /** السوبرُ بدورِه `-1` — والمقارنةُ نصيةٌ كما في بقيةِ النظام. */
    function ems_scope_is_super()
    {
        if (!empty($_SESSION['user']['is_super_admin'])) { return true; }
        return strval($_SESSION['user']['role'] ?? '') === '-1';
    }
}

if (!function_exists('ems_scope_company')) {
    /**
     * نطاقُ الكيانِ للقراءة. **يُرجع صفرًا عند الغياب — ولا يخترع رقمًا.**
     *
     * وللسوبرِ وحدَه: يجوز أن يختار كيانًا صراحةً بـ`?co=`، فيُثبَّت في الجلسة
     * ليصمد عبر الصفحات — بعد التحقق من وجودِه في `admin_companies`.
     *
     * @param mysqli|null $conn للتحققِ من الكيانِ المختار (لا اختيارَ بلا تحقق)
     * @return int معرِّفُ الكيانِ أو 0 إن لم يوجد سياقٌ صالح
     */
    function ems_scope_company($conn = null)
    {
        $own = intval($_SESSION['user']['company_id'] ?? 0);
        if ($own > 0) { return $own; }          // نطاقُ المستخدمِ جلستُه — لا يُتجاوز
        if (!ems_scope_is_super()) { return 0; } // وغيرُ السوبرِ لا يختار

        $pick = isset($_GET['co']) ? intval($_GET['co']) : 0;
        if ($pick > 0 && $conn instanceof mysqli) {
            $st = $conn->prepare('SELECT id FROM admin_companies WHERE id = ? LIMIT 1');
            if ($st) {
                $st->bind_param('i', $pick);
                $st->execute();
                $found = (bool) $st->get_result()->fetch_row();
                $st->close();
                if ($found) { $_SESSION['ems_scope_co'] = $pick; return $pick; }
            }
            return 0;   // رقمٌ في العنوانِ لا يقابله كيانٌ ⇒ لا نطاق
        }
        return intval($_SESSION['ems_scope_co'] ?? 0);
    }
}

if (!function_exists('ems_require_company')) {
    /**
     * نطاقُ الكيانِ للكتابة — **فيُغلق مغلقًا عند غيابِه**.
     * تُنادى في الشاشاتِ التي تُنشئ مستندًا: «حسابٌ بلا شركةٍ صالحةٍ لا يستطيع
     * فتحَ الشاشة ولا إنشاءَ عملية» (INJ-0203).
     *
     * @return int معرِّفُ الكيان (لا يعود إلا بقيمةٍ موجبة)
     */
    function ems_require_company($conn = null, $redirect = '../main/dashboard.php')
    {
        $co = ems_scope_company($conn);
        if ($co > 0) { return $co; }
        require_once __DIR__ . '/permissions_helper.php';
        ems_gov_flash_redirect($redirect,
            'لا كيانَ في سياقِ جلستك — ولا تُكتب بيانةٌ بكيانٍ مفترَض ❌',
            'GOV-TENANT-409',
            ems_scope_is_super()
                ? 'اختر الكيانَ صراحةً من منتقي الكياناتِ ثم أعِدِ المحاولة'
                : 'راجعْ مديرَ الصلاحيات: حسابُك بلا كيانٍ مالك');
        exit();
    }
}

if (!function_exists('ems_company_picker')) {
    /**
     * منتقي الكيانِ للسوبرِ بلا كيان — «يرى منتقيَ كيانٍ لا أرقامًا».
     * يُصيَّر مكانَ الجدولِ فلا تُعرض أعدادُ كيانٍ لم يُختَر.
     * @return string HTML جاهزٌ للطباعة، أو '' إن كان ثمَّ نطاقٌ صالح
     */
    function ems_company_picker($conn, $co)
    {
        if ((int) $co > 0 || !ems_scope_is_super()) { return ''; }
        $rows = array();
        $r = $conn instanceof mysqli
            ? $conn->query('SELECT id, company_name FROM admin_companies ORDER BY company_name')
            : false;
        while ($r && ($x = $r->fetch_assoc())) { $rows[] = $x; }
        $self = htmlspecialchars(strtok((string) ($_SERVER['REQUEST_URI'] ?? ''), '?'), ENT_QUOTES, 'UTF-8');
        $h  = '<div class="alert alert-warning" style="max-width:640px">';
        $h .= '<strong>اختر الكيانَ أولًا.</strong> حسابُك بلا كيانٍ مالك، ';
        $h .= 'ولا تُعرض أرقامُ كيانٍ لم تختره — فرقمٌ بلا كيانٍ مُعلَنٍ يُقرأ خطأً.';
        $h .= '<form method="get" action="' . $self . '" style="margin-top:10px;display:flex;gap:8px;align-items:center">';
        $h .= '<label for="ems_co_pick">الكيان</label>';
        $h .= '<select id="ems_co_pick" name="co" class="form-control" style="max-width:280px" required>';
        $h .= '<option value="">— اختر —</option>';
        foreach ($rows as $x) {
            $h .= '<option value="' . (int) $x['id'] . '">'
                . htmlspecialchars((string) $x['company_name'], ENT_QUOTES, 'UTF-8') . '</option>';
        }
        $h .= '</select><button class="btn btn-primary" type="submit">اعرض</button></form></div>';
        return $h;
    }
}
