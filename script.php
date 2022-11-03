<?php
// No direct access to this file
defined('_JEXEC') or die;

/**
 * @package    Joomla.Administrator
 * @subpackage com_foos
 *
 * @copyright Copyright (C) 2005 - 2020 Open Source Matters, Inc. All rights reserved.
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */
// No direct access to this file

use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Factory;
/**
 * Script file of Foo module
 */
class plgSystemModuleversionInstallerScript
{
    /**
     * Extension script constructor.
     *
     * @return  void
     */
    public function __construct()
    {
        $this->minimumJoomla = '4.0';
        $this->minimumPhp = JOOMLA_MINIMUM_PHP;
    }

    /**
     * Method to install the extension
     *
     * @param InstallerAdapter $parent The class calling this method
     *
     * @return boolean True on success
     */
    public function install($parent)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = "CREATE TABLE IF NOT EXISTS `#__modules_versions` (
            `id` bigint(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `current` tinyint(1) NOT NULL DEFAULT '0',
            `mod_id` bigint(11) UNSIGNED NOT NULL,
            `asset_id` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'FK to the #__assets table.',
            `title` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
            `note` varchar(255) COLLATE utf8mb4_unicode_ci NULL,
            `content` text COLLATE utf8mb4_unicode_ci,
            `ordering` int(11) NOT NULL DEFAULT '0',
            `position` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
            `checked_out` int(10) UNSIGNED DEFAULT NULL,
            `checked_out_time` datetime DEFAULT NULL,
            `publish_up` datetime DEFAULT NULL,
            `publish_down` datetime DEFAULT NULL,
            `published` tinyint(4) NOT NULL DEFAULT '0',
            `module` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `access` int(10) UNSIGNED NOT NULL DEFAULT '0',
            `showtitle` tinyint(3) UNSIGNED NOT NULL DEFAULT '1',
            `params` text COLLATE utf8mb4_unicode_ci NOT NULL,
            `client_id` tinyint(4) NOT NULL DEFAULT '0',
            `language` char(7) COLLATE utf8mb4_unicode_ci NOT NULL,
            `changedate` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $db->setQuery($query)->execute();

        echo Text::_('PLG_SYSTEM_MODULEVERSION_INSTALLERSCRIPT_INSTALL');

        return true;
    }

    /**
     * Method to uninstall the extension
     *
     * @param InstallerAdapter $parent The class calling this method
     *
     * @return boolean  True on success
     */
    public function uninstall($parent)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $db->setQuery("DROP TABLE `#__modules_versions`")->execute();

        echo Text::_('PLG_SYSTEM_MODULEVERSION_INSTALLERSCRIPT_UNINSTALL');

        return true;
    }

    /**
     * Function called before extension installation/update/removal procedure commences
     *
     * @param string           $type   The type of change (install, update or discover_install, not uninstall)
     * @param InstallerAdapter $parent The class calling this method
     *
     * @return boolean  True on success
     */
    public function preflight($type, $parent)
    {
        // Check for the minimum PHP version before continuing
        if (!empty($this->minimumPhp) && version_compare(PHP_VERSION, $this->minimumPhp, '<')) {
            Log::add(Text::sprintf('JLIB_INSTALLER_MINIMUM_PHP', $this->minimumPhp), Log::WARNING, 'jerror');
            return false;
        }
        // Check for the minimum Joomla version before continuing
        if (!empty($this->minimumJoomla) && version_compare(JVERSION, $this->minimumJoomla, '<')) {
            Log::add(Text::sprintf('JLIB_INSTALLER_MINIMUM_JOOMLA', $this->minimumJoomla), Log::WARNING, 'jerror');
            return false;
        }

        return true;
    }
}
