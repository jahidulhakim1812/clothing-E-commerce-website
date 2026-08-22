<?php
/**
 * Admin Bootstrap — loads config, session, and enforces employee login.
 * This file produces NO HTML output, so it is safe to require at the very
 * top of any admin page BEFORE running POST-handling logic that may call
 * redirect() (which needs to send a Location header before any output).
 *
 * admin_header.php (which does output HTML) requires this file too, so it
 * is always safe/idempotent to require this on its own first.
 */
require_once __DIR__ . '/../../config/config.php';
requireEmployeeLogin();
$currentPage = basename($_SERVER['PHP_SELF']);
