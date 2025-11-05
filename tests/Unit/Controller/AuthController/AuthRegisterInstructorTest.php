<?php

namespace Tests\Unit\Controller\AuthController;

use App\Http\Controllers\Api\AuthController;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Illuminate\Support\Str;

class AuthRegisterInstructorTest extends TestCase
{
    use DatabaseTransactions;

    public function test_registers_instructor_successfully()
    {
        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Teknologi',
            'description' => 'Kategori untuk kursus teknologi'
        ]);

        $request = new Request([
            'name' => 'Jane Doe',
            'username' => 'janedoe',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'instructor',
            'category_id' => $category->id,
        ]);

        $controller = new AuthController();
        $response = $controller->registerInstructor($request);
        $responseData = $response->getData(true);

        $this->assertEquals('success', $responseData['status']);
        $this->assertEquals('Admin/Instructor registered successfully', $responseData['message']);
    }
}
