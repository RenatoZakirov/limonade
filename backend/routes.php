<?php

class Router {
    private $basePath;
    private $requestMethod;
    private $requestUri;
    private $routes = [];

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
                    header('Content-Type: text/html');
                    readfile(__DIR__ . '/../frontend/index.html');
                },
                // Объявления
                'api/ad/get_list' => function() {
                    $controller = new AdController(ADM_PASS);
                    $controller->getAllAds();
                },
                'api/ad/get_one/{id}' => function($params) {
                    // $id = intval($params[0]);
                    // Проверяем и приводим к целому числу
                    $id = self::validateId($params[0]);
                    $controller = new AdController(ADM_PASS);
                    $controller->getAd($id, false);
                },
                'api/ad/get_list_by_user/{password}' => function($params) {
                    $password = trim((string)$params[0]);
                    $controller = new AdController(ADM_PASS);
                    $controller->findAdsByUser($password);
                },
                'api/ad/delete/{id}/{password}' => function($params) {
                    // $id = intval($params[0]);
                    // Проверяем и приводим к целому числу
                    $id = self::validateId($params[0]);
                    $password = trim((string)$params[1]);
                    $controller = new AdController(ADM_PASS);
                    $controller->deleteAd($id, $password);
                },
                'api/ad/block/{id}/{password}' => function($params) {
                    // $id = intval($params[0]);
                    // Проверяем и приводим к целому числу
                    $id = self::validateId($params[0]);
                    $password = trim((string)$params[1]); // 
                    $controller = new AdController(ADM_PASS);
                    $controller->blockAd($id, $password);
                },

                // Новости
                'api/news/get_list' => function() {
                    $controller = new NewsController(ADM_PASS);
                    $controller->getAllNews();
                },
                'api/news/get_one/{id}' => function($params) {
                    // $id = intval($params[0]);
                    // Проверяем и приводим к целому числу
                    $id = self::validateId($params[0]);
                    $controller = new NewsController(ADM_PASS);
                    $controller->getNews($id);
                },
                'api/news/delete/{id}/{password}' => function($params) {
                    // $id = intval($params[0]);
                    // Проверяем и приводим к целому числу
                    $id = self::validateId($params[0]);
                    $password = trim((string)$params[1]); // 
                    $controller = new NewsController(ADM_PASS);
                    $controller->deleteNews($id, $password);
                },

                // Пользователи
                'api/user/block/{id}/{password}' => function($params) {
                    // $id = intval($params[0]);
                    // Проверяем и приводим к целому числу
                    $id = self::validateId($params[0]);
                    $password = trim((string)$params[1]); // 
                    $controller = new UserController(ADM_PASS);
                    $controller->blockUser($id, $password);
                },

                // Запрос HTML
                'html/ad/get_one/{id}' => function($params) {
                    // $id = intval($params[0]);
                    // Проверяем и приводим к целому числу
                    $id = self::validateId($params[0]);
                    $controller = new AdController(ADM_PASS);
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
                    $controller = new AdController(ADM_PASS);
                    $controller->createAd();
                },
                'api/ad/dislike/{id}' => function($params) {
                    // $id = intval($params[0]);
                    // Проверяем и приводим к целому числу
                    $id = self::validateId($params[0]);
                    $controller = new AdController(ADM_PASS);
                    $controller->dislikeAd($id);
                },
                // Новости
                'api/news/create' => function() {
                    $controller = new NewsController(ADM_PASS);
                    $controller->createNews();
                },
                // Пользователи
                'api/user/create' => function() {
                    $controller = new UserController(ADM_PASS);
                    $controller->createUser();
                },
                // Телеграм
                'bot' => function() {
                    $controller = new TgController(TG_KEY);
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
                array_shift($matches); // Удалим полный путь
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
        http_response_code(404);
        echo json_encode(['code' => 100]);
    }

    private function incrementUniqueVisitors() {
        $ipAddress = $_SERVER['REMOTE_ADDR'];
        
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
}

// Запуск роутера
$router = new Router('/limonade/');
$router->handleRequest();
