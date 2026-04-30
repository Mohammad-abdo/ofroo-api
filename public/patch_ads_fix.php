<?php
/**
 * ONE-TIME PATCH — fixes: Method Illuminate\Http\Request::validated does not exist
 * Access once via browser, then it self-deletes.
 */

$filePath = __DIR__ . '/../app/Http/Controllers/Api/AdminController.php';

if (!file_exists($filePath)) {
    die('❌ AdminController.php not found at: ' . $filePath);
}

$content = file_get_contents($filePath);

// Count existing occurrences to confirm state
$badPattern   = '$updateData = collect($request->validated())';
$goodPattern  = '$updateData = collect($validated)';

if (strpos($content, $goodPattern) !== false) {
    // Already fixed — just delete this file and exit
    unlink(__FILE__);
    die('✅ Already patched. Patch file deleted.');
}

if (strpos($content, $badPattern) === false) {
    die('❌ Target line not found. File may have different content. No changes made.');
}

// Apply fix 1: capture return value of validate()
$content = str_replace(
    '$request->validate([',
    '$validated = $request->validate([',
    $content,
    $count1
);

// Apply fix 2: use $validated instead of $request->validated()
$content = str_replace(
    '$updateData = collect($request->validated())',
    '$updateData = collect($validated)',
    $content,
    $count2
);

if (file_put_contents($filePath, $content) === false) {
    die('❌ Could not write file. Check permissions.');
}

// Self-delete
unlink(__FILE__);

echo '✅ Patch applied successfully!<br>';
echo "- validate() return captured into \$validated<br>";
echo "- collect(\$validated) used instead of \$request->validated()<br>";
echo '<br>Patch file has been deleted. You can now test editing an ad.';
