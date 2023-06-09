<?php

// Удаление информации о сеансе
session_start();
session_destroy();
$_SESSION = array();
// Удалить информацию о файлах cookie
setcookie('pass_word', '', time() - 420000);
header("Location: index.php");
