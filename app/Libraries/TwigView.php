<?php

declare(strict_types=1);

namespace App\Libraries;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

final class TwigView
{
    private Environment $twig;

    public function __construct()
    {
        helper(['url', 'form', 'authorization']);

        $loader = new FilesystemLoader(APPPATH . 'Views');
        $cache = false;

        if (ENVIRONMENT === 'production') {
            $cachePath = WRITEPATH . 'cache' . DIRECTORY_SEPARATOR . 'twig';

            if (is_dir($cachePath) || @mkdir($cachePath, 0775, true)) {
                $cache = $cachePath;
            }
        }

        $this->twig = new Environment($loader, [
            'cache' => $cache,
            'auto_reload' => ENVIRONMENT !== 'production',
            'autoescape' => 'html',
            'strict_variables' => false,
        ]);

        /** @var \Config\SystemSettings $settings */
        $settings = config(\Config\SystemSettings::class);

        /** @var \App\Modules\Authentication\Domain\Services\PasswordPolicy $passwordPolicy */
        $passwordPolicy = service('passwordPolicy');

        $this->twig->addGlobal('authPasswordMinLength', $settings->passwordMinLength);
        $this->twig->addGlobal('authPasswordMaxLength', $settings->passwordMaxLength);
        $this->twig->addGlobal('authPasswordPolicyHelp', $passwordPolicy->description());
        $this->twig->addGlobal('authUsernameMinLength', $settings->usernameMinLength);
        $this->twig->addGlobal('authUsernameMaxLength', $settings->usernameMaxLength);
        $this->twig->addGlobal('authEmailMaxLength', $settings->emailMaxLength);
        $this->twig->addGlobal('authLoginIdentifierMaxLength', $settings->loginIdentifierMaxLength());
        $this->twig->addGlobal('authPasswordVerificationMaxLength', $settings->passwordVerificationMaxLength);
        $this->twig->addGlobal('authPaths', [
            'login' => $settings->routePath($settings->loginPath),
            'dashboard' => $settings->routePath($settings->dashboardPath),
            'logout' => $settings->routePath($settings->logoutPath),
            'password' => $settings->routePath($settings->changePasswordPath),
        ]);
        $this->twig->addGlobal('ciVersion', \CodeIgniter\CodeIgniter::CI_VERSION);
        $this->twig->addGlobal('environment', ENVIRONMENT);

        $this->registerFunctions();
    }

    public function render(string $template, array $data = []): string
    {
        if (! str_ends_with($template, '.twig')) {
            $template .= '.html.twig';
        }

        return $this->twig->render($template, $data);
    }

    private function registerFunctions(): void
    {
        $this->twig->addFunction(new TwigFunction('site_url', 'site_url'));
        $this->twig->addFunction(new TwigFunction('base_url', 'base_url'));
        $this->twig->addFunction(new TwigFunction('current_url', 'current_url'));
        $this->twig->addFunction(new TwigFunction('auth_can', 'auth_can'));

        $this->twig->addFunction(new TwigFunction(
            'csrf_field',
            'csrf_field',
            ['is_safe' => ['html']],
        ));

        $this->twig->addFunction(new TwigFunction(
            'session_get',
            static fn (string $key): mixed => service('session')->get($key),
        ));

        $this->twig->addFunction(new TwigFunction(
            'session_has',
            static fn (string $key): bool => service('session')->has($key),
        ));

        $this->twig->addFunction(new TwigFunction(
            'flash',
            static fn (string $key): mixed => service('session')->getFlashdata($key),
        ));

        $this->twig->addFunction(new TwigFunction(
            'is_active',
            static function (string $path, bool $exact = false): string {
                $currentPath = trim(service('uri')->getPath(), '/');
                $path = trim($path, '/');

                $active = $exact
                    ? $currentPath === $path
                    : ($currentPath === $path || str_starts_with($currentPath, $path . '/'));

                return $active ? ' is-active' : '';
            },
        ));

        $this->twig->addFunction(new TwigFunction(
            'asset_url',
            static function (string $path): string {
                $path = ltrim($path, '/');
                $file = FCPATH . $path;
                $version = is_file($file) ? (string) filemtime($file) : '1';

                return base_url($path) . '?v=' . rawurlencode($version);
            },
        ));
    }
}
