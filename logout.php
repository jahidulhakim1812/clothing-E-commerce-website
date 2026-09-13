<?php
require_once __DIR__ . '/config/config.php';
unset($_SESSION['customer_id'], $_SESSION['customer_name']);
redirect('index.php');
