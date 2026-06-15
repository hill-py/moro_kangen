<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['id_user']) && isset($_SESSION['role']);
}

function isKaryawan() {
    return isLoggedIn() && $_SESSION['role'] === 'karyawan';
}

function isPemilik() {
    return isLoggedIn() && $_SESSION['role'] === 'pemilik';
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ../auth/login.php');
        exit;
    }
}

function requireKaryawan() {
    requireLogin();

    if (!isKaryawan()) {
        header('Location: ../dashboard/index.php');
        exit;
    }
}

function currentUser() {
    if (!isLoggedIn()) {
        return null;
    }

    return [
        'id_user' => $_SESSION['id_user'],
        'username' => $_SESSION['username'],
        'role' => $_SESSION['role'],
    ];
}

function setUserSession($user) {
    $_SESSION['id_user'] = $user['id_user'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
}

function destroySession() {
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }

    session_destroy();
}
