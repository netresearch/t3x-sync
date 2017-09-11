<?php
/**
 * Created by PhpStorm.
 * User: sebastian.mendel
 * Date: 2017-09-04
 * Time: 17:41
 */

namespace Netresearch\Sync\Module;


use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class FalModule extends BaseModule
{
    protected $tables = [
        'sys_file',
        'sys_category',
        'sys_filemounts',
        'sys_category_record_mm',
        'sys_file_reference',
        'sys_collection',
        'sys_file_storage',
        'sys_file_metadata',
    ];

    protected $name = 'FAL';
    protected $type = 'sync_tables';
    protected $target = '';
    protected $dumpFileName = 'fal.sql';
    protected $accessLevel = 0;


    public function run()
    {
        // http://jira.aida.de/jira/browse/SDM-2099
        if (isset($_POST['data']['dam_cleanup'])) {
            $this->cleanUpDAM();
        }

        // DAM Test
        $this->testDAMForErrors();

        // http://jira.aida.de/jira/browse/SDM-2099
        if ($this->hasError()) {
            $this->content =
                '<input type="Submit" name="data[dam_cleanup]" value="clean up FAL">';
        }
    }



    /**
     * http://jira.aida.de/jira/browse/SDM-2099
     *
     * @return void
     */
    protected function cleanUpDAM()
    {
        echo 'This tasks can take some time, please be patient ... ';
        flush();

        /* @var $connectionPool ConnectionPool */
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);

        /* @var $connection \TYPO3\CMS\Core\Database\Connection */
        $connection = $connectionPool->getConnectionForTable('sys_file_reference');

        $connection->delete('sys_file_reference', array('uid_foreign' => 0));

        echo 'done.';
    }



    /**
     * http://jira.aida.de/jira/browse/SDM-2099
     *
     * @return void
     */
    protected function testDAMForErrors()
    {
        /* @var $connectionPool ConnectionPool */
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);

        $queryBuilder = $connectionPool->getQueryBuilderForTable('sys_file_reference');

        $count = $queryBuilder->count('*')
            ->from('sys_file_reference')
            ->where(
                $queryBuilder->expr()->eq('uid_foreign', 0)
            )
            ->execute()
            ->fetchColumn(0);

        if ($count > 0) {
            $this->error .= 'FAL has corrupted entries. (Entries with uid_foreign = 0)';
        }
    }
}