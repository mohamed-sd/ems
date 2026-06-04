<?php
/**
 * FileReader — قراءة آمنة لملفات الاستيراد (xlsx / xls / csv).
 *
 * يتولّى الفحوص الأمنية (الامتداد، الحجم، عدم الفراغ)، ودعم الترميز العربي
 * الكامل (UTF-8 / BOM / Windows-1256)، ويعيد صف الرأس + الصفوف.
 *
 * @package App\Services\Excel
 */

declare(strict_types=1);

namespace App\Services\Excel;

class FileReader
{
    public const ALLOWED_EXT = ['xlsx', 'xls', 'csv'];
    public const MAX_BYTES    = 5242880; // 5 ميجابايت

    /**
     * قراءة ملف مرفوع.
     *
     * @param array $file  عنصر من $_FILES.
     * @param int   $maxRows
     * @return array{header: string[], rows: array[]}
     * @throws \RuntimeException عند أي خطأ مع رسالة عربية.
     */
    public static function readUpload(array $file, int $maxRows): array
    {
        self::guardUpload($file);

        $tmp = $file['tmp_name'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if ($ext === 'csv') {
            return self::readCsv($tmp, $maxRows);
        }
        return self::readSpreadsheet($tmp, $maxRows);
    }

    private static function guardUpload(array $file): void
    {
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            $messages = [
                UPLOAD_ERR_INI_SIZE   => 'حجم الملف يتجاوز الحد المسموح في الخادم',
                UPLOAD_ERR_FORM_SIZE  => 'حجم الملف يتجاوز الحد المسموح',
                UPLOAD_ERR_PARTIAL    => 'تم رفع الملف جزئياً فقط',
                UPLOAD_ERR_NO_FILE    => 'لم يتم اختيار ملف',
                UPLOAD_ERR_NO_TMP_DIR => 'مجلد الملفات المؤقتة غير موجود',
                UPLOAD_ERR_CANT_WRITE => 'فشل حفظ الملف المؤقت',
            ];
            $code = $file['error'] ?? UPLOAD_ERR_NO_FILE;
            throw new \RuntimeException($messages[$code] ?? 'خطأ غير معروف في رفع الملف');
        }

        if (!is_uploaded_file($file['tmp_name']) || !is_readable($file['tmp_name'])) {
            throw new \RuntimeException('لا يمكن قراءة الملف المرفوع');
        }
        if ((int) $file['size'] === 0) {
            throw new \RuntimeException('الملف فارغ');
        }
        if ((int) $file['size'] > self::MAX_BYTES) {
            throw new \RuntimeException('حجم الملف يتجاوز الحد الأقصى (5 ميجابايت)');
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXT, true)) {
            throw new \RuntimeException('صيغة الملف غير مدعومة. الصيغ المقبولة: xlsx, xls, csv');
        }
    }

    /** @return array{header: string[], rows: array[]} */
    private static function readCsv(string $path, int $maxRows): array
    {
        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException('تعذر قراءة محتوى الملف');
        }

        // إزالة BOM.
        if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
            $content = substr($content, 3);
        }
        // توحيد الترميز إلى UTF-8.
        if (function_exists('mb_check_encoding') && !mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1256');
        }

        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $lines = explode("\n", $content);

        $header = [];
        $rows = [];
        $first = true;
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $parsed = str_getcsv($line, ',', '"');
            $parsed = array_map(static function ($v) { return is_string($v) ? trim($v) : $v; }, $parsed);
            if ($first) {
                // إزالة BOM من أول خلية رأس.
                if (isset($parsed[0])) {
                    $parsed[0] = ltrim((string) $parsed[0], "\xEF\xBB\xBF");
                }
                $header = $parsed;
                $first = false;
                continue;
            }
            $rows[] = $parsed;
            if (count($rows) > $maxRows) {
                throw new \RuntimeException("الملف يحتوي على أكثر من {$maxRows} صف. يرجى تقسيمه.");
            }
        }
        return ['header' => $header, 'rows' => $rows];
    }

    /** @return array{header: string[], rows: array[]} */
    private static function readSpreadsheet(string $path, int $maxRows): array
    {
        if (!class_exists('ZipArchive')) {
            throw new \RuntimeException('قراءة ملفات Excel تتطلب تفعيل إضافة php_zip. استخدم CSV مؤقتاً.');
        }
        if (!class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
            throw new \RuntimeException('مكتبة PhpSpreadsheet غير متوفرة.');
        }

        try {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($path);
            $worksheet = $spreadsheet->getActiveSheet();
            $all = $worksheet->toArray(null, true, true, false);
        } catch (\Throwable $e) {
            throw new \RuntimeException('فشل قراءة الملف: ' . $e->getMessage());
        }

        if (empty($all)) {
            throw new \RuntimeException('الملف لا يحتوي على بيانات');
        }

        $header = array_map(static function ($v) { return is_string($v) ? trim($v) : (string) $v; }, array_shift($all));

        $rows = [];
        foreach ($all as $r) {
            $rows[] = array_map(static function ($v) { return is_string($v) ? trim($v) : $v; }, $r);
            if (count($rows) > $maxRows) {
                throw new \RuntimeException("الملف يحتوي على أكثر من {$maxRows} صف. يرجى تقسيمه.");
            }
        }
        return ['header' => $header, 'rows' => $rows];
    }

    /**
     * مطابقة الأعمدة: تربط كل عمود في التعريف بفهرسه في صف الرأس بالاسم،
     * مع الرجوع إلى الترتيب الموضعي عند تعذّر المطابقة بالاسم.
     *
     * @param Column[] $columns
     * @param string[] $header
     * @return int[] field => columnIndex
     */
    public static function mapColumns(array $columns, array $header): array
    {
        $normalize = static function ($s) {
            $s = (string) $s;
            // إزالة أي شرح بين قوسين أو بعد سطر جديد في الرأس.
            $s = preg_replace('/\(.*?\)/u', '', $s);
            $s = preg_replace('/\s+/u', ' ', str_replace("\n", ' ', $s));
            return trim($s);
        };

        $headerNorm = [];
        foreach ($header as $idx => $h) {
            $headerNorm[$idx] = $normalize($h);
        }

        $map = [];
        foreach ($columns as $position => $c) {
            /** @var Column $c */
            $found = null;
            $label = $normalize($c->label);
            foreach ($headerNorm as $idx => $h) {
                if ($h !== '' && ($h === $label || mb_strpos($h, $label) === 0 || mb_strpos($label, $h) === 0)) {
                    $found = $idx;
                    break;
                }
            }
            // رجوع موضعي.
            $map[$c->field] = $found !== null ? $found : $position;
        }
        return $map;
    }
}
