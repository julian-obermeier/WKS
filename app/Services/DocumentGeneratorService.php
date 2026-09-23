<?php
declare(strict_types=1);

namespace WKS\Services;

use ZipArchive;
use WKS\Repositories\AdminRepository;

final class DocumentGeneratorService
{
    public function pdfForTemplate(string $templateCode,string $title,array $sections): string
    {
        $template=(new AdminRepository())->templateByCode($templateCode);
        if(!$template)return $this->pdf($title,$sections);
        return $this->pdf($title,$sections,$template['watermark_text'],[
            'header'=>$template['header_text'],
            'footer'=>$template['footer_text'],
            'page_numbers'=>(bool)$template['show_page_numbers'],
            'layout'=>(string)($template['settings']['layout']??'standard'),
            'logo_path'=>$template['logo_path']??null,
        ]);
    }

    public function docxForTemplate(string $templateCode,string $title,array $sections): string
    {
        $template=(new AdminRepository())->templateByCode($templateCode);
        $options=[];
        if($template){
            if($template['header_text'])$sections=['Kopfzeile'=>$template['header_text']]+$sections;
            if($template['footer_text'])$sections['Fußzeile']=$template['footer_text'];
            $options['layout']=(string)($template['settings']['layout']??'standard');
            $options['logo_path']=$template['logo_path']??null;
        }
        return $this->docx($title,$sections,$options);
    }

    public function pdf(string $title,array $sections,?string $watermark='VERTRAULICH',array $options=[]): string
    {
        $layout=in_array((string)($options['layout']??'standard'),['standard','compact'],true)?(string)($options['layout']??'standard'):'standard';
        $compact=$layout==='compact';$fontSize=$compact?8:10;$leading=$compact?10:13;$wrapWidth=$compact?118:95;$linesPerPage=$compact?62:48;
        $logo=$this->pdfLogo($options['logo_path']??null);
        if($logo)$linesPerPage=max(20,$linesPerPage-4);
        $lines=[];
        if(!empty($options['header'])){$lines[]=(string)$options['header'];$lines[]='';}
        $lines[]=$title;
        $lines[]=str_repeat('=',min(90,max(10,mb_strlen($title))));
        $lines[]='';
        foreach($sections as $heading=>$value){
            if($heading!==''){
                $lines[]=strtoupper((string)$heading);
                $lines[]=str_repeat('-',min(80,max(6,mb_strlen((string)$heading))));
            }
            if(is_array($value)){
                foreach($value as $row)$lines[]=$this->flatten($row);
            }else{
                foreach(preg_split('/\R/u',(string)$value)?:[] as $line)$lines[]=$line;
            }
            $lines[]='';
        }

        $wrapped=[];
        foreach($lines as $line){
            $line=trim((string)$line);
            if($line===''){$wrapped[]='';continue;}
            foreach($this->wrap($line,$wrapWidth) as $part)$wrapped[]=$part;
        }

        $pages=array_chunk($wrapped,$linesPerPage);
        if($pages===[])$pages=[[]];
        $objects=[];
        $fontId=3;$logoId=$logo?4:null;
        $pageIds=[];
        $contentIds=[];
        $nextId=$logo?5:4;
        foreach($pages as $_){$pageIds[]=$nextId++;$contentIds[]=$nextId++;}

        $objects[1]='<< /Type /Catalog /Pages 2 0 R >>';
        $kids=implode(' ',array_map(static fn(int $id):string=>$id.' 0 R',$pageIds));
        $objects[2]='<< /Type /Pages /Kids ['.$kids.'] /Count '.count($pageIds).' >>';
        $objects[$fontId]='<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        if($logo&&$logoId!==null){
            $objects[$logoId]='<< /Type /XObject /Subtype /Image /Width '.$logo['width'].' /Height '.$logo['height'].' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length '.strlen($logo['data'])." >>\nstream\n".$logo['data']."\nendstream";
        }

        foreach($pages as $i=>$pageLines){
            $textY=$logo?748:790;$commands=[];
            if($logo&&$logoId!==null){
                $drawWidth=min(110.0,max(48.0,(float)$logo['width']));
                $drawHeight=$drawWidth*((float)$logo['height']/max(1.0,(float)$logo['width']));
                if($drawHeight>54.0){$drawHeight=54.0;$drawWidth=$drawHeight*((float)$logo['width']/max(1.0,(float)$logo['height']));}
                $x=595-48-$drawWidth;$y=794-$drawHeight;
                $commands[]='q';$commands[]=sprintf('%.2F 0 0 %.2F %.2F %.2F cm',$drawWidth,$drawHeight,$x,$y);$commands[]='/Logo Do';$commands[]='Q';
            }
            $commands[]="BT";$commands[]="/F1 ".$fontSize." Tf";$commands[]="48 ".$textY." Td";$commands[]=$leading." TL";
            if($watermark){
                $wm=$this->pdfText($watermark);
                $commands[]="q";
                $commands[]="0.88 g";
                $commands[]="/F1 42 Tf";
                $commands[]="90 350 Td";
                $commands[]="(".$wm.") Tj";
                $commands[]="Q";
                $commands[]="/F1 ".$fontSize." Tf";
                $commands[]="-90 440 Td";
            }
            foreach($pageLines as $line){
                $commands[]='('.$this->pdfText($line).') Tj';
                $commands[]='T*';
            }
            $commands[]="ET";
            if(!empty($options['footer'])){
                $commands[]="BT";
                $commands[]="/F1 8 Tf";
                $commands[]="48 24 Td";
                $commands[]="(".$this->pdfText((string)$options['footer']).") Tj";
                $commands[]="ET";
            }
            if(($options['page_numbers']??true)===true){
                $commands[]="BT";
                $commands[]="/F1 8 Tf";
                $commands[]="270 24 Td";
                $commands[]="(".$this->pdfText('Seite '.($i+1).' / '.count($pages)).") Tj";
                $commands[]="ET";
            }
            $stream=implode("\n",$commands);
            $contentId=$contentIds[$i];
            $pageId=$pageIds[$i];
            $objects[$contentId]='<< /Length '.strlen($stream)." >>\nstream\n".$stream."\nendstream";
            $xObject=$logo&&$logoId!==null?' /XObject << /Logo '.$logoId.' 0 R >>':'';
            $objects[$pageId]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >>'.$xObject.' >> /Contents '.$contentId.' 0 R >>';
        }

        ksort($objects);
        $pdf="%PDF-1.4\n";
        $offsets=[0];
        foreach($objects as $id=>$object){
            $offsets[$id]=strlen($pdf);
            $pdf.=$id." 0 obj\n".$object."\nendobj\n";
        }
        $xref=strlen($pdf);
        $max=max(array_keys($objects));
        $pdf.="xref\n0 ".($max+1)."\n0000000000 65535 f \n";
        for($i=1;$i<=$max;$i++)$pdf.=sprintf("%010d 00000 n \n",$offsets[$i]??0);
        $pdf.="trailer\n<< /Size ".($max+1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";
        return $pdf;
    }

    public function docx(string $title,array $sections,array $options=[]): string
    {
        if(!class_exists(ZipArchive::class))throw new \RuntimeException('PHP-Erweiterung ZipArchive ist für DOCX nicht verfügbar.');
        $layout=in_array((string)($options['layout']??'standard'),['standard','compact'],true)?(string)($options['layout']??'standard'):'standard';
        $compact=$layout==='compact';$titleSize=$compact?28:32;$headingSize=$compact?21:24;$bodySize=$compact?18:20;$margin=$compact?720:1134;
        $logo=$this->docxLogo($options['logo_path']??null);
        $dir=BASE_PATH.'/storage/generated';
        if(!is_dir($dir)&&!mkdir($dir,0770,true)&&!is_dir($dir))throw new \RuntimeException('Ausgabeverzeichnis kann nicht angelegt werden.');
        $path=$dir.'/'.bin2hex(random_bytes(20)).'.docx';
        $zip=new ZipArchive();
        if($zip->open($path,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)throw new \RuntimeException('DOCX-Datei kann nicht erzeugt werden.');

        $content='<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture"><w:body>';
        if($logo)$content.=$this->docxLogoParagraph($logo);
        $content.=$this->paragraph($title,true,$titleSize);
        foreach($sections as $heading=>$value){
            if($heading!=='')$content.=$this->paragraph((string)$heading,true,$headingSize);
            if(is_array($value)){
                foreach($value as $row)$content.=$this->paragraph($this->flatten($row),false,$bodySize);
            }else{
                foreach(preg_split('/\R/u',(string)$value)?:[] as $line)$content.=$this->paragraph($line,false,$bodySize);
            }
        }
        $content.='<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="'.$margin.'" w:right="'.$margin.'" w:bottom="'.$margin.'" w:left="'.$margin.'"/></w:sectPr></w:body></w:document>';

        $imageType=$logo?'<Default Extension="'.$logo['extension'].'" ContentType="'.$logo['mime'].'"/>':'';
        $zip->addFromString('[Content_Types].xml','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'.$imageType.'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $zip->addFromString('_rels/.rels','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
        if($logo){
            $zip->addFromString('word/media/logo.'.$logo['extension'],$logo['data']);
            $zip->addFromString('word/_rels/document.xml.rels','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdLogo" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/logo.'.$logo['extension'].'"/></Relationships>');
        }
        $zip->addFromString('word/document.xml','<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.$content);
        $zip->close();
        return $path;
    }

    private function logoPath(mixed $relative): ?string
    {
        $relative=trim((string)($relative??''));if($relative==='')return null;
        $root=realpath(BASE_PATH.'/storage/generated');$path=realpath(BASE_PATH.'/storage/generated/'.$relative);
        if(!$root||!$path||!str_starts_with($path,$root.DIRECTORY_SEPARATOR)||!is_file($path))return null;
        return $path;
    }

    private function pdfLogo(mixed $relative): ?array
    {
        $path=$this->logoPath($relative);if(!$path)return null;
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($path);
        $data=(string)file_get_contents($path);
        if($mime==='image/png'){
            if(!function_exists('imagecreatefromstring')||!function_exists('imagejpeg'))throw new \RuntimeException('PNG-Logo benötigt die PHP-GD-Erweiterung für PDF-Ausgaben.');
            $source=@imagecreatefromstring($data);if(!$source)throw new \RuntimeException('PNG-Logo konnte nicht gelesen werden.');
            $width=imagesx($source);$height=imagesy($source);$canvas=imagecreatetruecolor($width,$height);
            $white=imagecolorallocate($canvas,255,255,255);imagefill($canvas,0,0,$white);imagecopy($canvas,$source,0,0,0,0,$width,$height);
            ob_start();imagejpeg($canvas,null,92);$data=(string)ob_get_clean();imagedestroy($canvas);imagedestroy($source);
        } elseif($mime!=='image/jpeg'){
            throw new \RuntimeException('Dokumentlogo muss PNG oder JPEG sein.');
        }
        $size=@getimagesizefromstring($data);if(!$size||($size[0]??0)<1||($size[1]??0)<1)throw new \RuntimeException('Dokumentlogo hat ungültige Bildabmessungen.');
        return ['data'=>$data,'width'=>(int)$size[0],'height'=>(int)$size[1]];
    }

    private function docxLogo(mixed $relative): ?array
    {
        $path=$this->logoPath($relative);if(!$path)return null;
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($path);$extension=$mime==='image/png'?'png':($mime==='image/jpeg'?'jpeg':null);
        if(!$extension)throw new \RuntimeException('Dokumentlogo muss PNG oder JPEG sein.');
        $data=(string)file_get_contents($path);$size=@getimagesizefromstring($data);
        if(!$size||($size[0]??0)<1||($size[1]??0)<1)throw new \RuntimeException('Dokumentlogo hat ungültige Bildabmessungen.');
        return ['data'=>$data,'mime'=>$mime,'extension'=>$extension,'width'=>(int)$size[0],'height'=>(int)$size[1]];
    }

    private function docxLogoParagraph(array $logo): string
    {
        $maxWidth=140;$width=min($maxWidth,max(48,(int)$logo['width']));$height=(int)round($width*((int)$logo['height']/max(1,(int)$logo['width'])));
        if($height>70){$height=70;$width=(int)round($height*((int)$logo['width']/max(1,(int)$logo['height'])));}
        $cx=$width*9525;$cy=$height*9525;
        return '<w:p><w:r><w:drawing><wp:inline distT="0" distB="0" distL="0" distR="0"><wp:extent cx="'.$cx.'" cy="'.$cy.'"/><wp:docPr id="1" name="WKS Logo"/><a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture"><pic:pic><pic:nvPicPr><pic:cNvPr id="0" name="Logo"/><pic:cNvPicPr/></pic:nvPicPr><pic:blipFill><a:blip r:embed="rIdLogo"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill><pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="'.$cx.'" cy="'.$cy.'"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr></pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing></w:r></w:p>';
    }

    private function paragraph(string $text,bool $bold=false,int $size=20): string
    {
        $safe=htmlspecialchars($text,ENT_XML1|ENT_QUOTES,'UTF-8');
        $props='<w:sz w:val="'.$size.'"/><w:szCs w:val="'.$size.'"/>'.($bold?'<w:b/>':'');
        return '<w:p><w:r><w:rPr>'.$props.'</w:rPr><w:t xml:space="preserve">'.$safe.'</w:t></w:r></w:p>';
    }

    private function wrap(string $line,int $width): array
    {
        $out=[];$remaining=$line;
        while(mb_strlen($remaining)>$width){
            $cut=mb_substr($remaining,0,$width);
            $space=mb_strrpos($cut,' ');
            if($space===false||$space<20)$space=$width;
            $out[]=mb_substr($remaining,0,$space);
            $remaining=ltrim(mb_substr($remaining,$space));
        }
        $out[]=$remaining;
        return $out;
    }

    private function pdfText(string $text): string
    {
        $encoded=iconv('UTF-8','Windows-1252//TRANSLIT//IGNORE',$text);
        $encoded=$encoded===false?$text:$encoded;
        return str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$encoded);
    }

    private function flatten(mixed $value): string
    {
        if(!is_array($value))return (string)$value;
        $parts=[];
        foreach($value as $k=>$v){
            if(is_array($v))$v=implode(', ',array_map('strval',$v));
            $parts[]=(is_string($k)?$k.': ':'').(string)$v;
        }
        return implode(' | ',$parts);
    }
}
