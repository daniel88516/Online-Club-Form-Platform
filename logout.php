<?php
require_once 'config/session.php';
session_destroy();
header('Location: ' . APP_BASE . '/login.php');
exit();
