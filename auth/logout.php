<?php
require_once '../config/session.php';

destroySession();
header('Location: ../auth/login.php');
exit;
