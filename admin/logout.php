<?php
require_once __DIR__ . '/../config/config.php';
unset($_SESSION['employee_id'], $_SESSION['employee_name'], $_SESSION['employee_role']);
redirect('login.php');
