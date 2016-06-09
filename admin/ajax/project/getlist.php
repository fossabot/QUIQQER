<?php

/**
 * Return the project list
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'ajax_project_getlist',
    function () {
        $projects = QUI\Projects\Manager::getProjects(true);
        $result   = array();

        /* @var $Project \QUI\Projects\Project */
        foreach ($projects as $Project) {
            $result[$Project->getName()] = $Project->getConfig();
        }

        return $result;
    },
    false,
    'Permission::checkAdminUser'
);
