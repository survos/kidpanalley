<?php

namespace App\Services;

use DOMDocument;
use ZipArchive;

class DocxConversion{
    public function __construct(private string $filename)
    {
    }

    private function read_doc(string $delim="\n") {
        $fileHandle = fopen($this->filename, "r");
        $line = @fread($fileHandle, filesize($this->filename));
        dd($line);
        $lines = explode(chr(0x0D),$line);
        $outtext = "";
        foreach($lines as $thisline)
        {

            $pos = strpos($thisline, chr(0x00));
            if (($pos !== FALSE)||(strlen($thisline)==0))
            {
//                dd($thisline);
            } else {
                $outtext .= $thisline. $delim;
            }
        }
        $outtext = preg_replace("/[^a-zA-Z0-9\s\,\.\-\n\r\t@\/\_\(\)]/","",$outtext);
        return $outtext;
    }

    private function read_docx(){

        $striped_content = '';
        $content = '';

        $zip = zip_open($this->filename);

        if (!$zip || is_numeric($zip)) return false;

        while ($zip_entry = zip_read($zip)) {

            if (zip_entry_open($zip, $zip_entry) == FALSE) continue;

            if (zip_entry_name($zip_entry) != "word/document.xml") continue;

            $content .= zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));

            zip_entry_close($zip_entry);
        }// end while

        zip_close($zip);

        $content = str_replace('</w:r></w:p></w:tc><w:tc>', " ", $content);
        $content = str_replace('</w:r></w:p>', "\r\n", $content);

        dd($content);
        $striped_content = strip_tags($content);

        return $striped_content;
    }

    /************************excel sheet************************************/

    function xlsx_to_text($input_file){
        $xml_filename = "xl/sharedStrings.xml"; //content file name
        $zip_handle = new ZipArchive;
        $output_text = "";
        if(true === $zip_handle->open($input_file)){
            if(($xml_index = $zip_handle->locateName($xml_filename)) !== false){
                $xml_datas = $zip_handle->getFromIndex($xml_index);
                $xml_handle = (new DOMDocument())->loadXML($xml_datas, LIBXML_NOENT | LIBXML_XINCLUDE | LIBXML_NOERROR | LIBXML_NOWARNING);
                $output_text = strip_tags((string) $xml_handle->saveXML());
            }else{
                $output_text .="";
            }
            $zip_handle->close();
        }else{
            $output_text .="";
        }
        return $output_text;
    }

    /*************************power point files*****************************/
    function pptx_to_text($input_file){
        $zip_handle = new ZipArchive;
        $output_text = "";
        if(true === $zip_handle->open($input_file)){
            $slide_number = 1; //loop through slide files
            while(($xml_index = $zip_handle->locateName("ppt/slides/slide".$slide_number.".xml")) !== false){
                $xml_datas = $zip_handle->getFromIndex($xml_index);
                $xml_handle = (new DOMDocument())->loadXML($xml_datas, LIBXML_NOENT | LIBXML_XINCLUDE | LIBXML_NOERROR | LIBXML_NOWARNING);
                $output_text .= strip_tags((string) $xml_handle->saveXML());
                $slide_number++;
            }
            if($slide_number == 1){
                $output_text .="";
            }
            $zip_handle->close();
        }else{
            $output_text .="";
        }
        return $output_text;
    }


    public function convertToText() {

        if(isset($this->filename) && !file_exists($this->filename)) {
            return "File Not exists";
        }

        $fileArray = pathinfo((string) $this->filename);
        $file_ext  = $fileArray['extension'];
        if($file_ext == "doc" || $file_ext == "docx" || $file_ext == "xlsx" || $file_ext == "pptx")
        {

            $phpWord = new \PhpOffice\PhpWord\PhpWord();
            if($file_ext == "doc") {
                $text = '';
                $lines = [];
                $phpWord = \PhpOffice\PhpWord\IOFactory::load($this->filename, 'MsDoc');
                foreach ($phpWord->getSections() as $section) {
                    foreach ($section->getElements() as $e) {
                        $class = get_class($e);
                        if (method_exists($class, 'getText')) {
                            $text .= $e->getText();
                            foreach (explode("\t", $e->getText()) as $item) {
                                $lines[] = $item;
                            }
                        } else {
                        }
                        $text .= "\n";
                    }
                }
                dd($lines, $text);
                dd($phpWord);
                return $this->read_doc();
            } elseif($file_ext == "docx") {
                    $text = '';
                    $lines = [];
                    $phpWord = \PhpOffice\PhpWord\IOFactory::load($this->filename);
                    foreach ($phpWord->getSections() as $section) {
                        foreach ($section->getElements() as $e) {
                            $class = get_class($e);
                            if (method_exists($class, 'getText')) {
                                dd($class, $e, $e->getText());
                                $text .= $e->getText();
                                foreach (explode("\t", $e->getText()) as $item) {
                                    $lines[] = $item;
                                }
                            } else {
                            }
                            $text .= "\n";
                        }
                    }
                    dd($lines, $text);


                    return $this->read_docx();
            } elseif($file_ext == "xlsx") {
                return $this->xlsx_to_text();
            }elseif($file_ext == "pptx") {
                return $this->pptx_to_text();
            }
        } else {
            return "Invalid File Type";
        }
    }

}
