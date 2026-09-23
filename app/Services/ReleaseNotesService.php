<?php
declare(strict_types=1);

namespace WKS\Services;

use WKS\Repositories\UpdateRepository;

final class ReleaseNotesService
{
    public function syncLocal(): ?array
    {
        $path=BASE_PATH.'/CHANGELOG.json';if(!is_file($path))return null;
        $data=json_decode((string)file_get_contents($path),true);
        if(!is_array($data)||empty($data['version'])||empty($data['build_date'])||empty($data['title'])||!is_array($data['sections']??null))return null;
        (new UpdateRepository())->upsertRelease($data);return $data;
    }

    public function latestUnseen(int $userId): ?array
    {
        $this->syncLocal();return (new UpdateRepository())->latestUnseen($userId);
    }

    public function all(): array
    {
        $this->syncLocal();return (new UpdateRepository())->releaseNotes();
    }

    public function markSeen(int $userId,string $version): void
    {
        (new UpdateRepository())->markSeen($userId,$version);
    }
}
