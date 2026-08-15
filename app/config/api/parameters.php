<?php

include_once __DIR__ . '/../../../src/config.php';
include_once __DIR__ . '/../../../src/version.php';

$container->setParameter('secret', CSWEB_APP_SECRET);
$container->setParameter('database_port', 3306);
$container->setParameter('database_host', DBHOST);
$container->setParameter('database_name', DBNAME);
$container->setParameter('database_user', DBUSER);
$password = DBPASS;
$password = str_replace("%", "%%", $password); //escape % character if any in the password
$container->setParameter('database_password', $password);
// cspro_rest_api_url is the PUBLIC address: it is written into the .pff sync
// spec as SyncService= and into the cspro:/// links on the Data screen, both of
// which are consumed by CSPro on a tablet or a workstation. It must therefore
// be the address those clients can reach — a hostname or IP, with the published
// port when there is one.
$container->setParameter('cspro_rest_api_url', API_URL);

// Community layer: separate address for CSWeb calling its own API.
//
// Behind Docker the public URL is not reachable from inside the container: the
// published port is host-side only, and a public hostname may not resolve.
// CSWEB_INTERNAL_API_URL provides the container-side address; when it is unset
// the public URL is reused, which is correct for a bare-metal install where
// both are the same.
$internalApiUrl = getenv('CSWEB_INTERNAL_API_URL');
$container->setParameter(
    'cspro_internal_api_url',
    is_string($internalApiUrl) && trim($internalApiUrl) !== '' ? trim($internalApiUrl) : API_URL
);
$container->setParameter('csweb_internal_files_folder', INTERNAL_FILES_FOLDER);
$container->setParameter('csweb_api_files_folder', FILES_FOLDER);
$container->setParameter('csweb_api_default_timezone', DEFAULT_TIMEZONE);
$container->setParameter('csweb_max_script_execution_time', MAX_EXECUTION_TIME);
$container->setParameter('enable_oauth', ENABLE_OAUTH);

$container->setParameter('cspro_version', CSPRO_VERSION);
$container->setParameter('csweb_api_schema_version', SCHEMA_VERSION);
$container->setParameter('csweb_api_version', API_VERSION);

$container->setParameter('csweb_log_level', CSWEB_LOG_LEVEL);
switch (strtolower(CSWEB_LOG_LEVEL)) {
    case 'debug':
        $container->setParameter('csweb_db_log_level', Monolog\Logger::DEBUG);
        break;
    case 'error':
        $container->setParameter('csweb_db_log_level', Monolog\Logger::ERROR);
        break;
    case 'info':
        $container->setParameter('csweb_db_log_level', Monolog\Logger::INFO);
        break;
    case 'notice':
        $container->setParameter('csweb_db_log_level', Monolog\Logger::NOTICE);
        break;
    case 'warning':
        $container->setParameter('csweb_db_log_level', Monolog\Logger::WARNING);
        break;
    default:
        $container->setParameter('csweb_db_log_level', Monolog\Logger::ERROR);
}
$container->setParameter('csweb_process_cases_log_level', CSWEB_PROCESS_CASES_LOG_LEVEL);


