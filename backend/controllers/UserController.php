<?php

class UserController {
    private $adm_pass;

    public function __construct($adm_pass) {
        $this->adm_pass = $adm_pass;
    }    

    // Заблокировать пользователя по ID
    public function blockUser($id, $password) {
        // Проверка на наличие пароля и ID
        if (empty($password) || empty($id) || mb_strlen($password, 'UTF-8') > 17) {
            // Выводим сообщение на экран
            http_response_code(400);
            echo json_encode(['code' => 820]);
            return;
        }

        // Проверить введенный пароль
        if ($password != $this->adm_pass) {
            // Выводим сообщение на экран
            http_response_code(400);
            echo json_encode(['code' => 821]);
            return;
        }

        // Загрузить пользователя по ID
        $user = R::load('users', $id);

        // Проверка пользователя
        if (!$user->id) {
            // Выводим сообщение на экран
            http_response_code(400);
            echo json_encode(['code' => 822]);
            return;
        }

        // Проверка статуса пользователя
        if ($user->status != 1) {
            // Выводим сообщение на экран
            http_response_code(400);
            echo json_encode(['code' => 823]);
            return;
        }

        // Обновить статус пользователя и дату
        $user->status = 0; // Статус пользователя
        $user->closed_at = date('Y-m-d H:i:s'); // Дата удаления
        R::store($user);

        // Сообщение об успехе
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    }

}
