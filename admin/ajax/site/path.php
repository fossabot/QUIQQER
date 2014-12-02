<?php

/**
 * Return the parent ids
 *
 * @param String $project
 * @param String $id
 * @return Array
 */
function ajax_site_path($project, $id)
{
    $Project = \QUI::getProjectManager()->decode( $project );
    $Site    = new \QUI\Projects\Site\Edit( $Project, (int)$id );

    $pids    = array();
    $parents = $Site->getParents();

    foreach ( $parents as $Parent ) {
        $pids[] = $Parent->getId();
    }

    return $pids;
}

\QUI::$Ajax->register(
    'ajax_site_path',
    array('project', 'id'),
    'Permission::checkAdminUser'
);
