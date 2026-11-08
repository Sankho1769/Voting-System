# VerityVote — System & XAMPP Prerequisites

Before installing VerityVote, make sure your computer meets the following hardware and software requirements.

---

## 1. Operating System & Hardware

- **Operating System:** Windows 10 or Windows 11 (64-bit).
- **Processor:** 1 GHz or faster dual-core CPU.
- **Memory (RAM):** Minimum 4 GB RAM (8 GB recommended).
- **Disk Space:** At least 500 MB free space on drive `C:\` (for XAMPP and VerityVote).

---

## 2. Required Software: XAMPP for Windows

VerityVote is designed to run locally using the **Apache Friends XAMPP** distribution stack.

- **Stack Package:** **XAMPP for Windows**
- **Required PHP Version:** **PHP 8.2.x** or higher (PHP 8.2.12 or newer recommended).
- **Web Server:** Apache 2.4.x (included with XAMPP).
- **Database Engine:** MariaDB 10.4.x or MySQL 8.0.x (included with XAMPP).
- **Recommended Install Location:** `C:\xampp\` (default XAMPP location).

### Downloading XAMPP
If you do not have XAMPP installed:
1. Visit the official Apache Friends website:
   ```
   https://www.apachefriends.org/download.html
   ```
2. Download the installer for **Windows with PHP 8.2.x**.
3. Run the installer and accept the default destination folder `C:\xampp\`.
4. Ensure **Apache** and **MySQL** components are checked during installation.

---

## 3. Network Ports & Services

XAMPP services require two standard local network ports:

| Service | Default Port | Purpose | Potential Conflicts |
|---|---|---|---|
| **Apache** | **Port 80** & **443** | Serves web pages to your browser | IIS, World Wide Web Publishing Service, Skype |
| **MySQL / MariaDB** | **Port 3306** | Stores voting database | Existing standalone MySQL / MariaDB service |

*If either port is already occupied, see [`TROUBLESHOOTING.md`](./TROUBLESHOOTING.md) for how to release it or change the port.*

---

## 4. Required PHP Extensions

The following PHP extensions are used by VerityVote. In standard XAMPP for Windows, all of these are enabled by default:

- `mysqli` (Native MariaDB/MySQL database connectivity)
- `curl` (HTTP communications & health probes)
- `mbstring` (Multibyte UTF-8 institutional name processing)
- `fileinfo` (MIME verification for candidate photo uploads)
- `openssl` (Cryptographic tokens, HMAC receipts, password hashing)
- `json` (Structured logging and configuration payloads)
- `session` (Secure authentication portal state management)

*You can verify that all extensions are active at any time by running `C:\xampp\php\php.exe bin/install_check.php`.*

---

## 5. User Tools

You will need:
- **Windows File Explorer:** To extract ZIP files and copy configuration files.
- **Web Browser:** Google Chrome, Microsoft Edge, Mozilla Firefox, or Brave.
- **Windows PowerShell or Command Prompt:** To run initial setup and administrative commands.
- **Text Editor:** Notepad, Notepad++, or Visual Studio Code (to review or edit `config/config.php` if needed).
