<?php

class ImageEditor
{
    private $fileTmpName;
    private $image;
    private $paddedImage;
    private $width;
    private $height;
    private $fileType;
    private $fileSize;
    public $orientation;
    public $watermarkAdded = false;

    // Метод установки базовых параметров
    public function __construct($file) {
        $this->fileTmpName = $file['tmp_name'];
        $this->width = $file['width'];     // Ширина передана в массиве $file
        $this->height = $file['height'];   // Высота передана в массиве $file
        $this->fileType = $file['type'];   // MIME-тип передан в массиве $file
        $this->fileSize = $file['size'];   // Размер передан в массиве $file

        // Определение ориентации
        if ($this->width > $this->height) {
            $this->orientation = 'h';
        } elseif ($this->width < $this->height) {
            $this->orientation = 'v';
        } else {
            $this->orientation = 'h';
        }
    }

    // Метод для проверки типа файла
    public function validateType($allowedTypes) {
        return in_array($this->fileType, $allowedTypes);
    }

    // Метод для проверки размера файла
    public function validateSize($maxSize) {
        return $this->fileSize <= $maxSize;
    }

    // Метод проверки минимального размера изображения
    public function validateResolution($minResolution) {
        // Проверка минимального размера для горизонтального изображения
        if ($this->orientation === 'h' && $this->width >= $minResolution[0] && $this->height >= $minResolution[1]) {
            return true;
        }

        // Проверка минимального размера для вертикального изображения
        if ($this->orientation == 'v' && $this->width >= $minResolution[1] && $this->height >= $minResolution[0]) {
            return true;
        }

        // Если размеры не соответствуют ни одному из условий
        return false;
    }

    // Метод создания образа изображения для работы с ним в RAM
    public function createImage() {
        // Определение формата изображения на основе MIME-типа
        switch ($this->fileType) {
            case 'image/jpeg':
                $this->fileType = 'jpeg';
                $this->image = imagecreatefromjpeg($this->fileTmpName);
                break;
            case 'image/png':
                $this->fileType = 'png';
                $this->image = imagecreatefrompng($this->fileTmpName);
                break;
            case 'image/gif':
                $this->fileType = 'gif';
                $this->image = imagecreatefromgif($this->fileTmpName);
                break;
            default:
                //
                return false;
        }

        //
        return true;
    }

    // Изменение размера изображения до стандарта и вставка watermark по центру
    public function resizeToFit($text = 'Limonade') {
        // Устанавливаем целевые размеры в зависимости от ориентации
        $targetWidth = $this->orientation === 'h' ? 600 : 450;
        $targetHeight = $this->orientation === 'h' ? 450 : 600;
    
        // Вычисляем коэффициенты масштабирования по каждой стороне
        $scaleWidth = $targetWidth / $this->width;
        $scaleHeight = $targetHeight / $this->height;
    
        // Выбираем максимальный коэффициент для масштабирования, чтобы изображение полностью заполнило целевой размер
        $scale = max($scaleWidth, $scaleHeight);
    
        // Вычисляем новые размеры для масштабирования
        $newWidth = round($this->width * $scale);
        $newHeight = round($this->height * $scale);
    
        // Создаем пустое изображение с целевыми размерами
        $newImage = imagecreatetruecolor($targetWidth, $targetHeight);
    
        // Заливаем фон белым цветом (или можно использовать другой цвет/ прозрачность)
        $backgroundColor = imagecolorallocate($newImage, 255, 255, 255);
        imagefill($newImage, 0, 0, $backgroundColor);
    
        // Вычисляем координаты для центрирования изображения
        $xOffset = ($newWidth - $targetWidth) / 2;
        $yOffset = ($newHeight - $targetHeight) / 2;
    
        // Создаем промежуточное изображение с новым масштабом
        $scaledImage = imagecreatetruecolor($newWidth, $newHeight);
        imagefill($scaledImage, 0, 0, $backgroundColor);
        imagecopyresampled(
            $scaledImage,
            $this->image,
            0, 0, 0, 0,
            $newWidth, $newHeight,
            $this->width, $this->height
        );
    
        // Копируем и изменяем размер промежуточного изображения в новое, центрируя его
        imagecopy(
            $newImage,
            $scaledImage,
            0, 0, $xOffset, $yOffset,
            $targetWidth, $targetHeight
        );

        // Если текст еще не был добавлен, добавляем его
        if (!$this->watermarkAdded) {
            // Путь к файлу шрифта
            $fontFile = 'libraries/fonts/NerkoRegular.ttf'; // Укажите путь к вашему файлу шрифта TTF
            $fontSize = 50; // Размер шрифта

            // Устанавливаем параметры для текста
            $textColor = imagecolorallocatealpha($newImage, 255, 255, 255, 85); // Белый цвет с прозрачностью

            // Определяем размеры текста
            $textBox = imagettfbbox($fontSize, 0, $fontFile, $text);
            $textWidth = $textBox[2] - $textBox[0];
            $textHeight = $textBox[7] - $textBox[1];

            // Вычисляем координаты для центрирования текста
            $textX = ($targetWidth - $textWidth) / 2;
            $textY = ($targetHeight - $textHeight) / 2 + $textHeight;

            // Добавляем текст на изображение
            imagettftext($newImage, $fontSize, 0, $textX, $textY, $textColor, $fontFile, $text);

            // Устанавливаем флаг
            $this->watermarkAdded = true;
        }
  
        // Заменяем старое изображение новым
        $this->image = $newImage;
        $this->width = $targetWidth;
        $this->height = $targetHeight;
    
        // Освобождаем ресурсы промежуточного изображения
        imagedestroy($scaledImage);

        return true;
    }

    // Создание изображения с белыми полями и центрированным исходным изображением
    public function createPaddedImage() {
        // Устанавливаем целевые размеры шаблона в зависимости от ориентации исходного изображения
        $targetWidth = $this->orientation === 'h' ? 450 : 600;
        $targetHeight = $this->orientation === 'h' ? 600 : 450;

        // Создаем новое изображение (шаблон) с белым фоном
        $this->paddedImage = imagecreatetruecolor($targetWidth, $targetHeight);
        // $backgroundColor = imagecolorallocate($this->paddedImage, 255, 255, 255); // 108, 117, 125
        $backgroundColor = imagecolorallocate($this->paddedImage, 108, 117, 125);
        imagefill($this->paddedImage, 0, 0, $backgroundColor);

        // Вычисляем коэффициент масштабирования, чтобы вписать изображение в шаблон
        $scaleWidth = $targetWidth / $this->width;
        $scaleHeight = $targetHeight / $this->height;
        $scale = min($scaleWidth, $scaleHeight);

        // Новые размеры изображения после масштабирования
        $newWidth = round($this->width * $scale);
        $newHeight = round($this->height * $scale);

        // Вычисляем координаты для центрирования изображения в шаблоне
        $xOffset = ($targetWidth - $newWidth) / 2;
        $yOffset = ($targetHeight - $newHeight) / 2;

        // Вставляем масштабированное изображение в центр шаблона
        imagecopyresampled(
            $this->paddedImage,
            $this->image,
            $xOffset, $yOffset, 0, 0,
            $newWidth, $newHeight,
            $this->width, $this->height
        );

        //
        return true;
    }
    
    // Сохранение изображения
    public function saveOriginal($filePath, $quality = 90) {
        // Убедимся, что новое имя файла заканчивается на .jpg
        $filePath = pathinfo($filePath, PATHINFO_EXTENSION) === 'jpg' ? $filePath : $filePath . '.jpg';

        // Сохраняем изображение в формате JPEG по указанному пути
        if (!imagejpeg($this->image, $filePath, $quality)) {
            return false;
        }

        //
        return true;
    }

    // Сохранение изображения с белыми полями
    public function savePadded($filePath, $quality = 90) {
        // Убедимся, что новое имя файла заканчивается на .jpg
        $filePath = pathinfo($filePath, PATHINFO_EXTENSION) === 'jpg' ? $filePath : $filePath . '.jpg';

        // Сохраняем изображение в формате JPEG по указанному пути
        if (!imagejpeg($this->paddedImage, $filePath, $quality)) {
            return false;
        }

        //
        return true;
    }

    // Генерация уникального имени файла
    public function generateUniqueName($length = 5) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    // Высвобождение ресурсов
    public function __destruct() {
        if ($this->image) {
            imagedestroy($this->image);
        }
        if ($this->paddedImage) {
            imagedestroy($this->paddedImage);
        }
    }
    
}
