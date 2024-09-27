<?php

class Router {
    private $basePath;
    private $requestMethod;
    private $requestUri;
    private $path;
    private $pathParts;

    public function __construct($basePath = '/') {
        $this->basePath = $basePath;
        $this->requestMethod = $_SERVER["REQUEST_METHOD"];
        $this->requestUri = $_SERVER["REQUEST_URI"];
        $path = str_replace($this->basePath, '', $this->requestUri);
        $this->path = parse_url($path, PHP_URL_PATH);
        $this->pathParts = explode('/', trim($this->path, '/'));
    }

    public function handleRequest() {
        // Стартовая страница
        if ($this->requestMethod === 'GET'
            && empty($this->path)) {
            header('Content-Type: text/html');
            readfile(__DIR__ . '/../frontend/index.html');
        }
        // Обработка HTML-запросов
        elseif ($this->pathParts[0] === 'html') {
            $this->handleHtmlRequest();
        }
        // API-запросы
        elseif ($this->pathParts[0] === 'api') {
            $this->handleApiRequest();
        }
        // Обработка вебхуков от Telegram
        elseif ($this->path === 'bot') {
            $this->handleTelegramWebhook();
        }
        else {
            http_response_code(404);
            echo json_encode(["message" => "Not Found"]);
        }
    }

    private function handleHtmlRequest() {
        $controller = new AdController();

        // Показать одно объявление по ID (HTML)
        if ($this->requestMethod === 'GET'
            && $this->pathParts[1] === 'ad'
            && $this->pathParts[2] === 'get_one'
            && isset($this->pathParts[3]) && is_numeric($this->pathParts[3])) {
            $adId = intval($this->pathParts[3]);
            $controller->getAd($adId, true);
        } else {
            http_response_code(404);
            echo json_encode(["message" => "Not Found"]);
        }
    }

    private function handleApiRequest() {
        $controller = new AdController();

        // Показать список объявлений
        if ($this->requestMethod === 'GET'
            && $this->pathParts[1] === 'ad'
            && $this->pathParts[2] === 'get_list') {
            $controller->getAllAds();
        }
        // Показать одно объявление по ID
        elseif ($this->requestMethod === 'GET'
            && $this->pathParts[1] === 'ad'
            && $this->pathParts[2] === 'get_one'
            && isset($this->pathParts[3]) && is_numeric($this->pathParts[3])) {
            $adId = intval($this->pathParts[3]);
            $controller->getAd($adId, false);
        }
        // Заблокировать одно обьявление по ID
        elseif ($this->requestMethod === 'GET'
            && $this->pathParts[1] === 'ad'
            && $this->pathParts[2] === 'block'
            && isset($this->pathParts[3]) // password
            && isset($this->pathParts[4]) && is_numeric($this->pathParts[4])) {
            // Приводим пароль к строке и обрезаем лишние пробелы
            $password = trim((string)$this->pathParts[3]);
            $adId = intval($this->pathParts[4]);
            // Вызов метода блокировки объявления
            $controller->blockAd($password, $adId);
        }
        // Создать новое объявление
        elseif ($this->requestMethod === 'POST'
            && $this->pathParts[1] === 'ad'
            && $this->pathParts[2] === 'create') {
            $controller->createAd();
        }
        // Показать список активных объявлений одного пользователя
        elseif ($this->requestMethod === 'POST'
            && $this->pathParts[1] === 'ad'
            && $this->pathParts[2] === 'get_your_list') {
            $controller->findAdsByUser();
        }
        // Удалить одно объявление по ID
        elseif ($this->requestMethod === 'POST'
            && $this->pathParts[1] === 'ad'
            && $this->pathParts[2] === 'delete'
            && isset($this->pathParts[3]) && is_numeric($this->pathParts[3])) {
            $adId = intval($this->pathParts[3]);
            $controller->deleteAd($adId);
        }
        // Заблокировать одного пользователя по ID
        elseif ($this->requestMethod === 'GET'
            && $this->pathParts[1] === 'user'
            && $this->pathParts[2] === 'block'
            && isset($this->pathParts[3]) // password
            && isset($this->pathParts[4]) && is_numeric($this->pathParts[4])) {
            $controller = new UserController();
            // Приводим пароль к строке и обрезаем лишние пробелы
            $password = trim((string)$this->pathParts[3]);
            $userId = intval($this->pathParts[4]);
            // Вызов метода блокировки пользователя
            $controller->blockUser($password, $userId);
        }
        else {
            http_response_code(404);
            echo json_encode(["message" => "Not Found"]);
        }
    }

    // Обработка вебхуков от Telegram
    private function handleTelegramWebhook() {
        if ($this->requestMethod === 'POST') {
            $controller = new TgController();
            $controller->handleWebhook();
        }
        else {
            http_response_code(404);
            echo json_encode(["message" => "Not Found"]);
        }
    }
}

// Запуск роутера
$router = new Router('/limonade/');
$router->handleRequest();
