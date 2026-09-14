<?php
declare(strict_types=1);

namespace JFS;

use RuntimeException;

final class View
{
    public function __construct(private readonly string $basePath)
    {
    }

    public function render(string $template, array $data = [], string $layout = 'layouts/app'): string
    {
        $content = $this->capture($template, $data);
        return $this->capture($layout, array_merge($data, ['content' => $content]));
    }

    public function partial(string $template, array $data = []): string
    {
        return $this->capture($template, $data);
    }

    private function capture(string $template, array $data): string
    {
        $path = $this->basePath . '/' . $template . '.php';
        if (!is_file($path)) {
            throw new RuntimeException('View nicht gefunden: ' . $template);
        }
        extract($data, EXTR_SKIP);
        ob_start();
        require $path;
        return (string) ob_get_clean();
    }
}
