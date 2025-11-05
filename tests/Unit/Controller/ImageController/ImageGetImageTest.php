<?php

namespace Tests\Unit\Controller\ImageController;

use App\Http\Controllers\Api\ImageController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageGetImageTest extends TestCase
{
    /**
     * Test getImage returns the image content and proper Content-Type when the file exists.
     */
    public function test_get_image_returns_image_response_when_file_exists(): void
    {
        $path = 'images';
        $filename = 'test.jpg';
        $fullPath = $path . '/' . $filename;
        $fakeImageContent = 'fake-image-bytes';
        $fakeMimeType = 'image/jpeg';

        // Mock Storage facade behavior
        Storage::shouldReceive('exists')
            ->once()
            ->with($fullPath)
            ->andReturn(true);

        Storage::shouldReceive('get')
            ->once()
            ->with($fullPath)
            ->andReturn($fakeImageContent);

        Storage::shouldReceive('mimeType')
            ->once()
            ->with($fullPath)
            ->andReturn($fakeMimeType);

        // Create a request without width/height (validation allows nullable)
        $request = Request::create('/', 'GET');

        $controller = new ImageController();
        $response = $controller->getImage($request, $path, $filename);

        // Assert HTTP 200
        $this->assertEquals(200, $response->getStatusCode());

        // Assert response content and header
        $this->assertEquals($fakeImageContent, $response->getContent());
        $this->assertEquals($fakeMimeType, $response->headers->get('Content-Type'));
    }

    /**
     * Test getImage returns 404 JSON when the file does not exist.
     */
    public function test_get_image_returns_404_when_not_found(): void
    {
        $path = 'images';
        $filename = 'missing.jpg';
        $fullPath = $path . '/' . $filename;

        // Mock Storage::exists to return false
        Storage::shouldReceive('exists')
            ->once()
            ->with($fullPath)
            ->andReturn(false);

        $request = Request::create('/', 'GET');

        $controller = new ImageController();
        $response = $controller->getImage($request, $path, $filename);

        // Assert HTTP 404
        $this->assertEquals(404, $response->getStatusCode());

        // Assert JSON message
        $data = $response->getData(true);
        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Image not found', $data['message']);
    }
}
