<?php

namespace Tests\Unit\Controller\UserProfileController;

use App\Http\Controllers\Api\UserProfileController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserProfileGetStudentsTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Test getStudents filters by name and returns expected students.
     */
    public function test_get_students_filters_by_name_and_returns_results()
    {
        // Create student Alice
        $alice = User::create([
            'id' => Str::uuid()->toString(),
            'first_name' => 'Alice',
            'last_name' => 'Smith',
            'username' => 'alice_student',
            'email' => 'alice.student@example.com',
            'password' => Hash::make('password123'),
            'role' => 'student',
            'status' => 'active',
        ]);

        // Create student Bob
        $bob = User::create([
            'id' => Str::uuid()->toString(),
            'first_name' => 'Bob',
            'last_name' => 'Jones',
            'username' => 'bob_student',
            'email' => 'bob.student@example.com',
            'password' => Hash::make('password123'),
            'role' => 'student',
            'status' => 'active',
        ]);

        $controller = new UserProfileController();

        // Request with name filter for "Alice"
        $requestFiltered = new Request(['name' => 'Alice']);
        $responseFiltered = $controller->getStudents($requestFiltered);

        $this->assertEquals(200, $responseFiltered->getStatusCode(), 'Filtered request should return 200');

        $contentFiltered = $responseFiltered->getContent();
        // Should contain Alice's name and not Bob's
        $this->assertStringContainsString('Alice', $contentFiltered);
        $this->assertStringNotContainsString('Bob', $contentFiltered);

        // Request without filter to get all students
        $requestAll = new Request();
        $responseAll = $controller->getStudents($requestAll);

        $this->assertEquals(200, $responseAll->getStatusCode(), 'Unfiltered request should return 200');

        $contentAll = $responseAll->getContent();
        // Should contain both Alice and Bob
        $this->assertStringContainsString('Alice', $contentAll);
        $this->assertStringContainsString('Bob', $contentAll);
    }
}
