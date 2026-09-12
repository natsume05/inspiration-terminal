<?php

declare(strict_types=1);

namespace App\Http;

use RuntimeException;

/**
 * Renders PHP templates inside a layout.
 *
 * Escaping is provided by {@see self::escape()} so templates have an obvious,
 * short way to be correct. The previous pages echoed values directly and relied
 * on remembering to wrap each one, which is how output-escaping gaps appear.
 */
final class View
{
    /**
     * @param string $templateDirectory Absolute path to the templates.
     * @param array<string, mixed> $shared Values available to every template.
     */
    public function __construct(
        private readonly string $templateDirectory,
        private array $shared = [],
    ) {
    }

    /**
     * Add a value available to every template, such as the current user.
     *
     * @param string $key Variable name.
     * @param mixed $value Value to expose.
     * @return void
     */
    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    /**
     * Render a template inside the main layout.
     *
     * @param string $template Template name without the extension.
     * @param array<string, mixed> $data Values for this template.
     * @param int $status HTTP status code.
     * @return Response The rendered HTML response.
     */
    public function render(string $template, array $data = [], int $status = 200): Response
    {
        $content = $this->renderTemplate($template, $data);

        // The child template's markup is named `renderedContent` rather than the
        // more obvious `content`, so it cannot be confused with a value that
        // needs escaping. It is the one intentional raw output in the project,
        // and tools/lint.php recognises the name for that reason.
        $layout = $this->renderTemplate('layout', [...$this->shared, ...$data, 'renderedContent' => $content]);

        return Response::html($layout, $status);
    }

    /**
     * Render a template without the layout, for fragments and partials.
     *
     * @param string $template Template name without the extension.
     * @param array<string, mixed> $data Values for this template.
     * @return string The rendered markup.
     */
    public function renderTemplate(string $template, array $data = []): string
    {
        $path = $this->templateDirectory . '/' . $template . '.php';

        if (!is_file($path)) {
            throw new RuntimeException(sprintf('Template not found: %s', $template));
        }

        // Extract into a local scope so templates read like plain PHP variables
        // while never receiving $this or $path.
        $variables = [...$this->shared, ...$data];

        $render = static function (string $__path, array $__variables): string {
            extract($__variables, EXTR_SKIP);
            ob_start();

            try {
                require $__path;
            } catch (\Throwable $throwable) {
                ob_end_clean();

                throw $throwable;
            }

            return (string) ob_get_clean();
        };

        return $render($path, $variables);
    }

    /**
     * Escape a value for HTML output.
     *
     * @param mixed $value Value to escape.
     * @return string Escaped text safe to print inside HTML.
     */
    public static function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Escape a value for use inside a JavaScript string literal.
     *
     * @param mixed $value Value to encode.
     * @return string JSON-encoded text safe to embed in a script block.
     */
    public static function json(mixed $value): string
    {
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        return $encoded === false ? 'null' : $encoded;
    }
}
