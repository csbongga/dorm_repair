<?php
/**
 * Resize and save an uploaded image using GD.
 *
 * @param string $src     Temp file path (from $_FILES[...]['tmp_name'])
 * @param string $dest    Destination path on disk
 * @param int    $maxDim  Max width or height in pixels (default 1200)
 * @param int    $quality JPEG/WEBP quality 0–100 (default 80)
 * @return bool
 */
function resizeAndSave(string $src, string $dest, int $maxDim = 800, int $quality = 80): bool
{
    if (!extension_loaded('gd')) {
        return copy($src, $dest);
    }

    $info = @getimagesize($src);
    if (!$info) {
        return copy($src, $dest);
    }

    [$origW, $origH, $type] = $info;

    // Already within limit — skip resize, just copy
    if ($origW <= $maxDim && $origH <= $maxDim) {
        return copy($src, $dest);
    }

    // Scale proportionally
    if ($origW >= $origH) {
        $newW = $maxDim;
        $newH = (int)round($origH * $maxDim / $origW);
    } else {
        $newH = $maxDim;
        $newW = (int)round($origW * $maxDim / $origH);
    }

    $srcImg = match($type) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($src),
        IMAGETYPE_PNG  => @imagecreatefrompng($src),
        IMAGETYPE_GIF  => @imagecreatefromgif($src),
        IMAGETYPE_WEBP => @imagecreatefromwebp($src),
        default        => false,
    };

    if (!$srcImg) {
        return copy($src, $dest);
    }

    $dstImg = imagecreatetruecolor($newW, $newH);

    // Preserve alpha channel for PNG/GIF
    if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_GIF) {
        imagealphablending($dstImg, false);
        imagesavealpha($dstImg, true);
        $trans = imagecolorallocatealpha($dstImg, 0, 0, 0, 127);
        imagefilledrectangle($dstImg, 0, 0, $newW, $newH, $trans);
    }

    imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $newW, $newH, $origW, $origH);

    // PNG quality: GD uses 0–9 (0=no compression). Convert from 0–100 scale.
    $ok = match($type) {
        IMAGETYPE_JPEG => imagejpeg($dstImg, $dest, $quality),
        IMAGETYPE_PNG  => imagepng($dstImg, $dest, (int)round((100 - $quality) / 10)),
        IMAGETYPE_GIF  => imagegif($dstImg, $dest),
        IMAGETYPE_WEBP => imagewebp($dstImg, $dest, $quality),
        default        => false,
    };

    imagedestroy($srcImg);
    imagedestroy($dstImg);

    return $ok;
}
