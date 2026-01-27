# BookStack Page Lock & PIN Protection

![BookStack](https://img.shields.io/badge/BookStack-Addon-0288D1?style=flat-square&logo=bookstack&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4?style=flat-square&logo=php&logoColor=white)
![Security](https://img.shields.io/badge/Security-PIN_Lock-critical?style=flat-square)
![License](https://img.shields.io/badge/license-MIT-green?style=flat-square)

A lightweight, PIN-based protection layer for [BookStack](https://www.bookstackapp.com/). This modification allows administrators to "lock" specific pages behind a custom PIN or a global Master PIN, effectively hiding the content until the correct code is entered.

---

## 🚀 Features

* **Per-Page Locking:** Secure specific pages without altering global role permissions.
* **Dual PIN Modes:** Pages can use a specific "Custom Password" or fall back to a global "Master PIN" defined in your `.env` file.
* **Native UI Integration:** Controls are embedded directly into the Page "Permissions" view.
* **Visual Indicators:** Automatically adds a lock symbol (🔒) to page titles and status indicators in the UI.
* **Search & Preview Scrubbing:** Prevents protected content from leaking into search results or list previews by clearing preview text for protected items.
* **CLI Management Tool:** Includes a terminal script to manage locks, update passwords, and remove protections.

---

## 🛠️ Installation

### 1. File Placement
This guide assumes you are using the [BookStack Logical Theme System](https://www.bookstackapp.com/docs/admin/themes/). Replace `themes/my-theme/` with your actual theme folder.

| Component | Source File | Destination | Description |
| :--- | :--- | :--- | :--- |
| **Logic** | `functions.php` | `themes/my-theme/functions.php` | Handles backend interception and PIN validation. |
| **Footer** | `footer.blade.php` | `themes/my-theme/layouts/parts/footer.blade.php` | Handles JS visual scrubbing of tags. |
| **UI** | `entity-permissions.blade.php` | `themes/my-theme/form/entity-permissions.blade.php` | Adds the lock interface to the permissions page. |
| **CLI Tool** | `manage-locks.php` | `/var/www/bookstack/manage-locks.php` | **Place in BookStack Root**. Admin tool for managing locks. |

### 2. Configuration
Open your BookStack `.env` file and add a Master PIN. This is used if a page is locked but no custom password is set.

```bash
# .env
SECURE_PAGE_PIN=123456
```

### 3. Usage: Web Interface

* Navigate to the page you wish to protect.
* Click Permissions in the sidebar.
* Scroll down to the PIN Protection card.
* Enter a Custom Password (optional) and click Enable Lock.

> [!WARNING]
> If you leave the password blank, the page will require the SECURE_PAGE_PIN defined in your .env.

The page title will automatically update to include "🔒" to indicate its protected status.

### 4. Usage: CLI Tool (manage-locks.php)
You can manage locks directly from the terminal using manage-locks.php. This is useful for auditing protected pages, resetting forgotten PINs, or bulk unlocking content.

How to run it
Open your terminal and navigate to your BookStack root directory (e.g., /var/www/bookstack).

Run the script using PHP:

```bash
cd <bookstack root directory>
php manage-locks.php
```

> [!WARNING]
> When you run the script, you will see a list of all currently protected pages:

```Plaintext
🔒 BookStack Secure Page Manager
================================
ID    | Page Title                               | Current Password    
----------------------------------------------------------------------
[1]   | Server Access Codes 🔒                  | [Master PIN]        
[2]   | HR Confidential 🔒                      | secret123           
----------------------------------------------------------------------
```
```plaintext
Select a page number to edit (or press Enter to exit): 
To Edit a Lock:

Type the ID number (e.g., 2) and press Enter.
```
The script will show the current status and ask for a new password.

```Plaintext
Selected: HR Confidential 🔒
Current Password: secret123
Enter NEW password (leave empty to use Master PIN, or type 'DELETE' to unlock):
Options:
```
Type a new password: Updates the lock to the new code.

Press Enter (Empty): Sets the page to use the global Master PIN.

Type DELETE: Removes the lock entirely and makes the page public again.
