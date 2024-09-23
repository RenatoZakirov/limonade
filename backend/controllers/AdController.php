<?php

class AdController {
    // Получить все объявления с пагинацией и фильтрацией по категории
    public function getAllAds() {
        // Фиксированное количество объявлений на странице
        $perPage = 10;

        // Получить номер страницы из запроса
        $page = isset($_GET['page']) ? $_GET['page'] : '1';

        // Проверка на валидность номера страницы
        if (!ctype_digit($page) || intval($page) < 1) {
            // Если параметр не является положительным целым числом, вернуть ошибку
            http_response_code(400); // Bad Request
            echo json_encode(["message" => "Invalid page number"]);
            return;
        }

        $page = intval($page); // Преобразование в целое число после проверки

        // Рассчитать смещение для выборки
        $offset = ($page - 1) * $perPage;

        // Проверить, передана ли категория в запросе
        $category = isset($_GET['category']) ? $_GET['category'] : null;

        // Базовый запрос для выборки объявлений
        $query = 'WHERE status = 1 ORDER BY created_at DESC LIMIT ? OFFSET ?';
        $params = [$perPage, $offset];

        if ($category) {
            // Проверка, что категория — это строка и не длиннее 6 символов
            if (!is_string($category) || strlen($category) > 4) {
                http_response_code(400); // Bad Request
                echo json_encode(["message" => "Invalid category"]);
                return;
            }

            // Выполняем поиск, начиная с полной категории и убираем символы, если не находим объявлений
            $ads = $this->findAdsByCategory($category, $perPage, $offset);
            if (empty($ads)) {
                // Если ничего не найдено, вернуть сообщение
                http_response_code(404); // Not Found
                echo json_encode(["message" => "Обьявления не найдены"]);
                return;
            }
        } else {
            // Если категории нет, просто выбрать объявления с пагинацией
            $ads = R::findAll('ads', $query, $params);
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

    // Метод для поиска объявлений с постепенным сокращением категории
    private function findAdsByCategory($category, $perPage, $offset) {
        while (strlen($category) > 0) {
            // Выполняем запрос с текущей категорией
            $query = 'WHERE status = 1 AND category = ? ORDER BY created_at DESC LIMIT ? OFFSET ?';
            $ads = R::findAll('ads', $query, [$category, $perPage, $offset]);

            // Если объявления найдены, возвращаем их
            if (!empty($ads)) {
                return $ads;
            }

            // Убираем последний символ категории и пробуем снова
            $category = substr($category, 0, -1);
        }

        // Если объявления не найдены на любом уровне, вернуть пустой массив
        return [];
    }

    // Получить полный путь к фото, если оно существует
    private function getPhotoUrl($photoName) {
        $filePath = 'backend/uploads/images/';
        return $photoName ? $filePath . $photoName . '.jpg' : null;
    }

    // Найти все активные обьявления одного пользователя
    public function findAdsByUser() {
        // Получить тело запроса (например, JSON с hash_num)
        $data = json_decode(file_get_contents("php://input"), true);

        // Проверка наличия hash_num
        if (!isset($data['password'])) {
            http_response_code(400);
            echo json_encode(["message" => "Не указан пароль"]);
            return;
        }

        // Поиск пользователя по hash_num
        $user = R::findOne('users', 'hash_num = ?', [$data['password']]);

        // Проверка, существует ли пользователь и активен ли он
        if (!$user || $user->status != 1) {
            http_response_code(400);
            echo json_encode(["message" => "Пользователь не найден или неактивен"]);
            return;
        }

        // Обновление даты последнего визита
        $user->last_visit = date('d.m.Y H:i:s');
        R::store($user);

        // Фиксированное количество объявлений на странице
        $perPage = 10;

        // Получить номер страницы из запроса
        $page = isset($_GET['page']) ? $_GET['page'] : '1';

        // Проверка на валидность номера страницы
        if (!ctype_digit($page) || intval($page) < 1) {
            http_response_code(400); // Bad Request
            echo json_encode(["message" => "Invalid page number"]);
            return;
        }

        $page = intval($page); // Преобразование в целое число после проверки

        // Рассчитать смещение для выборки
        $offset = ($page - 1) * $perPage;

        // Запрос на выборку объявлений пользователя со статусом 1 (активные)
        $query = 'WHERE status = 1 AND user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?';
        $ads = R::findAll('ads', $query, [$user->id, $perPage, $offset]);

        // Проверка, найдены ли объявления
        if (empty($ads)) {
            http_response_code(404); // Not Found
            echo json_encode(["message" => "Объявления не найдены"]);
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
    public function getAd($id) {
        // Проверяем заголовки запроса
        $isJsonRequest = false;
        if (isset($_SERVER['HTTP_ACCEPT'])) {
            $isJsonRequest = strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;
        }

        // Если запрос не JSON, отправляем базовую HTML-страницу
        if (!$isJsonRequest) {
            header('Content-Type: text/html');
            readfile(__DIR__ . '/../../frontend/index.html');
            return;
        }

        // Загрузить объявление по ID
        $ad = R::load('ads', $id);
        
        // Проверить, существует ли объявление и его статус равен 1 (активное объявление)
        if ($ad->id && $ad->status == 1) {
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
            R::store($ad); // Сохранить изменения в базе данных

            // Отправить результат
            header('Content-Type: application/json');
            echo json_encode($adData);
        } else {
            // Если объявление не найдено или статус не равен 1, вернуть ошибку 404
            http_response_code(404);
            echo json_encode(["message" => "Объявление не найдено"]);
        }
    }

    // Создать новое объявление
    public function createAd() {
        // Собираем данные из POST как массив $data
        $data = [
            'hash_num' => $_POST['password'] ?? null,
            'title' => $_POST['title'] ?? null,
            'description' => $_POST['description'] ?? null,
            'category' => $_POST['category'] ?? null,
            'contact' => $_POST['contact'] ?? null
        ];

        // Проверка наличия всех необходимых полей
        if (
            !isset($data['hash_num']) ||
            !isset($data['title']) ||
            !isset($data['description']) ||
            !isset($data['category']) ||
            !isset($data['contact'])
        ) {
            http_response_code(400);
            echo json_encode(["message" => "Недостаточно данных"]);
            return;
        }

        // Присвоение и валидация данных
        $hash_num = $data['hash_num'];
        $title = $data['title'];
        $description = $data['description'];
        $category = $data['category'];
        $contact = $data['contact'];

        // Проверка полей на пустоту
        if (empty(trim($title)) || empty(trim($description)) || empty(trim($category)) || empty(trim($contact))) {
            http_response_code(400);
            echo json_encode(["message" => "Одно из обязательных полей пусто"]);
            return;
        }
        
        // Проверка длины полей
        if (strlen($title) > 100 || strlen($description) > 1100 || strlen($category) > 5 || strlen($contact) > 200) {
            http_response_code(400);
            echo json_encode(["message" => "Длина данных превышает допустимые пределы"]);
            echo json_encode([
                "title" => strlen($title),
                "description" => strlen($description),
                "category" => strlen($category),
                "contact" => strlen($contact)
            ]);
            return;
        }

        // Поиск пользователя по hash_num
        $user = R::findOne('users', 'hash_num = ?', [$hash_num]);

        if (!$user || $user->status != 1) {
            http_response_code(400);
            echo json_encode(["message" => "Пользователь не найден или неактивен"]);
            return;
        }

        // Обновление даты последнего визита
        $user->last_visit = date('d.m.Y H:i:s');
        R::store($user);

        // Проверка количества активных объявлений пользователя
        $activeAdsCount = R::count('ads', 'user_id = ? AND status = 1', [$user->id]);

        if ($activeAdsCount >= 4) {
            http_response_code(400);
            echo json_encode(["message" => "Лимит объявлений исчерпан"]);
            return;
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
                    http_response_code(415);
                    echo json_encode(["message" => 'Недопустимый тип фото, порядковый номер фото: ' . $key + 1]);
                    return;
                }

                // Проверяем размер файла
                if (!$imageEditor->validateSize($maxSize)) {
                    $this->deleteSavedPhotos($savedPhotos);
                    http_response_code(413);
                    echo json_encode(["message" => 'Фото превышает допустимый размер, порядковый номер фото: ' . $key + 1]);
                    return;
                }

                // Проверяем разрешение изображения
                if (!$imageEditor->validateResolution($minResolution)) {
                    $this->deleteSavedPhotos($savedPhotos);
                    http_response_code(400);
                    echo json_encode(["message" => 'Фото имеет слишком маленькое разрешение, порядковый номер фото: ' . $key + 1 . '. Минимальное разрешение должно быть 600 * 450 пикселей (или наоборот)']);
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
        $ad->description = $description; // Описание объявления
        $ad->category = $category; // Категория объявления

        // Сохраняем фотографии в базе данных
        $ad->cover_photo = isset($adPhotos[0]) ? (string) $adPhotos[0] : null;
        $ad->photo_1 = isset($adPhotos[1]) ? (string) $adPhotos[1] : null;
        $ad->photo_2 = isset($adPhotos[2]) ? (string) $adPhotos[2] : null;
        $ad->photo_3 = isset($adPhotos[3]) ? (string) $adPhotos[3] : null;

        $ad->contact = $contact; // Контактная информация
        $ad->status = 1; // Статус объявления
        $ad->created_at = date('d.m.Y H:i:s'); // Дата создания
        $ad->viewed = 0; // Счетчик просмотров (изначально 0)
        R::store($ad); // Сохраняем объявление в базе данных

        // Возвращаем ответ с ID нового объявления
        header('Content-Type: application/json');
        echo json_encode(["id" => $ad->id, "message" => "Объявление создано"]);
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
            echo json_encode(["message" => 'Ошибка при загрузке файла, порядковый номер файла: ' . ($index + 1)]);
            return null;
        }

        // Проверяем, является ли файл изображением
        $imageInfo = getimagesize($tmpName);
        if ($imageInfo === false) {
            http_response_code(400);
            echo json_encode(["message" => 'Файл не является фото, порядковый номер файла: ' . ($index + 1)]);
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
        $photoName = $imageEditor->generateUniqueName(); // Генерация уникального имени файла
        $imageEditor->createImage();                    // Создание изображения
        $imageEditor->resizeToFit();                    // Изменение размера изображения

        if ($imageEditor->orientation == 'h') {
            $imageEditor->saveOriginal($filePath . $photoName . '.jpg'); // Сохраняем оригинал горизонтального изображения
        } else {
            $imageEditor->createPaddedImage();                           // Создаем изображение с добавлением полей
            $imageEditor->savePadded($filePath . $photoName . '.jpg');   // Сохраняем изображение с полями
        }
        $adPhotos[$key] = $photoName; // Добавляем имя файла в массив
        $savedPhotos[] = $filePath . $photoName . '.jpg'; // Сохраняем путь к файлу
    }

    // Пометить объявление как удалённое (статус = 0) и удалить связанные фотографии
    public function deleteAd($id) {
        // Получить тело запроса (например, JSON с hash_num)
        $data = json_decode(file_get_contents("php://input"), true);

        // Проверка наличия hash_num
        if (!isset($data['password'])) {
            http_response_code(400);
            echo json_encode(["message" => "Не указан пароль"]);
            return;
        }

        // Поиск пользователя по hash_num
        $user = R::findOne('users', 'hash_num = ?', [$data['password']]);

        // Проверка, существует ли пользователь и активен ли он
        if (!$user || $user->status != 1) {
            http_response_code(400);
            echo json_encode(["message" => "Пользователь не найден или неактивен"]);
            return;
        }

        // Обновление даты последнего визита
        $user->last_visit = date('d.m.Y H:i:s');
        R::store($user);

        // Загрузить объявление по ID
        $ad = R::load('ads', $id);

        // Проверка, существует ли объявление
        if ($ad->id && $ad->status == 1) {
            if ($ad->user_id == $user->id) {
                // Удалить связанные фотографии, если они существуют
                $this->deletePhotoIfExists($ad->cover_photo);
                $this->deletePhotoIfExists($ad->photo_1);
                $this->deletePhotoIfExists($ad->photo_2);
                $this->deletePhotoIfExists($ad->photo_3);

                // Пометить объявление как удалённое (статус = 0)
                $ad->status = 0; // Статус объявления
                $ad->closed_at = date('d.m.Y H:i:s'); // Дата удаления
                R::store($ad);

                // Ответ в формате JSON
                header('Content-Type: application/json');
                echo json_encode(["message" => "Объявление было успешно удалено"]);
            } else {
                // Если объявление не принадлежит пользователю, вернуть ошибку 403
                http_response_code(403);
                echo json_encode(["message" => "Доступ запрещён"]);
            }
        } else {
            // Если объявление не найдено, вернуть ошибку 404
            http_response_code(404);
            echo json_encode(["message" => "Объявление не найдено"]);
        }
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

}
