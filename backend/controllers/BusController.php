<?php

class BusController {
    private $adm_pass;
    private $creator_pass;

    public function __construct($adm_pass, $creator_pass) {
        $this->adm_pass = $adm_pass;
        $this->creator_pass = $creator_pass;
    }

    // Искать доступные рейсы
    public function findRoutesByDate($routeDate, $routeCreator, $flag) {
        // Проверяем, что параметры не пустые
        if (empty($routeDate) || empty($routeCreator)) {
            http_response_code(400);
            echo json_encode(['error' => 'Некорректные параметры запроса!']);
            return;
        }

        // Получаем все рейсы по заданной дате и создателю
        // $routes = R::findAll('routes', 'route_date = ? AND created_by = ? AND status != 4', [$routeDate, $routeCreator]);
        // Формируем SQL-запрос в зависимости от флага
        if ($flag == 1) {
            $routes = R::findAll('routes', 'route_date = ? AND created_by = ? AND status = 1', [$routeDate, $routeCreator]);
        } else {
            $routes = R::findAll('routes', 'route_date = ? AND created_by = ? AND status != 4', [$routeDate, $routeCreator]);
        }

        // Если рейсы не найдены, отправляем ошибку
        if (empty($routes)) {
            http_response_code(404);
            echo json_encode(['error' => 'Рейсы на выбранную дату не найдены!']);
            return;
        }

        // Формируем массив id найденных рейсов
        $routeIds = array_map(fn($route) => $route->id, $routes);

        // Сбрасываем ключи массива
        $routeIds = array_values($routeIds);

        // Отправить результат
        header('Content-Type: application/json');
        echo json_encode($routeIds);
    }

    // Искать конкретный рейс
    public function findRouteById($routeId) {
        // Проверяем, что передан корректный ID
        if (empty($routeId) || !is_numeric($routeId) || $routeId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Некорректный ID рейса!']);
            return;
        }

        // Ищем рейс по ID
        $route = R::load('routes', $routeId);

        // Проверяем, найден ли рейс (R::load всегда возвращает объект, но с ID 0, если запись не найдена)
        if ($route->id == 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Рейс с таким ID не найден!']);
            return;
        }

        // Преобразуем объект в массив
        $routeData = $route->export();

        // Отправить результат
        header('Content-Type: application/json');
        echo json_encode($routeData);        
    }

    // Искать рейс с урезанными данными
    public function findCroppedRouteById($routeId) {
        // Проверяем, что передан корректный ID
        if (empty($routeId) || !is_numeric($routeId) || $routeId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Некорректный ID рейса!']);
            return;
        }

        // Ищем рейс по ID
        $route = R::load('routes', $routeId);

        // Проверяем, найден ли рейс (R::load всегда возвращает объект, но с ID 0, если запись не найдена)
        if ($route->id == 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Рейс с таким ID не найден!']);
            return;
        }

        // Преобразуем объект в массив
        $routeData = $route->export();

        // Декодируем JSON seats
        $seats = json_decode($routeData['seats'], true) ?? [];

        // Обрабатываем seats, убирая contact и заменяя статусы
        $processedSeats = [];
        foreach ($seats as $seat => $data) {
            $processedSeats[$seat] = ($data['status'] === 1) ? 1 : 3;
        }

        // Обновляем данные рейса перед отправкой
        $routeData['seats'] = $processedSeats;

        // Отправить результат
        header('Content-Type: application/json');
        echo json_encode($routeData);
    }

    // Обновить конкретный рейс
    public function updateRouteById($routeId) {
        // Получаем входные данные
        $inputData = json_decode(file_get_contents('php://input'), true);

        // Проверяем, передан ли создатель рейса
        if (!isset($inputData['createdBy'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Отсутствует создатель рейса!']);
            return;
        }

        // Проверяем, передан ли пароль
        if (!isset($inputData['password'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Отсутствует пароль!']);
            return;
        }

        // Получаем рейс из базы по ID
        $route = R::load('routes', $routeId);
    
        // Проверяем, существует ли рейс
        if (!$route->id) {
            http_response_code(404);
            echo json_encode(['error' => 'Рейс с таким ID не найден!']);
            return;
        }

        // Проверяем, что пользователь является создателем рейса
        if ($route->created_by !== $inputData['createdBy']) {
            http_response_code(403);
            echo json_encode(['error' => 'Вы не являетесь создателем рейса!']);
            return;
        }

        // Проверяем пароль
        if ($inputData['password'] !== $this->creator_pass) {
            http_response_code(403);
            echo json_encode(['error' => 'Неверный пароль!']);
            return;
        }
    
        // Обновляем только непустые значения
        if (isset($inputData['status']) && $inputData['status'] !== '') {
            $route->status = (int)$inputData['status'];
        }
        if (isset($inputData['location']) && $inputData['location'] !== '') {
            $route->location = $inputData['location'];
        }
        if (isset($inputData['departureTime']) && $inputData['departureTime'] !== '') {
            $route->departure_time = $inputData['departureTime'];
        }
        if (isset($inputData['busNumber']) && $inputData['busNumber'] !== '') {
            $route->bus_number = $inputData['busNumber'];
        }
        if (isset($inputData['basePrice']) && $inputData['basePrice'] !== '') {
            $route->base_price = (int)$inputData['basePrice'];
        }
        if (isset($inputData['discountPrice']) && $inputData['discountPrice'] !== '') {
            $route->discount_price = (int)$inputData['discountPrice'];
        }
        if (isset($inputData['telegramGroup']) && $inputData['telegramGroup'] !== '') {
            $route->telegram_group = $inputData['telegramGroup'];
        }
        if (isset($inputData['wifiPassword']) && $inputData['wifiPassword'] !== '') {
            $route->wifi_password = $inputData['wifiPassword'];
        }

        // Обновляем массив мест
        $route->seats = json_encode($inputData['seats'], JSON_UNESCAPED_UNICODE);
    
        // НЕ трогаем created_at, но обновляем updated_at
        $route->updated_at = date('Y-m-d H:i:s');
        //
        R::store($route);

        // Отправить результат
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            // 'message' => 'Рейс успешно обновлен',
        ]);
    }

    // Сохранить новый рейс
    public function createNewRoute() {
        // Получаем входные данные
        $inputData = json_decode(file_get_contents('php://input'), true);
    
        // Проверяем наличие необходимых полей
        if (
            // Дата обязательна
            empty($inputData['date']) ||
            // Создатель обязателен
            empty($inputData['createdBy']) ||
            // Пароль обязателен
            empty($inputData['password']) ||
            // Места обязательны
            empty($inputData['seats']) ||
            // Места должны быть массивом
            !is_array($inputData['seats'])
        ) {
            http_response_code(400);
            echo json_encode(['error' => 'Некорректные входные данные!']);
            return;
        }
    
        // Проверяем пароль создателя
        if ($inputData['password'] !== $this->creator_pass) {
            http_response_code(403);
            echo json_encode(['error' => 'Неверный пароль!']);
            return;
        }
    
        // Создаем новый объект маршрута
        $route = R::dispense('routes');
        // Дата обязательна, проверена ранее
        $route->route_date = $inputData['date'];
        // Создатель обязателен, проверен ранее
        $route->created_by = $inputData['createdBy'];
        // По умолчанию 1
        $route->status = isset($inputData['status']) ? (int)$inputData['status'] : 1;
        $route->location = !empty($inputData['location']) ? $inputData['location'] : null;
        $route->departure_time = !empty($inputData['departureTime']) ? $inputData['departureTime'] : null;
        $route->bus_number = !empty($inputData['busNumber']) ? $inputData['busNumber'] : null;
        $route->base_price = isset($inputData['basePrice']) ? (int)$inputData['basePrice'] : 0;
        $route->discount_price = isset($inputData['discountPrice']) ? (int)$inputData['discountPrice'] : 0;
        $route->telegram_group = !empty($inputData['telegramGroup']) ? $inputData['telegramGroup'] : null;
        $route->wifi_password = !empty($inputData['wifiPassword']) ? $inputData['wifiPassword'] : null;

        // Конвертируем массив seats в JSON
        $route->seats = json_encode($inputData['seats'], JSON_UNESCAPED_UNICODE);
    
        // Сохраняем в БД
        $id = R::store($route);
    
        // Отправить результат
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            // 'message' => 'Рейс успешно создан',
            // 'route_id' => $id
        ]);
    }    

}
