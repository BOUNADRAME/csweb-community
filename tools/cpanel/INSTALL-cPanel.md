---
title: "CSWeb 8.1 on cPanel Shared Hosting"
subtitle: "Installation and Operation Guide"
---

# About this package

This package installs **CSWeb 8.1** (CSWeb Community edition) on a shared hosting account managed with **cPanel**. It is ready to upload: no SSH, Composer or command-line tools are required to install it.

On shared hosting, CSWeb is used for **data collection and synchronization**:

- deploying CSPro applications to tablets and phones,
- synchronizing cases between CSEntry and the server,
- monitoring progress (sync report, map),
- managing interviewers and supervisors,
- downloading the data for processing with CSPro (Data Viewer, export to Stata, SPSS, R or CSV).

The **breakout** feature (automatic export of cases to relational tables) and scheduled jobs are not available on shared hosting. They require a dedicated server.

## Tested configuration

The package was installed and tested in an environment reproducing a cPanel shared hosting plan:

| Component | Version / setting |
|---|---|
| PHP | 8.3, with `exec`, `proc_open`, `shell_exec`, `system`, `popen` disabled |
| Web server | Apache 2.4 with `mod_rewrite` and `.htaccess` |
| Database | **MariaDB 10.6** (also compatible with MySQL 8) |
| Limits | 1 CPU core, 20 database connections per user |

Results: installation, application deployment, CSEntry synchronization (including **5,000 cases in a single sync**), data download, user management and Arabic text all work. **200 devices synchronizing within the same minute completed with no error.**

# Before you start

## Hosting checklist

| Requirement | Where to check in cPanel |
|---|---|
| PHP **8.3** (8.1 minimum) | *MultiPHP Manager* |
| PHP extensions: `pdo_mysql`, `zip`, `curl`, `openssl`, `fileinfo`, `dom` | *Select PHP Version* or provider |
| MariaDB 10.6+ or MySQL 8.0+ | *MySQL Databases* |
| Apache `mod_rewrite` and `.htaccess` | Provider (enabled on standard cPanel) |
| SSL certificate for the subdomain | *SSL/TLS Status* (AutoSSL) |
| Disk space: about 200 MB, plus data | *Disk Usage* |
| Files (inodes): about 16,000 | Provider |
| Terminal or SSH access (one command, Step 7) | *Terminal*. Optional, see Step 7 |

## Ask your provider one question

> *What is the maximum number of simultaneous connections per database user (`max_user_connections`) on our plan?*

A value of **20 or more** is suitable. Each synchronization uses one connection for a fraction of a second.

## What you need

- The package `csweb-<version>-cpanel.zip` and its checksum file `.sha256`.
- A subdomain name, for example `csweb.your-domain.org`. This guide uses this example.
- A strong administrator password (12 characters or more).

# Installation

## Step 1. Create the subdomain

1. In cPanel, open **Domains** (or **Subdomains**) and create `csweb.your-domain.org`.
2. Set its **document root** to `/home/<cpanel-user>/csweb`.

   Do not use a folder inside `public_html`. The package is extracted into `/home/<cpanel-user>/csweb` in Step 4.

## Step 2. Select the PHP version and settings

1. Open **MultiPHP Manager**, select the subdomain and choose **PHP 8.3**.
2. Open **MultiPHP INI Editor**, select the subdomain and set:

| Setting | Value |
|---|---|
| `memory_limit` | `256M` |
| `upload_max_filesize` | `64M` |
| `post_max_size` | `64M` |
| `max_execution_time` | `300` |

## Step 3. Create the database

1. Open **MySQL Databases**.
2. Create a database, for example `csweb`. cPanel adds your account prefix: note the full name, for example `myaccount_csweb`.
3. Create a user, for example `myaccount_cswebuser`, with a strong password.
4. Under **Add User To Database**, select the user and the database, then grant **ALL PRIVILEGES**.

## Step 4. Upload and extract the package

1. Open **File Manager** and go to your **home folder** (`/home/<cpanel-user>`), not `public_html`.
2. Click **Upload** and select `csweb-<version>-cpanel.zip`.
3. Back in File Manager, right-click the zip and choose **Extract**. This creates the folder `csweb`.
4. Click **Settings** (top right) and tick **Show Hidden Files (dotfiles)**. Open `csweb` and check that the file **`.htaccess`** is present. Without it, CSEntry cannot authenticate.
5. Delete the zip file.

> **Optional: check the download.** Compare the SHA-256 of the zip with the `.sha256` file before uploading (on Windows: `certutil -hashfile file.zip SHA256`).

Folder permissions are already correct: on cPanel, PHP runs as your account, so **do not set 777 permissions**.

## Step 5. Enable HTTPS

1. Open **SSL/TLS Status**, select `csweb.your-domain.org` and click **Run AutoSSL**.
2. Wait until `https://csweb.your-domain.org` opens without a certificate warning. A page from the CSWeb setup is expected at this stage.

Complete this step **before** Step 6: the address entered in the setup wizard is the one written into the CSEntry applications.

## Step 6. Run the setup wizard

1. Open `https://csweb.your-domain.org/setup/`. All requirements must show a green check. If one is red, see *Troubleshooting*.
2. Click **Next** and fill in the form:

| Field | Value |
|---|---|
| Database name | Full name from Step 3, for example `myaccount_csweb` |
| Database host | `localhost` |
| Database user / password | From Step 3 |
| Admin password | Your strong administrator password |
| Timezone | Your local timezone, for example `Asia/Baghdad` |
| Max execution time | `300` |
| Files directory | `/home/<cpanel-user>/csweb/files` (pre-filled) |
| CSWeb API URL | `https://csweb.your-domain.org/api/` **(with the final slash)** |

3. Click **Next**. The wizard creates the database and shows a completion page.

After installation, the wizard **locks itself** (`/setup/` returns *403 Forbidden*). This is expected and protects your installation.

## Step 7. Activate the administration pages

This step enables the **Settings**, **Backup** and **Logs** pages. Data collection and synchronization work without it.

1. In cPanel, open **Terminal**.
2. Run this command, replacing `ea-php83` with the PHP version chosen in Step 2 if it differs:

```
cd ~/csweb && /opt/cpanel/ea-php83/root/usr/bin/php bin/console csweb:community:install-schema --env=prod
```

3. The expected result ends with: `[OK] Community schema installed`.

If **Terminal** is not available on your plan, ask your provider to run the command once, or skip this step.

## Step 8. Validate the installation

Complete this checklist before using the server for fieldwork.

| # | Test | Expected result |
|---|---|---|
| 1 | Log in at `https://csweb.your-domain.org` as `admin` | The dashboard opens, with styled tables and menus |
| 2 | **Users**: create an interviewer account | The account appears in the list |
| 3 | From **CSPro Designer**, deploy an application to `https://csweb.your-domain.org/api` | The application appears under **Apps** |
| 4 | On a device, in **CSEntry**, add the application from the server, log in as the interviewer | The application installs |
| 5 | Enter 2 or 3 test cases and **synchronize**, preferably over mobile data | Sync completes; cases appear in **Sync Report** |
| 6 | Synchronize a second device | It downloads the shared files and data as designed |
| 7 | Download the data with **CSPro Data Viewer** (download from CSWeb, using the server address above) | All test cases are present |

Then delete the test data from the dashboard's list of dictionaries (*Delete data*), so that fieldwork starts from an empty database.

# Operation

## Backups

Use the **cPanel backups**. CSWeb's own *Backup now* button needs functions that shared hosting disables, and shows an error.

- **Backup** (or **JetBackup**) in cPanel saves the database and files daily on most plans. Check the frequency and retention with your provider.
- During fieldwork, download a copy at least weekly: **Backup > Download a MySQL Database Backup** (your CSWeb database) and **Download a Home Directory Backup**.
- Data is also kept on the devices until it is synchronized. Do not delete CSEntry data on devices until it has been downloaded and checked.

## Accounts and security

- Use one account per interviewer. The server limits repeated wrong passwords: after **5 failed attempts in one minute**, an account is locked for one minute.
- Keep the `admin` password private. Create supervisor accounts for daily monitoring.
- Always use the `https://` address in CSPro Designer and CSEntry.

## Capacity

| Situation | Behaviour |
|---|---|
| Up to 200 devices synchronizing within the same minute | Tested with no error |
| More than 400 devices within the same minute | A few syncs may fail and must be retried |
| Devices sharing one network (office Wi-Fi, mobile operator NAT) | Supported (each interviewer has their own account) |
| Large syncs (5,000 cases at once) | Tested: about 2 seconds |

For a census or a survey with several hundred interviewers synchronizing at the same time, a dedicated server is recommended.

## Where to find logs

`/home/<cpanel-user>/csweb/var/logs/` (`api-<date>.log` for synchronization, `ui.log` for the web interface). These files are not accessible from the web.

# Troubleshooting

| Symptom | Cause and solution |
|---|---|
| A requirement is red in the setup wizard | PHP version or extension: see Step 2 and the hosting checklist. For "directories are writeable", re-extract the zip with the File Manager. |
| HTTP 500 when logging in to the dashboard right after setup | The API URL lacks its final slash. In File Manager, edit `csweb/src/config.php` and make the `API_URL` line end with `/api/'`. |
| Pages display without style; buttons and tables do not work | Incomplete upload. Delete the `csweb` folder and extract the full zip again. |
| CSEntry or Designer: *Unauthorized* (401) with correct passwords | The hidden file `csweb/.htaccess` is missing. Re-extract the zip (Step 4). |
| *Too many requests* (429) | Repeated wrong passwords: wait one minute. |
| Some syncs fail at peak times; the log shows `max_user_connections` | The plan's connection limit is reached. Ask the provider to raise it, or schedule teams to sync at different times. |
| Large syncs fail or time out | Check Step 2 settings (`max_execution_time`, upload limits). If the provider's firewall (ModSecurity) blocks requests, ask them to allow `/api/` on the CSWeb subdomain. |
| *Backup now* shows an error | Expected on shared hosting: use the cPanel backups. |
| Settings, Backup or Logs page shows an error | Step 7 was not run. |
| `/setup/` returns *403 Forbidden* | Normal after installation. |

# Updating CSWeb

1. Make a cPanel backup (database and home directory).
2. Extract the new package into a new folder, for example `csweb-new`.
3. Copy into it, from the current installation: the file `src/config.php` (written by the setup wizard) and the folders `files/` and `files_csweb/`.
4. Swap the folders (rename `csweb` to `csweb-old`, then `csweb-new` to `csweb`), and empty the folder `csweb/var/cache`.
5. Repeat Step 7 and the checklist of Step 8. Keep `csweb-old` until the update is validated.

# Support

CSWeb Community: <https://github.com/BOUNADRAME/csweb-community>. Report issues under *Issues* with the content of `BUILD-INFO.txt` and the relevant lines of `var/logs/`.

Installation assistance and dedicated server set-up are available from StatPro Consulting.
