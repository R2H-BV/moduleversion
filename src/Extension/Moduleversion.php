<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  System.Moduleversion
 *
 * @copyright   (C) 2022 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\System\Moduleversion\Extension;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Event\Extension\BeforeUninstallEvent;
use Joomla\CMS\Event\Model\AfterDeleteEvent;
use Joomla\CMS\Event\Model\AfterSaveEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\DispatcherInterface;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
use Joomla\Plugin\System\Moduleversion\Helper\ModuleversionHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Module Version Plugin
 *
 * @since  1.0.0
 */
final class Moduleversion extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;

    /**
     * Application object.
     *
     * @var    CMSApplicationInterface
     * @since  1.0.0
     */
    protected $app;

    /**
     * Affects constructor behavior. If true, language files will be loaded automatically.
     *
     * @var    boolean
     * @since  1.0.0
     */
    protected $autoloadLanguage = true;

    /**
     * Get the current module versions as result
     *
     * @var    object
     * @since  1.0.0
     */
    protected static $results;

    /**
     * Get the current module versions count
     *
     * @var    integer
     * @since  1.0.0
     */
    protected static $numberOfVersions = 0;

    /**
     * Constructor
     *
     * @param   DispatcherInterface  $dispatcher  The dispatcher
     * @param   array                $config      An optional associative array of configuration settings
     *
     * @since   1.0.0
     */
    public function __construct(DispatcherInterface $dispatcher, array $config = [])
    {
        parent::__construct($dispatcher, $config);

        $this->app = Factory::getApplication();
    }

    /**
     * Returns an array of events this subscriber will listen to.
     *
     * @return  array
     *
     * @since   1.0.0
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onBeforeRender'         => 'onBeforeRender',
            'onAfterRender'          => 'onAfterRender',
            'onBeforeCompileHead'    => 'onBeforeCompileHead',
            'onExtensionAfterSave'   => 'onExtensionAfterSave',
            'onExtensionAfterDelete' => 'onExtensionAfterDelete',
            'onExtensionBeforeUninstall' => 'onExtensionBeforeUninstall',
        ];
    }

    /**
     * Listener for the `onBeforeRender` event.
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function onBeforeRender(): void
    {
        // Check if client is administrator or view is module.
        if (!$this->app->isClient('administrator') || $this->app->input->get->getString('view') !== 'module') {
            return;
        }

        $this->loadVersionsResults();

        // Return if we have no entries.
        if (self::$numberOfVersions === 0) {
            return;
        }

        // Check if parameter is set and selected version is loaded.
        $input = $this->app->input;
        $restoredMessage = (bool) $input->get('modver', '', 'bool');

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
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function onAfterRender(): void
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

        // Create modal with dropdowns.
        $newBodyOutput = <<<HTML
        <div class="modal fade modal-module-versions"
            id="modal-moduleVersions"
            tabindex="-1"
            aria-labelledby="moduleVersionsModalLabel"
            aria-hidden="true">
        <div class="modal-dialog modal-xl">
        <div class="modal-content">
        <div class="modal-header">
        <h3 class="modal-title" id="moduleVersionsModalLabel">$modalTitle</h3>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-3">
        <form action="" method="POST">
        <div class="d-flex mb-2">
        <button class="btn btn-primary" type="submit" name="submit"/>
        <span class="icon-upload me-2" aria-hidden="true"></span>$modalButtonText
        </button>
        </div>
        <div class="mb-3">$modalInfo</div>
        <div class="accordion" id="accordionModInfo">
        HTML;

        foreach (self::$results as $index => $result) {
            $modulePosition = $result->position ? $result->position : Text::_('JNONE');
            $modulePublished = $result->published ? Text::_('JPUBLISHED') : Text::_('JUNPUBLISHED');
            $moduleShowtitle = $result->showtitle ? Text::_('JSHOW') : Text::_('JHIDE');
            $moduleLanguage = $result->language === '*' ? Text::_('JALL_LANGUAGE') : $result->language;

            $defaultParams = '<fieldset class="options-form p-3"><legend class="mb-0">' .
                Text::_('PLG_SYSTEM_MODULEVERSION_GLOBALPARAMS_TITLE') . '</legend>';
            $defaultParams .= '<div class="overflow-hidden">';
            $defaultParams .= '<dl class="dl-horizontal">';
            $defaultParams .= '<dt class="d-flex justify-content-between"><span>' .
                Text::_('COM_MODULES_FIELD_POSITION_LABEL');
            $defaultParams .= '</span><span class="ms-1">:</span></dt><dd><span class="badge bg-info">' .
                $modulePosition . '</span></dd>';
            $defaultParams .= '<dt class="d-flex justify-content-between"><span>' . Text::_('JSTATUS');
            $defaultParams .= '</span><span class="ms-1">:</span></dt><dd>' . $modulePublished . '</dd>';
            $defaultParams .= '<dt class="d-flex justify-content-between"><span>' . Text::_('JGLOBAL_TITLE');
            $defaultParams .= '</span><span class="ms-1">:</span></dt><dd>' . $moduleShowtitle . '</dd>';
            $defaultParams .= '<dt class="d-flex justify-content-between"><span>' . Text::_('JGRID_HEADING_LANGUAGE');
            $defaultParams .= '</span><span class="ms-1">:</span></dt><dd>' . $moduleLanguage . '</dd>';
            $defaultParams .= '<dt class="d-flex justify-content-between"><span>' . Text::_('JGRID_HEADING_ACCESS');
            $defaultParams .= '</span><span class="ms-1">:</span></dt><dd>' . $result->access . '</dd>';
            $defaultParams .= '<dt class="d-flex justify-content-between"><span>' .
                Text::_('COM_MODULES_FIELD_PUBLISH_UP_LABEL');
            $defaultParams .= '</span><span class="ms-1">:</span></dt><dd>' . $result->publish_up . '</dd>';
            $defaultParams .= '<dt class="d-flex justify-content-between"><span>' .
                Text::_('COM_MODULES_FIELD_PUBLISH_DOWN_LABEL');
            $defaultParams .= '</span><span class="ms-1">:</span></dt><dd>' . $result->publish_down . '</dd>';
            $defaultParams .= '</dl>';
            $defaultParams .= '</div></fieldset>';

            $modContent = '';
            $showParams = (bool) $this->params->get('showparams', 1);

            if (!strlen($result->content)) {
                // Rewrite image URL's to show the images and store in temp variable to prevent overriden output.
                $tempContent = str_replace('src="images', 'src="' . URI::root(true) . '/images', $result->content);

                // Remove href tags
                $tempContent = preg_replace('#href="(.*?)"#', '', $tempContent);

                $modContent = '<fieldset class="options-form p-3"><legend class="mb-0">' .
                    Text::_('PLG_SYSTEM_MODULEVERSION_CONTENT_TITLE') . '</legend>';
                $modContent .= '<div class="overflow-hidden ps-3">' . $tempContent . '</div></fieldset>';
            }

            $modParams = '';

            if (strlen($result->params) && $showParams) {
                $modParams = '<fieldset class="options-form p-3"><legend class="mb-0">' .
                    Text::_('PLG_SYSTEM_MODULEVERSION_PARAMS_TITLE') . '</legend>';
                $modParams .= '<div class="overflow-hidden">' .
                    ModuleversionHelper::formatOutput(json_decode($result->params, true)) . '</div></fieldset>';
            }

            $moduleTitle = $result->title;

            if ($result->current === 1) {
                $moduleTitle .= '<span class="ms-1 icon-star" aria-hidden="true"></span>';
            }

            $titlePadding = $result->note ? ' py-1 px-lg-3 py-lg-2 my-1' : ' py-2 px-lg-3 py-lg-2 my-1';

            $newBodyOutput .= <<<HTML

            <div class="accordion-item">
            <div class="accordion-header d-block d-lg-flex justify-content-between" id="heading$index">
            <div class="form-check d-flex align-items-center mx-3 pb-1">
            <input class="form-check-input mt-0 me-2" type="radio" name="index"
                value="$index" id="moduleRadioSelect$index">
            <label class="d-block d-sm-flex form-check-label" for="moduleRadioSelect$index">
            <span class="d-block pe-3 mod-date-info d-flex align-items-center">$result->changedate</span>
            </label>
            </div>
            <div class="button-wrapper w-100 pe-1">
            <button class="accordion-button collapsed collapsed d-flex align-items-center w-100$titlePadding"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#collapse$index"
                aria-expanded="false"
                aria-controls="collapse$index">
                <span class="d-block mod-title-info w-100 me-2">
                    <span class="d-block">$moduleTitle</span>
                    <span class="small d-block">$result->note</span>
                </span>

            </button>
            </div>
            </div>
            <div
                id="collapse$index"
                class="accordion-collapse collapse"
                aria-labelledby="heading$index"
                data-bs-parent="#accordionModInfo">
                <div class="accordion-body border-top">
                $defaultParams
                $modContent
                $modParams
                </div>
            </div>
            </div>
            HTML;
        }

        $newBodyOutput .= <<<HTML
        </div>
        </form>
        </div>
        </div>
        </div>
        </div>
        HTML;

        // Replace the closing body tag with form.
        $buffer = str_ireplace('</body>', $newBodyOutput . '</body>', $content);

        // Output the buffer.
        $this->app->setBody($buffer);

        // Get the current page URL.
        $currentUri = Uri::getInstance();
        $currentUri->toString();

        // Load the module version.
        if (($index = $this->app->input->post->getInt('index', -1)) >= 0) {
            // Get the index of the list.
            $item = self::$results[$index];

            // Update the current module with the selected version.
            ModuleversionHelper::updateModuleToVersion($item);

            // Set the check mark to new item.
            ModuleversionHelper::setCurrent($item->id, $item->mod_id);

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
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function onBeforeCompileHead(): void
    {
        // Check if client is administrator and view is module.
        if (!$this->app->isClient('administrator') && $this->app->input->get->getString('view') !== 'module') {
            return;
        }

        /** @var \Joomla\CMS\WebAsset\WebAssetManager $wa */
        $wa = $this->app->getDocument()->getWebAssetManager();

        $wa->getRegistry()->addRegistryFile('media/plg_system_moduleversion/joomla.asset.json');

        // Load the styling.
        $wa->useStyle('plg_system_moduleversion.moduleversion');
    }

    /**
     * Method is called when an extension is saved.
     *
     * @param   AfterSaveEvent  $event  The event
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function onExtensionAfterSave(AfterSaveEvent $event): void
    {
        $context = $event->getContext();
        $item = $event->getItem();
        $isNew = $event->getIsNew();

        // Check if client is Administrator or context is module
        if (!$this->app->isClient('administrator') || ($context !== 'com_modules.module' && $context !== 'com_advancedmodules.module')) {
            return;
        }

        $this->loadVersionsResults();

        // Store module version when isNew.
        if ($isNew === true || self::$numberOfVersions === 0) {
            // Reset the current check mark.
            ModuleversionHelper::resetCurrent($item->id);

            ModuleversionHelper::storeVersion($item);

            return;
        }

        // Compare module setting with latest version.
        $moduleHasChanged = ModuleversionHelper::compareVersion($item, self::$results[0]);

        // If nothing has changed return
        if (!$moduleHasChanged) {
            return;
        }

        // Reset the current check mark.
        ModuleversionHelper::resetCurrent($item->id);

        // Store module version in DB.
        ModuleversionHelper::storeVersion($item);

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
                    ModuleversionHelper::removeObsolete($item->id);
                }
            }
        }
    }

    /**
     * Method is called when an Extension is being deleted.
     *
     * @param   AfterDeleteEvent  $event  The event
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function onExtensionAfterDelete(AfterDeleteEvent $event): void
    {
        $context = $event->getContext();
        $table = $event->getItem();

        // Check if client is administrator or view is module.
        if (!$this->app->isClient('administrator') || ($context !== 'com_modules.module' && $context !== 'com_advancedmodules.module')) {
            return;
        }

        // Delete the versions of the trashed module.
        ModuleversionHelper::deleteVersion($table->id);
    }

    /**
     * Method is called when an Extension is being uninstalled.
     *
     * @param   Event  $event  The event
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function onExtensionBeforeUninstall(Event $event): void
    {
        $eid = $event->getArgument('eid');

        // Check if client is administrator or view is module.
        if (!$this->app->isClient('administrator')) {
            return;
        }

        // Delete the versions of the uninstalled module.
        ModuleversionHelper::uninstallVersion((string) $eid);
    }

    /**
     * Load the versions of the selected module.
     *
     * @return  void
     *
     * @since   1.0.0
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
        self::$results = ModuleversionHelper::getVersions($version);

        // Count the versions.
        self::$numberOfVersions = count(self::$results);
    }
}
