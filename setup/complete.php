<?php
// Community layer: install the Community schema as soon as upstream setup has
// finished.
//
// docker-entrypoint.sh runs the same command on boot, but only when
// src/config.php already exists. On a fresh stack the container starts before
// anyone has been through /setup, so that call is skipped and nothing runs it
// again afterwards: the port / db_type columns stay missing and every visit to
// /dataSettings fails with "Unknown column 'cspro_dictionaries_schema.port'".
//
// Running it here closes that window. The installer is idempotent, so the boot
// call and this one cannot conflict.
if (is_file(__DIR__ . '/../src/config.php')) {
    $console = __DIR__ . '/../bin/console';
    if (is_file($console)) {
        // PHP_BINARY is the CLI path only under the CLI SAPI. Under Apache with
        // mod_php it is empty, which produced an empty command and
        // "sh: 1: : Permission denied" — the installer silently never ran, so
        // the Community permissions were missing and the sidebar showed
        // neither Breakout Health nor Backup/Logs. The admin role is built-in
        // and cannot be edited afterwards, so these grants have to land here.
        $phpBinary = PHP_BINARY;
        if ($phpBinary === '' || !is_executable($phpBinary) || PHP_SAPI !== 'cli') {
            foreach (['/usr/local/bin/php', '/usr/bin/php', '/usr/local/sbin/php'] as $candidate) {
                if (is_executable($candidate)) {
                    $phpBinary = $candidate;
                    break;
                }
            }
        }

        if ($phpBinary === '' || !is_executable($phpBinary)) {
            error_log('[CSWeb] Community schema install skipped: no PHP CLI binary found.');
        } else {
            $command = escapeshellarg($phpBinary) . ' ' . escapeshellarg($console)
                     . ' csweb:community:install-schema --env=prod --no-debug 2>&1';
            exec($command, $communityInstallOutput, $communityInstallStatus);
            if ($communityInstallStatus !== 0) {
                error_log('[CSWeb] Community schema install failed after setup: '
                    . implode(' | ', $communityInstallOutput));
            }
        }
    }

    // Community layer: persist config.php to the Docker volume immediately.
    //
    // docker-entrypoint.sh copies it to /var/www/html/config-persist and leaves
    // a symlink behind, but that only runs at container start — and at start
    // the file does not exist yet, because setup writes it while the container
    // is already running. The copy therefore never happened, and rebuilding the
    // image silently discarded the configuration, sending the operator back to
    // /setup. Persisting here closes the same window as the installer above.
    $persistDir = '/var/www/html/config-persist';
    $configSrc = __DIR__ . '/../src/config.php';
    if (is_dir($persistDir) && is_writable($persistDir) && !is_link($configSrc)) {
        $persisted = $persistDir . '/config.php';
        if (@copy($configSrc, $persisted)) {
            @chmod($persisted, 0644);
            // Replace the real file with a symlink so later writes land in the
            // volume, exactly as the entrypoint would have set it up.
            if (@unlink($configSrc)) {
                @symlink($persisted, $configSrc);
            }
        } else {
            error_log('[CSWeb] Could not persist config.php to ' . $persistDir);
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="author" content="">

	<link rel='icon' href='../dist/img/favicon.ico' type='image/x-icon'/ >

	<title>CSWeb: Setup</title>

    <!-- Bootstrap Core CSS -->
    <link href="../bower_components/bootstrap/css/bootstrap.min.css" rel="stylesheet">

    <!-- Custom Fonts -->
    <link href="../bower_components/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">

    <!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
        <script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
        <script src="https://oss.maxcdn.com/libs/respond.js/1.4.2/respond.min.js"></script>
    <![endif]-->

</head>
<body>

<div class="container">

<div class="page-header">
<h1>CSWeb: Setup</h1>
</div>

<br/>

<div class="alert alert-success" role="alert">Setup Complete!</div>
<a href="../" class="btn btn-primary float-right">Login</a>
</div>

</body>
</html>