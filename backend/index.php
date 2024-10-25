<?php

// Установка заголовков для API
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Обработка OPTIONS запросов для CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Подключение библиотеки для работы с БД
require_once 'libraries/redbeanphp/rb.php';

// Подключение библиотеки для работы с фото
require_once 'libraries/imageeditorphp/ie.php';

// Конфигурация базы данных
require_once 'config/config.php';

// Базовая настройка RedBeanPHP
R::setup($dsn, $username, $password);

// Дополнительные настройки RedBeanPHP
// R::freeze(false);
R::freeze(true); // Установить true в продакшн для повышения производительности

// Включение логирования ошибок
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/app.log');

// Подключение контроллера обьявлений
require_once 'controllers/AdController.php';

// Подключение контроллера пользователей
require_once 'controllers/UserController.php';

// Подключение контроллера новостей
require_once 'controllers/NewsController.php';

// Подключение контроллера ботов
require_once 'controllers/TgController.php';

// Подключение маршрутизатора
require_once 'routes.php';
