<?php

class AdController {
    private $adm_pass;

    public function __construct($adm_pass) {
        $this->adm_pass = $adm_pass;
    }

    // Получить все объявления с пагинацией и фильтрацией по категории
    public function getAllAds() {
        // Фиксированное количество объявлений на странице
        $perPage = 10;

        // Получить номер страницы из запроса
        $page = isset($_GET['page']) ? $_GET['page'] : '1';

        // Проверка на валидность номера страницы
        if (!ctype_digit($page) || intval($page) < 1) {
            // Если параметр не является положительным целым числом, вернуть ошибку
            http_response_code(400);
            echo json_encode(['code' => 200]);
            return;
        }

        $page = intval($page); // Преобразование в целое число после проверки

        // Рассчитать смещение для выборки
        $offset = ($page - 1) * $perPage;

        // Проверить, передана ли категория в запросе
        $category = isset($_GET['category']) ? $_GET['category'] : null;
        // Проверить, передан ли текст в запросе
        $searchText = isset($_GET['text']) ? trim($_GET['text']) : null;

        // Валидация категории
        if ($category) {
            if (!is_string($category) || mb_strlen($category, 'UTF-8') > 6) {
                http_response_code(400);
                // Ошибка со строкой категорий
                echo json_encode(['code' => 201]);
                return;
            }
        }
        // Валидация текста для поиска
        if ($searchText) {
            if (!is_string($searchText) || mb_strlen($searchText, 'UTF-8') > 51) {
                http_response_code(400);
                // Текст который вы ищете слишком длинный
                echo json_encode(['code' => 205]);
                return;
            }
        }

        // Если категория или текст указаны, выполняем поиск с фильтрацией
        if ($category || $searchText) {
            $ads = $this->findAdsByCategoryAndText($category, $searchText, $perPage, $offset);
            if (empty($ads)) {
                http_response_code(400);
                // Не удалось найти активных объявлений
                echo json_encode(['code' => 202]);
                return;
            }
        } else {
            // Если нет категории и текста, просто выбрать объявления с пагинацией
            $query = 'WHERE status = 1 ORDER BY created_at DESC LIMIT ? OFFSET ?';
            $params = [$perPage, $offset];
            $ads = R::findAll('ads', $query, $params);
        }

        // Путь к шаблонному изображению
        $defaultPhotoUrl = $this->getPhotoUrl('templates/no_image');

        // Подготовить результаты
        $result = [];
        foreach ($ads as $ad) {
            $adData = R::exportAll([$ad])[0];
            // Если нет обложки, использовать шаблонное изображение
            $adData['cover_photo'] = $adData['cover_photo'] ? $this->getPhotoUrl($adData['cover_photo']) : $defaultPhotoUrl;
            $result[] = $adData;
        }

        // Отправить результат
        header('Content-Type: application/json');
        echo json_encode($result);
    }

    // Метод для поиска объявлений с постепенным сокращением категории
    private function findAdsByCategoryAndText($category, $searchText, $perPage, $offset) {
        // Пытаемся найти объявления по категории, постепенно сокращая её
        while ($category && mb_strlen($category, 'UTF-8') > 0) {
            $params = [];
            $query = 'WHERE status = 1';
    
            // Добавляем условие по категории
            $categoryParam = $category . '%';
            $query .= ' AND category LIKE ?';
            $params[] = $categoryParam;
    
            // Добавляем условие по тексту
            if ($searchText) {
                // Применяем регистронезависимый поиск по title и description
                $query .= ' AND (LOWER(title) LIKE LOWER(?) OR LOWER(description) LIKE LOWER(?))';
                $searchTextParam = '%' . $searchText . '%'; // Поддержка частичного совпадения
                $params[] = $searchTextParam;
                $params[] = $searchTextParam;
            }
    
            // Добавляем пагинацию
            $query .= ' ORDER BY created_at DESC LIMIT ? OFFSET ?';
            $params[] = $perPage;
            $params[] = $offset;
    
            // Выполняем запрос
            $ads = R::findAll('ads', $query, $params);
    
            // Если объявления найдены, возвращаем их
            if (!empty($ads)) {
                return $ads;
            }
    
            // Убираем последний символ категории и пробуем снова
            $category = substr($category, 0, -1);
        }
    
        // Если объявления не найдены по категории, пробуем искать только по тексту
        if ($searchText) {
            $query = 'WHERE status = 1 AND (LOWER(title) LIKE LOWER(?) OR LOWER(description) LIKE LOWER(?)) ORDER BY created_at DESC LIMIT ? OFFSET ?';
            $searchTextParam = '%' . $searchText . '%'; // Поддержка частичного совпадения
            $ads = R::findAll('ads', $query, [$searchTextParam, $searchTextParam, $perPage, $offset]);

            // Если объявления найдены, возвращаем их
            if (!empty($ads)) {
                return $ads;
            }
        }
    
        // Если ничего не найдено ни по категории, ни по тексту, возвращаем пустой массив
        return [];
    }
    
    // Получить полный путь к фото, если оно существует
    private function getPhotoUrl($photoName) {
        // Базовый URL проекта
        $baseUrl = 'https://www.limonade.pro/';
        $filePath = 'backend/uploads/images/';
        return $photoName ? $baseUrl . $filePath . $photoName . '.jpg' : null;
    }

    // Получить полный путь к фото, если оно существует
    private function getTmpPhotoUrl($photoName) {
        // Базовый URL проекта
        $baseUrl = 'https://www.limonade.pro/';
        $filePath = 'backend/uploads/images/tmp/';
        return $photoName ? $baseUrl . $filePath . $photoName . '.jpg' : null;
    }

    // Найти все активные обьявления одного пользователя
    public function findAdsByUser($password) {
        // Проверка на наличие пароля и ID
        if (empty($password) || mb_strlen($password, 'UTF-8') > 17) {
            // Выводим сообщение на экран
            http_response_code(400);
            echo json_encode(['code' => 203]);
            return;
        }

        // Поиск пользователя по hash_num
        $user = R::findOne('users', 'hash_num = ?', [$password]);

        // Проверка, существует ли пользователь и активен ли он
        if (!$user || $user->status != 1) {
            http_response_code(400);
            echo json_encode(['code' => 204]);
            return;
        }

        // Обновление даты последнего визита
        $user->last_visit = date('Y-m-d H:i:s');
        R::store($user);

        // Запрос на выборку объявлений пользователя со статусом 1 (активные)
        $query = 'WHERE status = 1 AND user_id = ? ORDER BY created_at DESC';
        $ads = R::findAll('ads', $query, [$user->id]);

        // Проверка, найдены ли объявления
        if (empty($ads)) {
            http_response_code(400); // Not Found
            echo json_encode(['code' => 202]);
            return;
        }

        // Путь к шаблонному изображению
        $defaultPhotoUrl = $this->getPhotoUrl('templates/grey');

        // Подготовить результаты
        $result = [];
        foreach ($ads as $ad) {
            $adData = R::exportAll([$ad])[0];
            // Если нет обложки, использовать шаблонное изображение
            $adData['cover_photo'] = $adData['cover_photo'] ? $this->getPhotoUrl($adData['cover_photo']) : $defaultPhotoUrl;
            $result[] = $adData;
        }

        // Отправить результат
        header('Content-Type: application/json');
        echo json_encode($result);
    }

    // Получить одно объявление по ID
    public function getAd($id, $html = false) {
        // Проверяем, это JSON запрос или нет
        $isJsonRequest = false;
        if ($html === false && isset($_SERVER['HTTP_ACCEPT'])) {
            $isJsonRequest = strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;
        }

        // Если это не JSON запрос, возвращаем базовую HTML страницу
        if (!$isJsonRequest) {
            header('Content-Type: text/html');

            // вставляем id обьявления в js код
            $htmlContent = file_get_contents(__DIR__ . '/../../frontend/index.html');
            echo str_replace('phpAdId: 0', 'phpAdId: ' . $id, $htmlContent);
            return;
        }

        // Если это JSON запрос, возвращаем объявление в формате JSON. загружаем объявление по ID
        $ad = R::load('ads', $id);
        
        // Проверить, существует ли объявление и его статус равен 1 (активное объявление)
        if (!$ad->id || $ad->status != 1) {
            // Если объявление не найдено или статус не равен 1, вернуть ошибку 404
            http_response_code(400);
            echo json_encode(['code' => 400]);
        }

        // Подготовить массив для данных объявления
        $adData = R::exportAll([$ad])[0];

        // Обновить пути к фотографиям прямо в соответствующих полях
        if ($adData['photo_1']) {
            $adData['photo_1'] = $this->getPhotoUrl($adData['photo_1']);
            
            // Если есть фото_2, обновить путь
            if ($adData['photo_2']) {
                $adData['photo_2'] = $this->getPhotoUrl($adData['photo_2']);
            }

            // Если есть фото_3, обновить путь
            if ($adData['photo_3']) {
                $adData['photo_3'] = $this->getPhotoUrl($adData['photo_3']);
            }
        }

        // Увеличить значение просмотров на 1
        $ad->viewed += 1;
        // Сохранить изменения в базе данных
        R::store($ad);

        // Отправить результат
        header('Content-Type: application/json');
        echo json_encode($adData);
        
    }

    // Получить одно объявление по ID или самое старое активное объявление из временной таблицы
    public function checkAd($id, $password) {
        // Проверка на наличие пароля
        if (empty($password) || mb_strlen($password, 'UTF-8') > 17) {
            // Выводим сообщение на экран
            http_response_code(400);
            echo json_encode(['code' => 424]);
            return;
        }

        // Проверить введенный пароль
        if ($password != $this->adm_pass) {
            // Выводим сообщение на экран
            http_response_code(400);
            echo json_encode(['code' => 422]);
            return;
        }

        // Определяем таблицу и загружаем объявление
        $table = $id ? 'ads' : 'messages';
        $ad = $id ? R::load($table, $id) : R::findOne($table, 'status = 1 ORDER BY id ASC');

        // error_log("table: " . print_r($table, true));

        // Проверить, существует ли объявление и его статус равен 1 (активное объявление)
        if (!$ad || !$ad->id || $ad->status != 1) {
            http_response_code(400);
            echo json_encode(['code' => 400]);
            return;
        }

        // Подготовить массив для данных объявления
        $adData = R::exportAll([$ad])[0];

        // Обновить пути к фотографиям в зависимости от значения id
        if (!empty($adData['photo_1'])) {
            $adData['photo_1'] = $id ? $this->getPhotoUrl($adData['photo_1']) : $this->getTmpPhotoUrl($adData['photo_1']);
        }
        if (!empty($adData['photo_2'])) {
            $adData['photo_2'] = $id ? $this->getPhotoUrl($adData['photo_2']) : $this->getTmpPhotoUrl($adData['photo_2']);
        }
        if (!empty($adData['photo_3'])) {
            $adData['photo_3'] = $id ? $this->getPhotoUrl($adData['photo_3']) : $this->getTmpPhotoUrl($adData['photo_3']);
        }

        // Увеличить просмотры только для основной таблицы 'ads' и если ID указан
        if ($id) {
            $ad->viewed += 1;
            R::store($ad);
        }

        // Отправить результат
        header('Content-Type: application/json');
        echo json_encode($adData);
    }

    // Обновить обьявление
    public function updateAd($id, $password) {

        // Декодируем JSON из php://input
        $inputData = json_decode(file_get_contents("php://input"), true);

        // error_log("POST: " . print_r($inputData, true));

        // Проверка на наличие пароля
        if (empty($password) || mb_strlen($password, 'UTF-8') > 17) {
            // Выводим сообщение на экран
            http_response_code(400);
            echo json_encode(['code' => 424]);
            return;
        }

        // Проверить введенный пароль
        if ($password != $this->adm_pass) {
            // Выводим сообщение на экран
            http_response_code(400);
            echo json_encode(['code' => 422]);
            return;
        }

        // Поля и их значения по умолчанию
        $fields = [
            'contact' => isset($inputData['contact']) ? trim($inputData['contact']) : null,
            'title' => isset($inputData['title']) ? trim($inputData['title']) : null,
            'category' => isset($inputData['category']) ? trim($inputData['category']) : null,
            'description' => isset($inputData['description']) ? trim($inputData['description']) : null,
        ];

        // Проверка обязательных полей на наличие и пустоту
        foreach ($fields as $key => $value) {
            if (empty($value)) {
                http_response_code(400);
                echo json_encode(['code' => 600]);
                return;
            }
        }

        // Ограничения по длине полей
        $fieldLimits = [
            'contact' => 51,
            'title' => 41,
            'category' => 6,
            'description' => 1001
        ];

        // Проверка длины полей
        foreach ($fieldLimits as $field => $limit) {
            if (mb_strlen($fields[$field], 'UTF-8') > $limit) {
                http_response_code(400);
                echo json_encode(['code' => 601]);
                return;
            }
        }

        // Присвоение переменных для дальнейшего использования
        $contact = $fields['contact'];
        $title = $fields['title'];
        $category = $fields['category'];
        $description = $fields['description'];

        //
        if (!$id) {
            //
            $message_id = isset($inputData['id']) ? trim($inputData['id']) : null;
            //
            $limit = 8;
            // Проверяем, что $id является строкой, состоящей только из цифр и его длина не превышает лимит
            if (!ctype_digit($message_id) || strlen($message_id) > $limit) {
                http_response_code(400);
                echo json_encode(['code' => 101]);
                // Прекращаем выполнение скрипта при ошибке
                return;
            }
            // Загружаем объявление по ID
            $ad = R::load('messages', (int)$message_id);
            
            // Проверить, существует ли объявление и его статус равен 1 (активное объявление)
            if (!$ad->id || $ad->status != 1) {
                // Если объявление не найдено или статус не равен 1, вернуть ошибку 404
                http_response_code(400);
                echo json_encode(['code' => 400]);
                return;
            }
            // error_log("Ad: " . $ad);

            // Создаем новое объявление
            $newAd = R::dispense('ads'); // Используем RedBeanPHP для создания записи в таблице "ads"
            $newAd->user_id = 1;
            $newAd->title = $title; // Заголовок объявления
            $newAd->category = $category; // Категория объявления
            $newAd->description = $description; // Описание объявления

            // Устанавливаем пути к временной и целевой папкам
            $tmpPath = 'uploads/images/tmp/';
            $newPath = 'uploads/images/';

            // Сохраняем названия фотографий в таблицу ads
            $newAd->cover_photo = $ad->cover_photo ? $ad->cover_photo : null;
            $newAd->photo_1 = $ad->photo_1 ? $ad->photo_1 : null;
            $newAd->photo_2 = $ad->photo_2 ? $ad->photo_2 : null;
            $newAd->photo_3 = $ad->photo_3 ? $ad->photo_3 : null;

            // Перемещаем файлы из временной папки в целевую и удаляем их из временной папки
            $photos = ['cover_photo', 'photo_1', 'photo_2', 'photo_3'];

            foreach ($photos as $photoField) {
                // Проверяем, что поле не пустое
                if ($ad->{$photoField}) {
                    // Добавляем расширение к имени файла
                    $fileName = $ad->{$photoField} . '.jpg';
                    $tmpFile = $tmpPath . $fileName;
                    $newFile = $newPath . $fileName;

                    // Проверяем, что файл существует в временной папке
                    if (file_exists($tmpFile)) {
                        // Перемещаем файл в новую папку
                        if (rename($tmpFile, $newFile)) {
                            // Успешно перемещено
                            // echo "Файл {$fileName} успешно перемещен.\n";
                        } else {
                            // Ошибка при перемещении
                            // echo "Не удалось переместить файл {$fileName}.\n";
                            http_response_code(400);
                            echo json_encode(['code' => 461]);
                            return;
                        }
                    } else {
                        // echo "Файл {$fileName} не найден в временной папке.\n";
                        http_response_code(400);
                        echo json_encode(['code' => 462]);
                        return;
                    }
                }
            }

            $newAd->contact = $contact; // Контактная информация
            $newAd->status = 1; // Статус объявления
            $newAd->created_at = date('Y-m-d H:i:s'); // Дата создания
            $newAd->viewed = 0; // Счетчик просмотров (изначально 0)
            R::store($newAd); // Сохраняем объявление в базе данных

            $ad->status = 0;
            $ad->closed_at = date('Y-m-d H:i:s');
            R::store($ad); // Сохраняем объявление в базе данных

            // Возвращаем ответ
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            // return;
        }

        if ($id) {
            // Загружаем объявление по ID
            $ad = R::load('ads', $id);

            // Проверить, существует ли объявление и его статус равен 1 (активное объявление)
            if (!$ad->id || $ad->status != 1) {
                // Если объявление не найдено или статус не равен 1, вернуть ошибку 404
                http_response_code(400);
                echo json_encode(['code' => 400]);
                return;
            }
            // Сохраняем новые данные в старое обьявление
            $ad->title = $title; // Заголовок объявления
            $ad->category = $category; // Категория объявления
            $ad->description = $description; // Описание объявления
            $ad->contact = $contact; // Контактная информация
            R::store($ad); // Сохраняем объявление в базе данных
            // Возвращаем ответ
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
        }
    }

    //
    public function cleanAd($id, $password) {
        // Проверка на наличие пароля и ID
        if (empty($password) || empty($id) || mb_strlen($password, 'UTF-8') > 17) {
            // Выводим сообщение на экран
            http_response_code(400);
            echo json_encode(['code' => 420]);
            return;
        }

        // Проверить введенный пароль
        if ($password != $this->adm_pass) {
            // Выводим сообщение на экран
            http_response_code(400);
            echo json_encode(['code' => 422]);
            return;
        }

        // Загрузить объявление по ID
        $ad = R::load('messages', $id);

        // Проверка объявления
        if (!$ad->id) {
            //
            http_response_code(400);
            echo json_encode(['code' => 421]);
            return;
        }

        // Проверка статуса объявления
        if ($ad->status != 1) {
            //
            http_response_code(400);
            echo json_encode(['code' => 423]);
            return;
        }

        // Устанавливаем пути к временной папке
        $tmpPath = 'uploads/images/tmp/';

        // Собираем названия фотографий
        $photos = [$ad->cover_photo, $ad->photo_1, $ad->photo_2, $ad->photo_3];

        foreach ($photos as $photoFile) {
            // Проверяем, что поле не пустое
            if ($photoFile) {
                // Формируем полный путь к файлу с расширением
                $tmpFile = $tmpPath . $photoFile . '.jpg';
        
                // Удаляем файл, если он существует
                if (file_exists($tmpFile)) {
                    if (!unlink($tmpFile)) {
                        // Ошибка при удалении
                        http_response_code(400);
                        echo json_encode(['code' => 462]);
                        return;
                    }
                }
            }
        }

        // Обновить статус объявления и дату
        $ad->status = 0; // Статус объявления
        $ad->closed_at = date('Y-m-d H:i:s'); // Дата удаления
        R::store($ad);

        // Сообщение об успехе
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    }

    // Создать новое объявление
    public function createAd() {
        // Поля и их значения по умолчанию
        $fields = [
            'hash_num' => isset($_POST['password']) ? trim($_POST['password']) : null,
            'title' => isset($_POST['title']) ? trim($_POST['title']) : null,
            'category' => isset($_POST['category']) ? trim($_POST['category']) : null,
            'description' => isset($_POST['description']) ? trim($_POST['description']) : null,
            'contact' => isset($_POST['contact']) ? trim($_POST['contact']) : null,
        ];

        // Проверка обязательных полей на наличие и пустоту
        foreach ($fields as $key => $value) {
            if (empty($value)) {
                http_response_code(400);
                // echo json_encode(["message" => "Поле $field пустое или отсутствует"]);
                echo json_encode(['code' => 600]);
                return;
            }
        }

        // Ограничения по длине полей
        $fieldLimits = [
            'hash_num' => 17,
            'title' => 41,
            'category' => 6,
            'description' => 1001,
            'contact' => 51
        ];

        // Проверка длины полей
        foreach ($fieldLimits as $field => $limit) {
            if (mb_strlen($fields[$field], 'UTF-8') > $limit) {
                http_response_code(400);
                // echo json_encode([
                //     "message" => "Длина поля $field превышает $limit символов",
                //     "lengths" => [$field => strlen($fields[$field])]
                // ]);
                echo json_encode(['code' => 601]);
                return;
            }
        }

        // Присвоение переменных для дальнейшего использования
        $hash_num = $fields['hash_num'];
        $title = $fields['title'];
        $category = $fields['category'];
        $description = $fields['description'];
        $contact = $fields['contact'];
        $permanent = ($_POST['permanent'] ?? 'false') === 'true';

        // Поиск пользователя по hash_num
        $user = R::findOne('users', 'hash_num = ?', [$hash_num]);

        if (!$user || $user->status != 1) {
            http_response_code(400);
            echo json_encode(['code' => 602]);
            return;
        }

        // Обновление даты последнего визита
        $user->last_visit = date('Y-m-d H:i:s');
        R::store($user);

        if ($hash_num != $this->adm_pass) {

            // Проверка количества активных объявлений пользователя
            $ads_limit = 3;
            $activeAdsCount = R::count('ads', 'user_id = ? AND status = 1', [$user->id]);

            if ($activeAdsCount >= $ads_limit) {
                http_response_code(400);
                echo json_encode(['code' => 603]);
                return;
            }
        }

        // Проверяем, есть ли загруженные фотографии в $_FILES
        if (isset($_FILES['photos'])) {
            $photos = [];
            
            // Проверяем, является ли $_FILES['photos'] массивом или одиночным файлом
            $isMultiple = isset($_FILES['photos']['name']) && is_array($_FILES['photos']['name']);
            
            // Определяем количество файлов для обработки (максимум 3)
            $fileCount = $isMultiple ? min(3, count($_FILES['photos']['name'])) : 1;

            // Обрабатываем каждый файл
            for ($i = 0; $i < $fileCount; $i++) {
                // Приведение массива $_FILES['photos'] к унифицированному виду для удобства обработки
                $photo = $this->checkPhoto($isMultiple, $i);
                
                // Если фото обработано успешно, добавляем его в массив
                if ($photo !== null) {
                    $photos[] = $photo;
                } else {
                    return;  // Если была ошибка, ответ уже отправлен в методе checkPhoto
                }
            }

            // Разрешенные типы изображений
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            // Максимальный размер файла (5 MB)
            $maxSize = 5 * 1024 * 1024;
            // Минимальные допустимые размеры изображения
            $minResolution = [600, 450];
            // Путь к папке для сохранения изображений
            $filePath = 'uploads/images/';

            // Массив для хранения имен фотографий объявления
            $adPhotos = [];
            $savedPhotos = [];  // Массив для хранения путей сохраненных файлов

            // Цикл по всем загруженным фотографиям
            foreach ($photos as $key => $photoFile) {
                $imageEditor = new ImageEditor($photoFile); // Создаем объект для редактирования изображений

                // Проверяем тип файла
                if (!$imageEditor->validateType($allowedTypes)) {
                    $this->deleteSavedPhotos($savedPhotos);
                    http_response_code(400);
                    // echo json_encode(["message" => 'Недопустимый тип фото, порядковый номер фото: ' . $key + 1]);
                    echo json_encode(['code' => 622]);
                    return;
                }

                // Проверяем размер файла
                if (!$imageEditor->validateSize($maxSize)) {
                    $this->deleteSavedPhotos($savedPhotos);
                    http_response_code(413);
                    // echo json_encode(["message" => 'Фото превышает допустимый размер, порядковый номер фото: ' . $key + 1]);
                    echo json_encode(['code' => 623]);
                    return;
                }

                // Проверяем разрешение изображения
                if (!$imageEditor->validateResolution($minResolution)) {
                    $this->deleteSavedPhotos($savedPhotos);
                    http_response_code(400);
                    // echo json_encode(["message" => 'Фото имеет слишком маленькое разрешение, порядковый номер фото: ' . $key + 1 . '. Минимальное разрешение должно быть 600 * 450 пикселей (или наоборот)']);
                    echo json_encode(['code' => 624]);
                    return;
                }

                // Обработка фотографий в зависимости от их индекса
                switch ($key) {
                    case 0:
                        // Обрабатываем cover_photo как обложку
                        $coverPhotoName = $imageEditor->generateUniqueName(); // Генерируем уникальное имя для обложки
                        
                        // Если ориентация горизонтальная, создаем изображение с полями
                        if ($imageEditor->orientation == 'h') {
                            $imageEditor->createImage();          // Создаем изображение
                            $imageEditor->resizeToFit();          // Меняем размер изображения
                            $imageEditor->createPaddedImage();    // Создаем изображение с добавлением полей
                            $imageEditor->savePadded($filePath . $coverPhotoName . '.jpg'); // Сохраняем с полями
                        } else {
                            // Для вертикальных изображений сохраняем оригинал
                            $imageEditor->createImage();
                            $imageEditor->resizeToFit();
                            $imageEditor->saveOriginal($filePath . $coverPhotoName . '.jpg');
                        }
                        $adPhotos[0] = $coverPhotoName;
                        $savedPhotos[] = $filePath . $coverPhotoName . '.jpg';
                        // Обрабатываем photo_1
                        $this->processPhoto($imageEditor, $filePath, $adPhotos, $savedPhotos, 1);
                        break;

                    case 1:
                        // Обрабатываем photo_2
                        $this->processPhoto($imageEditor, $filePath, $adPhotos, $savedPhotos, 2);
                        break;

                    case 2:
                        // Обрабатываем photo_3
                        $this->processPhoto($imageEditor, $filePath, $adPhotos, $savedPhotos, 3);
                        break;
                }
            }
        }

        // Создаем новое объявление
        $ad = R::dispense('ads'); // Используем RedBeanPHP для создания записи в таблице "ads"
        $ad->user_id = $user->id;
        $ad->title = $title; // Заголовок объявления
        $ad->category = $category; // Категория объявления
        $ad->description = $description; // Описание объявления
        $ad->permanent = $permanent; // Флаг вечного объявления

        // Сохраняем фотографии в базе данных
        $ad->cover_photo = isset($adPhotos[0]) ? (string) $adPhotos[0] : null;
        $ad->photo_1 = isset($adPhotos[1]) ? (string) $adPhotos[1] : null;
        $ad->photo_2 = isset($adPhotos[2]) ? (string) $adPhotos[2] : null;
        $ad->photo_3 = isset($adPhotos[3]) ? (string) $adPhotos[3] : null;

        $ad->contact = $contact; // Контактная информация
        $ad->status = 1; // Статус объявления
        $ad->created_at = date('Y-m-d H:i:s'); // Дата создания
        $ad->viewed = 0; // Счетчик просмотров (изначально 0)
        R::store($ad); // Сохраняем объявление в базе данных

        // Возвращаем ответ
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    }

    private function checkPhoto($isMultiple, $index) {
        // Получаем данные о файле (для одного файла используем прямой доступ, для нескольких — через индекс)
        $tmpName = $isMultiple ? $_FILES['photos']['tmp_name'][$index] : $_FILES['photos']['tmp_name'];
        $name = $isMultiple ? $_FILES['photos']['name'][$index] : $_FILES['photos']['name'];
        $error = $isMultiple ? $_FILES['photos']['error'][$index] : $_FILES['photos']['error'];
        $size = $isMultiple ? $_FILES['photos']['size'][$index] : $_FILES['photos']['size'];

        // Проверка на наличие ошибок при загрузке файла
        if ($error !== UPLOAD_ERR_OK) {
            http_response_code(400);
            // echo json_encode(["message" => 'Ошибка при загрузке файла, порядковый номер файла: ' . ($index + 1)]);
            echo json_encode(['code' => 620]);
            return null;
        }

        // Проверяем, является ли файл изображением
        $imageInfo = getimagesize($tmpName);
        if ($imageInfo === false) {
            http_response_code(400);
            // echo json_encode(["message" => 'Файл не является фото, порядковый номер файла: ' . ($index + 1)]);
            echo json_encode(['code' => 621]);
            return null;
        }

        // Возвращаем массив с информацией о фото, если всё успешно
        return [
            'tmp_name' => $tmpName,
            'name' => $name,
            'size' => $size,
            'type' => $imageInfo['mime'],  // Тип файла (MIME)
            'width' => $imageInfo[0],      // Ширина изображения
            'height' => $imageInfo[1]      // Высота изображения
        ];
    }

    // Функция для удаления сохранённых фото
    private function deleteSavedPhotos($savedPhotos) {
        foreach ($savedPhotos as $photoPath) {
            if (file_exists($photoPath)) {
                // error_log("файл найден. путь файла: $photoPath");
                unlink($photoPath);
            }
        }
    }

    // Функция для обработки фотографий
    private function processPhoto($imageEditor, $filePath, &$adPhotos, &$savedPhotos, $key) {
        // Генерация уникального имени файла
        $photoName = $imageEditor->generateUniqueName();
        // Создание изображения
        $imageEditor->createImage();
        // Изменение размера изображения
        $imageEditor->resizeToFit();
        //
        if ($imageEditor->orientation == 'h') {
            // Сохраняем оригинал горизонтального изображения
            $imageEditor->saveOriginal($filePath . $photoName . '.jpg');
        } else {
            // Создаем изображение с добавлением полей
            $imageEditor->createPaddedImage();
            // Сохраняем изображение с полями
            $imageEditor->savePadded($filePath . $photoName . '.jpg');
        }
        // Добавляем имя файла в массив
        $adPhotos[$key] = $photoName;
        // Сохраняем путь к файлу
        $savedPhotos[] = $filePath . $photoName . '.jpg';
    }

    // Пометить объявление как удалённое и удалить связанные фотографии
    public function deleteAd($id, $password) {
        // Проверка на наличие пароля и ID
        if (empty($password) || empty($id) || mb_strlen($password, 'UTF-8') > 17) {
            // Выводим сообщение на экран
            http_response_code(400);
            echo json_encode(['code' => 420]);
            return;
        }

        // Поиск пользователя по hash_num
        $user = R::findOne('users', 'hash_num = ?', [$password]);

        // Проверка, существует ли пользователь и активен ли он
        if (!$user || $user->status != 1) {
            http_response_code(400);
            echo json_encode(['code' => 401]);
            return;
        }

        // Обновление даты последнего визита
        $user->last_visit = date('Y-m-d H:i:s');
        R::store($user);

        // Загрузить объявление по ID
        $ad = R::load('ads', $id);

        // Проверка объявления
        if (!$ad->id) {
            //
            http_response_code(400);
            echo json_encode(['code' => 421]);
            return;
        }

        // Проверка id владельца
        if ($ad->user_id != $user->id) {
            //
            http_response_code(400);
            echo json_encode(['code' => 422]);
            return;
        }

        // Проверка статуса объявления
        if ($ad->status != 1) {
            //
            http_response_code(400);
            echo json_encode(['code' => 423]);
            return;
        }

        // Удалить связанные фотографии, если они существуют
        $this->deletePhotoIfExists($ad->cover_photo);
        $this->deletePhotoIfExists($ad->photo_1);
        $this->deletePhotoIfExists($ad->photo_2);
        $this->deletePhotoIfExists($ad->photo_3);

        // Обновить статус объявления и дату
        $ad->status = 0; // Статус объявления
        $ad->closed_at = date('Y-m-d H:i:s'); // Дата удаления
        R::store($ad);

        // Сообщение об успехе
        header('Content-Type: application/json');
        // Объявление было успешно удалено
        echo json_encode(['success' => true]);
    }

    // Вспомогательный метод для удаления фото, если оно существует
    private function deletePhotoIfExists($photoName) {
        if ($photoName) {
            $imageFile = __DIR__ . '/../uploads/images/' . $photoName . '.jpg';
            if (file_exists($imageFile)) {
                unlink($imageFile);
            }
        }
    }

    // Заблокировать обьявление по ID и удалить связанные фотографии
    public function blockAd($id, $password) {
        // Проверка на наличие пароля и ID
        if (empty($password) || empty($id) || mb_strlen($password, 'UTF-8') > 17) {
            // Выводим сообщение на экран
            http_response_code(400);
            echo json_encode(['code' => 420]);
            return;
        }

        // Проверить введенный пароль
        if ($password != $this->adm_pass) {
            // Выводим сообщение на экран
            http_response_code(400);
            echo json_encode(['code' => 422]);
            return;
        }

        // Загрузить объявление по ID
        $ad = R::load('ads', $id);

        // Проверка объявления
        if (!$ad->id) {
            //
            http_response_code(400);
            echo json_encode(['code' => 421]);
            return;
        }

        // Проверка статуса объявления
        if ($ad->status != 1) {
            //
            http_response_code(400);
            echo json_encode(['code' => 423]);
            return;
        }

        // Удалить связанные фотографии, если они существуют
        $this->deletePhotoIfExists($ad->cover_photo);
        $this->deletePhotoIfExists($ad->photo_1);
        $this->deletePhotoIfExists($ad->photo_2);
        $this->deletePhotoIfExists($ad->photo_3);

        // Обновить статус объявления и дату
        $ad->status = 0; // Статус объявления
        $ad->closed_at = date('Y-m-d H:i:s'); // Дата удаления
        R::store($ad);

        // Сообщение об успехе
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    }

    // Удалить старые обьявления
    public function closeOldAds($password) {
        // Проверка на наличие пароля
        if (empty($password) || mb_strlen($password, 'UTF-8') > 17) {
            // Выводим сообщение на экран
            // http_response_code(400);
            // echo json_encode(['code' => 420]);
            header('Content-Type: text/html');
            echo 'Проблема с паролем...';
            return;
        }

        // Проверить введенный пароль
        if ($password != $this->adm_pass) {
            // Выводим сообщение на экран
            // http_response_code(400);
            // echo json_encode(['code' => 422]);
            header('Content-Type: text/html');
            echo 'Проблема с доступом...';
            return;
        }

        // Вычисление даты 10 дней назад
        $dateLimit = date('Y-m-d H:i:s', strtotime('-12 days'));

        // Поиск всех объявлений, созданных более 10 дней назад и активных
        $oldAds = R::find('ads', 'created_at < ? AND status = 1', [$dateLimit]);

        // Проверка, есть ли объявления для закрытия
        if (empty($oldAds)) {
            // http_response_code(400);
            // echo json_encode(['code' => 422]);
            header('Content-Type: text/html');
            echo 'Объявления старше 12 дней не найдены...';
            return;
        }

        // header('Content-Type: text/html');
        // echo 'Всего найдено объявлений: ' . count($oldAds);
        // return;

        // error_log('Массив объявлений: ' . print_r($oldAds, true)); die;

        // Закрытие объявлений в цикле
        foreach ($oldAds as $ad) {
            // Удалить связанные фотографии
            $this->deletePhotoIfExists($ad->cover_photo);
            $this->deletePhotoIfExists($ad->photo_1);
            $this->deletePhotoIfExists($ad->photo_2);
            $this->deletePhotoIfExists($ad->photo_3);

            // Обновить статус и дату закрытия
            $ad->status = 0;
            $ad->closed_at = date('Y-m-d H:i:s');
            R::store($ad);
        }

        // Сообщение об успехе
        // header('Content-Type: application/json');
        // echo json_encode(['success' => true]);
        header('Content-Type: text/html');
        echo 'Всего удаленно объявлений: ' . count($oldAds);
    }

    // Пожаловаться на обьявление по ID
    public function dislikeAd($id) {
        // Присвоение данных
        $text = trim($_POST['text']) ?? null;

        if (empty($id) || empty($text)) {
            // Выводим сообщение на экран
            http_response_code(400);
            echo json_encode(['code' => 430]);
            return;
        }

        // Проверить введенный текст
        if (mb_strlen($text, 'UTF-8') > 401) {
            // Выводим сообщение на экран
            http_response_code(400);
            echo json_encode(['code' => 431]);
            return;
        }

        // Создаем новую жалобу
        $dislike = R::dispense('dislikes');
        $dislike->ad_id = $id; // ID объявления
        $dislike->description = $text; // Описание жалобы

        $dislike->status = 1; // Статус жалобы
        $dislike->created_at = date('Y-m-d H:i:s'); // Дата создания
        R::store($dislike); // Сохраняем жалобу в базе данных

        // Возвращаем ответ
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    }

}
