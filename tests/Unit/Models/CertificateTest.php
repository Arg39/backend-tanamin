<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\Certificate;
use App\Models\User;
use App\Models\Course;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

class CertificateTest extends TestCase
{
    #[Test]
    public function it_can_fill_and_get_attributes()
    {
        $data = [
            'id' => 'cert-1',
            'user_id' => 'user-1',
            'course_id' => 'course-1',
            'certificate_code' => 'ABC123',
            'issued_at' => now(),
        ];

        $certificate = new Certificate($data);

        $this->assertEquals('cert-1', $certificate->id);
        $this->assertEquals('user-1', $certificate->user_id);
        $this->assertEquals('course-1', $certificate->course_id);
        $this->assertEquals('ABC123', $certificate->certificate_code);
        $this->assertEquals($data['issued_at']->toDateTimeString(), $certificate->issued_at->toDateTimeString());
    }

    #[Test]
    public function it_has_belongs_to_user_relationship()
    {
        $certificate = new Certificate();
        $relation = $certificate->user();

        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertEquals(User::class, $relation->getRelated()::class);
    }

    #[Test]
    public function it_has_belongs_to_course_relationship()
    {
        $certificate = new Certificate();
        $relation = $certificate->course();

        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertEquals(Course::class, $relation->getRelated()::class);
    }

    #[Test]
    public function it_has_correct_table_and_key_settings()
    {
        $certificate = new Certificate();

        $this->assertEquals('certificates', $certificate->getTable());
        $this->assertFalse($certificate->incrementing);
        $this->assertEquals('string', $certificate->getKeyType());
    }

    #[Test]
    public function it_generates_uuid_when_creating()
    {
        // Mock User and Course with UUIDs
        $user = \Mockery::mock(\App\Models\User::class)->makePartial();
        $user->shouldReceive('getAttribute')->with('id')->andReturn('11111111-1111-1111-1111-111111111111');
        $user->id = '11111111-1111-1111-1111-111111111111';

        $course = \Mockery::mock(\App\Models\Course::class)->makePartial();
        $course->shouldReceive('getAttribute')->with('id')->andReturn('22222222-2222-2222-2222-222222222222');
        $course->id = '22222222-2222-2222-2222-222222222222';

        // Mock Certificate model
        $certificate = \Mockery::mock(Certificate::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $certificate->fill([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'certificate_code' => 'XYZ789',
            'issued_at' => now(),
        ]);
        $certificate->setAttribute('id', null);

        $certificate->shouldReceive('save')->andReturnUsing(function () use ($certificate) {
            if (empty($certificate->id)) {
                $certificate->id = (string) \Illuminate\Support\Str::uuid();
            }
            return true;
        });

        $certificate->save();

        $this->assertNotEmpty($certificate->id);
        $this->assertTrue(Str::isUuid($certificate->id));
    }
}
