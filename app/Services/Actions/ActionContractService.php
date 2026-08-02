<?php
/**
 * خدمةُ عقد الفعل — ActionContractService (ACT-01 §8-①⑤)
 * ───────────────────────────────────────────────────────────────────────────
 * «لا يُبنى زرٌّ في النظام إلا وله عقدُ فعلٍ مسجَّل» — وهذه بوابةُ التسجيل
 * والعكس:
 *   register(): كودٌ مكرر → 409 · معالجٌ غيرُ موجودٍ → 422 ·
 *               فعلُ كتابةٍ بلا حرّاس → 422 · ماليٌّ بلا عكسٍ → 422.
 *   reverse():  عكسٌ بلا مرجعِ أصلٍ → 422 · حذفٌ بدل عكسٍ → 403 بنيويًّا.
 *   logExecution(): سجلُّ التنفيذ سماحًا أو منعًا — Insert-only، ومنه يُقاس
 *               أيُّ حارسٍ يعيق العمل.
 *
 * گوتشا المستودع: mysqli لا يرمي — كلُّ مُرجَعٍ يُفحص.
 */

namespace App\Services\Actions;

class ActionContractService
{
    /** @var \mysqli */
    private $conn;

    public function __construct($conn) { $this->conn = $conn; }

    /**
     * تسجيلُ عقد فعلٍ — Validation بترتيب ACT-01 §8-①.
     * @return array{ok:bool,code:int,reason:string}
     */
    public function register(array $c)
    {
        $code = trim($c['action_code'] ?? '');
        if ($code === '') return array('ok' => false, 'code' => 422, 'reason' => 'بلا كود فعل');

        // كودٌ مكرر → 409
        $st = mysqli_prepare($this->conn, 'SELECT 1 FROM actions WHERE action_code = ? LIMIT 1');
        mysqli_stmt_bind_param($st, 's', $code);
        mysqli_stmt_execute($st);
        if (mysqli_stmt_fetch($st)) { mysqli_stmt_close($st); return array('ok' => false, 'code' => 409, 'reason' => 'كودُ الفعل مسجَّلٌ من قبل'); }
        mysqli_stmt_close($st);

        // معالجٌ غيرُ موجودٍ → 422 (يُفحص وجودُ الصنف والدالة لا الاسمُ فقط — الفحص ②)
        $cls = $c['handler_class'] ?? null; $meth = $c['handler_method'] ?? null; $path = $c['handler_path'] ?? null;
        if ($cls !== null) {
            if (!class_exists($cls) || ($meth !== null && !method_exists($cls, $meth)))
                return array('ok' => false, 'code' => 422, 'reason' => "المعالجُ غيرُ موجود: {$cls}::{$meth}");
        } elseif ($path !== null) {
            if (!is_file(dirname(__DIR__, 3) . '/' . ltrim($path, '/')))
                return array('ok' => false, 'code' => 422, 'reason' => "ملفُّ المعالج غيرُ موجود: {$path}");
        } else {
            return array('ok' => false, 'code' => 422, 'reason' => 'فعلٌ بلا معالجٍ أصلًا');
        }

        $isWrite = (int)($c['is_write'] ?? 0);
        $guards  = $c['guards'] ?? array();
        // فعلُ كتابةٍ بلا حرّاس → 422 (القاعدة ③ — والقراءةُ وحدَها تُعفى)
        if ($isWrite && empty($guards))
            return array('ok' => false, 'code' => 422, 'reason' => 'فعلُ كتابةٍ بلا حارسٍ معلن');

        $isFin = (int)($c['is_financial'] ?? 0);
        $rev   = $c['reverse_action_code'] ?? null;
        // ماليٌّ أو تعاقديٌّ بلا عكسٍ → 422 (القاعدة ⑧ — ولا عكسَ بحذفٍ ولا بتعديل)
        if ($isFin && ($rev === null || $rev === ''))
            return array('ok' => false, 'code' => 422, 'reason' => 'فعلٌ ماليٌّ بلا فعلِ عكسٍ معرَّف');

        $st = mysqli_prepare($this->conn,
            'INSERT INTO actions (action_code, name_ar, module_id, placement, handler_class, handler_method,
                                  handler_path, is_write, guards_json, precondition_expr, reverse_action_code,
                                  is_financial, owner_doc, active)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,1)');
        $name = $c['name_ar'] ?? $code;
        $mod  = isset($c['module_id']) ? (int)$c['module_id'] : null;
        $plc  = $c['placement'] ?? 'row';
        $gj   = json_encode(array_values($guards), JSON_UNESCAPED_UNICODE);
        $pre  = $c['precondition_expr'] ?? null;
        $doc  = $c['owner_doc'] ?? null;
        mysqli_stmt_bind_param($st, 'ssissssissssis', $code, $name, $mod, $plc, $cls, $meth, $path,
                               $isWrite, $gj, $pre, $rev, $isFin, $doc);
        $ok = mysqli_stmt_execute($st);
        mysqli_stmt_close($st);
        if (!$ok) return array('ok' => false, 'code' => 500, 'reason' => mysqli_error($this->conn));
        return array('ok' => true, 'code' => 201, 'reason' => 'سُجّل');
    }

    /**
     * تنفيذُ فعل العكس المعرَّف — ولا عكسَ بحذف (ReverseService §8-⑤).
     * لا ينفّذ المنطقَ الماليَّ نفسَه (ذاك لخدمة المجال) — يتحقق من العقد ويسجّل.
     * @return array{ok:bool,code:int,reason:string,reverse_code:?string}
     */
    public function resolveReverse($actionCode, $originalRef)
    {
        if ($originalRef === null || $originalRef === '')
            return array('ok' => false, 'code' => 422, 'reason' => 'عكسٌ بلا مرجعِ أصل', 'reverse_code' => null);
        $st = mysqli_prepare($this->conn, 'SELECT reverse_action_code, is_financial FROM actions WHERE action_code = ? AND active = 1');
        mysqli_stmt_bind_param($st, 's', $actionCode);
        mysqli_stmt_execute($st);
        mysqli_stmt_bind_result($st, $rev, $fin);
        if (!mysqli_stmt_fetch($st)) { mysqli_stmt_close($st); return array('ok' => false, 'code' => 404, 'reason' => 'فعلٌ غيرُ مسجَّل', 'reverse_code' => null); }
        mysqli_stmt_close($st);
        if ($fin && !$rev)
            return array('ok' => false, 'code' => 403, 'reason' => 'ماليٌّ بلا عكسٍ معرَّف — والحذفُ بدل العكس ممنوعٌ بنيويًّا', 'reverse_code' => null);
        return array('ok' => true, 'code' => 200, 'reason' => 'العكسُ معرَّف', 'reverse_code' => $rev);
    }

    /** سجلُّ التنفيذ — Insert-only (§8-⑥). */
    public function logExecution($companyId, $actionCode, $personId, $subjectRef, $allowed, $deniedByGuard = null)
    {
        $st = mysqli_prepare($this->conn,
            'INSERT INTO action_execution_log (company_id, action_code, person_id, subject_ref, result, denied_by_guard, ip)
             VALUES (?,?,?,?,?,?,?)');
        $res = $allowed ? 'allowed' : 'denied';
        $ip  = $_SERVER['REMOTE_ADDR'] ?? null;
        mysqli_stmt_bind_param($st, 'sisssss', $companyId, $actionCode, $personId, $subjectRef, $res, $deniedByGuard, $ip);
        $ok = mysqli_stmt_execute($st);
        mysqli_stmt_close($st);
        return (bool)$ok;
    }
}
