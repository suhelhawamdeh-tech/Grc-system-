<?php
declare(strict_types=1);

namespace App\Core;

final class View
{
    public static function partial(string $view, array $data = []): string
    {
        $file = APP_ROOT . '/app/views/' . $view . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException('View not found: ' . $view);
        }
        return (static function (string $__file, array $__data): string {
            extract($__data, EXTR_SKIP);
            ob_start();
            try {
                require $__file;
            } catch (\Throwable $e) {
                ob_end_clean();
                throw $e;
            }
            return (string)ob_get_clean();
        })($file, $data);
    }

    public static function render(string $view, array $data = [], string $layout = 'layout/main'): string
    {
        $content = self::partial($view, $data);
        if ($layout === '') {
            return $content;
        }
        return self::partial($layout, $data + ['content' => $content]);
    }
}
