<?php
/**
 * Extends Csv loader to handle Categories (Taxonomy DataObject) better.
 *
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
     * Avoids creating new categories if not found in the root taxonomy
     * It will get the right CategoryID link, or leave the FAQ without categories.
     */
    public static function getCategoryByName(&$obj, $val, $record)
    {
        $val = trim($val);

        $root = FAQ::getRootCategory();
        if (!$root || !$root->exists()) {
            return null;
        }

        $category = $root->getChildDeep(array('Name' => trim($val)));

        if ($category && $category->exists()) {
            $obj->CategoryID = $category->ID;
            $obj->write();
        }
    }
}
