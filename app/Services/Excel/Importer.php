<?php
/**
 * Importer — تنسيق عملية الاستيراد على مرحلتين: معاينة ثم تنفيذ.
 *
 *  - preview(): يقرأ الملف، يطابق الأعمدة، يتحقق، يخزّن الصفوف الصحيحة مؤقتاً،
 *    ويعيد ملخّصاً + عيّنة + تقرير أخطاء + رمز جلسة (token) للتنفيذ.
 *  - commit(): يقرأ الصفوف الصحيحة المخزّنة بالرمز ويُدرجها داخل Transaction
 *    باستخدام Prepared Statement واحد مُعاد الاستخدام.
 *
 * التخزين المؤقت في storage/excel_imports/{token}.json ومقيّد بالمستخدم والشركة.
 *
 * @package App\Services\Excel
 */

declare(strict_types=1);

namespace App\Services\Excel;

class Importer
{
    private const SAMPLE_LIMIT = 50;   // عدد الصفوف المعروضة في المعاينة.
    private const ERROR_LIMIT   = 200; // سقف الأخطاء المعادة لتفادي التضخّم.
    private const CACHE_TTL      = 3600; // عمر الملف المؤقت بالثواني.

    /**
     * المرحلة 1: المعاينة والتحقق.
     *
     * @return array مُعدّ للإخراج كـ JSON.
     */
    public static function preview(EntityDefinition $def, array $file, \mysqli $conn, int $companyId, int $userId): array
    {
        $parsed = FileReader::readUpload($file, $def->maxRows);
        $colMap = FileReader::mapColumns($def->importColumns(), $parsed['header']);

        if (empty($parsed['rows'])) {
            throw new \RuntimeException('الملف لا يحتوي على بيانات بعد صف الرأس');
        }

        $validation = Validator::validate($def, $parsed['rows'], $colMap, $conn, $companyId);

        // تخزين الصفوف الصحيحة فقط مؤقتاً للتنفيذ اللاحق.
        $validData = [];
        foreach ($validation['rows'] as $r) {
            if ($r['valid']) {
                $validData[] = $r['data'];
            }
        }

        $token = '';
        if (!empty($validData)) {
            $token = self::store($def->key, $companyId, $userId, $validData);
        }

        // تجميع الأخطاء (مع سقف).
        $errorReport = [];
        foreach ($validation['rows'] as $r) {
            foreach ($r['errors'] as $e) {
                if (count($errorReport) >= self::ERROR_LIMIT) {
                    break 2;
                }
                $errorReport[] = $e;
            }
        }

        // عيّنة العرض.
        $sample = array_slice($validation['rows'], 0, self::SAMPLE_LIMIT);

        return [
            'success'  => true,
            'stage'    => 'preview',
            'token'    => $token,
            'summary'  => $validation['summary'],
            'columns'  => array_map(static function (Column $c) {
                return ['field' => $c->field, 'label' => $c->label];
            }, $def->importColumns()),
            'sample'   => $sample,
            'errors'   => $errorReport,
            'entity'   => $def->key,
            'title'    => $def->title,
        ];
    }

    /**
     * المرحلة 2: التنفيذ (إدراج الصفوف الصحيحة المخزّنة).
     *
     * @return array مُعدّ للإخراج كـ JSON.
     */
    public static function commit(EntityDefinition $def, string $token, \mysqli $conn, int $companyId, int $userId): array
    {
        $rows = self::retrieve($def->key, $companyId, $userId, $token);
        if ($rows === null) {
            throw new \RuntimeException('انتهت صلاحية المعاينة أو لم تُعثر عليها. يرجى رفع الملف من جديد.');
        }
        if (empty($rows)) {
            throw new \RuntimeException('لا توجد صفوف صحيحة للاستيراد.');
        }

        // بناء قائمة الأعمدة الفعلية للإدراج (الموجودة في الجدول فقط).
        $importCols = $def->importColumns();
        $fields = [];
        foreach ($importCols as $c) {
            if (self::columnExists($conn, $def->table, $c->field)) {
                $fields[] = $c->field;
            }
            // عمود المفتاح الأجنبي المحلول عبر Lookup (مثل client_id) يُدرَج أيضاً.
            if (!empty($c->lookup['storeIdIn'])) {
                $idCol = $c->lookup['storeIdIn'];
                if (!in_array($idCol, $fields, true) && self::columnExists($conn, $def->table, $idCol)) {
                    $fields[] = $idCol;
                }
            }
        }

        $hasCompany = $def->companyScoped && self::columnExists($conn, $def->table, $def->companyColumn);
        $hasCreatedBy = $def->createdByColumn && self::columnExists($conn, $def->table, $def->createdByColumn);
        $hasSoftDelete = $def->softDeleteColumn && self::columnExists($conn, $def->table, $def->softDeleteColumn);

        $insertFields = $fields;
        if ($hasCompany) {
            $insertFields[] = $def->companyColumn;
        }
        if ($hasCreatedBy) {
            $insertFields[] = $def->createdByColumn;
        }
        if ($hasSoftDelete) {
            $insertFields[] = $def->softDeleteColumn;
        }

        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $def->table);
        $escFields = array_map(static function ($f) {
            return '`' . preg_replace('/[^a-zA-Z0-9_]/', '', $f) . '`';
        }, $insertFields);
        $placeholders = implode(',', array_fill(0, count($insertFields), '?'));
        $sql = "INSERT INTO `{$table}` (" . implode(',', $escFields) . ") VALUES ({$placeholders})";

        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            throw new \RuntimeException('تعذر تجهيز الاستيراد: ' . mysqli_error($conn));
        }

        $added = 0;
        $failed = 0;
        $failErrors = [];

        mysqli_begin_transaction($conn);
        try {
            foreach ($rows as $i => $data) {
                $values = [];
                foreach ($fields as $f) {
                    $col = $def->column($f);
                    $v = $data[$f] ?? null;
                    if (($v === null || $v === '') && $col && $col->default !== null) {
                        $v = $col->default;
                    }
                    $values[] = $v;
                }
                if ($hasCompany) {
                    $values[] = $companyId;
                }
                if ($hasCreatedBy) {
                    $values[] = $userId;
                }
                if ($hasSoftDelete) {
                    $values[] = 0;
                }

                $types = str_repeat('s', count($values));
                mysqli_stmt_bind_param($stmt, $types, ...$values);
                if (mysqli_stmt_execute($stmt)) {
                    $added++;
                } else {
                    $failed++;
                    if (count($failErrors) < 50) {
                        $failErrors[] = ['row' => $i + 1, 'error' => mysqli_stmt_error($stmt)];
                    }
                }
            }
            mysqli_commit($conn);
        } catch (\Throwable $e) {
            mysqli_rollback($conn);
            mysqli_stmt_close($stmt);
            throw new \RuntimeException('فشل الاستيراد وتم التراجع عن كل التغييرات: ' . $e->getMessage());
        }
        mysqli_stmt_close($stmt);

        self::forget($def->key, $companyId, $userId, $token);

        return [
            'success' => true,
            'stage'   => 'commit',
            'added'   => $added,
            'failed'  => $failed,
            'errors'  => $failErrors,
            'message' => "تم استيراد {$added} سجلاً بنجاح" . ($failed > 0 ? "، وفشل {$failed}." : '.'),
        ];
    }

    // ── التخزين المؤقت ─────────────────────────────────────────────────────

    private static function dir(): string
    {
        $base = defined('EMS_STORAGE_DIR') ? EMS_STORAGE_DIR : (__DIR__ . '/../../../storage');
        $dir = $base . '/excel_imports';
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
            @file_put_contents($dir . '/.htaccess', "Require all denied\n");
        }
        return $dir;
    }

    private static function key(string $entity, int $companyId, int $userId, string $token): string
    {
        $safeToken = preg_replace('/[^a-f0-9]/', '', strtolower($token));
        return hash('sha256', $entity . '|' . $companyId . '|' . $userId . '|' . $safeToken);
    }

    private static function store(string $entity, int $companyId, int $userId, array $rows): string
    {
        self::gc();
        $token = bin2hex(random_bytes(16));
        $payload = ['entity' => $entity, 'company' => $companyId, 'user' => $userId, 'at' => time(), 'rows' => $rows];
        $path = self::dir() . '/' . self::key($entity, $companyId, $userId, $token) . '.json';
        file_put_contents($path, json_encode($payload, JSON_UNESCAPED_UNICODE), LOCK_EX);
        return $token;
    }

    private static function retrieve(string $entity, int $companyId, int $userId, string $token): ?array
    {
        $path = self::dir() . '/' . self::key($entity, $companyId, $userId, $token) . '.json';
        if (!is_file($path)) {
            return null;
        }
        $payload = json_decode((string) file_get_contents($path), true);
        if (!is_array($payload) || !isset($payload['rows'])) {
            return null;
        }
        if (($payload['company'] ?? -1) !== $companyId || ($payload['user'] ?? -1) !== $userId || ($payload['entity'] ?? '') !== $entity) {
            return null;
        }
        if ((time() - (int) ($payload['at'] ?? 0)) > self::CACHE_TTL) {
            @unlink($path);
            return null;
        }
        return $payload['rows'];
    }

    private static function forget(string $entity, int $companyId, int $userId, string $token): void
    {
        $path = self::dir() . '/' . self::key($entity, $companyId, $userId, $token) . '.json';
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /** حذف الملفات المؤقتة المنتهية. */
    private static function gc(): void
    {
        $files = @glob(self::dir() . '/*.json');
        if (!$files) {
            return;
        }
        $now = time();
        foreach ($files as $f) {
            if (($now - @filemtime($f)) > self::CACHE_TTL) {
                @unlink($f);
            }
        }
    }

    private static function columnExists(\mysqli $conn, string $table, string $column): bool
    {
        if (function_exists('db_table_has_column')) {
            return db_table_has_column($conn, $table, $column);
        }
        $t = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $c = mysqli_real_escape_string($conn, $column);
        $res = @mysqli_query($conn, "SHOW COLUMNS FROM `{$t}` LIKE '{$c}'");
        return $res && mysqli_num_rows($res) > 0;
    }
}
