<?php

class Router {
    private $basePath;
    private $requestMethod;
    private $requestUri;
    private $routes = [];
    // Мой IP для исключения
    private $excludedIp = '113.189.101.12'; // 14.191.114.103

    public function __construct($basePath = '/') {
        $this->basePath = $basePath;
        $this->requestMethod = $_SERVER["REQUEST_METHOD"];
        $this->requestUri = $_SERVER["REQUEST_URI"];
        $this->initializeRoutes();
    }

    private function initializeRoutes() {
        $this->routes = [
            'GET' => [
                '/' => function() {
                    // header('Content-Type: text/html');
                    // readfile(__DIR__ . '/../frontend/index.html');
                    $this->notFound();
                },
                'web' => function() { 
                    header('Content-Type: text/html');
                    readfile(__DIR__ . '/../frontend/index_web.html'); 
                },
                // Объявления
                'api/ad/get_list' => function() {
                    $controller = new AdController(ADM_PASS, ADM_USER_ID, TRANSLATE_API_KEY);
                    $controller->getAllAds();
                },
                'web/ad/get_list' => function() {
                    $controller = new AdController(ADM_PASS, ADM_USER_ID, TRANSLATE_API_KEY);
                    $controller->getAllAds();
                },
                'api/ad/get_one/{id}' => function($params) {
                    // Проверяем и приводим к целому числу
                    $id = self::validateId($params[0]);
                    $controller = new AdController(ADM_PASS, ADM_USER_ID, TRANSLATE_API_KEY);
                    $controller->getAd($id, false);
                },
                'web/ad/get_one/{id}' => function($params) {
                    // Проверяем и приводим к целому числу
                    $id = self::validateId($params[0]);
                    $controller = new AdController(ADM_PASS, ADM_USER_ID, TRANSLATE_API_KEY);
                    $controller->getAd($id, false);
                },
                'web/ad/get_weather' => function() {
                    // 
                    $controller = new AdController(ADM_PASS, ADM_USER_ID, TRANSLATE_API_KEY);
                    $controller->getWeatherAd();
                },
                'web/ad/get_dollar' => function() {
                    // 
                    $controller = new AdController(ADM_PASS, ADM_USER_ID, TRANSLATE_API_KEY);
                    $controller->getDollarAd();
                },
                'api/ad/check/{id}/{password}' => function($params) {
                    // Используем тернарный оператор для проверки id
                    $id = ($params[0] === '0') ? 0 : self::validateId($params[0]);
                    $password = trim((string)$params[1]);
                    $controller = new AdController(ADM_PASS, ADM_USER_ID, TRANSLATE_API_KEY);
                    $controller->checkAd($id, $password);
                },
                'api/ad/get_list_by_user/{password}' => function($params) {
                    $password = trim((string)$params[0]);
                    $controller = new AdController(ADM_PASS, ADM_USER_ID, TRANSLATE_API_KEY);
                    $controller->findAdsByUser($password);
                },
                'web/ad/get_list_by_user/{telegram_user_id}' => function($params) {
                    $telegram_user_id = trim((string)$params[0]);
                    $controller = new AdController(ADM_PASS, ADM_USER_ID, TRANSLATE_API_KEY);
                    $controller->findAdsByTgId($telegram_user_id);
                },
                'api/ad/delete/{id}/{password}' => function($params) {
                    // Проверяем и приводим к целому числу
                    $id = self::validateId($params[0]);
                    $password = trim((string)$params[1]);
                    $controller = new AdController(ADM_PASS, ADM_USER_ID, TRANSLATE_API_KEY);
                    $controller->deleteAd($id, $password);
                },
                'web/ad/delete/{id}/{telegram_user_id}' => function($params) {
                    // Проверяем и приводим к целому числу
                    $id = self::validateId($params[0]);
                    $telegram_user_id = trim((string)$params[1]);
                    $controller = new AdController(ADM_PASS, ADM_USER_ID, TRANSLATE_API_KEY);
                    $controller->deleteAdByTgId($id, $telegram_user_id);
                },
                'api/ad/block/{id}/{password}' => function($params) {
                    // Проверяем и приводим к целому числу
                    $id = self::validateId($params[0]);
                    $password = trim((string)$params[1]); // 
                    $controller = new AdController(ADM_PASS, ADM_USER_ID, TRANSLATE_API_KEY);
                    $controller->blockAd($id, $password);
                },
                'api/ad/clean/{id}/{password}' => function($params) {
                    // Проверяем и приводим к целому числу
                    $id = self::validateId($params[0]);
                    $password = trim((string)$params[1]); // 
                    $controller = new AdController(ADM_PASS, ADM_USER_ID, TRANSLATE_API_KEY);
                    $controller->cleanAd($id, $password);
                },
                'api/ad/close_old/{password}' => function($params) {
                    // Проверяем
                    $password = trim((string)$params[0]); // 
                    $controller = new AdController(ADM_PASS, ADM_USER_ID, TRANSLATE_API_KEY);
                    $controller->closeOldAds($password);
                },

                // Новости
                'api/news/get_list' => function() {
                    $controller = new NewsController(ADM_PASS, ADM_USER_ID);
                    $controller->getAllNews();
                },
                'api/news/get_one/{id}' => function($params) {
                    // Проверяем и приводим к целому числу
                    $id = self::validateId($params[0]);
                    $controller = new NewsController(ADM_PASS, ADM_USER_ID);
                    $controller->getNews($id);
                },
                'api/news/delete/{id}/{password}' => function($params) {
                    // Проверяем и приводим к целому числу
                    $id = self::validateId($params[0]);
                    $password = trim((string)$params[1]); // 
                    $controller = new NewsController(ADM_PASS, ADM_USER_ID);
                    $controller->deleteNews($id, $password);
                },

                // Пользователи
                'api/user/block/{id}/{password}' => function($params) {
                    // Проверяем и приводим к целому числу
                    $id = self::validateId($params[0]);
                    $password = trim((string)$params[1]); // 
                    $controller = new UserController(ADM_PASS, ADM_USER_ID);
                    $controller->blockUser($id, $password);
                },

                // Запрос HTML
                'html/ad/get_one/{id}' => function($params) {
                    // Проверяем и приводим к целому числу
                    $id = self::validateId($params[0]);
                    $controller = new AdController(ADM_PASS, ADM_USER_ID, TRANSLATE_API_KEY);
                    $controller->getAd($id, true);
                },
                'html/admin' => function() {
                    header('Content-Type: text/html');
                    readfile(__DIR__ . '/../frontend/index_adm.html');
                },
            ],
            'POST' => [
                // Объявления
                'api/ad/create' => function() {
                    $controller = new AdController(ADM_PASS, ADM_USER_ID, TRANSLATE_API_KEY);
                    // $controller->createAd();
                    $controller->createAdByTgId();
                },
                'web/ad/create' => function() {
                    $controller = new AdController(ADM_PASS, ADM_USER_ID, TRANSLATE_API_KEY);
                    $controller->createAdByTgId();
                },
                'api/ad/update/{id}/{password}' => function($params) {
                    // Используем тернарный оператор для проверки id
                    $id = ($params[0] === '0') ? 0 : self::validateId($params[0]);
                    $password = trim((string)$params[1]);
                    $controller = new AdController(ADM_PASS, ADM_USER_ID, TRANSLATE_API_KEY);
                    $controller->updateAd($id, $password);
                },
                'api/ad/dislike/{id}' => function($params) {
                    // Проверяем и приводим к целому числу
                    $id = self::validateId($params[0]);
                    $controller = new AdController(ADM_PASS, ADM_USER_ID, TRANSLATE_API_KEY);
                    $controller->dislikeAd($id);
                },
                'web/ad/dislike/{id}' => function($params) {
                    // Проверяем и приводим к целому числу
                    $id = self::validateId($params[0]);
                    $controller = new AdController(ADM_PASS, ADM_USER_ID, TRANSLATE_API_KEY);
                    $controller->dislikeAd($id);
                },
                // Новости
                'api/news/create' => function() {
                    $controller = new NewsController(ADM_PASS, ADM_USER_ID);
                    $controller->createNews();
                },
                // Пользователи (пока не написан этот метод)
                'api/user/create' => function() {
                    $controller = new UserController(ADM_PASS, ADM_USER_ID);
                    $controller->createUser();
                },
                // Телеграм
                'bot' => function() {
                    $controller = new TgController(TG_KEY, ADM_2_USER_ID);
                    $controller->handleWebhook();
                },
                // Телеграм Игра
                'game' => function() {
                    // Подключение контроллера игры
                    require_once 'controllers/GameController.php';
                    $controller = new GameController();
                    $controller->handleWebhook();
                }
            ],
        ];
    }

    public function handleRequest() {
        $path = str_replace($this->basePath, '', $this->requestUri);
        // $pathParts = explode('/', trim($path, '/'));
        $pathParts = explode('?', $path, 2); // Разделяем по '?'
        $path = trim($pathParts[0], '/');

        // Добавим вывод для отладки
        // error_log("Request URI: " . $this->requestUri);
        // error_log("Base Path: " . $this->basePath);
        // error_log("Path: " . $path);

        // Считаем уникальных пользователей
        $this->incrementUniqueVisitors();
        
        // Считаем просмотры для каждой страницы
        $this->incrementPageView($path);

        // Учитываем только запросы к "/web" и дальше
        if (strpos($path, 'web') === 0) {
            $this->recordWebVisitor();
        }

        // Проверяем, пустой ли путь
        if ($path === '') {
            $this->routes[$this->requestMethod]['/']();
            return;
        }

        // Найдем подходящий маршрут
        foreach ($this->routes[$this->requestMethod] as $route => $handler) {
            // Заменим параметры на регулярные выражения
            $pattern = preg_replace('/{[^}]+}/', '([^/]+)', $route);
            if (preg_match('#^' . $pattern . '$#', $path, $matches)) {
                // Удалим полный путь
                array_shift($matches);
                $this->callHandler($handler, $matches);
                return;
            }
        }

        // Если маршрут не найден
        $this->notFound();
    }

    private function callHandler($handler, $params) {
        if (is_callable($handler)) {
            // Вызовем анонимную функцию обработчик
            call_user_func($handler, $params);
        } else {
            // Если это не функция, то можно сделать дополнительные действия
            $this->notFound();
        }
    }

    // Приватный статический метод для проверки ID
    private static function validateId($id) {
        $limit = 8;
        // Проверяем, что $id является строкой, состоящей только из цифр и его длина не превышает лимит
        if (!ctype_digit($id) || strlen($id) > $limit) {
            http_response_code(400);
            echo json_encode(['code' => 101]);
            exit;  // Прекращаем выполнение скрипта при ошибке
        }
        return (int) $id;  // Приводим ID к целому числу и возвращаем
    }

    private function notFound() {
        // http_response_code(404);
        // echo json_encode(['code' => 100]);
        // 404
        header('Content-Type: text/html');
        $htmlContent = file_get_contents(__DIR__ . '/../frontend/404_web.html');
        echo $htmlContent;
    }

    private function incrementUniqueVisitors() {
        $ipAddress = $_SERVER['REMOTE_ADDR'];

        // Пропускаем мой IP

        // Проверка уникальности IP за последние 24 часа
        $existingVisitor = R::findOne('visitors', 'ip_address = ? AND visit_date = ?', [
            $ipAddress, date('Y-m-d')
        ]);
        
        if (!$existingVisitor) {
            $visitor = R::dispense('visitors');
            $visitor->ip_address = $ipAddress;
            $visitor->visit_date = date('Y-m-d');
            R::store($visitor);
        }
    }

    private function incrementPageView($path) {
        $view = R::findOne('pages', 'path = ?', [$path]);
        if (!$view) {
            $view = R::dispense('pages');
            $view->path = $path;
            $view->views = 1;
            $view->last_viewed = date('Y-m-d H:i:s');
        } else {
            $view->views++;
            $view->last_viewed = date('Y-m-d H:i:s');
        }
        R::store($view);
    }

    private function recordWebVisitor() {
        $ipAddress = $_SERVER['REMOTE_ADDR'];

        // Пропускаем мой IP
        if ($ipAddress === $this->excludedIp) {
            return;
        }

        // Проверка записи IP в таблице web_visitors за текущую дату
        $existingVisitor = R::findOne('webvisitors', 'ip_address = ? AND visit_date = ?', [
            $ipAddress, date('Y-m-d')
        ]);

        if (!$existingVisitor) {
            $webVisitor = R::dispense('webvisitors');
            $webVisitor->ip_address = $ipAddress;
            $webVisitor->visit_date = date('Y-m-d');
            R::store($webVisitor);
        }
    }
}

// Запуск роутера
$router = new Router('/limonade/');
$router->handleRequest();
