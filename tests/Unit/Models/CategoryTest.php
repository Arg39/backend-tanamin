<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\Category;
use App\Models\Course;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;

class CategoryTest extends TestCase
{
    #[Test]
    public function it_can_fill_and_get_attributes()
    {
        $data = [
            'id' => 'cat-1',
            'name' => 'Science',
            'image' => 'categories/science.png',
        ];

        $category = new Category($data);

        $this->assertEquals('cat-1', $category->id);
        $this->assertEquals('Science', $category->name);
        $this->assertEquals('categories/science.png', $category->image);
    }

    #[Test]
    public function it_has_correct_table_and_key_settings()
    {
        $category = new Category();

        $this->assertEquals('categories', $category->getTable());
        $this->assertFalse($category->incrementing);
        $this->assertEquals('string', $category->getKeyType());
    }

    #[Test]
    public function it_returns_image_url_accessor()
    {
        $category = new Category(['image' => 'categories/science.png']);

        Storage::shouldReceive('disk->url')
            ->with('categories/science.png')
            ->andReturn('http://localhost/storage/categories/science.png');

        $this->assertEquals(
            'http://localhost/storage/categories/science.png',
            $category->image_url
        );
    }

    #[Test]
    public function it_has_courses_relationship()
    {
        $category = new Category();
        $relation = $category->courses();

        $this->assertInstanceOf(HasMany::class, $relation);
        $this->assertEquals(Course::class, $relation->getRelated()::class);
    }

    #[Test]
    public function it_formats_created_at_and_updated_at()
    {
        $category = new Category();

        $category->setRawAttributes([
            'created_at' => '2024-06-01 12:34:56',
            'updated_at' => '2024-06-02 15:20:10',
        ]);

        $this->assertEquals('2024-06-01 12:34', $category->created_at);
        $this->assertEquals('2024-06-02 15:20', $category->updated_at);
    }
}
