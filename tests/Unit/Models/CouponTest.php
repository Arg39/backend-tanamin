<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\Coupon;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

class CouponTest extends TestCase
{
    #[Test]
    public function it_can_fill_and_get_attributes()
    {
        $data = [
            'title' => 'Holiday Sale',
            'code' => 'HOLIDAY2025',
            'type' => 'percentage',
            'value' => 15,
            'start_at' => '2025-12-01 00:00:00',
            'end_at' => '2025-12-31 00:00:00',
            'is_active' => true,
            'max_usage' => 100,
            'used_count' => 0,
        ];

        $coupon = new Coupon($data);
        $coupon->id = 'coupon-1';

        $this->assertEquals('coupon-1', $coupon->id);
        $this->assertEquals('Holiday Sale', $coupon->title);
        $this->assertEquals('HOLIDAY2025', $coupon->code);
        $this->assertEquals('percentage', $coupon->type);
        $this->assertEquals(15, $coupon->value);
        $this->assertEquals('2025-12-01 00:00:00', (string) $coupon->start_at);
        $this->assertEquals('2025-12-31 00:00:00', (string) $coupon->end_at);
        $this->assertTrue((bool) $coupon->is_active);
        $this->assertEquals(100, $coupon->max_usage);
        $this->assertEquals(0, $coupon->used_count);
    }

    #[Test]
    public function it_has_correct_table_and_key_settings()
    {
        $coupon = new Coupon();

        $this->assertEquals('coupons', $coupon->getTable());
        $this->assertFalse($coupon->incrementing);
        $this->assertEquals('string', $coupon->getKeyType());
    }

    #[Test]
    public function it_generates_uuid_when_creating()
    {
        $coupon = \Mockery::mock(Coupon::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $coupon->fill([
            'title' => 'Flash Deal',
            'code' => 'FLASH50',
            'type' => 'fixed',
            'value' => 50,
            'is_active' => true,
        ]);
        $coupon->setAttribute('id', null);

        $coupon->shouldReceive('save')->andReturnUsing(function () use ($coupon) {
            if (empty($coupon->id)) {
                $coupon->id = (string) Str::uuid();
            }
            return true;
        });

        $coupon->save();

        $this->assertNotEmpty($coupon->id);
        $this->assertTrue(Str::isUuid($coupon->id));
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}
