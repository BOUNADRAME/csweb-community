<?php
require_once __DIR__ . '/../setup/util.php';
require_once getApiVersionFilePath();
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

        <title>CSWeb: Upgrade</title>

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
                <h1>CSWeb: Upgrade Database</h1>
            </div>

            <?php

            function schema1To2($pdo) {
                //apps table
                $sql = <<<'EOT'
			CREATE TABLE IF NOT EXISTS `cspro_apps` (
			  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
			  `name` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
			  `description` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL,
			  `build_time` datetime NOT NULL,
			  `path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
			  `version` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
			  `modified_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			  `created_time` timestamp DEFAULT '1971-01-01 00:00:00',
			  UNIQUE KEY `name` (`name`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
			CREATE TRIGGER tr_cspro_apps BEFORE INSERT ON `cspro_apps` FOR EACH ROW SET NEW.`created_time` = CURRENT_TIMESTAMP;
EOT;
                $pdo->exec($sql);
                $sql = "UPDATE `cspro_config` SET `value`=2 where `name` = 'schema_version'";
                $pdo->exec($sql);
            }

            function schema2To3($pdo) {
                try {
                    $addNewColumns = array("email", "phone");
                    //add the email and phone columns to the user table and upgrade the schema version 
                    //Read out actual columns
                    $addedFields = array();
                    $rs = $pdo->query('SELECT * FROM `cspro_users` LIMIT 0');
                    for ($i = 0; $i < $rs->columnCount(); $i++) {
                        $col = $rs->getColumnMeta($i);
                        $colName = strtolower($col['name']);
                        if (in_array($colName, $addNewColumns)) {
                            $addedFields[] = $colName;
                        }
                    }
                    $columnsToAdd = array_diff($addNewColumns, $addedFields);
                    //Add columns
                    if (!empty($columnsToAdd)) {
                        foreach ($columnsToAdd as $c) {
                            if (strcasecmp($c, "email") == 0) {
                                $pdo->exec('ALTER TABLE `cspro_users` add `' . $c . '` VARCHAR(255) NULL DEFAULT NULL AFTER `last_name`;');
                            } else if (strcasecmp($c, "phone") == 0) {
                                $pdo->exec('ALTER TABLE `cspro_users` add `' . $c . '` VARCHAR(50) NULL DEFAULT NULL AFTER `email`;');
                            }
                        }
                    }
                    //update the schema version 
                    $sql = "UPDATE `cspro_config` SET `value`=3 where `name` = 'schema_version'";
                    $pdo->exec($sql);

                    $sql = "INSERT IGNORE INTO `cspro_config` (`name`, `value`) VALUES ('server_device_id', '" . guidv4() . "');";
                    $pdo->exec($sql);
                } catch (\Exception $e) {
                    throw $e;
                }
            }

            function schema3To4($pdo) {
                try {
                    //increase the role id size and autoincrement 
                    $sql = <<<'EOT'
                ALTER TABLE cspro_sync_history DROP FOREIGN KEY cspro_dict_id_constraint;
                ALTER TABLE `cspro_dictionaries` MODIFY `dictionary_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL;
                ALTER TABLE `cspro_dictionaries` MODIFY `id` smallint unsigned NOT NULL AUTO_INCREMENT;
                ALTER TABLE `cspro_users` DROP FOREIGN KEY role_id_constraint;
                ALTER TABLE `cspro_roles` CHANGE `id` `id` INT UNSIGNED NOT NULL AUTO_INCREMENT ;
                ALTER TABLE `cspro_roles` MODIFY `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL;
                ALTER TABLE `cspro_roles` ADD UNIQUE INDEX `rolename_unique` (`name` ASC);
                ALTER TABLE `cspro_users` CHANGE `role` `role` INT UNSIGNED NOT NULL;
                ALTER TABLE `cspro_sync_history` MODIFY `dictionary_id` smallint unsigned NOT NULL;
                ALTER TABLE `cspro_users` ADD CONSTRAINT `role_id_constraint` FOREIGN KEY (`role`) REFERENCES `cspro_roles` (`id`);
                ALTER TABLE `cspro_sync_history` ADD CONSTRAINT `cspro_dict_id_constraint` FOREIGN KEY (`dictionary_id`) REFERENCES `cspro_dictionaries`(`id`);
EOT;
                    $pdo->exec($sql);

                    //permissions 
                    $sql = <<<'EOT'
			CREATE TABLE IF NOT EXISTS `cspro_permissions` (
			  `id` int unsigned NOT NULL AUTO_INCREMENT,
                          `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
			  `modified_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			  `created_time` timestamp DEFAULT '1971-01-01 00:00:00',
			  PRIMARY KEY (`id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Permissions Table';
			CREATE TRIGGER tr_cspro_permissions BEFORE INSERT ON `cspro_permissions` FOR EACH ROW SET NEW.`created_time` = CURRENT_TIMESTAMP;

EOT;
                    $pdo->exec($sql);
                    //add permissions into table
                    $sql = <<<'EOT'
                    INSERT IGNORE INTO `cspro_permissions` (`id`, `name`) VALUES
                    (1,'data_all'),
                    (2,'apps_all'),
                    (3,'users_all'),
                    (4,'roles_all'),
                    (5,'reports_all'),
                    (6,'dictionary_sync_upload'),
                    (7,'dictionary_sync_download'),
                    (8,'settings_all'),
                    ;
EOT;
                    $pdo->exec($sql);
                    //role permissions 
                    $sql = <<<'EOT'
			CREATE TABLE IF NOT EXISTS `cspro_role_permissions` (
                          `role_id` int unsigned NOT NULL,
                          `permission_id` int unsigned NOT NULL,
			  `modified_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			  `created_time` timestamp DEFAULT '1971-01-01 00:00:00',
			   CONSTRAINT `cspro_role_id_constraint` FOREIGN KEY (role_id) REFERENCES cspro_roles(id) ON DELETE CASCADE,
                           CONSTRAINT `cspro_permission_id_constraint` FOREIGN KEY (permission_id) REFERENCES cspro_permissions(id) ON DELETE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Role Permissions Table';
			CREATE TRIGGER tr_cspro_role_permissions BEFORE INSERT ON `cspro_role_permissions` FOR EACH ROW SET NEW.`created_time` = CURRENT_TIMESTAMP;

EOT;
                    $pdo->exec($sql);
                    //role dictionary permissions 
                    $sql = <<<'EOT'
			CREATE TABLE IF NOT EXISTS `cspro_role_dictionary_permissions` (
                          `role_id` int unsigned NOT NULL,
                          `dictionary_id` smallint unsigned NOT NULL,
                          `permission_id` int unsigned NOT NULL,
			  `modified_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			  `created_time` timestamp DEFAULT '1971-01-01 00:00:00',
                           CONSTRAINT `cspro_role_dictionary_role_id_constraint` FOREIGN KEY (role_id) REFERENCES cspro_roles(id) ON DELETE CASCADE,
                           CONSTRAINT `cspro_role_dictionary_id_constraint` FOREIGN KEY (dictionary_id) REFERENCES cspro_dictionaries(id) ON DELETE CASCADE,
                           CONSTRAINT `cspro_role_dictionary_permission_id_constraint` FOREIGN KEY (permission_id) REFERENCES cspro_permissions(id) ON DELETE CASCADE			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Role Dictionary Permissions Table';
			CREATE TRIGGER tr_cspro_role_dictionary_permissions BEFORE INSERT ON `cspro_role_dictionary_permissions` FOR EACH ROW SET NEW.`created_time` = CURRENT_TIMESTAMP;

EOT;
                    $pdo->exec($sql);
                    // Add files and signature to the apps table
                    $addNewColumns = array('files', 'signature');
                    $addedFields = array();
                    $rs = $pdo->query('SELECT * FROM `cspro_apps` LIMIT 0');
                    for ($i = 0; $i < $rs->columnCount(); $i++) {
                        $col = $rs->getColumnMeta($i);
                        $colName = strtolower($col['name']);
                        if (in_array($colName, $addNewColumns)) {
                            $addedFields[] = $colName;
                        }
                    }
                    $columnsToAdd = array_diff($addNewColumns, $addedFields);

                    if (!empty($columnsToAdd)) {
                        foreach ($columnsToAdd as $c) {
                            if (strcasecmp($c, 'files') == 0) {
                                $pdo->exec('ALTER TABLE cspro_apps add files TEXT NULL AFTER version;');
                            } else if (strcasecmp($c, 'signature') == 0) {
                                $pdo->exec('ALTER TABLE `cspro_apps` add `signature` CHAR(32) NULL AFTER `version`;');
                            }
                        }
                    }

                    //add the userName column to sync_history 
                    $addNewColumns = array('username');
                    $addedFields = array();
                    $rs = $pdo->query('SELECT * FROM `cspro_sync_history` LIMIT 0');
                    for ($i = 0; $i < $rs->columnCount(); $i++) {
                        $col = $rs->getColumnMeta($i);
                        $colName = strtolower($col['name']);
                        if (in_array($colName, $addNewColumns)) {
                            $addedFields[] = $colName;
                        }
                    }
                    $columnsToAdd = array_diff($addNewColumns, $addedFields);

                    if (!empty($columnsToAdd)) {
                        foreach ($columnsToAdd as $c) {
                            if (strcasecmp($c, 'username') == 0) {
                                $pdo->exec('ALTER TABLE cspro_sync_history add `username` varchar(128) NULL AFTER device;');
                            }
                        }
                    }

                    //update the schema version 
                    $sql = "UPDATE `cspro_config` SET `value`=4 where `name` = 'schema_version'";
                    $pdo->exec($sql);
                } catch (\Exception $e) {
                    throw $e;
                }
            }

            function schema5To6($pdo) {
                try {
                    $addNewColumns = array("map_info");
                    //add the email and phone columns to the user table and upgrade the schema version 
                    //Read out actual columns
                    $addedFields = array();
                    $rs = $pdo->query('SELECT * FROM `cspro_dictionaries_schema` LIMIT 0');
                    for ($i = 0; $i < $rs->columnCount(); $i++) {
                        $col = $rs->getColumnMeta($i);
                        $colName = strtolower($col['name']);
                        if (in_array($colName, $addNewColumns)) {
                            $addedFields[] = $colName;
                        }
                    }
                    $columnsToAdd = array_diff($addNewColumns, $addedFields);
                    //Add columns
                    if (!empty($columnsToAdd)) {
                        foreach ($columnsToAdd as $c) {
                            if (strcasecmp($c, "map_info") == 0) {
                                $pdo->exec('ALTER TABLE `cspro_dictionaries_schema` add `' . $c . '` TEXT COLLATE utf8mb4_unicode_ci AFTER `schema_password`;');
                            }
                        }
                    }
                    //update the schema version 
                    $sql = "UPDATE `cspro_config` SET `value`=6 where `name` = 'schema_version'";
                    $pdo->exec($sql);
                } catch (\Exception $e) {
                    throw $e;
                }
            }

            function schema7To8($pdo) {
                try {
                    $sql = <<<'EOT'
			CREATE TABLE IF NOT EXISTS `cspro_breakout_scheduler` (
			  `id` int unsigned NOT NULL AUTO_INCREMENT,
			  `dictionary_id` smallint unsigned NOT NULL,
			  `enabled` tinyint(1) NOT NULL DEFAULT 0,
			  `cron_expression` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0 2 * * *',
			  `last_run` timestamp NULL DEFAULT NULL,
			  `next_run` timestamp NULL DEFAULT NULL,
			  `last_exit_code` int DEFAULT NULL,
			  `last_log_file` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
			  `modified_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			  `created_time` timestamp DEFAULT '1971-01-01 00:00:00',
			  PRIMARY KEY (`id`),
			  UNIQUE KEY `dictionary_id` (`dictionary_id`),
			  CONSTRAINT `scheduler_dict_id_constraint` FOREIGN KEY (`dictionary_id`) REFERENCES `cspro_dictionaries`(`id`) ON DELETE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
			CREATE TRIGGER tr_cspro_breakout_scheduler BEFORE INSERT ON `cspro_breakout_scheduler` FOR EACH ROW SET NEW.`created_time` = CURRENT_TIMESTAMP;
EOT;
                    $pdo->exec($sql);
                    $sql = "UPDATE `cspro_config` SET `value`=8 where `name` = 'schema_version'";
                    $pdo->exec($sql);
                } catch (\Exception $e) {
                    throw $e;
                }
            }

            function schema8To9($pdo) {
                try {
                    $sql = <<<'EOT'
			CREATE TABLE IF NOT EXISTS `cspro_backup_config` (
			  `id` int unsigned NOT NULL AUTO_INCREMENT,
			  `enabled` tinyint(1) NOT NULL DEFAULT 0,
			  `cron_expression` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0 2 * * *',
			  `retention_days` int unsigned NOT NULL DEFAULT 30,
			  `last_run` timestamp NULL DEFAULT NULL,
			  `next_run` timestamp NULL DEFAULT NULL,
			  `last_exit_code` int DEFAULT NULL,
			  `last_log_file` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
			  `modified_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			  `created_time` timestamp DEFAULT '1971-01-01 00:00:00',
			  PRIMARY KEY (`id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
			CREATE TRIGGER tr_cspro_backup_config BEFORE INSERT ON `cspro_backup_config` FOR EACH ROW SET NEW.`created_time` = CURRENT_TIMESTAMP;
EOT;
                    $pdo->exec($sql);
                    $pdo->exec("INSERT INTO `cspro_backup_config` (`enabled`, `cron_expression`, `retention_days`) VALUES (0, '0 2 * * *', 30)");
                    $sql = "UPDATE `cspro_config` SET `value`=9 where `name` = 'schema_version'";
                    $pdo->exec($sql);
                } catch (\Exception $e) {
                    throw $e;
                }
            }

            function schema9To10($pdo) {
                try {
                    // Add port column to cspro_dictionaries_schema for non-default remote DB ports
                    $col = $pdo->query("SHOW COLUMNS FROM `cspro_dictionaries_schema` LIKE 'port'")->rowCount();
                    if ($col === 0) {
                        $pdo->exec("ALTER TABLE `cspro_dictionaries_schema` ADD COLUMN `port` smallint unsigned DEFAULT NULL AFTER `host_name`");
                    }
                    $pdo->exec("UPDATE `cspro_config` SET `value`=10 WHERE `name` = 'schema_version'");
                } catch (\Exception $e) {
                    throw $e;
                }
            }

            function schema10To11($pdo) {
                try {
                    $col = $pdo->query("SHOW COLUMNS FROM `cspro_dictionaries_schema` LIKE 'db_type'")->rowCount();
                    if ($col === 0) {
                        $pdo->exec("ALTER TABLE `cspro_dictionaries_schema` ADD COLUMN `db_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'postgresql' AFTER `port`");
                    }
                    $pdo->exec("UPDATE `cspro_config` SET `value`=11 WHERE `name` = 'schema_version'");
                } catch (\Exception $e) {
                    throw $e;
                }
            }

            function schema11To12($pdo) {
                try {
                    $pdo->exec("INSERT IGNORE INTO \`cspro_permissions\` (\`id\`, \`name\`) VALUES (9, 'backup_all'), (10, 'logs_all')");
                    // Grant backup_all and logs_all to Administrator role (id=2)
                    $pdo->exec("INSERT IGNORE INTO \`cspro_role_permissions\` (\`role_id\`, \`permission_id\`) VALUES (2, 9), (2, 10)");
                    $pdo->exec("UPDATE \`cspro_config\` SET \`value\`=12 WHERE \`name\` = 'schema_version'");
                } catch (\Exception $e) {
                    throw $e;
                }
            }

            $migrateFuncs = array(
                5 => 'schema5To6',
                7 => 'schema7To8',
                8 => 'schema8To9',
                9 => 'schema9To10',
                10 => 'schema10To11',
                11 => 'schema11To12'
            );

            // Check if app was already configured
            if (!alreadyConfigured()) {
                echo 'This application has not yet been configured. Click <a href="/setup/index.php">here</a> to configure it.';
            } else {
                require_once getApiConfigFilePath();

                try {

                    // Create database connection
                    $pdo = new PDO('mysql:host=' . DBHOST . ';dbname=' . DBNAME . ';charset=utf8mb4', DBUSER, DBPASS, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));

                    // Get current schema version
                    $dbSchemaVersion = $pdo->query("SELECT `value` FROM `cspro_config` WHERE `name`='schema_version'")->fetchColumn();
                    if ($dbSchemaVersion < 7) {//CSPro 8.0 has no upgrade path
                        $strMsg = "There is no upgrade path available from older versions of CSWeb to CSWeb 8.0. "
                                . "Click <a href=\"/setup/index.php\">here</a> to configure CSWeb 8.0 using a new database name.";
                        echo '<div class="alert alert-danger" role="alert">Error: ' . $strMsg . '</div>';
                        return;
                    }
                    while ($dbSchemaVersion < SCHEMA_VERSION) {
                        echo '<p>Upgrading to version ' . ($dbSchemaVersion + 1) . '...';
                        $migrateFunc = $migrateFuncs[$dbSchemaVersion];
                        call_user_func($migrateFunc, $pdo);
                        echo ' Done</p>';
                        ++$dbSchemaVersion;
                    }

                    echo '<br/>';
                    echo '<div class="alert alert-success" role="alert">Database Upgrade Complete!</div>';
                    echo '<a href="../" class="btn btn-primary float-right">Login</a>';
                } catch (Exception $e) {
                    echo '<div class="alert alert-danger" role="alert">Error: ' . $e->getMessage() . '</div>';
                }
            }
            ?>

        </div>
    </body>
</html>