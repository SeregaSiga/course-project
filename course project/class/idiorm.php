<?php
    
    class ORM implements ArrayAccess {
        // ----------------------- //
        // --- КЛАСС КОНСТАНТЫ --- //
        // ----------------------- //
        // Массив условий WHERE и HAVING
        const CONDITION_FRAGMENT = 0;
        const CONDITION_VALUES = 1;
        const DEFAULT_CONNECTION = 'default';
        // Предельный объем условий
        const LIMIT_STYLE_TOP_N = "top";
        const LIMIT_STYLE_LIMIT = "limit";
        // ------------------------ //
        // --- КЛАСС СВОЙСТВ --- //
        // ------------------------ //
        // Класс конфигурации
        protected static $_default_config = array(
            'connection_string' => 'sqlite::memory:',
            'id_column' => 'id',
            'id_column_overrides' => array(),
            'error_mode' => PDO::ERRMODE_EXCEPTION,
            'username' => null,
            'password' => null,
            'driver_options' => null,
            'identifier_quote_character' => null, // если значение равно null, будет определено автоматически
            'limit_clause_style' => null, // если значение равно null, будет определено автоматически
            'logging' => false,
            'logger' => null,
            'caching' => false,
            'caching_auto_clear' => false,
            'return_result_sets' => false,
        );
        // Карта настроек конфигурации
        protected static $_config = array();
        // Map of database connections, instances of the PDO class
        protected static $_db = array();
        // Последний выполненный запрос, заполняется, только если включено ведение журнала
        protected static $_last_query;
        // Журнал всех выполненных запросов, сопоставленных по ключу соединения, заполняется только в том случае, если включено ведение журнала
        protected static $_query_log = array();
        // Кэш запросов, используется только в том случае, если кэширование запросов включено
        protected static $_query_cache = array();
        // Ссылка на ранее использованный объект PDOStatement для обеспечения низкоуровневого доступа, если это необходимо
        protected static $_last_statement = null;
        // --------------------------- //
        // --- СВОЙСТВА ЭКЗЕМПЛЯРА --- //
        // --------------------------- //
        // Ключевое имя соединений в self::$_db, используемых этим экземпляром
        protected $_connection_name;
        // Имя таблицы, с которой связан текущий экземпляр ORM
        protected $_table_name;
        // Псевдоним для таблицы, который будет использоваться в запросах SELECT
        protected $_table_alias = null;
        // Значения, которые необходимо привязать к запросу
        protected $_values = array();
        // Колонки для выбора в результатах
        protected $_result_columns = array('*');
        // Используем ли мы столбец результатов по умолчанию или они были изменены вручную?
        protected $_using_default_result_columns = true;
        // Присоединение к источникам
        protected $_join_sources = array();
        // Должен ли запрос включать ключевое слово DISTINCT?
        protected $_distinct = false;
        // Это исходный запрос?
        protected $_is_raw_query = false;
        // Исходный запрос
        protected $_raw_query = '';
        // Исходные параметры запроса
        protected $_raw_parameters = array();
        // Массив предложений WHERE
        protected $_where_conditions = array();
        // ОГРАНИЧЕНИЕ
        protected $_limit = null;
        // ВЫВОД
        protected $_offset = null;
        // УПОРЯДОЧИВАТЬ
        protected $_order_by = array();
        // ГРУППИРОВАТЬ
        protected $_group_by = array();
        // ИМЕТЬ
        protected $_having_conditions = array();
        // Данные для гидратированного экземпляра класса
        protected $_data = array();
        // Поля, которые были изменены в течение
        // времени жизни объекта
        protected $_dirty_fields = array();
        // Поля, которые должны быть вставлены в DB raw
        protected $_expr_fields = array();
        // Является ли этот объект новым (был ли вызван вызов create())?
        protected $_is_new = false;
        // Имя столбца, который будет использоваться в качестве первичного ключа для
        // данного экземпляра. Отменяет настройки конфигурации.
        protected $_instance_id_column = null;
        // ---------------------- //
        // --- СТАТИЧЕСКИЙ МЕТОД --- //
        // ---------------------- //
        /**
         * Передача параметров конфигурации в класс в виде
         * пар ключ/значение. В качестве сокращения, если второй аргумент
         * опущен и ключ является строкой, то настройка
         * принимается за строку DSN, используемую PDO для подключения
         * к базе данных (часто это единственная конфигурация.
         * требуемая для использования Idiorm). Если у вас несколько параметров
         * которые вы хотите сконфигурировать, другим способом будет передача массива
         * настроек (и опустить второй аргумент).
         * @param string $key
         * @param mixed $value
         * @param string $connection_name Какое соединение использовать
         */
        public static function configure($key, $value = null, $connection_name = self::DEFAULT_CONNECTION) {
            self::_setup_db_config($connection_name); //обеспечивает установку, по крайней мере, конфигурации по умолчанию
            if (is_array($key)) {
                // Сокращение: Если передан только один аргумент массива,
                // предположим, что это массив настроек конфигурации
                foreach ($key as $conf_key => $conf_value) {
                    self::configure($conf_key, $conf_value, $connection_name);
                }
            } else {
                if (is_null($value)) {
                    // Сокращение: Если передан только один строковый аргумент,
                    // предположим, что это строка подключения
                    $value = $key;
                    $key = 'connection_string';
                }
                self::$_config[$connection_name][$key] = $value;
            }
        }
        /**
         * Получение параметров конфигурации по ключу или в виде целого массива.
         * @param string $ключ
         * @param string $имя_соединения Какое соединение использовать
         */
        public static function get_config($key = null, $connection_name = self::DEFAULT_CONNECTION) {
            if ($key) {
                return self::$_config[$connection_name][$key];
            } else {
                return self::$_config[$connection_name];
            }
        }
        /**
         * Удалить все конфигурации в массиве _config.
         */
        public static function reset_config() {
            self::$_config = array();
        }

        /**
         * Несмотря на свое немного странное название, на самом деле это завод
         * метод, используемый для получения экземпляров класса. Он называется
         * таким образом, ради читабельного интерфейса, т.е.
         * ORM::for_table('имя_таблицы')->find_one()-> и т.д. Таким образом,
         * обычно это будет первый метод, вызываемый в цепочке.
         * @param string $table_name
         * @param string $connection_name Which connection to use
         * @return ORM
         */
        public static function for_table($table_name, $connection_name = self::DEFAULT_CONNECTION) {
            self::_setup_db($connection_name);
            return new self($table_name, array(), $connection_name);
        }
        /**
         * Установите соединение с базой данных, используемое классомУстановите соединение с базой данных, используемое классом
         * @param string $connection_name Какое соединение использовать
         */
        protected static function _setup_db($connection_name = self::DEFAULT_CONNECTION) {
            if (!array_key_exists($connection_name, self::$_db) ||
                !is_object(self::$_db[$connection_name])) {
                self::_setup_db_config($connection_name);
                $db = new PDO(
                    self::$_config[$connection_name]['connection_string'],
                    self::$_config[$connection_name]['username'],
                    self::$_config[$connection_name]['password'],
                    self::$_config[$connection_name]['driver_options']
                );
                $db->setAttribute(PDO::ATTR_ERRMODE, self::$_config[$connection_name]['error_mode']);
                // 20161011 Добавить
                $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);

                self::set_db($db, $connection_name);
            }
        }
       /**
        * Убедитесь, что конфигурация (несколько соединений) установлена как минимум по умолчанию.
        * @param string $connection_name Какое соединение использовать
        */
        protected static function _setup_db_config($connection_name) {
            if (!array_key_exists($connection_name, self::$_config)) {
                self::$_config[$connection_name] = self::$_default_config;
            }
        }
        /**
         * Установите объект PDO, используемый Idiorm для связи с базой данных.
         * Это общедоступно на тот случай, если ORM будет использовать готовое постоянное значение
         * Объект PDO в качестве соединения с базой данных. Принимает необязательный строковый ключ
         * для идентификации соединения, если используется несколько соединений.
         * @param PDO $db
         * @param string $connection_name Какое соединение использовать
         */
        public static function set_db($db, $connection_name = self::DEFAULT_CONNECTION) {
            self::_setup_db_config($connection_name);
            self::$_db[$connection_name] = $db;
            if(!is_null(self::$_db[$connection_name])) {
                self::_setup_identifier_quote_character($connection_name);
                self::_setup_limit_clause_style($connection_name);
            }
        }
        /**
         * Удалите все зарегистрированные объекты PDO в массиве _db.
         */
        public static function reset_db() {
            self::$_db = array();
        }
        /**
         * Определить и инициализировать символ, используемый для цитирования идентификаторов
         * (имена таблиц, столбцов и т.д.). Если это было указано
         * вручную с помощью ORM::configure('identifier_quote_character', 'some-char'),
         * это ничего не даст.
         * @param string $connection_name Какое соединение использовать
         */
        protected static function _setup_identifier_quote_character($connection_name) {
            if (is_null(self::$_config[$connection_name]['identifier_quote_character'])) {
                self::$_config[$connection_name]['identifier_quote_character'] =
                    self::_detect_identifier_quote_character($connection_name);
            }
        }
        /**
         * Определите и инициализируйте стиль предложения ограничения ("SELECT TOP 5" /
         * "... LIMIT 5"). If this has been specified manually using
         * ORM::configure('limit_clause_style', 'top'), это ничего не даст.
         * @param string $connection_name Какое соединение использовать
         */
        public static function _setup_limit_clause_style($connection_name) {
            if (is_null(self::$_config[$connection_name]['limit_clause_style'])) {
                self::$_config[$connection_name]['limit_clause_style'] =
                    self::_detect_limit_clause_style($connection_name);
            }
        }
        /**
         * Верните правильный символ, используемый для цитирования идентификаторов (таблица
         * имена, имена столбцов и т.д.), посмотрев на драйвер, используемый PDO.
         * @param string $connection_name Какое соединение использовать
         * @return string
         */
        protected static function _detect_identifier_quote_character($connection_name) {
            switch(self::get_db($connection_name)->getAttribute(PDO::ATTR_DRIVER_NAME)) {
                case 'pgsql':
                case 'sqlsrv':
                case 'dblib':
                case 'mssql':
                case 'sybase':
                case 'firebird':
                    return '"';
                case 'mysql':
                case 'sqlite':
                case 'sqlite2':
                default:
                    return '`';
            }
        }
        /**
         * Возвращает константу после определения соответствующего предельного условия
         * стиль
         * @param string $connection_name Какое соединение использовать
         * @return string Ключевое слово/константа стиля ограничительной части
         */
        protected static function _detect_limit_clause_style($connection_name) {
            switch(self::get_db($connection_name)->getAttribute(PDO::ATTR_DRIVER_NAME)) {
                case 'sqlsrv':
                case 'dblib':
                case 'mssql':
                    return ORM::LIMIT_STYLE_TOP_N;
                default:
                    return ORM::LIMIT_STYLE_LIMIT;
            }
        }
        /**
         * Возвращает экземпляр PDO, используемый ORM для связи с
         * базы данных. Это может быть вызвано, если любой низкоуровневый доступ к БД является
         * требуется за пределами класса. Если используется несколько соединений,
         * принимает необязательное имя ключа для соединения.
         * @param string $connection_name Какое соединение использовать
         * @return PDO
         */
        public static function get_db($connection_name = self::DEFAULT_CONNECTION) {
            self::_setup_db($connection_name); // требуется на случай, если этот вызов произойдет до того, как Idiorm будет инстанцирован
            return self::$_db[$connection_name];
        }
        /**
         * Выполняет необработанный запрос в качестве обертки для PDOStatement::execute.
         * Полезен для запросов, которые не могут быть выполнены через Idiorm,
         * особенно те, которые используют специфические особенности двигателя.
         * @example raw_execute('SELECT `name`, AVG(`order`) FROM `customer` GROUP BY `name` HAVING AVG(`order`) > 10')
         * @example raw_execute('INSERT OR REPLACE INTO `widget` (`id`, `name`) SELECT `id`, `name` FROM `other_table`')
         * @param string $query The raw SQL query
         * @param array  $parameters Optional bound parameters
         * @param string $connection_name Какое соединение использовать
         * @return bool Success
         */
        public static function raw_execute($query, $parameters = array(), $connection_name = self::DEFAULT_CONNECTION) {
            self::_setup_db($connection_name);
            return self::_execute($query, $parameters, $connection_name);
        }
        /**
         * Возвращает экземпляр PDOStatement, который в последний раз использовался любым соединением, обернутым ORM.
         * Используется для доступа к PDOStatement::rowCount() или информации об ошибке
         * @return PDOStatement
         */
        public static function get_last_statement() {
            return self::$_last_statement;
        }
       /**
        * Внутренний вспомогательный метод для выполнения уставок. Регистрирует запросы и
        * хранит объект statement в ::_last_statment, доступный публично
        * через ::get_last_statement()
        * @param string $query
        * @param array $parameters Массив параметров для привязки к запросу
        * @param string $connection_name Какое соединение использовать
        * @return bool Ответ PDOStatement::execute()
        */
        protected static function _execute($query, $parameters = array(), $connection_name = self::DEFAULT_CONNECTION) {
            $statement = self::get_db($connection_name)->prepare($query);
            self::$_last_statement = $statement;
            $time = microtime(true);
            foreach ($parameters as $key => &$param) {
                if (is_null($param)) {
                    $type = PDO::PARAM_NULL;
                } else if (is_bool($param)) {
                    $type = PDO::PARAM_BOOL;
                } else if (is_int($param)) {
                    $type = PDO::PARAM_INT;
                } else {
                    $type = PDO::PARAM_STR;
                }
                $statement->bindParam(is_int($key) ? ++$key : $key, $param, $type);
            }
            $q = $statement->execute();
            self::_log_query($query, $parameters, $connection_name, (microtime(true)-$time));
            return $q;
        }
        /**
         * Добавьте запрос во внутренний журнал запросов. Работает только в том случае, если
         * Опция конфигурации 'logging' имеет значение true.
         *
         * Это работает путем ручного привязывания параметров к запросу - параметр
         * запрос не выполняется подобным образом (PDO обычно передает запрос и
         * параметры в базу данных, которая заботится о привязке), но
         * при таком подходе запросы в журнале становятся более читабельными.
         * @param string $query
         * @param array $parameters Массив параметров для привязки к запросу
         * @param string $connection_name Какое соединение использовать
         * @param float $query_time Время запроса
         * @return bool
         */
        protected static function _log_query($query, $parameters, $connection_name, $query_time) {
            // Если ведение журнала не включено, ничего не делайте
            if (!self::$_config[$connection_name]['logging']) {
                return false;
            }
            if (!isset(self::$_query_log[$connection_name])) {
                self::$_query_log[$connection_name] = array();
            }
            // Удалите все нецелые индексы из параметров
            foreach($parameters as $key => $value) {
                if (!is_int($key)) unset($parameters[$key]);
            }
            if (count($parameters) > 0) {
                // Вывод параметров
                $parameters = array_map(array(self::get_db($connection_name), 'quote'), $parameters);
                // Избегайте коллизии %формата для vsprintf
                $query = str_replace("%", "%%", $query);
                // Замените заполнители в запросе для vsprintf
                if(false !== strpos($query, "'") || false !== strpos($query, '"')) {
                    $query = IdiormString::str_replace_outside_quotes("?", "%s", $query);
                } else {
                    $query = str_replace("?", "%s", $query);
                }
                // Замените вопросительные знаки в запросе на параметры
                $bound_query = vsprintf($query, $parameters);
            } else {
                $bound_query = $query;
            }
            self::$_last_query = $bound_query;
            self::$_query_log[$connection_name][] = $bound_query;


            if(is_callable(self::$_config[$connection_name]['logger'])){
                $logger = self::$_config[$connection_name]['logger'];
                $logger($bound_query, $query_time);
            }

            return true;
        }
        /**
         * Получить последний выполненный запрос. Работает только в том случае, если
         * опция конфигурации 'logging' имеет значение true. В противном случае
         * вернет null. Возвращает последний запрос из всех соединений, если
         * имя_соединения не указано
         * @param null|string $connection_name Какое соединение использовать
         * @return string
         */
        public static function get_last_query($connection_name = null) {
            if ($connection_name === null) {
                return self::$_last_query;
            }
            if (!isset(self::$_query_log[$connection_name])) {
                return '';
            }
            return end(self::$_query_log[$connection_name]);
        }
        /**
         * Получить массив, содержащий все запросы, запущенные на
         * указанной связи до настоящего времени.
         * Работает только если опция конфигурации 'logging' имеет значение
         * устанавливается в true. В противном случае возвращаемый массив будет пуст.
         * @param string $connection_name Какое соединение использовать
         */
        public static function get_query_log($connection_name = self::DEFAULT_CONNECTION) {
            if (isset(self::$_query_log[$connection_name])) {
                return self::$_query_log[$connection_name];
            }
            return array();
        }
        /**
         * Получение списка доступных имен соединений
         * @return array
         */
        public static function get_connection_names() {
            return array_keys(self::$_db);
        }
        // ------------------------ //
        // --- МЕТОДЫ ЭКЗЕМПЛЯРА --- //
        // ------------------------ //
        /**
         * "Частный" конструктор; не должен вызываться напрямую.
         * Вместо этого используйте фабричный метод ORM::for_table.
         */
        protected function __construct($table_name, $data = array(), $connection_name = self::DEFAULT_CONNECTION) {
            $this->_table_name = $table_name;
            $this->_data = $data;
            $this->_connection_name = $connection_name;
            self::_setup_db_config($connection_name);
        }
        /**
         * Создает новый, пустой экземпляр класса. Используется
         * для добавления новой строки в вашу базу данных. Может опционально
         * передается ассоциативный массив данных для заполнения
         * экземпляра. Если это так, то все поля будут помечены как
         * грязными, поэтому все будет сохранено в базе данных, когда
         * вызывается функция save().
         */
        public function create($data=null) {
            $this->_is_new = true;
            if (!is_null($data)) {
                return $this->hydrate($data)->force_all_dirty();
            }
            return $this;
        }
        /**
         * Укажите столбец ID, который будет использоваться только для этого экземпляра или массива экземпляров.
         * Это отменяет настройки id_column и id_column_overrides.
         *
         * Это в основном полезно для библиотек, построенных на основе Idiorm, и позволит
         * обычно не используются в запросах, построенных вручную. Если вы не знаете, почему
         * вы захотите использовать это, вам, вероятно, следует просто игнорировать это.
         */
        public function use_id_column($id_column) {
            $this->_instance_id_column = $id_column;
            return $this;
        }
        /**
         * Создайте экземпляр ORM из заданного ряда (ассоциативный
         * массив данных, извлеченных из базы данных)
         */
        protected function _create_instance_from_row($row) {
            $instance = self::for_table($this->_table_name, $this->_connection_name);
            $instance->use_id_column($this->_instance_id_column);
            $instance->hydrate($row);
            return $instance;
        }
        /**
         * Сообщите ORM, что вы ожидаете один результат
         * обратно из вашего запроса и выполните его. Вернется
         * единственный экземпляр класса ORM, или false, если нет
         * строк были возвращены.
         * В качестве сокращения можно указать идентификатор в качестве параметра
         * к этому методу. При этом будет выполнен первичный ключ
         * поиск по таблице.
         */
        public function find_one($id=null) {
            if (!is_null($id)) {
                $this->where_id_is($id);
            }
            $this->limit(1);
            $rows = $this->_run();
            if (empty($rows)) {
                return false;
            }
            return $this->_create_instance_from_row($rows[0]);
        }
        /**
         * Сообщите ORM, что вы ожидаете получить несколько результатов
         * из вашего запроса и выполните его. Будет возвращен массив
         * экземпляров класса ORM, или пустой массив, если
         * не было возвращено ни одной строки.
         * @return array|\IdiormResultSet
         */
        public function find_many() {
            if(self::$_config[$this->_connection_name]['return_result_sets']) {
                return $this->find_result_set();
            }
            return $this->_find_many();
        }
        /**
         * Сообщите ORM, что вы ожидаете получить несколько результатов
         * из вашего запроса и выполните его. Будет возвращен массив
         * экземпляров класса ORM, или пустой массив, если
         * не было возвращено ни одной строки.
         * @return array
         */
        protected function _find_many() {
            $rows = $this->_run();
            return array_map(array($this, '_create_instance_from_row'), $rows);
        }
        /**
         * Сообщите ORM, что вы ожидаете получить несколько результатов
         * из вашего запроса и выполните его. Будет возвращен объект набора результатов
         * содержащие экземпляры класса ORM.
         * @return \IdiormResultSet
         */
        public function find_result_set() {
            return new IdiormResultSet($this->_find_many());
        }
        /**
         * Сообщите ORM, что вы ожидаете получить несколько результатов
         * из вашего запроса и выполните его. Будет возвращен массив,
         * или пустой массив, если не было возвращено ни одной строки.
         * @return array
         */
        public function find_array() {
            return $this->_run();
        }
        /**
         * Сообщите ORM, что вы хотите выполнить запрос COUNT.
         * Возвращает целое число, представляющее количество
         * возвращенных строк.
         */
        public function count($column = '*') {
            return $this->_call_aggregate_db_function(__FUNCTION__, $column);
        }
        /**
         * Сообщите ORM, что вы хотите выполнить запрос MAX.
         * Возвращает максимальное значение выбранного столбца.
         */
        public function max($column)  {
            return $this->_call_aggregate_db_function(__FUNCTION__, $column);
        }
        /**
         * Сообщите ORM, что вы хотите выполнить MIN-запрос.
         * Возвращает минимальное значение выбранного столбца.
         */
        public function min($column)  {
            return $this->_call_aggregate_db_function(__FUNCTION__, $column);
        }
        /**
         * Сообщите ORM, что вы хотите выполнить запрос AVG.
         * Возвращает среднее значение выбранного столбца.
         */
        public function avg($column)  {
            return $this->_call_aggregate_db_function(__FUNCTION__, $column);
        }
        /**
         * Сообщите ORM, что вы хотите выполнить запрос SUM.
         * Возвращает сумму выбранного столбца.
         */
        public function sum($column)  {
            return $this->_call_aggregate_db_function(__FUNCTION__, $column);
        }
        /**
         * Выполнение агрегированного запроса на текущем соединении.
         * @param string $sql_function Агрегатная функция для вызова, например. MIN, COUNT и т.д.
         * @param string $column Столбец, по которому будет выполняться агрегированный запрос
         * @return int
         */
        protected function _call_aggregate_db_function($sql_function, $column) {
            $alias = strtolower($sql_function);
            $sql_function = strtoupper($sql_function);
            if('*' != $column) {
                $column = $this->_quote_identifier($column);
            }
            $result_columns = $this->_result_columns;
            $this->_result_columns = array();
            $this->select_expr("$sql_function($column)", $alias);
            $result = $this->find_one();
            $this->_result_columns = $result_columns;
            $return_value = 0;
            if($result !== false && isset($result->$alias)) {
                if (!is_numeric($result->$alias)) {
                    $return_value = $result->$alias;
                }
                elseif((int) $result->$alias == (float) $result->$alias) {
                    $return_value = (int) $result->$alias;
                } else {
                    $return_value = (float) $result->$alias;
                }
            }
            return $return_value;
        }
         /**
         * Этот метод может быть вызван для гидратации (заполнения) этого
         * экземпляр класса из ассоциативного массива данных.
         * Обычно это вызывается только изнутри класса,
         * но он общедоступен на случай, если вам понадобится вызвать его напрямую.
         */
        public function hydrate($data=array()) {
            $this->_data = $data;
            return $this;
        }
        /**
         * Заставьте ORM отметить все поля в массиве $data
         * как "грязные" и, следовательно, обновлять их при вызове функции save().
         */
        public function force_all_dirty() {
            $this->_dirty_fields = $this->_data;
            return $this;
        }
        /**
         * Выполните необработанный запрос. Запрос может содержать заполнители в
         * в стиле именованного или вопросительного знака. Если заполнители
         * используются, параметры должны представлять собой массив значений, которые будут
         * будут привязаны к заполнителям в запросе. Если этот метод
         * вызывается, все остальные методы построения запроса будут проигнорированы.
         */
        public function raw_query($query, $parameters = array()) {
            $this->_is_raw_query = true;
            $this->_raw_query = $query;
            $this->_raw_parameters = $parameters;
            return $this;
        }
        /**
         * Добавьте псевдоним для основной таблицы, который будет использоваться в запросах SELECT
         */
        public function table_alias($alias) {
            $this->_table_alias = $alias;
            return $this;
        }
        /**
         * Внутренний метод для добавления выражения без кавычек к набору
         * столбцов, возвращаемых запросом SELECT. Вторым необязательным
         * аргументом является псевдоним, под которым возвращается выражение.
         */
        protected function _add_result_column($expr, $alias=null) {
            if (!is_null($alias)) {
                $expr .= " AS " . $this->_quote_identifier($alias);
            }
            if ($this->_using_default_result_columns) {
                $this->_result_columns = array($expr);
                $this->_using_default_result_columns = false;
            } else {
                $this->_result_columns[] = $expr;
            }
            return $this;
        }
        /**
         * Подсчитывает количество столбцов, которые принадлежат первичному
         * ключу и их значение равно null.
         */
        public function count_null_id_columns() {
            if (is_array($this->_get_id_column_name())) {
                return count(array_filter($this->id(), 'is_null'));
            } else {
                return is_null($this->id()) ? 1 : 0;
            }
        }
        /**
         * Добавьте столбец в список столбцов, возвращенных запросом SELECT
         * запрос. По умолчанию это значение равно '*'. Вторым необязательным аргументом является
         * псевдоним, под которым будет возвращен столбец.
         */
        public function select($column, $alias=null) {
            $column = $this->_quote_identifier($column);
            return $this->_add_result_column($column, $alias);
        }
        /**
         * Добавьте выражение без кавычек к списку столбцов, возвращаемых
         * запросом SELECT. Вторым необязательным аргументом является
         * псевдоним, под которым будет возвращен столбец.
         */
        public function select_expr($expr, $alias=null) {
            return $this->_add_result_column($expr, $alias);
        }
        /**
         * Добавьте столбцы в список столбцов, возвращенных запросом SELECT
         * запрос. По умолчанию это значение равно '*'. Многие столбцы могут быть предоставлены
         * как массив или как список параметров метода.
         *
         * Обратите внимание, что псевдоним не должен быть числовым - если вам нужен
         * числовой псевдоним, то добавьте к нему несколько буквенных символов. например, a1
         *
         * @example select_many(array('alias' => 'column', 'column2', 'alias2' => 'column3'), 'column4', 'column5');
         * @example select_many('column', 'column2', 'column3');
         * @example select_many(array('column', 'column2', 'column3'), 'column4', 'column5');
         *
         * @return \ORM
         */
        public function select_many() {
            $columns = func_get_args();
            if(!empty($columns)) {
                $columns = $this->_normalise_select_many_columns($columns);
                foreach($columns as $alias => $column) {
                    if(is_numeric($alias)) {
                        $alias = null;
                    }
                    $this->select($column, $alias);
                }
            }
            return $this;
        }
        /**
         * Добавьте выражение без кавычек к списку столбцов, возвращаемых
         * запросом SELECT. Многие столбцы могут быть предоставлены либо как
         * массив или как список параметров метода.
         *
         * Обратите внимание, что псевдоним не должен быть числовым - если вам нужен
         * числовой псевдоним, то добавьте к нему несколько буквенных символов. например, a1
         *
         * @example select_many_expr(array('alias' => 'column', 'column2', 'alias2' => 'column3'), 'column4', 'column5')
         * @example select_many_expr('column', 'column2', 'column3')
         * @example select_many_expr(array('column', 'column2', 'column3'), 'column4', 'column5')
         *
         * @return \ORM
         */
        public function select_many_expr() {
            $columns = func_get_args();
            if(!empty($columns)) {
                $columns = $this->_normalise_select_many_columns($columns);
                foreach($columns as $alias => $column) {
                    if(is_numeric($alias)) {
                        $alias = null;
                    }
                    $this->select_expr($column, $alias);
                }
            }
            return $this;
        }
        /**
         * Возьмите спецификацию столбцов для методов select many и преобразуйте ее
         * в нормализованный массив столбцов и псевдонимов.
         *
         * Он предназначен для преобразования следующих стилей в нормализованный массив:
         *
         * array(array('alias' => 'column', 'column2', 'alias2' => 'column3'), 'column4', 'column5'))
         *
         * @param array $columns
         * @return array
         */
        protected function _normalise_select_many_columns($columns) {
            $return = array();
            foreach($columns as $column) {
                if(is_array($column)) {
                    foreach($column as $key => $value) {
                        if(!is_numeric($key)) {
                            $return[$key] = $value;
                        } else {
                            $return[] = $value;
                        }
                    }
                } else {
                    $return[] = $column;
                }
            }
            return $return;
        }
        /**
         * Добавьте ключевое слово DISTINCT перед списком столбцов в запросе SELECT
         */
        public function distinct() {
            $this->_distinct = true;
            return $this;
        }
        /**
         * Внутренний метод для добавления источника JOIN в запрос.
         *
         * Оператор join_operator должен быть одним из INNER, LEFT OUTER, CROSS и т.д. - к нему будет добавлено значение JOIN.
         * будет добавлено к JOIN.
         *
         * Таблица должна быть именем таблицы, к которой нужно присоединиться.
         *
         * Ограничение может быть либо строкой, либо массивом с тремя элементами. Если это
         * это строка, она будет скомпилирована в запрос как есть, без экранирования. На сайте
         * рекомендуемый способ предоставления ограничений - массив из трех элементов:
         *
         * first_column, operator, second_column
         *
         * Пример: array('user.id', '=', 'profile.user_id')
         *
         * будет составляться в
         *
         * ON `user`.`id` = `profile`.`user_id`
         *
         * Последний (необязательный) аргумент задает псевдоним для объединенной таблицы.
         */
        protected function _add_join_source($join_operator, $table, $constraint, $table_alias=null) {
            $join_operator = trim("{$join_operator} JOIN");
            $table = $this->_quote_identifier($table);
            // Добавьте имя таблицы, если оно присутствует
            if (!is_null($table_alias)) {
                $table_alias = $this->_quote_identifier($table_alias);
                $table .= " {$table_alias}";
            }
            // Постройте ограничение
            if (is_array($constraint)) {
                list($first_column, $operator, $second_column) = $constraint;
                $first_column = $this->_quote_identifier($first_column);
                $second_column = $this->_quote_identifier($second_column);
                $constraint = "{$first_column} {$operator} {$second_column}";
            }
            $this->_join_sources[] = "{$join_operator} {$table} ON {$constraint}";
            return $this;
        }
        /**
         * Добавьте источник RAW JOIN к запросу
         */
        public function raw_join($table, $constraint, $table_alias, $parameters = array()) {
            // Добавьте имя таблицы, если оно присутствует
            if (!is_null($table_alias)) {
                $table_alias = $this->_quote_identifier($table_alias);
                $table .= " {$table_alias}";
            }
            $this->_values = array_merge($this->_values, $parameters);
            // Постройте ограничение
            if (is_array($constraint)) {
                list($first_column, $operator, $second_column) = $constraint;
                $first_column = $this->_quote_identifier($first_column);
                $second_column = $this->_quote_identifier($second_column);
                $constraint = "{$first_column} {$operator} {$second_column}";
            }
            $this->_join_sources[] = "{$table} ON {$constraint}";
            return $this;
        }
        /**
         * Добавьте в запрос простой источник JOIN
         */
        public function join($table, $constraint, $table_alias=null) {
            return $this->_add_join_source("", $table, $constraint, $table_alias);
        }
        /**
         * Добавьте в запрос источник INNER JOIN
         */
        public function inner_join($table, $constraint, $table_alias=null) {
            return $this->_add_join_source("INNER", $table, $constraint, $table_alias);
        }
        /**
         * Добавьте в запрос сущность LEFT OUTER JOIN
         */
        public function left_outer_join($table, $constraint, $table_alias=null) {
            return $this->_add_join_source("LEFT OUTER", $table, $constraint, $table_alias);
        }
        /**
         * Добавьте в запрос сущность RIGHT OUTER JOIN
         */
        public function right_outer_join($table, $constraint, $table_alias=null) {
            return $this->_add_join_source("RIGHT OUTER", $table, $constraint, $table_alias);
        }
        /**
         * Добавьте в запрос FULL OUTER JOIN источников
         */
        public function full_outer_join($table, $constraint, $table_alias=null) {
            return $this->_add_join_source("FULL OUTER", $table, $constraint, $table_alias);
        }
        /**
         * Внутренний метод для добавления условия HAVING в запрос
         */
        protected function _add_having($fragment, $values=array()) {
            return $this->_add_condition('having', $fragment, $values);
        }
        /**
         * Внутренний метод для добавления условия HAVING в запрос
         */
        protected function _add_simple_having($column_name, $separator, $value) {
            return $this->_add_simple_condition('having', $column_name, $separator, $value);
        }
        /**
         * Внутренний метод для добавления предложения HAVING с несколькими значениями (например, IN и NOT IN)
         */
        public function _add_having_placeholder($column_name, $separator, $values) {
            if (!is_array($column_name)) {
                $data = array($column_name => $values);
            } else {
                $data = $column_name;
            }
            $result = $this;
            foreach ($data as $key => $val) {
                $column = $result->_quote_identifier($key);
                $placeholders = $result->_create_placeholders($val);
                $result = $result->_add_having("{$column} {$separator} ({$placeholders})", $val);
            }
            return $result;
        }
        /**
         * Внутренний метод для добавления предложения HAVING без параметров (как IS NULL и IS NOT NULL)
         */
        public function _add_having_no_value($column_name, $operator) {
            $conditions = (is_array($column_name)) ? $column_name : array($column_name);
            $result = $this;
            foreach($conditions as $column) {
                $column = $this->_quote_identifier($column);
                $result = $result->_add_having("{$column} {$operator}");
            }
            return $result;
        }
        /**
         * Внутренний метод для добавления условия WHERE к запросу
         */
        protected function _add_where($fragment, $values=array()) {
            return $this->_add_condition('where', $fragment, $values);
        }
        /**
         * Внутренний метод для добавления условия WHERE к запросу
         */
        protected function _add_simple_where($column_name, $separator, $value) {
            return $this->_add_simple_condition('where', $column_name, $separator, $value);
        }
        /**
         * Добавьте предложение WHERE с несколькими значениями (например, IN и NOT IN).
         */
        public function _add_where_placeholder($column_name, $separator, $values) {
            if (!is_array($column_name)) {
                $data = array($column_name => $values);
            } else {
                $data = $column_name;
            }
            $result = $this;
            foreach ($data as $key => $val) {
                $column = $result->_quote_identifier($key);
                $placeholders = $result->_create_placeholders($val);
                $result = $result->_add_where("{$column} {$separator} ({$placeholders})", $val);
            }
            return $result;
        }
        /**
         * Добавьте предложение WHERE без параметров (например, IS NULL и IS NOT NULL).
         */
        public function _add_where_no_value($column_name, $operator) {
            $conditions = (is_array($column_name)) ? $column_name : array($column_name);
            $result = $this;
            foreach($conditions as $column) {
                $column = $this->_quote_identifier($column);
                $result = $result->_add_where("{$column} {$operator}");
            }
            return $result;
        }
        /**
         * Внутренний метод для добавления условия HAVING или WHERE к запросу
         */
        protected function _add_condition($type, $fragment, $values=array()) {
            $conditions_class_property_name = "_{$type}_conditions";
            if (!is_array($values)) {
                $values = array($values);
            }
            array_push($this->$conditions_class_property_name, array(
                self::CONDITION_FRAGMENT => $fragment,
                self::CONDITION_VALUES => $values,
            ));
            return $this;
        }
       /**
         * Метод-помощник для компиляции простого значения COLUMN SEPARATOR VALUE
         * в стиле HAVING или WHERE в строку и значение, готовые к тому, чтобы
         * для передачи в метод _add_condition. Позволяет избежать дублирования
         * вызова _quote_identifier
         *
         * Если имя_столбца является ассоциативным массивом, то добавляется условие для каждого столбца.
         */
        protected function _add_simple_condition($type, $column_name, $separator, $value) {
            $multiple = is_array($column_name) ? $column_name : array($column_name => $value);
            $result = $this;
            foreach($multiple as $key => $val) {
                // Добавьте имя таблицы в случае неоднозначных столбцов
                if (count($result->_join_sources) > 0 && strpos($key, '.') === false) {
                    $table = $result->_table_name;
                    if (!is_null($result->_table_alias)) {
                        $table = $result->_table_alias;
                    }
                    $key = "{$table}.{$key}";
                }
                $key = $result->_quote_identifier($key);
                $result = $result->_add_condition($type, "{$key} {$separator} ?", $val);
            }
            return $result;
        }
        /**
         * Возвращает строку, содержащую заданное количество вопросительных знаков,
         * разделенных запятыми. Например "?, ?, ?"
         */
        protected function _create_placeholders($fields) {
            if(!empty($fields)) {
                $db_fields = array();
                foreach($fields as $key => $value) {
                    // Обрабатывайте поля выражения непосредственно в запросе
                    if(array_key_exists($key, $this->_expr_fields)) {
                        $db_fields[] = $value;
                    } else {
                        $db_fields[] = '?';
                    }
                }
                return implode(', ', $db_fields);
            }
        }

        /**
         * Метод-помощник, который фильтрует массив столбцов/значений, возвращая только те.
         * столбцы, которые принадлежат составному первичному ключу.
         *
         * Если ключ содержит столбец, который не существует в данном массиве,
         * для него будет возвращено нулевое значение.
         */
        protected function _get_compound_id_column_values($value) {
            $filtered = array();
            foreach($this->_get_id_column_name() as $key) {
                $filtered[$key] = isset($value[$key]) ? $value[$key] : null;
            }
            return $filtered;
        }
       /**
         * Метод-помощник, который фильтрует массив, содержащий составные столбцы/значения.
         * массивы.
         */
        protected function _get_compound_id_column_values_array($values) {
            $filtered = array();
            foreach($values as $value) {
                $filtered[] = $this->_get_compound_id_column_values($value);
            }
            return $filtered;
        }
        /**
         * Добавьте в запрос предложение WHERE column = value. Каждый раз, когда
         * это вызывается в цепочке, будет добавляться дополнительное WHERE
         * добавляться, и они будут объединены вместе, когда окончательный запрос
         * будет построен.
         *
         * Если вы используете массив в $column_name, то для каждого элемента будет
         * добавлено для каждого элемента. В этом случае $value игнорируется.
         */
        public function where($column_name, $value=null) {
            return $this->where_equal($column_name, $value);
        }
        /**
         * Более явно названная версия метода where().
         * Может использоваться по желанию.
         */
        public function where_equal($column_name, $value=null) {
            return $this->_add_simple_where($column_name, '=', $value);
        }
        /**
         * Добавьте в запрос предложение WHERE столбец != значение.
         */
        public function where_not_equal($column_name, $value=null) {
            return $this->_add_simple_where($column_name, '!=', $value);
        }
        /**
         * Специальный метод для запроса таблицы по ее первичному ключу
         *
         * Если первичный ключ составной, то только те столбцы, которые
         * принадлежат этому ключу, будут использоваться для запроса
         */
        public function where_id_is($id) {
            return (is_array($this->_get_id_column_name())) ?
                $this->where($this->_get_compound_id_column_values($id), null) :
                $this->where($this->_get_id_column_name(), $id);
        }
        /**
         * Позволяет добавить предложение WHERE, которое соответствует любому из условий.
         *, указанных в массиве. Каждый элемент в ассоциативном массиве будет
         * быть различным условием, где ключом будет имя столбца.
         *
         * По умолчанию для всех столбцов будет использоваться оператор равенства, но
         * его можно переопределить для любого или всех столбцов с помощью второго параметра.
         *
         * Каждое условие будет объединено в OR при добавлении в окончательный запрос.
         */
        public function where_any_is($values, $operator='=') {
            $data = array();
            $query = array("((");
            $first = true;
            foreach ($values as $item) {
                if ($first) {
                    $first = false;
                } else {
                    $query[] = ") OR (";
                }
                $firstsub = true;
                foreach($item as $key => $item) {
                    $op = is_string($operator) ? $operator : (isset($operator[$key]) ? $operator[$key] : '=');
                    if ($firstsub) {
                        $firstsub = false;
                    } else {
                        $query[] = "AND";
                    }
                    $query[] = $this->_quote_identifier($key);
                    $data[] = $item;
                    $query[] = $op . " ?";
                }
            }
            $query[] = "))";
            return $this->where_raw(join($query, ' '), $data);
        }
        /**
         * Аналогичен where_id_is(), но позволяет использовать несколько первичных ключей.
         *
         * Если первичный ключ составной, только столбцы, которые
         * принадлежат этому ключу, будут использоваться в запросе.
         */
        public function where_id_in($ids) {
            return (is_array($this->_get_id_column_name())) ?
                $this->where_any_is($this->_get_compound_id_column_values_array($ids)) :
                $this->where_in($this->_get_id_column_name(), $ids);
        }
        /**
         * Добавьте условие WHERE ... LIKE к вашему запросу.
         */
        public function where_like($column_name, $value=null) {
            return $this->_add_simple_where($column_name, 'LIKE', $value);
        }
        /**
         * Добавьте пункт where WHERE ... НЕ LIKE к вашему запросу.
         */
        public function where_not_like($column_name, $value=null) {
            return $this->_add_simple_where($column_name, 'NOT LIKE', $value);
        }
        /**
         * Добавьте WHERE ... > к вашему запросу
         */
        public function where_gt($column_name, $value=null) {
            return $this->_add_simple_where($column_name, '>', $value);
        }
        /**
         * Добавьте WHERE ... < к вашему запросу
         */
        public function where_lt($column_name, $value=null) {
            return $this->_add_simple_where($column_name, '<', $value);
        }
        /**
         * Добавьте предложение WHERE ... >= к вашему запросу
         */
        public function where_gte($column_name, $value=null) {
            return $this->_add_simple_where($column_name, '>=', $value);
        }
        /**
         * Добавьте WHERE ... <= к вашему запросу
         */
        public function where_lte($column_name, $value=null) {
            return $this->_add_simple_where($column_name, '<=', $value);
        }
        /**
         * Добавьте условие WHERE ... IN к вашему запросу
         */
        public function where_in($column_name, $values) {
            return $this->_add_where_placeholder($column_name, 'IN', $values);
        }
        /**
         * Добавьте условие WHERE ... NOT IN к вашему запросу
         */
        public function where_not_in($column_name, $values) {
            return $this->_add_where_placeholder($column_name, 'NOT IN', $values);
        }
        /**
         * Добавьте в запрос предложение WHERE столбец IS NULL
         */
        public function where_null($column_name) {
            return $this->_add_where_no_value($column_name, "IS NULL");
        }
        /**
         * Добавьте в запрос предложение WHERE столбец IS NOT NULL
         */
        public function where_not_null($column_name) {
            return $this->_add_where_no_value($column_name, "IS NOT NULL");
        }
        /**
         * Добавьте в запрос необработанное предложение WHERE. Предложение должно
         * содержать заполнители вопросительных знаков, которые будут привязаны
         * к параметрам, указанным во втором аргументе.
         */
        public function where_raw($clause, $parameters=array()) {
            return $this->_add_where($clause, $parameters);
        }
        /**
         * Добавьте LIMIT в запрос
         */
        public function limit($limit) {
            $this->_limit = $limit;
            return $this;
        }
        /**
         * Добавить OFFSET к запросу
         */
        public function offset($offset) {
            $this->_offset = $offset;
            return $this;
        }
        /**
         * Добавьте в запрос предложение ORDER BY
         */
        protected function _add_order_by($column_name, $ordering) {
            $column_name = $this->_quote_identifier($column_name);
            $this->_order_by[] = "{$column_name} {$ordering}";
            return $this;
        }
        /**
         * Добавить предложение DESC столбца ORDER BY
         */
        public function order_by_desc($column_name) {
            return $this->_add_order_by($column_name, 'DESC');
        }
        /**
         * Добавить предложение ASC столбца ORDER BY
         */
        public function order_by_asc($column_name) {
            return $this->_add_order_by($column_name, 'ASC');
        }
        /**
         * Добавьте выражение без кавычек в качестве предложения ORDER BY
         */
        public function order_by_expr($clause) {
            $this->_order_by[] = $clause;
            return $this;
        }
        /**
         * Добавьте столбец в список столбцов для GROUP BY
         */
        public function group_by($column_name) {
            $column_name = $this->_quote_identifier($column_name);
            $this->_group_by[] = $column_name;
            return $this;
        }
        /**
         * Добавьте выражение без кавычек к списку столбцов для GROUP BY
         */
        public function group_by_expr($expr) {
            $this->_group_by[] = $expr;
            return $this;
        }
        /**
         * Добавьте в запрос предложение HAVING column = value. Каждый раз, когда
         * это вызывается в цепочке, будет добавляться дополнительное HAVING
         * добавляться, и они будут объединены вместе, когда окончательный запрос
         * будет построен.
         *
         * Если вы используете массив в $column_name, то для каждого элемента будет
         * добавлено для каждого элемента. В этом случае $value игнорируется.
         */
        public function having($column_name, $value=null) {
            return $this->having_equal($column_name, $value);
        }
        /**
         * Более явно названная версия для метода having().
         * Может использоваться по желанию.
         */
        public function having_equal($column_name, $value=null) {
            return $this->_add_simple_having($column_name, '=', $value);
        }
        /**
         * Добавьте в запрос предложение HAVING столбец != значение.
         */
        public function having_not_equal($column_name, $value=null) {
            return $this->_add_simple_having($column_name, '!=', $value);
        }
        /**
         * Специальный метод для запроса таблицы по ее первичному ключу.
         *
         * Если первичный ключ составной, то только те столбцы.
         * принадлежат этому ключу, будут использоваться для запроса.
         */
        public function having_id_is($id) {
            return (is_array($this->_get_id_column_name())) ?
                $this->having($this->_get_compound_id_column_values($value)) :
                $this->having($this->_get_id_column_name(), $id);
        }
        /**
         * Добавьте HAVING ... LIKE к вашему запросу.
         */
        public function having_like($column_name, $value=null) {
            return $this->_add_simple_having($column_name, 'LIKE', $value);
        }
        /**
         * Добавьте пункт where HAVING ... НЕ LIKE к вашему запросу.
         */
        public function having_not_like($column_name, $value=null) {
            return $this->_add_simple_having($column_name, 'NOT LIKE', $value);
        }
        /**
         * Добавьте HAVING ... > в запрос
         */
        public function having_gt($column_name, $value=null) {
            return $this->_add_simple_having($column_name, '>', $value);
        }
        /**
         * Добавьте HAVING ... < предложение к вашему запросу
         */
        public function having_lt($column_name, $value=null) {
            return $this->_add_simple_having($column_name, '<', $value);
        }
        /**
         * Добавьте HAVING ... >= в запрос
         */
        public function having_gte($column_name, $value=null) {
            return $this->_add_simple_having($column_name, '>=', $value);
        }
        /**
         * Добавьте HAVING ... <= в запрос
         */
        public function having_lte($column_name, $value=null) {
            return $this->_add_simple_having($column_name, '<=', $value);
        }
        /**
         * Добавьте HAVING ... IN к вашему запросу
         */
        public function having_in($column_name, $values=null) {
            return $this->_add_having_placeholder($column_name, 'IN', $values);
        }
        /**
         * Добавьте HAVING ... NOT IN к вашему запросу
         */
        public function having_not_in($column_name, $values=null) {
            return $this->_add_having_placeholder($column_name, 'NOT IN', $values);
        }
        /**
         * Добавьте в запрос предложение HAVING столбец IS NULL
         */
        public function having_null($column_name) {
            return $this->_add_having_no_value($column_name, 'IS NULL');
        }
        /**
         * Добавьте в запрос предложение HAVING столбец IS NOT NULL
         */
        public function having_not_null($column_name) {
            return $this->_add_having_no_value($column_name, 'IS NOT NULL');
        }
        /**
         * Добавьте в запрос необработанное предложение HAVING. Оговорка должна
         * содержать заполнители вопросительных знаков, которые будут привязаны
         * к параметрам, указанным во втором аргументе.
         */
        public function having_raw($clause, $parameters=array()) {
            return $this->_add_having($clause, $parameters);
        }
        /**
         * Постройте оператор SELECT на основе условий, которые были
         * были переданы этому экземпляру путем цепочки вызовов методов.
         */
        protected function _build_select() {
            // Если запрос необработанный, просто установите $this->_values как.
            // необработанные параметры запроса и верните необработанный запрос
            if ($this->_is_raw_query) {
                $this->_values = $this->_raw_parameters;
                return $this->_raw_query;
            }
            // Постройте и верните полный оператор SELECT путем конкатенации
            // результатов вызова каждого отдельного метода построителя.
            return $this->_join_if_not_empty(" ", array(
                $this->_build_select_start(),
                $this->_build_join(),
                $this->_build_where(),
                $this->_build_group_by(),
                $this->_build_having(),
                $this->_build_order_by(),
                $this->_build_limit(),
                $this->_build_offset(),
            ));
        }
        /**
         * Постройте начало оператора SELECT
         */
        protected function _build_select_start() {
            $fragment = 'SELECT ';
            $result_columns = join(', ', $this->_result_columns);
            if (!is_null($this->_limit) &&
                self::$_config[$this->_connection_name]['limit_clause_style'] === ORM::LIMIT_STYLE_TOP_N) {
                $fragment .= "TOP {$this->_limit} ";
            }
            if ($this->_distinct) {
                $result_columns = 'DISTINCT ' . $result_columns;
            }
            $fragment .= "{$result_columns} FROM " . $this->_quote_identifier($this->_table_name);
            if (!is_null($this->_table_alias)) {
                $fragment .= " " . $this->_quote_identifier($this->_table_alias);
            }
            return $fragment;
        }
        /**
         * Постройте источники JOIN
         */
        protected function _build_join() {
            if (count($this->_join_sources) === 0) {
                return '';
            }
            return join(" ", $this->_join_sources);
        }
        /**
         * Постройте предложение(я) WHERE
         */
        protected function _build_where() {
            return $this->_build_conditions('where');
        }
        /**
         * Постройте предложение(я) HAVING
         */
        protected function _build_having() {
            return $this->_build_conditions('having');
        }
        /**
         * Построить GROUP BY
         */
        protected function _build_group_by() {
            if (count($this->_group_by) === 0) {
                return '';
            }
            return "GROUP BY " . join(", ", $this->_group_by);
        }
        /**
         * Постройте предложение WHERE или HAVING
         * @param string $type
         * @return string
         */
        protected function _build_conditions($type) {
            $conditions_class_property_name = "_{$type}_conditions";
            // Если нет ни одного пункта, возвращается пустая строка
            if (count($this->$conditions_class_property_name) === 0) {
                return '';
            }
            $conditions = array();
            foreach ($this->$conditions_class_property_name as $condition) {
                $conditions[] = $condition[self::CONDITION_FRAGMENT];
                $this->_values = array_merge($this->_values, $condition[self::CONDITION_VALUES]);
            }
            return strtoupper($type) . " " . join(" AND ", $conditions);
        }
        /**
         * Построить ORDER BY
         */
        protected function _build_order_by() {
            if (count($this->_order_by) === 0) {
                return '';
            }
            return "ORDER BY " . join(", ", $this->_order_by);
        }
        /**
         * Построить LIMIT
         */
        protected function _build_limit() {
            $fragment = '';
            if (!is_null($this->_limit) &&
                self::$_config[$this->_connection_name]['limit_clause_style'] == ORM::LIMIT_STYLE_LIMIT) {
                if (self::get_db($this->_connection_name)->getAttribute(PDO::ATTR_DRIVER_NAME) == 'firebird') {
                    $fragment = 'ROWS';
                } else {
                    $fragment = 'LIMIT';
                }
                $fragment .= " {$this->_limit}";
            }
            return $fragment;
        }
        /**
         * Построить OFFSET
         */
        protected function _build_offset() {
            if (!is_null($this->_offset)) {
                $clause = 'OFFSET';
                if (self::get_db($this->_connection_name)->getAttribute(PDO::ATTR_DRIVER_NAME) == 'firebird') {
                    $clause = 'TO';
                }
                return "$clause " . $this->_offset;
            }
            return '';
        }
        /**
         * Образец вокруг функции PHP join, которая.
         * добавляет части, только если они не пусты.
         */
        protected function _join_if_not_empty($glue, $pieces) {
            $filtered_pieces = array();
            foreach ($pieces as $piece) {
                if (is_string($piece)) {
                    $piece = trim($piece);
                }
                if (!empty($piece)) {
                    $filtered_pieces[] = $piece;
                }
            }
            return join($glue, $filtered_pieces);
        }
        /**
         * Процитируйте строку, которая используется в качестве идентификатора
         * (имена таблиц, столбцов и т.д.). Этот метод может
         * также работать с идентификаторами, разделенными точками, например table.column
         */
        protected function _quote_one_identifier($identifier) {
            $parts = explode('.', $identifier);
            $parts = array_map(array($this, '_quote_identifier_part'), $parts);
            return join('.', $parts);
        }
        /**
         * Цитата строка, которая используется в качестве идентификатора
         * (имена таблиц, столбцов и т.д.) или массив, содержащий
         * несколько идентификаторов. Этот метод также может работать с
         * идентификаторами, разделенными точками, например table.column
         */
        protected function _quote_identifier($identifier) {
            if (is_array($identifier)) {
                $result = array_map(array($this, '_quote_one_identifier'), $identifier);
                return join(', ', $result);
            } else {
                return $this->_quote_one_identifier($identifier);
            }
        }
        /**
         * Этот метод выполняет фактическое цитирование одной
         * части идентификатора, используя кавычки идентификатора
         * символ, указанный в конфигурации (или автоопределяемый).
         */
        protected function _quote_identifier_part($part) {
            if ($part === '*') {
                return $part;
            }
            $quote_character = self::$_config[$this->_connection_name]['identifier_quote_character'];
            // удваивайте любые кавычки идентификаторов, чтобы избежать их появления
            return $quote_character .
                   str_replace($quote_character,
                               $quote_character . $quote_character,
                               $part
                   ) . $quote_character;
        }
        /**
         * Создает ключ кэша для заданного запроса и параметров.
         */
        protected static function _create_cache_key($query, $parameters, $table_name = null, $connection_name = self::DEFAULT_CONNECTION) {
            if(isset(self::$_config[$connection_name]['create_cache_key']) and is_callable(self::$_config[$connection_name]['create_cache_key'])){
                return call_user_func_array(self::$_config[$connection_name]['create_cache_key'], array($query, $parameters, $table_name, $connection_name));
            }
            $parameter_string = join(',', $parameters);
            $key = $query . ':' . $parameter_string;
            return sha1($key);
        }
        /**
         * Проверка кэша запросов для заданного ключа кэша. Если значение
         * находится в кэше для данного ключа, верните это значение. В противном случае верните false.
         */
        protected static function _check_query_cache($cache_key, $table_name = null, $connection_name = self::DEFAULT_CONNECTION) {
            if(isset(self::$_config[$connection_name]['check_query_cache']) and is_callable(self::$_config[$connection_name]['check_query_cache'])){
                return call_user_func_array(self::$_config[$connection_name]['check_query_cache'], array($cache_key, $table_name, $connection_name));
            } elseif (isset(self::$_query_cache[$connection_name][$cache_key])) {
                return self::$_query_cache[$connection_name][$cache_key];
            }
            return false;
        }
        /**
         * Очистить кэш запросов
         */
        public static function clear_cache($table_name = null, $connection_name = self::DEFAULT_CONNECTION) {
            self::$_query_cache = array();
            if(isset(self::$_config[$connection_name]['clear_cache']) and is_callable(self::$_config[$connection_name]['clear_cache'])){
                return call_user_func_array(self::$_config[$connection_name]['clear_cache'], array($table_name, $connection_name));
            }
        }
        /**
         * Добавляет заданное значение в кэш запросов.
         */
        protected static function _cache_query_result($cache_key, $value, $table_name = null, $connection_name = self::DEFAULT_CONNECTION) {
            if(isset(self::$_config[$connection_name]['cache_query_result']) and is_callable(self::$_config[$connection_name]['cache_query_result'])){
                return call_user_func_array(self::$_config[$connection_name]['cache_query_result'], array($cache_key, $value, $table_name, $connection_name));
            } elseif (!isset(self::$_query_cache[$connection_name])) {
                self::$_query_cache[$connection_name] = array();
            }
            self::$_query_cache[$connection_name][$cache_key] = $value;
        }
        /**
         * Выполнение запроса SELECT, который был построен путем цепочки методов
         * на этом классе. Возвращает массив строк в виде ассоциативных массивов.
         */
        protected function _run() {
            $query = $this->_build_select();
            $caching_enabled = self::$_config[$this->_connection_name]['caching'];
            if ($caching_enabled) {
                $cache_key = self::_create_cache_key($query, $this->_values, $this->_table_name, $this->_connection_name);
                $cached_result = self::_check_query_cache($cache_key, $this->_table_name, $this->_connection_name);
                if ($cached_result !== false) {
                    return $cached_result;
                }
            }
            self::_execute($query, $this->_values, $this->_connection_name);
            $statement = self::get_last_statement();
            $rows = array();
            while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
                $rows[] = $row;
            }
            if ($caching_enabled) {
                self::_cache_query_result($cache_key, $rows, $this->_table_name, $this->_connection_name);
            }
            // сбросить Idiorm после выполнения запроса
            $this->_values = array();
            $this->_result_columns = array('*');
            $this->_using_default_result_columns = true;
            return $rows;
        }
        /**
         * Возвращает необработанные данные, обернутые этим экземпляром ORM.
         * как ассоциативный массив. Столбец
         * имена колонок могут быть указаны в качестве аргументов,
         * если это так, то будут возвращены только эти ключи.
         */
        public function as_array() {
            if (func_num_args() === 0) {
                return $this->_data;
            }
            $args = func_get_args();
            return array_intersect_key($this->_data, array_flip($args));
        }
        /**
         * Возвращает значение свойства данного объекта (строки базы данных)
         * или null, если оно отсутствует.
         *
         * Если передан массив имен столбцов, то возвращается ассоциативный массив
         * со значением каждого столбца или null, если он отсутствует.
         */
        public function get($key) {
            if (is_array($key)) {
                $result = array();
                foreach($key as $column) {
                    $result[$column] = isset($this->_data[$column]) ? $this->_data[$column] : null;
                }
                return $result;
            } else {
                return isset($this->_data[$key]) ? $this->_data[$key] : null;
            }
        }
        /**
         * Возвращает имя столбца в таблице базы данных, который содержит.
         * первичный ключ ID строки.
         */
        protected function _get_id_column_name() {
            if (!is_null($this->_instance_id_column)) {
                return $this->_instance_id_column;
            }
            if (isset(self::$_config[$this->_connection_name]['id_column_overrides'][$this->_table_name])) {
                return self::$_config[$this->_connection_name]['id_column_overrides'][$this->_table_name];
            }
            return self::$_config[$this->_connection_name]['id_column'];
        }
        /**
         * Получение идентификатора первичного ключа этого объекта.
         */
        public function id($disallow_null = false) {
            $id = $this->get($this->_get_id_column_name());
            if ($disallow_null) {
                if (is_array($id)) {
                    foreach ($id as $id_part) {
                        if ($id_part === null) {
                            throw new Exception('Primary key ID contains null value(s)');
                        }
                    }
                } else if ($id === null) {
                    throw new Exception('Primary key ID missing from row or is null');
                }
            }
            return $id;
        }
        /**
         * Установите свойство на определенное значение для данного объекта.
         * Чтобы установить несколько свойств одновременно, передайте ассоциативный массив
         * в качестве первого параметра и опустите второй параметр.
         * Пометьте свойства как "грязные", чтобы они были сохранены в
         * базе данных при вызове функции save().
         */
        public function set($key, $value = null) {
            return $this->_set_orm_property($key, $value);
        }
        /**
         * Установите свойство на определенное значение для данного объекта.
         * Чтобы установить несколько свойств одновременно, передайте ассоциативный массив
         * в качестве первого параметра и опустите второй параметр.
         * Пометьте свойства как "грязные", чтобы они были сохранены в
         * базе данных при вызове функции save().
         * @param string|array $key
         * @param string|null $value
         */
        public function set_expr($key, $value = null) {
            return $this->_set_orm_property($key, $value, true);
        }
        /**
         * Установите свойство объекта ORM.
         * @param string|array $key
         * @param string|null $value
         * @param bool $raw Следует ли рассматривать это значение как необработанное или нет
         */
        protected function _set_orm_property($key, $value = null, $expr = false) {
            if (!is_array($key)) {
                $key = array($key => $value);
            }
            foreach ($key as $field => $value) {
                $this->_data[$field] = $value;
                $this->_dirty_fields[$field] = $value;
                if (false === $expr and isset($this->_expr_fields[$field])) {
                    unset($this->_expr_fields[$field]);
                } else if (true === $expr) {
                    $this->_expr_fields[$field] = true;
                }
            }
            return $this;
        }
        /**
         * Проверьте, было ли данное поле изменено с момента сохранения этого
         * объект был сохранен.
         */
        public function is_dirty($key) {
            return isset($this->_dirty_fields[$key]);
        }
        /**
         * Проверьте, была ли модель результатом вызова create() или нет
         * @return bool
         */
        public function is_new() {
            return $this->_is_new;
        }
        /**
         * Сохраните все поля, которые были изменены в этом объекте.
         * в базу данных.
         */
        public function save() {
            $query = array();
            // удалить любые поля выражения, поскольку они уже заложены в запрос
            $values = array_values(array_diff_key($this->_dirty_fields, $this->_expr_fields));
            if (!$this->_is_new) { // UPDATE
                // Если нет грязных значений, ничего не делайте
                if (empty($values) && empty($this->_expr_fields)) {
                    return true;
                }
                $query = $this->_build_update();
                $id = $this->id(true);
                if (is_array($id)) {
                    $values = array_merge($values, array_values($id));
                } else {
                    $values[] = $id;
                }
            } else { // ВСТАВИТЬ
                $query = $this->_build_insert();
            }
            $success = self::_execute($query, $values, $this->_connection_name);
            $caching_auto_clear_enabled = self::$_config[$this->_connection_name]['caching_auto_clear'];
            if($caching_auto_clear_enabled){
                self::clear_cache($this->_table_name, $this->_connection_name);
            }
            // Если мы только что вставили новую запись, установите идентификатор этого объекта
            if ($this->_is_new) {
                $this->_is_new = false;
                if ($this->count_null_id_columns() != 0) {
                    $db = self::get_db($this->_connection_name);
                    if($db->getAttribute(PDO::ATTR_DRIVER_NAME) == 'pgsql') {
                        // может вернуть несколько столбцов, если используется составной первичный // ключ.
                        // ключ используется
                        $row = self::get_last_statement()->fetch(PDO::FETCH_ASSOC);
                        foreach($row as $key => $value) {
                            $this->_data[$key] = $value;
                        }
                    } else {
                        $column = $this->_get_id_column_name();
                        // если первичный ключ является составным, присвойте последний вставленный идентификатор
                        // первому столбцу
                        if (is_array($column)) {
                            $column = array_slice($column, 0, 1);
                        }
                        $this->_data[$column] = $db->lastInsertId();
                    }
                }
            }
            $this->_dirty_fields = $this->_expr_fields = array();
            return $success;
        }
        /**
         * Добавьте предложение WHERE для каждого столбца, принадлежащего первичному ключу
         */
        public function _add_id_column_conditions(&$query) {
            $query[] = "WHERE";
            $keys = is_array($this->_get_id_column_name()) ? $this->_get_id_column_name() : array( $this->_get_id_column_name() );
            $first = true;
            foreach($keys as $key) {
                if ($first) {
                    $first = false;
                }
                else {
                    $query[] = "AND";
                }
                $query[] = $this->_quote_identifier($key);
                $query[] = "= ?";
            }
        }
        /**
         * Построение запроса UPDATE
         */
        protected function _build_update() {
            $query = array();
            $query[] = "UPDATE {$this->_quote_identifier($this->_table_name)} SET";
            $field_list = array();
            foreach ($this->_dirty_fields as $key => $value) {
                if(!array_key_exists($key, $this->_expr_fields)) {
                    $value = '?';
                }
                $field_list[] = "{$this->_quote_identifier($key)} = $value";
            }
            $query[] = join(", ", $field_list);
            $this->_add_id_column_conditions($query);
            return join(" ", $query);
        }
        /**
         * Построение запроса INSERT
         */
        protected function _build_insert() {
            $query[] = "INSERT INTO";
            $query[] = $this->_quote_identifier($this->_table_name);
            $field_list = array_map(array($this, '_quote_identifier'), array_keys($this->_dirty_fields));
            $query[] = "(" . join(", ", $field_list) . ")";
            $query[] = "VALUES";
            $placeholders = $this->_create_placeholders($this->_dirty_fields);
            $query[] = "({$placeholders})";
            if (self::get_db($this->_connection_name)->getAttribute(PDO::ATTR_DRIVER_NAME) == 'pgsql') {
                $query[] = 'RETURNING ' . $this->_quote_identifier($this->_get_id_column_name());
            }
            return join(" ", $query);
        }
        /**
         * Удалить эту запись из базы данных
         */
        public function delete() {
            $query = array(
                "DELETE FROM",
                $this->_quote_identifier($this->_table_name)
            );
            $this->_add_id_column_conditions($query);
            return self::_execute(join(" ", $query), is_array($this->id(true)) ? array_values($this->id(true)) : array($this->id(true)), $this->_connection_name);
        }
        /**
         * Удалить много записей из базы данных
         */
        public function delete_many() {
            // Создайте и верните полный оператор DELETE путем объединения
            // результатов вызова каждого отдельного метода построителя.
            $query = $this->_join_if_not_empty(" ", array(
                "DELETE FROM",
                $this->_quote_identifier($this->_table_name),
                $this->_build_where(),
            ));
            return self::_execute($query, $this->_values, $this->_connection_name);
        }
        // --------------------- //
        // ---  МассивДоступ  --- //
        // --------------------- //
        public function offsetExists($key) {
            return array_key_exists($key, $this->_data);
        }
        public function offsetGet($key) {
            return $this->get($key);
        }
        public function offsetSet($key, $value) {
            if(is_null($key)) {
                throw new InvalidArgumentException('You must specify a key/array index.');
            }
            $this->set($key, $value);
        }
        public function offsetUnset($key) {
            unset($this->_data[$key]);
            unset($this->_dirty_fields[$key]);
        }
        // --------------------- //
        // --- ВОЛШЕБНЫЕ МЕТОДЫ --- //
        // --------------------- //
        public function __get($key) {
            return $this->offsetGet($key);
        }
        public function __set($key, $value) {
            $this->offsetSet($key, $value);
        }
        public function __unset($key) {
            $this->offsetUnset($key);
        }
        public function __isset($key) {
            return $this->offsetExists($key);
        }
        /**
         * Магический метод для перехвата вызовов неопределенных методов класса.
         * В данном случае мы пытаемся преобразовать методы, отформатированные в верблюжьем регистре.
         * методы в методы с подчеркиванием.
         *
         * Это позволяет нам вызывать методы ORM, используя верблюжий регистр, и сохранять обратную совместимость.
         * обратную совместимость.
         *
         * @param  string   $name
         * @param  array    $arguments
         * @return ORM
         */
        public function __call($name, $arguments)
        {
            $method = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $name));
            if (method_exists($this, $method)) {
                return call_user_func_array(array($this, $method), $arguments);
            } else {
                throw new IdiormMethodMissingException("Method $name() does not exist in class " . get_class($this));
            }
        }
        /**
         * Магический метод для перехвата вызовов неопределенных статических методов класса.
         * В данном случае мы пытаемся преобразовать методы, отформатированные в верблюжьем регистре.
         * методы в методы с подчеркиванием.
         *
         * Это позволяет нам вызывать методы ORM, используя верблюжий регистр, и сохранять обратную совместимость.
         * обратную совместимость.
         *
         * @param  string   $name
         * @param  array    $arguments
         * @return ORM
         */
        public static function __callStatic($name, $arguments)
        {
            $method = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $name));
            return call_user_func_array(array('ORM', $method), $arguments);
        }
    }
    /**
     * Класс для обработки операций str_replace, в которых участвуют строки с кавычками
     * @example IdiormString::str_replace_outside_quotes('?', '%s', 'columnA = "Hello?" AND columnB = ?');
     * @example IdiormString::value('columnA = "Hello?" AND columnB = ?')->replace_outside_quotes('?', '%s');
     * @author Jeff Roberson <ridgerunner@fluxbb.org>
     * @author Simon Holywell <treffynnon@php.net>
     * @link http://stackoverflow.com/a/13370709/461813 Ответ на StackOverflow
     */
    class IdiormString {
        protected $subject;
        protected $search;
        protected $replace;
        /**
         * Получите простой в использовании экземпляр класса
         * @param string $subject
         * @return \self
         */
        public static function value($subject) {
            return new self($subject);
        }
        /**
         * Метод сокращения: Замените все вхождения строки поиска на замену
         * строкой, где они появляются вне кавычек.
         * @param string $search
         * @param string $replace
         * @param string $subject
         * @return string
         */
        public static function str_replace_outside_quotes($search, $replace, $subject) {
            return self::value($subject)->replace_outside_quotes($search, $replace);
        }
        /**
         * Установите базовый строковый объект
         * @param string $subject
         */
        public function __construct($subject) {
            $this->subject = (string) $subject;
        }
        /**
         * Замените все вхождения строки поиска на замену
         * строка, где они появляются вне кавычек
         * @param string $search
         * @param string $replace
         * @return string
         */
        public function replace_outside_quotes($search, $replace) {
            $this->search = $search;
            $this->replace = $replace;
            return $this->_str_replace_outside_quotes();
        }
        /**
         * Проверьте входную строку и выполните замену всех повторений
         * из $this->search с $this->replace
         * @author Jeff Roberson <ridgerunner@fluxbb.org>
         * @link http://stackoverflow.com/a/13370709/461813 StackOverflow answer
         * @return string
         */
        protected function _str_replace_outside_quotes(){
            $re_valid = '/
                # Validate string having embedded quoted substrings.
                ^                           # Anchor to start of string.
                (?:                         # Zero or more string chunks.
                  "[^"\\\\]*(?:\\\\.[^"\\\\]*)*"  # Either a double quoted chunk,
                | \'[^\'\\\\]*(?:\\\\.[^\'\\\\]*)*\'  # or a single quoted chunk,
                | [^\'"\\\\]+               # or an unquoted chunk (no escapes).
                )*                          # Zero or more string chunks.
                \z                          # Anchor to end of string.
                /sx';
            if (!preg_match($re_valid, $this->subject)) {
                throw new IdiormStringException("Subject string is not valid in the replace_outside_quotes context.");
            }
            $re_parse = '/
                # Match one chunk of a valid string having embedded quoted substrings.
                  (                         # Either $1: Quoted chunk.
                    "[^"\\\\]*(?:\\\\.[^"\\\\]*)*"  # Either a double quoted chunk,
                  | \'[^\'\\\\]*(?:\\\\.[^\'\\\\]*)*\'  # or a single quoted chunk.
                  )                         # End $1: Quoted chunk.
                | ([^\'"\\\\]+)             # or $2: an unquoted chunk (no escapes).
                /sx';
            return preg_replace_callback($re_parse, array($this, '_str_replace_outside_quotes_cb'), $this->subject);
        }
        /**
         * Обработайте каждый совпадающий чанк из preg_replace_callback, заменяющий.
         * каждое вхождение $this->search на $this->replace
         * @author Jeff Roberson <ridgerunner@fluxbb.org>
         * @link http://stackoverflow.com/a/13370709/461813 Ответ StackOverflow
         * @param array $matches
         * @return string
         */
        protected function _str_replace_outside_quotes_cb($matches) {
            // Возвращает куски строк с кавычками (в группе $1) без изменений.
            if ($matches[1]) return $matches[1];
            // Обрабатывайте только нецитируемые куски (в группе $2).
            return preg_replace('/'. preg_quote($this->search, '/') .'/',
                $this->replace, $matches[2]);
        }
    }
    /**
     * Класс набора результатов для работы с коллекциями экземпляров моделей
     * @author Simon Holywell <treffynnon@php.net>
     */
    class IdiormResultSet implements Countable, IteratorAggregate, ArrayAccess, Serializable {
        /**
         * Текущий набор результатов в виде массива
         * @var array
         */
        protected $_results = array();
        /**
         * По желанию установите содержимое набора результатов, передав массив
         * @param array $results
         */
        public function __construct(array $results = array()) {
            $this->set_results($results);
        }
        /**
         * Установите содержимое набора результатов, передав массив
         * @param array $results
         */
        public function set_results(array $results) {
            $this->_results = $results;
        }
        /**
         * Получить текущий набор результатов в виде массива
         * @return array
         */
        public function get_results() {
            return $this->_results;
        }
        /**
         * Получить текущий набор результатов в виде массива
         * @return array
         */
        public function as_array() {
            return $this->get_results();
        }

        /**
         * Получение количества записей в наборе результатов
         * @return int
         */
        public function count() {
            return count($this->_results);
        }
        /**
         * Получить итератор для данного объекта. В данном случае он поддерживает предварительную обработку
         * над набором результатов.
         * @return \ArrayIterator
         */
        public function getIterator() {
            return new ArrayIterator($this->_results);
        }
        /**
         * МассивДоступ
         * @param int|string $offset
         * @return bool
         */
        public function offsetExists($offset) {
            return isset($this->_results[$offset]);
        }
        /**
         * МассивДоступ
         * @param int|string $offset
         * @return mixed
         */
        public function offsetGet($offset) {
            return $this->_results[$offset];
        }

        /**
         * МассивДоступ
         * @param int|string $offset
         * @param mixed $value
         */
        public function offsetSet($offset, $value) {
            $this->_results[$offset] = $value;
        }
        /**
         * МассивДоступ
         * @param int|string $offset
         */
        public function offsetUnset($offset) {
            unset($this->_results[$offset]);
        }
        /**
         * Сериализуемый
         * @return string
         */
        public function serialize() {
            return serialize($this->_results);
        }
        /**
         * Сериализуемый
         * @param string $serialized
         * @return array
         */
        public function unserialize($serialized) {
            return unserialize($serialized);
        }
       /**
         * Вызов метода для всех моделей в наборе результатов. Это позволяет использовать метод
         * цепочки, например, установить свойство для всех моделей в наборе результатов или
         * любую другую пакетную операцию над моделями.
         * @example ORM::for_table('Widget')->find_many()->set('field', 'value')->save();
         * @param string $method
         * @param array $params
         * @return \IdiormResultSet
         */
        public function __call($method, $params = array()) {
            foreach($this->_results as $model) {
                if (method_exists($model, $method)) {
                    call_user_func_array(array($model, $method), $params);
                } else {
                    throw new IdiormMethodMissingException("Method $method() does not exist in class " . get_class($this));
                }
            }
            return $this;
        }
    }
    /**
     * Место для исключений, возникающих из класса IdiormString
     */
    class IdiormStringException extends Exception {}
    class IdiormMethodMissingException extends Exception {}