<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';

session_init();
logout();
redirect('/login.php');
