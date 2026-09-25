<?php

namespace Config;

use App\Modules\Administration\Application\Services\ModuleAdministrationService;
use App\Modules\Administration\Application\Services\RoleAdministrationService;
use App\Modules\Administration\Application\Services\UserAdministrationService;
use App\Modules\Administration\Infrastructure\Persistence\DatabaseModuleAdministrationRepository;
use App\Modules\Administration\Infrastructure\Persistence\DatabaseRoleAdministrationRepository;
use App\Modules\Administration\Infrastructure\Persistence\DatabaseUserAdministrationRepository;
use App\Modules\Administration\Infrastructure\Security\DatabaseSecurityAuditLogger;
use App\Modules\Authentication\Application\Services\AccountPasswordService;
use App\Modules\Authentication\Application\Services\AuthenticationService;
use App\Modules\Authentication\Application\Services\AuthorizationService;
use App\Modules\Authentication\Domain\Services\PasswordPolicy;
use App\Modules\Authentication\Infrastructure\Persistence\DatabaseActiveSessionRepository;
use App\Modules\Authentication\Infrastructure\Persistence\DatabaseAuthorizationRepository;
use App\Modules\Authentication\Infrastructure\Persistence\DatabaseUserRepository;
use App\Modules\Authentication\Infrastructure\Security\CodeIgniterLoginAttemptLimiter;
use App\Modules\Authentication\Infrastructure\Security\CodeIgniterSensitiveActionLimiter;
use App\Modules\Authentication\Infrastructure\Security\NativePasswordVerifier;
use App\Modules\Inventory\Application\Services\InventoryDashboardService;
use App\Modules\Inventory\Application\Services\InventoryMovementService;
use App\Modules\Inventory\Application\Services\InventoryReportService;
use App\Modules\Inventory\Application\Services\ProductSearchService;
use App\Modules\Inventory\Infrastructure\Persistence\Models\InventoryMovementModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\ReportExportModel;
use App\Modules\Inventory\Infrastructure\Persistence\Repositories\DatabaseInventoryDashboardRepository;
use App\Modules\Inventory\Infrastructure\Persistence\Repositories\DatabaseInventoryMovementRepository;
use App\Modules\Inventory\Infrastructure\Persistence\Repositories\DatabaseInventoryReportRepository;
use App\Modules\Inventory\Infrastructure\Persistence\Repositories\DatabaseProductSearchRepository;
use App\Libraries\TwigView;
use CodeIgniter\Config\BaseService;

class Services extends BaseService
{
    public static function authenticationService(bool $getShared = true): AuthenticationService
    {
        if ($getShared) {
            return static::getSharedInstance('authenticationService');
        }

        /** @var SystemSettings $config */
        $config = config(SystemSettings::class);
        $db     = Database::connect();

        return new AuthenticationService(
            new DatabaseUserRepository($db),
            new DatabaseActiveSessionRepository($db),
            new NativePasswordVerifier(),
            new CodeIgniterLoginAttemptLimiter(
                static::throttler(),
                $config->loginUserAttempts,
                $config->loginIpAttempts,
                $config->loginAttemptWindow,
            ),
            $config->inactivityTimeout,
            $config->maxActiveSessions,
        );
    }

    public static function authorizationService(bool $getShared = true): AuthorizationService
    {
        if ($getShared) {
            return static::getSharedInstance('authorizationService');
        }

        return new AuthorizationService(
            new DatabaseAuthorizationRepository(Database::connect()),
        );
    }

    public static function passwordPolicy(bool $getShared = true): PasswordPolicy
    {
        if ($getShared) {
            return static::getSharedInstance('passwordPolicy');
        }

        /** @var SystemSettings $config */
        $config = config(SystemSettings::class);

        return new PasswordPolicy(
            $config->passwordMinLength,
            $config->passwordMaxLength,
            $config->passwordRequireUppercase,
            $config->passwordRequireLowercase,
            $config->passwordRequireNumber,
            $config->passwordRequireSpecial,
        );
    }

    public static function accountPasswordService(bool $getShared = true): AccountPasswordService
    {
        if ($getShared) {
            return static::getSharedInstance('accountPasswordService');
        }

        /** @var SystemSettings $config */
        $config = config(SystemSettings::class);
        $db     = Database::connect();

        return new AccountPasswordService(
            new DatabaseUserRepository($db),
            new DatabaseActiveSessionRepository($db),
            new NativePasswordVerifier(),
            static::passwordPolicy(),
            new CodeIgniterSensitiveActionLimiter(
                static::throttler(),
                $config->sensitiveActionAttempts,
                $config->sensitiveActionWindow,
            ),
            new DatabaseSecurityAuditLogger($db),
        );
    }

    public static function userAdministrationService(bool $getShared = true): UserAdministrationService
    {
        if ($getShared) {
            return static::getSharedInstance('userAdministrationService');
        }

        /** @var SystemSettings $config */
        $config = config(SystemSettings::class);
        $db     = Database::connect();

        return new UserAdministrationService(
            new DatabaseUserAdministrationRepository($db),
            new DatabaseUserRepository($db),
            new DatabaseActiveSessionRepository($db),
            new DatabaseAuthorizationRepository($db),
            new NativePasswordVerifier(),
            static::passwordPolicy(),
            new CodeIgniterSensitiveActionLimiter(
                static::throttler(),
                $config->sensitiveActionAttempts,
                $config->sensitiveActionWindow,
            ),
            new DatabaseSecurityAuditLogger($db),
            $config->inactivityTimeout,
            $config->usernameMinLength,
            $config->usernameMaxLength,
            $config->emailMaxLength,
        );
    }

    public static function moduleAdministrationService(bool $getShared = true): ModuleAdministrationService
    {
        if ($getShared) {
            return static::getSharedInstance('moduleAdministrationService');
        }

        $db = Database::connect();

        return new ModuleAdministrationService(
            new DatabaseModuleAdministrationRepository($db),
            new DatabaseAuthorizationRepository($db),
            new DatabaseSecurityAuditLogger($db),
        );
    }

    public static function roleAdministrationService(bool $getShared = true): RoleAdministrationService
    {
        if ($getShared) {
            return static::getSharedInstance('roleAdministrationService');
        }

        $db = Database::connect();

        return new RoleAdministrationService(
            new DatabaseRoleAdministrationRepository($db),
            new DatabaseAuthorizationRepository($db),
            new DatabaseSecurityAuditLogger($db),
        );
    }

    public static function inventoryDashboardService(bool $getShared = true): InventoryDashboardService
    {
        if ($getShared) {
            return static::getSharedInstance('inventoryDashboardService');
        }

        /** @var SystemSettings $config */
        $config = config(SystemSettings::class);
        $app = config('App');

        return new InventoryDashboardService(
            new DatabaseInventoryDashboardRepository(Database::connect()),
            service('logger'),
            $config->dashboardRecentMovementsLimit,
            $config->dashboardTopProductsLimit,
            (string) $app->appTimezone,
        );
    }

    public static function inventoryMovementService(bool $getShared = true): InventoryMovementService
    {
        if ($getShared) {
            return static::getSharedInstance('inventoryMovementService');
        }

        $db = Database::connect();

        return new InventoryMovementService(
            new DatabaseInventoryMovementRepository(
                $db,
                new InventoryMovementModel($db),
            ),
            service('logger'),
        );
    }

    public static function inventoryReportService(bool $getShared = true): InventoryReportService
    {
        if ($getShared) {
            return static::getSharedInstance('inventoryReportService');
        }

        /** @var SystemSettings $config */
        $config = config(SystemSettings::class);
        $app = config('App');
        $db = Database::connect();

        return new InventoryReportService(
            new DatabaseInventoryReportRepository(
                $db,
                new ReportExportModel($db),
            ),
            service('logger'),
            rtrim(WRITEPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'reports',
            $config->reportSearchMaxLength,
            (string) $app->appTimezone,
        );
    }

    public static function productSearchService(bool $getShared = true): ProductSearchService
    {
        if ($getShared) {
            return static::getSharedInstance('productSearchService');
        }

        return new ProductSearchService(
            new DatabaseProductSearchRepository(),
        );
    }

    public static function twig(bool $getShared = true): TwigView
    {
        if ($getShared) {
            return static::getSharedInstance('twig');
        }

        return new TwigView();
    }

}
