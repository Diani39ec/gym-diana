<?php require_once __DIR__.'/config.php';
if(!empty($_SESSION['uid'])) logAction((int)$_SESSION['uid'],'logout');
session_unset(); session_destroy(); header('Location: index.php'); exit;
