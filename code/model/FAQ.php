<?php
/**
 * DataObject for a single FAQ related to the FAQ search module.
 * Provides db fields for a question and an answer.
 *
 * @see FAQAdmin for FAQ ModelAdmin.
 */
class FAQ extends DataObject
{
    private static $db = array(
        'Question' => 'Varchar(255)',
        'Answer' => 'HTMLText',
        'Keywords' => 'Text'
    );

    private static $summary_fields = array(
        'Question' => 'Question',
        'Answer.Summary' => 'Answer',
        'Category.Name' => 'Category'
    );

    private static $has_one = array(
        'Category' => 'TaxonomyTerm'
    );

    /**
     * Search boost for questions.
     * @config
     * @var string
     */
    private static $question_boost = '3';

    /**
     * Search boost for answer
     * @config
     * @var string
     */
    private static $answer_boost = '1';

    /**
     * Search boost for keywords
     * @config
     * @var string
     */
    private static $keywords_boost = '4';

    /**
     * Name of the taxonomy to use for categories
     * @config
     * @var string
     */
    private static $taxonomy_name = 'FAQ Categories';

    /**
     * Add fields to manage FAQs.
     *
     * @return FieldList
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $this->extend('beforeGetCMSFields', $fields);

        // setup category dropdown field
        $taxonomyRoot = self::getRootCategory();
        $categoryField = new TreeDropdownField(
            'CategoryID',
            'Category',
            'TaxonomyTerm',
            'ID',
            'Name'
        );
        //change this to 0 if you want the root category to show
        $categoryField->setTreeBaseID($taxonomyRoot->ID);
        $categoryField->setDescription(sprintf(
            'Select one <a href="admin/taxonomy/TaxonomyTerm/EditForm/field/TaxonomyTerm/item/%d/#Root_Children">'
                    . 'FAQ Category</a>',
            $taxonomyRoot->ID
        ));
        $fields->addFieldToTab('Root.Main', $categoryField);

        $this->extend('updateGetCMSFields', $fields);
        return $fields;
    }

    /**
     * Set required fields for model form submission.
     *
     * @return RequiredFields
     */
    public function getCMSValidator()
    {
        return new RequiredFields('Question', 'Answer');
    }


    /**
     * Filters items based on member permissions or other criteria,
     * such as if a state is generally available for the current record.
     *
     * @param Member $member
     * @return boolean
     */
    public function canView($member = null)
    {
        $canView = true;
        $this->extend('updateCanView', $member, $canView);
        return $canView;
    }

    /**
     * Gets a link to the view page for each FAQ
     *
     * @return string Link to view this particular FAQ on the current FAQPage.
     */
    public function getLink()
    {
        $faqPage = Controller::curr();

        if (!$faqPage->exists() || $this->ID <= 0) {
            return '';
        }

        $this->extend('updateGetLink', $faqPage);
        return Controller::join_links(
            $faqPage->Link(),
            "view/",
            $this->ID
        );
    }

    /**
     * Gets all nested categories for FAQs
     *
     * @return ArrayList
     */
    public static function getAllCategories()
    {
        Deprecation::notice('2.0', 'getAllCategories is deprecated. Create extended function');
        $taxName = Config::inst()->get('FAQ', 'taxonomy_name');
        $root = FAQTaxonomyTermExtension::getOrCreate(
            array('Name' => $taxName),
            array('Name' => $taxName, 'ParentID' => 0)
        );
        return $root->Children();
    }

    /**
     * Gets the root category for the FAQs
     * If it doesn't find it it creates it
     *
     * @return null|TaxonomyTerm root category of FAQs
     */
    public static function getRootCategory()
    {
        $taxName = Config::inst()->get('FAQ', 'taxonomy_name');
        $root = FAQTaxonomyTermExtension::getOrCreate(
            array('Name' => $taxName),
            array('Name' => $taxName, 'ParentID' => 0)
        );
        return $root;
    }
}
