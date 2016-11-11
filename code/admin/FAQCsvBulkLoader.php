<?php
/**
 * Extends Csv loader to handle Categories (Taxonomy DataObject) better.
 */
class FAQCsvBulkLoader extends CsvBulkLoader
{

    public $columnMap = array(
        'Question' => 'Question',
        'Answer' => 'Answer',
        'Keywords' => 'Keywords',
        'Category' => '->getCategoryByName'
    );

    public $duplicateChecks = array(
        'Question' => 'Question'
    );

    /**
     * Avoids creating new categories if not found in the root taxonomy by default.
     * It will get the right CategoryID link, or leave the FAQ without categories.
     */
    public static function getCategoryByName(&$obj, $val, $record)
    {
        $root = FAQ::getRootCategory();
        if (!$root || !$root->exists()) {
            return null;
        }

        $shouldCreateCategories = Config::inst()->get('FAQ', 'import_create_missing_category');
        $val = trim($val);

        $category = $root->getChildDeep(array('Name' => $val));

        // create category if it doesn't exists unless config stops it
        if ($shouldCreateCategories && (!$category || !$category->exists()) && $val) {
            $category = new TaxonomyTerm(array(
                'Name' => trim($val),
                'ParentID' => $root->ID
            ));
            $category->write();
        }

        if ($category && $category->exists()) {
            $obj->CategoryID = $category->ID;
            $obj->write();
        }
    }
}
