<?php
require_once __DIR__ . '/includes/auth.php';

if (super_admin_is_logged_in()) {
    super_admin_redirect('dashboard');
}

super_admin_redirect('login');