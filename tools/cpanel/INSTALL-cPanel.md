---
title: "CSWeb 8.1.0 on cPanel Shared Hosting"
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

The **breakout** feature (automatic export of cases to relational tables) and scheduled jobs are not available on shared hosting. They require a dedicated server. Ignore the **Breakout Health** menu and the breakout options under **Settings**.

## Tested configuration

The package was installed and tested in an environment reproducing a cPanel shared hosting plan:

| Component | Version / setting |
|---|---|
| PHP | 8.3, with `exec`, `proc_open`, `shell_exec`, `system`, `popen` disabled |
| Web server | Apache 2.4 with `mod_rewrite` and `.htaccess` |
| Database | **MariaDB 10.6** (also compatible with MySQL 8) |
| Limits | 1 CPU core, 20 database connections per user |

Results: installation, application deployment, CSEntry synchronization (including **5,000 cases in a single sync**), data download, user management and Arabic text all work. **100 to 200 devices synchronizing within the same minute completed with no error.**

# Before you start

## Hosting checklist

| Requirement | Where to check in cPanel |
|---|---|
| PHP **8.3** (8.1 minimum) | *MultiPHP Manager*, or *Select PHP Version* on CloudLinux hosts |
| PHP extensions: `pdo_mysql`, `zip`, `curl`, `openssl`, `fileinfo`, `dom` | *Select PHP Version* or provider |
| MariaDB 10.6+ or MySQL 8.0+ | *MySQL Databases* |
| Apache `mod_rewrite` and `.htaccess` | Provider (enabled on standard cPanel) |
| SSL certificate for the subdomain | *SSL/TLS Status* (AutoSSL) |
| Disk space: about 200 MB, plus data | *Disk Usage* |
| Files (inodes): about 18,000 | Provider |

## Ask your provider one question

> *What is the maximum number of simultaneous connections per database user (`max_user_connections`) on our plan?*

A value of **20 or more** is suitable. Each synchronization uses one connection for a fraction of a second.

## What you need

- The package `csweb-<version>-cpanel.zip` and its checksum file `.sha256`.
- A subdomain name, for example `csweb.your-domain.org`. This guide uses this example.
- A strong administrator password (12 characters or more).

**Choose the address once.** The address `https://csweb.your-domain.org` is written into every CSEntry application you deploy. Changing it during fieldwork means redeploying the application to every tablet.

# Installation

## Step 1. Create the subdomain

1. In cPanel, open **Domains** (or **Subdomains** on older cPanel) and create `csweb.your-domain.org`.
2. cPanel fills the **Document Root** field automatically (for example `csweb.your-domain.org` or `public_html/csweb`). Replace it with **`csweb`**, so the folder is `/home/<cpanel-user>/csweb`. The `<cpanel-user>` is the user name shown in the right column of the cPanel home page.

   Do not use a folder inside `public_html`.

## Step 2. Create the database

1. Open **MySQL Databases**.
2. Create a database, for example `csweb`. cPanel adds your account prefix: note the **full name**, for example `myaccount_csweb`.
3. Create a user, for example `myaccount_cswebuser`, with a strong password.
4. Under **Add User To Database**, select the user and the database, then grant **ALL PRIVILEGES**.

## Step 3. Upload and extract the package

1. Open **File Manager** and go to your **home folder** (`/home/<cpanel-user>`, the top of the folder tree), **not** inside `csweb` or `public_html`.
2. Click **Upload** and select `csweb-<version>-cpanel.zip`.
3. Back in File Manager, right-click the zip and choose **Extract**, then confirm `/home/<cpanel-user>`. The files go into the folder `csweb` created in Step 1.
4. Open `csweb`. You must see the folders `api`, `setup`, `vendor` directly inside it. If you see another `csweb` folder inside `csweb`, the zip was extracted in the wrong place: delete everything and repeat from item 1.
5. Click **Settings** (top right) and tick **Show Hidden Files (dotfiles)**. Check that the file **`.htaccess`** is present in `csweb`. Without it, the home page shows *403 Forbidden* and private files can be downloaded.
6. Delete the zip file.

> **Optional: check the download.** Compare the SHA-256 of the zip with the `.sha256` file before uploading (on Windows: `certutil -hashfile file.zip SHA256`).

Folder permissions are already correct: on cPanel, PHP runs as your account, so **do not set 777 permissions**.

## Step 4. Select the PHP version and settings

Do this **after** Step 3: cPanel stores these settings in `csweb/.htaccess`, and extracting the zip replaces that file.

1. Open **MultiPHP Manager**, tick `csweb.your-domain.org` and choose **PHP 8.3** (on CloudLinux hosts: **Select PHP Version**).
2. Open **MultiPHP INI Editor**, select the subdomain and set:

| Setting | Value |
|---|---|
| `memory_limit` | `256M` |
| `upload_max_filesize` | `64M` |
| `post_max_size` | `64M` |
| `max_execution_time` | `300` |

If you extract the zip again later, repeat this step.

## Step 5. Enable HTTPS

1. Open **SSL/TLS Status**, select `csweb.your-domain.org` and click **Run AutoSSL**.
2. Wait until `https://csweb.your-domain.org` opens without a certificate warning. A page from the CSWeb setup is expected at this stage.

Complete this step **before** Step 6: the wizard checks the address over HTTPS, and this address is the one written into the CSEntry applications.

## Step 6. Run the setup wizard

1. Open `https://csweb.your-domain.org/setup/` (with `https://`). All requirements must show a green check. If one is red, see *Troubleshooting*.
2. Click **Next** and fill in the form:

| Field in the wizard | Value |
|---|---|
| Database name | Full name from Step 2, for example `myaccount_csweb` |
| Hostname | `localhost` |
| Database username | Full user name from Step 2, with the prefix, for example `myaccount_cswebuser` |
| Database password | Password from Step 2 |
| CSWeb admin password | Your strong administrator password. The user name is **`admin`**. |
| Timezone | Your local timezone, for example `Asia/Baghdad` or `Africa/Dakar` |
| Maximum execution time | `300` (pre-filled) |
| Path to files directory | `/home/<cpanel-user>/csweb/files` (pre-filled, keep it) |
| CSWeb API URL | `https://csweb.your-domain.org/api/` (pre-filled: check it starts with `https://`; the final slash is added automatically) |

3. Click **Next**. The wizard creates the database and shows *Setup Complete!*.

After installation, the wizard **locks itself** (`/setup/` returns *403 Forbidden*). This is expected and protects your installation.

## Step 7. Validate the installation

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

Then delete the test data from the dashboard's list of dictionaries (*Delete data*) and the test accounts, so that fieldwork starts from an empty database.

# Operation

## Interviewer accounts

- Create **one account per interviewer**. The server accepts at most **5 logins per minute for the same account from the same network**; more are refused for one minute (*Too many requests*). An account shared by several tablets fails at synchronization time.
- User names may contain **only letters and digits** (for example `int001`, not `int_001`).
- To create many accounts, use **Users > Import** with a CSV file: `username, first name, last name, role, password, email, phone`, for example `int001,Ahmed,Ali,Standard User,Pass!2026a,,`. In Excel, save as **CSV UTF-8** (required for Arabic names) and tick **CSV file has a header row** in the import window.
- Keep the `admin` password private. Create supervisor accounts for daily monitoring.
- Always use the `https://` address in CSPro Designer and CSEntry.

## During fieldwork

- **Do not** change the subdomain, switch between `http://` and `https://`, delete `src/config.php`, re-run the setup or extract a new zip over the installation. Each of these stops synchronization on every tablet.
- **Do not** use *Delete data* or delete a dictionary once fieldwork has started.
- If a synchronization fails at the end of the day, the interviewer waits one minute and synchronizes again. Data stays on the tablet until it is sent.

## Backups

Use the **cPanel backups**. The CSWeb **Backup** page cannot run on shared hosting: *Backup now* shows an error, and a schedule enabled on that page never runs.

- **Backup** (or **JetBackup**) in cPanel saves the database and files daily on most plans. Check the frequency and retention with your provider.
- During fieldwork, download a copy at least weekly: **Backup > Download a MySQL Database Backup** (your CSWeb database) and **Download a Home Directory Backup**.
- Data is also kept on the devices until it is synchronized. Do not delete CSEntry data on devices until it has been downloaded and checked.

## Capacity

| Situation | Behaviour |
|---|---|
| 100 to 200 devices synchronizing within the same minute | Tested with no error |
| Many devices at the same second (for example 100 within 10 seconds) | A few syncs fail and must be retried |
| Devices sharing one network (office Wi-Fi, mobile operator NAT) | Supported, up to about 60 logins per minute from one public address (each interviewer has their own account) |
| Large syncs (5,000 cases at once) | Tested: about 2 seconds |

For a census or a survey with several hundred interviewers synchronizing at the same time, a dedicated server is recommended.

## Where to find logs

In the dashboard, **Logs**. The files are in `/home/<cpanel-user>/csweb/var/logs/` (`api-<date>.log` for synchronization, `ui.log` for the web interface). They are not accessible from the web.

# Troubleshooting

| Symptom | Cause and solution |
|---|---|
| A requirement is red in the setup wizard | PHP version or extension: see Step 4 and the hosting checklist. For "directories are writeable", re-extract the zip with the File Manager. |
| Page shows *Composer detected issues in your platform ... PHP version* | The subdomain does not use PHP 8.1 or later: repeat Step 4. |
| Home page or `/setup/` shows *403 Forbidden* or *Index of /* before setup | `csweb/.htaccess` is missing, or the zip was extracted into `csweb/csweb`. Repeat Step 3, then Step 4. |
| Wizard: *Access denied for user ... (using password: YES)* | Wrong database user name or password. Use the full names with prefix from Step 2; reset the password in *MySQL Databases* if needed. |
| Wizard: *Access denied for user ... to database ...* | Database name without the account prefix, or user not added to the database (Step 2, item 4). |
| Wizard: *Failed to contact API server* | HTTPS is not ready or the API URL is wrong. Fix it (Step 5), correct the **CSWeb API URL** field and click **Next** again. |
| Pages display without style; buttons and tables do not work | Incomplete upload. Delete the `csweb` folder and extract the full zip again. |
| *Too many requests* (429) | More than 5 logins in one minute with the same account from the same network: wait one minute. Do not share accounts between tablets. |
| Some syncs fail at peak times; the log shows `max_user_connections` | The plan's connection limit is reached. Ask the provider to raise it, or schedule teams to sync at different times. |
| Large syncs fail or time out | Check Step 4 settings (`max_execution_time`, upload limits). If the provider's firewall (ModSecurity) blocks requests, ask them to allow `/api/` on the CSWeb subdomain. |
| User import: *Malformed UTF-8 characters* or *Username must contain only letters and numbers; Line number: 1* | Save the file as **CSV UTF-8** and tick **CSV file has a header row**. |
| *Backup now* shows an error | Expected on shared hosting: use the cPanel backups. |
| Settings, Backup or Logs page shows an error | These pages are prepared automatically on the first visit after installation: reload the page. If the error persists, see `var/logs/ui.log`. |
| `/setup/` returns *403 Forbidden* | Normal after installation. |

# Updating CSWeb

Do not update during fieldwork.

1. Make a cPanel backup (database and home directory).
2. Create the folder `/home/<cpanel-user>/new` and extract the new zip there. This creates `new/csweb`.
3. Copy into `new/csweb`, from the current installation: the file `src/config.php` (written by the setup wizard) and the folders `files/` and `files_csweb/`.
4. Rename `csweb` to `csweb-old`, move `new/csweb` to `/home/<cpanel-user>/csweb`, and empty the folder `csweb/var/cache`.
5. Repeat Step 4 (PHP version and settings).
6. Run the checklist of Step 7. Keep `csweb-old` until the update is validated.

# Support

CSWeb Community: <https://github.com/BOUNADRAME/csweb-community>. Report issues under *Issues* with the content of `BUILD-INFO.txt` and the relevant lines of `var/logs/`.

Installation assistance and dedicated server set-up are available from StatPro Consulting.
