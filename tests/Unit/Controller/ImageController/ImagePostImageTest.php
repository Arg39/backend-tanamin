<?php

namespace Tests\Unit\Controller\ImageController;

use App\Http\Controllers\Api\ImageController;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImagePostImageTest extends TestCase
{
    /**
     * Test postImage stores the uploaded image on the 'public' disk and returns its URL.
     */
    public function test_post_image_stores_file_and_returns_url(): void
    {
        // Use fake storage so no real filesystem interaction happens
        Storage::fake('public');

        // Create a fake uploaded image
        $file = UploadedFile::fake()->image('test.jpg', 100, 100)->size(500);

        // Create a request and attach the fake file
        $request = Request::create('/', 'POST');
        $request->files->set('image', $file);

        // Call controller
        $controller = new ImageController();
        $response = $controller->postImage($request);

        // Assert HTTP 200
        $this->assertEquals(200, $response->getStatusCode());

        // Decode JSON response
        $data = $response->getData(true);
        $this->assertArrayHasKey('url', $data);

        // Ensure the file was stored in the wysiwyg folder on the public disk
        $storedFiles = Storage::disk('public')->allFiles('wysiwyg');
        $this->assertCount(1, $storedFiles);

        // Build expected URL and compare with response
        $expectedUrl = Storage::url($storedFiles[0]);
        $this->assertEquals($expectedUrl, $data['url']);
    }
}
