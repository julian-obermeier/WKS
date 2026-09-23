<?php
declare(strict_types=1);

namespace WKS\Core;

final class View
{
    public static function render(string $view, array $data = [], int $status = 200, ?string $layout = 'layout'): Response
    {
        $viewFile = BASE_PATH . '/resources/views/' . $view . '.php';
        if (!is_file($viewFile)) {
            throw new \RuntimeException('View nicht gefunden: ' . $view);
        }

        if ($layout === 'layout' && Auth::check() && !array_key_exists('whatsNew', $data)) {
            try {
                $data['whatsNew'] = (new \WKS\Services\ReleaseNotesService())->latestUnseen((int) Auth::id());
            } catch (\Throwable) {
                $data['whatsNew'] = null;
            }
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $viewFile;
        $content = (string) ob_get_clean();

        if ($layout !== null) {
            $layoutFile = BASE_PATH . '/resources/views/' . $layout . '.php';
            if (!is_file($layoutFile)) {
                throw new \RuntimeException('Layout nicht gefunden: ' . $layout);
            }

            ob_start();
            require $layoutFile;
            $content = (string) ob_get_clean();
        }

        return new Response($content, $status);
    }
}
