<?php
declare(strict_types=1);

namespace WKS\Controllers;

use WKS\Core\Database;
use WKS\Core\MigrationRunner;
use WKS\Core\Request;
use WKS\Core\Response;
use WKS\Core\View;
use WKS\Repositories\UserRepository;
use WKS\Services\InstallerService;
use WKS\Services\SystemStatusService;

final class InstallController
{
    public function show(Request $request): Response
    {
        $installer=new InstallerService();
        if($this->alreadyInstalled($installer))return Response::redirect(url('login'));

        $requirements=$installer->requirements();
        $defaults=$installer->defaults();
        $envConfigured=$installer->envConfigured();
        $connectionOk=false;$error=null;$pending=[];

        if($envConfigured){
            try{
                Database::reset();
                Database::connection()->query('SELECT 1');
                $connectionOk=true;
                $runner=new MigrationRunner();
                $pending=array_map('basename',$runner->pending());
            }catch(\Throwable $e){
                $error=$e->getMessage();
            }
        }

        return View::render('install/index',compact(
            'requirements','defaults','envConfigured','connectionOk','error','pending'
        ));
    }

    public function configure(Request $request): Response
    {
        $installer=new InstallerService();
        if($this->alreadyInstalled($installer))return Response::redirect(url('login'));

        $input=[
            'app_url'=>trim((string)$request->post('app_url','')),
            'app_base_path'=>trim((string)$request->post('app_base_path','')),
            'db_host'=>trim((string)$request->post('db_host','')),
            'db_port'=>trim((string)$request->post('db_port','3306')),
            'db_database'=>trim((string)$request->post('db_database','')),
            'db_username'=>trim((string)$request->post('db_username','')),
            'db_password'=>(string)$request->post('db_password',''),
        ];

        try{
            $installer->configure($input);
            clear_old();
            flash('success','Konfiguration gespeichert und Datenbankverbindung erfolgreich geprüft.');
            $base='/'.trim((string)$input['app_base_path'],'/');
            if($base==='/')$base='';
            return Response::redirect($base.'/install');
        }catch(\Throwable $e){
            $safe=$input;unset($safe['db_password']);set_old($safe);
            flash('error',$e->getMessage());
        }
        return Response::redirect(url('install'));
    }

    public function install(Request $request): Response
    {
        $installer=new InstallerService();
        if($this->alreadyInstalled($installer))return Response::redirect(url('login'));
        if(!$installer->envConfigured()){
            flash('error','Bitte zuerst Anwendung und Datenbank konfigurieren.');
            return Response::redirect(url('install'));
        }

        $firstName=trim((string)$request->post('first_name'));
        $lastName=trim((string)$request->post('last_name'));
        $personnelNumber=trim((string)$request->post('personnel_number'));
        $username=trim((string)$request->post('username'));
        $email=trim((string)$request->post('email'));
        $password=(string)$request->post('password');
        $confirmation=(string)$request->post('password_confirmation');

        $errors=[];
        if($firstName==='')$errors[]='Vorname fehlt.';
        if($lastName==='')$errors[]='Nachname fehlt.';
        if($personnelNumber==='')$errors[]='Personalnummer fehlt.';
        if($username==='')$errors[]='Benutzername fehlt.';
        if(!filter_var($email,FILTER_VALIDATE_EMAIL))$errors[]='Bitte eine gültige E-Mail-Adresse angeben.';
        if(strlen($password)<12)$errors[]='Das Admin-Passwort muss mindestens 12 Zeichen lang sein.';
        if($password!==$confirmation)$errors[]='Die beiden Passwörter stimmen nicht überein.';

        if($errors!==[]){
            set_old([
                'first_name'=>$firstName,'last_name'=>$lastName,'personnel_number'=>$personnelNumber,
                'username'=>$username,'email'=>$email
            ]);
            flash('error',implode(' ',$errors));
            return Response::redirect(url('install'));
        }

        try{
            Database::reset();
            $runner=new MigrationRunner();
            $runner->migrate();

            $users=new UserRepository();
            if($users->count()>0)return Response::redirect(url('login'));

            $pdo=Database::connection();
            $adminRoleId=(int)$pdo->query("SELECT id FROM roles WHERE code='admin' LIMIT 1")->fetchColumn();
            if($adminRoleId<1)throw new \RuntimeException('Admin-Rolle wurde durch die Migrationen nicht angelegt.');

            $locations=array_map('intval',$pdo->query('SELECT id FROM locations WHERE active=1 ORDER BY id')->fetchAll(\PDO::FETCH_COLUMN));
            if($locations===[])throw new \RuntimeException('Es wurden keine aktiven Standorte angelegt.');

            $adminId=$users->create([
                'first_name'=>$firstName,
                'last_name'=>$lastName,
                'personnel_number'=>$personnelNumber,
                'username'=>$username,
                'email'=>$email,
                'password_hash'=>password_hash($password,PASSWORD_DEFAULT),
                'role_id'=>$adminRoleId,
                'status'=>'active',
                'must_change_password'=>0,
                'first_login'=>0,
                'theme'=>'light',
                'created_by'=>null,
                'updated_by'=>null,
            ],$locations);

            // Testphase sicher erzwingen: keinerlei betriebliche Mailausgabe direkt nach Installation.
            $pdo->exec("UPDATE locations SET mail_mode='disabled',mail_test_address=NULL");
            $pdo->prepare(
                'UPDATE settings SET setting_value="0",value_type="bool",updated_at=NOW(),updated_by=:user_id
                 WHERE setting_key="mail.global_enabled"'
            )->execute(['user_id'=>$adminId]);

            $systemCheck=(new SystemStatusService())->check();
            $installer->markInstalled($adminId,$systemCheck);

            clear_old();
            if(($systemCheck['overall']??'error')==='error'){
                flash('warning','WKS wurde installiert. Der Systemcheck enthält noch Fehler. Bitte nach dem Login den Systemstatus öffnen.');
            }elseif(($systemCheck['overall']??'warning')==='warning'){
                flash('success','WKS wurde installiert. Der Systemcheck enthält Hinweise; diese können Sie nach dem Login im Systemstatus prüfen.');
            }else{
                flash('success','WKS wurde vollständig eingerichtet und der abschließende Systemcheck war erfolgreich.');
            }
            return Response::redirect(url('login'));
        }catch(\Throwable $e){
            set_old([
                'first_name'=>$firstName,'last_name'=>$lastName,'personnel_number'=>$personnelNumber,
                'username'=>$username,'email'=>$email
            ]);
            flash('error','Installation konnte nicht abgeschlossen werden: '.$e->getMessage());
            return Response::redirect(url('install'));
        }
    }

    private function alreadyInstalled(InstallerService $installer): bool
    {
        if($installer->isInstalled())return true;
        if(!$installer->envConfigured())return false;

        try{
            Database::reset();
            if($this->usersTableExists()&&(new UserRepository())->count()>0)return true;
        }catch(\Throwable){
            return false;
        }
        return false;
    }

    private function usersTableExists(): bool
    {
        $stmt=Database::connection()->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=:table'
        );
        $stmt->execute(['table'=>'users']);
        return (bool)$stmt->fetchColumn();
    }
}
