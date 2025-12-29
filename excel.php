<?php
// подключение библиотеки
require_once 'PhpSpreadsheet-1.12.0/vendor/autoload.php';

// создание таблицы
$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// заголовки
$sheet->setCellValue('A1', 'Товар');
$sheet->setCellValue('B1', 'Цена');

// данные
$sheet->setCellValue('A2', 'Яблоки');
$sheet->setCellValue('B2', '100 руб');

// подготовка к скачиванию
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="report.xlsx"');

// скачивем файл 
$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
$writer->save('php://output');