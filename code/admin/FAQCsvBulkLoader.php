<?php
/**
 * Extends Csv loader to handle Categories (Taxonomy DataObject) better.
 */
class FAQCsvBulkLoader extends CsvBulkLoader
{

    public $columnMap = array(
        'Question' => 'Question',
        'Answer' => '->formatAnswer',
        'Keywords' => 'Keywords',
        'Category' => '->getCategoryByName'
    );

    public $duplicateChecks = array(
        'Question' => 'Question'
    );

    /**
     * Formats the answer into HTML, by replacing new lines characters with <br />
     * Does not format if value already contains html
     *
     * @param $obj
     * @param $val
     * @param $record
     * @return string
     */
    public function formatAnswer(&$obj, $val, $record)
    {
        // is this already html?
        if (preg_match("/<[^<a-zA-Z]+>/", $val) === 1 ) {
            $answer = $val;
        }
        else {
            $answer = '<p>' . nl2br(Convert::raw2xml($val)) . '</p>';
        }

        $obj->Answer = $answer;
        $obj->write();

        return $answer;
    }

    /**
     * Avoids creating new categories if not found in the root taxonomy by default.
     * It will get the right CategoryID link, or leave the FAQ without categories.
     * @param $obj
     * @param $val
     * @param $record
     * @return string
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

        return $category;
    }
}
