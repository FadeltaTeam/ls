<?php
class TemplateEngine {
    private $templatesPath;
    
    public function __construct($templatesPath = null) {
        $this->templatesPath = $templatesPath ?: __DIR__ . '/../templates/';
    }
    
    public function render($templateName, $data = array()) {
        $templatePath = $this->templatesPath . $templateName;
        
        if (!file_exists($templatePath)) {
            throw new Exception("Template file not found: " . $templatePath);
        }
        
        $content = file_get_contents($templatePath);
        
        // جایگزینی متغیرها
        foreach ($data as $key => $value) {
            $placeholder = '{{' . $key . '}}';
            $content = str_replace($placeholder, $value, $content);
        }
        
        // جایگزینی حلقه‌ها (برای فاکتور)
        if (strpos($templateName, 'invoice') !== false && isset($data['items'])) {
            $content = $this->processLoop($content, 'items', $data['items']);
        }
        
        return $content;
    }
    
    private function processLoop($content, $loopName, $items) {
        $pattern = '/\{\{#'.$loopName.'\}\}(.*?)\{\{\/'.$loopName.'\}\}/s';
        
        if (preg_match($pattern, $content, $matches)) {
            $loopTemplate = $matches[1];
            $loopContent = '';
            
            foreach ($items as $index => $item) {
                $itemContent = $loopTemplate;
                $item['row_number'] = $index + 1;
                
                foreach ($item as $key => $value) {
                    $placeholder = '{{' . $key . '}}';
                    $itemContent = str_replace($placeholder, $value, $itemContent);
                }
                
                $loopContent .= $itemContent;
            }
            
            $content = str_replace($matches[0], $loopContent, $content);
        }
        
        return $content;
    }
    
    public function renderEmail($templateName, $data = array()) {
        $content = $this->render('email/' . $templateName, $data);
        
        // اضافه کردن استایل‌های inline برای ایمیل
        if (class_exists('TijsVerkoyen\CssToInlineStyles\CssToInlineStyles')) {
            $cssToInlineStyles = new TijsVerkoyen\CssToInlineStyles\CssToInlineStyles();
            $content = $cssToInlineStyles->convert($content);
        }
        
        return $content;
    }
    
    public function renderPDF($templateName, $data = array()) {
        $content = $this->render('pdf/' . $templateName, $data);
        return $content;
    }
    
    public function generatePDF($templateName, $data = array(), $filename = null) {
        $content = $this->renderPDF($templateName, $data);
        
        // استفاده از Dompdf برای تولید PDF
        if (class_exists('Dompdf\Dompdf')) {
            $dompdf = new Dompdf\Dompdf();
            $dompdf->loadHtml($content);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            
            if ($filename) {
                $dompdf->stream($filename, array('Attachment' => 0));
            }
            
            return $dompdf->output();
        }
        
        return $content;
    }
}
?>