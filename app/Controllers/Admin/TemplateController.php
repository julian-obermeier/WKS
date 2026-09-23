<?php
declare(strict_types=1);

namespace WKS\Controllers\Admin;

use WKS\Core\HttpException;
use WKS\Core\Request;
use WKS\Core\Response;
use WKS\Core\View;
use WKS\Repositories\AdminRepository;
use WKS\Services\AuditService;
use WKS\Services\DocumentGeneratorService;
use WKS\Services\TemplateService;

final class TemplateController
{
    public function index(Request $request): Response
    {
        $templates=(new AdminRepository())->templates();return View::render('admin/templates/index',compact('templates'));
    }

    public function update(Request $request,string $id): Response
    {
        (new TemplateService())->update((int)$id,$request->all(),$request->file('logo'));
        (new AuditService())->log('document_template_updated','templates',$id,null,$request->all(),[],$request);
        flash('success','Dokumentvorlage wurde gespeichert.');return Response::redirect(url('admin/templates'));
    }

    public function preview(Request $request,string $id): Response
    {
        $template=(new AdminRepository())->template((int)$id);if(!$template)throw new HttpException(404,'Vorlage nicht gefunden.');
        $pdf=(new DocumentGeneratorService())->pdfForTemplate($template['template_code'],'Vorschau · '.$template['template_name'],[
            'Beispielabschnitt'=>'Dies ist eine Vorschau der Kopf-/Fußzeilen- und Wasserzeicheneinstellungen.'
        ]);
        return new Response($pdf,200,['Content-Type'=>'application/pdf','Content-Disposition'=>'inline; filename="Vorschau.pdf"']);
    }
}
