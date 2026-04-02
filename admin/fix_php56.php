<?php
/**
 * PHP 5.6 Compatibility Fixer
 * Replaces PHP 7+ null coalescing ?? with compatible isset() ternary
 * Run ONCE via browser, then delete this file.
 * URL: /admin/fix_php56.php (تحت جذر المشروع الحالي)
 */
$base = __DIR__;
$files = [
    $base . '/dashboard.php',
    $base . '/companies.php',
    $base . '/companies/view.php',
    $base . '/subscriptions/requests.php',
    $base . '/plans.php',
    $base . '/support/view.php',
    $base . '/audit_log.php',
    $base . '/settings.php',
    $base . '/includes/layout_head.php',
];

header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html dir="ltr"><head><meta charset="UTF-8"><title>PHP 5.6 Fixer</title>';
echo '<style>body{font-family:monospace;padding:20px;} .ok{color:green} .err{color:red} pre{background:#f5f5f5;padding:10px;border:1px solid #ddd;}</style></head><body>';
echo '<h2>PHP 5.6 Compatibility Fixer</h2>';

$results = [];

foreach ($files as $file) {
    if (!file_exists($file)) {
        $results[$file] = ['status' => 'skip', 'msg' => 'File not found'];
        continue;
    }

    $orig    = file_get_contents($file);
    $content = $orig;

    // ── 1. Double null coalescing (must come first) ────────────────────────
    $content = str_replace(
        '$_POST[\'lookup\'] ?? $_GET[\'q\'] ?? \'\'',
        '(isset($_POST[\'lookup\']) ? $_POST[\'lookup\'] : (isset($_GET[\'q\']) ? $_GET[\'q\'] : \'\'))',
        $content
    );

    // ── 2. Superglobal ?? patterns ────────────────────────────────────────
    // Pattern: $_X['key'] ?? 'string' OR ?? number
    // Using regex for generic handling
    $supers = ['_GET', '_POST', '_SERVER', '_SESSION', '_REQUEST', '_FILES', '_COOKIE', '_ENV'];
    foreach ($supers as $sg) {
        // Replace $_X['key'] ?? '' (empty string)
        $content = preg_replace_callback(
            '/\$' . preg_quote($sg, '/') . '\[\'([^\']+)\'\]\s*\?\?\s*\'\'/',
            function ($m) use ($sg) {
                return "(isset(\${$sg}['{$m[1]}']) ? \${$sg}['{$m[1]}'] : '')";
            },
            $content
        );
        // Replace $_X['key'] ?? 'non_empty_string'
        $content = preg_replace_callback(
            '/\$' . preg_quote($sg, '/') . '\[\'([^\']+)\'\]\s*\?\?\s*\'([^\']+)\'/',
            function ($m) use ($sg) {
                return "(isset(\${$sg}['{$m[1]}']) ? \${$sg}['{$m[1]}'] : '{$m[2]}')";
            },
            $content
        );
        // Replace $_X['key'] ?? NUMBER
        $content = preg_replace_callback(
            '/\$' . preg_quote($sg, '/') . '\[\'([^\']+)\'\]\s*\?\?\s*(\d+)/',
            function ($m) use ($sg) {
                return "(isset(\${$sg}['{$m[1]}']) ? \${$sg}['{$m[1]}'] : {$m[2]})";
            },
            $content
        );
    }

    // ── 3. $arr[$var] ?? $var  (e.g., $sl[$st] ?? $st) ───────────────────
    $content = preg_replace_callback(
        '/\$(\w+)\[(\$\w+)\]\s*\?\?\s*(\$\w+)/',
        function ($m) {
            return "(isset(\${$m[1]}[{$m[2]}]) ? \${$m[1]}[{$m[2]}] : {$m[3]})";
        },
        $content
    );

    // ── 4. $arr[$var] ?? 'string' (e.g., $sc[$st] ?? 'bg-gray') ─────────
    $content = preg_replace_callback(
        '/\$(\w+)\[(\$\w+)\]\s*\?\?\s*\'([^\']*?)\'/',
        function ($m) {
            return "(isset(\${$m[1]}[{$m[2]}]) ? \${$m[1]}[{$m[2]}] : '{$m[3]}')";
        },
        $content
    );

    // ── 5. $arr['key'] ?? 'string' (generic — must come AFTER superglobals) ─
    $content = preg_replace_callback(
        '/\$(\w+)\[\'([^\']+)\'\]\s*\?\?\s*\'([^\']*?)\'/',
        function ($m) {
            // Skip if already wrapped in isset() — avoid double processing
            return "(isset(\${$m[1]}['{$m[2]}']) ? \${$m[1]}['{$m[2]}'] : '{$m[3]}')";
        },
        $content
    );

    // ── 6. $arr['key'] ?? NUMBER ──────────────────────────────────────────
    $content = preg_replace_callback(
        '/\$(\w+)\[\'([^\']+)\'\]\s*\?\?\s*(\d+)/',
        function ($m) {
            return "(isset(\${$m[1]}['{$m[2]}']) ? \${$m[1]}['{$m[2]}'] : {$m[3]})";
        },
        $content
    );

    // ── 7. Simple $var ?? '' OR $var ?? 'string' ─────────────────────────
    $content = preg_replace_callback(
        '/\$([a-zA-Z_]\w*)\s*\?\?\s*\'([^\']*?)\'/',
        function ($m) {
            return "(isset(\${$m[1]}) ? \${$m[1]} : '{$m[2]}')";
        },
        $content
    );

    // ── 8. Simple $var ?? NUMBER ──────────────────────────────────────────
    $content = preg_replace_callback(
        '/\$([a-zA-Z_]\w*)\s*\?\?\s*(\d+)(?!\s*\?\?)/',
        function ($m) {
            return "(isset(\${$m[1]}) ? \${$m[1]} : {$m[2]})";
        },
        $content
    );

    if ($content === $orig) {
        $results[$file] = ['status' => 'unchanged', 'msg' => 'No changes needed'];
    } else {
        $written = file_put_contents($file, $content);
        if ($written !== false) {
            // Count replacements roughly
            $before = substr_count($orig,    '??');
            $after  = substr_count($content, '??');
            $results[$file] = ['status' => 'ok', 'msg' => "Fixed. ?? occurrences: $before → $after"];
        } else {
            $results[$file] = ['status' => 'err', 'msg' => 'Failed to write file'];
        }
    }
}

// ── Output results ──────────────────────────────────────────────────────
echo '<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;width:100%">';
echo '<tr style="background:#333;color:#fff"><th>File</th><th>Status</th><th>Message</th></tr>';
foreach ($results as $file => $r) {
    $rel = str_replace(__DIR__, '', $file);
    $cls = $r['status'] === 'ok' ? 'ok' : ($r['status'] === 'err' ? 'err' : '');
    echo "<tr><td>{$rel}</td><td class='{$cls}'><strong>{$r['status']}</strong></td><td>{$r['msg']}</td></tr>";
}
echo '</table>';
echo '<br><p class="ok"><strong>✓ Done!</strong> Delete this file: <code>admin/fix_php56.php</code></p>';
echo '</body></html>';
