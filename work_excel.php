<?php
require_once './PhpSpreadsheet-1.12.0/vendor/autoload.php';
use \PhpOffice\PhpSpreadsheet\Spreadsheet;
use \PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ControllerExtensionReportSaleTogether extends Controller {

	private $objPHPExcel;
	
	public function index() {
		$this->objPHPExcel= new Spreadsheet();
	
		/*Инициализация*/
        $this->objPHPExcel->setActiveSheetIndex(0);
        $sheet = $this->objPHPExcel->getActiveSheet();
        $sheet->setTitle('Название книги');
        $sheet->getColumnDimension('A')->setWidth(5);
        $sheet->getColumnDimension('B')->setWidth(40);
        $sheet->getColumnDimension('C')->setWidth(40);
        $sheet->getColumnDimension('D')->setWidth(40);
        $sheet->getColumnDimension('E')->setWidth(40);
        $sheet->getColumnDimension('F')->setWidth(40);

		$sheet->setCellValue('A1', 'Название столбца1');
		$sheet->setCellValue('B1', 'Название столбца2');
		$sheet->setCellValue('C1', 'Название столбца3');
		$sheet->setCellValue('D1', 'Название столбца4');
		$sheet->setCellValue('E1', 'Название столбца5');
		$sheet->setCellValue('F1', 'Название столбца6');
		$sheet->setCellValue('G1', 'Название столбца7');
			
		$row = 1;
		$num = 0;
		$mergeStart = 2;
		// $results - массив с данными из БД
		foreach ($results as $result) {
			$row++;
			$sheet->setCellValue('A'.$row, 'fefef');
			if (true) {	// если нужно объединить ячейки	
				$sheet->mergeCells('A' . $mergeStart .':A' . ($row - 1));	
				$mergeStart = $row;			
			}	
			
			$sheet->setCellValue('B'.$row, 'fefe');
			$sheet->setCellValue('C'.$row, 'fefe');
			$sheet->setCellValue('D'.$row, 'fefe');
			
			$Text = new \PhpOffice\PhpSpreadsheet\RichText\RichText();
			$Text->createTextRun("какой-то\nмногострочный\nтекст");
			$Text->getFont()->setSize(10); 
			$Text->getFont()->setItalic(true);
									
			$sheet->setCellValue('E'.$row, $Text);
			$sheet->setCellValue('F'.$row, 'grgrg');
			$sheet->setCellValue('G'.$row, 'fewfefe');
			
		}
		
		//форматирование ячеек
		$sheet->getStyle('A1:G1')->getFont()->setBold(true);
		$alignArray = array(
		             'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
		             'vertical'   => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
		             'rotation'   => 0,
		         );
		$sheet->getStyle('A1:G1')->getAlignment()->applyFromArray($alignArray);
		         
		// перенос слов
		$alignArray['wrapText'] = true;
		$sheet->getStyle('A2:G' . $row)->getAlignment()->applyFromArray($alignArray);
		  
		$sheet->getStyle('A1:G' . $row)->applyFromArray(array(
			'borders'=>array(
				'allBorders' => array(
					'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
					'color' => array('rgb' => '000000')
				)
			)
		));

		$objWriter = new Xlsx($this->objPHPExcel);
		
        // файл не пишем на диск, а в оперативную память сохраняем
        ob_start();
        $objWriter->save('php://output');
        $xlsData = ob_get_contents();
        ob_end_clean();        
        $xlsData = "data:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet;base64," . base64_encode($xlsData);
       	
       	// или сохранить в файл
		//$objWriter->save('./filename.xlsx');
	}
}