<?php
declare(strict_types=1);

namespace WKS\Services;

use finfo;
use WKS\Core\Auth;
use WKS\Core\HttpException;
use WKS\Repositories\AdminRepository;

final class TemplateService
{
    public function update(int $id,array $input,?array $logoFile): void
    {
        $repo=new AdminRepository();$old=$repo->template($id);if(!$old)throw new HttpException(404,'Vorlage nicht gefunden.');
        $logo=$old['logo_path'];
        if($logoFile&&($logoFile['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE)$logo=$this->storeLogo($logoFile,$old['template_code'],$logo);
        $layout=(string)($input['layout']??'standard');if(!in_array($layout,['standard','compact'],true))$layout='standard';
        $data=[
            'header_text'=>$this->nullable($input['header_text']??null),'footer_text'=>$this->nullable($input['footer_text']??null),
            'logo_path'=>$logo,'show_page_numbers'=>!empty($input['show_page_numbers'])?1:0,
            'watermark_text'=>$this->nullable($input['watermark_text']??null),
            'settings_json'=>json_encode(['layout'=>$layout],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)
        ];
        $repo->updateTemplate($id,$data,(int)Auth::id());
    }

    private function storeLogo(array $file,string $code,?string $old): string
    {
        if(($file['error']??UPLOAD_ERR_OK)!==UPLOAD_ERR_OK||!is_uploaded_file((string)($file['tmp_name']??'')))throw new HttpException(422,'Logo konnte nicht hochgeladen werden.');
        if((int)($file['size']??0)>5*1024*1024)throw new HttpException(422,'Logo darf maximal 5 MB groß sein.');
        $mime=(new finfo(FILEINFO_MIME_TYPE))->file((string)$file['tmp_name']);$ext=match($mime){'image/png'=>'png','image/jpeg'=>'jpg',default=>null};
        if(!$ext)throw new HttpException(422,'Logo muss PNG oder JPEG sein.');
        if($mime==='image/png'&&(!function_exists('imagecreatefromstring')||!function_exists('imagejpeg')))throw new HttpException(422,'PNG-Logos benötigen auf diesem Server die PHP-GD-Erweiterung. Bitte JPEG verwenden.');
        $dimensions=@getimagesize((string)$file['tmp_name']);if(!$dimensions||($dimensions[0]??0)<1||($dimensions[1]??0)<1)throw new HttpException(422,'Logo enthält keine gültigen Bilddaten.');
        $dir=BASE_PATH.'/storage/generated/templates';if(!is_dir($dir)&&!mkdir($dir,0770,true)&&!is_dir($dir))throw new \RuntimeException('Vorlagenverzeichnis kann nicht angelegt werden.');
        if($old){$path=realpath(BASE_PATH.'/storage/generated/'.$old);$root=realpath(BASE_PATH.'/storage/generated');if($root&&$path&&str_starts_with($path,$root.DIRECTORY_SEPARATOR)&&is_file($path))@unlink($path);}
        $relative='templates/'.$code.'_'.bin2hex(random_bytes(8)).'.'.$ext;$target=BASE_PATH.'/storage/generated/'.$relative;
        if(!move_uploaded_file((string)$file['tmp_name'],$target))throw new \RuntimeException('Logo konnte nicht gespeichert werden.');
        @chmod($target,0640);return $relative;
    }

    private function nullable(mixed $value): ?string{$value=trim((string)($value??''));return $value===''?null:$value;}
}
