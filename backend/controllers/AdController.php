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

        // Базовый запрос для выборки объявлений
        $query = 'WHERE status = 1 ORDER BY created_at DESC LIMIT ? OFFSET ?';
        $params = [$perPage, $offset];

        if ($category) {
            // Проверка, что категория — это строка и не длиннее 6 символов
            if (!is_string($category) || mb_strlen($category, 'UTF-8') > 6) {
                http_response_code(400);
                echo json_encode(['code' => 201]);
                return;
            }

            // Выполняем поиск, начиная с полной категории и убираем символы, если не находим объявлений
            $ads = $this->findAdsByCategory($category, $perPage, $offset);
            if (empty($ads)) {
                // Если ничего не найдено, вернуть сообщение
                http_response_code(400);
                echo json_encode(['code' => 202]);
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
        while (mb_strlen($category, 'UTF-8') > 0) {
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
        $baseUrl = 'https://www.limonade.pro/'; // Базовый URL проекта
        $filePath = 'backend/uploads/images/';
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
        R::store($ad); // Сохранить изменения в базе данных

        // Отправить результат
        header('Content-Type: application/json');
        echo json_encode($adData);
        
    }

    // Создать новое объявление
    public function createAd() {
        // Поля и их значения по умолчанию
        $fields = [
            'hash_num' => $_POST['password'] ?? null,
            'title' => $_POST['title'] ?? null,
            'category' => $_POST['category'] ?? null,
            'description' => $_POST['description'] ?? null,
            'contact' => $_POST['contact'] ?? null,
        ];

        // Проверка обязательных полей на наличие и пустоту
        foreach ($fields as $key => $value) {
            if (empty(trim($value))) {
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
            $ads_limit = 6;
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

        // Загрузить объявление по ID
        $ad = R::load('ads', $id);

        // Проверка объявления
        if (!$ad->id) {
            //
            http_response_code(400);
            echo json_encode(['code' => 421]);
            return;
        }

        // Проверить введенный пароль
        if ($password != $this->adm_pass) {
            // Выводим сообщение на экран
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
        echo json_encode(['success' => true]);
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
