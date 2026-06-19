<?php
/**
 * Bootstrap dos testes PHPUnit.
 *
 * O módulo depende de algumas globais do WHMCS (constantes, helpers e
 * classes do Laravel/Eloquent re-exportadas). Aqui criamos shims mínimos
 * para permitir testar Helper.php e SSHManager.php isoladamente, sem
 * precisar de uma instalação WHMCS.
 */

declare(strict_types=1);

// 1. Constantes e funções globais que o WHMCS define.
if (!defined('WHMCS')) {
    define('WHMCS', true);
}

if (!function_exists('logActivity')) {
    function logActivity($desc): void {
        // no-op nos testes
    }
}

// 2. Stub de \WHMCS\Database\Capsule (configurável via MockCapsule::seed).
require_once __DIR__ . '/src/Stub/MockQuery.php';
require_once __DIR__ . '/src/Stub/MockCapsule.php';

if (!class_exists('WHMCS\\Database\\Capsule')) {
    eval('namespace WHMCS\\Database; class Capsule {
        public static function table($table) {
            return \\NextcloudSaaS\\Tests\\Stub\\MockCapsule::table($table);
        }
    }');
}

// 3. Composer autoload da suite de testes.
$autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

// 4. Carregar classes do módulo a testar.
require_once __DIR__ . '/../modules/servers/nextcloudsaas/lib/Helper.php';
require_once __DIR__ . '/../modules/servers/nextcloudsaas/lib/SSHManager.php';
require_once __DIR__ . '/../modules/servers/nextcloudsaas/lib/NextcloudAPI.php';
