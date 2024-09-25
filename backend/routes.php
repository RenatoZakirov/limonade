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
        if ($this->requestMethod === 'GET' && empty($this->path)) {
            header('Content-Type: text/html');
            readfile(__DIR__ . '/../frontend/index.html');
        }
        // API-запросы
        elseif ($this->pathParts[0] === 'api') {
            $this->handleApiRequest();
        }
        // Обработка вебхуков от Telegram
        elseif ($this->path === 'bot' && $this->requestMethod === 'POST') {
            $this->handleTelegramWebhook();
        }
        else {
            http_response_code(404);
            echo json_encode(["message" => "Not Found"]);
        }
    }

    private function handleApiRequest() {
        $controller = new AdController();

        // Показать список объявлений
        if ($this->requestMethod === 'GET' && $this->pathParts[1] === 'get_list') {
            $controller->getAllAds();
        }
        // Показать одно объявление по ID
        elseif ($this->requestMethod === 'GET' && $this->pathParts[1] === 'get_one' && isset($this->pathParts[2]) && is_numeric($this->pathParts[2])) {
            $adId = intval($this->pathParts[2]);
            $controller->getAd($adId);
        }
        // Создать новое объявление
        elseif ($this->requestMethod === 'POST' && $this->pathParts[1] === 'create') {
            $controller->createAd();
        }
        // Показать список своих объявлений
        elseif ($this->requestMethod === 'POST' && $this->pathParts[1] === 'get_own_list') {
            $controller->findAdsByUser();
        }
        // Удалить одно объявление по ID
        elseif ($this->requestMethod === 'POST' && $this->pathParts[1] === 'delete' && isset($this->pathParts[2]) && is_numeric($this->pathParts[2])) {
            $adId = intval($this->pathParts[2]);
            $controller->deleteAd($adId);
        }
        else {
            http_response_code(404);
            echo json_encode(["message" => "Not Found"]);
        }
    }

    // Обработка вебхуков от Telegram
    private function handleTelegramWebhook() {
        $controller = new TgController();
        $controller->handleWebhook();
    }
}

// Запуск роутера
$router = new Router('/limonade/');
$router->handleRequest();
