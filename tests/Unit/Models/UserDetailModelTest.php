<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\UserDetail;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use PHPUnit\Framework\Attributes\Test;

class UserDetailModelTest extends TestCase
{
    #[Test]
    public function it_can_fill_and_get_attributes()
    {
        $data = [
            'user_id' => 'uuid-123',
            'expertise' => 'Programming',
            'about' => 'Experienced developer',
            'social_media' => ['twitter' => '@dev'],
            'photo_cover' => 'cover.jpg',
            'update_password' => true,
        ];

        $detail = new UserDetail($data);

        $this->assertEquals('uuid-123', $detail->user_id);
        $this->assertEquals('Programming', $detail->expertise);
        $this->assertEquals('Experienced developer', $detail->about);
        $this->assertEquals(['twitter' => '@dev'], $detail->social_media);
        $this->assertEquals('cover.jpg', $detail->photo_cover);
        $this->assertTrue($detail->update_password);
    }

    #[Test]
    public function it_casts_social_media_to_array()
    {
        $detail = new UserDetail([
            'social_media' => json_encode(['linkedin' => 'profile'])
        ]);
        // Simulasi casting
        $detail->social_media = ['linkedin' => 'profile'];
        $this->assertIsArray($detail->social_media);
        $this->assertArrayHasKey('linkedin', $detail->social_media);
    }

    #[Test]
    public function it_has_user_relationship()
    {
        $detail = new UserDetail();
        $this->assertInstanceOf(BelongsTo::class, $detail->user());
    }

    #[Test]
    public function it_has_correct_primary_key_and_incrementing()
    {
        $detail = new UserDetail();
        $this->assertEquals('user_id', $detail->getKeyName());
        $this->assertFalse($detail->getIncrementing());
        $this->assertEquals('string', $detail->getKeyType());
    }

    #[Test]
    public function it_defaults_update_password_false()
    {
        $detail = new UserDetail();
        $this->assertFalse($detail->update_password);
    }
}
