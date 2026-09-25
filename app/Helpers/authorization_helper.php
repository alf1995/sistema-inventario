<?php

if (! function_exists('auth_can')) {
    function auth_can(string $module, string $action): bool
    {
        $permissions = (array) service('session')->get('auth_permissions');

        return in_array(
            mb_strtolower(trim($module)) . '.' . mb_strtolower(trim($action)),
            $permissions,
            true,
        );
    }
}
