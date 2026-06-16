<?php

namespace tests\unit\models;

use app\models\Category;
use Codeception\Test\Unit;

class CategoryTest extends Unit
{
    public function testSlugValidation(): void
    {
        $category = new Category([
            'name' => 'Test',
            'slug' => 'Invalid Slug',
        ]);

        $this->assertFalse($category->validate(['slug']));
    }

    public function testValidSlug(): void
    {
        $category = new Category([
            'name' => 'Test',
            'slug' => 'valid-slug-1',
        ]);

        $this->assertTrue($category->validate(['slug']));
    }

    public function testAutoSlugFromName(): void
    {
        $category = new Category([
            'name' => 'Диваны и кресла',
        ]);
        $category->validate();

        $this->assertSame('divany-i-kresla', $category->slug);
    }
}
