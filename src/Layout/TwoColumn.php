<?php
    namespace PDFformatter\Layout;
    
    require('vendor/autoload.php');
    
    use \stdClass;

    class TwoColumn extends \tFPDF
    {

        protected $col = 0;

        protected $colWidth;

        protected $bodyWidth;

        protected $bodyMarginTop = 30;

        protected $bodyMarginBottom = 30;

        protected $pageBreakSignal = false;

        protected $columnBreakSignal = false;


        /**
         * TwoColumn constructor.
         */
        function __construct($orientation = 'P', $unit = 'mm', $size = 'A4')
        {
            parent::__construct($orientation, $unit, $size);

            $this->config = new stdClass;

            $this->config->layout = new stdClass;
            $this->config->layout->pageMarginLeft = 15;
            $this->config->layout->pageMarginRight = 15;
            $this->config->layout->pageMarginTop = 15;
            $this->config->layout->numOfColumns = 2;

            $this->SetLeftMargin($this->config->layout->pageMarginLeft);
            $this->SetRightMargin($this->config->layout->pageMarginRight);

            $this->bodyWidth = $this->GetPageWidth() - $this->lMargin - $this->rMargin;
            $this->colWidth = $this->bodyWidth / $this->config->layout->numOfColumns;

            // Set total page alias. use default {nb}
            $this->AliasNbPages();

            // page config
            $this->setAutoPageBreak(true, $this->bodyMarginBottom); // default: 20mm. Should be about the height of the entire footer
        }

        function SetCol($col)
        {
            // Move position to a column
            $this->col = $col;
            $x = $this->getPageMarginLeft() + ($col * $this->colWidth);
            $this->SetLeftMargin($x);   // absolute margin left of column relative to the page
            $this->SetX($x);

            //var_dump('reset Left Margin to: ' . $this->lMargin . ' and new X cursor to: '. $this->GetX());
        }

        /**
         * Gets called when AutoPageBreak triggers a page break action. Do note the element that initially triggers the Page Break is already in the document
         * @link http://www.fpdf.org/en/doc/acceptpagebreak.htm
         *
         * @return bool
         */
        function AcceptPageBreak()
        {
            //var_dump('Call AcceptPageBreak');

            // set to 1 for a 2-column layout
            if ($this->col < 1) {
                // Go to next column
                $this->SetCol($this->col + 1);
                $this->SetY($this->bodyMarginTop);    // absolute margin top of column relative to the page. set where the start Y for the next column

                $this->columnBreakSignal = true;

                //var_dump('move to column ' . ($this->col) . ' and reset Y cursor to: ' . $this->GetY());
                return false;
            } else {
                // Go back to first column and issue page break
                $this->SetCol(0);

                $this->pageBreakSignal = true;

                //var_dump('move to page: ' . $this->PageNo());
                return true;
            }
        }

        function getPageMarginTop() {
            return $this->config->layout->pageMarginTop;
        }

        function getPageMarginLeft() {
            return $this->config->layout->pageMarginLeft;
        }

        function setBodyMarginTop(float $marginTop) {
            $this->bodyMarginTop = $marginTop;
            $this->SetTopMargin($marginTop);
            $this->SetY($marginTop);
        }

        function setHeader(callable $setter, float $height = 30) {
            call_user_func ($setter);

            $this->setBodyMarginTop($height);
        }

        // Page footer
        function setFooter(callable $setter, float $height = 30)
        {
            $prevMarginLeft = $this->lMargin;   // preserve current left margin
            $this->SetLeftMargin($this->getPageMarginLeft());   // set footer margin left same as page margin left

            call_user_func ($setter);

            $this->SetLeftMargin($prevMarginLeft);  // restore current left margin

            // Allow overwriting body margin bottom to accommodate the footer and update autopage break if it is enabled
            $this->bodyMarginBottom = $height;
            if ($this->AutoPageBreak) {
                $this->setAutoPageBreak(true, $this->bodyMarginBottom);
            }
        }
    }