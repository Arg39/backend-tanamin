<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;

class UserModelTest extends TestCase
{
    #[Test]
    public function it_can_fill_and_get_attributes()
    {
        $data = [
            'id' => 'uuid-123',
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'secret',
            'role' => 'student',
            'username' => 'johndoe',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'token' => 'sometoken',
            'telephone' => '08123456789',
            'photo_profile' => 'profile.jpg',
            'status' => 'active',
        ];

        $user = new User($data);

        $this->assertEquals('uuid-123', $user->id);
        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals('john@example.com', $user->email);
        $this->assertEquals('student', $user->role);
        $this->assertEquals('johndoe', $user->username);
        $this->assertEquals('John', $user->first_name);
        $this->assertEquals('Doe', $user->last_name);
        $this->assertEquals('08123456789', $user->telephone);
        $this->assertEquals('profile.jpg', $user->photo_profile);
        $this->assertEquals('active', $user->status);
    }

    #[Test]
    public function it_returns_full_name_accessor()
    {
        $user = new User([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'name' => 'Jane S.',
        ]);
        $this->assertEquals('Jane Smith', $user->full_name);

        $user2 = new User([
            'first_name' => '',
            'last_name' => '',
            'name' => 'Jane S.',
        ]);
        $this->assertEquals('Jane S.', $user2->full_name);
    }

    #[Test]
    public function it_hides_hidden_attributes_when_serialized()
    {
        $user = new User([
            'password' => 'secret',
            'token' => 'sometoken',
            'remember_token' => 'remember',
        ]);
        $array = $user->toArray();

        $this->assertArrayNotHasKey('password', $array);
        $this->assertArrayNotHasKey('token', $array);
        $this->assertArrayNotHasKey('remember_token', $array);
    }

    #[Test]
    public function it_returns_jwt_identifier_and_custom_claims()
    {
        $user = new User(['id' => 'uuid-123']);
        $this->assertEquals('uuid-123', $user->getJWTIdentifier());
        $this->assertEquals([], $user->getJWTCustomClaims());
    }

    #[Test]
    public function it_has_relationships_defined()
    {
        $user = new User();

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasOne::class, $user->detail());
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $user->courses());
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsToMany::class, $user->categoriesInstructor());
    }
}
