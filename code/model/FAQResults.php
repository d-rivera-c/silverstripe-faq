<?php

/**
 * Common fields and functionality for FAQ result sets and article views.
 */

use SilverStripe\Core\Extension;
use SilverStripe\ORM\DataObject;
use SilverStripe\Forms\GridField\GridFieldEditButton;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\Forms\GridField\GridFieldDetailForm_ItemRequest;
use SilverStripe\View\ArrayData;
use SilverStripe\Security\Permission;

class FAQResults_Extension extends Extension
{
    private static $singular_name = 'View';

    private static $db = array(
        'Useful' => "Enum('Y,N,U','U')", // Yes, No, Unset
        'Comment' => 'Varchar(255)',
        'SessionID' => 'Varchar(255)',
        'Archived' => 'Boolean'
    );

    private static $has_one = array(
        'Search' => 'FAQSearch'
    );

    /**
     * Helper for pretty printing useful value.
     *
     * @return string
     */
    public function getUsefulness()
    {
        switch ($this->owner->Useful) {
            case 'Y':
                return 'Yes';
            case 'N':
                return 'No';
            case 'U':
            default:
                return '';
        }
    }

    public function canView($member = false)
    {
        return Permission::check('FAQ_VIEW_SEARCH_LOGS');
    }

    public function canEdit($member = false)
    {
        return Permission::check('FAQ_EDIT_SEARCH_LOGS');
    }

    public function canDelete($member = false)
    {
        return false;
    }

    public function canCreate($member = false)
    {
        return false;
    }
}

/**
 * Represents a result set resulting from a search.
 */
class FAQResults extends DataObject
{
    private static $singular_name = 'Result Set';

    private static $db = array(
        'ArticleSet' => 'Varchar(255)',
        'SetSize' => 'Int'
    );

    private static $has_many = array(
        'ArticlesViewed' => 'FAQResults_Article'
    );

    private static $summary_fields = array(
        'getArticlesViewedIDs' => 'Articles viewed',
        'Created.Nice' => 'Date viewed',
        'getArticleIDs' => 'Articles displayed in results',
        'SetSize' => 'Total displayed'
    );

    /**
     * Get IDs of articles in this set
     *
     * @return string Comma separated list of IDs
     */
    public function getArticleIDs()
    {
        return trim($this->ArticleSet, '[]');
    }

    /**
     * Get articles that were actually viewed from this set.
     *
     * @return string Comma separated list of IDs
     */
    public function getArticlesViewedIDs()
    {
        $ids = 'None viewed';
        $views = $this->ArticlesViewed();
        if ($views && $views->exists()) {
            $ids = implode(array_keys($views->map('FAQID')->toArray()), ',');
        }
        return $ids;
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $fields->removeByName(array('ArticleSet', 'SessionID', 'SearchID', 'Useful', 'Comment', 'Archived'));
        $fields->removeFieldFromTab('Root', 'ArticlesViewed');

        // Get FAQs listed, the 'FIELD(ID,{IDs})' ensures they appear in the order provided
        $articleIDs = json_decode($this->ArticleSet);
        $articles = FAQ::get()
            ->where('ID IN (' . implode(',', $articleIDs) . ')')
            ->sort('FIELD(ID,' . implode(',', $articleIDs) .')');

        $fields->addFieldToTab('Root.Main', ReadonlyField::create('SetSize', 'Size of this results set'));

        $sort = new GridFieldSortableHeader();

        $columns = new GridFieldDataColumns();
        $columns->setDisplayFields(array(
            'ID' => 'ID',
            'Question' => 'Question',
            'Answer.FirstSentence' => 'Answer'
        ));

        if (!empty($articleIDs) && $articles->exists()) {
            $fields->addFieldToTab('Root.Main', GridField::create(
                'FAQ',
                'Article Set',
                $articles,
                $configSet = GridFieldConfig::create()
            ));

            $configSet->addComponents(
                new GridFieldButtonRow('before'),
                new GridFieldToolbarHeader(),
                $sort,
                $columns,
                new GridFieldEditButton(),
                new GridFieldDetailForm(),
                new GridFieldFooter()
            );
        }
        $articlesViewed = $this->ArticlesViewed();
        if ($articlesViewed->exists()) {
            $fields->addFieldToTab('Root.Main', GridField::create(
                'Articles',
                'Articles viewed',
                $articlesViewed,
                $configView = GridFieldConfig::create()
            ));

            $configView->addComponents(
                new GridFieldButtonRow('before'),
                new GridFieldToolbarHeader(),
                $sort,
                new GridFieldDataColumns(),
                new FAQResults_Article_EditButton(),
                new FAQResults_Article_DetailForm(),
                new GridFieldFooter()
            );
        }

        return $fields;
    }
}

/**
 * Represents views of individual articles from a search result set.
 */
class FAQResults_Article extends DataObject
{
    private static $singular_name = 'Article';

    private static $default_sort = 'Created DESC';

    /**
     * Whether to count a new view for the FAQ
     *
     * @var boolean
     */
    private $countView = false;

    private static $has_one = array(
        'FAQ' => 'FAQ',
        'ResultSet' => 'FAQResults'
    );

    private static $summary_fields = array(
        'FAQ.ID' => 'Article ID',
        'Created.Nice' => 'Date viewed',
        'FAQ.Question' => 'Question',
        'getUsefulness' => 'Usefulness Rating',
        'Comment' => 'Comment'
    );

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $fields->removeByName(array('FAQID', 'ResultSetID', 'SearchID', 'Useful', 'Comment', 'Archived'));

        $fields->addFieldsToTab('Root.Main', array(
            ReadonlyField::create('Article', 'Article Question', $this->FAQ()->Question),
            ReadonlyField::create('Useful', 'Useful rating'),
            ReadonlyField::create('Comment', 'Comments')
        ));

        return $fields;
    }

    public function getTitle()
    {
        return "Feedback to '{$this->FAQ()->Question}'";
    }

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        // Count a view only on first write
        if (!$this->ID) {
            $this->countView = true;
        }
    }

    public function onAfterWrite()
    {
        parent::onAfterWrite();

        if ($this->countView) {
            $faq = $this->FAQ();
            if ($faq && $faq->exists()) {
                $faq->TotalViews = $faq->TotalViews + 1;
                $faq->write();
            }
        }
    }
}

/**
 * Gridfield edit button to open FAQ when gridfield list consists of FAQResults_Article items.
 */
class FAQResults_Article_EditButton extends GridFieldEditButton
{
    public function getColumnContent($gridField, $record, $columnName)
    {
        $faq = FAQ::get()->byID($record->FAQID);

        if ($faq && $faq->exists()) {
            $data = new ArrayData(array(
                'Link' => Controller::join_links($gridField->Link('item'), $record->FAQID, 'edit')
            ));
            return $data->renderWith('GridFieldEditButton');
        }
    }
}

/**
 * Gridfield detail form for handing FAQ items when linked to from a list of FAQResults_Article items.
 */
class FAQResults_Article_DetailForm extends GridFieldDetailForm
{
    public function handleItem($gridField, $request)
    {
        // Our getController could either give us a true Controller, if this is the top-level GridField.
        // It could also give us a RequestHandler in the form of GridFieldDetailForm_ItemRequest if this is a
        // nested GridField.
        $requestHandler = $gridField->getForm()->getController();

        $record = FAQ::get()->byID($request->param("ID"));

        $class = $this->getItemRequestClass();

        $handler = Object::create($class, $gridField, $this, $record, $requestHandler, $this->name);
        $handler->setTemplate($this->template);

        // if no validator has been set on the GridField and the record has a
        // CMS validator, use that.
        if(!$this->getValidator() && (method_exists($record, 'getCMSValidator') || $record instanceof Object && $record->hasMethod('getCMSValidator'))) {
            $this->setValidator($record->getCMSValidator());
        }

        return $handler->handleRequest($request, DataModel::inst());
    }
}

/**
 * Saving FAQ records from FAQResults_Article_DetailForm.
 */
class FAQResults_Article_DetailForm_ItemRequest extends GridFieldDetailForm_ItemRequest
{
    public function doSave($data, $form)
    {
        $new_record = $this->record->ID == 0;
        $controller = $this->getToplevelController();
        $list = $this->gridField->getList();

        if(!$this->record->canEdit()) {
            return $controller->httpError(403);
        }

        if (isset($data['ClassName']) && $data['ClassName'] != $this->record->ClassName) {
            $newClassName = $data['ClassName'];
            // The records originally saved attribute was overwritten by $form->saveInto($record) before.
            // This is necessary for newClassInstance() to work as expected, and trigger change detection
            // on the ClassName attribute
            $this->record->setClassName($this->record->ClassName);
            // Replace $record with a new instance
            $this->record = $this->record->newClassInstance($newClassName);
        }

        try {
            $form->saveInto($this->record);
            $this->record->write();

        } catch(ValidationException $e) {
            $form->sessionMessage($e->getResult()->message(), 'bad', false);
            $responseNegotiator = new PjaxResponseNegotiator(array(
                'CurrentForm' => function() use(&$form) {
                    return $form->forTemplate();
                },
                'default' => function() use(&$controller) {
                    return $controller->redirectBack();
                }
            ));
            if($controller->getRequest()->isAjax()){
                $controller->getRequest()->addHeader('X-Pjax', 'CurrentForm');
            }
            return $responseNegotiator->respond($controller->getRequest());
        }

        $link = '<a href="' . $this->Link('edit') . '">"'
            . htmlspecialchars($this->record->Title, ENT_QUOTES)
            . '"</a>';
        $message = _t(
            'GridFieldDetailForm.Saved',
            'Saved {name} {link}',
            array(
                'name' => $this->record->i18n_singular_name(),
                'link' => $link
            )
        );

        $form->sessionMessage($message, 'good', false);

        if($new_record) {
            return $controller->redirect($this->Link());
        } else {
            return $this->edit($controller->getRequest());
        }
    }
}

