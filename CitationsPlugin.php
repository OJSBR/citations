<?php

/**
 * @file CitationsPlugin.php
 *
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class CitationsPlugin
 *
 * @brief Shows citation counts and citing works from Crossref, Scopus,
 *  Europe PMC and Google Scholar on the article/preprint landing page.
 */

namespace APP\plugins\generic\citations;

use APP\core\Application;
use APP\plugins\generic\citations\classes\CitationsHandler;
use APP\plugins\generic\citations\classes\form\CitationsSettingsForm;
use APP\template\TemplateManager;
use Exception;
use PKP\core\JSONMessage;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;


class CitationsPlugin extends GenericPlugin
{
    /**
     * @copydoc Plugin::register()
     *
     * @param null|mixed $mainContextId
     * @throws Exception
     */
    public function register($category, $path, $mainContextId = null): bool
    {
        $success = parent::register($category, $path, $mainContextId);
        if (Application::isUnderMaintenance()) {
            return true;
        }
        if ($success && $this->getEnabled($mainContextId)) {
            $request = Application::get()->getRequest();
            $templateMgr = TemplateManager::getManager($request);
            $templateMgr->addStyleSheet(
                'citations', $request->getBaseUrl() . '/' . $this->getPluginPath() . '/css/citations.css'
            );
            Hook::add('Templates::Article::Details', $this->citationsContent(...));
            Hook::add('Templates::Preprint::Details', $this->citationsContent(...));
            Hook::add('LoadHandler', $this->setPageHandler(...));
        }
        return $success;
    }

    /**
     * @copydoc Plugin::getDisplayName()
     */
    public function getDisplayName(): string
    {
        return __('plugins.generic.citations.title');
    }

    /**
     * @copydoc Plugin::getDescription()
     */
    public function getDescription(): string
    {
        return __('plugins.generic.citations.desc');
    }

    /**
     * Appends the citations widget to the article/preprint details template.
     */
    public function citationsContent(string $hookName, array $args): bool
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        if (!$context) {
            return Hook::CONTINUE;
        }
        $smarty = &$args[1];
        $pubId = $this->getPubId($smarty);
        $settings = json_decode((string) $this->getSetting($context->getId(), 'settings'), true);
        if (!empty($pubId) && !empty($settings)) {
            $smarty->assign([
                'imagePath' => $request->getBaseUrl() . '/' . $this->getPluginPath() . '/images/',
                'urlArgs' => ['doi' => $pubId],
                'showGoogle' => $settings['showGoogle'] ?? 0,
                'maxHeight' => $settings['maxHeight'] ?? 300
            ]);
            $smarty->addJavaScript('citations', $request->getBaseUrl() . '/' . $this->getPluginPath() . '/js/citations.js');
            $args[2] .= $smarty->fetch($this->getTemplateResource('citations.tpl'));
        }
        return Hook::CONTINUE;
    }

    /**
     * Routes <host>/index.php/<context>/citations/get to the plugin handler.
     *
     * OJS 3.5 no longer accepts the HANDLER_CLASS constant (PKPPageRouter throws
     * if it is defined); the handler instance is injected through $params[3].
     */
    public function setPageHandler(string $hookName, array $params): bool
    {
        $page = &$params[0];
        $handler = &$params[3];
        if ($this->getEnabled() && $page === 'citations') {
            $handler = new CitationsHandler();
            return Hook::ABORT;
        }
        return Hook::CONTINUE;
    }

    /**
     * @copydoc Plugin::getActions()
     */
    public function getActions($request, $actionArgs): array
    {
        $actions = parent::getActions($request, $actionArgs);
        if (!$this->getEnabled()) {
            return $actions;
        }
        $router = $request->getRouter();
        array_unshift($actions, new LinkAction(
            'settings',
            new AjaxModal(
                $router->url(
                    $request,
                    null,
                    null,
                    'manage',
                    null,
                    [
                        'verb' => 'settings',
                        'plugin' => $this->getName(),
                        'category' => 'generic'
                    ]
                ),
                $this->getDisplayName()
            ),
            __('manager.plugins.settings'),
            null
        ));
        return $actions;
    }


    /**
     * @copydoc Plugin::manage()
     * @throws Exception
     */
    public function manage($args, $request): JSONMessage
    {

        if ('settings' === $request->getUserVar('verb')) {
            $context = $request->getContext();
            $contextId = ($context == null) ? 0 : $context->getId();
            $templateMgr = TemplateManager::getManager($request);
            $templateMgr->registerPlugin('function', 'plugin_url', [$this, 'smartyPluginUrl']);

            $templateMgr->assign('citationsProviderOptions', [
                'all' => 'plugins.generic.citations.options.all',
                'scopus' => 'plugins.generic.citations.options.scopus',
                'crossref' => 'plugins.generic.citations.options.crossref'
            ]);
            $form = new CitationsSettingsForm($this, $contextId);
            if (!$request->getUserVar('save')) {
                $form->initData();
                return new JSONMessage(true, $form->fetch($request));
            }
            $form->readInputData();
            if ($form->validate()) {
                $form->execute();
                return new JSONMessage(true);
            }
        }
        return parent::manage($args, $request);
    }

    private function getPubId($smarty): ?string
    {
        $application = Application::getName();
        $submission = null;
        if (str_contains($application, 'ojs')) {
            $submission = $smarty->getTemplateVars('article');
        } elseif (str_contains($application, 'ops')) {
            $submission = $smarty->getTemplateVars('preprint');
        }
        return $submission?->getCurrentPublication()?->getDoi();
    }

}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\plugins\generic\citations\CitationsPlugin', '\CitationsPlugin');
}
