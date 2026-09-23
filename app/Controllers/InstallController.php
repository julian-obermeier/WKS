<?php
declare(strict_types=1);

namespace WKS\Controllers;

use WKS\Core\Database;
use WKS\Core\MigrationRunner;
use WKS\Core\Request;
use WKS\Core\Response;
use WKS\Core\View;
use WKS\Repositories\UserRepository;

final class InstallController
{
    public function show(Request $request): Response
    {
        $connectionOk = true;
        $error = null;
        $pending = [];

        try {
            Database::connection();
            $runner = new MigrationRunner();
            $pending = array_map('basename', $runner->pending());

            if ($this->usersTableExists() && (new UserRepository())->count() > 0) {
                return Response::redirect(url('login'));
            }
        } catch (\Throwable $e) {
            $connectionOk = false;
            $error = $e->getMessage();
        }

        return View::render('install/index', compact('connectionOk', 'error', 'pending'));
    }

    public function install(Request $request): Response
    {
        $firstName = trim((string) $request->post('first_name'));
        $lastName = trim((string) $request->post('last_name'));
        $personnelNumber = trim((string) $request->post('personnel_number'));
        $username = trim((string) $request->post('username'));
        $email = trim((string) $request->post('email'));
        $password = (string) $request->post('password');

        $errors = [];
        if ($firstName === '') $errors[] = 'Vorname fehlt.';
        if ($lastName === '') $errors[] = 'Nachname fehlt.';
        if ($personnelNumber === '') $errors[] = 'Personalnummer fehlt.';
        if ($username === '') $errors[] = 'Benutzername fehlt.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Bitte geben Sie eine gültige E-Mail-Adresse ein.';
        if (strlen($password) < 12) $errors[] = 'Das Admin-Passwort muss mindestens 12 Zeichen lang sein.';

        if ($errors !== []) {
            set_old($request->all());
            flash('error', implode(' ', $errors));
            return Response::redirect(url('install'));
        }

        $runner = new MigrationRunner();
        $runner->migrate();

        $users = new UserRepository();
        if ($users->count() > 0) {
            return Response::redirect(url('login'));
        }

        $pdo = Database::connection();
        $adminRoleId = (int) $pdo->query("SELECT id FROM roles WHERE code = 'admin' LIMIT 1")->fetchColumn();
        $locations = array_map('intval', $pdo->query('SELECT id FROM locations WHERE active = 1 ORDER BY id')->fetchAll(\PDO::FETCH_COLUMN));

        $users->create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'personnel_number' => $personnelNumber,
            'username' => $username,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role_id' => $adminRoleId,
            'status' => 'active',
            'must_change_password' => 0,
            'first_login' => 0,
            'theme' => 'light',
            'created_by' => null,
            'updated_by' => null,
        ], $locations);

        clear_old();
        flash('success', 'WKS wurde eingerichtet. Sie können sich jetzt anmelden.');
        return Response::redirect(url('login'));
    }

    private function usersTableExists(): bool
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table'
        );
        $stmt->execute(['table' => 'users']);
        return (bool) $stmt->fetchColumn();
    }
}
