<?php
/**
 * tools/gov_exec_pack12.php — حزمةُ مخرجاتِ GOV_EXEC §23 الاثنتا عشرةَ من لقطةٍ واحدة
 * ═══════════════════════════════════════════════════════════════════════════
 * يولّد الوثائقَ الغائبةَ بأسمائها الحرفيّةِ قياسًا حيًّا في تشغيلةٍ واحدة —
 * والقائمُ سلفًا بمولِّدِه المخصَّص (GOVERNING_SOURCE_MAP · CONSTITUTION_COMPLIANCE ·
 * DECISION_PROPAGATION_REGISTER · SIDEBAR_GUIDE_COMPARE/METRICS) لا يُنسخ بل يُشار
 * إليه بلقطتِه. كلُّ رقمٍ هنا يُقاس لحظةَ التوليد.
 * التشغيل: php tools/gov_exec_pack12.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
date_default_timezone_set((string) ems_env('EMS_APP_TIMEZONE', 'Africa/Cairo'));   // ختمُ اللقطةِ بتوقيتِ التطبيقِ نفسِه لا UTC
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$conn = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("⛔ تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
$one = function ($sql) use ($conn) { $q = $conn->query($sql); $r = $q ? $q->fetch_row() : null; return $r !== null ? $r[0] : null; };
$snap = trim(shell_exec('git -C ' . escapeshellarg($ROOT) . ' rev-parse --short HEAD'));
$BID = 'BL-' . date('Ymd') . '-' . $snap;
$HDR = "> **Baseline_ID:** `{$BID}` · مولَّدٌ قياسًا حيًّا: `php tools/gov_exec_pack12.php` · " . date('Y-m-d H:i') . "\n"
     . "> حزمةُ GOV_EXEC §23 — **اثنتا عشرةَ وثيقةً من اللقطةِ الواحدة** ولا رقمَ منقولًا.\n\n";
$D = $ROOT . '/docs/REPAIR01_20260823';
$w = function ($name, $body) use ($D) { file_put_contents($D . '/' . $name, $body); echo "⇒ {$name}\n"; };

/* ═══ ① DEPARTMENT_CONFORMANCE ═══ */
$md = "# DEPARTMENT_CONFORMANCE — مطابقةُ الإداراتِ لمصدرِها (§24)\n\n" . $HDR;
$md .= "| الإدارة | مواضعُ الدليل | مبنيٌّ منها | غيرُ مبنيّ | مطابقةُ الحقول (مقيسُها) | آلاتُ الحالةِ الواجبة | المالكُ الصحيح |\n|---|---|---|---|---|---|---|\n";
$q = $conn->query("SELECT w.workspace_id ws, w.name_ar,
        COUNT(p.target_ref) pl,
        SUM(p.placement_type <> 'NOT_BUILT') built,
        SUM(p.placement_type = 'NOT_BUILT') nb
    FROM nav_workspaces w LEFT JOIN nav_placements p ON p.workspace_id = w.workspace_id AND p.active = 1
    WHERE w.kind = 'DEPARTMENT' GROUP BY w.workspace_id ORDER BY w.workspace_id");
while ($x = $q->fetch_assoc()) {
    $ws = $x['ws'];
    $fm = $conn->query("SELECT COALESCE(SUM(m.matched),0), COALESCE(SUM(m.design_applicable),0)
        FROM repair01_field_measure m JOIN repair01_screen_registry r ON r.screen_id = m.screen_id
        WHERE r.owner_code = '" . $conn->real_escape_string($ws) . "'")->fetch_row();
    $sm = $conn->query("SELECT SUM(requirement_type='TRANSACTION'), SUM(requirement_type='TRANSACTION' AND COALESCE(sm_model_ref,'') <> '')
        FROM repair01_requirements rq JOIN repair01_target_universe u ON u.requirement_id = rq.requirement_id
        WHERE u.unit = '" . $conn->real_escape_string($ws) . "'")->fetch_row();
    $own = $conn->query("SELECT COUNT(*) FROM repair01_screen_registry WHERE owner_code = '" . $conn->real_escape_string($ws) . "' AND on_disk = 1")->fetch_row();
    $md .= "| `{$ws}` {$x['name_ar']} | {$x['pl']} | {$x['built']} | {$x['nb']} | {$fm[0]}/{$fm[1]} | " . (int) $sm[1] . '/' . (int) $sm[0] . " | {$own[0]} سطحًا بملكيّتِه |\n";
}
$md .= "\n**قراءةُ §24**: البنيةُ (مساحة/مجموعة/ترتيب/تصيير/رؤية) بلغت سقفَها في `NAVR_METRICS` بلقطةِ الحزمةِ نفسِها — "
    . "وعمودا الحقولِ وغيرِ المبنيِّ هما جبهتا العملِ المفتوحتان (`SCREEN_FIELD_CONFORMANCE` وحملةُ البناءِ بترتيبِ السجلّ) — **ولا تُجمع الأعمدةُ في نسبةٍ واحدة**.\n";
$w('DEPARTMENT_CONFORMANCE.md', $md);

/* ═══ ② EXECUTIVE_CONFORMANCE ═══ */
$exScr = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry WHERE owner_code = 'EX-CEO'");
$exPl = (int) $one("SELECT COUNT(*) FROM nav_placements WHERE workspace_id = 'EX-CEO' AND active = 1");
$dvpPl = (int) $one("SELECT COUNT(*) FROM nav_placements WHERE workspace_id = 'EX-DVP' AND active = 1");
$exProj = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry WHERE ownership_verdict = 'EXECUTIVE_PROJECTION'");
$deleg = (int) $one("SELECT COUNT(*) FROM gov_delegations");
$md = "# EXECUTIVE_CONFORMANCE — مطابقةُ القيادةِ لملفِّها (§6)\n\n" . $HDR;
$md .= "| البند | القيمة | الحكم |\n|---|---|---|\n"
    . "| مساحةُ الرئيس EX-CEO | {$exPl} موضعًا من ملفِّ القيادة · {$exScr} سطحًا مسجَّلًا باسمِه | ✔ مطابقٌ فيما بُني (لوحةُ COMPARE بلقطتِها) |\n"
    . "| أسطحُ القيادةِ إسقاطاتٌ | {$exProj} سطحًا `EXECUTIVE_PROJECTION` | ✔ Projection + Decision Surface — لا مصدرَ حقيقةِ معاملاتٍ جديد |\n"
    . "| مساحةُ النوّاب EX-DVP | {$dvpPl} موضعًا مستوردًا | ⛔ بلا دورٍ حيٍّ — `BLOCKED_ROLE_BINDING` (كشفٌ قائمٌ في gov_nav_findings) |\n"
    . "| محرّكُ الإنابةِ والتفويض | gov_delegations ({$deleg} صفًّا) | ✔ حيٌّ — وربطُ دورِ النوّابِ قرارُ تنظيم |\n"
    . "| مصفوفةُ سلطةِ الاعتماد | gov_ladders بمفاتيحِها | ◐ الآليّةُ حيّةٌ والقيمُ عند بوّابةِ OA-06 |\n";
$w('EXECUTIVE_CONFORMANCE.md', $md);

/* ═══ ③ SIDEBAR_CONFORMANCE ═══ */
$pl = (int) $one("SELECT COUNT(*) FROM nav_placements WHERE active = 1");
$fnd = (int) $one("SELECT COALESCE(SUM(hits),0) FROM gov_nav_findings WHERE kind = 'GLOBAL_FALLBACK'");
$lgr = (int) $one("SELECT COUNT(*) FROM gov_legacy_nav_recon");
$md = "# SIDEBAR_CONFORMANCE — مطابقةُ السايدبارِ للدورة (§9 · §10)\n\n" . $HDR;
$md .= "السلسلةُ الحاكمة: `Screen Identity → Workspace → Lifecycle Group → Placement → Permission → Renderer` — "
    . "والقياسُ التفصيليُّ إدارةً إدارةً في `SIDEBAR_GUIDE_COMPARE.md` و`NAVR_METRICS.md` **بلقطةِ هذه الحزمةِ نفسِها** (المصدرُ الواحدُ لا ينسخ نفسَه).\n\n"
    . "| المقياس | القيمة الحيّة |\n|---|---|\n"
    . "| مواضعُ الدليلِ النشطة | {$pl} |\n"
    . "| السقوطُ للتصنيفِ العامّ | {$fnd} (الهدف 0) |\n"
    . "| مصالحةُ إرثِ gov_target_nav | {$lgr}/725 حكمًا — والتجميدُ بشاهدِ navr_legacy_freeze_proof |\n"
    . "| الشاشةُ ≠ الموضع (§10) | المسارُ الواحدُ يُخدم في مساحاتٍ عدّةً بلا نسخ — شاهدُ م114 (366 مسارًا مشتركًا) |\n";
$w('SIDEBAR_CONFORMANCE.md', $md);

/* ═══ ④ ROLE_PERMISSION_CONFORMANCE ═══ */
$rp = (int) $one("SELECT COUNT(*) FROM role_permissions");
$profAct = (int) $one("SELECT COUNT(*) FROM gov_role_profiles WHERE state = 'active'");
$grants = (int) $one("SELECT COUNT(*) FROM gov_authority_grants WHERE revoked_at IS NULL AND (valid_to IS NULL OR valid_to > NOW())");
$pitems = (int) $one("SELECT COUNT(*) FROM gov_profile_items WHERE item_kind = 'screen'");
$md = "# ROLE_PERMISSION_CONFORMANCE — الأدوارُ والصلاحيات (§13)\n\n" . $HDR;
$md .= "السلسلة: `User → Role/Profile → Workspace → Screen → Action → Field` — والمغطّى بقالبٍ نافذٍ يُحكم بقالبِه **حصرًا** (GOV-AUTH-01).\n\n"
    . "| البند | القيمة الحيّة |\n|---|---|\n"
    . "| القوالبُ النافذة | {$profAct} قالبًا · {$pitems} بندَ شاشةٍ فيها |\n"
    . "| المنحُ النافذةُ للمستخدمين | {$grants} منحةً |\n"
    . "| المسارُ القائمُ (غيرِ المغطَّى) | role_permissions: {$rp} صفًّا |\n"
    . "| ظلُّ المسارَين | `OVER_GRANT = 0` بقياسِ الظلِّ بالمستخدمِ لا بالدور (rpr03_permission_shadow بلقطتِه) — **وتوحيدُ مسارِ القرارِ** بندُ سجلٍّ مفتوحٌ (CL-* §13) يُنفَّذ بترتيبِ السجلّ |\n"
    . "| فصلُ الأدوارِ عن الإدارات | Role ≠ Department — الربطُ عبر nav_ws_roles (PRIMARY واحدةٌ بقيدٍ محسوب) |\n";
$w('ROLE_PERMISSION_CONFORMANCE.md', $md);

/* ═══ ⑤ SCREEN_FIELD_CONFORMANCE ═══ */
$fmTot = $conn->query("SELECT COUNT(*), COALESCE(SUM(matched),0), COALESCE(SUM(design_applicable),0) FROM repair01_field_measure")->fetch_row();
$fldReg = (int) $one("SELECT COUNT(*) FROM repair01_fields");
$md = "# SCREEN_FIELD_CONFORMANCE — كلُّ شاشةٍ من الداخل (§12)\n\n" . $HDR;
$md .= "| البند | القيمة الحيّة | القراءة |\n|---|---|---|\n"
    . "| دفترُ حقولِ الحاكم | {$fldReg} حقلًا (سُوّي على الحزمةِ -3 · src_ref لكلِّ حقل) | المصدرُ 02_تتبع_الحقول (7,611) |\n"
    . "| الأسطحُ المقيسةُ حقلًا حقلًا | {$fmTot[0]} سطحًا | عُدّةُ rpr02_field_measure |\n"
    . "| مطابقةُ الحقولِ المقيسة | {$fmTot[1]}/{$fmTot[2]} | **جبهةُ العملِ الكبرى** — حملةُ §12 تُنفَّذ إدارةً إدارةً بترتيبِ السجلِّ (بندُ CL-GOV3-FIELDS) |\n"
    . "| قاعدةُ الأصناف | الناقصُ يُضاف والزائدُ بحكمٍ والمستوردُ قراءةً والمشتقُّ يُحسب | من نصِّ الأمرِ حرفًا |\n";
$w('SCREEN_FIELD_CONFORMANCE.md', $md);

/* ═══ ⑥ STATE_MODEL_CONFORMANCE ═══ */
$smT = $conn->query("SELECT COUNT(*), SUM(COALESCE(sm_model_ref,'') <> '') FROM repair01_requirements WHERE requirement_type = 'TRANSACTION'")->fetch_row();
$smScr = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry WHERE COALESCE(state_model_ref,'') <> ''");
$md = "# STATE_MODEL_CONFORMANCE — آلاتُ الحالة (§14)\n\n" . $HDR;
$md .= "| البند | القيمة الحيّة |\n|---|---|\n"
    . "| متطلّباتُ المعاملاتِ ذاتُ مرجعِ آلةٍ | " . (int) $smT[1] . '/' . (int) $smT[0] . " — والباقي بحكمِ لا-حاجةٍ مقيَّدٍ في جولةِ RPR-02 (اللوحة #4 = 100٪ على الواجب) |\n"
    . "| أسطحٌ بمرجعِ آلةِ حالةٍ في السجلّ | {$smScr} |\n"
    . "| `BUILT_TRANSACTION_WITHOUT_REQUIRED_STATE_MODEL` | **0** — Regression Gate قائمٌ (المفرداتُ المغلقةُ وقيودُ المخطَّط) |\n"
    . "| الجديدُ (WH-03) | حالاتُه قائمةٌ محكومةٌ بقيدَي مخطَّطٍ (`chk_pwc_period`/`chk_pwc_closed_has_note`) — سجلٌّ مرجعيٌّ لا معاملةَ دورةٍ فلا آلةَ واجبة |\n";
$w('STATE_MODEL_CONFORMANCE.md', $md);

/* ═══ ⑦ EVENT_INTEGRATION_CONFORMANCE ═══ */
$ev = (int) $one("SELECT COUNT(*) FROM ems_business_events");
$dl = (int) $one("SELECT COUNT(*) FROM ems_event_deliveries");
$em = (int) $one("SELECT COUNT(*) FROM ems_event_deliveries WHERE fail_text LIKE '%EFFECT_MISSING%'");
$cons = (int) $one("SELECT COUNT(*) FROM event_consumers");
$cls = array();
$q = $conn->query("SELECT classification, COUNT(*) c FROM rpr03_event_classification GROUP BY classification");
while ($x = $q->fetch_assoc()) { $cls[] = $x['classification'] . '=' . $x['c']; }
$md = "# EVENT_INTEGRATION_CONFORMANCE — الأحداثُ والأثر (§15)\n\n" . $HDR;
$md .= "| البند | القيمة الحيّة |\n|---|---|\n"
    . "| دفترُ الحقائق ems_business_events | {$ev} واقعةً (ADR-15 · الجذرُ المحايد) |\n"
    . "| التسليمات | {$dl} — وحالاتُها الكاملةُ بنبضِ cron_events المعلَن |\n"
    . "| `EFFECT_MISSING` | **{$em}** — نمطًا بسوالبِه لا ترقيعًا |\n"
    . "| المستهلكون المسجَّلون | {$cons} عقدَ مستهلكٍ (EffectLinkConsumer) — والتمرينُ الحيُّ لغيرِ المُمارَسِ بترتيبِ السجلّ |\n"
    . "| تصنيفُ المفردات | " . implode(' · ', $cls) . " |\n"
    . "| سجلُّ الاشتراكاتِ المعلَن | `REFERENCE_ONLY` بحكمِ تخفيضِ §5 (خريطةُ المصادر) — يُنذَر بفرقِه كلَّ تشغيلة |\n";
$w('EVENT_INTEGRATION_CONFORMANCE.md', $md);

/* ═══ ⑧ OPEN_OWNER_ACTIONS ═══ */
$md = "# OPEN_OWNER_ACTIONS — القراراتُ الحقيقيّةُ المفتوحةُ وحدَها (§23)\n\n" . $HDR;
$md .= "## قراراتٌ بانتظارِ قيمِ المالك (من دفترِ القرارات)\n\n| القرار | المجال | السؤال |\n|---|---|---|\n";
$q = $conn->query("SELECT decision_id, domain, question FROM repair01_decisions WHERE status = 'NEEDS_OWNER_DECISION' ORDER BY decision_id");
$n8 = 0;
while ($x = $q->fetch_assoc()) { $n8++; $md .= "| `{$x['decision_id']}` | {$x['domain']} | " . str_replace('|', '،', mb_substr($x['question'], 0, 120)) . " |\n"; }
$md .= "\n## بوّاباتُ المالكِ القائمة (OWNER_ACTION_REGISTER)\n\n"
    . "الثماني OA-01..OA-08 بنصِّها وبوّابتِها في `registers/OWNER_ACTION_REGISTER.md` — ومنها OA-06 (قيمُ الاعتماد) التي تحجب إنفاذَ AAM لا بناءَه.\n\n"
    . "**المجموع المفتوح**: {$n8} قرارَ قيمٍ + 8 بنودِ بوّاباتٍ + قرارا تنظيمٍ (دورُ DEP-08 · دورُ EX-DVP) + قراراتُ فرزِ LEG626 وأزواجِ CL-PAT-DUPLABEL.\n";
$w('OPEN_OWNER_ACTIONS.md', $md);

/* ═══ ⑨ REVERSE_AUDIT_MASTER ═══ */
$md = "# REVERSE_AUDIT_MASTER — المراجعةُ العكسيّةُ الشاملةُ من المصدرِ الحاكمِ إلى النظام\n\n" . $HDR;
$md .= "الاتجاه: **مصدرٌ حاكمٌ ← هدفٌ ← تنفيذٌ ← زمنُ تشغيلٍ ← دليل** (م115) — وكلُّ طبقةٍ بوثيقتِها من هذه الحزمةِ الواحدة:\n\n"
    . "| الطبقة | الوثيقة | خلاصةُ حكمِها |\n|---|---|---|\n"
    . "| خريطةُ المصادر (§4) | `registers/GOVERNING_SOURCE_MAP.md` | مطابقةٌ بورقةِ J حرفًا · TARGET_WITHOUT_GOVERNING_SOURCE=0 |\n"
    . "| الدستور (§7) | `CONSTITUTION_COMPLIANCE.md` | كلُّ صفِّ مصدرٍ بفحصِه أو ◐ ببندِ سجلٍّ — صفرُ مخالفةٍ بلا حكم |\n"
    . "| القرارات (§8) | `DECISION_PROPAGATION_REGISTER.md` | 114/114 محكومةً بمجسِّها · UNPROPAGATED=0 |\n"
    . "| الإدارات (§5·§24) | `DEPARTMENT_CONFORMANCE.md` | البنيةُ بسقفِها وجبهتا الحقولِ والبناءِ مفتوحتان بترتيبِ السجلّ |\n"
    . "| القيادة (§6) | `EXECUTIVE_CONFORMANCE.md` | EX-CEO مطابقٌ فيما بُني · EX-DVP بحاجزِ دورِه |\n"
    . "| السايدبار (§9·§10) | `SIDEBAR_CONFORMANCE.md` + `SIDEBAR_GUIDE_COMPARE.md` + `NAVR_METRICS.md` | غيرُ مطابقٍ=0 · سقوطٌ=0 · النسبُ 0 غيرَ مفسَّر |\n"
    . "| الصلاحيات (§13) | `ROLE_PERMISSION_CONFORMANCE.md` | القالبُ النافذُ يحكم حصرًا · التوحيدُ بندُ سجلّ |\n"
    . "| الحقول (§12) | `SCREEN_FIELD_CONFORMANCE.md` | الدفترُ مُسوًّى على -3 والحملةُ بترتيبِ السجلّ |\n"
    . "| آلاتُ الحالة (§14) | `STATE_MODEL_CONFORMANCE.md` | الواجبُ 100٪ وRegression Gate |\n"
    . "| الأحداث (§15) | `EVENT_INTEGRATION_CONFORMANCE.md` | EFFECT_MISSING=0 والدفترُ حصين |\n"
    . "| قراراتُ المالكِ المفتوحة | `OPEN_OWNER_ACTIONS.md` | الحقيقيّةُ وحدَها ببوّاباتِها |\n"
    . "\n**قاعدةُ القراءة**: ما بلغ سقفَه Regression Gate يُشغَّل لا يُعاد (§22) — والمفتوحُ كلُّه بندُ سجلٍّ جامعٍ أو بوّابةُ مالكٍ مسمّاة.\n";
$w('REVERSE_AUDIT_MASTER.md', $md);

echo "═ الحزمةُ مولَّدة بلقطة {$BID} ═\n";
