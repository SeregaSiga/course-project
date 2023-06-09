<?php

class apiFunc {

    // Определяет, используется ли связь Ajax.
    public function is_ajax(){
        return isset($_SERVER['HTTP_X_REQUESTED_WITH'])&&strtolower($_SERVER['HTTP_X_REQUESTED_WITH'])=='xmlhttprequest';
    }

    // Определяет, является ли это сообщение POST.
    public function is_post() {
        if($_SERVER["REQUEST_METHOD"] === "POST"){
            return true;
        }else{
            return false;
        }
    }

    // Определяет, является ли это сообщение GET
    public function is_get() {
        if($_SERVER["REQUEST_METHOD"] === "GET"){
            return true;
        }else{
            return false;
        }
    }

    /** CSRF запущен (хэш создан).
     *
     * @param session_column string Имя ключа сессии
     * @param csrf_hash Имя хэша для генерации пароля
     */
    public function createCsrf($session_column, $csrf_hash){
        $_SESSION[$session_column] = password_hash($csrf_hash, PASSWORD_DEFAULT);
    }

    /** Функция проверки CSRF.
    *
     * @param session_column string Имя ключа сеанса.
     * @param csrf_key имя передаваемого ключа
     * @result boolean
     */
    public function chkCsrf($session_column, $csrf_key){
        if( $_SESSION[$session_column] === $csrf_key ){
            return true;
        } else {
            return false;
        }
    }


    /**
     * Проверка соединения с БД
     */
    public function chkDbConnect($db_type, $db_host, $db_name, $db_accout, $db_pass, $db_port){

        // Строка подключения переключателя
        switch($db_type) {
            case "mysql":
                $dsn = 'mysql:dbname='.$db_name.';host='.$db_host.';charset=utf8';
            break;
            case "postgres":
                $dsn = 'pgsql:dbname='.$db_name.';host='.$db_host.';port=5432';
            break;
            default:
            break;
        }

        try {
            $dbh = new PDO($dsn, $db_accout, $db_pass);
        } catch (PDOException $e)
        {
            return false;
            // die('Error:' . $e->getMessage());
        }
        return true;

    }

}
