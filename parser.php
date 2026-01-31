<?php
/**
 * @author KolesnikBg
 * Парсинг с сайта https://www.juraprofi.de/ в эксель такие характеристики товара, как:
 * 1. Название - наименование товара (string)
 * 2. Ссылка на товар (string)
 * 3. Ссылки на картинки товара (string с разделителем '|')
 * 4. Совместимость - с какими произволителями/моделями совместим товар (string с разделителем ', ')
 * 5. Описание товара - "Данная запчасть подходит для ремионта кофемашины: <ul>$перечень_совместимостей</ul>" (string html)
 */

// подключение библиотек
require_once "./PhpSpreadsheet-1.12.0/vendor/autoload.php";
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use \PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory; // обработка i-o без скачивания

// файл прогресса 
$fileProgress = __DIR__ . "/last_row.txt";

// кол-во проходка за раз
$batchSize = 2;

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
    
    // html страницы
    $html = curl_exec($ch);
   
    // curl_close($ch);

    // обработка ошибок
     $error = curl_error($ch);
    if($error) {return false; }

    return $html;
}

// извлечения ссылок на картинки
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

// извлечение совместимостей
function extract_compatibility($html) {
    $comp_s = [];

    $pattern_comp_container = '@<div\s+class="ContentSmall prod_info_suche_merkmale"[^>]*>(.*?)</div>@imsu';
    if(!preg_match($pattern_comp_container, $html, $match)) {
        return [];
    }

    $html_comp_s = $match[1];

    $pattern_li = '@<li[^>]*>(.*?)</li>@imsu';

    if (preg_match_all($pattern_li, $html_comp_s, $matches)) {
        foreach ($matches[1] as $item) {
            // минус html (на всякий)
            $text = strip_tags($item);
            // минус пробелы и переносы 
            $text = trim(preg_replace('/\s+/', ' ', $text));
            if ($text !== '') {
                $comp_s[] = $text;
            }
        }
    }
    return $comp_s;
}


// извлечение название карточки
function extract_name($html) {
    $name = '';
    
    $pattern_name_container = '@<div\s+class="prodContent prodDescription"[^>]*>(.*?)</div>@imsu';
    if (!preg_match($pattern_name_container, $html, $match)) {
        return '';
    }

    $html_name = $match[1];

    $pattern_name = '@<p\s+class="h3"[^>]*>(.*?)</p>@imsu';
    if (preg_match($pattern_name, $html_name, $matches)) {
        $name = $matches[1]; 
    }

    return $name;

}

// извлечение и сборка описания
function extract_desc($html) {
    $desc = '';
    $pattern_comp_desc = '@<div\s+class="ContentSmall prod_info_suche_merkmale"[^>]*>(.*?)</div>@imsu';
    if(!preg_match($pattern_comp_desc, $html, $match)) {
        return '';
    }
    
    // захватываем с html
    $desc = $match[1];

    // === ЗАМЕНА html (стили) ===
    // замена заголовка описания
    $start =  '<div>&nbsp;</div><div>Данная запчасть подходит для ремонта кофемашины:</div>';

    // минус немецен шпрехен
    $desc = str_replace('<p class="h2">Das Ersatzteil ist passend für:</p>', '<div>&nbsp;</div>', $desc);

    // h2 заголовки - название брендов
    $desc = str_replace('<h2>', '<h2 style="margin: 0px 0px 15px; padding: 0px; font-size: 18px; color: rgb(0, 76, 179); font-weight: 400; line-height: 24px;"><strong>', $desc);
    $desc = str_replace('</h2>', '</strong></h2>', $desc);

    // ul
    $desc = str_replace('<ul', '<ul style="margin: 0px 0px 13px 15px; padding-right: 0px; padding-left: 0px; color: rgb(88, 88, 90);  line-height: 18px;">', $desc);

    // li
    $desc = str_replace('<li>', '<li><span style="color: rgb(105, 105, 105);">', $desc);
    $desc = str_replace('</li>', '</span></li>', $desc);

    $desc = $start . $desc;

    return $desc;
}

// === МАИН ===

// инициализация таймера
$start = microtime(true);

// ячейки положения
$cell_links = 'B';
$cell_image = 'N';
$cell_compatibility = 'P';
$cell_name = 'I';
$cell_desc = 'K';


// файлы
$filename = 'output.xlsx'; // файл записи
$inputFile = 'input.xlsx'; // файл чтения

$reader = IOFactory::createReader('Xlsx');
$spreadsheet = $reader->load($inputFile);
$worksheet = $spreadsheet->getSheetByName('НОВЫЕ!'); // страница экселя

if (!$worksheet) {
    echo "Страница не найдена";
}

//
$startRow = 2;
if (file_exists($fileProgress)) {
    $startRow = (int)file_get_contents($fileProgress);
}
echo "Начинаем с: $startRow <br>";
flush();

// храним пути на страницы из эксель 
$id_urls = [];
$processedRow = 0; 

// считывание с экселя
for ($row = $startRow; $row <= 3556 && $processedRow < $batchSize; $row++) {
    $cellValue = $worksheet->getCell("B$row")->getValue();
    $id_urls[] = trim($cellValue);
    $processedRow++;
}

// файл сущ.
if(file_exists($filename)) {
    $outspreadsheet = IOFactory::load($filename); // открытие файла
    $sheet = $outspreadsheet->getActiveSheet(); // к активной странице
    $nextRow = $sheet->getHighestRow() + 1; // сдвигаемся к после заполненой
} else {
    $outspreadsheet = new Spreadsheet(); // новая табла
    $sheet = $outspreadsheet->getActiveSheet();// к активной странице таблы
    
    // заполнение таблицы
    $sheet->setCellValue($cell_links . '1', 'ссылка');
    $sheet->setCellValue($cell_image . '1', 'картинки');
    $sheet->setCellValue($cell_compatibility . '1', 'совместимость');
    $sheet->setCellValue($cell_name . '1', 'название');
    $sheet->setCellValue($cell_desc . '1', 'описание');

    $nextRow = 2;
}

// проход по ссылкам из входного excel 
foreach ($id_urls as $url) {

    // html страницы 
    $html = fetch_html($url);

    // === ВЫЧЛИНЕНИЕ ИНФОРМАЦИИ ===
    // ссылка на карточку
    $sheet->setCellValue($cell_links . $nextRow, $url);

    // ссылки на картинки
    $image_links = extract_image_links($html); 
    $links_string = implode('|', $image_links); // соединение в массиве с разделителем "|"
    $sheet->setCellValue($cell_image . $nextRow, $links_string); // запись ниже заполненных данных

    // совместимость
    $compatibility = extract_compatibility($html);
    $compatibility_string = implode(", ", $compatibility);
    $sheet->setCellValue($cell_compatibility . $nextRow, $compatibility_string);
    
    // название 
    $name = extract_name($html);
    $sheet->setCellValue($cell_name . $nextRow, $name);

    // описание
    $desc = extract_desc($html);
    // echo "$desc";
    $sheet->setCellValue($cell_desc . $nextRow, $desc);


    
    // переход к следующией строке
    file_put_contents($fileProgress, $startRow + $batchSize);
    $nextRow++;

    // if ($nextRow % 10 == 0) {
    //     sleep(random_int(2, 5));
    // } else {
    sleep(1);
    // }
}

// // подготовка к скачиванию
// header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
// header('Content-Disposition: attachment; filename="' . $filename . '"');

// // скачивем файл 
// $writer = new Xlsx($spreadsheet);
// $writer->save('php://output');

// сохранение файла на сервере 0_о
$writer = IOFactory::createWriter($outspreadsheet, 'Xlsx');
$writer->save($filename);
echo "<h1>Файл сохранен " . realpath($filename) . '</h1></br>';
echo '<h2>Время выполнения скрипта: ' . round(microtime(true)-$start, 4) . 'секунд</h2>';
// exit;
?>