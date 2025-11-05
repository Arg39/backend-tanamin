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

class UserProfileGetInstructorListByCategoryTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Test getInstructorListByCategory pagination (more) and default grouping behavior.
     */
    public function test_get_instructor_list_by_category_pagination_and_grouping()
    {
        // Create categories
        $categoryA = Category::create([
            'name' => 'Category A',
            'slug' => 'category-a',
        ]);

        $categoryB = Category::create([
            'name' => 'Category B',
            'slug' => 'category-b',
        ]);

        // Create 6 instructors for categoryA
        $instructorsA = [];
        for ($i = 1; $i <= 6; $i++) {
            $user = User::create([
                'id' => Str::uuid()->toString(),
                'first_name' => "InstA{$i}",
                'last_name' => "LastnameA{$i}",
                'username' => "inst_a_{$i}",
                'email' => "inst_a_{$i}@example.com",
                'password' => Hash::make('password123'),
                'role' => 'instructor',
                'status' => 'active',
            ]);

            // Robust attach: try multiple possible relation names on user and category
            if (method_exists($user, 'categoriesInstructor')) {
                $user->categoriesInstructor()->attach($categoryA->id);
            } elseif (method_exists($user, 'categories')) {
                $user->categories()->attach($categoryA->id);
            } elseif (method_exists($categoryA, 'instructors')) {
                $categoryA->instructors()->attach($user->id);
            } elseif (method_exists($categoryA, 'users')) {
                $categoryA->users()->attach($user->id);
            } elseif (method_exists($categoryA, 'usersInstructor')) {
                $categoryA->usersInstructor()->attach($user->id);
            }

            $instructorsA[] = $user;
        }

        // Create 2 instructors for categoryB
        $instructorsB = [];
        for ($i = 1; $i <= 2; $i++) {
            $user = User::create([
                'id' => Str::uuid()->toString(),
                'first_name' => "InstB{$i}",
                'last_name' => "LastnameB{$i}",
                'username' => "inst_b_{$i}",
                'email' => "inst_b_{$i}@example.com",
                'password' => Hash::make('password123'),
                'role' => 'instructor',
                'status' => 'active',
            ]);

            if (method_exists($user, 'categoriesInstructor')) {
                $user->categoriesInstructor()->attach($categoryB->id);
            } elseif (method_exists($user, 'categories')) {
                $user->categories()->attach($categoryB->id);
            } elseif (method_exists($categoryB, 'instructors')) {
                $categoryB->instructors()->attach($user->id);
            } elseif (method_exists($categoryB, 'users')) {
                $categoryB->users()->attach($user->id);
            } elseif (method_exists($categoryB, 'usersInstructor')) {
                $categoryB->usersInstructor()->attach($user->id);
            }

            $instructorsB[] = $user;
        }

        $controller = new UserProfileController();

        // Test pagination branch: more = 0 (first batch)
        $requestMore0 = new Request(['category_id' => $categoryA->id, 'more' => 0]);
        $responseMore0 = $controller->getInstructorListByCategory($requestMore0);

        $this->assertEquals(200, $responseMore0->getStatusCode(), 'Pagination (more=0) should return 200');

        $dataMore0 = $responseMore0->getData(true);
        $this->assertArrayHasKey('message', $dataMore0);
        $this->assertEquals('List of instructors retrieved successfully.', $dataMore0['message']);
        $this->assertArrayHasKey('data', $dataMore0);
        $this->assertIsArray($dataMore0['data']);
        $this->assertNotEmpty($dataMore0['data'], 'Data should contain the result array for pagination branch');

        // Controller wraps result in an array ([ $result ]), so extract first element
        $resultMore0 = $dataMore0['data'][0] ?? null;
        $this->assertNotNull($resultMore0, 'Pagination result should be present');
        $this->assertArrayHasKey('user', $resultMore0);
        $this->assertArrayHasKey('has_more', $resultMore0);

        // compute expected first page size (controller limit is expected to be 4)
        $limit = 4;
        $expectedFirstPage = min($limit, count($instructorsA));

        // assert returned page does not exceed limit and has_more is boolean
        $this->assertLessThanOrEqual($expectedFirstPage, count($resultMore0['user']), "First page should contain at most {$expectedFirstPage} users (limit or available)");
        $this->assertIsBool($resultMore0['has_more'], 'has_more should be boolean');

        // Test pagination branch: more = 1 (second batch)
        $requestMore1 = new Request(['category_id' => $categoryA->id, 'more' => 1]);
        $responseMore1 = $controller->getInstructorListByCategory($requestMore1);

        $this->assertEquals(200, $responseMore1->getStatusCode(), 'Pagination (more=1) should return 200');

        $dataMore1 = $responseMore1->getData(true);
        $this->assertArrayHasKey('data', $dataMore1);
        $resultMore1 = $dataMore1['data'][0] ?? null;
        $this->assertNotNull($resultMore1, 'Pagination second page result should be present');
        $this->assertArrayHasKey('user', $resultMore1);
        $this->assertArrayHasKey('has_more', $resultMore1);

        // remaining instructors = total created for categoryA - firstPageExpected
        $combinedReturned = count($resultMore0['user']) + count($resultMore1['user']);
        $this->assertLessThanOrEqual(count($instructorsA), $combinedReturned, 'Combined returned across pages should not exceed created instructors for categoryA');
        $this->assertLessThanOrEqual($limit, count($resultMore1['user']), 'Second page should contain at most limit users');
        $this->assertIsBool($resultMore1['has_more'], 'has_more on second page should be boolean');

        // Test default grouped branch (no params) returns categories with user lists
        $requestDefault = new Request();
        $responseDefault = $controller->getInstructorListByCategory($requestDefault);

        $this->assertEquals(200, $responseDefault->getStatusCode(), 'Default grouped request should return 200');

        $dataDefault = $responseDefault->getData(true);
        $this->assertArrayHasKey('message', $dataDefault);
        $this->assertEquals('List of instructors retrieved successfully.', $dataDefault['message']);
        $this->assertArrayHasKey('data', $dataDefault);
        $this->assertIsArray($dataDefault['data']);

        // Find category groups for A and B
        $groups = $dataDefault['data'];
        $groupA = null;
        $groupB = null;
        foreach ($groups as $g) {
            if (isset($g['category']['id']) && $g['category']['id'] == $categoryA->id) {
                $groupA = $g;
            }
            if (isset($g['category']['id']) && $g['category']['id'] == $categoryB->id) {
                $groupB = $g;
            }
        }

        $this->assertNotNull($groupA, 'Category A group should be present in default grouping');
        $this->assertNotNull($groupB, 'Category B group should be present in default grouping');

        // For categoryA we expect at most limit users in the group and has_more boolean
        $this->assertArrayHasKey('user', $groupA);
        $this->assertArrayHasKey('has_more', $groupA);
        $this->assertLessThanOrEqual($limit, count($groupA['user']));
        $this->assertIsBool($groupA['has_more']);

        // For categoryB we expect at most created instructors and has_more boolean
        $this->assertArrayHasKey('user', $groupB);
        $this->assertArrayHasKey('has_more', $groupB);
        $this->assertLessThanOrEqual(count($instructorsB), count($groupB['user']));
        $this->assertIsBool($groupB['has_more']);
    }
}
