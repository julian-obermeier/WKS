<?php
declare(strict_types=1);

namespace WKS\Controllers\Admin;

use PDOException;
use WKS\Core\Auth;
use WKS\Core\HttpException;
use WKS\Core\Request;
use WKS\Core\Response;
use WKS\Core\View;
use WKS\Repositories\LocationRepository;
use WKS\Repositories\RoleRepository;
use WKS\Repositories\UserRepository;
use WKS\Services\AuditService;
use WKS\Services\SessionService;

final class UserController
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('q', ''));
        $page = max(1, (int) $request->query('page', 1));
        $users = (new UserRepository())->paginate($search, $page);
        return View::render('admin/users/index', compact('users', 'search'));
    }

    public function create(Request $request): Response
    {
        return View::render('admin/users/form', [
            'user' => null,
            'roles' => (new RoleRepository())->all(),
            'locations' => (new LocationRepository())->all(false),
            'selectedLocations' => [],
        ]);
    }

    public function store(Request $request): Response
    {
        return $this->save($request, null);
    }

    public function edit(Request $request, string $id): Response
    {
        $userId = (int) $id;
        $user = (new UserRepository())->findById($userId);
        if (!$user) {
            throw new HttpException(404, 'Benutzer nicht gefunden.');
        }

        return View::render('admin/users/form', [
            'user' => $user,
            'roles' => (new RoleRepository())->all(),
            'locations' => (new LocationRepository())->all(false),
            'selectedLocations' => (new UserRepository())->locationIds($userId),
        ]);
    }

    public function update(Request $request, string $id): Response
    {
        return $this->save($request, (int) $id);
    }

    public function status(Request $request, string $id): Response
    {
        $userId = (int) $id;
        $status = (string) $request->post('status');
        if (!in_array($status, ['active', 'locked', 'disabled'], true)) {
            throw new HttpException(422, 'Ungültiger Kontostatus.');
        }
        if ($userId === Auth::id() && $status !== 'active') {
            throw new HttpException(422, 'Das eigene Administratorkonto kann nicht auf diesem Weg gesperrt oder deaktiviert werden.');
        }

        $repo = new UserRepository();
        $old = $repo->findById($userId);
        if (!$old) {
            throw new HttpException(404, 'Benutzer nicht gefunden.');
        }

        $repo->setStatus($userId, $status, (int) Auth::id());
        if ($status !== 'active') {
            (new SessionService())->revokeAllForUser($userId);
        }
        (new AuditService())->log('user_status_changed', 'users', (string) $userId, ['status' => $old['status']], ['status' => $status], [], $request);
        flash('success', 'Kontostatus wurde aktualisiert.');
        return Response::redirect(url('admin/users'));
    }

    public function resetPassword(Request $request, string $id): Response
    {
        $password = (string) $request->post('temporary_password');
        if (strlen($password) < 12) {
            flash('error', 'Das temporäre Passwort muss mindestens 12 Zeichen lang sein.');
            return Response::redirect(url('admin/users/' . (int) $id . '/edit'));
        }

        (new UserRepository())->resetPassword((int) $id, password_hash($password, PASSWORD_DEFAULT), (int) Auth::id());
        (new SessionService())->revokeAllForUser((int) $id);
        (new AuditService())->log('password_reset_by_admin', 'users', (string) (int) $id, null, null, [], $request);
        flash('success', 'Passwort wurde zurückgesetzt. Beim nächsten Login ist ein Passwortwechsel erforderlich.');
        return Response::redirect(url('admin/users/' . (int) $id . '/edit'));
    }

    public function revokeSessions(Request $request, string $id): Response
    {
        (new SessionService())->revokeAllForUser((int) $id);
        (new AuditService())->log('sessions_revoked', 'users', (string) (int) $id, null, null, [], $request);
        flash('success', 'Alle aktiven Sitzungen des Benutzers wurden beendet.');
        return Response::redirect(url('admin/users/' . (int) $id . '/edit'));
    }

    private function save(Request $request, ?int $id): Response
    {
        $firstName = trim((string) $request->post('first_name'));
        $lastName = trim((string) $request->post('last_name'));
        $personnelNumber = trim((string) $request->post('personnel_number'));
        $username = trim((string) $request->post('username'));
        $email = trim((string) $request->post('email'));
        $roleId = (int) $request->post('role_id');
        $locations = array_values(array_filter(array_map('intval', (array) $request->post('location_ids', []))));
        $temporaryPassword = (string) $request->post('temporary_password');

        $errors = [];
        if ($firstName === '' || $lastName === '' || $personnelNumber === '' || $username === '') $errors[] = 'Bitte füllen Sie alle Pflichtfelder aus.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Die E-Mail-Adresse ist ungültig.';
        if ($roleId < 1) $errors[] = 'Bitte wählen Sie eine Rolle.';
        if ($locations === []) $errors[] = 'Mindestens ein Standort muss zugeordnet werden.';
        if ($id === null && strlen($temporaryPassword) < 12) $errors[] = 'Das temporäre Passwort muss mindestens 12 Zeichen lang sein.';

        if ($errors !== []) {
            set_old($request->all());
            flash('error', implode(' ', $errors));
            return Response::redirect($id === null ? url('admin/users/create') : url('admin/users/' . $id . '/edit'));
        }

        $repo = new UserRepository();

        try {
            if ($id === null) {
                $newId = $repo->create([
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'personnel_number' => $personnelNumber,
                    'username' => $username,
                    'email' => $email,
                    'password_hash' => password_hash($temporaryPassword, PASSWORD_DEFAULT),
                    'role_id' => $roleId,
                    'status' => 'active',
                    'must_change_password' => 1,
                    'first_login' => 1,
                    'theme' => 'light',
                    'created_by' => Auth::id(),
                    'updated_by' => Auth::id(),
                ], $locations);

                (new AuditService())->log('user_created', 'users', (string) $newId, null, [
                    'username' => $username,
                    'role_id' => $roleId,
                    'location_ids' => $locations,
                ], [], $request);
                flash('success', 'Benutzer wurde angelegt.');
            } else {
                $old = $repo->findById($id);
                if (!$old) throw new HttpException(404, 'Benutzer nicht gefunden.');

                $repo->update($id, [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'personnel_number' => $personnelNumber,
                    'username' => $username,
                    'email' => $email,
                    'role_id' => $roleId,
                    'updated_by' => Auth::id(),
                ], $locations);

                (new AuditService())->log('user_updated', 'users', (string) $id, [
                    'first_name' => $old['first_name'],
                    'last_name' => $old['last_name'],
                    'personnel_number' => $old['personnel_number'],
                    'username' => $old['username'],
                    'email' => $old['email'],
                    'role_id' => $old['role_id'],
                    'location_ids' => $repo->locationIds($id),
                ], [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'personnel_number' => $personnelNumber,
                    'username' => $username,
                    'email' => $email,
                    'role_id' => $roleId,
                    'location_ids' => $locations,
                ], [], $request);
                flash('success', 'Benutzer wurde aktualisiert.');
            }
        } catch (PDOException) {
            flash('error', 'Benutzername, Personalnummer oder E-Mail-Adresse wird bereits verwendet.');
            return Response::redirect($id === null ? url('admin/users/create') : url('admin/users/' . $id . '/edit'));
        }

        clear_old();
        return Response::redirect(url('admin/users'));
    }
}
