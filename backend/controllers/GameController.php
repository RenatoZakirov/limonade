<?php

class GameController {
    //
    private $telegramApiUrl = 'https://api.telegram.org/bot';
    //
    private $bot_token = '7697174154:AAE3wtUbYhbJWgsteWKnCAxIPfQ_0EFkaI0';

    // Основной метод обработки входящих сообщений
    public function handleWebhook() {
        // Получение данных из вебхука
        $update = json_decode(file_get_contents("php://input"), TRUE);
        //
        if (isset($update['message'])) {
            //
            $this->processMessage($update['message']);

        } elseif (isset($update['callback_query'])) {
            //
            $this->processCallback($update['callback_query']);
        } // else {
            // Ответ на некорректные запросы
            // http_response_code(400);
            // $this->sendTelegramMessage($chatId, 'Я не понимаю ваш запрос и не могу его обработать');
        // }
    }

    // Обработка текстовых сообщений
    private function processMessage($message) {
        //
        $chatId = $message['chat']['id'];

        if (isset($message['photo'])) {
            //
            $this->savePhotoId($chatId, $message['photo']);
            //
            return;
        }

        //
        $text = $message['text'] ?? '';
        //
        switch ($text) {
            case '/start':
                $this->sendWelcomeMessage($chatId);
                break;
            default:
                $this->processPlayerInput($chatId, $text);
                break;
        }
    }

    // Обработка нажатий на кнопки
    private function processCallback($callbackQuery) {
        //
        $callbackData = json_decode($callbackQuery['data'], true);
        //
        $chatId = $callbackQuery['message']['chat']['id'];
        //
        switch ($callbackData['action']) {
            //
            case 'rules':
                $this->sendTelegramMessage($chatId, 'Здесь будут описаны подробные правила игры...');
                break;
            //
            case 'play_game':
                $this->startNewGame($chatId);
                break;
            //
            case 'add_player':
                $this->requestPlayerName($chatId, $callbackData['game_id']);
                break;
            //
            case 'begin_game':
                $this->sendRandomQuestion($chatId, $callbackData['game_id']);
                break;
            //
            case 'roll_dice':
                $this->rollDiceAndSendResult($chatId, $callbackData['game_id']);
                break;
            //
            case 'choose_winner':
                $this->updateScores($chatId, $callbackData);
                break;
            //
            case 'continue_game':
                $this->sendRandomQuestion($chatId, $callbackData['game_id']);
                break;
            //
            case 'end_game':
                $this->endGame($chatId, $callbackData['game_id']);
                break;
        }
    }

    // Отправка приветственного сообщения
    private function sendWelcomeMessage($chatId) {
        //
        $photoId = 'AgACAgIAAxkBAAMHZ1AhDo5OFbdvMUf57pZT0ZWEV0MAAvjlMRtshYBKa79fgmWKmq0BAAMCAAN4AAM2BA';
        $text = "Приветствуем вас в игре 'Словоглот'";
        $keyboard = [
            [
                ['text' => 'Правила', 'callback_data' => json_encode(['action' => 'rules'])],
                ['text' => 'Играть', 'callback_data' => json_encode(['action' => 'play_game'])]
            ]
        ];

        $this->sendTelegramPhoto($chatId, $photoId, $text, $keyboard);
    }

    // Начало новой игры
    private function startNewGame($chatId) {
        // Обновляем статусы всех игр
        R::exec("UPDATE games SET status = 0");
        // Обновляем статусы всех вопросов
        R::exec("UPDATE questions SET status = 1");
        //
        $game = R::dispense('games');
        $game->player_1 = null;
        $game->score_1 = 0;
        $game->player_2 = null;
        $game->score_2 = 0;
        $game->player_3 = null;
        $game->score_3 = 0;
        $game->status = 1;
        $game->created_at = date('Y-m-d H:i:s');
        $gameId = R::store($game);

        $this->requestPlayerName($chatId, $gameId);
    }

    // Запрос имени игрока
    private function requestPlayerName($chatId, $gameId) {
        //
        $game = R::load('games', $gameId);

        if (!$game->player_1) {
            //
            $text = "Приветствуем вас в новой игре!\nНапишите имя первого игрока:";
        } elseif (!$game->player_2) {
            //
            $text = "Напишите имя второго игрока:";
        } elseif (!$game->player_3) {
            //
            $text = "Напишите имя третьего игрока:";
        } else {
            //
            $text = "Максимум игроков достигнут.";
        }

        $this->sendTelegramMessage($chatId, $text);
    }

    // Обработка имени игрока
    private function processPlayerInput($chatId, $text) {
        //
        $game = R::findOne('games', 'status = 1 ORDER BY id DESC');

        if ($game) {
            //
            if (!$game->player_1) {
                //
                $game->player_1 = $text;

            } elseif (!$game->player_2) {
                //
                $game->player_2 = $text;

            } elseif (!$game->player_3) {
                //
                $game->player_3 = $text;
            }
            //
            R::store($game);

            $text = "Отлично! Имя игрока '{$text}' добавлено.";
            $keyboard = [
                [['text' => 'Добавить ещё', 'callback_data' => json_encode(['action' => 'add_player', 'game_id' => $game->id])]],
                [['text' => 'Начать игру', 'callback_data' => json_encode(['action' => 'begin_game', 'game_id' => $game->id])]]
            ];

            $this->sendTelegramMessage($chatId, $text, $keyboard);
        }
    }

    // Отправка рандомного вопроса
    private function sendRandomQuestion($chatId, $gameId) {
        // Выбор случайного вопроса
        $question = R::findOne('questions', 'status = 1 ORDER BY RAND()');
        
        if (!$question) {
            $keyboard = [
                [
                    ['text' => 'Играть еще раз', 'callback_data' => json_encode(['action' => 'play_game'])]
                ]
            ];
            $this->sendTelegramMessage($chatId, 'Все вопросы уже использованы. Начинайте новый раунд!', $keyboard);
            return;
        }

        // Отправка фото с вопросом
        $photoId = $question->photo_id;
        $caption = "Прочитайте вопрос и бросьте кубик, чтобы определить количество букв в слове.";
        // Сообщение о начале следующего этапа
        $keyboard = [
            [['text' => 'Бросить кубик', 'callback_data' => json_encode(['action' => 'roll_dice', 'game_id' => $gameId])]]
        ];
        $this->sendTelegramPhoto($chatId, $photoId, $caption, $keyboard);

        // Обновляем статус вопроса, чтобы он больше не использовался
        $question->status = 0;
        R::store($question);
    }

    // Бросание кубика
    private function rollDiceAndSendResult($chatId, $gameId) {
        // Генерация случайного числа от 3 до 8
        $diceRand = random_int(3, 8);

       // Получение данных из таблицы dice
        $dice = R::findOne('dice', 'name = ?', [$diceRand]);
        $dicePhotoId = $dice->photo_id;

        // Получение активных игроков из таблицы games
        $game = R::findOne('games', 'id = ?', [$gameId]);
        if (!$game) {
            $this->sendTelegramMessage($chatId, "Ошибка: Игра с ID {$gameId} не найдена.");
            return;
        }

        // Извлечение имен игроков
        $players = [];
        for ($i = 1; $i <= 3; $i++) {
            $playerName = $game->{'player_' . $i};
            if (!is_null($playerName)) {
                $players[] = ['name' => $playerName, 'index' => $i];
            }
        }

        if (empty($players)) {
            $this->sendTelegramMessage($chatId, "В этой игре нет активных игроков.");
            return;
        }

        // Создание кнопок для активных игроков
        $playerButtons = [];
        foreach ($players as $player) {
            $playerButtons[] = [
                'text' => $player['name'],
                'callback_data' => json_encode([
                    'action' => 'choose_winner',
                    'player_index' => $player['index'],
                    'game_id' => $gameId
                ])
            ];
        }

        // Добавляем кнопку завершения игры
        $keyboard = [
            // Кнопки игроков (в одном ряду)
            $playerButtons,
            [['text' => 'Завершить игру', 'callback_data' => json_encode(['action' => 'end_game', 'game_id' => $gameId])]]
        ];

        // Текст сообщения
        $caption = "Кубик брошен! Вам нужно составить слово из {$diceRand} букв.\nПосле того как будет известен победитель в этом раунде, выберите его из списка.\nТакже вы можете завершить вашу игру на этом этапе.";
        
        // Отправляем фото с кнопками
        $this->sendTelegramPhoto($chatId, $dicePhotoId, $caption, $keyboard);
    }

    // Обновление очков
    private function updateScores($chatId, $callbackData) {
        $gameId = $callbackData['game_id'];
        $playerIndex = $callbackData['player_index'];
    
        // Загрузка игры из БД
        $game = R::load('games', $gameId);
    
        // Проверка корректности номера игрока
        $column = "score_{$playerIndex}";
        $playerColumn = "player_{$playerIndex}";


        // Проверяем, существует ли поле игрока и оно не равно NULL
        if (!isset($game->$playerColumn) || empty($game->$playerColumn)) {
            $this->sendTelegramMessage($chatId, "Ошибка: Игрок с номером {$playerIndex} не найден.");
            return;
        }
    
        // Увеличение очков игрока
        $game->$column += 1;
        R::store($game);
    
        // Сбор текущих результатов
        $results = "Очки обновлены! Текущие результаты:\n\n";
        for ($i = 1; $i <= 3; $i++) {
            $playerName = $game->{"player_{$i}"};
            $playerScore = $game->{"score_{$i}"};
            if (!is_null($playerName)) {
                $results .= "{$i}. {$playerName}: {$playerScore}\n";
            }
        }
    
        // Кнопки для продолжения игры или завершения
        $keyboard = [
            [['text' => 'Продолжить', 'callback_data' => json_encode(['action' => 'continue_game', 'game_id' => $gameId])]],
            [['text' => 'Завершить игру', 'callback_data' => json_encode(['action' => 'end_game', 'game_id' => $gameId])]]
        ];
    
        // Отправка сообщения с результатами
        $this->sendTelegramMessage($chatId, $results, $keyboard);
    }
    
    // Окончание игры
    private function endGame($chatId, $gameId) {
        $game = R::load('games', $gameId);

        //
        $photoId = 'AgACAgIAAxkBAAMLZ1AiZAyvRTG6t0tN56Kc3znXQIQAAgHmMRtshYBKKjYzdca0Wi4BAAMCAAN4AAM2BA';
    
        // Составляем сообщение о финальных результатах
        $text = "Игра завершена! Итоговые результаты:\n\n";
        
        // Проверяем, сколько игроков зарегистрировано и выводим их результаты
        if (!empty($game->player_1)) {
            $text .= "1. {$game->player_1}: {$game->score_1}\n";
        }
        if (!empty($game->player_2)) {
            $text .= "2. {$game->player_2}: {$game->score_2}\n";
        }
        if (!empty($game->player_3)) {
            $text .= "3. {$game->player_3}: {$game->score_3}\n";
        }
    
        $text .= "\nСпасибо за игру!";
    
        $keyboard = [
            [
                ['text' => 'Правила', 'callback_data' => json_encode(['action' => 'rules'])],
                ['text' => 'Играть еще раз', 'callback_data' => json_encode(['action' => 'play_game'])]
            ]
        ];

        $this->sendTelegramPhoto($chatId, $photoId, $text, $keyboard);
    
        // Завершаем игру в базе данных
        $game->status = 0;
        R::store($game);
    }

     // Отправить сообщение без фото
     private function sendTelegramMessage($chatId, $text, $keyboard = []) {
        //
        $data = [
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ];

        $this->sendTelegramRequest('/sendMessage', $data);
    }

    // Отправить сообщение с фото
    private function sendTelegramPhoto($chatId, $photoId, $caption, $keyboard = []) {
        //
        $data = [
            'chat_id' => $chatId,
            'photo' => $photoId,
            'caption' => $caption,
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ];

        $this->sendTelegramRequest('/sendPhoto', $data);
    }

    // Отправить сообщение
    private function sendTelegramRequest($method, $data) {
        //
        $url = $this->telegramApiUrl . $this->bot_token . $method;
        $options = [
            'http' => [
                'header' => "Content-Type:application/json\r\n",
                'method' => 'POST',
                'content' => json_encode($data),
            ]
        ];
        $context = stream_context_create($options);
        file_get_contents($url, false, $context);
    }

    // Метод для сохранения photo_id из сообщения
    private function savePhotoId($chatId, $photos) {
        // if (!DEVELOPMENT_MODE) {
        //     return;
        // }

        // Берём последнее фото в массиве (максимальное качество)
        $photo = end($photos);
        $photoId = $photo['file_id'];

        $this->sendTelegramMessage($chatId, "Сохранённый ID фото: $photoId");
    }
}
