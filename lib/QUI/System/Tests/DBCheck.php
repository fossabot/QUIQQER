<?php

/**
 * This class contains \QUI\System\Tests\DBCheck
 */

namespace QUI\System\Tests;

use QUI;
use QUI\Utils\System\File as SystemFile;
use QUI\Utils\XML as XML;
use QUI\Utils\String as String;

/**
 * Database Check - Compares existing QUIQQER database tables with database.xml files
 * and detects discrepancies
 *
 * @package quiqqer/quiqqer
 * @author  www.pcsg.de (Patrick Müller)
 * @licence For copyright and license information, please view the /README.md
 */
class DBCheck extends QUI\System\Test
{
    /**
     * @var \QUI\Database\Tables
     */
    protected $_Tables = null;

    /**
     * @var bool
     */
    protected $_error = false;

    /**
     * @var array
     */
    protected $_errors = array();

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->setAttributes(array(
            'title'       => 'QUIQQER - Database Check (on failure check error log!)',
            'description' => 'Compares existing QUIQQER database tables '.
                'with database.xml files and detects discrepancies.'
        ));

        $this->_isRequired = self::TEST_IS_REQUIRED;
    }

    /**
     * Database Check
     *
     * @return self::STATUS_OK|self::STATUS_ERROR
     */
    public function execute()
    {
        if (defined('OPT_DIR')) {
            $packages_dir = OPT_DIR;
        } else {
            return self::STATUS_ERROR;
        }

        $packages = SystemFile::readDir($packages_dir);

        $this->_Tables = QUI::getDataBase()->Table();

        // first we need all databases
        foreach ($packages as $package) {
            if ($package == 'composer') {
                continue;
            }

            $package_dir = $packages_dir.$package;
            $list = SystemFile::readDir($package_dir);

            foreach ($list as $sub) {
                if (!is_dir($package_dir.'/'.$sub)) {
                    continue;
                }

                $databaseXml = $package_dir.'/'.$sub.'/database.xml';

                if (!file_exists($databaseXml)) {
                    continue;
                }

                try {
                    $this->_checkIntegrity($databaseXml);
                    $this->_outputError($databaseXml);
                } catch (\Exception $Exception) {
                    QUI\System\Log::addWarning($databaseXml);
                    QUI\System\Log::addWarning($Exception->getMessage());
                    QUI\System\Log::addWarning($Exception->getTraceAsString());
                }
            }
        }

        if ($this->_error) {
            return self::STATUS_ERROR;
        }

        return self::STATUS_OK;
    }

    /**
     * Main method for extracting table info from xml and compare it with the database
     *
     * @param $xmlFile
     */
    protected function _checkIntegrity($xmlFile)
    {
        $content = XML::getDataBaseFromXml($xmlFile);

        // Project tables
        if (isset($content['projects'])) {
            $projects = QUI::getProjectManager()->getProjects(true);

            $langTables = array(); // language dependant tables
            $noLangTables = array(); // language independant tables

            foreach ($content['projects'] as $info) {
                $checkData = $this->_extractTableData($info);

                if (!empty($info['no-project-lang'])) {
                    $noLangTables[] = $checkData;
                } else {
                    $langTables[] = $checkData;
                }
            }

            // first check language independant project tables
            if (!empty($noLangTables)) {
                foreach ($projects as $Project) {
                    foreach ($langTables as $tblData) {
                        $projectTable = QUI::getDBProjectTableName(
                            $tblData['table'], $Project, false
                        );

                        $this->_checkTableIntegrity(
                            $projectTable,
                            $tblData
                        );
                    }
                }
            }

            // check language dependant project tables
            if (!empty($langTables)) {
                foreach ($projects as $Project) {
                    /* @var $Project \QUI\Projects\Project */
                    $langs = $Project->getAttribute('langs');

                    foreach ($langs as $lang) {
                        foreach ($langTables as $tblData) {
                            $projectTable = QUI::getDBProjectTableName(
                                $tblData['table'], $Project, $lang
                            );

                            $this->_checkTableIntegrity(
                                $projectTable,
                                $tblData
                            );
                        }
                    }
                }
            }
        }

        if (isset($content['globals'])) {
            $globalTables = array();

            foreach ($content['globals'] as $info) {
                $globalTables[] = $this->_extractTableData($info, true);
            }

            foreach ($globalTables as $tblData) {
                $table = QUI::getDBTableName($tblData['table']);
                $this->_checkTableIntegrity($table, $tblData);
            }
        }
    }

    /**
     * Extracts check relevant data from xml table information
     *
     * @param      $info
     * @param bool $isGlobal (optional) - is a global table
     *
     * @return array
     */
    protected function _extractTableData($info, $isGlobal = false)
    {
        $primaryKeys = array();
        $checkData = array(
            'table'    => $info['suffix'],
            'fields'   => $info['fields'],
            'indices'  => false,
            'auto_inc' => false
        );

        // if primary keys are not explicitly declared by attribute
        // try to extract them out of the column structure declaration
        if (isset($info['primary'])) {
            $primaryKeys = $info['primary'];
        } else {
            foreach ($info['fields'] as $column => $structure) {
                $structure = String::toLower($structure);

                if (mb_strpos($structure, 'primary key') !== false) {
                    $primaryKeys[] = $column;
                }
            }
        }

        $checkData['primaryKeys'] = $primaryKeys;

        if (isset($info['index'])) {
            $checkData['indices'] = $info['index'];
        }

        if (isset($info['auto_increment'])) {
            $checkData['auto_inc'] = $info['auto_increment'];
        } else {
            foreach ($info['fields'] as $column => $structure) {
                $structure = String::toLower($structure);

                if (mb_strpos($structure, 'auto_increment') !== false) {
                    $checkData['auto_inc'] = $column;
                    break; // @todo sobald auto_inc auch für mehrere felder definiert werden kann, anpassen
                }
            }
        }

        // check if user can set individual primary keys
        if (empty($info['no-site-reference'])) {
            if (empty($info['no-project-lang']) && !empty($primaryKeys)) {
                if (!(count($primaryKeys) === 1
                    && in_array('id', $primaryKeys))
                ) {
                    $this->_addError(
                        $checkData['table'],
                        "---",
                        "Primary Key error -> ".
                        "XML file declares a primary key for table "
                        .$checkData['table'].
                        " You can only declare a primary key if the table has the "
                        .
                        "-> no-site-reference=\"1\" <- attribute OR the ".
                        "-> no-project-lang=\"1\" <- attribute!"
                    );


                }
            }

            // assume the xml file declares an id key
            // although technically it is created by the system in this special case
            if (!$isGlobal && !in_array('id', $primaryKeys)) {
                $checkData['primaryKeys'][] = 'id';
                $checkData['fields']['id'] = 'BIGINT(20) NOT NULL PRIMARY KEY';
            }
        }

        return $checkData;
    }

    /**
     * Compares xml data with database table data
     *
     * @param string $table   - name of the table in the database
     * @param array  $tblData - the data extracted form the database.xml
     */
    protected function _checkTableIntegrity($table, $tblData)
    {
        // xml data
        $tbl = $tblData['table'];
        $primaryKeys = $tblData['primaryKeys'];
        $autoInc = $tblData['auto_inc'];
        $fields = $tblData['fields'];

        if (!$this->_Tables->exist($table)) {
            $this->_addError(
                $tbl,
                $table,
                "Missing table -> ".
                "Table \"$table\" is not found in the database.".
                " Please execute the QUIQQER Setup."
            );

            return;
        }

        // get table info from database
        $dbKeys = $this->_Tables->getKeys($table);

        // get all primary keys
        foreach ($dbKeys as $k => $columnInfo) {
            if ($columnInfo['Key_name'] !== 'PRIMARY') {
                unset($dbKeys[$k]);
                continue;
            }

            $dbKeys[$k] = $columnInfo['Column_name'];
        }

        $_dbFields = $this->_Tables->getFieldsInfos($table);
        $dbFields = array();

        foreach ($_dbFields as $_entry) {
            $dbFields[] = $_entry['Field'];
        }

        // compare primary keys
        $keyCompare = array_intersect($primaryKeys, $dbKeys);

        if (count($primaryKeys) !== count($dbKeys)
            || count($keyCompare) !== count($primaryKeys)
        ) {
            $_dbKeys = empty($dbKeys) ? '(none)' : implode(',', $dbKeys);
            $_prKeys = empty($primaryKeys) ? '(none)'
                : implode(',', $primaryKeys);

            $this->_addError(
                $tbl,
                $table,
                "Primary Key mismatch -> ".
                "XML primary keys: ".$_prKeys.
                " | Database table primary keys: ".$_dbKeys
            );
        }


        // check if xml file declares fields that are not present in the database table
        $xmlFieldsDiff = array_diff(
            array_keys($fields),
            $dbFields
        );

        if (!empty($xmlFieldsDiff)) {
            $this->_addError(
                $tbl,
                $table,
                "Missing table fields -> ".
                "The XML file declares table fields that are ".
                "missing in the database table: ".
                implode(',', $xmlFieldsDiff)
            );
        }

        // collect detailled column information from database
        $dbFieldsData = $this->_Tables->getFieldsInfos($table);

        foreach ($dbFieldsData as $field) {
            $fieldName = $field['Field'];

            if (!isset($fields[$fieldName])) {
                continue;
            }

            // column declaration from the xml file
            $fieldData = String::toLower($fields[$fieldName]);

            /*** NULL/NOT NULL check ***/
            $nullable = true;

            if (mb_strpos($fieldData, 'not null') !== false) {
                $nullable = false;
            }

            // check if column in datase is nullable
            $isNullable = true;

            if ($field['Null'] === 'NO') {
                $isNullable = false;
            }

            if ($nullable !== $isNullable) {
                $should = $nullable ? "NULL" : "NOT NULL";
                $is = $isNullable ? "NULL" : "NOT NULL";

                $this->_addError(
                    $tbl,
                    $table,
                    "Field structure mismatch -> ".
                    "The XML file says that field \"$fieldName\" ".
                    "should be $should but the database says it is $is."
                );
            }

            /*** AUTO_INCREMENT check ***/
            $autoIncrement = false;

            if ($autoInc === $fieldName) {
                $autoIncrement = true;
            }

            $isAutoIncrement = false;

            if ($field['Extra'] === 'auto_increment') {
                $isAutoIncrement = true;
            }

            if ($autoIncrement !== $isAutoIncrement) {
                $should = $autoIncrement ? "AUTO_INCREMENT"
                    : "not AUTO_INCREMENT";
                $is = $isAutoIncrement ? "AUTO_INCREMENT"
                    : "not AUTO_INCREMENT";

                $this->_addError(
                    $tbl,
                    $table,
                    "Field structure mismatch -> ".
                    "The XML file says that field \"$fieldName\" ".
                    "should be $should but the database says it is $is."
                );
            }

            /*** DATATYPE check ***/
            $isDatatype = $field['Type'];

            // clear xml field data of already checked content
            $fieldData = str_replace(
                array(
                    'primary key',
                    'not null',
                    'null',
                    'auto_increment'
                ),
                '',
                $fieldData
            );

            $fieldData = trim($fieldData);

            // correct things like "varchar( 20 )" to "varchar(20)" to match the column information
            $fieldData = preg_replace('#\(\D*(\d+)\D*\)#i', '($1)', $fieldData);

            if (mb_strpos($fieldData, $isDatatype) === false) {
                $this->_addError(
                    $tbl,
                    $table,
                    "Field structure mismatch -> ".
                    "The XML file says that field \"$fieldName\" ".
                    "should be \"$fieldData\"".
                    " but the database says it is \"$isDatatype\"."
                );
            }
        }
    }

    /**
     * @param String $table
     * @param String $dbTable
     * @param String $error
     */
    protected function _addError($table, $dbTable, $error)
    {
        $this->_errors[] = array(
            'table'   => $table,
            'dbTable' => $dbTable,
            'error'   => $error
        );

        $this->_error = true;
    }

    /**
     * @param String $xmlFile
     */
    protected function _outputError($xmlFile)
    {
        if (empty($this->_errors)) {
            return;
        }

        $msg = "\n-> Database Check Errors in: $xmlFile <-";

        foreach ($this->_errors as $k => $err) {
            $msg .= "\n";
            $msg .= "\n Error #".($k + 1).":";
            $msg .= "\n -------------------------";
            $msg .= "\n Definition for table: \t\"".$err['table']."\"";
            $msg .= "\n Database table: \t\"".$err['dbTable']."\"";
            $msg .= "\n Error: ".$err['error'];
        }

        QUI\System\Log::addWarning($msg);
        $this->_errors = array();
    }
}
