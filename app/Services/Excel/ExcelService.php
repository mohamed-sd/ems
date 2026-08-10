<?php
/**
 * ExcelService — الواجهة الموحّدة (Facade) لإطار Excel.
 *
 * تجمع الحُرّاس الأمنية (المصادقة، CSRF، الصلاحيات، نطاق الشركة) مع عمليات
 * التصدير/النموذج/المعاينة/التنفيذ. هي الـ API الوحيد الذي يستدعيه المتحكّم الأمامي.
 *
 * @package App\Services\Excel
 */

declare(strict_types=1);

namespace App\Services\Excel;

require_once __DIR__ . '/../../../includes/catch_log.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExcelService
{
    /** @var \mysqli */
    private $conn;
    /** @var int */
    private $companyId;
    /** @var int */
    private $userId;
    /** @var bool */
    private $isSuperAdmin;

    public function __construct(\mysqli $conn)
    {
        $this->conn = $conn;
        $user = $_SESSION['user'] ?? null;
        if (!$user) {
            $this->fail(401, 'غير مصرّح — يرجى تسجيل الدخول');
        }
        $role = isset($user['role']) ? (string) $user['role'] : '';
        $this->isSuperAdmin = ($role === '-1');
        $this->companyId = isset($user['company_id']) ? (int) $user['company_id'] : 0;
        $this->userId = isset($user['id']) ? (int) $user['id'] : 0;

        if (!$this->isSuperAdmin && $this->companyId <= 0) {
            $this->fail(403, 'الحساب غير مرتبط بشركة');
        }
    }

    public function definition(string $entityKey): EntityDefinition
    {
        $def = ExcelRegistry::get($entityKey);
        if (!$def) {
            $this->fail(404, 'الكيان المطلوب غير معرّف في نظام Excel');
        }
        return $def;
    }

    /**
     * فحص صلاحية الوحدة للإجراء المطلوب (view/add).
     *
     * FIX-01 · RF-03 + CS-02 — **الفشلُ مغلق**.
     * ◆ كان هنا فرعٌ مفتوح: ‎if (!function_exists('check_page_permissions')) return;‎
     *   و‎excel.php‎ لا يحمّل ‎permissions_helper‎ أصلًا — فكان التصديرُ الموحَّدُ
     *   يمرُّ بلا أيِّ فحصِ تفويضٍ لكلِّ كيان. الآن: غيابُ الحارسِ **منعٌ** لا
     *   سماح، والملفُّ الأماميُّ يحمّله في رأسِه (FIXA-0027 · FIXA-0028).
     */
    private function authorize(EntityDefinition $def, string $action): void
    {
        if ($this->isSuperAdmin) {
            return;
        }
        if (!function_exists('check_page_permissions')) {
            // ◆ لا توافقيةَ مع غيابِ الحارس: الغيابُ خللٌ في التحميلِ لا حالةٌ عادية.
            $this->fail(500, 'طبقةُ الصلاحياتِ غيرُ محمَّلةٍ — التصديرُ ممنوعٌ (فشلٌ مغلق)');
        }
        $perms = check_page_permissions($this->conn, $def->moduleCode);
        $allowed = ($action === 'add') ? !empty($perms['can_add']) : !empty($perms['can_view']);
        if (!$allowed) {
            $label = ($action === 'add') ? 'استيراد' : 'تصدير';
            $this->fail(403, "لا توجد صلاحية {$label} لـ {$def->title}");
        }
    }

    private function verifyCsrf(): void
    {
        $token = $_POST['csrf_token']
            ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!function_exists('verify_csrf_token') || !verify_csrf_token((string) $token)) {
            $this->fail(403, 'رمز الحماية (CSRF) غير صالح. يرجى تحديث الصفحة وإعادة المحاولة.');
        }
    }

    // ── العمليات ───────────────────────────────────────────────────────────

    /** بثّ نموذج الاستيراد. */
    public function template(EntityDefinition $def): void
    {
        $this->authorize($def, 'view');
        $spreadsheet = TemplateBuilder::build($def);
        $this->stream($spreadsheet, 'template_' . $def->key);
    }

    /**
     * بثّ تصدير البيانات.
     *
     * FIX-01 · RF-03 خطوتا ③+④ — الأعمدةُ تمرُّ بحاكمِ الحقولِ قبلَ الاستعلام،
     * والتصديرُ **كتابةُ حوكمةٍ** تكتب سجلًّا بتسعةِ بنودٍ ومنها المستبعَدة.
     * ◆ الحجبُ في **الخادمِ قبلَ القراءة**: العمودُ الممنوعُ لا يدخل ‎SELECT‎
     *   أصلًا فلا يُقرأ ولا يُبثّ — لا يُخفى بعدَ قراءته.
     */
    public function export(EntityDefinition $def): void
    {
        $this->authorize($def, 'view');

        $gov  = $this->governExportColumns($def);
        $rows = $this->fetchRows($def, $gov['blocked']);

        // E-19: معاييرُ التصدير المطبَّقةُ فعلًا — تُعلَن في الملف لا تُضمَر
        $criteria = [];
        if (!$this->isSuperAdmin && $def->companyScoped) {
            $criteria[] = 'نطاقُ الشركة #' . $this->companyId;
        }
        if ($def->softDeleteColumn) { $criteria[] = 'بلا المحذوف'; }
        if (is_callable($def->exportRowScope)) { $criteria[] = 'نطاقُ رؤية الشاشة'; }
        if ($gov['blocked']) {
            // ◆ المستبعَدُ يُعلَن في وجهِ الملفِّ لا يُحذف صامتًا — فالصمتُ يُقرأ اكتمالًا.
            $criteria[] = 'حقولٌ حساسةٌ مُستبعَدةٌ بلا منح: ' . implode('، ', $gov['blocked']);
        }
        $criteria[] = 'عددُ الصفوف: ' . count($rows);

        $this->logGovernedExport($def, $gov, count($rows), $criteria);

        $spreadsheet = Exporter::build($def, $rows, $criteria, $gov['blocked']);
        $this->stream($spreadsheet, 'export_' . $def->key);
    }

    /**
     * CS-10 — يحسب الأعمدةَ المسموحةَ والمحجوبةَ لهذا الدورِ على جدولِ الكيان.
     * @return array{allowed:string[],blocked:string[],logged:string[]}
     */
    private function governExportColumns(EntityDefinition $def): array
    {
        $wanted = [];
        foreach ($def->exportColumns() as $c) { $wanted[] = $c->field; }

        // ◆ تحميلٌ صريحٌ لا اتكالٌ على المحمِّل التلقائي: ‎app/bootstrap.php‎ لا
        //   يُحمَّل في وضعِ CLI، فالاتكالُ عليه يجعل الحاكمَ غائبًا في الاختبارِ
        //   الآليِّ ويُقرأ ذلك «سماحًا» — وهو بالضبط نمطُ العطلِ الذي نُصحِّحه.
        $governorFile = dirname(__DIR__) . '/Governance/FieldGovernor.php';
        if (!class_exists('\\App\\Services\\Governance\\FieldGovernor', false) && is_file($governorFile)) {
            require_once $governorFile;
        }

        if (!class_exists('\\App\\Services\\Governance\\FieldGovernor', false)) {
            // ◆ فشلٌ مغلق: غيابُ الحاكمِ يعني منعَ كلِّ حقلٍ حساسٍ لا السماحَ به.
            // نستبعد ما هو مسجَّلٌ حساسًا مباشرةً من القاموسِ بلا وسيط.
            $blocked = [];
            $tbl = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $def->table);
            $rs = $this->conn->query("SELECT field_name FROM scr_sensitive_fields
                                       WHERE table_name = '" . $this->conn->real_escape_string($tbl) . "'
                                         AND status = 'معتمد'");
            while ($rs && ($r = $rs->fetch_assoc())) {
                if (in_array($r['field_name'], $wanted, true)) { $blocked[] = $r['field_name']; }
            }
            return ['allowed' => array_values(array_diff($wanted, $blocked)), 'blocked' => $blocked, 'logged' => $blocked];
        }

        $roleId = isset($_SESSION['user']['role']) ? (int) $_SESSION['user']['role'] : 0;
        return \App\Services\Governance\FieldGovernor::exportableColumns(
            $this->conn, (string) $def->table, $roleId, $wanted, $this->isSuperAdmin
        );
    }

    /**
     * FIXA-0030 — التصديرُ فعلُ حوكمةٍ يكتب سجلًّا بتسعةِ بنود:
     * ① من صدّر ② بصفتِه ③ الكيان/الشاشة ④ الوقت ⑤ الأعمدةُ المُصدَّرة
     * ⑥ **الأعمدةُ المستبعَدة** ⑦ المرشِّحاتُ المطبَّقة ⑧ عددُ الصفوف ⑨ الصيغة.
     * ◆ ولا يبتلع فشلَه صامتًا (CS-12): يُسجَّل في ‎error_log‎ ولا يُوقف المنع.
     */
    private function logGovernedExport(EntityDefinition $def, array $gov, int $rowCount, array $criteria): void
    {
        // ◆ گوتشا موثَّقة: انزياحُ حرفٍ واحدٍ في ‎bind_param‎ يمحو نصًّا إلى '0'
        //   صامتًا. الترتيبُ هنا مطابقٌ للأعمدة حرفًا بحرف: i i s s s s s s i s.
        try {
            $st = $this->conn->prepare(
                "INSERT INTO gov_export_log
                   (company_id, exported_by, actor_capacity, entity_key, screen_code,
                    columns_text, blocked_text, filters_text, row_count, fmt, exported_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,NOW())");
            if (!$st) { throw new \RuntimeException('prepare: ' . $this->conn->error); }
            $capacity = (string) ($_SESSION['user']['role_name'] ?? ('role#' . ($_SESSION['user']['role'] ?? '?')));
            $cols     = implode(',', $gov['allowed']);
            $blocked  = implode(',', $gov['blocked']);
            $filters  = implode(' · ', $criteria);
            $fmt      = 'xlsx';
            $screen   = (string) $def->moduleCode;
            $entity   = (string) $def->key;
            $st->bind_param('iissssssis',
                $this->companyId, $this->userId, $capacity, $entity, $screen,
                $cols, $blocked, $filters, $rowCount, $fmt);
            if (!$st->execute()) { throw new \RuntimeException('execute: ' . $st->error); }
            $st->close();
        } catch (\Throwable $e) { ems_catch_ignored($e, __METHOD__, 'EMS gov_export_log failed');
            error_log('EMS gov_export_log failed: ' . $e->getMessage());
        }
    }

    /** المعاينة (JSON). */
    public function importPreview(EntityDefinition $def): array
    {
        $this->verifyCsrf();
        $this->authorize($def, 'add');
        if (!isset($_FILES['excel_file'])) {
            $this->fail(400, 'لم يتم استلام أي ملف');
        }
        return Importer::preview($def, $_FILES['excel_file'], $this->conn, $this->companyId, $this->userId);
    }

    /** التنفيذ (JSON). */
    public function importCommit(EntityDefinition $def): array
    {
        $this->verifyCsrf();
        $this->authorize($def, 'add');
        $token = (string) ($_POST['token'] ?? '');
        if ($token === '') {
            $this->fail(400, 'رمز المعاينة مفقود');
        }
        return Importer::commit($def, $token, $this->conn, $this->companyId, $this->userId);
    }

    // ── مساعدات داخلية ───────────────────────────────────────────────────

    /**
     * جلب صفوف التصدير مع تطبيق نطاق الشركة والحذف الناعم.
     *
     * @param string[] $blockedFields حقولٌ حساسةٌ بلا منحٍ لهذا الدور — **لا تدخل
     *        عبارةَ SELECT أصلًا** (CS-10: الحجبُ في الخادمِ قبلَ القراءة).
     */
    private function fetchRows(EntityDefinition $def, array $blockedFields = []): array
    {
        $columns = $def->exportColumns();
        $select = [];
        foreach ($columns as $c) {
            if (in_array($c->field, $blockedFields, true)) { continue; }
            $col = preg_replace('/[^a-zA-Z0-9_]/', '', $c->field);
            if ($c->exportExpr) {
                $select[] = $c->exportExpr . " AS `{$c->field}`";
            } else {
                $select[] = "`{$col}`";
            }
        }
        if (!$select) {
            // كلُّ الأعمدةِ محجوبة ⇒ لا تصديرَ يُبثّ. المنعُ صريحٌ لا ملفٌّ فارغ.
            $this->fail(403, 'كلُّ أعمدةِ هذا الكيانِ حساسةٌ ولا منحَ لدورِك — لا يوجد ما يُصدَّر');
        }
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $def->table);
        $sql = 'SELECT ' . implode(', ', $select) . " FROM `{$table}` WHERE 1=1";

        $params = [];
        $types = '';
        if (!$this->isSuperAdmin && $def->companyScoped && $this->columnExists($def->table, $def->companyColumn)) {
            $sql .= " AND `{$def->companyColumn}` = ?";
            $params[] = $this->companyId;
            $types .= 'i';
        }
        if ($def->softDeleteColumn && $this->columnExists($def->table, $def->softDeleteColumn)) {
            $sql .= " AND `{$def->softDeleteColumn}` = 0";
        }

        // نطاقُ رؤيةٍ داخل الشركة (اختياري لكل كيان): يمنع أن يتجاوز التصديرُ
        // ما تحجبه الشاشة نفسها — مثل نطاق البلاغات (D3).
        if (is_callable($def->exportRowScope)) {
            $extra = call_user_func($def->exportRowScope, [
                'conn'         => $this->conn,
                'companyId'    => $this->companyId,
                'userId'       => $this->userId,
                'role'         => isset($_SESSION['user']['role']) ? (string) $_SESSION['user']['role'] : '',
                'isSuperAdmin' => $this->isSuperAdmin,
            ]);
            if (is_array($extra) && !empty($extra['sql'])) {
                $sql .= ' AND (' . $extra['sql'] . ')';
                foreach (($extra['params'] ?? []) as $p) {
                    $params[] = $p;
                }
                $types .= (string) ($extra['types'] ?? '');
            }
        }

        $sql .= ' ORDER BY ' . $def->exportOrderBy;

        $stmt = mysqli_prepare($this->conn, $sql);
        if (!$stmt) {
            $this->fail(500, 'خطأ في جلب البيانات: ' . mysqli_error($this->conn));
        }
        if ($params) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $rows = [];
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $rows[] = $row;
            }
        }
        mysqli_stmt_close($stmt);
        return $rows;
    }

    private function columnExists(string $table, string $column): bool
    {
        if (function_exists('db_table_has_column')) {
            return db_table_has_column($this->conn, $table, $column);
        }
        return true;
    }

    /** بثّ مصنّف إلى المتصفح كملف xlsx بترميز عربي سليم. */
    private function stream(Spreadsheet $spreadsheet, string $asciiBase): void
    {
        while (ob_get_level()) {
            ob_end_clean();
        }
        $ascii = $asciiBase . '_' . date('Y-m-d') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $ascii . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    /** إنهاء بخطأ JSON موحّد. */
    public function fail(int $code, string $message): void
    {
        while (ob_get_level()) {
            ob_end_clean();
        }
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
