<?php

class TgController {
    private $tg_key;

    public function __construct($tg_key) {
        $this->tg_key = $tg_key;
    }

    // Обработка вебхука
    public function handleWebhook() {
        // Получение данных из вебхука
        $update = json_decode(file_get_contents("php://input"), TRUE);

        // Логирование всего полученного объекта для проверки
        // error_log("Incoming update: " . print_r($update, true));

        // Проверка на наличие сообщения
        if (isset($update['message'])) {
            // Обработки текстовых сообщений
            $this->handleMessage($update['message']);

        } elseif (isset($update['callback_query'])) {
            // Заглушка для обработки callback запросов
            $this->handleCallbackQuery($update['callback_query']);

        } else {
            // Ответ на некорректные запросы
            http_response_code(400);
            $this->sendMessage($chatId, 'Я не понимаю ваш запрос и не могу его обработать');
        }
    }

    // Обработка входящего сообщения
    public function handleMessage($message) {
        //
        $chatId = (string)$message['chat']['id'];
        $text = isset($message['text']) ? trim($message['text']) : '';
    
        // Обработка команды /start
        if ($text === '/start') {
            //
            $this->handleStartCommand($chatId);
            return;
        }
    
        // Логика для пересланного сообщения с объявлением
        if (isset($message['forward_origin'])) {
            // Мой chat_id
            $myChatId = '7969651882';
            // Приводим оба значения к строковому типу перед сравнением
            if ($chatId !== (string)$myChatId) {
                // Если сообщение пришло не от меня, отправляем ошибку
                $this->sendMessage($chatId, 'Я не понимаю ваш запрос и не могу его обработать');
                return;
            }

            $structure = $this->getForwardedMessageInfo($message);

            $userContact = $structure['userContact'];

            $userName = $structure['userName'];

            $description = $structure['description'];

            
            // media_group_id (если это группа медиа)
            $mediaGroupId = isset($message['media_group_id']) ? strval($message['media_group_id']) : null;
            // Массив с фото
            $photos = $message['photo'] ?? [];

            // Сохранение информации в базу данных
            $this->saveMessage($chatId, $userContact, $userName, $description, $mediaGroupId, $photos);
        } else {
            // Если это не пересланное сообщение отправляем ошибку
            $this->sendMessage($chatId, 'Я не понимаю ваш запрос и не могу его обработать');
        }
    }

    // Сохранение данных из сообщения
    public function saveMessage($chatId, $userContact, $userName, $description, $mediaGroupId, $photos) {
        // Лимиты по длине полей
        $contact_limit = 50;
        $description_limit = 1000;
        
        // Подготавливаем значение user_contact из $userName и $userContact
        $separator = ', телеграм ';
        //
        $userName =  $userName ?? 'Noname';
        //
        $fullContact = $userName . $separator . $userContact;
        
        // Проверяем, не превышает ли длина полного контакта лимит
        if (mb_strlen($fullContact, 'UTF-8') > $contact_limit) {
            // Рассчитываем излишек символов
            $excessLength = mb_strlen($fullContact, 'UTF-8') - $contact_limit;
            
            // Укорачиваем $userName на избыточное количество символов
            $userName = mb_substr($userName, 0, mb_strlen($userName, 'UTF-8') - $excessLength, 'UTF-8');
            
            // Пересобираем $fullContact с укороченным $userName
            $fullContact = $userName . $separator . $userContact;
            
            // Если всё равно превышает лимит, отправляем ошибку в телеграм и выходим
            if (mb_strlen($fullContact, 'UTF-8') > $contact_limit) {
                //
                $this->sendMessage($chatId, 'Ошибка: контакт превышает лимит символов');
                return;
            }
        }

        // Проверка длины $description
        if (mb_strlen($description, 'UTF-8') > $description_limit) {
            //
            $this->sendMessage($chatId, 'Ошибка: текст сообщения превышает лимит символов');
            return;
        }

        // Проверяем наличие записи с таким media_group_id
        $message = $mediaGroupId ? R::findOne('messages', 'media_group_id = ?', [$mediaGroupId]) : null;

        // Если запись не найдена, создаем новую
        if (!$message) {
            $message = R::dispense('messages');
            // $message->user_id = $userId;
            $message->contact = $fullContact;
            $message->description = $description;
            $message->media_group_id = $mediaGroupId;
            $message->status = 1;
            $message->created_at = date('Y-m-d H:i:s');

        } else if ($message->status == 0) {
            return;
        }
    
        // Определяем, сколько фото уже сохранено для этой записи, включая cover_photo
        $existingPhotos = 0;
        $photoFields = ['cover_photo', 'photo_1', 'photo_2', 'photo_3'];

        foreach ($photoFields as $field) {
            if (!empty($message->{$field})) {
                $existingPhotos++;
            }
        }

        // Если уже сохранено 4 фото, игнорируем обработку
        if ($existingPhotos >= 4) {
            return;
        }
    
        // Обрабатываем самое большое фото, если фото присутствуют
        if (!empty($photos)) {
            // Путь к папке для сохранения изображений
            $tmpPath = 'uploads/images/tmp/';
            $tmpPath_2 = 'uploads/images/tmp_2/';
            // Выбираем самое большое фото — последний элемент в массиве
            $largestPhoto = end($photos);
            // Получаем путь к фото на сервере
            $telegramFilePath = $this->downloadFromTelegram($largestPhoto['file_id']);

            // Приведение фото к унифицированному виду для удобства обработки
            $photo = $this->checkPhoto($chatId, $telegramFilePath, $existingPhotos);
            // error_log("Incoming update: " . print_r($photo, true));
            // Если была ошибка, ответ уже отправлен в методе checkPhoto
            if (!$photo) {
                // Закрыть объявление
                $this->closeMessage($tmpPath, $message, $chatId);
                $this->deleteFilesInDirectory($tmpPath_2);
                return;
            }

            // Разрешенные типы изображений
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            // Максимальный размер файла (5 MB)
            $maxSize = 5 * 1024 * 1024;
            // Минимальные допустимые размеры изображения
            $minResolution = [600, 450];
 
            // Создаем объект для редактирования изображений
            $imageEditor = new ImageEditor($photo);

            // Проверяем тип файла
            if (!$imageEditor->validateType($allowedTypes)) {
                // Закрыть объявление
                $this->closeMessage($tmpPath, $message, $chatId);
                $this->deleteFilesInDirectory($tmpPath_2);
                $this->sendMessage($chatId, 'Ошибка: недопустимый тип фото, порядковый номер фото: ' . $existingPhotos++);
                return;
            }

            // Проверяем размер файла
            if (!$imageEditor->validateSize($maxSize)) {
                // Закрыть объявление
                $this->closeMessage($tmpPath, $message, $chatId);
                $this->deleteFilesInDirectory($tmpPath_2);
                $this->sendMessage($chatId, 'Ошибка: недопустимо большой размер фото, порядковый номер фото: ' . $existingPhotos++);
                return;
            }

            // Проверяем разрешение изображения
            if (!$imageEditor->validateResolution($minResolution)) {
                // Закрыть объявление
                $this->closeMessage($tmpPath, $message, $chatId);
                $this->deleteFilesInDirectory($tmpPath_2);
                $this->sendMessage($chatId, 'Ошибка: недопустимо маленькое разрешение фото, порядковый номер фото: ' . $existingPhotos++);
                return;
            }

            // Обработка фотографий в зависимости от их индекса
            switch ($existingPhotos) {
                case 0:
                    // Обрабатываем cover_photo как обложку
                    $coverPhotoName = $imageEditor->generateUniqueName(); // Генерируем уникальное имя для обложки
                    
                    // Если ориентация горизонтальная, создаем изображение с полями
                    if ($imageEditor->orientation == 'h') {
                        $imageEditor->createImage();          // Создаем изображение
                        $imageEditor->resizeToFit();          // Меняем размер изображения
                        $imageEditor->createPaddedImage();    // Создаем изображение с добавлением полей
                        $imageEditor->savePadded($tmpPath . $coverPhotoName . '.jpg'); // Сохраняем с полями
                    } else {
                        // Для вертикальных изображений сохраняем оригинал
                        $imageEditor->createImage();
                        $imageEditor->resizeToFit();
                        $imageEditor->saveOriginal($tmpPath . $coverPhotoName . '.jpg');
                    }
                    $message->cover_photo = $coverPhotoName;
                    // Обрабатываем photo_1
                    $message->photo_1 = $this->processPhoto($imageEditor, $tmpPath);
                    break;

                case 2:
                    // Обрабатываем photo_2
                    $message->photo_2 = $this->processPhoto($imageEditor, $tmpPath);
                    break;

                case 3:
                    // Обрабатываем photo_3
                    $message->photo_3 = $this->processPhoto($imageEditor, $tmpPath);
                    break;
            }
        }

        // Сохраняем запись в базе данных (независимо от того, есть ли фото)
        R::store($message);
    }

    private function getForwardedMessageInfo($message) {
        // Проверка на наличие разных полей, чтобы обработать оба варианта
        $userContact = '';
        $userName = '';
        $description = '';
    
        if (isset($message['forward_origin'])) {
            // Если сообщение переслано из канала
            if ($message['forward_origin']['type'] === 'channel') {
                $userContact = isset($message['forward_origin']['chat']['username'])
                    ? '@' . $message['forward_origin']['chat']['username']
                    : '';
                $userName = isset($message['forward_origin']['chat']['title'])
                    ? $message['forward_origin']['chat']['title']
                    : '';
            } else {
                // Обработка вариантов с sender_user или других форматов
                $userContact = isset($message['forward_origin']['sender_user']['username'])
                    ? '@' . $message['forward_origin']['sender_user']['username']
                    : (isset($message['forward_origin']['username'])
                        ? '@' . $message['forward_origin']['username']
                        : '');

                $userName = isset($message['forward_origin']['sender_user']['first_name'])
                    ? $message['forward_origin']['sender_user']['first_name']
                    : (isset($message['forward_origin']['sender_user_name'])
                        ? $message['forward_origin']['sender_user_name']
                        : '');
            }
        }
    
        // Получение текста или описания сообщения (caption или text)
        $description = isset($message['caption']) 
            ? trim($message['caption'])
            : (isset($message['text']) ? trim($message['text']) : '');
    
        return [
            'userContact' => $userContact,
            'userName' => $userName,
            'description' => $description,
        ];
    }
    
    private function closeMessage($tmpPath, $message, $chatId) {
        // Закрыть объявление
        $message->status = 0;
        $message->closed_at = date('Y-m-d H:i:s');
        R::store($message);
        // Собираем названия фотографий
        $photoFields = ['cover_photo', 'photo_1', 'photo_2', 'photo_3'];

        foreach ($photoFields as $field) {
            if (!empty($message->{$field})) {
                // Формируем полный путь к файлу с расширением
                $tmpFile = $tmpPath . $message->{$field} . '.jpg';
        
                // Удаляем файл, если он существует
                if (file_exists($tmpFile)) {
                    if (!unlink($tmpFile)) {
                        // Ошибка при удалении
                        $this->sendMessage($chatId, 'Ошибка: не получилось удалить фото');
                        return;
                    }
                }
            }
        }
    }

    private function deleteFilesInDirectory($directory) {
        // Получаем список всех файлов в директории
        $files = glob($directory . '*'); // добавляем * для выбора всех файлов
        
        // Проходим по каждому файлу и удаляем его
        foreach($files as $file) {
            if (is_file($file)) {
                // Удаляем файл
                unlink($file);
            }
        }
    }

    private function downloadFromTelegram($fileId) {
        $botToken = $this->tg_key;
        // Получаем путь к файлу на серверах Telegram
        $fileUrl = "https://api.telegram.org/bot{$botToken}/getFile?file_id={$fileId}";
        //
        $fileInfo = json_decode(file_get_contents($fileUrl), true);
    
        if (isset($fileInfo['result']['file_path'])) {
            //
            $telegramPath = $fileInfo['result']['file_path'];
            //
            $downloadUrl = "https://api.telegram.org/file/bot{$botToken}/{$telegramPath}";
    
            // Загружаем файл на сервер
            $fileContent = file_get_contents($downloadUrl);
            //
            if ($fileContent) {
                // Путь к временной папке
                $localFilePath = 'uploads/images/tmp_2/' . basename($telegramPath);
                // Сохраняем файл на сервер
                file_put_contents($localFilePath, $fileContent);
                // Возвращаем путь к локальному файлу
                return $localFilePath;
            }
        }
        return null;
    }

    private function checkPhoto($chatId, $filePath, $index) {
        // Проверяем, существует ли файл
        if (!file_exists($filePath)) {
            $this->sendMessage($chatId, 'Ошибка: файл не найден на сервере, порядковый номер файла: ' . $index++);
            return null;
        }
    
        // Проверяем, является ли файл изображением
        $imageInfo = getimagesize($filePath);
        //
        if ($imageInfo === false) {
            // Удаляем временный файл
            unlink($filePath);
            $this->sendMessage($chatId, 'Ошибка: файл не является изображением, порядковый номер файла: ' . $index++);
            return null;
        }
    
        // Получаем информацию о файле
        $size = filesize($filePath);          // Размер файла в байтах
        $name = basename($filePath);          // Имя файла без пути
        $mimeType = $imageInfo['mime'];       // MIME-тип файла
        $width = $imageInfo[0];               // Ширина изображения
        $height = $imageInfo[1];              // Высота изображения
    
        // Возвращаем массив с информацией о фото, если все успешно
        return [
            'tmp_name' => $filePath,   // Устанавливаем путь к локальному файлу, как эквивалент tmp_name
            'name' => $name,
            'size' => $size,
            'type' => $mimeType,
            'width' => $width,
            'height' => $height
        ];
    }

    // Функция для обработки фотографий
    private function processPhoto($imageEditor, $filePath) {
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
        // Возвращаем имя файла
        return (string)$photoName;
    }
    
    // Отправка сообщения пользователю через Telegram API
    private function sendMessage($chatId, $text) {
        $url = "https://api.telegram.org/bot" . $this->tg_key . "/sendMessage";
        $data = [
            'chat_id' => $chatId,
            'text' => $text,
        ];

        // Выполнение POST-запроса к Telegram API
        $options = [
            'http' => [
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($data),
            ],
        ];

        $context  = stream_context_create($options);
        file_get_contents($url, false, $context);
    }

    // Обработка команды /start
    private function handleStartCommand($chatId) {
        // Поиск пользователя по chat_id в базе данных
        $user = R::findOne('users', 'chat_id = ?', [$chatId]);

        if ($user) {
            // Пользователь найден, возвращаем его hash_num
            $hashNum = $user->hash_num;
            $this->sendMessage($chatId, $hashNum);
            $this->sendMessage($chatId, "Это ваш ID. Используйте его для работы с сервисом www.limonade.pro и никому не показывайте\n\n" . 
                "This is your ID. Use it to work with the www.limonade.pro service and do not show it to anyone", 'Markdown');

        } else {
            // Пользователя нет, создаем новую запись
            $hashNum = $this->generateUniqueHash();

            // Создаем нового пользователя
            $user = R::dispense('users');
            $user->chat_id = $chatId;
            $user->hash_num = $hashNum;
            $user->status = 1; // Статус активен
            $user->created_at = date('Y-m-d H:i:s'); // Дата создания
            $user->last_visit = date('Y-m-d H:i:s'); // Дата последнего посещения
            $user->closed_at = null;

            // Сохраняем нового пользователя в базу данных
            R::store($user);

            // Возвращаем пользователю новый hash_num
            $this->sendMessage($chatId, $hashNum);
            $this->sendMessage($chatId, "Это ваш ID. Используйте его для работы с сервисом www.limonade.pro и никому не показывайте\n\n" . 
                "This is your ID. Use it to work with the www.limonade.pro service and do not show it to anyone", 'Markdown');
        }
    }

    // Генерация уникального hash_num
    public function generateUniqueHash($length = 8) {
        $characters = '23456789CFGHJKMPQRVWX';
        $charactersLength = strlen($characters);
        $randomString = '';

        // Генерация случайной строки
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }

        return $randomString;
    }

    // Обработка callback-запросов (заглушка)
    private function handleCallbackQuery($callbackQuery) {
        $chatId = $callbackQuery['message']['chat']['id'];
        $this->sendMessage($chatId, 'Обработка callback запроса временно не поддерживается');
    }

}
