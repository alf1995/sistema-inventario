<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class SystemSettings extends BaseConfig
{
    // ---------------------------------------------------------------------
    // Autenticación y sesión de aplicación
    // ---------------------------------------------------------------------

    /** Segundos de inactividad antes de considerar vencida una sesión. */
    public int $inactivityTimeout = 3600;

    /** Máximo de sesiones activas simultáneas permitidas por usuario. */
    public int $maxActiveSessions = 3;

    /** Intentos fallidos permitidos para un mismo usuario/email. */
    public int $loginUserAttempts = 5;

    /** Intentos fallidos permitidos desde una misma IP. */
    public int $loginIpAttempts = 10;

    /** Ventana, en segundos, usada para contabilizar intentos de login. */
    public int $loginAttemptWindow = 300;

    /** Intentos fallidos permitidos al confirmar acciones sensibles. */
    public int $sensitiveActionAttempts = 5;

    /** Ventana, en segundos, para acciones sensibles. */
    public int $sensitiveActionWindow = 900;

    /**
     * Longitud máxima aceptada al verificar una contraseña existente.
     *
     * Se usa en login y confirmaciones de contraseña actual. Es
     * deliberadamente mayor que passwordMaxLength para no impedir la
     * verificación de credenciales antiguas creadas con otra política.
     */
    public int $passwordVerificationMaxLength = 4096;

    // ---------------------------------------------------------------------
    // Rutas principales de autenticación
    // ---------------------------------------------------------------------

    public string $loginPath = '/login';
    public string $dashboardPath = '/dashboard';
    public string $logoutPath = '/logout';
    public string $changePasswordPath = '/account/password';

    // ---------------------------------------------------------------------
    // Cuenta de usuario
    // ---------------------------------------------------------------------

    public int $usernameMinLength = 3;
    public int $usernameMaxLength = 100;
    public int $emailMaxLength = 191;

    // ---------------------------------------------------------------------
    // Política de contraseñas
    // ---------------------------------------------------------------------

    public int $passwordMinLength = 6;
    public int $passwordMaxLength = 128;
    public bool $passwordRequireUppercase = false;
    public bool $passwordRequireLowercase = false;
    public bool $passwordRequireNumber = false;
    public bool $passwordRequireSpecial = false;

    // ---------------------------------------------------------------------
    // Paginación
    // ---------------------------------------------------------------------

    /** Valor usado cuando una pantalla no tiene configuración específica. */
    public int $defaultRowsPerPage = 25;

    /**
     * Filas por página para las tablas paginadas del sistema.
     *
     * @var array<string, int>
     */
    public array $pagination = [
        'products'       => 25,
        'inventory'      => 30,
        'kardex'         => 50,
        'movements'      => 25,
        'report_history' => 25,
    ];

    // ---------------------------------------------------------------------
    // Búsquedas y listados auxiliares
    // ---------------------------------------------------------------------

    /** Máximo de productos sugeridos por consulta en el buscador de movimientos. */
    public int $productSearchLimit = 30;

    /** Caracteres mínimos antes de consultar productos al servidor. */
    public int $productSearchMinChars = 2;

    /** Máximo de caracteres aceptados por la búsqueda de productos. */
    public int $productSearchMaxLength = 100;

    /** Espera, en milisegundos, antes de consultar mientras el usuario escribe. */
    public int $productSearchDebounceMs = 300;

    /** Tiempo de caché en navegador para una misma consulta de productos. */
    public int $productSearchCacheTtlMs = 15000;

    /**
     * Si no alcanza el límite con coincidencias por prefijo, permite buscar
     * el texto dentro del nombre. Puede desactivarse en catálogos masivos.
     */
    public bool $productSearchContainsFallback = true;

    /** Máximo de caracteres del texto libre en el filtro de reportes. */
    public int $reportSearchMaxLength = 100;

    // ---------------------------------------------------------------------
    // Reportes
    // ---------------------------------------------------------------------

    /** Máximo de movimientos mostrados en la vista previa del reporte. */
    public int $reportPreviewLimit = 100;

    // ---------------------------------------------------------------------
    // Dashboard
    // ---------------------------------------------------------------------

    /** Cantidad de movimientos recientes mostrados en el dashboard. */
    public int $dashboardRecentMovementsLimit = 6;

    /** Cantidad de productos con mayor stock mostrados en el dashboard. */
    public int $dashboardTopProductsLimit = 5;

    /**
     * Longitud máxima aceptada por el campo usuario/email del login.
     *
     * Se calcula desde los límites reales de username y email para evitar
     * tener otro "191" duplicado en la configuración.
     */
    public function loginIdentifierMaxLength(): int
    {
        return max($this->usernameMaxLength, $this->emailMaxLength);
    }

    public function pageSize(string $key): int
    {
        $configured = (int) ($this->pagination[$key] ?? $this->defaultRowsPerPage);

        return $configured > 0 ? $configured : $this->defaultRowsPerPage;
    }

    /**
     * Devuelve una ruta sin slash inicial/final, formato requerido por Routes.php
     * y útil para comparar el path actual.
     */
    public function routePath(string $path): string
    {
        return trim($path, '/');
    }
}
