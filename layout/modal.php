<?php
declare(strict_types = 1);

use Joomla\CMS\Language\Text;
use Joomla\Plugin\System\Moduleversion\Helper;

$results = $displayData->results;

?><div class="modal fade modal-module-versions"
  id="modal-moduleVersions"
  tabindex="-1"
  aria-labelledby="moduleVersionsModalLabel"
  aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h3 class="modal-title" id="moduleVersionsModalLabel">
          <?php echo Text::_('PLG_SYSTEM_MODULEVERSION_MODALTITLE'); ?>
        </h3>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body p-3">
        <form action="" method="POST">
          <div class="d-flex mb-2">
            <button class="btn btn-primary" type="submit" name="submit"/>
              <span class="icon-upload me-2" aria-hidden="true"></span>
              <?php echo Text::_('PLG_SYSTEM_MODULEVERSION_LOADVERSION'); ?>
            </button>
          </div>

          <div class="mb-3">
            <?php echo Text::_('PLG_SYSTEM_MODULEVERSION_MODALINFO'); ?>
          </div>

          <div class="accordion" id="accordionModInfo">
            <?php foreach ($results as $index => $result) { ?>
                <?php
                $moduleTitle = $result->title;

                if ($result->current === 1) {
                    $moduleTitle .= '<span class="ms-1 icon-star" aria-hidden="true"></span>';
                }

                $modulePosition = $result->position ? $result->position : Text::_('JNONE');
                $modulePublished = $result->published ? Text::_('JPUBLISHED') : Text::_('JUNPUBLISHED');
                $moduleShowtitle = $result->showtitle ? Text::_('JSHOW') : Text::_('JHIDE');
                $moduleLanguage = $result->language === '*' ? Text::_('JALL_LANGUAGE') : $result->language;

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

                if (!strlen($result->params) && $showParams) {
                    $modParams = '<fieldset class="options-form p-3"><legend class="mb-0">' .
                        Text::_('PLG_SYSTEM_MODULEVERSION_PARAMS_TITLE') . '</legend>';
                    $modParams .= '<div class="overflow-hidden">' .
                        Helper::formatOutput(json_decode($result->params, true)) . '</div></fieldset>';
                }

                ?>

              <div class="accordion-item">
                <div
                  class="accordion-header d-block d-lg-flex justify-content-between"
                  id="heading<?php echo $index ?>">
                  <div class="form-check d-flex align-items-center mx-3 pb-1">
                    <input
                      class="form-check-input mt-0 me-2"
                      type="radio"
                      name="index"
                      value="<?php echo $index ?>"
                      id="moduleRadioSelect<?php echo $index ?>" />

                    <label class="d-block d-sm-flex form-check-label" for="moduleRadioSelect<?php echo $index ?>">
                      <span class="d-block pe-3 mod-date-info d-flex align-items-center">
                        <?php echo $result->changedate ?>
                      </span>
                    </label>
                  </div>
                  <div class="button-wrapper w-100 pe-1">
                    <button class="accordion-button collapsed collapsed d-flex align-items-center w-100$titlePadding"
                      type="button"
                      data-bs-toggle="collapse"
                      data-bs-target="#collapse<?php echo $index ?>"
                      aria-expanded="false"
                      aria-controls="collapse<?php echo $index ?>">
                      <span class="d-block mod-title-info w-100 me-2">
                        <span class="d-block"><?php echo $moduleTitle; ?></span>
                        <span class="small d-block">
                          <?php echo $result->note; ?>
                        </span>
                      </span>
                    </button>
                  </div>
                </div>
                <div
                  id="collapse<?php echo $index ?>"
                  class="accordion-collapse collapse"
                  aria-labelledby="heading<?php echo $index ?>"
                  data-bs-parent="#accordionModInfo">
                  <div class="accordion-body border-top">

                    <fieldset class="options-form p-3">
                      <legend class="mb-0">
                        <?php echo Text::_('PLG_SYSTEM_MODULEVERSION_GLOBALPARAMS_TITLE'); ?>
                      </legend>

                      <div class="overflow-hidden">
                        <dl class="dl-horizontal">';
                          <dt class="d-flex justify-content-between">
                            <span>
                              <?php echo Text::_('COM_MODULES_FIELD_POSITION_LABEL'); ?>
                            </span>
                            <span class="ms-1">:</span>
                          </dt>
                          <dd>
                            <span class="badge bg-info">
                              <?php echo $modulePosition; ?>
                            </span>
                          </dd>
                          <dt class="d-flex justify-content-between">
                            <span>
                              <?php echo Text::_('JSTATUS'); ?>
                            </span>
                            <span class="ms-1">:</span>
                          </dt>
                          <dd>
                            <?php echo $modulePublished; ?>
                          </dd>
                          <dt class="d-flex justify-content-between">
                            <span>
                              <?php echo Text::_('JGLOBAL_TITLE'); ?>
                            </span>
                            <span class="ms-1">:</span>
                          </dt>
                          <dd>
                            <?php echo $moduleShowtitle; ?>
                          </dd>';
                          <dt class="d-flex justify-content-between">
                            <span>
                              <?php echo Text::_('JGRID_HEADING_LANGUAGE'); ?>
                            </span>
                            <span class="ms-1">:</span>
                          </dt>
                          <dd>
                            <?php echo $moduleLanguage; ?>
                          </dd>
                          <dt class="d-flex justify-content-between">
                            <span>
                              <?php echo Text::_('JGRID_HEADING_ACCESS'); ?>
                            </span>
                            <span class="ms-1">:</span>
                          </dt>
                          <dd>
                            <?php echo $result->access; ?>
                          </dd>
                          <dt class="d-flex justify-content-between">
                            <span>
                              <?php echo Text::_('COM_MODULES_FIELD_PUBLISH_UP_LABEL'); ?>
                            </span>
                            <span class="ms-1">:</span>
                          </dt>
                          <dd>
                            <?php echo $result->publish_up; ?>
                          </dd>
                          <dt class="d-flex justify-content-between">
                            <span>
                              <?php echo Text::_('COM_MODULES_FIELD_PUBLISH_DOWN_LABEL'); ?>
                            </span>
                            <span class="ms-1">:</span>
                          </dt>
                          <dd>
                            <?php echo $result->publish_down; ?>
                          </dd>
                        </dl>
                      </div>
                    </fieldset>

                    <?php echo $modContent; ?>
                    <?php echo $modParams; ?>
                  </div>
                </div>
              </div>
            <?php } ?>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
