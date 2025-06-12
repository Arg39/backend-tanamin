<?php

namespace App\Traits;

trait WysiwygTrait
{
    public function getImagesToDeleteFromDetail($oldDetail, $newDetail)
    {
        $extractLocalImages = function($html) {
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

        $oldImages = $extractLocalImages($oldDetail);
        $newImages = $extractLocalImages($newDetail);

        $toDelete = array_diff($oldImages, $newImages);

        return array_values($toDelete);
    }

    public function removeDeletedImagesFromDetail($detailHtml, $imagesToDelete)
    {
        if (empty($imagesToDelete)) return $detailHtml;

        foreach ($imagesToDelete as $imgPath) {
            $pattern = '#<img[^>]+src="[^">]*' . preg_quote($imgPath, '#') . '[^">]*"[^>]*>#i';
            $detailHtml = preg_replace($pattern, '', $detailHtml);
        }
        return $detailHtml;
    }
}