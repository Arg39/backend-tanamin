<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\Bookmark;
use App\Models\User;
use App\Models\Course;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use PHPUnit\Framework\Attributes\Test;

class BookmarkTest extends TestCase
{
    #[Test]
    public function it_can_fill_and_get_attributes()
    {
        $data = [
            'id' => 'bookmark-1',
            'user_id' => 'user-1',
            'course_id' => 'course-1',
        ];

        $bookmark = new Bookmark($data);

        $this->assertEquals('bookmark-1', $bookmark->id);
        $this->assertEquals('user-1', $bookmark->user_id);
        $this->assertEquals('course-1', $bookmark->course_id);
    }

    #[Test]
    public function it_has_belongs_to_user_relationship()
    {
        $bookmark = new Bookmark();
        $relation = $bookmark->user();

        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertEquals(User::class, $relation->getRelated()::class);
    }

    #[Test]
    public function it_has_belongs_to_course_relationship()
    {
        $bookmark = new Bookmark();
        $relation = $bookmark->course();

        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertEquals(Course::class, $relation->getRelated()::class);
    }

    #[Test]
    public function it_has_correct_table_and_key_settings()
    {
        $bookmark = new Bookmark();

        $this->assertEquals('bookmark', $bookmark->getTable());
        $this->assertFalse($bookmark->incrementing);
        $this->assertEquals('string', $bookmark->getKeyType());
    }
}
