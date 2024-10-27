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
            echo json_encode(['code' => 300]);
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
        $defaultPhotoUrl = $this->getPhotoUrl('templates/no_image');

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
        $baseUrl = 'https://www.limonade.pro/'; // Базовый URL вашего проекта
        $filePath = 'backend/uploads/images/news/';
        return $photoName ? $baseUrl . $filePath . $photoName . '.jpg' : null;
    }

    // Получить одну новость по ID
    public function getNews($id) {
        // Загружаем новость по ID
        $news = R::load('news', $id);
        
        // Проверить, существует ли новость и ее статус равен 1 (активная новость)
        if (!$news->id || $news->status != 1) {
            // Если новость не найдена или статус не равен 1, вернуть ошибку 404
            http_response_code(400);
            echo json_encode(['code' => 500]);
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

            // Если есть фото_4, обновить путь
            if ($newsData['photo_4']) {
                $newsData['photo_4'] = $this->getPhotoUrl($newsData['photo_4']);
            }

            // Если есть фото_5, обновить путь
            if ($newsData['photo_5']) {
                $newsData['photo_5'] = $this->getPhotoUrl($newsData['photo_5']);
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
        // Поля и их значения по умолчанию
        $fields = [
            'hash_num' => isset($_POST['password']) ? trim($_POST['password']) : null,
            'title_ru' => isset($_POST['title_ru']) ? trim($_POST['title_ru']) : null,
            'description_ru' => isset($_POST['description_ru']) ? trim($_POST['description_ru']) : null,
            'author_ru' => isset($_POST['author_ru']) ? trim($_POST['author_ru']) : null,
            'title_en' => isset($_POST['title_en']) ? trim($_POST['title_en']) : null,
            'description_en' => isset($_POST['description_en']) ? trim($_POST['description_en']) : null,
            'author_en' => isset($_POST['author_en']) ? trim($_POST['author_en']) : null,
        ];

        // Проверка обязательных полей на наличие и пустоту
        foreach ($fields as $key => $value) {
            if (empty($value)) {
                http_response_code(400);
                echo json_encode(['code' => 700]);
                return;
            }
        }

        // Ограничения по длине полей
        $fieldLimits = [
            'hash_num' => 17,
            'title_ru' => 51,
            'description_ru' => 2001,
            'author_ru' => 51,
            'title_en' => 51,
            'description_en' => 2001,
            'author_en' => 51
        ];

        // Проверка длины полей
        foreach ($fieldLimits as $field => $limit) {
            if (mb_strlen($fields[$field], 'UTF-8') > $limit) {
                http_response_code(400);
                echo json_encode(['code' => 701]);
                return;
            }
        }

        // Присвоение переменных для дальнейшего использования
        $hash_num = $fields['hash_num'];
        $title_ru = $fields['title_ru'];
        $description_ru = $fields['description_ru'];
        $author_ru = $fields['author_ru'];
        $title_en = $fields['title_en'];
        $description_en = $fields['description_en'];
        $author_en = $fields['author_en'];

        if ($hash_num != $this->adm_pass) {
            http_response_code(400);
            echo json_encode(['code' => 702]);
            return;
        }

        // Проверяем, есть ли загруженные фотографии в $_FILES
        if (isset($_FILES['photos'])) {
            $photos = [];
            
            // Проверяем, является ли $_FILES['photos'] массивом или одиночным файлом
            $isMultiple = isset($_FILES['photos']['name']) && is_array($_FILES['photos']['name']);
            
            // Определяем количество файлов для обработки (максимум 3)
            $fileCount = $isMultiple ? min(5, count($_FILES['photos']['name'])) : 1;

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
            $filePath = 'uploads/images/news/';

            // Массив для хранения имен фотографий объявления
            $newsPhotos = [];
            $savedPhotos = [];  // Массив для хранения путей сохраненных файлов

            // Цикл по всем загруженным фотографиям
            foreach ($photos as $key => $photoFile) {
                $imageEditor = new ImageEditor($photoFile); // Создаем объект для редактирования изображений

                // Проверяем тип файла
                if (!$imageEditor->validateType($allowedTypes)) {
                    $this->deleteSavedPhotos($savedPhotos);
                    http_response_code(400);
                    echo json_encode(['code' => 722]);
                    return;
                }

                // Проверяем размер файла
                if (!$imageEditor->validateSize($maxSize)) {
                    $this->deleteSavedPhotos($savedPhotos);
                    http_response_code(400);
                    echo json_encode(['code' => 723]);
                    return;
                }

                // Проверяем разрешение изображения
                if (!$imageEditor->validateResolution($minResolution)) {
                    $this->deleteSavedPhotos($savedPhotos);
                    http_response_code(400);
                    echo json_encode(['code' => 724]);
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

                    case 3:
                        // Обрабатываем photo_4
                        $this->processPhoto($imageEditor, $filePath, $newsPhotos, $savedPhotos, 4);
                        break;

                    case 4:
                        // Обрабатываем photo_5
                        $this->processPhoto($imageEditor, $filePath, $newsPhotos, $savedPhotos, 5);
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
        $news->photo_4 = isset($newsPhotos[4]) ? (string) $newsPhotos[4] : null;
        $news->photo_5 = isset($newsPhotos[5]) ? (string) $newsPhotos[5] : null;

        $news->author_ru = $author_ru; // Автор новости
        $news->author_en = $author_en; // Автор новости
        $news->status = 1; // Статус новости
        $news->created_at = date('Y-m-d H:i:s'); // Дата создания
        $news->viewed = 0; // Счетчик просмотров (изначально 0)
        // $news->closed_at = null;
        R::store($news); // Сохраняем новость в базе данных

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
            echo json_encode(['code' => 720]);
            return null;
        }

        // Проверяем, является ли файл изображением
        $imageInfo = getimagesize($tmpName);
        if ($imageInfo === false) {
            http_response_code(400);
            echo json_encode(['code' => 721]);
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
        if (empty($password) || empty($id) || mb_strlen($password, 'UTF-8') > 17) {
            // Выводим сообщение на экран
            http_response_code(400);
            echo json_encode(['code' => 520]);
            return;
        }

        // Проверить введенный пароль
        if ($password != $this->adm_pass) {
            // Выводим сообщение на экран
            http_response_code(400);
            echo json_encode(['code' => 522]);
            return;
        }

        // Загрузить новость по ID
        $news = R::load('news', $id);

        // Проверка новости
        if (!$news->id) {
            //
            http_response_code(400);
            echo json_encode(['code' => 521]);
            return;
        }

        // // Проверить введенный пароль
        // if ($password != $this->adm_pass) {
        //     // Выводим сообщение на экран
        //     http_response_code(400);
        //     echo json_encode(['code' => 522]);
        //     return;
        // }

        // Проверка статуса новости
        if ($news->status != 1) {
            //
            http_response_code(400);
            echo json_encode(['code' => 523]);
            return;
        }

        // Удалить связанные фотографии, если они существуют
        $this->deletePhotoIfExists($news->cover_photo);
        $this->deletePhotoIfExists($news->photo_1);
        $this->deletePhotoIfExists($news->photo_2);
        $this->deletePhotoIfExists($news->photo_3);
        $this->deletePhotoIfExists($news->photo_4);
        $this->deletePhotoIfExists($news->photo_5);

        // Обновить статус новости и дату
        $news->status = 0; // Статус новости
        $news->closed_at = date('Y-m-d H:i:s'); // Дата удаления
        R::store($news);

        // Сообщение об успехе
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    }

    // Вспомогательный метод для удаления фото, если оно существует
    private function deletePhotoIfExists($photoName) {
        if ($photoName) {
            $imageFile = __DIR__ . '/../uploads/images/news/' . $photoName . '.jpg';
            if (file_exists($imageFile)) {
                unlink($imageFile);
            }
        }
    }

}
