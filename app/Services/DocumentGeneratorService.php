<?php
declare(strict_types=1);

namespace WKS\Services;

use ZipArchive;

final class DocumentGeneratorService
{
    public function pdf(string $title,array $sections,?string $watermark='VERTRAULICH'): string
    {
        $lines=[];
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
            foreach($this->wrap($line,95) as $part)$wrapped[]=$part;
        }

        $pages=array_chunk($wrapped,48);
        if($pages===[])$pages=[[]];
        $objects=[];
        $fontId=3;
        $pageIds=[];
        $contentIds=[];
        $nextId=4;
        foreach($pages as $_){$pageIds[]=$nextId++;$contentIds[]=$nextId++;}

        $objects[1]='<< /Type /Catalog /Pages 2 0 R >>';
        $kids=implode(' ',array_map(static fn(int $id):string=>$id.' 0 R',$pageIds));
        $objects[2]='<< /Type /Pages /Kids ['.$kids.'] /Count '.count($pageIds).' >>';
        $objects[$fontId]='<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';

        foreach($pages as $i=>$pageLines){
            $commands=["BT","/F1 10 Tf","48 790 Td","13 TL"];
            if($watermark){
                $wm=$this->pdfText($watermark);
                $commands[]="q";
                $commands[]="0.88 g";
                $commands[]="/F1 42 Tf";
                $commands[]="90 350 Td";
                $commands[]="(".$wm.") Tj";
                $commands[]="Q";
                $commands[]="/F1 10 Tf";
                $commands[]="-90 440 Td";
            }
            foreach($pageLines as $line){
                $commands[]='('.$this->pdfText($line).') Tj';
                $commands[]='T*';
            }
            $commands[]="ET";
            $stream=implode("\n",$commands);
            $contentId=$contentIds[$i];
            $pageId=$pageIds[$i];
            $objects[$contentId]='<< /Length '.strlen($stream)." >>\nstream\n".$stream."\nendstream";
            $objects[$pageId]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents '.$contentId.' 0 R >>';
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

    public function docx(string $title,array $sections): string
    {
        if(!class_exists(ZipArchive::class))throw new \RuntimeException('PHP-Erweiterung ZipArchive ist für DOCX nicht verfügbar.');
        $dir=BASE_PATH.'/storage/generated';
        if(!is_dir($dir)&&!mkdir($dir,0770,true)&&!is_dir($dir))throw new \RuntimeException('Ausgabeverzeichnis kann nicht angelegt werden.');
        $path=$dir.'/'.bin2hex(random_bytes(20)).'.docx';
        $zip=new ZipArchive();
        if($zip->open($path,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)throw new \RuntimeException('DOCX-Datei kann nicht erzeugt werden.');

        $content='<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>';
        $content.=$this->paragraph($title,true,32);
        foreach($sections as $heading=>$value){
            if($heading!=='')$content.=$this->paragraph((string)$heading,true,24);
            if(is_array($value)){
                foreach($value as $row)$content.=$this->paragraph($this->flatten($row));
            }else{
                foreach(preg_split('/\R/u',(string)$value)?:[] as $line)$content.=$this->paragraph($line);
            }
        }
        $content.='<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1134"/></w:sectPr></w:body></w:document>';

        $zip->addFromString('[Content_Types].xml','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $zip->addFromString('_rels/.rels','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
        $zip->addFromString('word/document.xml','<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.$content);
        $zip->close();
        return $path;
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
