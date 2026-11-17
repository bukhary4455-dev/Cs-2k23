# CS-2K23: Private Alumni Network (PHP + MySQL)

## 1. Upload to GitHub
```
git init && git add . && git commit -m "CS-2K23"
git branch -M main
git remote add origin https://github.com/YOUR-USERNAME/cs2k23.git
git push -u origin main
```
`config.php` is git-ignored on purpose (it holds passwords).

## 2. Hosting (free PHP + MySQL, e.g. InfinityFree)
1. Create a MySQL database in the hosting panel.
2. Copy `config.sample.php` to `config.php`, fill in the DB details and a strong `admin_pass`, and upload `config.php` to the server once (File Manager).
3. Open `https://your-site/install.php` once, then delete install.php from the server.
4. Open the site and log in as admin. Make sure `uploads/` is writable.

## 3. Auto-deploy on every push (optional)
In GitHub: Settings > Secrets and variables > Actions, add
FTP_SERVER, FTP_USERNAME, FTP_PASSWORD, FTP_DIR (for example `/htdocs/`).
Every push to `main` then uploads the code. `config.php` and `uploads/` stay untouched on the server.

## Local test (XAMPP)
Copy this folder to htdocs, copy config.sample.php to config.php, open /install.php.
