<?php
declare(strict_types=1);

namespace WKS\Controllers;

use WKS\Core\Authorization;
use WKS\Core\HttpException;
use WKS\Core\Request;
use WKS\Core\Response;
use WKS\Repositories\DutybookRepository;
use WKS\Services\UploadService;

final class AttachmentController
{
    public function download(Request $request,string $id): Response
    {
        $attachment=(new UploadService())->find((int)$id);
        if(!$attachment)throw new HttpException(404,'Anhang nicht gefunden.');

        if($attachment['module']==='dutybook'){
            if(!Authorization::can('dutybook.read'))throw new HttpException(403,'Kein Zugriff auf diesen Anhang.');
            if(!(new DutybookRepository())->findEntry((int)$attachment['record_id'],(int)active_location_id())){
                throw new HttpException(404,'Anhang nicht gefunden.');
            }
        } elseif ($attachment['module']==='special_report') {
            if(!Authorization::can('special_reports.read'))throw new HttpException(403,'Kein Zugriff auf diesen Anhang.');
            if(!(new SpecialReportRepository())->find((int)$attachment['record_id'],(int)active_location_id())){
                throw new HttpException(404,'Anhang nicht gefunden.');
            }
        } elseif ($attachment['module']==='valuables') {
            if(!Authorization::can('valuables.read'))throw new HttpException(403,'Kein Zugriff auf diesen Anhang.');
            if(!(new ValuablesRepository())->find((int)$attachment['record_id'],(int)active_location_id())){
                throw new HttpException(404,'Anhang nicht gefunden.');
            }
        } else {
            throw new HttpException(403,'Dieser Anhangstyp ist derzeit nicht freigegeben.');
        }

        $root=realpath(BASE_PATH.'/storage/uploads');
        $path=realpath(BASE_PATH.'/storage/uploads/'.$attachment['stored_name']);
        if(!$root||!$path||!str_starts_with($path,$root.DIRECTORY_SEPARATOR)||!is_file($path)){
            throw new HttpException(404,'Datei nicht gefunden.');
        }

        $name=preg_replace('/[^A-Za-z0-9._ -]/u','_',basename((string)$attachment['original_name'])) ?: 'download';
        return new Response((string)file_get_contents($path),200,[
            'Content-Type'=>(string)$attachment['mime_type'],
            'Content-Length'=>(string)filesize($path),
            'Content-Disposition'=>'attachment; filename="'.addslashes($name).'"',
            'X-Content-Type-Options'=>'nosniff',
        ]);
    }
}
