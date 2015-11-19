<?php

/**
 * Save the available permissions to a user or a group
 *
 * @param String $params - JSON Array
 * @param String $btype - bind type (QUI.controls.users.User or QUI.controls.groups.Group)
 * @param String $permissions - JSON permissions
 * @throws \QUI\Exception
 */
function ajax_permissions_recursive($params, $btype, $permissions)
{
    $Manager     = QUI::getPermissionManager();
    $permissions = json_decode($permissions, true);
    $params      = json_decode($params, true);
    $errors      = 0;

    switch ($btype) {
        case 'classes/projects/project/Site':
            if (!isset($params['id'])) {
                throw new QUI\Exception('Undefined index id');
            }

            $Project = QUI::getProject($params['project'], $params['lang']);
            $Site    = $Project->get($params['id']);
            break;

        default:
            throw new QUI\Exception(
                'Cannot find permissions for Object'
            );
            break;
    }


    $childrenIds = $Site->getChildrenIdsRecursive(array(
        'active' => '0&1'
    ));

    foreach ($childrenIds as $siteId) {
        try {

            $Manager->setPermissions(
                new \QUI\Projects\Site\Edit($Project, $siteId),
                $permissions
            );

        } catch (QUI\Exception $Exception) {
            QUI::getMessagesHandler()->addAttention(
                $Exception->getMessage()
            );

            $errors++;
        }
    }

    if (!$errors) {
        QUI::getMessagesHandler()->addSuccess(
            QUI::getLocale()->get(
                'quiqqer/system',
                'permissions.message.save.success'
            )
        );
    }
}

QUI::$Ajax->register(
    'ajax_permissions_recursive',
    array('params', 'btype', 'permissions'),
    array(
        'Permission::checkAdminUser',
        'quiqqer.system.permissions'
    )
);