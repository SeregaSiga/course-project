<?php
require_once("class/apiFunc.php");
$apiFunc = new apiFunc();

if( $_SESSION["active"] !== "on"){
    
    $file = 'app.db';
    
    // Объявляется в двоичной форме
    header('Content-Type: application/octet-stream'); 
     
    //　Это связано с тем, что файл обычно загружается под именем запущенного PHP-скрипта,
    // Сообщите браузеру имя и попросите его загрузить файл по имени.
    header('Content-Disposition: attachment; filename='.$file."");
     
    $file_size = filesize($file);
     
    //　Получить размер файла, чтобы отображался прогресс загрузки в диалоге
    header('Content-Length: '.$file_size);
     
    // Чтение фактического файла
    readfile($file);

}
?>