<?php

class UserController {
    private $adm_pass;

    public function __construct($adm_pass) {
        $this->adm_pass = $adm_pass;
    }    

    // Заблокировать пользователя по ID
    public function blockUser($id, $password) {
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

        // Загрузить пользователя по ID
        $user = R::load('users', $id);

        // Проверка, существует ли пользователь и его статус
        if (!$user->id || $user->status != 1) {
            // Выводим сообщение на экран
            http_response_code(404);
            echo json_encode(["message" => "Пользователь не найден или уже был заблокирован ранее"]);
            return;
        }

        // Обновить статус пользователя и дату
        $user->status = 0; // Статус пользователя
        $user->closed_at = date('Y-m-d H:i:s'); // Дата удаления
        R::store($user);

        // Сообщение об успехе
        header('Content-Type: application/json');
        echo json_encode(["message" => "Пользователь был успешно заблокирован"]);
    }

}