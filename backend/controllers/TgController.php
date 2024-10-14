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

}
