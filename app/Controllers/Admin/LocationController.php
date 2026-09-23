<?php
declare(strict_types=1);

namespace WKS\Controllers\Admin;

use PDOException;
use WKS\Core\Auth;
use WKS\Core\Database;
use WKS\Core\HttpException;
use WKS\Core\Request;
use WKS\Core\Response;
use WKS\Core\View;
use WKS\Repositories\LocationRepository;
use WKS\Services\AuditService;
use WKS\Services\LocationProvisioningService;

final class LocationController
{
    public function index(Request $request): Response
    {
        $locations = (new LocationRepository())->all(true);
        return View::render('admin/locations/index', compact('locations'));
    }

    public function create(Request $request): Response
    {
        return View::render('admin/locations/form', ['location' => null]);
    }

    public function store(Request $request): Response
    {
        return $this->save($request, null);
    }

    public function edit(Request $request, string $id): Response
    {
        $location = (new LocationRepository())->find((int) $id);
        if (!$location) throw new HttpException(404, 'Standort nicht gefunden.');
        return View::render('admin/locations/form', compact('location'));
    }

    public function update(Request $request, string $id): Response
    {
        return $this->save($request, (int) $id);
    }

    private function save(Request $request, ?int $id): Response
    {
        $name = trim((string) $request->post('name'));
        $code = strtoupper(trim((string) $request->post('code')));
        $email = trim((string) $request->post('email_address'));
        $mailMode = (string) $request->post('mail_mode', 'disabled');
        $testAddress = trim((string) $request->post('mail_test_address'));
        $active = $request->post('active') ? 1 : 0;

        if ($name === '' || $code === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Name, Kürzel und gültige Standort-Mailadresse sind erforderlich.');
            return Response::redirect($id === null ? url('admin/locations/create') : url('admin/locations/' . $id . '/edit'));
        }
        if (!in_array($mailMode, ['disabled', 'test', 'production'], true)) {
            $mailMode = 'disabled';
        }
        if ($mailMode === 'test' && !filter_var($testAddress, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Im Testmodus ist eine gültige Testadresse erforderlich.');
            return Response::redirect($id === null ? url('admin/locations/create') : url('admin/locations/' . $id . '/edit'));
        }

        $repo = new LocationRepository();
        $pdo=Database::connection();$ownsTransaction=!$pdo->inTransaction();
        try {
            if($ownsTransaction)$pdo->beginTransaction();
            $payload = [
                'name' => $name,
                'code' => $code,
                'email_address' => $email,
                'mail_mode' => $mailMode,
                'mail_test_address' => $testAddress !== '' ? $testAddress : null,
                'active' => $active,
                'updated_by' => Auth::id(),
            ];

            if ($id === null) {
                $newId = $repo->create($payload + ['created_by' => Auth::id()]);
                $provisioned=(new LocationProvisioningService())->provision($newId,Auth::id());
                (new AuditService())->log('location_created', 'locations', (string) $newId, null, $payload, ['provisioned'=>$provisioned], $request);
            } else {
                $old = $repo->find($id);
                if (!$old) throw new HttpException(404, 'Standort nicht gefunden.');
                $repo->update($id, $payload);
                $provisioned=(new LocationProvisioningService())->provision($id,Auth::id());
                (new AuditService())->log('location_updated', 'locations', (string) $id, $old, $payload, ['provisioned'=>$provisioned], $request);
            }
            if($ownsTransaction)$pdo->commit();
        } catch (PDOException $e) {
            if($ownsTransaction&&$pdo->inTransaction())$pdo->rollBack();
            if((string)$e->getCode()==='23000'){
                flash('error', 'Das Standortkürzel wird bereits verwendet.');
                return Response::redirect($id === null ? url('admin/locations/create') : url('admin/locations/' . $id . '/edit'));
            }
            throw $e;
        } catch (\Throwable $e) {
            if($ownsTransaction&&$pdo->inTransaction())$pdo->rollBack();
            throw $e;
        }

        flash('success', 'Standort wurde gespeichert.');
        return Response::redirect(url('admin/locations'));
    }
}
