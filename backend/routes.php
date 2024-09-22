<?php

class Router {
    private $basePath;
    private $requestMethod;
    private $requestUri;
    private $path;
    private $pathParts;

    // Инициализирует переменные и подготавливает путь для обработки
    public function __construct($basePath = '/') {
        $this->basePath = $basePath;
        $this->requestMethod = $_SERVER["REQUEST_METHOD"];
        $this->requestUri = $_SERVER["REQUEST_URI"];
        // Очищает путь от базового пути и возвращает чистый путь
        $path = str_replace($this->basePath, '', $this->requestUri);
        $this->path = parse_url($path, PHP_URL_PATH);
        // Помещает переменные в массив
        $this->pathParts = explode('/', trim($this->path, '/'));
    }

    // Основная функция, которая решает, какой маршрут обработать
    public function handleRequest() {
        if ($this->pathParts[0] === 'api' && $this->pathParts[1] === 'ads') {
            // Обрабатывает API-запросы
            $this->handleApiRequest();
        } elseif ($this->path === 'bot') {
            // Обработка вебхуков для Telegram
            $this->handleTelegramWebhook();
        } elseif ($this->requestMethod === 'GET' && empty($this->path)) {
            // Отправляет index.html при запросе на корневой путь
            header('Content-Type: text/html');
            readfile(__DIR__ . '/../frontend/index.html');
        } else {
            http_response_code(404);
            echo json_encode(["message" => "Not Found"]);
        }
    }

    // Обрабатывает API-запросы
    private function handleApiRequest() {
        $controller = new AdController();

        if (count($this->pathParts) === 2) {
            // Обработка коллекции объявлений или создание нового объявления
            if ($this->requestMethod === 'GET') {
                $controller->getAllAds();
            } elseif ($this->requestMethod === 'POST') {
                $controller->createAd();
            } else {
                http_response_code(405);
                echo json_encode(["message" => "Method Not Allowed"]);
            }
        } elseif (count($this->pathParts) === 3 && is_numeric($this->pathParts[2])) {
            // Обработка конкретного объявления по ID
            $adId = intval($this->pathParts[2]);
            if ($this->requestMethod === 'GET') {
                $controller->getAd($adId);
            } elseif ($this->requestMethod === 'DELETE') {
                $controller->deleteAd($adId);
            } else {
                http_response_code(405);
                echo json_encode(["message" => "Method Not Allowed"]);
            }
        } elseif (count($this->pathParts) === 3 && $this->pathParts[2] === 'hash_num'
            && $this->requestMethod === 'POST') {
            // Обработка запросов к коллекции объявлений по пользователю (по hash_num)
            $controller->findAdsByUser();
        } else {
            http_response_code(404);
            echo json_encode(["message" => "Not Found"]);
        }
    }

    // Обрабатывает вебхуки для Telegram
    private function handleTelegramWebhook() {
        $controller = new TgController();

        // Метод для обработки вебхуков
        $controller->handleWebhook();
    }

}

// Создание и запуск роутера
$router = new Router('/limonade/');
$router->handleRequest();
