<?php

/**
 * Return the site data
 *
 * @param string $project
 * @param string $id
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'ajax_site_get',
    function ($project, $id) {
        $Project = QUI::getProjectManager()->decode($project);
        $Site    = new QUI\Projects\Site\Edit($Project, (int)$id);

        $attributes = $Site->getAttributes();

        $attributes['icon'] = QUI::getPluginManager()->getIconByType(
            $Site->getAttribute('type')
        );

        return array(
            'modules' => QUI\Projects\Site\Utils::getAdminSiteModulesFromSite($Site),
            'attributes' => $attributes,
            'has_children' => $Site->hasChildren(true),
            'parentid' => $Site->getParentId(),
            'url' => $Site->getUrlRewritten()
        );
    },
    array('project', 'id'),
    'Permission::checkAdminUser'
);
