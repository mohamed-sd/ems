<?php
/**
 * بوابة العزل / DAO الإلزامية — TenantDb (ADR-02 · المرحلة 1)
 * ───────────────────────────────────────────────────────────────────────────
 * الطبقة الوحيدة المعتمدة لوصول الشاشات المُهاجَرة إلى قاعدة البيانات.
 * العقد الكامل: docs/TENANT_GATE_CONTRACT_ar.md (المخرَج ⑥ من حزمة المراجعة).
 *
 * الضمانات البنيوية:
 *   • حقن عزل الشركة آليًا قراءةً وكتابةً — المطوّر لا يكتب company_id أبدًا.
 *   • Fail-Closed: جدول غير مسجَّل في TenantRegistry أو سياق بلا شركة أو
 *     محاولة تمرير company_id مغاير = رفض + tenant_gate_violation في السجل.
 *   • الهوية من TenantContext حصريًا (جلسة/خادم) — لا مدخل يقبل هوية عميل.
 *   • prepared statements دائمًا؛ كل المعرّفات تُتحقق ضد نمطٍ صارمٍ والسجل.
 *   • الحذف عبر البوابة ناعمٌ حصريًا (معيار القبول §4 بند ⑧) — لا DELETE خام.
 *   • تجاوز العزل (قراءة عابرة للشركات) صريحٌ فقط عبر forAllTenants() —
 *     للمدير الأعلى وحده، وكل استدعاءٍ يُسجَّل (tenant_gate_cross_tenant).
 *
 * ملاحظة نطاق: البوابة تخدم الشاشات المُهاجَرة والوحدات الجديدة. الشاشات
 * القديمة تبقى على mysqli الخام حتى دور هجرتها (خارطة R02 §3.4) — المؤشر:
 * «100% من استعلامات الشاشة المُهاجَرة عبر البوابة».
 */

namespace App\Core;

class TenantDb
{
    /** @var \mysqli */
    private $conn;
    /** @var TenantContext */
    private $ctx;
    /** @var bool وضع القراءة العابرة للشركات (super admin، مُسجَّل) */
    private $crossTenant;

    public function __construct(\mysqli $conn, TenantContext $ctx, $crossTenant = false)
    {
        $this->conn = $conn;
        $this->ctx = $ctx;
        $this->crossTenant = (bool) $crossTenant;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // العمليات العامة
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * قراءة صفوف. $opts:
     *   columns  string[] أعمدة (افتراضي *)
     *   where    array    شروط تساوٍ مرتبطة بمعاملات (col => value)
     *   whereRaw string   جزء شرطٍ حر بعلامات ? (يكتبه المطوّر لا العميل)
     *   params   array    قيم علامات whereRaw
     *   orderBy  string   "col ASC, col2 DESC"
     *   limit/offset int
     *   includeDeleted bool  ضم المحذوف ناعمًا (افتراضي: لا)
     */
    public function select($table, array $opts = array())
    {
        list($sql, $params) = $this->buildSelect($table, $opts, false);
        return $this->run($sql, $params)->fetch_all(MYSQLI_ASSOC);
    }

    /** صفٌّ واحد أو null. */
    public function selectOne($table, array $opts = array())
    {
        $opts['limit'] = 1;
        $rows = $this->select($table, $opts);
        return empty($rows) ? null : $rows[0];
    }

    /** عدد الصفوف ضمن نطاق العزل. */
    public function count($table, array $opts = array())
    {
        list($sql, $params) = $this->buildSelect($table, $opts, true);
        $row = $this->run($sql, $params)->fetch_row();
        return intval($row[0]);
    }

    /** إدراج صف — العزل يُحقن؛ تمرير company_id مغايرٍ = محاولة تزوير (رفض). */
    public function insert($table, array $data)
    {
        $def = $this->requireTable($table);
        $this->assertWritable($table, $def);

        if ($def['type'] === TenantRegistry::T_TENANT) {
            if (array_key_exists('company_id', $data)
                && intval($data['company_id']) !== $this->ctx->companyId()
                && !$this->crossTenant) {
                $this->deny('identity forgery attempt', $table . ' insert company_id=' . intval($data['company_id']));
            }
            $data['company_id'] = $this->crossTenant && array_key_exists('company_id', $data)
                ? intval($data['company_id'])
                : $this->ctx->companyId();
        } elseif ($def['type'] === TenantRegistry::T_CHILD) {
            $this->assertParentOwned($table, $def, $data);
        }

        if (empty($data)) {
            $this->deny('empty insert', $table);
        }

        $cols = array();
        $marks = array();
        $params = array();
        foreach ($data as $col => $val) {
            $this->assertIdent($col);
            $cols[] = '`' . $col . '`';
            $marks[] = '?';
            $params[] = $val;
        }
        $sql = 'INSERT INTO `' . $table . '` (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $marks) . ')';
        $this->run($sql, $params);
        return $this->conn->insert_id;
    }

    /** تحديث صفوف ضمن نطاق العزل. تغيير company_id ممنوع (إلا crossTenant). */
    public function update($table, array $data, array $where, $whereRaw = '', array $rawParams = array())
    {
        $def = $this->requireTable($table);
        $this->assertWritable($table, $def);

        if (array_key_exists('company_id', $data) && !$this->crossTenant) {
            $this->deny('identity forgery attempt', $table . ' update company_id');
        }
        if (empty($data)) {
            $this->deny('empty update', $table);
        }
        if (empty($where) && trim($whereRaw) === '') {
            $this->deny('update without where', $table);
        }

        $sets = array();
        $params = array();
        foreach ($data as $col => $val) {
            $this->assertIdent($col);
            $sets[] = '`' . $col . '` = ?';
            $params[] = $val;
        }

        list($cond, $condParams) = $this->buildWhere($table, $def, array(
            'where' => $where, 'whereRaw' => $whereRaw, 'params' => $rawParams,
            'includeDeleted' => true, // التحديث لا يستثني المحذوف ضمنيًا (قد نحدّث حالة الحذف نفسها)
        ));

        $sql = 'UPDATE `' . $table . '` SET ' . implode(', ', $sets) . ' WHERE ' . $cond;
        return (int) $this->run($sql, array_merge($params, $condParams));
    }

    /**
     * الحذف عبر البوابة ناعمٌ حصريًا (is_deleted/deleted_at/deleted_by).
     * جدول بلا أعمدة حذفٍ ناعم = العملية مرفوضة (سجّل الجدول أو أضف الأعمدة
     * بترحيل — لا DELETE خام عبر البوابة أبدًا).
     */
    public function softDelete($table, $id)
    {
        $def = $this->requireTable($table);
        if (empty($def['soft'])) {
            $this->deny('hard delete refused (no soft-delete columns)', $table);
        }
        return $this->update($table, array(
            'is_deleted' => 1,
            'deleted_at' => date('Y-m-d H:i:s'),
            'deleted_by' => $this->ctx->userId(),
        ), array('id' => intval($id)));
    }

    /**
     * القراءة العابرة للشركات — للمدير الأعلى حصرًا، وكل استدعاءٍ يُسجَّل.
     * تعيد نسخةً منفصلة؛ النسخة الأصلية تبقى معزولة.
     */
    public function forAllTenants($reason = '')
    {
        if (!$this->ctx->isSuperAdmin()) {
            $this->deny('cross-tenant escape refused (not super admin)', 'role=' . $this->ctx->role());
        }
        if (function_exists('log_security_event')) {
            $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
            $caller = isset($bt[0]['file']) ? basename($bt[0]['file']) . ':' . $bt[0]['line'] : 'unknown';
            log_security_event('tenant_gate_cross_tenant',
                'user=' . $this->ctx->userId() . ' caller=' . $caller . ($reason !== '' ? ' reason=' . $reason : ''));
        }
        return new self($this->conn, $this->ctx, true);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // البناء الداخلي
    // ─────────────────────────────────────────────────────────────────────────

    private function buildSelect($table, array $opts, $countOnly)
    {
        $def = $this->requireTable($table);

        $columns = '*';
        if (!empty($opts['columns']) && is_array($opts['columns'])) {
            $safe = array();
            foreach ($opts['columns'] as $c) {
                $this->assertIdent($c);
                $safe[] = '`' . $c . '`';
            }
            $columns = implode(', ', $safe);
        }

        list($cond, $params) = $this->buildWhere($table, $def, $opts);

        $sql = 'SELECT ' . ($countOnly ? 'COUNT(*)' : $columns) . ' FROM `' . $table . '` WHERE ' . $cond;

        if (!$countOnly && !empty($opts['orderBy'])) {
            $this->assertOrderBy($opts['orderBy']);
            $sql .= ' ORDER BY ' . $opts['orderBy'];
        }
        if (!$countOnly && isset($opts['limit'])) {
            $sql .= ' LIMIT ' . intval($opts['limit']);
            if (isset($opts['offset'])) {
                $sql .= ' OFFSET ' . intval($opts['offset']);
            }
        }
        return array($sql, $params);
    }

    /** شرط WHERE الكامل: حقن العزل + الحذف الناعم + شروط المطوّر. */
    private function buildWhere($table, array $def, array $opts)
    {
        $conds = array();
        $params = array();

        // 1) حقن العزل — القلب.
        if (!$this->crossTenant) {
            if ($def['type'] === TenantRegistry::T_TENANT) {
                $this->requireTenant($table);
                $conds[] = '`' . $table . '`.`company_id` = ?';
                $params[] = $this->ctx->companyId();
            } elseif ($def['type'] === TenantRegistry::T_CHILD) {
                $this->requireTenant($table);
                $parent = $def['parent'];
                $fk = $def['fk'];
                $this->assertIdent($parent);
                $this->assertIdent($fk);
                $conds[] = 'EXISTS (SELECT 1 FROM `' . $parent . '` __p WHERE __p.`id` = `' . $table . '`.`' . $fk . '` AND __p.`company_id` = ?)';
                $params[] = $this->ctx->companyId();
            }
            // T_GLOBAL: قراءة بلا نطاق.
        }

        // 2) استبعاد المحذوف ناعمًا (افتراضيًا).
        if (!empty($def['soft']) && empty($opts['includeDeleted'])) {
            $conds[] = 'COALESCE(`' . $table . '`.`is_deleted`, 0) = 0';
        }

        // 3) شروط تساوٍ مرتبطة.
        if (!empty($opts['where']) && is_array($opts['where'])) {
            foreach ($opts['where'] as $col => $val) {
                $this->assertIdent($col);
                if ($val === null) {
                    $conds[] = '`' . $table . '`.`' . $col . '` IS NULL';
                } else {
                    $conds[] = '`' . $table . '`.`' . $col . '` = ?';
                    $params[] = $val;
                }
            }
        }

        // 4) جزء حر (يكتبه المطوّر؛ القيم عبر ? حصريًا).
        if (!empty($opts['whereRaw'])) {
            $raw = trim($opts['whereRaw']);
            if (stripos($raw, 'company_id') !== false && !$this->crossTenant) {
                // منع التلاعب بنطاق العزل من الجزء الحر — العزل مسؤولية البوابة وحدها.
                $this->deny('company_id in whereRaw refused', $table);
            }
            $conds[] = '(' . $raw . ')';
            if (!empty($opts['params']) && is_array($opts['params'])) {
                foreach ($opts['params'] as $p) {
                    $params[] = $p;
                }
            }
        }

        if (empty($conds)) {
            $conds[] = '1=1';
        }
        return array(implode(' AND ', $conds), $params);
    }

    /** تنفيذ prepared statement وإرجاع النتيجة. */
    private function run($sql, array $params)
    {
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            $this->deny('prepare failed: ' . $this->conn->error, $sql);
        }
        if (!empty($params)) {
            $types = '';
            foreach ($params as $p) {
                $types .= is_int($p) ? 'i' : (is_float($p) ? 'd' : 's');
            }
            $stmt->bind_param($types, ...$params);
        }
        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            $this->deny('execute failed: ' . $err, $sql);
        }
        $result = $stmt->get_result();
        if ($result === false) {
            // DML (INSERT/UPDATE): نلتقط affected_rows من الـ statement قبل
            // إغلاقه — قراءته من الاتصال بعد الإغلاق غير موثوقة.
            $affected = $stmt->affected_rows;
            $stmt->close();
            return $affected;
        }
        $stmt->close();
        return $result;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // الحرّاس
    // ─────────────────────────────────────────────────────────────────────────

    private function requireTable($table)
    {
        $this->assertIdent($table);
        $def = TenantRegistry::get($table);
        if ($def === null) {
            $this->deny('unregistered table', $table);
        }
        if ($def['type'] === TenantRegistry::T_RESTRICTED) {
            $this->deny('restricted table (pending its module migration)', $table);
        }
        return $def;
    }

    private function requireTenant($table)
    {
        if (!$this->ctx->hasTenant()) {
            $this->deny('no tenant in context (fail-closed)', $table);
        }
    }

    private function assertWritable($table, array $def)
    {
        if ($def['type'] === TenantRegistry::T_GLOBAL && !$this->ctx->isSuperAdmin()) {
            $this->deny('write to global reference refused', $table);
        }
    }

    /** لإدراج ابنٍ: الأب المُشار إليه يجب أن يملكه مستأجر السياق. */
    private function assertParentOwned($table, array $def, array $data)
    {
        $fk = $def['fk'];
        $parent = $def['parent'];
        if (!isset($data[$fk])) {
            $this->deny('child insert without parent fk', $table);
        }
        if ($this->crossTenant) {
            return;
        }
        $this->requireTenant($table);
        $res = $this->run(
            'SELECT 1 FROM `' . $parent . '` WHERE `id` = ? AND `company_id` = ? LIMIT 1',
            array(intval($data[$fk]), $this->ctx->companyId())
        );
        if (!($res instanceof \mysqli_result) || $res->num_rows === 0) {
            $this->deny('child insert: parent not owned by tenant', $table . '.' . $fk . '=' . intval($data[$fk]));
        }
    }

    private function assertIdent($name)
    {
        if (!is_string($name) || !preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            $this->deny('invalid identifier', var_export($name, true));
        }
    }

    private function assertOrderBy($orderBy)
    {
        if (!preg_match('/^[A-Za-z0-9_`\.\s,]+(ASC|DESC|asc|desc)?[A-Za-z0-9_`\.\s,]*$/', $orderBy)
            || !preg_match('/^[A-Za-z0-9_`\.\s,]+$/', str_ireplace(array('ASC', 'DESC'), '', $orderBy))) {
            $this->deny('invalid orderBy', $orderBy);
        }
    }

    /** تسجيل المخالفة ثم الرفض — لا مسار فشلٍ صامت. */
    private function deny($msg, $detail = '')
    {
        if (function_exists('log_security_event')) {
            log_security_event('tenant_gate_violation',
                $msg . ($detail !== '' ? ' :: ' . substr($detail, 0, 200) : '')
                . ' | user=' . $this->ctx->userId() . ' company=' . $this->ctx->companyId());
        }
        throw new TenantGateException('TenantGate: ' . $msg);
    }
}
