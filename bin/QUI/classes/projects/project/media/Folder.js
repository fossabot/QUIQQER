
/**
 * A media file
 *
 * @module classes/projects/project/media/Folder
 * @author www.pcsg.de (Henning Leutz)
 *
 * @require classes/projects/project/media/Item
 * @require Ajax
 * @require UploadManager
 */

define('classes/projects/project/media/Folder', [

    'classes/projects/project/media/Item',
    'Ajax',
    'UploadManager'

], function(MediaItem, Ajax, UploadManager)
{
    "use strict";

    /**
     * @class classes/projects/project/media/Folder
     *
     * @memberof! <global>
     */
    return new Class({

        Extends : MediaItem,
        Type    : 'classes/projects/project/media/Folder',

        /**
         * create a sub folder
         *
         * @method classes/projects/project/media/Folder#createFolder
         *
         * @param {String} newfolder    - New folder name
         * @param {Function} oncomplete - callback( new_folder_id ) function
         */
        createFolder : function(newfolder, oncomplete)
        {
            var self = this;

            Ajax.post('ajax_media_folder_create', function(result)
            {
                oncomplete(
                    self.getMedia().$parseResultToItem( result )
                );
            }, {
                project   : this.getMedia().getProject().getName(),
                parentid  : this.getId(),
                newfolder : newfolder
            });
        },

        /**
         * Return the children
         *
         * @method classes/projects/project/media/Folder#createFolder
         *
         * @param {Function} oncomplete - callback( children ) function
         * @param {Object} [params] - order params
         */
        getChildren : function(oncomplete, params)
        {
            params = params || {};

            Ajax.get('ajax_media_folder_children', function(result)
            {
                oncomplete( result );
            }, {
                project  : this.getMedia().getProject().getName(),
                folderid : this.getId(),
                params   : JSON.encode( params )
            });
        },

        /**
         * Upload files to the folder
         *
         * @method classes/projects/project/media/Folder#uploadFiles
         *
         * @param {Array|Object} files - Array | Filelist
         * @param {Function} [onfinish] - callback function
         */
        uploadFiles : function(files, onfinish)
        {
            onfinish = onfinish || function() {};

            UploadManager.uploadFiles(
                files,
                'ajax_media_upload',
                {
                    project  : this.getMedia().getProject().getName(),
                    parentid : this.getId(),
                    events   : {
                        onComplete : onfinish
                    }
                }
            );
        },

        /**
         * Folder replace
         * you cannot replace a folder at the moment
         *
         * @method classes/projects/project/media/Folder#replace
         */
        replace : function()
        {
            // nothing, you cannot replace a folder
        }
    });
});
