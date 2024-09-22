<?php

class Router
{
    private $basePath;
    private $requestMethod;
    private $requestUri;
    private $path;
    private $pathParts;

    // Инициализирует переменные и подготавливает путь для обработки
    public function __construct($basePath = '/')
    {
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
    public function handleRequest()
    {
        // error_log("PATH: " . $this->path);

        if ($this->pathParts[0] === 'api' && $this->pathParts[1] === 'ads') {
            // Обрабатывает API-запросы
            $this->handleApiRequest();
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
    private function handleApiRequest()
    {
        $controller = new AdController();

        if (count($this->pathParts) === 2) {
            // Обработка запросов к коллекции объявлений или создание нового объявления
            $this->handleAdsCollectionOrCreateRequest($controller);
        } elseif (count($this->pathParts) === 3 && is_numeric($this->pathParts[2])) {
            // Обработка запросов для отдельного объявления
            $this->handleSingleAdRequest($controller);
        } else {
            http_response_code(404);
            echo json_encode(["message" => "Not Found"]);
        }
    }

    // Обрабатывает запросы к коллекции объявлений (GET) или создание нового объявления (POST)
    private function handleAdsCollectionOrCreateRequest($controller)
    {
        switch ($this->requestMethod) {
            case 'GET':
                // Получить все объявления с параметрами пагинации
                $controller->getAllAds();
                break;
            case 'POST':
                // Создать новое объявление
                $controller->createAd();
                break;
            default:
                http_response_code(405);
                echo json_encode(["message" => "Method Not Allowed"]);
                break;
        }
    }

    // Обрабатывает запросы к конкретному объявлению (GET, DELETE)
    private function handleSingleAdRequest($controller)
    {
        $adId = intval($this->pathParts[2]);

        switch ($this->requestMethod) {
            case 'GET':
                $controller->getAd($adId);
                break;
            case 'DELETE':
                $controller->deleteAd($adId);
                break;
            default:
                http_response_code(405);
                echo json_encode(["message" => "Method Not Allowed"]);
                break;
        }
    }
}

// Создание и запуск роутера
$router = new Router('/limonade/');
$router->handleRequest();
