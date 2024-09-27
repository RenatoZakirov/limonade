<?php

class UserController {
    // Заблокировать пользователя по ID
    public function blockUser($password, $id) {
        // Проверка на наличие пароля и ID
        if (empty($password) || empty($id) || strlen($password) > 20) {
            http_response_code(400);
            // Выводим сообщение на экран
            echo '<p>Не достаточно данных</p>';
            return;
        }

        // Загрузка пользователя с ID = 1
        $user = R::load('users', 1);

        // Проверить введенный пароль
        if ($user->hash_num != $password) {
            // Пароль не правильный
            http_response_code(400);
            // Выводим сообщение на экран
            echo '<p>Неверный пароль</p>';
            return;
        }

        // Обновление даты последнего визита
        $user->last_visit = date('d.m.Y H:i:s');
        R::store($user);

        // Загрузить плохого пользователя по ID
        $badUser = R::load('users', $id);

        // Проверка, существует ли пользователь и его статус
        if ($badUser->id && $badUser->status == 1) {
            // Обновить статус пользователя и дату
            $badUser->status = 0; // Статус пользователя
            $badUser->closed_at = date('d.m.Y H:i:s'); // Дата удаления
            R::store($badUser);

            // Сообщение об успехе
            echo '<p>Пользователь был успешно заблокирован</p>';
        } else {
            // Пользователь не найден или уже не активен
            http_response_code(404);
            // Выводим сообщение на экран
            echo '<p>Пользователь не найден или уже был заблокирован</p>';
        }
    }

}