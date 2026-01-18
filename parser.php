<?php
// подключение библы для excel
require_once "./PhpSpreadsheet-1.12.0/vendor/autoload.php";
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use \PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory; // обработка i-o 

// кодировка
header('Content-Type: text/html; charset=utf-8');

// получения html
function fetch_html($url) {
    $ch = curl_init(); // инициализация запроса
    curl_setopt($ch, CURLOPT_URL, trim($url)); // url для работы
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // верни как строку
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // редиректы
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // ждем 
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); // подключаемся 
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // скипаем ssl 
    curl_setopt($ch, CURLOPT_ENCODING, ''); // gzip БЕЗ ЭТОГО НЕ РАБОТАЕТ !!!

    // маскировка под браузер
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36');
        
    $html = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if($error) {die("Ошибка curl:  $error"); }
    return $html;
}

// извлечения
function extract_image_links($html) {
    $links = [];

    $pattern_img_container = '@<div\s+class="mehrBilder"[^>]*>(.*?)</div>@imsu';
    if (!preg_match($pattern_img_container, $html, $match)) {
        return [];
    }

    $html_img_container = $match[1];

    $pattern_img = '@<a\s+data-options="lazyZoom:\s*true"[^>]*href="([^"]+)"@imsu';
    if (preg_match_all($pattern_img, $html_img_container, $matches)) {
        $links = $matches[1];
    }
    return $links;    
}

// ===МАИН===
// откуда 
$url = 'https://www.juraprofi.de/DeLonghi-Ersatzteile/Wassertank/Abdeckung-Wassertank-silber-fuer-DeLonghi-Perfecta-Evo::17096.html'; 

// echo "<h2>Получение html</h2>";

// получение html 
$html = fetch_html($url);

// echo "<h2>Извлеение ссылок</h2>";
$image_links = extract_image_links($html);

// if (empty($image_links)) {
//     echo "Не получилось собрать данные";
// } else {
//     echo "<p>Найдено картинок: " . count($image_links) . "</p>";
//     echo "<pre>";
//     print_r($image_links); // массив
//     echo "</pre>";
// }

// соединение в массиве "|"
$links_string = implode('|', $image_links);

// файл
$filename = 'test.xlsx';

// файл сущ.
if(file_exists($filename)) {
    $spreadsheet = IOFactory::load($filename); // открытие файла
    $sheet = $spreadsheet->getActiveSheet(); // к активной странице
    $nextRow = $sheet->getHighestRow() + 1; // сдвигаемся к после заполненой
    $sheet->setCellValue('A' . $nextRow, $links_string); // запись ниже заполненных данных НАДО ДОБАВИТЬ ЛОГИКУ !!!
    echo "$links_string";
} else {
    $spreadsheet = new Spreadsheet(); // новая табла
    $sheet = $spreadsheet->getActiveSheet();// к активной странице таблы
    // заполнение таблицы
    $sheet->setCellValue('A1', 'Картинки');
    $sheet->setCellValue('A2', $links_string);
    echo "Создан файл";
}


// // подготовка к скачиванию
// header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
// header('Content-Disposition: attachment; filename="' . $filename . '"');

// // скачивем файл 
// $writer = new Xlsx($spreadsheet);
// $writer->save('php://output');

// сохранение файла на сервере 0_о
$writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
$writer->save($filename);
echo "Файл сохранен " . realpath($filename);
// exit;
?>