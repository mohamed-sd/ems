<?php
/**
 * includes/EmsDbSessionHandler.php — معالج جلسات القاعدة (NFR-13)
 * صف قاعدة لا قفل ملف — يسجله session_bootstrap.php خلف EMS_SESSION_STORE=db.
 */

require_once __DIR__ . '/env.php';

class EmsDbSessionHandler implements SessionHandlerInterface
{
    /** @var mysqli|null */
    private $db = null;
    private $ttl = 7200;

    public function open($path, $name): bool
    {
        $this->db = @new mysqli(ems_env('DB_HOST'), ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'));
        if ($this->db->connect_errno) { $this->db = null; return false; } // fail → PHP يسقط للملفات
        $this->db->set_charset('utf8mb4');
        $ini = intval(ini_get('session.gc_maxlifetime'));
        if ($ini > 0) { $this->ttl = $ini; }
        return true;
    }

    public function close(): bool
    {
        if ($this->db) { $this->db->close(); $this->db = null; }
        return true;
    }

    #[\ReturnTypeWillChange]
    public function read($id)
    {
        if (!$this->db) { return ''; }
        $stmt = $this->db->prepare("SELECT sess_data FROM ems_sessions WHERE sess_id = ? AND expires_at >= NOW()");
        $stmt->bind_param('s', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row && $row['sess_data'] !== null ? (string) $row['sess_data'] : '';
    }

    public function write($id, $data): bool
    {
        if (!$this->db) { return false; }
        $stmt = $this->db->prepare(
            "INSERT INTO ems_sessions (sess_id, sess_data, expires_at)
             VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND))
             ON DUPLICATE KEY UPDATE sess_data = VALUES(sess_data), expires_at = VALUES(expires_at)");
        $stmt->bind_param('ssi', $id, $data, $this->ttl);
        $ok = $stmt->execute();
        $stmt->close();
        return (bool) $ok;
    }

    public function destroy($id): bool
    {
        if (!$this->db) { return false; }
        $stmt = $this->db->prepare("DELETE FROM ems_sessions WHERE sess_id = ?");
        $stmt->bind_param('s', $id);
        $stmt->execute();
        $stmt->close();
        return true;
    }

    #[\ReturnTypeWillChange]
    public function gc($max_lifetime)
    {
        if (!$this->db) { return 0; }
        $this->db->query("DELETE FROM ems_sessions WHERE expires_at < NOW()");
        return $this->db->affected_rows;
    }
}
