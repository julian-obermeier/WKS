<?php
declare(strict_types=1);

use PDO;

return static function (PDO $pdo): void {
    $stmt=$pdo->prepare(
        'INSERT INTO document_templates
         (template_code,template_name,header_text,footer_text,show_page_numbers,watermark_text,created_at,updated_at)
         VALUES ("statistics","Statistik","WKS · Statistik","",1,"VERTRAULICH",NOW(),NOW())
         ON DUPLICATE KEY UPDATE template_name=VALUES(template_name)'
    );
    $stmt->execute();
};
