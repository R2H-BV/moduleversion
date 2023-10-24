<?php
declare(strict_types = 1);

/**
 * @package     Joomla.Plugin
 * @subpackage  System.Moduleversion
 *
 * @copyright   (C) 2022 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Factory;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\Plugin\System\Moduleversion\Helper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 *  Class for module version
 *
 * @package     Joomla.Plugin
 * @since     __DEPLOY_VERSION__
 */
class PlgSystemModuleversion extends CMSPlugin
{
    /**
     * Application object.
     *
     * @var    CMSApplicationInterface
     */
    protected $app;

    /**
     * Affects constructor behavior. If true, language files will be loaded automatically.
     *
     * @var    boolean
     */
    protected $autoloadLanguage = true;

    /**
     * Get the current module versions as result
     *
     * @var    object
     */
    protected static $results;

    /**
     * Get the current module versions as result
     *
     * @var    integer
     */
    protected static $numberOfVersions = 0;

    /**
     * Listener for the `onBeforeRender` event.
     */
    public function onBeforeRender(): void
    {
        // Check if client is administrator or view is module.
        if (!$this->app->isClient('administrator') || $this->app->input->get->getString('view') !== 'module') {
            return;
        }

        self::loadVersionsResults();

        // Return if we have no entries.
        if (self::$numberOfVersions === 0) {
            return;
        }

        // Check if parameter is set and selected version is loaded.
        $input = $this->app->input;
        (bool) $restoredMessage = $input->get('modver', '', 'bool');

        // Output message if version is loaded.
        if ($restoredMessage) {
            $this->app->enqueueMessage(Text::_('PLG_SYSTEM_MODULEVERSION_MODULE_RESTORED'), 'success');
        }

        // Count the versions.
        self::$numberOfVersions = count(self::$results);

        // Load the Bootstrap modal JS.
        HTMLHelper::_('bootstrap.modal');

        // Get an instance of the Toolbar.
        $toolbar = Toolbar::getInstance('toolbar');

        // Add button to the menu bar.
        $toolbar->popupButton('moduleVersions', Text::_('JTOOLBAR_VERSIONS'))->icon('icon-code-branch');
    }

    /**
     * Listener for the `onAfterRender` event.
     */
    public function onAfterRender(): void //phpcs:ignore
    {
        // Check if client is administrator or we have results.
        if (!$this->app->isClient('administrator') || !self::$results) {
            return;
        }

        // Get the body text from the Application.
        $content = $this->app->getBody();

        $modalTitle = Text::_('PLG_SYSTEM_MODULEVERSION_MODALTITLE');
        $modalButtonText = Text::_('PLG_SYSTEM_MODULEVERSION_LOADVERSION');
        $modalInfo = Text::_('PLG_SYSTEM_MODULEVERSION_MODALINFO');

        $layout = LayoutHelper::render(
            'modal',
            [
                'results' => self::$results,
                'numberOfVersions' => self::$numberOfVersions,
                'modalTitle' => $modalTitle,
                'modalButtonText' => $modalButtonText,
                'modalInfo' => $modalInfo,
            ],
            __DIR__ . '/layouts'
        );

        // Replace the closing body tag with form.
        $buffer = str_ireplace('</body>', $layout . '</body>', $content);

        // Output the buffer.
        $this->app->setBody($buffer);

        // Get the current page URL.
        $currentUri = \Joomla\CMS\Uri\Uri::getInstance();
        $currentUri->toString();

        // Load the module version.
        if (($index = $this->app->input->post->getInt('index', -1)) >= 0) {
            // Get the index of the list.
            $item = self::$results[$index];

            // Update the current module with the selected version.
            Helper::updateModuleToVersion($item);

            // Reset the current check mark.
            // Helper::resetCurrent();

            // Set the check mark to new item.
            Helper::setCurrent($item->id, $item->mod_id); //phpcs:ignore

            // Create waiting spinner overlay.
            $waitingspinner = str_ireplace(
                '</body>',
                '<div class="waitingspinner-container"><div class="lds-dual-ring"></div></div></body>',
                $content
            );

            // Add the waiting spinner while loading.
            Factory::getApplication()->setBody($waitingspinner);

            header("Refresh: 0; url=$currentUri&modver=1");
        }
    }

    /**
     * Triggered before compiling the head.
     */
    public function onBeforeCompileHead(): void
    {
        // Check if client is administrator and view is module.
        if (!$this->app->isClient('administrator') && $this->app->input->get->getString('view') !== 'module') {
            return;
        }

        /** @var Joomla\CMS\WebAsset\WebAssetManager $wa*/
        $wa = $this->app->getDocument()->getWebAssetManager();

        $wa->getRegistry()->addRegistryFile('media/plg_system_moduleversion/joomla.asset.json');

        // Load the styling.
        $wa->useStyle('plg_system_moduleversion.moduleversion');
    }

    /**
     * Method is called when an extension is saved.
     *
     * @param  object  $context The context which is active.
     * @param  object  $item    An optional associative array of module settings.
     * @param  boolean $isNew   Check if module is new.
     *
     * @since   1.0
     * @return  void
     */
    public function onExtensionAfterSave($context, $item, $isNew) //phpcs:ignore
    {
        // Check if client is Administrator or context is module
        if (!$this->app->isClient('administrator') || $context !== 'com_modules.module') {
            return;
        }

        self::loadVersionsResults();

        // Store module version when isNew.
        if ($isNew === true || self::$numberOfVersions === 0) {
            // Reset the current check mark.
            Helper::resetCurrent($item->id); // phpcs:ignore

            Helper::storeVersion($item);

            return;
        }

        // Compare module setting with latest version.
        $moduleHasChanged = Helper::compareVersion($item, self::$results[0]);

        // If noting has changed return
        if (!$moduleHasChanged) {
            return;
        }

        // Reset the current check mark.
        Helper::resetCurrent($item->id); // phpcs:ignore

        // Store module version in DB.
        Helper::storeVersion($item);

        // Get the number of versions set in plugin options.
        $versionsToKeep = (int) $this->params->get('versionstokeep', 10);

        // Error trap if 0 or negative number is set in the plugin.
        if ($versionsToKeep < 1) {
            $versionsToKeep = 1;
        }

        // Update the number of versions to store
        if (self::$numberOfVersions >= $versionsToKeep) {
            $toMuchVersions = self::$numberOfVersions - $versionsToKeep;

            if ($toMuchVersions >= 0) {
                for ($i = 0; $i <= $toMuchVersions; $i++) {
                    Helper::removeObsolete($item->id);
                }
            }
        }
    }

    /**
     * Method is called when an Extension is being deleted.
     *
     * @param  string $context The module.
     * @param  Table  $table   DataBase Table object.
     *
     */
    public function onExtensionAfterDelete($context, $table): void //phpcs:ignore
    {
        // Check if client is administrator or view is module.
        if (!$this->app->isClient('administrator') || $context !== 'com_modules.module') {
            return;
        }

        // Delete the versions of the trashed module.
        Helper::deleteVersion($table->id);
    }

    /**
     * Method is called when an Extension is being uninstalled.
     *
     * @param  integer $eid Extension id.
     *
     * @return  void
     *
     * @since   4.0.0
     */
    public function onExtensionBeforeUninstall($eid) //phpcs:ignore
    {
        // Check if client is administrator or view is module.
        if (!$this->app->isClient('administrator')) {
            return;
        }

        // Delete the versions of the uninstalled module.
        Helper::uninstallVersion((string) $eid);
    }

    /**
     * Load the versions of the selected module.
     */
    protected function loadVersionsResults(): void
    {
        if (self::$results !== null) {
            return;
        }

        $version = $this->app->input->get->getInt('id');

        if (!$version) {
            self::$numberOfVersions = 0;
            self::$results = [];

            return;
        }

        // Get the entries of the current module from the DB as result.
        self::$results = Helper::getVersions($version);

        // Count the versions.
        self::$numberOfVersions = count(self::$results);
    }
}
