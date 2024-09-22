<?php

class TgController {
    // Объявление константы для токена
    private const TELEGRAM_BOT_TOKEN = '12jlkj32lk3lk3lkjl23kj2l';

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
                $this->sendMessage($chatId, "Бот не понимает ваш запрос и не может его обработать.");
            }
        } elseif (isset($update['callback_query'])) {
            // Заглушка для обработки callback запросов
            $this->handleCallbackQuery($update['callback_query']);
        } else {
            // http_response_code(400); // Некорректный запрос
            // Ответ на некорректные запросы
            $this->sendMessage($chatId, "Бот не понимает ваш запрос и не может его обработать.");
        }
    }

    // Обработка команды /start
    private function handleStartCommand($chatId) {
        // Поиск пользователя по chat_id в базе данных
        $user = R::findOne('users', 'chat_id = ?', [$chatId]);

        if ($user) {
            // Пользователь найден, возвращаем его hash_num
            $hashNum = $user->hash_num;
            $this->sendMessage($chatId, "Это ваш секретный код: $hashNum. Используйте его для работы со своими объявлениями в сервисе \"limonade.pro\" и никому не показывайте.");
        } else {
            // Пользователя нет, создаем новую запись
            $hashNum = $this->generateUniqueHash();

            // Создаем нового пользователя
            $user = R::dispense('users');
            $user->chat_id = $chatId;
            $user->hash_num = $hashNum;
            $user->status = 1; // Статус активен
            $user->created_at = date('d.m.Y H:i:s');
            $user->last_visit = date('d.m.Y H:i:s');
            $user->closed_at = null;

            // Сохраняем нового пользователя в базу данных
            R::store($user);

            // Возвращаем пользователю новый hash_num
            $this->sendMessage($chatId, "Это ваш секретный код: $hashNum. Используйте его для работы со своими объявлениями в сервисе \"limonade.pro\" и никому не показывайте.");
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
        $this->sendMessage($chatId, "Обработка callback запроса временно не поддерживается.");
    }

    // Отправка сообщения пользователю через Telegram API
    private function sendMessage($chatId, $text) {
        $url = "https://api.telegram.org/bot" . self::TELEGRAM_BOT_TOKEN . "/sendMessage";
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
