<?php

namespace Tests\Unit\Controller\UserProfileController;

use App\Http\Controllers\Api\UserProfileController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserProfileGetInstructorsTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Test getInstructors filters by name and returns expected instructors.
     */
    public function test_get_instructors_filters_by_name_and_returns_results()
    {
        // Create instructor Alice
        $alice = User::create([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
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
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'first_name' => 'Bob',
            'last_name' => 'Jones',
            'username' => 'bob_instructor',
            'email' => 'bob@example.com',
            'password' => Hash::make('password123'),
            'role' => 'instructor',
            'status' => 'active',
        ]);

        $controller = new UserProfileController();

        // Request with name filter for "Alice"
        $requestFiltered = new Request(['name' => 'Alice']);
        $responseFiltered = $controller->getInstructors($requestFiltered);

        $this->assertEquals(200, $responseFiltered->getStatusCode(), 'Filtered request should return 200');

        $contentFiltered = $responseFiltered->getContent();
        // Should contain Alice's name and not Bob's
        $this->assertStringContainsString('Alice', $contentFiltered);
        $this->assertStringNotContainsString('Bob', $contentFiltered);

        // Request without filter to get all instructors
        $requestAll = new Request();
        $responseAll = $controller->getInstructors($requestAll);

        $this->assertEquals(200, $responseAll->getStatusCode(), 'Unfiltered request should return 200');

        $contentAll = $responseAll->getContent();
        // Should contain both Alice and Bob
        $this->assertStringContainsString('Alice', $contentAll);
        $this->assertStringContainsString('Bob', $contentAll);
    }
}
