<?php
    namespace PDFformatter\Template;

    require $_SERVER['DOCUMENT_ROOT'] . '/api' . '/vendor/autoload.php';

    use \stdClass;
    use \DateTime;

    class VideoLibrary extends \PDFformatter\Layout\TwoColumn {

        function __construct($orientation='P', $unit='mm', $size='A4') {
            parent::__construct($orientation, $unit, $size);
//
//            /* data need for pdf
//             * Top Category (highlight title block)
//             *      - Sub Category (slightly bold title block)
//             * id
//             * title
//             * duration
//             * created_date
//             */
//
//            $this->config = new stdClass;
//
//            $this->config->layout = new stdClass;
//            $this->config->layout->pageMarginLeft = 15;
//            $this->config->layout->pageMarginRight = 15;
//            $this->config->layout->pageMarginTop = 15;
//            $this->config->layout->numOfColumns = 2;
//
//            $this->SetLeftMargin($this->config->layout->pageMarginLeft);
//            $this->SetRightMargin($this->config->layout->pageMarginRight);
//
//            $this->bodyWidth = $this->GetPageWidth() - $this->lMargin - $this->rMargin;
//            $this->colWidth = $this->bodyWidth / $this->config->layout->numOfColumns;
//
//            // Set total page alias. use default {nb}
//            $this->AliasNbPages();
//
//            // page config
//            $this->setAutoPageBreak(true, $this->bodyMarginBottom); // default: 20mm. Should be about the height of the entire footer

            // Built-in fonts: Courier, Arial, Helvetica, Times, Symbol, ZapfDingbats

            $this->AddPage();
            $this->AddFont('DejaVu','','DejaVuSansCondensed.ttf',true);
            $this->AddFont('DejaVu','B','DejaVuSansCondensed-Bold.ttf',true);
        }

        // Page header
        function Header()
        {
            $this->setHeader(function() {
                $title = "Video Library";
                // Logo
                $this->Image(__DIR__ . '/healthination-logo-colored.png', $this->getPageMarginLeft(), 15, 30);

                $this->SetFont('Helvetica', 'B', 18);
                $this->SetTextColor(33);

                // Move to the right
                $this->SetY(18);  // Position from top

                // Title
                $this->Cell($this->bodyWidth, 10, $title, 0, 0, 'C');
                // Line break
                $this->Ln(20);
            }, ($this->getPageMarginTop() > 35 ? $this->getPageMarginTop() : 35));
        }

        // Page footer
        function Footer()
        {
            $this->setFooter(function() {
                // Random text
                $date = new DateTime('now');
                $text = $date->format('M d, Y') . ' | ' . '212.633.0007' . ' | ' . 'www.healthination.com';
                $this->SetY(-20);  // Position  from bottom
                $this->SetFont('Helvetica', '', 8);
                $this->Cell($this->bodyWidth, 10, $text, 0, 0, 'C');

                // Page number

                $this->SetY(-15); // Position from bottom
                $this->SetFont('Helvetica', 'I', 8);
                $this->Cell($this->bodyWidth, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
            }, 30);
        }

        // Highly Customized function to print a section title
        function printSectionTitle($title) {
            $w = $this->colWidth - 6;
            $h = 14;
            $this->SetFont('DejaVu','B',15); // in points
            $this->SetTextColor(255);
            $this->SetFillColor(41,171,226);   // babyblue: #29ABE2 rgb(41,171,226)
            $this->Cell($w, $h, $title,0,1,'C',true);
            // column break or page break caused by the Cell element would not cause any issues. So just clear them.
            $this->columnBreakSignal = false;
            $this->pageBreakSignal = false;
            $this->Ln(2);
        }

        // Highly Customized function to print a section subtitle
        function printSectionSubTitle($title) {
            $w = $this->colWidth - 6;
            $h = 10;
            $this->SetFont('DejaVu','B',12); // in points
            $this->SetTextColor(33);
            $this->Cell($w, $h, $title,0,1,'L');
            // column break or page break caused by the Cell element would not cause any issues. So just clear them.
            $this->columnBreakSignal = false;
            $this->pageBreakSignal = false;
        }

        // Highly Customized function to print selected fields as row
        function printItem($item) {
            $h = 3.6;
            $w = array(11, 50, 9, 14);
            $border = 0;
            $this->SetFont('Helvetica','',8);
            $this->SetTextColor(128);

            $x = $this->GetX();
            $y = $this->GetY();

            if ($this->columnBreakSignal) {
                $y = $this->bodyMarginTop; // use the new Y set forth by the Column Break action

                $this->columnBreakSignal = false;
            }
            else if ($this->pageBreakSignal) {
                $y = $this->bodyMarginTop; // use the new Y set forth by the Page Break action

                $this->pageBreakSignal = false;
            }
            $offsetX = $this->lMargin;
            $this->SetXY($offsetX,$y);

            $this->MultiCell($w[0], $h, $item->id, $border);
            if ($this->columnBreakSignal) {
                $y = $this->bodyMarginTop; // use the new Y set forth by the Column Break action

                $this->columnBreakSignal = false;
            }
            else if ($this->pageBreakSignal) {
                $y = $this->bodyMarginTop; // use the new Y set forth by the Page Break action

                $this->pageBreakSignal = false;
            }
            $offsetX = $this->lMargin + $w[0];
            $this->SetXY($offsetX,$y);

            $this->MultiCell($w[1], $h, $item->title, $border);
            if ($this->columnBreakSignal) {
                $y = $this->bodyMarginTop; // use the new Y set forth by the Column Break action

                $this->columnBreakSignal = false;
            }
            else if ($this->pageBreakSignal) {
                $y = $this->bodyMarginTop; // use the new Y set forth by the Page Break action

                $this->pageBreakSignal = false;
            }
            $offsetX = $this->lMargin + $w[0] + $w[1];
            $this->SetXY($offsetX,$y);

            $this->MultiCell($w[2], $h, $item->duration, $border, 'R');
            if ($this->columnBreakSignal) {
                $y = $this->bodyMarginTop; // use the new Y set forth by the Column Break action

                $this->columnBreakSignal = false;
            }
            else if ($this->pageBreakSignal) {
                $y = $this->bodyMarginTop; // use the new Y set forth by the Page Break action

                $this->pageBreakSignal = false;
            }
            $offsetX = $this->lMargin + $w[0] + $w[1] + $w[2];
            $this->SetXY($offsetX,$y);

            $this->MultiCell($w[3], $h, $item->published_date, $border);

            $this->Ln($h+5);
        }


    }