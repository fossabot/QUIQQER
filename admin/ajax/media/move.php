<?php

/**
 * Copy files to a folder
 *
 * @param string $project - Name of the project
 * @param string $to - Folder-ID
 * @param string $ids - ids which copied
 *
 * @throws \QUI\Exception
 */
function ajax_media_move($project, $to, $ids)
{
    $Project = QUI\Projects\Manager::getProject($project);
    $Media   = $Project->getMedia();
    $Folder  = $Media->get($to);

    $ids = json_decode($ids, true);

    if (!QUI\Projects\Media\Utils::isFolder($Folder)) {
        throw new \QUI\Exception(
            'Bitte wählen Sie ein Ordner aus um die Dateie zu verschieben.' // #locale
        );
    }

    /* @var $Folder \QUI\Projects\Media\Folder */
    foreach ($ids as $id) {
        try {
            $Item = $Media->get((int)$id);
            $Item->moveTo($Folder);

        } catch (QUI\Exception $Exception) {
            QUI::getMessagesHandler()->addError($Exception->getMessage());
        }
    }
}

QUI::$Ajax->register(
    'ajax_media_move',
    array('project', 'to', 'ids'),
    'Permission::checkAdminUser'
);
