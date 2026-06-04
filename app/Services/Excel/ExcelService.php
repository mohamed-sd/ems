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

    /** فحص صلاحية الوحدة للإجراء المطلوب (view/add). */
    private function authorize(EntityDefinition $def, string $action): void
    {
        if ($this->isSuperAdmin) {
            return;
        }
        if (!function_exists('check_page_permissions')) {
            return; // التوافقية مع الصفحات القديمة.
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

    /** بثّ تصدير البيانات. */
    public function export(EntityDefinition $def): void
    {
        $this->authorize($def, 'view');
        $rows = $this->fetchRows($def);
        $spreadsheet = Exporter::build($def, $rows);
        $this->stream($spreadsheet, 'export_' . $def->key);
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

    /** جلب صفوف التصدير مع تطبيق نطاق الشركة والحذف الناعم. */
    private function fetchRows(EntityDefinition $def): array
    {
        $columns = $def->exportColumns();
        $select = [];
        foreach ($columns as $c) {
            $col = preg_replace('/[^a-zA-Z0-9_]/', '', $c->field);
            if ($c->exportExpr) {
                $select[] = $c->exportExpr . " AS `{$c->field}`";
            } else {
                $select[] = "`{$col}`";
            }
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
        while ($row = mysqli_fetch_assoc($res)) {
            $rows[] = $row;
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
