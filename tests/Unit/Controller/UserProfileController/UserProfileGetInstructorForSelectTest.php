<?php

namespace Tests\Unit\Controller\UserProfileController;

use App\Http\Controllers\Api\UserProfileController;
use App\Models\User;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserProfileGetInstructorForSelectTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Test getInstructorForSelect returns instructors and can filter by category_id.
     */
    public function test_get_instructor_for_select_returns_instructors_and_filters_by_category()
    {
        // Create instructor Alice
        $alice = User::create([
            'id' => Str::uuid()->toString(),
            'first_name' => 'Alice',
            'last_name' => 'Smith',
            'username' => 'alice_instructor',
            'email' => 'alice@example.com',
            'password' => Hash::make('password123'),
            'role' => 'instructor',
            'status' => 'active',
        ]);

        // Create instructor Bob
        $bob = User::create([
            'id' => Str::uuid()->toString(),
            'first_name' => 'Bob',
            'last_name' => 'Jones',
            'username' => 'bob_instructor',
            'email' => 'bob@example.com',
            'password' => Hash::make('password123'),
            'role' => 'instructor',
            'status' => 'active',
        ]);

        // Create a category and attach to Alice only
        $category = Category::create([
            'name' => 'Programming',
            'slug' => 'programming',
        ]);

        // attach via expected relationship name categoriesInstructor
        if (method_exists($alice, 'categoriesInstructor')) {
            $alice->categoriesInstructor()->attach($category->id);
        } elseif (method_exists($alice, 'categories')) {
            // fallback if relation name differs
            $alice->categories()->attach($category->id);
        }

        $controller = new UserProfileController();

        // Request without filters: should return both instructors
        $requestAll = new Request();
        $responseAll = $controller->getInstructorForSelect($requestAll);

        $this->assertEquals(200, $responseAll->getStatusCode(), 'Unfiltered request should return 200');

        $responseDataAll = $responseAll->getData(true);
        $this->assertArrayHasKey('message', $responseDataAll);
        $this->assertEquals('Instructor retrieved successfully', $responseDataAll['message']);
        $this->assertArrayHasKey('data', $responseDataAll);
        $this->assertIsArray($responseDataAll['data']);

        $namesAll = array_map(function ($item) {
            return $item['name'] ?? null;
        }, $responseDataAll['data']);

        $this->assertContains('Alice Smith', $namesAll);
        $this->assertContains('Bob Jones', $namesAll);

        // Request filtered by category_id: should return only Alice
        $requestFiltered = new Request(['category_id' => $category->id]);
        $responseFiltered = $controller->getInstructorForSelect($requestFiltered);

        $this->assertEquals(200, $responseFiltered->getStatusCode(), 'Filtered request should return 200');

        $responseDataFiltered = $responseFiltered->getData(true);
        $this->assertArrayHasKey('message', $responseDataFiltered);
        $this->assertEquals('Instructor retrieved successfully', $responseDataFiltered['message']);
        $this->assertArrayHasKey('data', $responseDataFiltered);
        $this->assertIsArray($responseDataFiltered['data']);

        $namesFiltered = array_map(function ($item) {
            return $item['name'] ?? null;
        }, $responseDataFiltered['data']);

        $this->assertContains('Alice Smith', $namesFiltered);
        $this->assertNotContains('Bob Jones', $namesFiltered);
    }
}
