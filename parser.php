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
    /* здесь внутри будет <p>, <h2>, а дальше в <ul> нам нужны будут <li>,
     причем просто все ul - это перечень совместимости
    */


}

function extract_name($html) {
    $name = '';
    
    $pattern_name_container = '@<div\s+class="prodContent prodDescription"[^>]*>(.*?)</div>@imsu';
    if (!preg_match($pattern_name_container, $html, $match)) {
        return [];
    }

    $html_name = $match[1];

    $pattern_name = '@<p\s+class="h3"[^>]*>(.*?)</p>@imsu';
    if (preg_match($pattern_name, $html_name, $matches)) {
        $name = $matches[1]; 
    }

    return $name;

}

// === МАИН ===

// ячайки положения
$cell_links = 'A';
$cell_image = 'B';
$cell_compatibility = 'C';
$cell_name = 'D';


// файлы
$filename = 'test.xlsx'; // файл записи 
$inputFile = 'input.xlsx'; // файл чтения

$reader = IOFactory::createReader('Xlsx');
$spreadsheet = $reader->load($inputFile);
$worksheet = $spreadsheet->getSheetByName('НОВЫЕ!'); // страница экселя

if (!$worksheet) {
    echo "Страница не найдена";
}

// храним пути на страницы из эксель 
$id_urls = [];

// считывание с экселя
for ($row=2; $row < 6; $row++) {
    $cellValue = $worksheet->getCell("B$row")->getValue();
    if (!empty($cellValue)) {
        $id_urls[] = trim($cellValue);
    }
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
    
    // название и описание
    $name = extract_name($html);
    $sheet->setCellValue($cell_name . $nextRow, $name);
    
    // переход к следующией строке
    $nextRow++;

    sleep(1);
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
echo "Файл сохранен " . realpath($filename);
// exit;
?>