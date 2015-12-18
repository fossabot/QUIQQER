<?php

/**
 * Execute a site search
 *
 * @param string $search - search string
 * @param string $params - JSON Array
 * @return array
 */
function ajax_site_search($search, $params)
{
    $params = json_decode($params, true);
    $page   = 1;

    if (isset($params['page']) && (int)$params['page']) {
        $page = (int)$params['page'];
    }

    $data = QUI\Projects\Sites::search($search, $params);

    $params['count'] = true;
    $total           = QUI\Projects\Sites::search($search, $params);

    return array(
        'data' => $data,
        'page' => $page,
        'total' => $total
    );
}

QUI::$Ajax->register(
    'ajax_site_search',
    array('search', 'params'),
    'Permission::checkAdminUser'
);
