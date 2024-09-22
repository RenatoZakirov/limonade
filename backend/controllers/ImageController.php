<?php

// Использование класса
$image = new ImageProcessor();
// Загрузка изображения из форматов jpeg, png, gif
$image->load(__DIR__ . '/../uploads/images/test.jpg');

if ($image->isValidSize()) {
    // Изменение размера
    $image->resizeToFit();
    // Сохранение как new_image_name.jpg
    if ($image->save(__DIR__ . '/../uploads/images/test_1.jpg')) {
        echo 'Изображение успешно сохранено<br>';

        // Создание изображения с белыми полями
        $image->createPaddedImage();

        // Сохранение изображения с белыми полями
        if ($image->savePadded(__DIR__ . '/../uploads/images/test_padded_1.jpg')) {
            echo 'Изображение с белыми полями успешно сохранено';
        } else {
            echo 'Ошибка сохранения изображения с белыми полями';
        }

    } else {
        echo 'Ошибка сохранения изображения';
    }
} else {
    echo 'Размеры изображения слишком маленькие';
}

