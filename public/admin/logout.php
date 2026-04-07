<?php
/**
 * Logout
 */

umask(0077);
ini_set('display_errors', 0);

require_once dirname(dirname(__DIR__)) . '/functions/bootstrap.php';

auth_session_start();
log_event('admin_logout');
auth_logout();
