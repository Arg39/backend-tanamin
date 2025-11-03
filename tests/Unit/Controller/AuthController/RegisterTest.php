<?php

namespace Tests\Unit\Controller\AuthController;

use App\Http\Controllers\Api\AuthController;
use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;

class RegisterTest extends TestCase
{
    use DatabaseTransactions;

    public function test_registers_user_successfully()
    {
        $request = new Request([
            'name' => 'John Doe',
            'username' => 'johndoe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $controller = new AuthController();
        $response = $controller->register($request);
        $responseData = $response->getData(true);

        $this->assertEquals('success', $responseData['status']);
        $this->assertEquals('User registered successfully', $responseData['message']);
        $this->assertArrayHasKey('token', $responseData['data']);
    }
}
