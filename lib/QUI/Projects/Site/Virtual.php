<?php

/**
 * File contains QUI\Projects\Site\Virtual
 */
namespace QUI\Projects\Site;

use QUI;

/**
 * Virtual site object
 * not a real site in the database
 *
 * @author  www.pcsg.de (Henning Leutz)
 * @licence For copyright and license information, please view the /README.md
 */
class Virtual extends QUI\QDOM implements QUI\Interfaces\Projects\Site
{
    /**
     * @var null|QUI\Projects\Site
     */
    protected $_Parent = null;

    /**
     * Project
     *
     * @var null
     */
    protected $_Project = null;

    /**
     * @param array                $attributes
     * @param QUI\Projects\Project $Project
     * @param QUI\Projects\Site    $Parent
     *
     * @throws QUI\Exception
     */
    public function __construct(
        $attributes = array(),
        QUI\Projects\Project $Project,
        QUI\Projects\Site $Parent = null
    ) {
        $this->_Project = $Project;
        $this->_Parent = $Parent;

        $this->setAttributes($attributes);

        $needles = array('id', 'title', 'name', 'url');

        foreach ($needles as $needle) {
            if (!$this->getAttribute($needle)) {
                throw new QUI\Exception('Misisng attribute '.$needle);
            }
        }
    }

    /**
     * Return the project object of the site
     *
     * @return QUI\Projects\Project
     */
    public function getProject()
    {
        return $this->_Project;
    }

    /**
     * Lädt die Plugins der Seite
     *
     * @param String|bool $plugin - Plugin welches geladen werden soll, optional, ansonsten werden alle geladen
     *
     * @return Virtual
     */
    public function load($plugin = false)
    {
        return $this;
    }

    /**
     * Serialisierungsdaten
     *
     * @return String
     */
    public function encode()
    {
        return json_encode($this->getAttributes());
    }

    /**
     * Setzt JSON Parameter
     *
     * @param String $params - JSON encoded string
     *
     * @throws QUI\Exception
     */
    public function decode($params)
    {
        $this->setAttributes(
            json_decode($params, true)
        );
    }

    /**
     * Hohlt frisch die Daten aus der DB
     */
    public function refresh()
    {
    }

    /**
     * Prüft ob es eine Verknüpfung ist
     *
     * @return Bool|Integer
     */
    public function isLinked()
    {
        return false;
    }

    /**
     * Prüft ob es die Seite auch in einer anderen Sprache gibt
     *
     * @param String $lang
     * @param Bool   $check_only_active - check only active pages
     *
     * @return Bool
     */
    public function existLang($lang, $check_only_active = true)
    {
        return false;
    }

    /**
     * Gibt die IDs von Sprachverknüpfungen zurück
     *
     * @return Array
     */
    public function getLangIds()
    {
        return array();
    }

    /**
     * Return the ID of the site,
     * or the ID of the sibling (linked) site of another languager
     *
     * @param String|bool $lang - optional, if it is set, then the language of the wanted linked sibling site
     *
     * @return Integer
     */
    public function getId($lang = false)
    {
        return $this->getAttribute('id');
    }

    /**
     * Gibt alle Kinder zurück
     *
     * @param array $params - Parameter für die Childrenausgabe
     *                      $params['where']
     *                      $params['limit']
     * @param Bool  $load   - Legt fest ob die Kinder die Plugins laden sollen
     *
     * @return Array;
     */
    public function getChildren($params = array(), $load = false)
    {
        return array();
    }

    /**
     * Liefert die nächstfolgende Seite
     *
     * @return QUI\Projects\Site
     * @throws QUI\Exception
     */
    public function nextSibling()
    {
        throw new QUI\Exception('Die Seite besitzt keine nächstfolgende Seite');
    }

    /**
     * Die nächsten x Kinder
     *
     * @param Integer $no
     *
     * @return Array
     */
    public function nextSiblings($no)
    {
        return array();
    }

    /**
     * Liefert die vorhergehenden Seite
     *
     * @return QUI\Projects\Site
     * @throws QUI\Exception
     */
    public function previousSibling()
    {
        throw new QUI\Exception('Die Seite besitzt keine vorhergehenden Seite');
    }

    /**
     * Die x vorhergehenden Geschwister
     *
     * @param Integer $no
     *
     * @return Array
     */
    public function previousSiblings($no)
    {
        return array();
    }

    /**
     * Gibt das erste Kind der Seite zurück
     *
     * @param Array $params
     *
     * @return QUI\Projects\Site | false
     */
    public function firstChild($params = array())
    {
        return false;
    }

    /**
     * Gibt die Kinder zurück achtet aber auf "Nicht in Navigation anzeigen" und Rechte
     *
     * @param array $params
     *
     * @return array
     */
    public function getNavigation($params = array())
    {
        return array();
    }

    /**
     * Gibt ein Kind zurück welches den Namen hat
     *
     * @param String $name
     *
     * @return Integer
     * @throws QUI\Exception
     */
    public function getChildIdByName($name)
    {
        throw new QUI\Exception(
            'No Child found with name '.$name, 705
        );
    }

    /**
     * Return a children by id
     *
     * @param Integer $id
     *
     * @return QUI\Projects\Site
     * @throws QUI\Exception
     */
    public function getChild($id)
    {
        throw new QUI\Exception('Child not found', 705);
    }

    /**
     * Gibt die ID's der Kinder zurück
     * Wenn nur die ID's verwendet werden sollte dies vor getChildren verwendet werden
     *
     * @param Array $params Parameter für die Childrenausgabe
     *                      $params['where']
     *                      $params['limit']
     *
     * @return Array
     */
    public function getChildrenIds($params = array())
    {
        return array();
    }

    /**
     * Return ALL children ids under the site
     *
     * @param array $params - db parameter
     *
     * @return array
     */
    public function getChildrenIdsRecursive($params = array())
    {
        return array();
    }

    /**
     * Gibt zurück ob Site Kinder besitzt
     *
     * @param Bool $navhide - if navhide == false, navhide must be 0
     *
     * @return Integer - Anzahl der Kinder
     */
    public function hasChildren($navhide = false)
    {
        return 0;
    }

    /**
     * Setzt das delete Flag
     *
     * @todo move to Site/Edit
     */
    public function delete()
    {
        return false;
    }

    /**
     * Gibt die URL der Seite zurück
     *
     * @param $params
     * @param $rewrited
     *
     * @return String
     */
    public function getUrl($params = array(), $rewrited = false)
    {
        return $this->getAttribute('url');
    }

    /**
     * Gibt eine sprechenden URL zurück
     * DB Abfragen werden gemacht - Hier auf Performance achten
     *
     * @param Array $params - Parameter welche an die URL angehängt werden
     *
     * @return String
     */
    public function getUrlRewrited($params = array())
    {
        return $this->getAttribute('url');
    }

    /**
     * Return the Parent id from the site object
     *
     * @return Integer
     */
    public function getParentId()
    {
        if (!$this->_Parent) {
            return 1;
        }

        return $this->_Parent->getId();
    }

    /**
     * Gibt alle direkten Eltern Ids zurück
     *
     * Site
     * ->Parent
     * ->Parent
     * ->Parent
     *
     * @return Array
     */
    public function getParentIds()
    {
        $parents = $this->getParent()->getParentIds();
        $parents[] = $this->getParent()->getId();

        return $parents;
    }

    /**
     * Return the Parent ID List
     *
     * @return Array
     */
    public function getParentIdTree()
    {
        return array();
    }

    /**
     * Gibt das Parent Objekt zurück
     *
     * @return QUI\Projects\Site
     */
    public function getParent()
    {
        if (!$this->_Parent) {
            return $this->_Project->firstChild();
        }

        return $this->_Parent;
    }

    /**
     * Gibt alle rekursive Parents als Objekte zurück
     * Site->Parent->ParentParent->ParentParentParent
     *
     * @return Array
     */
    public function getParents()
    {
        $parents = $this->getParent()->getParents();
        $parents[] = $this->getParent();

        return $parents;
    }

    /**
     * Stellt die Seite wieder her
     *
     * ??? wieso hier? und nicht im trash? O.o
     */
    public function restore()
    {
    }

    /**
     * Zerstört die Seite
     * Die Seite wird komplett aus der DB gelöscht und auch alle Beziehungen
     * Funktioniert nur wenn die Seite gelöscht ist
     */
    public function destroy()
    {
    }

    /**
     * Canonical URL - Um doppelte Inhalt zu vermeiden
     *
     * @return String
     */
    public function getCanonical()
    {
        return $this->getAttribute('url');
    }

    /**
     * Löscht den Seitencache
     */
    public function deleteCache()
    {
    }

    /**
     * Löscht den Seitencache
     */
    public function createCache()
    {
    }

    /**
     * Shortcut for QUI\Rights\Permission::hasSitePermission
     *
     * @param string              $permission - name of the permission
     * @param QUI\Users\User|Bool $User       - optional
     *
     * @return Bool|Integer
     */
    public function hasPermission($permission, $User = false)
    {
        return true;
    }

    /**
     * Shortcut for QUI\Rights\Permission::checkSitePermission
     *
     * @param string              $permission - name of the permission
     * @param QUI\Users\User|Bool $User       - optional
     *
     * @throws QUI\Exception
     */
    public function checkPermission($permission, $User = false)
    {

    }
}