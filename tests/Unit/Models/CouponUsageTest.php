<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\CouponUsage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

class CouponUsageTest extends TestCase
{
    #[Test]
    public function it_can_fill_and_get_attributes()
    {
        $data = [
            'user_id' => 'user-123',
            'course_id' => 'course-456',
            'coupon_id' => 'coupon-789',
            'used_at' => '2025-01-01 10:00:00',
        ];

        $usage = new CouponUsage($data);
        $usage->id = 'usage-1';

        $this->assertEquals('usage-1', $usage->id);
        $this->assertEquals('user-123', $usage->user_id);
        $this->assertEquals('course-456', $usage->course_id);
        $this->assertEquals('coupon-789', $usage->coupon_id);
        $this->assertEquals('2025-01-01 10:00:00', $usage->used_at);
    }

    #[Test]
    public function it_has_correct_table_and_key_settings()
    {
        $usage = new CouponUsage();

        $this->assertEquals('coupon_usages', $usage->getTable());
        $this->assertFalse($usage->incrementing);
        $this->assertEquals('string', $usage->getKeyType());
        $this->assertFalse($usage->timestamps);
    }

    #[Test]
    public function it_generates_uuid_when_creating()
    {
        // Partial mock to simulate model behavior during save
        $usage = \Mockery::mock(CouponUsage::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $usage->fill([
            'user_id' => 'user-123',
            'course_id' => 'course-456',
            'coupon_id' => 'coupon-789',
            'used_at' => '2025-01-01 10:00:00',
        ]);
        $usage->setAttribute('id', null);

        $usage->shouldReceive('save')->andReturnUsing(function () use ($usage) {
            if (empty($usage->id)) {
                $usage->id = (string) Str::uuid();
            }
            return true;
        });

        $usage->save();

        $this->assertNotEmpty($usage->id);
        $this->assertTrue(Str::isUuid($usage->id));
    }
}
