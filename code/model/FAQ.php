<?php
/**
 * DataObject for a single FAQ related to the FAQ search module.
 * Provides db fields for a question and an answer.
 *
 * @see FAQAdmin for FAQ ModelAdmin.
 */

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\PermissionProvider;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\DropdownField;
use SilverStripe\ORM\Filters\ExactMatchFilter;
use SilverStripe\ORM\Search\SearchContext;
use SilverStripe\Control\Controller;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Security\Permission;
use SilverStripe\Forms\TreeDropdownField;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordViewer;
use SilverStripe\Versioned\Versioned;
use SilverStripe\Versioned\VersionedGridFieldItemRequest;

class FAQ extends DataObject implements PermissionProvider
{
    private static $singular_name = 'FAQ';

    private static $db = array(
        'Question' => 'Varchar(255)',
        'Answer' => 'HTMLText',
        'Keywords' => 'Text',
        'TotalViews' => 'Int'
    );

    private static $summary_fields = array(
        'Question' => 'Question',
        'Answer.FirstSentence' => 'Answer',
        'Category.Name' => 'Category'
    );

    private static $has_one = array(
        'Category' => 'SilverStripe\Taxonomy\TaxonomyTerm'
    );

    private static $has_many = array(
        'Views' => 'FAQResults_Article'
    );

    private static $extensions = [
        Versioned::class,
        FAQExtension::class
    ];

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
     * Creates a custom FAQSearch search object, can override to prevent the field removals
     *
     * @return FAQSearch_SearchContext
     */
    public function getDefaultSearchContext()
    {
        $fields = $this->scaffoldSearchFields();
        $filters = $this->defaultSearchFilters();

        $fields->removeByName('Category');
        $categories = self::getRootCategory()->Children()->map('Name');
        $fields->push(
            DropdownField::create('Category__Name', 'Category Name', $categories)
            ->setEmptyString('(Any)')
        );

        $filters['Category.Name'] = ExactMatchFilter::create('Category.Name');

        return new SearchContext(
            $this->class,
            $fields,
            $filters
        );
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        // setup category dropdown field
        $taxonomyRoot = self::getRootCategory();
        $categoryField = new TreeDropdownField(
            'CategoryID',
            'Category',
            'SilverStripe\Taxonomy\TaxonomyTerm',
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
        $fields->addFieldToTab(
            'Root.Main',
            $categoryField,
            'Question'
        );

        $config = GridFieldConfig_RecordViewer::create();
        $config->getComponentByType(GridFieldDetailForm::class)->setItemRequestClass(VersionedGridFieldItemRequest::class);
        $fields->addFieldsToTab('Root.Views', array(
            ReadonlyField::create('TotalViews', 'Total Views', $this->TotalViews),
            GridField::create(
                'Views',
                'Views',
                $this->Views(),
                $config
            )
        ));

        $this->extend('updateFAQCMSFields', $fields);

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
     * Gets a link to the view page for each FAQ. If the tracking ID is set on
     * this object include it as a GET param in the link to this article.
     *
     * @return string Link to view this particular FAQ on the current FAQPage.
     */
    public function getLink()
    {
        $faqPage = FAQPage::get()->first();
        $link = '';

        if ($faqPage && $faqPage->exists() && $this->ID != 0) {
            // Include tracking ID if it is set
            if (isset($this->trackingID) && $this->trackingID) {
                $link = Controller::join_links(
                    $faqPage->Link(),
                    "view/",
                    $this->ID,
                    '?t=' . $this->trackingID
                );
            } else {
                $link = Controller::join_links(
                    $faqPage->Link(),
                    "view/",
                    $this->ID
                );
            }
        }

        return $link;
    }

    public function getTitle() {
        if ($this->Question) {
            return $this->Question;
        }
        return parent::getTitle();
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

    public function canEdit($member = null)
    {
        return Permission::check('FAQ_EDIT');
    }

    public function canDelete($member = null)
    {
        return Permission::check('FAQ_DELETE');
    }

    public function canCreate($member = null, $context = array())
    {
        return Permission::check('FAQ_CREATE');
    }

    public function canPublish($member = null)
    {
        return true;
    }

    public function canUnpublish($member = null)
    {
        return true;
    }

    public function canArchive($member = null)
    {
        return true;
    }

    public function canViewVersioned($member = null)
    {
        return true;
    }

    public function providePermissions()
    {
        return array(
            'FAQ_EDIT' => array(
                'name' => _t(
                    'Faq.EditPermissionLabel',
                    'Edit FAQs'
                ),
                'category' => _t(
                    'Faq.Category',
                    'FAQ'
                ),
            ),
            'FAQ_DELETE' => array(
                'name' => _t(
                    'Faq.DeletePermissionLabel',
                    'Delete FAQs'
                ),
                'category' => _t(
                    'Faq.Category',
                    'FAQ'
                ),
            ),
            'FAQ_CREATE' => array(
                'name' => _t(
                    'Faq.CreatePermissionLabel',
                    'Create FAQs'
                ),
                'category' => _t(
                    'Faq.Category',
                    'FAQ'
                ),
            )
        );
    }
}
