<?php
/**
 * Storage Link Script
 * 
 * Run this script ONCE on your server to create the storage symlink.
 * Access via: https://yourdomain.com/storage_link.php
 * 
 * DELETE THIS FILE AFTER RUNNING!
 */

$target = dirname(__DIR__) . '/storage/app/public';
$link = __DIR__ . '/storage';

echo "<h1>Storage Link Setup</h1>";

if (is_link($link)) {
    echo "<p style='color: green;'>✅ Storage link already exists!</p>";
    echo "<p>Link points to: " . readlink($link) . "</p>";
    echo "<p><strong>DELETE THIS FILE NOW!</strong></p>";
    exit;
}

if (file_exists($link)) {
    echo "<p style='color: orange;'>⚠️ A file or directory named 'storage' already exists.</p>";
    echo "<p>Please delete it manually and run this script again.</p>";
    exit;
}

if (symlink($target, $link)) {
    echo "<p style='color: green;'>✅ Storage link created successfully!</p>";
    echo "<p>Target: " . $target . "</p>";
    echo "<p>Link: " . $link . "</p>";
    echo "<p><strong>DELETE THIS FILE NOW!</strong></p>";
} else {
    echo "<p style='color: red;'>❌ Failed to create storage link.</p>";
    echo "<p>Please create it manually via SSH/FTP:</p>";
    echo "<code>ln -s " . $target . " " . $link . "</code>";
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Storage Link Setup</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        code { background: #f4f4f4; padding: 10px; display: block; margin: 10px 0; }
        a { color: blue; }
    </style>
</head>
<body>
    <p>Files in public folder:</p>
    <ul>
        <?php
        $items = scandir(__DIR__);
        foreach ($items as $item) {
            if ($item !== '.' && $item !== '..') {
                echo "<li>" . $item . "</li>";
            }
        }
        ?>
    </ul>
</body>
</html>
