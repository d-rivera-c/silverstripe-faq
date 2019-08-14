<?php
// do not want to load this handler if it cannot extend
if (!class_exists('GridFieldBulkActionHandler')) {
    return;
}
/**
 * Bulk action handler for archiving records.
 *
 * @author flamerohr
 * @package GridFieldBulkEditingTools
 * @subpackage BulkManager
 */
class GridFieldBulkActionArchiveHandler extends GridFieldBulkActionHandler
{
    /**
     * RequestHandler allowed actions
     *
     * @var array
     */
    private static $allowed_actions = array('archive');

    /**
     * RequestHandler url => action map
     *
     * @var array
     */
    private static $url_handlers = array(
        'archive' => 'archive'
    );

    /**
     * Archive the selected records passed from the archive bulk action
     *
     * @param SS_HTTPRequest $request
     * @return SS_HTTPResponse List of archived records ID
     */
    public function archive(SS_HTTPRequest $request)
    {
        $ids = array();

        foreach ( $this->getRecords() as $record )
        {
            array_push($ids, $record->ID);
            $record->Archived = true;
            $record->write();
        }

        $response = new SS_HTTPResponse(Convert::raw2json(array(
            'done' => true,
            'records' => $ids
        )));
        $response->addHeader('Content-Type', 'text/json');
        return $response;
    }
}
