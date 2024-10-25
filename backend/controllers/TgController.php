<?php

class TgController {
    private $tg_key;

    public function __construct($tg_key) {
        $this->tg_key = $tg_key;
    }

    // // Обработка вебхука
    // public function handleWebhook() {
    //     // Получение данных из вебхука
    //     $update = json_decode(file_get_contents("php://input"), TRUE);

    //     // Проверка на наличие сообщения
    //     if (isset($update['message'])) {
    //         $message = $update['message'];
    //         $chatId = $message['chat']['id']; // Получаем chat_id пользователя
    //         $text = isset($message['text']) ? trim($message['text']) : ''; // Текст сообщения

    //         // Обработка команды /start
    //         if ($text === '/start') {
    //             $this->handleStartCommand($chatId);
    //         } else {
    //             // Ответ на некорректные запросы
    //             $this->sendMessage($chatId, 'Я не понимаю ваш запрос и не могу его обработать');
    //         }
    //     } elseif (isset($update['callback_query'])) {
    //         // Заглушка для обработки callback запросов
    //         $this->handleCallbackQuery($update['callback_query']);
    //     } else {
    //         http_response_code(400);
    //         // Ответ на некорректные запросы
    //         // $this->sendMessage($chatId, 'Я не понимаю ваш запрос и не могу его обработать');
    //     }
    // }

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
    
            // Username пересланного сообщения
            $userContact = isset($message['forward_origin']['username']) ? $message['forward_origin']['username'] : '';
            // Имя пересланного сообщения
            $userName = isset($message['forward_origin']['sender_user_name']) ? $message['forward_origin']['sender_user_name'] : '';
            // Текст пересланного сообщения (если есть)
            $description = isset($message['caption']) ? trim($message['caption']) : '';
            // media_group_id (если это группа медиа)
            $mediaGroupId = isset($message['media_group_id']) ? $message['media_group_id'] : null;
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
        $contact_limit = 51;
        $description_limit = 1001;
        
        // Подготавливаем значение user_contact из $userName и $userContact
        $separator = ", телеграм ";
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
        }
    
        // Определяем, сколько фото уже сохранено для этой записи
        $existingPhotos = 0;
        //
        for ($i = 1; $i <= 3; $i++) {
            if (!empty($message->{'photo_' . $i})) {
                $existingPhotos++;
            }
        }
    
        // Если уже сохранено 3 фото, игнорируем обработку
        if ($existingPhotos >= 3) {
            return;
        }
    
        // Обрабатываем самое большое фото, если фото присутствуют
        if (!empty($photos)) {
            // Выбираем самое большое фото — последний элемент в массиве
            $largestPhoto = end($photos);
            // Получаем путь к фото на сервере
            $telegramFilePath = $this->downloadFromTelegram($largestPhoto['file_id']);

            // Приведение фото к унифицированному виду для удобства обработки
            $photo = $this->checkPhoto($chatId, $telegramFilePath, $existingPhotos);
            // error_log("Incoming update: " . print_r($photo, true));
            // Если была ошибка, ответ уже отправлен в методе checkPhoto
            if (!$photo) {
                return;
            }

            // Разрешенные типы изображений
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            // Максимальный размер файла (5 MB)
            $maxSize = 5 * 1024 * 1024;
            // Минимальные допустимые размеры изображения
            $minResolution = [600, 450];

            // Путь к папке для сохранения изображений
            $filePath = 'uploads/images/tmp/';
 
            // Создаем объект для редактирования изображений
            $imageEditor = new ImageEditor($photo);

            // Проверяем тип файла
            if (!$imageEditor->validateType($allowedTypes)) {
                // Удаляем временный файл
                unlink($telegramFilePath);
                $this->sendMessage($chatId, 'Недопустимый тип фото, порядковый номер фото: ' . ($existingPhotos + 1));
                return;
            }

            // Проверяем размер файла
            if (!$imageEditor->validateSize($maxSize)) {
                // Удаляем временный файл
                unlink($telegramFilePath);
                $this->sendMessage($chatId, 'Недопустимо большой размер фото, порядковый номер фото: ' . ($existingPhotos + 1));
                return;
            }

            // Проверяем разрешение изображения
            if (!$imageEditor->validateResolution($minResolution)) {
                // Удаляем временный файл
                unlink($telegramFilePath);
                $this->sendMessage($chatId, 'Недопустимо маленькое разрешение фото, порядковый номер фото: ' . ($existingPhotos + 1));
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
                        $imageEditor->savePadded($filePath . $coverPhotoName . '.jpg'); // Сохраняем с полями
                    } else {
                        // Для вертикальных изображений сохраняем оригинал
                        $imageEditor->createImage();
                        $imageEditor->resizeToFit();
                        $imageEditor->saveOriginal($filePath . $coverPhotoName . '.jpg');
                    }
                    $message->cover_photo = $coverPhotoName;
                    // Обрабатываем photo_1
                    $message->photo_1 = $this->processPhoto($imageEditor, $filePath);
                    break;

                case 1:
                    // Обрабатываем photo_2
                    $message->photo_2 = $this->processPhoto($imageEditor, $filePath);
                    break;

                case 2:
                    // Обрабатываем photo_3
                    $message->photo_3 = $this->processPhoto($imageEditor, $filePath);
                    break;
            }
        }

        // Сохраняем запись в базе данных (независимо от того, есть ли фото)
        R::store($message);
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
            $this->sendMessage($chatId, 'Ошибка: файл не найден на сервере, порядковый номер файла: ' . ($index + 1));
            return null;
        }
    
        // Проверяем, является ли файл изображением
        $imageInfo = getimagesize($filePath);
        //
        if ($imageInfo === false) {
            // Удаляем временный файл
            unlink($filePath);
            $this->sendMessage($chatId, 'Файл не является изображением, порядковый номер файла: ' . ($index + 1));
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
