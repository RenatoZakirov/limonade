<?php

class TgController {
    private $tg_key;

    // Храним уже обработанные media_group_id
    private static $processedMediaGroups = []; // Сделаем массив статическим

    public function __construct($tg_key) {
        $this->tg_key = $tg_key;
    }

    // Обработка вебхука
    public function handleWebhook() {
        // Получение данных из вебхука
        $update = json_decode(file_get_contents("php://input"), TRUE);

        // Проверка на наличие сообщения
        if (isset($update['message'])) {
            $message = $update['message'];
            $chatId = $message['chat']['id']; // Получаем chat_id пользователя
            $text = isset($message['text']) ? trim($message['text']) : ''; // Текст сообщения

            // Обработка команды /start
            if ($text === '/start') {
                $this->handleStartCommand($chatId);
            } else {
                // Ответ на некорректные запросы
                $this->sendMessage($chatId, 'Я не понимаю ваш запрос и не могу его обработать');
            }
        } elseif (isset($update['callback_query'])) {
            // Заглушка для обработки callback запросов
            $this->handleCallbackQuery($update['callback_query']);
        } else {
            http_response_code(400);
            // Ответ на некорректные запросы
            // $this->sendMessage($chatId, 'Я не понимаю ваш запрос и не могу его обработать');
        }
    }

    // Обработка вебхука
    // public function handleWebhook() {
    //     // Получение данных из вебхука
    //     $update = json_decode(file_get_contents("php://input"), TRUE);

    //     // Логирование всего полученного объекта для проверки
    //     // error_log("Incoming update: " . print_r($update, true));

    //     // Проверка на наличие сообщения
    //     if (isset($update['message'])) {
    //         $message = $update['message'];
    //         $chatId = $message['chat']['id']; // Получаем chat_id пользователя
    //         $text = isset($message['text']) ? trim($message['text']) : ''; // Текст сообщения
    
    //         // Обработка команды /start
    //         if ($text === '/start') {
    //             $this->handleStartCommand($chatId);
    //             return; // Завершаем выполнение, если это команда /start
    //         }
    
    //         // Логика для обработки пересланного сообщения с объявлением
    //         if (isset($message['forward_from'])) {
    //             // Ваш chat_id
    //             // $myChatId = '7969651882'; // Замените на ваш реальный chat_id
    //             // Приводим оба значения к строковому типу перед сравнением
    //             if ((string)$chatId !== '7969651882') {
    //                 // Если сообщение пришло не от вас, отправляем ошибку
    //                 $this->sendMessage($chatId, 'Я не понимаю ваш запрос и не могу его обработать');
    //                 return; // Завершаем выполнение
    //             }
    //             // Проверяем, пришло ли сообщение от вас
    //             $forwardFrom = $message['forward_from'];
    //             $userId = $forwardFrom['id']; // ID отправителя пересланного сообщения
    //             $userName = isset($forwardFrom['username']) ? $forwardFrom['username'] : 'Без имени'; // Username пересланного сообщения
    //             $firstName = isset($forwardFrom['first_name']) ? $forwardFrom['first_name'] : 'Без имени'; // Имя пересланного сообщения
    //             $text = isset($message['caption']) ? trim($message['caption']) : ''; // Текст пересланного сообщения (если есть)

    //             $filePath = __DIR__ . "/$userId.txt";
    
    //             // Проверка на наличие media_group_id (если это группа медиа)
    //             if (isset($message['media_group_id'])) {
    //                 $mediaGroupId = $message['media_group_id'];

    //                 // Путь к файлу для хранения информации о media_group_id
    //                 $filePath = __DIR__ . "/$mediaGroupId.txt";

    //                 // Проверка, существует ли файл
    //                 if (!file_exists($filePath)) {

    //                     // Записываем в файл информацию о пересланном сообщении
    //                     file_put_contents($filePath, "Forwarded message info: User ID = $userId, Username = $userName, Name = $firstName, Text = $text\n", FILE_APPEND);
    //                 }
    
    //             } else {
    //                 // Записываем в файл информацию о пересланном сообщении
    //                 file_put_contents($filePath, "Forwarded message info: User ID = $userId, Username = $userName, Name = $firstName, Text = $text\n", FILE_APPEND);
    //             }
    
    //             // Проверка на наличие фото
    //             if (isset($message['photo'])) {
    //                 $photos = $message['photo'];
    
    //                 // Самое большое фото — последний элемент в массиве
    //                 $largestPhoto = end($photos);
    
    //                 if ($largestPhoto) {
    //                     $fileId = $largestPhoto['file_id'];
    //                     $fileSize = isset($largestPhoto['file_size']) ? $largestPhoto['file_size'] : 'Unknown';
    //                     $width = isset($largestPhoto['width']) ? $largestPhoto['width'] : 'Unknown';
    //                     $height = isset($largestPhoto['height']) ? $largestPhoto['height'] : 'Unknown';
    
    //                     // Записываем информацию о самом большом фото в файл
    //                     file_put_contents($filePath, "Largest photo info: file_id = $fileId, file_size = $fileSize, width = $width, height = $height\n", FILE_APPEND);
    //                 }
    //             }
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
