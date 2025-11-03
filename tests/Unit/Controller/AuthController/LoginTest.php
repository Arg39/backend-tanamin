<?php

namespace Tests\Unit\Controller\AuthController;

use App\Http\Controllers\Api\AuthController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class LoginTest extends TestCase
{
    use DatabaseTransactions;

    public function test_user_can_login_successfully()
    {
        $user = User::create([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'first_name' => 'John',
            'last_name' => 'Doe',
            'username' => 'johndoe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
            'role' => 'student',
            'status' => 'active',
        ]);

        JWTAuth::shouldReceive('attempt')
            ->once()
            ->andReturn('fake-jwt-token');

        JWTAuth::shouldReceive('user')
            ->once()
            ->andReturn($user);

        $request = new Request([
            'login' => 'johndoe',
            'password' => 'password123',
        ]);

        $controller = new AuthController();
        $response = $controller->login($request);
        $responseData = $response->getData(true);

        $this->assertEquals('success', $responseData['status']);
        $this->assertEquals('Login successful', $responseData['message']);
        $this->assertArrayHasKey('token', $responseData['data']);
        $this->assertArrayHasKey('user', $responseData['data']);
    }
}
