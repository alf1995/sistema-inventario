<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * BaseController provides a convenient place for loading components
 * and performing functions that are needed by all your controllers.
 *
 * Extend this class in any new controllers:
 * ```
 *     class Home extends BaseController
 * ```
 *
 * For security, be sure to declare any new methods as protected or private.
 */
abstract class BaseController extends Controller
{
    /**
     * Be sure to declare properties for any property fetch you initialized.
     * The creation of dynamic property is deprecated in PHP 8.2.
     */

    // protected $session;

    /**
     * @return void
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Load here all helpers you want to be available in your controllers that extend BaseController.
        // Caution: Do not put the this below the parent::initController() call below.
        // $this->helpers = ['form', 'url'];

        // Caution: Do not edit this line.
        parent::initController($request, $response, $logger);

        // Preload any models, libraries, etc, here.
        // $this->session = service('session');
    }
    /**
     * Renderiza una plantilla Twig ubicada en app/Views.
     *
     * Puedes pasar "auth/login" o "auth/login.html.twig".
     */
    protected function renderTwig(string $template, array $data = []): string
    {
        /** @var \App\Libraries\TwigView $twig */
        $twig = service('twig');

        return $twig->render($template, $data);
    }

    /**
     * Calcula el rango visible y el total de un grupo de paginación.
     *
     * @return array{from:int,to:int,total:int}
     */
    protected function paginationMeta(object $pager, string $group): array
    {
        /** @var array<string, mixed> $details */
        $details = $pager->getDetails($group);

        $currentPage = max(1, (int) ($details['currentPage'] ?? 1));
        $perPage = max(1, (int) ($details['perPage'] ?? 1));
        $total = max(0, (int) ($details['total'] ?? 0));

        if ($total === 0) {
            return ['from' => 0, 'to' => 0, 'total' => 0];
        }

        $from = (($currentPage - 1) * $perPage) + 1;
        $to = min($currentPage * $perPage, $total);

        return ['from' => $from, 'to' => $to, 'total' => $total];
    }

}
