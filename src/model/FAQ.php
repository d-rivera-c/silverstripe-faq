<?php

namespace SilverStripe\Faq\Model;

use SilverStripe\Control\Controller;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\Forms\TreeDropdownField;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Taxonomy\TaxonomyTerm;
use SilverStripe\Faq\Extensions\FAQTaxonomyTermExtension;

/**
 * DataObject for a single FAQ related to the FAQ search module.
 * Provides db fields for a question and an answer.
 *
 * @see FAQAdmin for FAQ ModelAdmin.
 */
class FAQ extends DataObject
{
    /**
     * @var array
     */
    private static $db = [
        'Question' => 'Varchar(255)',
        'Answer'   => 'HTMLText',
        'Keywords' => 'Text',
    ];

    /**
     * @var array
     */
    private static $summary_fields = [
        'Question'       => 'Question',
        'Answer.Summary' => 'Answer',
        'Category.Name'  => 'Category',
    ];

    /**
     * @var array
     */
    private static $has_one = [
        'Category' => TaxonomyTerm::class,
    ];

    /**
     * Search boost for questions.
     *
     * @config
     * @var string
     */
    private static $question_boost = '3';

    /**
     * Search boost for answer
     *
     * @config
     * @var string
     */
    private static $answer_boost = '1';

    /**
     * Search boost for keywords
     *
     * @config
     * @var string
     */
    private static $keywords_boost = '4';

    /**
     * Name of the taxonomy to use for categories
     *
     * @config
     * @var string
     */
    private static $taxonomy_name = 'FAQ Categories';

    /**
     * @var string
     */
    private static $table_name = "FAQ";

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
            TaxonomyTerm::class,
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
     * Gets the root category for the FAQs
     * If it doesn't find it it creates it
     *
     * @return null|TaxonomyTerm root category of FAQs
     */
    public static function getRootCategory()
    {
        $taxName = Config::inst()->get(FAQ::class, 'taxonomy_name');

        $root = FAQTaxonomyTermExtension::getOrCreate(
            ['Name' => $taxName],
            ['Name' => $taxName, 'ParentID' => 0]
        );

        return $root;
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
     *
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
}
