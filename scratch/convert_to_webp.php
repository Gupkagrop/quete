<?php
$imgDir = dirname(__DIR__) . '/assets/img/';
$files = [
    'joystick.png',
    'slide1.png',
    'slide2.png',
    'slide3.png',
    'slide4.png',
    'slide5.png'
];

foreach ($files as $file) {
    $src = $imgDir . $file;
    $dst = $imgDir . str_replace('.png', '.webp', $file);
    
    if (file_exists($src)) {
        $image = @imagecreatefrompng($src);
        if ($image !== false) {
            imagepalettetotruecolor($image);
            imagealphasave($image, true);
            if (imagewebp($image, $dst, 85)) {
                echo "Converted $file to WebP (" . filesize($dst) . " bytes)\n";
            } else {
                echo "Failed to save WebP for $file\n";
            }
            imagedestroy($image);
        } else {
            echo "Failed to open $file\n";
        }
    } else {
        echo "File $file not found\n";
    }
}
