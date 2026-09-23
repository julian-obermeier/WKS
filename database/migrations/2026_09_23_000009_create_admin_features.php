<?php
declare(strict_types=1);

use PDO;

return static function (PDO $pdo): void {
    $sql=[
        "CREATE TABLE IF NOT EXISTS error_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            module VARCHAR(100) NULL,
            user_id BIGINT UNSIGNED NULL,
            message LONGTEXT NOT NULL,
            technical_details LONGTEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'open',
            resolved_by BIGINT UNSIGNED NULL,
            resolved_at DATETIME NULL,
            CONSTRAINT fk_error_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            CONSTRAINT fk_error_logs_resolved FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_error_logs_status (status,occurred_at),
            INDEX idx_error_logs_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS cron_jobs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            job_code VARCHAR(100) NOT NULL UNIQUE,
            job_name VARCHAR(180) NOT NULL,
            interval_minutes INT UNSIGNED NOT NULL DEFAULT 5,
            active TINYINT(1) NOT NULL DEFAULT 1,
            last_run_at DATETIME NULL,
            last_success_at DATETIME NULL,
            next_run_at DATETIME NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'never',
            last_error LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS mail_queue (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            location_id BIGINT UNSIGNED NOT NULL,
            trigger_code VARCHAR(120) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            body LONGTEXT NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'pending',
            attempts INT UNSIGNED NOT NULL DEFAULT 0,
            available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            sent_at DATETIME NULL,
            last_error LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_mail_queue_location FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE,
            INDEX idx_mail_queue_status (status,available_at),
            INDEX idx_mail_queue_location (location_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS document_templates (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            template_code VARCHAR(100) NOT NULL UNIQUE,
            template_name VARCHAR(180) NOT NULL,
            header_text TEXT NULL,
            footer_text TEXT NULL,
            logo_path VARCHAR(255) NULL,
            show_page_numbers TINYINT(1) NOT NULL DEFAULT 1,
            watermark_text VARCHAR(100) NULL,
            settings_json LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_by BIGINT UNSIGNED NULL,
            CONSTRAINT fk_document_templates_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];
    foreach($sql as $statement)$pdo->exec($statement);

    $job=$pdo->prepare(
        'INSERT INTO cron_jobs (job_code,job_name,interval_minutes,active,status,created_at,updated_at)
         VALUES (:code,:name,:minutes,1,"never",NOW(),NOW())
         ON DUPLICATE KEY UPDATE job_name=VALUES(job_name),interval_minutes=VALUES(interval_minutes)'
    );
    foreach([
        ['announcements','Geplante Mitteilungen / Archivierung',1],
        ['notifications','Interne Benachrichtigungen',60],
        ['mail_queue','E-Mail-Versand',5],
        ['maintenance','Technische Wartung',60],
        ['system_status','Systemstatusprüfung',60],
    ] as [$code,$name,$minutes])$job->execute(['code'=>$code,'name'=>$name,'minutes'=>$minutes]);

    $template=$pdo->prepare(
        'INSERT INTO document_templates
         (template_code,template_name,header_text,footer_text,show_page_numbers,watermark_text,created_at,updated_at)
         VALUES (:code,:name,:header,:footer,1,:watermark,NOW(),NOW())
         ON DUPLICATE KEY UPDATE template_name=VALUES(template_name)'
    );
    foreach([
        ['dutybook','Dienstbuch','WKS · Tagesdienstbuch','','VERTRAULICH'],
        ['special_report','Sonderbericht','WKS · Sonderbericht','','VERTRAULICH'],
        ['house_bans','Hausverbotsliste','WKS · Hausverbote','','VERTRAULICH'],
        ['valuables','Wertsachen','WKS · Wertsachenverwahrung','','VERTRAULICH'],
    ] as [$code,$name,$header,$footer,$watermark])$template->execute(['code'=>$code,'name'=>$name,'header'=>$header,'footer'=>$footer,'watermark'=>$watermark]);

    $setting=$pdo->prepare(
        'INSERT INTO settings (setting_key,setting_value,value_type,created_at,updated_at)
         VALUES (:key,:value,:type,NOW(),NOW()) ON DUPLICATE KEY UPDATE setting_key=setting_key'
    );
    foreach([
        ['system.maintenance_mode','0','bool'],
        ['mail.global_enabled','0','bool'],
        ['mail.from_name','WKS','string'],
        ['mail.from_address','wks@localhost','string']
    ] as [$key,$value,$type])$setting->execute(['key'=>$key,'value'=>$value,'type'=>$type]);
};
