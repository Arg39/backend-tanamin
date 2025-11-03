<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Builder;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use App\Models\{Course, ModuleCourse, LessonCourse, LessonMaterial, LessonQuiz};

class CourseTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_can_fill_and_get_attributes()
    {
        $data = [
            'id' => 'course-uuid',
            'category_id' => 'cat-1',
            'instructor_id' => 'inst-1',
            'title' => 'Test Course',
            'image' => 'img.jpg',
            'level' => 'beginner',
            'status' => 'published',
            'detail' => 'detail text',
            'price' => 100,
            'discount_type' => 'percent',
            'discount_value' => 10,
            'discount_start_at' => '2025-01-01',
            'discount_end_at' => '2025-01-31',
            'is_discount_active' => true,
        ];

        $course = new Course($data);

        $this->assertEquals('course-uuid', $course->id);
        $this->assertEquals('Test Course', $course->title);
        $this->assertEquals(100, $course->price);
        $this->assertTrue($course->is_discount_active);
    }

    #[Test]
    public function it_has_relationship_methods()
    {
        $course = new Course();

        $this->assertInstanceOf(BelongsTo::class, $course->category());
        $this->assertInstanceOf(BelongsTo::class, $course->instructor());
        $this->assertInstanceOf(HasMany::class, $course->attributes());
        $this->assertInstanceOf(HasMany::class, $course->descriptions());
        $this->assertInstanceOf(HasMany::class, $course->prerequisites());
        $this->assertInstanceOf(HasMany::class, $course->modules());
        $this->assertInstanceOf(BelongsToMany::class, $course->participants());
        $this->assertInstanceOf(HasMany::class, $course->reviews());
        $this->assertInstanceOf(HasMany::class, $course->couponUsages());
    }

    #[Test]
    public function it_handles_active_discount_accessors()
    {
        $course = new Course([
            'is_discount_active' => false,
            'discount_value' => 20,
            'discount_type' => 'amount',
        ]);

        $this->assertFalse($course->active_discount);
        $this->assertNull($course->active_discount_value);
        $this->assertNull($course->active_discount_type);

        $course->is_discount_active = true;
        $this->assertTrue($course->active_discount);
        $this->assertEquals(20, $course->active_discount_value);
        $this->assertEquals('amount', $course->active_discount_type);
    }

    #[Test]
    public function scopes_return_query_builder()
    {
        $query = Course::query();

        $this->assertInstanceOf(Builder::class, $query->search('term'));
        $this->assertInstanceOf(Builder::class, $query->category('cat-1'));
        $this->assertInstanceOf(Builder::class, $query->instructor('inst-1'));
        $this->assertInstanceOf(Builder::class, $query->dateRange('2025-01-01', '2025-01-31'));
    }

    #[Test]
    public function it_calculates_detail_ratings_from_reviews_relation()
    {
        // Mock the relation chain: selectRaw->groupBy->pluck->toArray
        $mockRelation = Mockery::mock();
        $mockRelation->shouldReceive('selectRaw')->once()->andReturnSelf();
        $mockRelation->shouldReceive('groupBy')->once()->andReturnSelf();
        $mockRelation->shouldReceive('pluck')->once()->andReturn(collect([5 => 3, 4 => 1]));
        // toArray() is called on the pluck result inside the model; pluck returns Collection so toArray exists
        $partial = Mockery::mock(Course::class)->makePartial();
        $partial->shouldReceive('reviews')->andReturn($mockRelation);

        $detail = $partial->getDetailRatings();

        $this->assertIsArray($detail);
        $this->assertCount(5, $detail);
        $this->assertEquals(3, $detail[0]['total']); // rating 5
        $this->assertEquals(1, $detail[1]['total']); // rating 4
        $this->assertEquals(0, $detail[4]['total']); // rating 1
    }

    #[Test]
    public function it_calculates_avg_and_total_ratings_from_reviews()
    {
        $mockRelation = Mockery::mock();
        $mockRelation->shouldReceive('avg')->with('rating')->andReturn(4.25);
        $mockRelation->shouldReceive('count')->andReturn(8);

        $partial = Mockery::mock(Course::class)->makePartial();
        $partial->shouldReceive('reviews')->andReturn($mockRelation);

        $this->assertEquals(4.25, $partial->avg_rating);
        $this->assertEquals(8, $partial->total_ratings);
    }

    #[Test]
    public function it_counts_total_materials_and_quiz_via_nested_static_calls()
    {
        $this->markTestSkipped('Skipping test that aliases already-loaded classes (mock alias conflict).');

        // Mock semua model terkait
        $mockModule = Mockery::mock('alias:' . ModuleCourse::class);
        $mockLesson = Mockery::mock('alias:' . LessonCourse::class);
        $mockMaterial = Mockery::mock('alias:' . LessonMaterial::class);
        $mockQuiz = Mockery::mock('alias:' . LessonQuiz::class);

        // Buat chain pluck() palsu
        $mockModule->shouldReceive('where')->once()->andReturnSelf();
        $mockModule->shouldReceive('pluck')->once()->andReturn(collect([10, 11]));

        $mockLesson->shouldReceive('whereIn')->once()->andReturnSelf();
        $mockLesson->shouldReceive('pluck')->once()->andReturn(collect([100, 101]));

        $mockMaterial->shouldReceive('whereIn')->once()->andReturnSelf();
        $mockMaterial->shouldReceive('count')->once()->andReturn(7);

        $mockQuiz->shouldReceive('whereIn')->once()->andReturnSelf();
        $mockQuiz->shouldReceive('count')->once()->andReturn(2);

        $course = new Course();
        $course->id = 'course-1';

        $this->assertEquals(7, $course->getTotalMaterialsAttribute());
        $this->assertEquals(2, $course->getTotalQuizAttribute());
    }
}
