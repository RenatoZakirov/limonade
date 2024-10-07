<?php

class NewsController {
    private $adm_pass;

    public function __construct($adm_pass) {
        $this->adm_pass = $adm_pass;
    }

    // Получить все новости с пагинацией
    public function getAllNews() {
        // Фиксированное количество новостей на странице
        $perPage = 10;

        // Получить номер страницы из запроса
        $page = isset($_GET['page']) ? $_GET['page'] : '1';

        // Проверка на валидность номера страницы
        if (!ctype_digit($page) || intval($page) < 1) {
            // Если параметр не является положительным целым числом, вернуть ошибку
            http_response_code(400);
            echo json_encode(["message" => "Invalid page number"]);
            return;
        }

        $page = intval($page); // Преобразование в целое число после проверки

        // Рассчитать смещение для выборки
        $offset = ($page - 1) * $perPage;

        // Базовый запрос для выборки новостей
        $query = 'WHERE status = 1 ORDER BY created_at DESC LIMIT ? OFFSET ?';
        $params = [$perPage, $offset];

        // Выбрать новость с пагинацией
        $news = R::findAll('news', $query, $params);

        // Путь к шаблонному изображению
        $defaultPhotoUrl = $this->getPhotoUrl('templates/grey');

        // Подготовить результаты
        $result = [];
        foreach ($news as $new) {
            $newsData = R::exportAll([$new])[0];
            // Если нет обложки, использовать шаблонное изображение
            $newsData['cover_photo'] = $newsData['cover_photo'] ? $this->getPhotoUrl($newsData['cover_photo']) : $defaultPhotoUrl;
            $result[] = $newsData;
        }

        // Отправить результат
        header('Content-Type: application/json');
        echo json_encode($result);
    }

    // Получить полный путь к фото, если оно существует
    private function getPhotoUrl($photoName) {
        $baseUrl = 'http://localhost/limonade/'; // Базовый URL вашего проекта
        $filePath = 'backend/uploads/images/';
        return $photoName ? $baseUrl . $filePath . $photoName . '.jpg' : null;
    }

    // Получить одну новость по ID
    public function getNews($id) {
        // Загружаем новость по ID
        $news = R::load('news', $id);
        
        // Проверить, существует ли новость и ее статус равен 1 (активная новость)
        if (!$news->id || $news->status != 1) {
            // Если новость не найдена или статус не равен 1, вернуть ошибку 404
            http_response_code(404);
            echo json_encode(["message" => "Новость не найдена"]);
        }

        // Подготовить массив для данных новости
        $newsData = R::exportAll([$news])[0];

        // Обновить пути к фотографиям прямо в соответствующих полях
        if ($newsData['photo_1']) {
            $newsData['photo_1'] = $this->getPhotoUrl($newsData['photo_1']);
            
            // Если есть фото_2, обновить путь
            if ($newsData['photo_2']) {
                $newsData['photo_2'] = $this->getPhotoUrl($newsData['photo_2']);
            }

            // Если есть фото_3, обновить путь
            if ($newsData['photo_3']) {
                $newsData['photo_3'] = $this->getPhotoUrl($newsData['photo_3']);
            }
        }

        // Увеличить значение просмотров на 1
        $news->viewed += 1;
        R::store($news); // Сохранить изменения в базе данных

        // Отправить результат
        header('Content-Type: application/json');
        echo json_encode($newsData);
        
    }

    // Создать новое объявление
    public function createNews() {
        // Присвоение данных
        $hash_num = $_POST['password'] ?? null;
        $title_ru = $_POST['title_ru'] ?? null;
        $description_ru = $_POST['description_ru'] ?? null;
        $author_ru = $_POST['author_ru'] ?? null;
        $title_en = $_POST['title_en'] ?? null;
        $description_en = $_POST['description_en'] ?? null;
        $author_en = $_POST['author_en'] ?? null;

        // Проверка наличия всех необходимых полей
        if (
            !isset($hash_num) ||
            !isset($title_ru) ||
            !isset($description_ru) ||
            !isset($author_ru) ||
            !isset($title_en) ||
            !isset($description_en) ||
            !isset($author_en)
        ) {
            http_response_code(400);
            echo json_encode(["message" => "Недостаточно данных"]);
            return;
        }

        if ($hash_num != $this->adm_pass) {
            http_response_code(400);
            echo json_encode(["message" => "Ошибка доступа"]);
        }

        // Проверка полей на пустоту
        if (empty(trim($title_ru))
            || empty(trim($description_ru))
            || empty(trim($author_ru))
            || empty(trim($title_en))
            || empty(trim($description_en))
            || empty(trim($author_en))
            ) {
            http_response_code(400);
            echo json_encode(["message" => "Одно из обязательных полей пустое"]);
            return;
        }
        
        // Проверка длины полей
        if (strlen($title_ru) > 100
            || strlen($description_ru) > 2000
            || strlen($author_ru) > 200
            || strlen($title_en) > 100
            || strlen($description_en) > 2000
            || strlen($author_en) > 200
            
            ) {
            http_response_code(400);
            echo json_encode(["message" => "Длина данных превышает допустимые пределы"]);
            echo json_encode([
                "title_ru" => strlen($title_ru),
                "description_ru" => strlen($description_ru),
                "author_ru" => strlen($author_ru),
                "title_en" => strlen($title_en),
                "description_en" => strlen($description_en),
                "author_en" => strlen($author_en)
            ]);
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
            $newsPhotos = [];
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
                        $newsPhotos[0] = $coverPhotoName;
                        $savedPhotos[] = $filePath . $coverPhotoName . '.jpg';
                        // Обрабатываем photo_1
                        $this->processPhoto($imageEditor, $filePath, $newsPhotos, $savedPhotos, 1);
                        break;

                    case 1:
                        // Обрабатываем photo_2
                        $this->processPhoto($imageEditor, $filePath, $newsPhotos, $savedPhotos, 2);
                        break;

                    case 2:
                        // Обрабатываем photo_3
                        $this->processPhoto($imageEditor, $filePath, $newsPhotos, $savedPhotos, 3);
                        break;
                }
            }
        }

        // Создаем новую новость
        $news = R::dispense('news'); // Используем RedBeanPHP для создания записи в таблице "news"
        $news->title_ru = $title_ru; // Заголовок новости
        $news->title_en = $title_en; // Заголовок новости
        $news->description_ru = $description_ru; // Описание новости
        $news->description_en = $description_en; // Описание новости

        // Сохраняем фотографии в базе данных
        $news->cover_photo = isset($newsPhotos[0]) ? (string) $newsPhotos[0] : null;
        $news->photo_1 = isset($newsPhotos[1]) ? (string) $newsPhotos[1] : null;
        $news->photo_2 = isset($newsPhotos[2]) ? (string) $newsPhotos[2] : null;
        $news->photo_3 = isset($newsPhotos[3]) ? (string) $newsPhotos[3] : null;

        $news->author_ru = $author_ru; // Автор новости
        $news->author_en = $author_en; // Автор новости
        $news->status = 1; // Статус новости
        $news->created_at = date('Y-m-d H:i:s'); // Дата создания
        $news->viewed = 0; // Счетчик просмотров (изначально 0)
        $news->closed_at = null;

        // error_log('массив фото: ' . json_encode($newsPhotos));
        R::store($news); // Сохраняем новость в базе данных

        // Возвращаем ответ
        header('Content-Type: application/json');
        echo json_encode(["message" => "Новость создана"]);
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
    private function processPhoto($imageEditor, $filePath, &$newsPhotos, &$savedPhotos, $key) {
        $photoName = $imageEditor->generateUniqueName(); // Генерация уникального имени файла
        $imageEditor->createImage();                    // Создание изображения
        $imageEditor->resizeToFit();                    // Изменение размера изображения

        if ($imageEditor->orientation == 'h') {
            $imageEditor->saveOriginal($filePath . $photoName . '.jpg'); // Сохраняем оригинал горизонтального изображения
        } else {
            $imageEditor->createPaddedImage();                           // Создаем изображение с добавлением полей
            $imageEditor->savePadded($filePath . $photoName . '.jpg');   // Сохраняем изображение с полями
        }
        $newsPhotos[$key] = $photoName; // Добавляем имя файла в массив
        $savedPhotos[] = $filePath . $photoName . '.jpg'; // Сохраняем путь к файлу
    }

    // Заблокировать новость по ID и удалить связанные фотографии
    public function deleteNews($id, $password) {
        // Проверка на наличие пароля и ID
        if (empty($password) || empty($id) || strlen($password) > 20) {
            // Выводим сообщение на экран
            http_response_code(400);
            echo json_encode(["message" => "Не достаточно данных"]);
            return;
        }

        // Проверить введенный пароль
        if ($password != $this->adm_pass) {
            // Выводим сообщение на экран
            http_response_code(400);
            echo json_encode(["message" => "Неверный пароль"]);
            return;
        }

        // Загрузить новость по ID
        $news = R::load('news', $id);

        // Проверка, существует ли новость и ее статус
        if (!$news->id || ($news->status != 1)) {
            //
            http_response_code(404);
            echo json_encode(["message" => "Новость не найдена или уже была заблокирована ранее"]);
            return;
        }

        // Удалить связанные фотографии, если они существуют
        $this->deletePhotoIfExists($news->cover_photo);
        $this->deletePhotoIfExists($news->photo_1);
        $this->deletePhotoIfExists($news->photo_2);
        $this->deletePhotoIfExists($news->photo_3);

        // Обновить статус новости и дату
        $news->status = 0; // Статус новости
        $news->closed_at = date('Y-m-d H:i:s'); // Дата удаления
        R::store($news);

        // Сообщение об успехе
        header('Content-Type: application/json');
        echo json_encode(["message" => "Новость была успешно заблокирована"]);
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