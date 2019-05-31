<?php
namespace classes\lcl;

use config;
use PhpOffice;
use utils;

use PhpOffice\PhpPresentation\Settings;
use PhpOffice\PhpPresentation\IOFactory;
use PhpOffice\PhpPresentation\Slide;
use PhpOffice\PhpPresentation\PhpPresentation;
use PhpOffice\PhpPresentation\AbstractShape;
use PhpOffice\PhpPresentation\DocumentLayout;
use PhpOffice\PhpPresentation\Shape\Drawing;
use PhpOffice\PhpPresentation\Shape\MemoryDrawing;
use PhpOffice\PhpPresentation\Shape\RichText;
use PhpOffice\PhpPresentation\Shape\RichText\BreakElement;
use PhpOffice\PhpPresentation\Shape\RichText\TextElement;
use PhpOffice\PhpPresentation\Style\Alignment;
use PhpOffice\PhpPresentation\Style\Color;

use PhpOffice\PhpPresentation\Style\Border;
use PhpOffice\PhpPresentation\Style\Fill;
use PhpOffice\PhpPresentation\Style\Shadow;
use PhpOffice\PhpPresentation\Shape\Chart\Type\Bar3D;
use PhpOffice\PhpPresentation\Shape\Chart\Type\Line;
use PhpOffice\PhpPresentation\Shape\Chart\Type\Pie3D;
use PhpOffice\PhpPresentation\Shape\Chart\Type\Scatter;
use PhpOffice\PhpPresentation\Shape\Chart\Series;
use PhpOffice\PhpPresentation\Shape\Chart\Type\Bar;
use PhpOffice\PhpPresentation\Shape\Chart\Legend;
use PhpOffice\PhpPresentation\Shape\Media;

class CreatePptx {
    
    private $storeData;
    private $storeCatData;
    private $topFiveSku;
    private $bottomFiveSku;
    private $returnPath;
    private $filePath;
    private $data;
    
    private $pptObject;
    private $currentSlide;
            
    function __construct(array $data){
        $this->data = $data;
        $this->storeData = $data['storeData'];
        $this->storeCatData = $data['storeCatData'];
        $this->topFiveSku = $data['topFiveSku'];
        $this->bottomFiveSku = $data['bottomFiveSku'];
        $this->returnPath = isset($data['returnPath']) ? $data['returnPath'] : false;        
        
        // Making current slide object
        $this->pptObject = new PhpOffice\PhpPresentation\PhpPresentation();
        $this->setFileProperties();
        $this->currentSlide = $this->pptObject->getActiveSlide();
    }

    public function init(){
        $this->createHeaderTextSection();
        $this->createStorePerformanceSection();
        $this->createStoreCategorySection();
        $this->createYellowBoxSection();
        $this->createSkuPerHeaderSection();
        $this->createTFiveSkuSection();
        $this->createBFiveSkuSection();
        $this->createCommentsSection();
        
        $this->filePath = $this->savePptFileToServer();
        
        if($this->returnPath == false)
            $this->downloadFile($this->filePath);
        
        return $this->filePath;
    }
    
    private function setFileProperties(){
        $this->pptObject->getProperties()
        ->setCreator("Byteshake Limited")
        ->setLastModifiedBy("Byteshake Limited")
        ->setTitle("Office 2007 PPTX Document")
        ->setSubject("Office 2007 PPTX Document")
        ->setDescription("Office 2007 PPTX Document")
        ->setKeywords("Office 2007 PPTX Document")
        ->setCategory("Office 2007 PPTX Document");
    }
    

    private function createHeaderTextSection() {
        $headerTextBoxLeft = $this->currentSlide->createRichTextShape()
                ->setHeight(80)
                ->setWidth(580)
                ->setOffsetX(0)
                ->setOffsetY(0);

        $headerTextBoxLeft->getActiveParagraph()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        $textRun = $headerTextBoxLeft->createTextRun('Store Name : ');
        $textRun->getFont()->setBold(true)
                ->setSize(16)
                ->setItalic(true)
                ->setColor(new Color( 'FF000000' ));
        $textRun = $headerTextBoxLeft->createTextRun($this->storeData['STORE']);
        $textRun->getFont()->setBold(true)
                ->setSize(14)
                ->setItalic(true)
                ->setColor(new Color( 'FF31859C' ));

        $headerTextBoxLeft->createBreak();

        $textRun = $headerTextBoxLeft->createTextRun('Store No : ');
        $textRun->getFont()->setBold(true)
                ->setSize(16)
                ->setItalic(true)
                ->setColor(new Color('FF000000'));
        $textRun = $headerTextBoxLeft->createTextRun($this->storeData['SNO']);
        $textRun->getFont()->setBold(true)
                ->setSize(14)
                ->setItalic(true)
                ->setColor(new Color('FF31859C'));

        $headerTextBoxLeft->createBreak();
                
        $textRun = $headerTextBoxLeft->createTextRun('Dates : ');
        $textRun->getFont()->setBold(true)
                ->setSize(16)
                ->setItalic(true)
                ->setColor(new Color('FF000000'));
        $textRun = $headerTextBoxLeft->createTextRun(date('d M Y', strtotime($this->data['fromDate']))." to ".date('d M Y', strtotime($this->data['toDate'])));
        $textRun->getFont()->setBold(true)
                ->setSize(14)
                ->setItalic(true)
                ->setColor(new Color('FF31859C'));                
                
        $headerTextBoxRight = $this->currentSlide->createRichTextShape()
                ->setHeight(80)
                ->setWidth(420)
                ->setOffsetX(590)
                ->setOffsetY(0);
        $headerTextBoxRight->getActiveParagraph()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        $textRun = $headerTextBoxRight->createTextRun('Banner/Area: ');
        $textRun->getFont()->setSize(12)
                ->setColor(new Color('FF000000'));
        $textRun = $headerTextBoxRight->createTextRun($this->storeData['AREA']);
        $textRun->getFont()->setSize(11)
                ->setColor(new Color('FF31859C'));

        /*     $headerTextBoxRight->createBreak();
          $textRun = $headerTextBoxRight->createTextRun('Municipality: ');
          $textRun->getFont()->setSize(12)
          ->setColor( new Color( 'FF000000' ) );
          $textRun = $headerTextBoxRight->createTextRun($this->storeData['MUNIC']);
          $textRun->getFont()->setSize(11)
          ->setColor( new Color( 'FF31859C' ) ); */

        $headerTextBoxRight->createBreak();
        $textRun = $headerTextBoxRight->createTextRun('Territory: ');
        $textRun->getFont()->setSize(12)
                ->setColor(new Color('FF000000'));
        $textRun = $headerTextBoxRight->createTextRun($this->storeData['TERRITORY']);
        $textRun->getFont()->setSize(11)
                ->setColor(new Color('FF31859C'));

        $headerTextBoxRight->createBreak();

        $shape = $this->currentSlide->createLineShape(0, 82, 960, 82);
        $shape->getBorder()->getColor()->setARGB('FF4A7EBB');
    }


    private function createStorePerformanceSection() {

        $storePerformanceText = $this->currentSlide->createRichTextShape()
                ->setHeight(33)
                ->setWidth(420)
                ->setOffsetX(0)
                ->setOffsetY(85);

        $storePerformanceText->getActiveParagraph()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $storePerformanceText->getFill()->setFillType(Fill::FILL_GRADIENT_LINEAR)
                ->setRotation(90)
                ->setStartColor(new Color('FF604A7B'))
                ->setEndColor(new Color('FF604A7B'));

        $storePerformanceText->getShadow()->setVisible(true);
        $storePerformanceText->getShadow()->setDirection(90);
        $storePerformanceText->getShadow()->setDistance(5);

        $textRun = $storePerformanceText->createTextRun('Store Performance');
        $textRun->getFont()->setBold(true)
                ->setSize(14)
                ->setColor(new Color('FFFFFFFF'));
        $storePerformanceText->createBreak();

        $storeGrid = $this->currentSlide->createTableShape(5);
        $storeGrid->setHeight(200);
        $storeGrid->setWidth(420);
        $storeGrid->setOffsetX(0);
        $storeGrid->setOffsetY(130);

        // Add row
        $headerRow = $storeGrid->createRow()->setHeight(20);
        $headerRow->getFill()->setFillType(Fill::FILL_GRADIENT_LINEAR)
                ->setRotation(90)
                ->setStartColor(new Color('FF4BACC6'))
                ->setEndColor(new Color('FF4BACC6'));
        $headerRow->nextCell()->createTextRun('')->getFont()->setSize(7)->setColor(new Color('FFFFFFFF'));
        $headerRow->nextCell()->createTextRun('TY$')->getFont()->setSize(7)->setColor(new Color('FFFFFFFF'));
        $headerRow->nextCell()->createTextRun('LY$')->getFont()->setSize(7)->setColor(new Color('FFFFFFFF'));
        $headerRow->nextCell()->createTextRun('VAR%')->getFont()->setSize(7)->setColor(new Color('FFFFFFFF'));
        $headerRow->nextCell()->createTextRun('AREA VAR%')->getFont()->setSize(7)->setColor(new Color('FFFFFFFF'));
        //$headerRow->nextCell()->createTextRun('MUNICIPALTY VAR%')->getFont()->setSize(7)->setColor( new Color( 'FFFFFFFF' ) );
        $this->setBorderColor($headerRow, 'FFFFFFFF');

        $dataRow = $storeGrid->createRow()->setHeight(20);
        $dataRow->getFill()->setFillType(Fill::FILL_GRADIENT_LINEAR)
                ->setRotation(90)
                ->setStartColor(new Color('FFD0E3EA'))
                ->setEndColor(new Color('FFD0E3EA'));

        $dataRow->nextCell()->createTextRun($this->storeData['STORE'])->getFont()->setSize(7)->setColor(new Color('FF000000'));
        $dataRow->nextCell()->createTextRun($this->storeData['TYEAR'])->getFont()->setSize(7)->setColor(new Color('FF000000'));
        $dataRow->nextCell()->createTextRun($this->storeData['LYEAR'])->getFont()->setSize(7)->setColor(new Color('FF000000'));
        $dataRow->nextCell()->createTextRun($this->storeData['VARP'])->getFont()->setSize(7)->setColor(new Color('FF000000'));
        $dataRow->nextCell()->createTextRun($this->storeData['AREA_VAR_PCT'])->getFont()->setSize(7)->setColor(new Color('FF000000'));
        //$dataRow->nextCell()->createTextRun($this->storeData['MUNIC_VAR_PCT'])->getFont()->setSize(7)->setColor( new Color( 'FF000000' ) );
        $this->setBorderColor($dataRow, 'FFFFFFFF');
    }


    private function createStoreCategorySection() {

        $storeCatText = $this->currentSlide->createRichTextShape()
                ->setHeight(32)
                ->setWidth(420)
                ->setOffsetX(0)
                ->setOffsetY(220);
        $storeCatText->getActiveParagraph()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $storeCatText->getFill()->setFillType(Fill::FILL_GRADIENT_LINEAR)
                ->setRotation(90)
                ->setStartColor(new Color('FF604A7B'))
                ->setEndColor(new Color('FF604A7B'));

        $storeCatText->getShadow()->setVisible(true);
        $storeCatText->getShadow()->setDirection(90);
        $storeCatText->getShadow()->setDistance(5);

        $textRun = $storeCatText->createTextRun('Store Performance By Category');
        $textRun->getFont()->setBold(true)
                ->setSize(14)
                ->setColor(new Color('FFFFFFFF'));


        $storeCatText->createBreak();

        $storeCatGrid = $this->currentSlide->createTableShape(5);
        $storeCatGrid->setHeight(150);
        $storeCatGrid->setWidth(420);
        $storeCatGrid->setOffsetX(0);
        $storeCatGrid->setOffsetY(270);


        $headerRow = $storeCatGrid->createRow()->setHeight(15);
        $headerRow->getFill()->setFillType(Fill::FILL_GRADIENT_LINEAR)
                ->setRotation(90)
                ->setStartColor(new Color('FF4BACC6'))
                ->setEndColor(new Color('FF4BACC6'));
        $cell = $headerRow->nextCell();
        $cell->setWidth(120);
        $cell->createTextRun('')->getFont()->setSize(7)->setColor(new Color('FFFFFFFF'));

        $cell = $headerRow->nextCell();
        $cell->setWidth(75);
        $cell->createTextRun('TY$')->getFont()->setSize(7)->setColor(new Color('FFFFFFFF'));

        $cell = $headerRow->nextCell();
        $cell->setWidth(75);
        $cell->createTextRun('LY$')->getFont()->setSize(7)->setColor(new Color('FFFFFFFF'));

        $cell = $headerRow->nextCell();
        $cell->setWidth(75);
        $cell->createTextRun('VAR%')->getFont()->setSize(7)->setColor(new Color('FFFFFFFF'));

        $cell = $headerRow->nextCell();
        $cell->setWidth(75);
        $cell->createTextRun('AREA VAR%')->getFont()->setSize(7)->setColor(new Color('FFFFFFFF'));

        /*     $cell = $headerRow->nextCell();
          $cell->setWidth(60);
          $cell->createTextRun('MUNICIPALTY VAR%')->getFont()->setSize(7)->setColor( new Color( 'FFFFFFFF' ) );
          $this->setBorderColor($headerRow,'FFFFFFFF'); */

        for ($i = 0; $i < count($this->storeCatData); $i++) {
            $dataRow = $storeCatGrid->createRow()->setHeight(15);
            $dataRow->getFill()->setFillType(Fill::FILL_GRADIENT_LINEAR)
                    ->setRotation(90)
                    ->setStartColor(new Color($this->getCellColor($i)))
                    ->setEndColor(new Color($this->getCellColor($i)));


            $dataRow->nextCell()->createTextRun($this->storeCatData[$i]['CATEGORY'])->getFont()->setSize(7);
            $dataRow->nextCell()->createTextRun($this->storeCatData[$i]['TYEAR'])->getFont()->setSize(7);
            $dataRow->nextCell()->createTextRun($this->storeCatData[$i]['LYEAR'])->getFont()->setSize(7);
            $dataRow->nextCell()->createTextRun($this->storeCatData[$i]['VARPCT'])->getFont()->setSize(7);
            $dataRow->nextCell()->createTextRun($this->storeCatData[$i]['AREA_VAR_PCT'])->getFont()->setSize(7);
            //$dataRow->nextCell()->createTextRun($this->storeCatData[$i]['MUNIC_VAR_PCT'])->getFont()->setSize(7);
            $this->setBorderColor($dataRow, 'FFFFFFFF');
        }
    }

    
    private function createYellowBoxSection() {

        $yellowBox = $this->currentSlide->createRichTextShape()
                ->setHeight(83)
                ->setWidth(420)
                ->setOffsetX(0)
                ->setOffsetY(435);
        $yellowBox->getActiveParagraph()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        $yellowBox->getFill()->setFillType(Fill::FILL_GRADIENT_LINEAR)
                ->setRotation(90)
                ->setStartColor(new Color('FFEEECE1'))
                ->setEndColor(new Color('FFEEECE1'));

        $yellowBox->getShadow()->setVisible(true);
        $yellowBox->getShadow()->setDirection(90);
        $yellowBox->getShadow()->setDistance(5);


        $yellowBox->createTextRun("Store associate name: ")->getFont()->setSize(14)->setColor(new Color('FF1F499F'));
        $smallLine = $this->currentSlide->createLineShape(200, 470, 400, 470);
        $smallLine->getBorder()->getColor()->setARGB('FF1F499F');

        $yellowBox->createBreak();
        $yellowBox->createBreak();
        $yellowBox->createTextRun("Store associate signature: ")->getFont()->setSize(14)->setColor(new Color('FF1F499F'));
    }


    private function createSkuPerHeaderSection() {

        $skuPerformanceText = $this->currentSlide->createRichTextShape()
                ->setHeight(33)
                ->setWidth(525)
                ->setOffsetX(440)
                ->setOffsetY(85);
        $skuPerformanceText->getActiveParagraph()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $skuPerformanceText->getFill()->setFillType(Fill::FILL_GRADIENT_LINEAR)
                ->setRotation(90)
                ->setStartColor(new Color('FF604A7B'))
                ->setEndColor(new Color('FF604A7B'));

        $skuPerformanceText->getShadow()->setVisible(true);
        $skuPerformanceText->getShadow()->setDirection(90);
        $skuPerformanceText->getShadow()->setDistance(5);

        $textRun = $skuPerformanceText->createTextRun('SKU Performance');
        $textRun->getFont()->setBold(true)
                ->setSize(14)
                ->setColor(new Color('FFFFFFFF'));

        $skuPerformanceText->createBreak();
    }



    private function createTFiveSkuSection() {

        $topFiveSkuText = $this->currentSlide->createRichTextShape()
                ->setHeight(33)
                ->setWidth(517)
                ->setOffsetX(443)
                ->setOffsetY(126);
        $topFiveSkuText->getActiveParagraph()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $topFiveSkuText->getFill()->setFillType(Fill::FILL_GRADIENT_LINEAR)
                ->setRotation(90)
                ->setStartColor(new Color('FF77933C'))
                ->setEndColor(new Color('FF77933C'));


        $textRun = $topFiveSkuText->createTextRun('Top 5 SKUs');
        $textRun->getFont()->setBold(true)
                ->setSize(14)
                ->setColor(new Color('FFFFFFFF'));


        $topFiveSkuText->createBreak();

        $topFiveSkuGrid = $this->currentSlide->createTableShape(6);
        $topFiveSkuGrid->setHeight(300);
        $topFiveSkuGrid->setWidth(520);
        $topFiveSkuGrid->setOffsetX(440);
        $topFiveSkuGrid->setOffsetY(159);



        $headerRow = $topFiveSkuGrid->createRow()->setHeight(20);
        $headerRow->getFill()->setFillType(Fill::FILL_GRADIENT_LINEAR)
                ->setRotation(90)
                ->setStartColor(new Color('FF4BACC6'))
                ->setEndColor(new Color('FF4BACC6'));
        $cell = $headerRow->nextCell();
        $cell->setWidth(58);
        $cell->createTextRun('Article No.')->getFont()->setBold(true)->setSize(7)->setColor(new Color('FFFFFFFF'));

        $cell = $headerRow->nextCell();
        $cell->setWidth(230);
        $cell->createTextRun('Sku Name')->getFont()->setBold(true)->setSize(7)->setColor(new Color('FFFFFFFF'));

        $cell = $headerRow->nextCell();
        $cell->setWidth(58);
        $cell->createTextRun('$Sales')->getFont()->setBold(true)->setSize(7)->setColor(new Color('FFFFFFFF'));

        $cell = $headerRow->nextCell();
        $cell->setWidth(58);
        $cell->createTextRun('Store Var%')->getFont()->setBold(true)->setSize(7)->setColor(new Color('FFFFFFFF'));

        $cell = $headerRow->nextCell();
        $cell->setWidth(58);
        $cell->createTextRun('AREA VAR%')->getFont()->setBold(true)->setSize(7)->setColor(new Color('FFFFFFFF'));

        $cell = $headerRow->nextCell();
        $cell->setWidth(58);
        $cell->createTextRun('OVER_UNDER')->getFont()->setBold(true)->setSize(7)->setColor(new Color('FFFFFFFF'));
        $this->setBorderColor($headerRow, 'FFFFFFFF');

        for ($i = 0; $i < count($this->topFiveSku); $i++) {
            $dataRow = $topFiveSkuGrid->createRow()->setHeight(25);
            $dataRow->getFill()->setFillType(Fill::FILL_GRADIENT_LINEAR)
                    ->setRotation(90)
                    ->setStartColor(new Color($this->getCellColor($i)))
                    ->setEndColor(new Color($this->getCellColor($i)));
            $dataRow->nextCell()->createTextRun($this->topFiveSku[$i]['PIN'])->getFont()->setSize(7);
            $dataRow->nextCell()->createTextRun($this->topFiveSku[$i]['PNAME'])->getFont()->setSize(7);
            $dataRow->nextCell()->createTextRun($this->topFiveSku[$i]['SALES'])->getFont()->setSize(7);
            $dataRow->nextCell()->createTextRun($this->topFiveSku[$i]['VARPCT'])->getFont()->setSize(7);
            $dataRow->nextCell()->createTextRun($this->topFiveSku[$i]['AREA_VAR_PCT'])->getFont()->setSize(7);
            $dataRow->nextCell()->createTextRun($this->topFiveSku[$i]['OVER_UNDER'])->getFont()->setSize(7);
            $this->setBorderColor($dataRow, 'FFFFFFFF');
        }
    }



    private function createBFiveSkuSection() {
        $bottomFiveSkuText = $this->currentSlide->createRichTextShape()
                ->setHeight(33)
                ->setWidth(517)
                ->setOffsetX(443)
                ->setOffsetY(324);
        $bottomFiveSkuText->getActiveParagraph()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $bottomFiveSkuText->getFill()->setFillType(Fill::FILL_GRADIENT_LINEAR)
                ->setRotation(90)
                ->setStartColor(new Color('FF953735'))
                ->setEndColor(new Color('FF953735'));

        $textRun = $bottomFiveSkuText->createTextRun('Bottom 5 SKUs');
        $textRun->getFont()->setBold(true)
                ->setSize(14)
                ->setColor(new Color('FFFFFFFF'));


        $bottomFiveSkuText->createBreak();


        $bottomFiveSkuGrid = $this->currentSlide->createTableShape(6);
        $bottomFiveSkuGrid->setHeight(180);
        $bottomFiveSkuGrid->setWidth(520);
        $bottomFiveSkuGrid->setOffsetX(440);
        $bottomFiveSkuGrid->setOffsetY(362);


        // Add row
        $headerRow = $bottomFiveSkuGrid->createRow()->setHeight(15);
        $headerRow->setHeight(20);
        $headerRow->getFill()->setFillType(Fill::FILL_GRADIENT_LINEAR)
                ->setRotation(90)
                ->setStartColor(new Color('FF4BACC6'))
                ->setEndColor(new Color('FF4BACC6'));
        $cell = $headerRow->nextCell();
        $cell->setWidth(58);
        $cell->createTextRun('Article No.')->getFont()->setBold(true)->setSize(7)->setColor(new Color('FFFFFFFF'));

        $cell = $headerRow->nextCell();
        $cell->setWidth(230);
        $cell->createTextRun('Sku Name')->getFont()->setBold(true)->setSize(7)->setColor(new Color('FFFFFFFF'));

        $cell = $headerRow->nextCell();
        $cell->setWidth(58);
        $cell->createTextRun('$Sales')->getFont()->setBold(true)->setSize(7)->setColor(new Color('FFFFFFFF'));

        $cell = $headerRow->nextCell();
        $cell->setWidth(58);
        $cell->createTextRun('Store Var%')->getFont()->setBold(true)->setSize(7)->setColor(new Color('FFFFFFFF'));

        $cell = $headerRow->nextCell();
        $cell->setWidth(58);
        $cell->createTextRun('AREA VAR%')->getFont()->setBold(true)->setSize(7)->setColor(new Color('FFFFFFFF'));

        $cell = $headerRow->nextCell();
        $cell->setWidth(58);
        $cell->createTextRun('OVER_UNDER')->getFont()->setBold(true)->setSize(7)->setColor(new Color('FFFFFFFF'));
        $this->setBorderColor($headerRow, 'FFFFFFFF');

        for ($i = 0; $i < count($this->bottomFiveSku); $i++) {
            $dataRow = $bottomFiveSkuGrid->createRow()->setHeight(25);
            $dataRow->getFill()->setFillType(Fill::FILL_GRADIENT_LINEAR)
                    ->setRotation(90)
                    ->setStartColor(new Color($this->getCellColor($i)))
                    ->setEndColor(new Color($this->getCellColor($i)));
            $dataRow->nextCell()->createTextRun($this->bottomFiveSku[$i]['PIN'])->getFont()->setSize(7);
            $dataRow->nextCell()->createTextRun($this->bottomFiveSku[$i]['PNAME'])->getFont()->setSize(7);
            $dataRow->nextCell()->createTextRun($this->bottomFiveSku[$i]['SALES'])->getFont()->setSize(7);
            $dataRow->nextCell()->createTextRun($this->bottomFiveSku[$i]['VARPCT'])->getFont()->setSize(7);
            $dataRow->nextCell()->createTextRun($this->bottomFiveSku[$i]['AREA_VAR_PCT'])->getFont()->setSize(7);
            $dataRow->nextCell()->createTextRun($this->bottomFiveSku[$i]['OVER_UNDER'])->getFont()->setSize(7);
            $this->setBorderColor($dataRow, 'FFFFFFFF');
        }
    }



    private function createCommentsSection() {

        $commentBox = $this->currentSlide->createRichTextShape()
                ->setHeight(30)
                ->setWidth(200)
                ->setOffsetX(0)
                ->setOffsetY(530);
        $commentBox->getActiveParagraph()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        $textRun = $commentBox->createTextRun('Commentary');
        $textRun->getFont()->setBold(true)
                ->setSize(12)
                ->setColor(new Color('FF000000'));

        //creating five line shapes
        // 1.
        $shape = $this->currentSlide->createLineShape(0, 555, 960, 555);
        $shape->getBorder()->getColor()->setARGB('FF4A7EBB');

        // 2.
        $shape = $this->currentSlide->createLineShape(0, 587, 960, 587);
        $shape->getBorder()->getColor()->setARGB('FF4A7EBB');

        // 3.
        $shape = $this->currentSlide->createLineShape(0, 622, 960, 622);
        $shape->getBorder()->getColor()->setARGB('FF4A7EBB');

        // 4.
        $shape = $this->currentSlide->createLineShape(0, 657, 960, 657);
        $shape->getBorder()->getColor()->setARGB('FF4A7EBB');

        // 5.
        $shape = $this->currentSlide->createLineShape(0, 685, 960, 685);
        $shape->getBorder()->getColor()->setARGB('FF4A7EBB');
    }



    private function savePptFileToServer() {
        $fileName = date('Y-m-d H:i:s') . "_storeReport.pptx";
        chdir("../zip/");
        $objWriter = IOFactory::createWriter($this->pptObject, 'PowerPoint2007');
        $objWriter->save(getcwd() . DIRECTORY_SEPARATOR . $fileName);
        $filePath = getcwd() . DIRECTORY_SEPARATOR . $fileName;

        return $filePath;
    }
    


    private function downloadFile($fullPath) {
        if ($fd = fopen($fullPath, "r")) {
            $fsize = filesize($fullPath);
            $path_parts = pathinfo($fullPath);
            $ext = strtolower($path_parts["extension"]);
            switch ($ext) {
                case "pptx": {
                        header("Content-type: application/force-download");
                        header("Content-Transfer-Encoding: application/vnd.openxmlformats-officedocument.presentationml.presentation");
                        header("Content-disposition: attachment; filename=" . "reportData.pptx");
                        break;
                    }
                default: print "Unknown file format";
                    exit;
            }
            header("Content-length: $fsize");
            header("Cache-Control: must-revalidate, post-check=0, pre-check=0, public");
            header("Expires: 0");
            @readfile($fullPath);
        }
        fclose($fd);
        unlink($fullPath);
    }



    /* SETS GIVEN COLOR '$color' AS THE BORDER COLOR OF THE GIVEN ROW '$row' */
    private function setBorderColor($row, $color) {
        foreach ($row->getCells() as $cell) {
            $cell->getBorders()->getTop()->setLineWidth(1)->setLineStyle(Border::LINE_SINGLE)->setColor(new Color($color));
            $cell->getBorders()->getLeft()->setLineWidth(1)->setLineStyle(Border::LINE_SINGLE)->setColor(new Color($color));
            $cell->getBorders()->getRight()->setLineWidth(1)->setLineStyle(Border::LINE_SINGLE)->setColor(new Color($color));
            $cell->getBorders()->getBottom()->setLineWidth(1)->setLineStyle(Border::LINE_SINGLE)->setColor(new Color($color));
        }
    }

    /* RETURNS CELL FILL COLOR ACCORDING TO $i */
    private function getCellColor($i) {
        if ($i % 2 == 0) {
            return 'FFD0E3EA';
        } else
            return 'FFE9F1F5';
    }

}
?>