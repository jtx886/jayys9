<?php
require_once __DIR__ . '/includes.php';
unset($_SESSION['admin_id']);
redirect('login.php');
