<?php

/**
 * @package faq
 * @subpackage tests
 */
class FAQCsvBulkLoaderTest extends SapphireTest {

    protected static $fixture_file = 'FAQCsvBulkLoaderTest.yml';

    /**
     * Test category export will return blank instead of the default Object->toString() = className
     */
    public function testCategoryExport() {
        $callback = function ($category) {
            return $category->Name;
        };

        $this->assertTrue(is_callable($callback), 'Category field is a callable function');

        $faqInCategory = $this->objFromFixture('FAQ', 'faqInCategory');
        $faqNoCategory = $this->objFromFixture('FAQ', 'faqNoCategory');

        $noCategoryName = $callback($faqNoCategory->Category());
        $categoryName = $callback($faqInCategory->Category());

        $this->assertEquals('', $noCategoryName, 'No category name is returned');
        $this->assertEquals('Compliance', $categoryName, 'Category name is populated');
    }

}
