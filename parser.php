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
// use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use \PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory; // обработка i-o без скачивания

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

// извлечение артикуля товара
function extract_article($html) {
    $article = '';
    
    // === Вариант 1: Извлечение прямо из HTML (надёжнее) ===
    // [^<]+ — захватывает всё, кроме <, т.е. до закрывающего тега
    if (preg_match('@Artikel-Nummer:\s*([^<]+)@i', $html, $m)) {
        $article = trim($m[1]);
    }
    
    // === Вариант 2: Если нужен strip_tags ===
    // if (empty($article)) {
    //     $text = strip_tags($html);
    //     // [\w\-\./]+ — только допустимые символы артикула
    //     if (preg_match('@Artikel-Nummer:\s*([\w\-\./]+)@i', $text, $m)) {
    //         $article = trim($m[1]);
    //     }
    // }
    
    return $article;
}

// извлечение цены в евро
function extract_price($html) {
    $price = '';

    $pattern_price_eur_container = '@<div\s+id="cuPrice"[^>]*>(.*?)</div>@imsu';
    if(!preg_match($pattern_price_eur_container, $html, $match)) {
        return '';
    }

    $price = str_replace(' EUR', '', $match[1]);
    
    return $price;

}

// извлечение категории
function extract_category($html) {

    $category = [];

    $pattern = '/<span class="icon--before[^"]* navtrail">\s*<a href="([^"]+)"[^>]*>\s*<span>([^<]+)<\/span>\s*<\/a>/u';
    preg_match_all($pattern, $html, $matches, PREG_SET_ORDER);

    foreach ($matches as $match) {
        $title = trim($match[2]);
        $category[] = $title;
    }

    return $category;
}

// извлечение производителя товара
function extract_manufacture($html) {
    $manufacture = '';

    $pattern = '/<br>\s*Hersteller:\s*(.*?)\s*<br>/imsu';
    if(!preg_match($pattern, $html, $match)) {
        return '';
    }

    $manufacture = trim($match[1]);
    // print_r($match);

    return $manufacture;
}

// извлечения ссылок на картинки
function extract_image_links($html) {
    $links = [];

    $pattern_img_container = '@<div\s+class="mehrBilder"[^>]*>(.*?)</div>@imsu';
    if (preg_match($pattern_img_container, $html, $match)) {
        
        $html_img_container = $match[1];
        $pattern_img = '@<a\s+data-options="lazyZoom:\s*true"[^>]*href="([^"]+)"@imsu';
        
        if (preg_match_all($pattern_img, $html_img_container, $matches)) {
            $links = $matches[1];
        }
        $links = implode('|', $links); // соединение в массиве с разделителем "|"
    }

    
    if (empty($links)) {
        if (preg_match('@<a[^>]*\s+id=["\']?Zoomer["\']?[^>]*\s+href=["\']([^"\']+)@imsu', $html, $zoomMatch)) {
            $links = trim($zoomMatch[1]);
            // print_r($links);
        }
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

/*
* === МАИН ===
*/

// инициализация таймера
$start = microtime(true);

// ячейки положения
$cell_links = 'B';
$cell_article = 'D';
$cell_price = 'E';
$cell_category = 'H';
$cell_name = 'I';
$cell_manufacture = 'J';
$cell_image = 'N';
$cell_compatibility = 'P';
$cell_desc = 'K';


// файлы
$filename = 'output_test.xlsx'; // файл записи
$inputFile = 'input_test.xlsx'; // файл чтения
$fileProgress = __DIR__ . "/last_row.txt"; // файл прогресса строки

// кол-во проходка за раз
$batchSize = 100;

// читание ссылко из ексель
$reader = IOFactory::createReader('Xlsx');
$spreadsheet = $reader->load($inputFile);
$worksheet = $spreadsheet->getSheetByName('List1'); // страница экселя

if (!$worksheet) {
    echo "Страница не найдена";
}

// стартует со строки эксель
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
for ($row = $startRow; $row <= 86 && $processedRow < $batchSize; $row++) {
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
    $sheet->setCellValue($cell_article . '1', 'артикул');
    $sheet->setCellValue($cell_price . '1', 'цена евро');
    $sheet->setCellValue($cell_category . '1', 'категория источник');
    $sheet->setCellValue($cell_name . '1', 'название');
    $sheet->setCellValue($cell_manufacture . '1', 'производитель');
    $sheet->setCellValue($cell_image . '1', 'картинки');
    $sheet->setCellValue($cell_compatibility . '1', 'совместимость');
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

    // артикул
    $article = extract_article($html);
    $sheet->setCellValue($cell_article . $nextRow, $article);

    // цена в евро
    $price_eur = extract_price($html);
    $sheet->setCellValue($cell_price . $nextRow, $price_eur);

    // категория источник (хлебные крошки)
    $category = extract_category($html);
    $category = implode(' > ', $category);
    // echo "$category</br>";
    $sheet->setCellValue($cell_category . $nextRow, $category);

    // название 
    $name = extract_name($html);
    $sheet->setCellValue($cell_name . $nextRow, $name);

    // производитель
    $manufacture = extract_manufacture($html);
    $sheet->setCellValue($cell_manufacture . $nextRow, $manufacture);

    // ссылки на картинки
    $image_links = extract_image_links($html); 
    $sheet->setCellValue($cell_image . $nextRow, $image_links); // запись ниже заполненных данных

    // совместимость
    $compatibility = extract_compatibility($html);
    $compatibility_string = implode(", ", $compatibility);
    $sheet->setCellValue($cell_compatibility . $nextRow, $compatibility_string);
    
    // описание
    $desc = extract_desc($html);
    // echo "$desc";
    $sheet->setCellValue($cell_desc . $nextRow, $desc);
    
    // переход к следующией строке
    file_put_contents($fileProgress, $startRow + $batchSize);
    $nextRow++;

    // sleep(1);

}


// сохранение файла на сервере 0_о
$writer = IOFactory::createWriter($outspreadsheet, 'Xlsx');
$writer->save($filename);
echo "<h1>Файл сохранен " . realpath($filename) . '</h1></br>';
echo '<h2>Время выполнения скрипта: ' . round(microtime(true)-$start, 4) . 'секунд</h2>';


?>