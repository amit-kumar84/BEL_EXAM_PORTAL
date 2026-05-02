<?php
/**
 * Offline Library Setup Script
 * Downloads all required libraries for offline/intranet use
 * Run via: php assets/lib/setup.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$basePath = __DIR__;
$downloads = [
    // Bootstrap 5.3.2
    [
        'url' => 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css',
        'path' => $basePath . '/bootstrap/css/bootstrap.min.css',
        'name' => 'Bootstrap CSS'
    ],
    [
        'url' => 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js',
        'path' => $basePath . '/bootstrap/js/bootstrap.bundle.min.js',
        'name' => 'Bootstrap JS Bundle'
    ],
    
    // Font Awesome 6.5.1 CSS
    [
        'url' => 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css',
        'path' => $basePath . '/fontawesome/css/all.min.css',
        'name' => 'Font Awesome CSS'
    ],
    
    // Font Awesome Fonts (WOFF2 - modern browsers)
    [
        'url' => 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/webfonts/fa-solid-900.woff2',
        'path' => $basePath . '/fontawesome/webfonts/fa-solid-900.woff2',
        'name' => 'Font Awesome Solid Icons (WOFF2)',
        'binary' => true
    ],
    [
        'url' => 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/webfonts/fa-brands-400.woff2',
        'path' => $basePath . '/fontawesome/webfonts/fa-brands-400.woff2',
        'name' => 'Font Awesome Brands Icons (WOFF2)',
        'binary' => true
    ],
    [
        'url' => 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/webfonts/fa-regular-400.woff2',
        'path' => $basePath . '/fontawesome/webfonts/fa-regular-400.woff2',
        'name' => 'Font Awesome Regular Icons (WOFF2)',
        'binary' => true
    ],
    
    // Font Awesome Fonts (TTF - fallback)
    [
        'url' => 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/webfonts/fa-solid-900.ttf',
        'path' => $basePath . '/fontawesome/webfonts/fa-solid-900.ttf',
        'name' => 'Font Awesome Solid Icons (TTF)',
        'binary' => true
    ],
    [
        'url' => 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/webfonts/fa-brands-400.ttf',
        'path' => $basePath . '/fontawesome/webfonts/fa-brands-400.ttf',
        'name' => 'Font Awesome Brands Icons (TTF)',
        'binary' => true
    ],
    [
        'url' => 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/webfonts/fa-regular-400.ttf',
        'path' => $basePath . '/fontawesome/webfonts/fa-regular-400.ttf',
        'name' => 'Font Awesome Regular Icons (TTF)',
        'binary' => true
    ],
    
    // QRCode.js library
    [
        'url' => 'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js',
        'path' => $basePath . '/qrcode/qrcode.min.js',
        'name' => 'QRCode.js Library'
    ],
];

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║     BEL Examination Portal - Offline Library Setup        ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

// Create directories
$dirs = [
    $basePath . '/bootstrap/css',
    $basePath . '/bootstrap/js',
    $basePath . '/fontawesome/css',
    $basePath . '/fontawesome/webfonts',
    $basePath . '/qrcode',
];

foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
        echo "✓ Created directory: " . str_replace('\\', '/', $dir) . "\n";
    }
}

echo "\n";

// Download files
$success = 0;
$failed = 0;

foreach ($downloads as $file) {
    $name = $file['name'];
    $url = $file['url'];
    $path = $file['path'];
    $isBinary = $file['binary'] ?? false;
    
    echo "Downloading {$name}... ";
    
    try {
        $content = @file_get_contents($url);
        
        if ($content === false) {
            echo "✗ FAILED (cannot fetch URL)\n";
            $failed++;
            continue;
        }
        
        // Ensure directory exists
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        
        // Write file
        $written = file_put_contents($path, $content);
        
        if ($written === false) {
            echo "✗ FAILED (cannot write file)\n";
            $failed++;
            continue;
        }
        
        echo "✓ OK (" . round($written / 1024, 1) . " KB)\n";
        $success++;
        
    } catch (Exception $e) {
        echo "✗ ERROR: " . $e->getMessage() . "\n";
        $failed++;
    }
}

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║ Download Complete: $success succeeded, $failed failed            ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n";

if ($failed === 0) {
    echo "\n✓ All libraries downloaded successfully!\n";
    echo "✓ Application is now ready for offline/intranet use.\n";
} else {
    echo "\n⚠ Some files failed to download.\n";
    echo "⚠ Check your internet connection and try again.\n";
    echo "⚠ Manual download: See OFFLINE_SETUP.md for details.\n";
}

echo "\n";
?>
