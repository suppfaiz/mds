<?php
/**
 * Image Helper for Photo Compression
 */

if (!function_exists('compressImage')) {
    function compressImage($sourcePath, $quality = 80) {
        if (!file_exists($sourcePath)) {
            return false;
        }
        
        // Check if it is a valid image
        $info = @getimagesize($sourcePath);
        if ($info === false) {
            return false;
        }
        
        $mime = $info['mime'];
        $image = null;
        
        // Read the image based on format
        switch ($mime) {
            case 'image/jpeg':
            case 'image/jpg':
                $image = @imagecreatefromjpeg($sourcePath);
                if ($image) {
                    // Auto-rotate based on EXIF Orientation
                    if (function_exists('exif_read_data')) {
                        $exif = @exif_read_data($sourcePath);
                        if ($exif && isset($exif['Orientation'])) {
                            $orientation = $exif['Orientation'];
                            if ($orientation == 3) {
                                $image = imagerotate($image, 180, 0);
                            } elseif ($orientation == 6) {
                                $image = imagerotate($image, -90, 0);
                            } elseif ($orientation == 8) {
                                $image = imagerotate($image, 90, 0);
                            }
                        }
                    }
                    // Save compressed image
                    imagejpeg($image, $sourcePath, $quality);
                    imagedestroy($image);
                    return true;
                }
                break;
                
            case 'image/png':
                $image = @imagecreatefrompng($sourcePath);
                if ($image) {
                    // Keep transparency
                    imagealphablending($image, false);
                    imagesavealpha($image, true);
                    
                    // PNG quality is 0-9. Quality 80 translates roughly to compression level 6.
                    imagepng($image, $sourcePath, 6);
                    imagedestroy($image);
                    return true;
                }
                break;
        }
        
        return false;
    }
}
