<?php
$cfg = require __DIR__.'/config.php';
if (file_exists(__DIR__.'/install.lock')) die('Already installed. Delete install.php from the server.');
try {
  $db = new PDO("mysql:host={$cfg['host']};charset=utf8mb4", $cfg['user'], $cfg['pass'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
  try { $db->exec("CREATE DATABASE IF NOT EXISTS `{$cfg['db']}` CHARACTER SET utf8mb4"); } catch (Throwable $e) {}
  $db->exec("USE `{$cfg['db']}`");
  $db->exec("CREATE TABLE IF NOT EXISTS users(id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(20) UNIQUE, roll VARCHAR(30) UNIQUE, pass_hash VARCHAR(255), name VARCHAR(80), role ENUM('member','admin') DEFAULT 'member', status ENUM('pending','approved','rejected') DEFAULT 'pending', pic VARCHAR(80), city VARCHAR(80) DEFAULT '', pos VARCHAR(80) DEFAULT '', job VARCHAR(120) DEFAULT '', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) CHARACTER SET utf8mb4");
  $db->exec("CREATE TABLE IF NOT EXISTS posts(id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, kind ENUM('post','job') DEFAULT 'post', title VARCHAR(120) DEFAULT '', loc VARCHAR(120) DEFAULT '', body TEXT NOT NULL, img VARCHAR(80) NULL, is_ad TINYINT DEFAULT 0, reports INT DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE) CHARACTER SET utf8mb4");
  $db->exec("CREATE TABLE IF NOT EXISTS likes(post_id INT, user_id INT, PRIMARY KEY(post_id,user_id), FOREIGN KEY(post_id) REFERENCES posts(id) ON DELETE CASCADE, FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE)");
  $db->exec("CREATE TABLE IF NOT EXISTS comments(id INT AUTO_INCREMENT PRIMARY KEY, post_id INT, user_id INT, body VARCHAR(500), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(post_id) REFERENCES posts(id) ON DELETE CASCADE, FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE) CHARACTER SET utf8mb4");
  $db->exec("CREATE TABLE IF NOT EXISTS settings(k VARCHAR(20) PRIMARY KEY, v TEXT)  CHARACTER SET utf8mb4");
  $db->prepare("INSERT IGNORE INTO users(username,roll,pass_hash,name,role,status,pic) VALUES(?,?,?,?,'admin','approved','')")
     ->execute([$cfg['admin_user'], strtoupper($cfg['admin_roll']), password_hash($cfg['admin_pass'], PASSWORD_DEFAULT), 'Batch Admin']);
  $db->prepare("INSERT IGNORE INTO settings(k,v) VALUES('motd',?),('motd_at',?)")->execute(['Welcome to CS-2K23. One Batch. One Community. Forever.', time()]);
  file_put_contents(__DIR__.'/install.lock', date('c'));
  echo "<h2>CS-2K23 installed</h2><p>Admin login: <b>{$cfg['admin_user']}</b> / roll <b>{$cfg['admin_roll']}</b> / your admin_pass from config.php.</p><p><b>Now delete install.php</b>, then open <a href='index.html'>the app</a>.</p>";
} catch (Throwable $e) { echo '<h2>Install failed</h2><pre>'.htmlspecialchars($e->getMessage()).'</pre><p>Check config.php.</p>'; }
