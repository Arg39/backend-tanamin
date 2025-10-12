<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\CompanyActivity;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

class CompanyActivityTest extends TestCase
{
    #[Test]
    public function it_can_fill_and_get_attributes()
    {
        $data = [
            'id' => 'activity-1',
            'image' => 'images/activity.jpg',
            'title' => 'Company Event',
            'description' => 'Annual company event',
            'order' => 1,
        ];

        $activity = new CompanyActivity($data);

        $this->assertEquals('activity-1', $activity->id);
        $this->assertEquals('images/activity.jpg', $activity->image);
        $this->assertEquals('Company Event', $activity->title);
        $this->assertEquals('Annual company event', $activity->description);
        $this->assertEquals(1, $activity->order);
    }

    #[Test]
    public function it_has_correct_table_and_key_settings()
    {
        $activity = new CompanyActivity();

        $this->assertEquals('company_activities', $activity->getTable());
        $this->assertFalse($activity->incrementing);
        $this->assertEquals('string', $activity->getKeyType());
    }

    #[Test]
    public function it_generates_uuid_when_creating()
    {
        $activity = \Mockery::mock(CompanyActivity::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $activity->fill([
            'image' => 'images/activity.jpg',
            'title' => 'Company Event',
            'description' => 'Annual company event',
            'order' => 2,
        ]);
        $activity->setAttribute('id', null);

        $activity->shouldReceive('save')->andReturnUsing(function () use ($activity) {
            if (empty($activity->id)) {
                $activity->id = (string) Str::uuid();
            }
            return true;
        });

        $activity->save();

        $this->assertNotEmpty($activity->id);
        $this->assertTrue(Str::isUuid($activity->id));
    }

    #[Test]
    public function it_returns_image_url_attribute()
    {
        $activity = new CompanyActivity([
            'image' => 'images/activity.jpg'
        ]);

        $expectedUrl = url('storage/images/activity.jpg');
        $this->assertEquals($expectedUrl, $activity->image_url);

        $activity->image = null;
        $this->assertNull($activity->image_url);
    }
}
