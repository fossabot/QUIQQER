<?php

/**
 * This file contains the \QUI\Projects\Media\Item
 */

namespace QUI\Projects\Media;

use QUI;
use QUI\Projects\Media;
use QUI\Utils\System\File as QUIFile;

/**
 * A media item
 * the parent class of each media entry
 *
 * @author  www.pcsg.de (Henning Leutz)
 * @package com.pcsg.qui.projects.media
 * @licence For copyright and license information, please view the /README.md
 */
abstract class Item extends QUI\QDOM
{
    /**
     * internal image effect parameter
     *
     * @var bool|array
     */
    protected $effects = false;

    /**
     * internal media object
     *
     * @var QUI\Projects\Media
     */
    protected $Media = null;

    /**
     * internal parent id (use ->getParentId())
     *
     * @var integer
     */
    protected $parent_id = false;

    /**
     * Path to the real file
     *
     * @var string
     */
    protected $file;

    /**
     * constructor
     *
     * @param array $params - item attributes
     * @param \QUI\Projects\Media $Media - Media of the file
     */
    public function __construct($params, Media $Media)
    {
        $this->Media = $Media;
        $this->setAttributes($params);

        $this->file = CMS_DIR.$this->Media->getPath().$this->getPath();

        if (!file_exists($this->file)) {
            QUI::getMessagesHandler()->addAttention(
                'File '.$this->file.' ('.$this->getId().') doesn\'t exist'
            );

            return;
        }

        $this->setAttribute('filesize', QUIFile::getFileSize($this->file));
        $this->setAttribute('url', $this->getUrl());

        $this->setAttribute(
            'cache_url',
            URL_DIR.$this->Media->getCacheDir().$this->getPath()
        );
    }

    /**
     * Returns the id of the item
     *
     * @return integer
     */
    public function getId()
    {
        return (int)$this->getAttribute('id');
    }

    /**
     * API Methods - Generell important file operations
     */

    /**
     * Activate the file
     * The file is now public
     *
     * @throws QUI\Exception
     */
    public function activate()
    {
        try {
            // activate the parents, otherwise the file is not accessible
            $this->getParent()->activate();
        } catch (QUI\Exception $Exception) {
            // has no parent
        }

        QUI::getDataBase()->update(
            $this->Media->getTable(),
            array('active' => 1),
            array('id' => $this->getId())
        );

        $this->setAttribute('active', 1);


        if (method_exists($this, 'deleteCache')) {
            try {
                $this->deleteCache();
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::addNotice($Exception->getMessage());
            }
        }

        if (method_exists($this, 'createCache')) {
            try {
                $this->createCache();
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::addNotice($Exception->getMessage());
            }
        }

        QUI::getEvents()->fireEvent('mediaActivate', array($this));
    }

    /**
     * Deactivate the file
     * the file is no longer public
     *
     * @throws QUI\Exception
     */
    public function deactivate()
    {
        QUI::getDataBase()->update(
            $this->Media->getTable(),
            array('active' => 0),
            array('id' => $this->getId())
        );

        $this->setAttribute('active', 0);

        if (method_exists($this, 'deleteCache')) {
            $this->deleteCache();
        }

        QUI::getEvents()->fireEvent('mediaDeactivate', array($this));
    }

    /**
     * Save the file to the database
     * The id attribute can not be overwritten
     *
     * @throws QUI\Exception
     */
    public function save()
    {
        QUI::getEvents()->fireEvent('mediaSaveBegin', array($this));

        // Rename the file, if necessary
        $this->rename($this->getAttribute('name'));

        $image_effects = $this->getEffects();

        if (is_string($image_effects) || is_bool($image_effects)) {
            $image_effects = array();
        }

        switch ($this->getAttribute('order')) {
            case 'priority':
            case 'priority ASC':
            case 'priority DESC':
            case 'c_date':
            case 'c_date ASC':
            case 'c_date DESC':
            case 'name':
            case 'name ASC':
            case 'name DESC':
            case 'title':
            case 'title ASC':
            case 'title DESC':
            case 'id':
            case 'id ASC':
            case 'id DESC':
                $order = $this->getAttribute('order');
                break;

            default:
                $order = '';
        }

        // svg fix
        if ($this->getAttribute('mime_type') == 'text/html') {
            $content = file_get_contents($this->getFullPath());

            if (strpos($content, '<svg') !== false && strpos($content, '</svg>')) {
                file_put_contents(
                    $this->getFullPath(),
                    '<?xml version="1.0" encoding="UTF-8"?>'.
                    $content
                );

                $fileinfo = QUI\Utils\System\File::getInfo($this->getFullPath());

                QUI::getDataBase()->update(
                    $this->Media->getTable(),
                    array('mime_type' => $fileinfo['mime_type']),
                    array('id' => $this->getId())
                );
            }
        }

        if (method_exists($this, 'deleteCache')) {
            $this->deleteCache();
        }

        if (method_exists($this, 'deleteAdminCache')) {
            $this->deleteAdminCache();
        }

        $fileinfo = QUI\Utils\System\File::getInfo($this->getFullPath());
        $type     = QUI\Projects\Media\Utils::getMediaTypeByMimeType($fileinfo['mime_type']);

        if (Utils::isFolder($this)) {
            $type = 'folder';
        }

        QUI::getDataBase()->update(
            $this->Media->getTable(),
            array(
                'title'         => $this->getAttribute('title'),
                'alt'           => $this->getAttribute('alt'),
                'short'         => $this->getAttribute('short'),
                'order'         => $order,
                'priority'      => (int)$this->getAttribute('priority'),
                'image_effects' => json_encode($image_effects),
                'type'          => $type
            ),
            array(
                'id' => $this->getId()
            )
        );

        // @todo in eine queue setzen
        $Project = $this->getProject();

        if ($Project->getConfig('media_createCacheOnSave')
            && method_exists($this, 'createCache')
        ) {
            $this->createCache();
        }

        QUI::getEvents()->fireEvent('mediaSave', array($this));
    }

    /**
     * Delete the file and move it to the trash
     *
     * @throws QUI\Exception
     */
    public function delete()
    {
        if ($this->isDeleted()) {
            throw new QUI\Exception(
                QUI::getLocale()->get('quiqqer/quiqqer', 'exception.media.already.deleted'),
                400
            );
        }

        QUI::getEvents()->fireEvent('mediaDeleteBegin', array($this));

        $Media = $this->Media;
        $First = $Media->firstChild();

        // Move file to the temp folder
        $original = $this->getFullPath();
        $notFound = false;

        $var_folder = VAR_DIR.'media/trash/'.$Media->getProject()->getName().'/';

        if (!is_file($original)) {
            QUI::getMessagesHandler()->addAttention(
                QUI::getLocale()->get(
                    'quiqqer/quiqqer',
                    'exception.delete.originalfile.notfound'
                )
            );

            $notFound = true;
        }

        if ($First->getFullPath() == $original) {
            throw new QUI\Exception(
                array(
                    'quiqqer/quiqqer',
                    'exception.delete.root.file'
                ),
                400
            );
        }

        // first, delete the cache
        if (method_exists($this, 'deleteCache')) {
            try {
                $this->deleteCache();
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::addWarning($Exception->getMessage());
            }
        }

        if (method_exists($this, 'deleteAdminCache')) {
            try {
                $this->deleteAdminCache();
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::addWarning($Exception->getMessage());
            }
        }


        // second, move the file to the trash
        try {
            QUIFile::unlink($var_folder.$this->getId());
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::addWarning($Exception->getMessage());
        }

        try {
            QUIFile::mkdir($var_folder);
            QUIFile::move($original, $var_folder.$this->getId());
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::addWarning($Exception->getMessage());
        }

        // change db entries
        QUI::getDataBase()->update(
            $this->Media->getTable(),
            array(
                'deleted' => 1,
                'active'  => 0,
                'file'    => ''
            ),
            array(
                'id' => $this->getId()
            )
        );

        QUI::getDataBase()->delete(
            $this->Media->getTable('relations'),
            array('child' => $this->getId())
        );

        $this->parent_id = false;
        $this->setAttribute('deleted', 1);
        $this->setAttribute('active', 0);

        try {
            QUI::getEvents()->fireEvent('mediaDelete', array($this));
        } catch (QUI\ExceptionStack $Exception) {
            QUI\System\Log::addWarning($Exception->getMessage());
        }

        if ($notFound) {
            $this->destroy();
        }
    }

    /**
     * Destroy the File complete from the DataBase and from the Filesystem
     *
     * @throws QUI\Exception
     */
    public function destroy()
    {
        if ($this->isActive()) {
            throw new QUI\Exception('Only inactive files can be destroyed');
        }

        if (!$this->isDeleted()) {
            throw new QUI\Exception('Only deleted files can be destroyed');
        }

        $Media = $this->Media;

        // get the trash file and destroy it
        $var_folder = VAR_DIR.'media/trash/'.$Media->getProject()->getName().'/';
        $var_file   = $var_folder.$this->getId();

        try {
            QUIFile::unlink($var_file);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::addWarning($Exception->getMessage());
        }

        QUI::getDataBase()->delete($this->Media->getTable(), array(
            'id' => $this->getId()
        ));

        QUI::getEvents()->fireEvent('mediaDestroy', array($this));
    }

    /**
     * Returns if the file is active or not
     *
     * @return boolean
     */
    public function isActive()
    {
        return $this->getAttribute('active') ? true : false;
    }

    /**
     * Returns if the file is deleted or not
     *
     * @return boolean
     */
    public function isDeleted()
    {
        return $this->getAttribute('deleted') ? true : false;
    }

    /**
     * Rename the File
     *
     * @param string $newname - The new name what the file get
     *
     * @throws \QUI\Exception
     */
    public function rename($newname)
    {
        $original  = $this->getFullPath();
        $extension = QUI\Utils\StringHelper::pathinfo($original, PATHINFO_EXTENSION);
        $Parent    = $this->getParent();

        $new_full_file = $Parent->getFullPath().$newname.'.'.$extension;
        $new_file      = $Parent->getPath().$newname.'.'.$extension;

        if ($new_full_file == $original) {
            return;
        }

        if (empty($newname)) {
            return;
        }

        // throws the \QUI\Exception
        $fileParts = explode('/', $new_file);

        foreach ($fileParts as $filePart) {
            Utils::checkMediaName($filePart);
        }


        if ($Parent->childWithNameExists($newname)) {
            throw new QUI\Exception(
                QUI::getLocale()->get('quiqqer/quiqqer', 'exception.media.file.with.same.name.exists', array(
                    'name' => $newname
                ))
            );
        }

        if ($Parent->fileWithNameExists($newname.'.'.$extension)) {
            throw new QUI\Exception(
                QUI::getLocale()->get('quiqqer/quiqqer', 'exception.media.file.with.same.name.exists', array(
                    'name' => $newname
                ))
            );
        }


        if (method_exists($this, 'deleteCache')) {
            $this->deleteCache();
        }

        if (method_exists($this, 'deleteAdminCache')) {
            $this->deleteAdminCache();
        }


        QUI::getDataBase()->update(
            $this->Media->getTable(),
            array(
                'name' => $newname,
                'file' => $new_file
            ),
            array(
                'id' => $this->getId()
            )
        );

        $this->setAttribute('name', $newname);
        $this->setAttribute('file', $new_file);

        QUIFile::move($original, $new_full_file);

        if (method_exists($this, 'createCache')) {
            $this->createCache();
        }

        QUI::getEvents()->fireEvent('mediaRename', array($this));
    }

    /**
     * Get Parent Methods
     */

    /**
     * Return the parent id
     *
     * @return integer
     */
    public function getParentId()
    {
        if ($this->parent_id) {
            return $this->parent_id;
        }

        $id = $this->getId();

        if ($id === 1) {
            return false;
        }

        $this->parent_id = $this->Media->getParentIdFrom($id);

        return $this->parent_id;
    }

    /**
     * Return all parent ids
     *
     * @return array
     */
    public function getParentIds()
    {
        if ($this->getId() === 1) {
            return array();
        }

        $parents = array();
        $id      = $this->getId();

        while ($id = $this->Media->getParentIdFrom($id)) {
            $parents[] = $id;
        }

        return array_reverse($parents);
    }

    /**
     * Return the Parent Media Item Object
     *
     * @return \QUI\Projects\Media\Folder
     * @throws \QUI\Exception
     */
    public function getParent()
    {
        return $this->Media->get($this->getParentId());
    }

    /**
     * Return all Parents
     *
     * @return array
     *
     * @throws QUI\Exception
     */
    public function getParents()
    {
        $ids     = $this->getParentIds();
        $parents = array();

        foreach ($ids as $id) {
            $parents[] = $this->Media->get($id);
        }

        return $parents;
    }

    /**
     * Path and URL Methods
     */

    /**
     * Return the path of the file, without host, url dir or cms dir
     *
     * @return string
     */
    public function getPath()
    {
        return $this->getAttribute('file');
    }

    /**
     * Return the fullpath of the file
     *
     * @return string
     */
    public function getFullPath()
    {
        return $this->Media->getFullPath().$this->getAttribute('file');
    }

    /**
     * Returns information about a file path
     *
     * @param array|boolean $options - If present, specifies a specific element to be returned;
     *                                  one of:
     *                                  PATHINFO_DIRNAME, PATHINFO_BASENAME,
     *                                  PATHINFO_EXTENSION or PATHINFO_FILENAME.
     * @return mixed
     */
    public function getPathinfo($options = false)
    {
        if (!$options) {
            return pathinfo($this->getFullPath());
        }

        return pathinfo($this->getFullPath(), $options);
    }

    /**
     * Returns the url from the file
     *
     * @param boolean $rewritten - false = image.php, true = rewrited URL
     *
     * @return string
     */
    public function getUrl($rewritten = false)
    {
        if ($rewritten == false) {
            $Project = $this->Media->getProject();

            $str = 'image.php?id='.$this->getId().'&project='.$Project->getAttribute('name');

            if ($this->getAttribute('maxheight')) {
                $str .= '&maxheight='.$this->getAttribute('maxheight');
            }

            if ($this->getAttribute('maxwidth')) {
                $str .= '&maxwidth='.$this->getAttribute('maxwidth');
            }

            return $str;
        }

        if ($this->getAttribute('active') == 1) {
            return URL_DIR.$this->Media->getCacheDir().$this->getAttribute('file');
        }

        return '';
    }

    /**
     * move the item to another folder
     *
     * @param \QUI\Projects\Media\Folder $Folder - the new folder of the file
     *
     * @throws QUI\Exception
     */
    public function moveTo(Folder $Folder)
    {
        // check if a child with the same name exist
        if ($Folder->fileWithNameExists($this->getAttribute('name'))) {
            throw new QUI\Exception(
                QUI::getLocale()->get('quiqqer/quiqqer', 'exception.media.file.with.same.name.exists', array(
                    'name' => $Folder->getAttribute('name')
                ))
            );
        }

        $Parent   = $this->getParent();
        $old_path = $this->getFullPath();

        $Parent->getFullPath();

        $new_path = str_replace(
            $Parent->getFullPath(),
            $Folder->getFullPath(),
            $this->getFullPath()
        );

        $new_file = str_replace($this->getMedia()->getFullPath(), '', $new_path);

        // delete the file cache
        // @todo move the cache too
        if (method_exists($this, 'deleteCache')) {
            $this->deleteCache();
        }

        if (method_exists($this, 'deleteAdminCache')) {
            $this->deleteAdminCache();
        }


        // update file path
        QUI::getDataBase()->update(
            $this->Media->getTable(),
            array(
                'file' => $new_file
            ),
            array(
                'id' => $this->getId()
            )
        );

        // set the new parent relationship
        QUI::getDataBase()->update(
            $this->Media->getTable('relations'),
            array(
                'parent' => $Folder->getId()
            ),
            array(
                'parent' => $Parent->getId(),
                'child'  => $this->getId()
            )
        );

        // move file on the real directory
        QUIFile::move($old_path, $new_path);

        // update internal references
        $this->setAttribute('file', $new_file);


        $this->parent_id = $Folder->getId();
    }

    /**
     * copy the item to another folder
     *
     * @param \QUI\Projects\Media\Folder $Folder
     *
     * @return \QUI\Projects\Media\Item - The new file
     *
     * @throws QUI\Exception
     */
    public function copyTo(Folder $Folder)
    {
        $File = $Folder->uploadFile($this->getFullPath());

        $File->setAttribute('title', $this->getAttribute('title'));
        $File->setAttribute('alt', $this->getAttribute('alt'));
        $File->setAttribute('short', $this->getAttribute('short'));
        $File->save();

        return $File;
    }

    /**
     * Return the Media of the item
     *
     * @return QUI\Projects\Media
     */
    public function getMedia()
    {
        return $this->Media;
    }

    /**
     * Return the Project of the item
     */
    public function getProject()
    {
        return $this->getMedia()->getProject();
    }


    /**
     * Effect methods
     */

    /**
     * Return the effects of the item
     *
     * @return array
     */
    public function getEffects()
    {
        if (is_array($this->effects)) {
            return $this->effects;
        }

        $effects = $this->getAttribute('image_effects');

        if (is_string($effects)) {
            $effects = json_decode($effects, true);
        }

        if (is_array($effects)) {
            $this->effects = $effects;
        } else {
            $this->effects = array();
        }

        return $this->effects;
    }

    /**
     * Set an item effect
     *
     * @param string $effect - Name of the effect
     * @param string|integer|float $value - Value of the effect
     */
    public function setEffect($effect, $value)
    {
        $this->getEffects();

        $this->effects[$effect] = $value;
    }

    /**
     * Set complete effects
     *
     * @param array $effects
     */
    public function setEffects($effects = array())
    {
        $this->effects = $effects;
    }
}
