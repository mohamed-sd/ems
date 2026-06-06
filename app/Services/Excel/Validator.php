<?php
/**
 * Validator — التحقق من صفوف الاستيراد صف-بصف وفق تعريف الكيان.
 *
 * يفحص: الحقول المطلوبة، النوع، قائمة القيم، البريد، الهاتف، التاريخ، الأرقام،
 * التكرار داخل الملف، التكرار في قاعدة البيانات (للحقول الفريدة، ضمن نطاق الشركة)،
 * والمفاتيح الأجنبية. يُنتج نتيجة منظّمة قابلة للعرض والتنزيل.
 *
 * @package App\Services\Excel
 */

declare(strict_types=1);

namespace App\Services\Excel;

class Validator
{
    /**
     * @param array[] $rows صفوف خام (مفهرسة رقمياً) من FileReader.
     * @param int[]   $colMap field => columnIndex من FileReader::mapColumns.
     * @return array{summary: array, rows: array[]}
     */
    public static function validate(
        EntityDefinition $def,
        array $rows,
        array $colMap,
        \mysqli $conn,
        int $companyId
    ): array {
        $importCols = $def->importColumns();

        // قيم فريدة سبق رؤيتها داخل نفس الملف (للكشف عن تكرار داخلي).
        $seen = [];
        foreach ($importCols as $c) {
            if ($c->unique) {
                $seen[$c->field] = [];
            }
        }

        $result = [];
        $validCount = 0;
        $invalidCount = 0;
        $warningCount = 0;

        foreach ($rows as $index => $raw) {
            $rowNum = $index + 2; // الرأس = الصف 1.

            // تخطّي الصفوف الفارغة تماماً.
            $nonEmpty = array_filter(array_map(static function ($v) {
                return is_string($v) ? trim($v) : $v;
            }, $raw), static function ($v) {
                return $v !== '' && $v !== null;
            });
            if (empty($nonEmpty)) {
                continue;
            }

            $data = [];
            $errors = [];
            $warnings = [];

            foreach ($importCols as $c) {
                /** @var Column $c */
                $idx = $colMap[$c->field] ?? null;
                $value = ($idx !== null && isset($raw[$idx])) ? trim((string) $raw[$idx]) : '';
                // إزالة BOM المتبقّي.
                $value = ltrim($value, "\xEF\xBB\xBF");

                // مطلوب.
                if ($value === '') {
                    if ($c->required) {
                        $errors[] = self::err($rowNum, $c->label, 'القيمة مطلوبة', 'أدخل قيمة في هذا العمود');
                        continue;
                    }
                    $data[$c->field] = ($c->default !== null) ? $c->default : null;
                    continue;
                }

                // فحص النوع/القيمة.
                $typeError = self::checkType($c, $value);
                if ($typeError !== null) {
                    $errors[] = self::err($rowNum, $c->label, $typeError['msg'], $typeError['fix']);
                    continue;
                }

                // تكرار داخل الملف.
                if ($c->unique) {
                    $k = mb_strtolower($value);
                    if (isset($seen[$c->field][$k])) {
                        $errors[] = self::err($rowNum, $c->label, "القيمة «{$value}» مكررة داخل الملف", 'اجعل القيمة فريدة');
                        continue;
                    }
                    $seen[$c->field][$k] = true;
                }

                $data[$c->field] = self::normalize($c, $value);
            }

            // فحوص قاعدة البيانات (تكرار فريد + مفاتيح أجنبية) — فقط إن لم تكن هناك أخطاء بنيوية.
            if (empty($errors)) {
                foreach ($importCols as $c) {
                    if (!isset($data[$c->field]) || $data[$c->field] === null || $data[$c->field] === '') {
                        continue;
                    }
                    if ($c->unique && self::existsInDb($conn, $def, $c->field, (string) $data[$c->field], $companyId)) {
                        $errors[] = self::err($rowNum, $c->label, "القيمة «{$data[$c->field]}» موجودة مسبقاً في النظام", 'استخدم قيمة جديدة أو احذف الصف');
                    }
                    if ($c->foreignKey && !self::fkExists($conn, $c, (string) $data[$c->field], $companyId)) {
                        $errors[] = self::err($rowNum, $c->label, "القيمة «{$data[$c->field]}» غير موجودة في الجدول المرتبط", 'تأكد من إدخال قيمة موجودة مسبقاً');
                    }
                    // بحث/Lookup: تحويل الاسم/الكود إلى مفتاح أجنبي.
                    if ($c->lookup) {
                        $resolved = self::resolveLookup($conn, $c->lookup, (string) $data[$c->field], $companyId);
                        if ($resolved === null) {
                            $errors[] = self::err($rowNum, $c->label, "القيمة «{$data[$c->field]}» غير موجودة في النظام", 'أدخل اسماً أو كوداً موجوداً مسبقاً، أو أضِف السجل أولاً');
                        } else {
                            $storeIdIn = $c->lookup['storeIdIn'];
                            // الحالة 1 (عمود نصّي منفصل): أعِد كتابة الاسم القانوني في عمود العرض.
                            // الحالة 2 (نفس العمود يحمل المعرف): لا تكتب الاسم فوقه — المعرف هو القيمة المخزّنة.
                            if ($c->field !== $storeIdIn) {
                                $data[$c->field] = $resolved['name'];
                            }
                            // تخزين المعرف في عموده (يفوز دائماً في الحالة 2).
                            $data[$storeIdIn] = $resolved['id'];
                        }
                    }
                }
            }

            $valid = empty($errors);
            if ($valid) {
                $validCount++;
            } else {
                $invalidCount++;
            }
            $warningCount += count($warnings);

            $result[] = [
                'row'      => $rowNum,
                'valid'    => $valid,
                'data'     => $data,
                'errors'   => $errors,
                'warnings' => $warnings,
            ];
        }

        return [
            'summary' => [
                'total'    => count($result),
                'valid'    => $validCount,
                'invalid'  => $invalidCount,
                'warnings' => $warningCount,
            ],
            'rows' => $result,
        ];
    }

    private static function err(int $row, string $col, string $msg, string $fix): array
    {
        return ['row' => $row, 'column' => $col, 'error' => $msg, 'fix' => $fix];
    }

    /** @return array{msg:string,fix:string}|null */
    private static function checkType(Column $c, string $value): ?array
    {
        switch ($c->type) {
            case Column::TYPE_INT:
                if (!preg_match('/^-?\d+$/', $value)) {
                    return ['msg' => 'يجب أن تكون رقماً صحيحاً', 'fix' => 'أدخل رقماً بدون كسور (مثل: 2020)'];
                }
                break;
            case Column::TYPE_FLOAT:
                if (!is_numeric(str_replace(',', '', $value))) {
                    return ['msg' => 'يجب أن تكون رقماً', 'fix' => 'أدخل رقماً (مثل: 1500 أو 1500.50)'];
                }
                break;
            case Column::TYPE_EMAIL:
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    return ['msg' => 'صيغة البريد الإلكتروني غير صحيحة', 'fix' => 'استخدم صيغة name@domain.com'];
                }
                break;
            case Column::TYPE_PHONE:
                if (!preg_match('/^[\+]?[0-9\s\-]{7,20}$/', $value)) {
                    return ['msg' => 'صيغة رقم الهاتف غير صحيحة', 'fix' => 'استخدم أرقاماً فقط مع رمز الدولة (+249...)'];
                }
                break;
            case Column::TYPE_DATE:
                if (!self::isValidDate($value)) {
                    return ['msg' => 'صيغة التاريخ غير صحيحة', 'fix' => 'استخدم الصيغة YYYY-MM-DD (مثل: 2026-01-01)'];
                }
                break;
            case Column::TYPE_ENUM:
                if ($c->enum && !in_array($value, $c->enum, true)) {
                    return ['msg' => "قيمة غير مقبولة «{$value}»", 'fix' => 'اختر إحدى: ' . implode(' / ', $c->enum)];
                }
                break;
        }
        return null;
    }

    /** تطبيع القيمة قبل التخزين (مثل توحيد صيغة التاريخ). */
    private static function normalize(Column $c, string $value)
    {
        if ($c->type === Column::TYPE_DATE) {
            $ts = strtotime($value);
            return $ts ? date('Y-m-d', $ts) : $value;
        }
        if ($c->type === Column::TYPE_FLOAT) {
            return str_replace(',', '', $value);
        }
        return $value;
    }

    private static function isValidDate(string $value): bool
    {
        foreach (['Y-m-d', 'd/m/Y', 'Y/m/d', 'd-m-Y'] as $fmt) {
            $d = \DateTime::createFromFormat($fmt, $value);
            if ($d && $d->format($fmt) === $value) {
                return true;
            }
        }
        return strtotime($value) !== false;
    }

    private static function existsInDb(\mysqli $conn, EntityDefinition $def, string $field, string $value, int $companyId): bool
    {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $def->table);
        $col   = preg_replace('/[^a-zA-Z0-9_]/', '', $field);

        $sql = "SELECT 1 FROM `{$table}` WHERE `{$col}` = ?";
        $types = 's';
        $params = [$value];

        if ($def->companyScoped && function_exists('db_table_has_column') && db_table_has_column($conn, $def->table, $def->companyColumn)) {
            $sql .= " AND `{$def->companyColumn}` = ?";
            $types .= 'i';
            $params[] = $companyId;
        }
        if ($def->softDeleteColumn && function_exists('db_table_has_column') && db_table_has_column($conn, $def->table, $def->softDeleteColumn)) {
            $sql .= " AND `{$def->softDeleteColumn}` = 0";
        }
        $sql .= ' LIMIT 1';

        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        $exists = mysqli_stmt_num_rows($stmt) > 0;
        mysqli_stmt_close($stmt);
        return $exists;
    }

    private static function fkExists(\mysqli $conn, Column $c, string $value, int $companyId): bool
    {
        $fk = $c->foreignKey;
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $fk['table']);
        $col   = preg_replace('/[^a-zA-Z0-9_]/', '', $fk['column']);
        $sql = "SELECT 1 FROM `{$table}` WHERE `{$col}` = ?";
        $types = 's';
        $params = [$value];
        if (!empty($fk['scoped']) && function_exists('db_table_has_column') && db_table_has_column($conn, $table, 'company_id')) {
            $sql .= ' AND company_id = ?';
            $types .= 'i';
            $params[] = $companyId;
        }
        $sql .= ' LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return true; // عند تعذّر الفحص، لا نمنع.
        }
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        $exists = mysqli_stmt_num_rows($stmt) > 0;
        mysqli_stmt_close($stmt);
        return $exists;
    }

    /**
     * يحلّ قيمة مقروءة (اسم/كود) إلى مفتاح أجنبي.
     * يجرّب أعمدة المطابقة بالترتيب (الكود أولاً ثم الاسم عادةً) ويعيد أول تطابق.
     *
     * @return array{id:mixed,name:string}|null عند عدم العثور.
     */
    private static function resolveLookup(\mysqli $conn, array $lookup, string $value, int $companyId): ?array
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $table    = preg_replace('/[^a-zA-Z0-9_]/', '', (string) ($lookup['table'] ?? ''));
        $idCol    = preg_replace('/[^a-zA-Z0-9_]/', '', (string) ($lookup['idColumn'] ?? 'id'));
        $nameCol  = preg_replace('/[^a-zA-Z0-9_]/', '', (string) ($lookup['nameColumn'] ?? 'name'));
        $matchBy  = $lookup['matchBy'] ?? [];
        if ($table === '' || empty($matchBy)) {
            return null;
        }

        $scoped     = !empty($lookup['scoped']);
        $softDelete = isset($lookup['softDelete']) ? (string) $lookup['softDelete'] : null;

        $useCompany = $scoped
            && function_exists('db_table_has_column')
            && db_table_has_column($conn, $table, 'company_id');
        $useSoftDelete = $softDelete
            && function_exists('db_table_has_column')
            && db_table_has_column($conn, $table, $softDelete);
        $softDeleteCol = $useSoftDelete ? preg_replace('/[^a-zA-Z0-9_]/', '', $softDelete) : null;

        foreach ($matchBy as $rawCol) {
            $matchCol = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $rawCol);
            if ($matchCol === '') {
                continue;
            }

            $sql = "SELECT `{$idCol}` AS lk_id, `{$nameCol}` AS lk_name FROM `{$table}` WHERE `{$matchCol}` = ?";
            $types = 's';
            $params = [$value];
            if ($useCompany) {
                $sql .= ' AND `company_id` = ?';
                $types .= 'i';
                $params[] = $companyId;
            }
            if ($softDeleteCol) {
                $sql .= " AND `{$softDeleteCol}` = 0";
            }
            $sql .= ' LIMIT 1';

            $stmt = mysqli_prepare($conn, $sql);
            if (!$stmt) {
                continue;
            }
            mysqli_stmt_bind_param($stmt, $types, ...$params);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $row = $res ? mysqli_fetch_assoc($res) : null;
            mysqli_stmt_close($stmt);

            if ($row) {
                return ['id' => $row['lk_id'], 'name' => (string) $row['lk_name']];
            }
        }

        return null;
    }
}
