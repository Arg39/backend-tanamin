<?php

namespace App\Traits;

use Illuminate\Support\Facades\Storage;

trait WysiwygTrait
{
    /**
     * Handles WYSIWYG content update: compare, clean up, and delete unused images.
     *
     * @param string $oldHtml
     * @param string $newHtml
     * @return string $cleanedNewHtml
     */
    public function handleWysiwygUpdate(string $oldHtml, string $newHtml): string
    {
        $extractImages = function ($html) {
            $images = [];
            preg_match_all('/<img[^>]+src="([^">]+)"/', $html, $matches);
            if (isset($matches[1])) {
                foreach ($matches[1] as $imgUrl) {
                    if (strpos($imgUrl, '/storage/wysiwyg/') !== false) {
                        $path = preg_replace('#^.*?/storage/#', '', $imgUrl);
                        $images[] = $path;
                    }
                }
            }
            return $images;
        };

        $oldImages = $extractImages($oldHtml);
        $newImages = $extractImages($newHtml);
        $imagesToDelete = array_diff($oldImages, $newImages);

        // Delete image files from storage
        foreach ($imagesToDelete as $imgPath) {
            if (Storage::disk('public')->exists($imgPath)) {
                Storage::disk('public')->delete($imgPath);
            }
        }

        // Remove <img> tags for deleted images
        foreach ($imagesToDelete as $imgPath) {
            $pattern = '#<img[^>]+src="[^">]*' . preg_quote($imgPath, '#') . '[^">]*"[^>]*>#i';
            $newHtml = preg_replace($pattern, '', $newHtml);
        }

        return $newHtml;
    }

    public function deleteWysiwygImages(string $html): void
    {
        $extractImages = function ($html) {
            $images = [];
            preg_match_all('/<img[^>]+src="([^">]+)"/', $html, $matches);
            if (isset($matches[1])) {
                foreach ($matches[1] as $imgUrl) {
                    if (strpos($imgUrl, '/storage/wysiwyg/') !== false) {
                        $path = preg_replace('#^.*?/storage/#', '', $imgUrl);
                        $images[] = $path;
                    }
                }
            }
            return $images;
        };

        $images = $extractImages($html);

        foreach ($images as $imgPath) {
            if (Storage::disk('public')->exists($imgPath)) {
                Storage::disk('public')->delete($imgPath);
            }
        }
    }
}
