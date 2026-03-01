<?php

if (!function_exists('ems_start_output_compression')) {
    function ems_start_output_compression()
    {
        if (php_sapi_name() === 'cli') {
            return;
        }

        if (headers_sent()) {
            return;
        }

        if (!extension_loaded('zlib')) {
            return;
        }

        if (ini_get('zlib.output_compression')) {
            return;
        }

        if (ob_get_level() === 0) {
            ob_start('ob_gzhandler');
        }
    }
}

if (!function_exists('ems_apply_runtime_tuning')) {
    function ems_apply_runtime_tuning()
    {
        ini_set('realpath_cache_size', '4096K');
        ini_set('realpath_cache_ttl', '600');
        ini_set('max_input_vars', '4000');
    }
}

if (!function_exists('ems_optimize_db_session')) {
    function ems_optimize_db_session($conn)
    {
        if (!$conn || !($conn instanceof mysqli)) {
            return;
        }

        @mysqli_query($conn, "SET SESSION sql_big_selects = 1");
        @mysqli_query($conn, "SET SESSION group_concat_max_len = 8192");
    }
}

if (!function_exists('ems_get_pagination')) {
    function ems_get_pagination($defaultPerPage = 25, $maxPerPage = 200)
    {
        $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
        $perPage = isset($_GET['per_page']) ? intval($_GET['per_page']) : $defaultPerPage;

        if ($page < 1) {
            $page = 1;
        }

        if ($perPage < 1) {
            $perPage = $defaultPerPage;
        }

        if ($perPage > $maxPerPage) {
            $perPage = $maxPerPage;
        }

        $offset = ($page - 1) * $perPage;

        return [
            'page' => $page,
            'per_page' => $perPage,
            'offset' => $offset,
            'limit_sql' => " LIMIT {$offset}, {$perPage} "
        ];
    }
}

if (!function_exists('ems_performance_bootstrap')) {
    function ems_performance_bootstrap()
    {
        ems_apply_runtime_tuning();
        ems_start_output_compression();
    }
}
