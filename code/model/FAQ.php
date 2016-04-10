<?php
/**
 * DataObject for a single FAQ related to the FAQ search module.
 * Provides db fields for a question and an answer.
 *
 * @see FAQAdmin for FAQ ModelAdmin.
 */
class FAQ extends DataObject
{

    private static $singular_name = 'FAQ';

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
     * Search boost defaults for fields.
     *
     * @config
     * @var    config
     * @string
     */
    private static $question_boost = '3';

    /**
     * @config
     */
    private static $answer_boost = '1';

    /**
     * @config
     */
    private static $keywords_boost = '4';

    /**
     * @config
     */
    private static $taxonomy_name = 'FAQ Categories';

    /**
     *
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

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
        $categoryField->setDescription(
            sprintf(
                'Select one <a href="admin/taxonomy/TaxonomyTerm/EditForm/field/TaxonomyTerm/item/%d/#Root_Children">'
                . 'FAQ Category</a>',
                $taxonomyRoot->ID
            )
        );
        $fields->addFieldToTab('Root.Main', $categoryField);
        return $fields;
    }

    /**
     * Set required fields for model form submission.
     */
    public function getCMSValidator()
    {
        return new RequiredFields('Question', 'Answer');
    }


    /**
     * Filters items based on member permissions or other criteria,
     * such as if a state is generally available for the current record.
     *
     * @param  Member
     * @return Boolean
     */
    public function canView($member = null)
    {
        return true;
    }

    /**
     * Gets a link to the view page for each FAQ
  *
     * @return string Link to view this particular FAQ on the current FAQPage.
     */
    public function getLink()
    {
        $faqPage = Controller::curr();

        if ($faqPage->exists() && $this->ID != 0) {
            return Controller::join_links(
                $faqPage->Link(),
                "view/",
                $this->ID
            );
        }

        return '';
    }

    /**
     * @return string "Read more" link text for the current FAQPage.
     */
    public function getMoreLinkText()
    {
        $faqPage = Controller::curr();

        if ($faqPage->exists() && $faqPage->ClassName === 'FAQPage') {
            return $faqPage->MoreLinkText;
        }

        return '';
    }

    /**
     * Gets all nested categories for FAQs
     * TODO: this, if it's required by SUP-75 or SUP-76, if not, delete
     */
    public static function getAllCategories()
    {
        $taxName = Config::inst()->get('FAQ', 'taxonomy_name');
        $root = FAQTaxonomyTermExtension::getOrCreate(
            array('Name'=> $taxName),
            array('Name'=> $taxName, 'ParentID'=> 0)
        );
        return $root->Children();
    }

    /**
     * Gets the root category for the FAQs
     * If it doesn't find it it creates it
     */
    public static function getRootCategory()
    {
        $taxName = Config::inst()->get('FAQ', 'taxonomy_name');
        $root = FAQTaxonomyTermExtension::getOrCreate(
            array('Name'=> $taxName),
            array('Name'=> $taxName, 'ParentID'=> 0)
        );
        return $root;
    }
}
