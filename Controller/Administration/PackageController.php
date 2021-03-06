<?php

/*
 * This file is part of the Claroline Connect package.
 *
 * (c) Claroline Consortium <consortium@claroline.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Claroline\CoreBundle\Controller\Administration;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Claroline\CoreBundle\Event\StrictDispatcher;
use Claroline\CoreBundle\Manager\ToolManager;
use Claroline\CoreBundle\Manager\DependencyManager;
use Claroline\CoreBundle\Manager\BundleManager;
use Claroline\CoreBundle\Manager\IPWhiteListManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use JMS\DiExtraBundle\Annotation as DI;
use Sensio\Bundle\FrameworkExtraBundle\Configuration as EXT;
use JMS\SecurityExtraBundle\Annotation as SEC;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Claroline\CoreBundle\Library\Configuration\PlatformConfigurationHandler;
use Claroline\CoreBundle\Library\Utilities\FileSystem;

/**
 * @DI\Tag("security.secure_service")
 * @SEC\PreAuthorize("canOpenAdminTool('platform_packages')")
 */
class PackageController extends Controller
{
    private $toolManager;
    private $eventDispatcher;
    private $adminToolPlugin;
    private $ipwlm;
    private $bundleManager;
    private $platformConfigHandler;

    /**
     * @DI\InjectParams({
     *      "eventDispatcher" = @DI\Inject("claroline.event.event_dispatcher"),
     *      "toolManager"     = @DI\Inject("claroline.manager.tool_manager"),
     *      "dm"              = @DI\Inject("claroline.manager.dependency_manager"),
     *      "ipwlm"           = @DI\Inject("claroline.manager.ip_white_list_manager"),
     *      "bundleManager"   = @DI\Inject("claroline.manager.bundle_manager"),
     *      "configHandler"   = @DI\Inject("claroline.config.platform_config_handler")
     * })
     */
    public function __construct(
        StrictDispatcher             $eventDispatcher,
        ToolManager                  $toolManager,
        DependencyManager            $dm,
        IPWhiteListManager           $ipwlm,
        BundleManager                $bundleManager,
        PlatformConfigurationHandler $configHandler

    )
    {
        $this->eventDispatcher = $eventDispatcher;
        $this->toolManager     = $toolManager;
        $this->adminToolPlugin = $toolManager->getAdminToolByName('platform_packages');
        $this->dm              = $dm;
        $this->ipwlm           = $ipwlm;
        $this->bundleManager   = $bundleManager;
        $this->configHandler   = $configHandler;
    }

    /**
     * @EXT\Route(
     *     "/",
     *     name="claro_admin_plugins",
     *     options = {"expose"=true}
     * )
     *
     * @EXT\Template()
     *
     * Display the plugin list
     *
     * @return Response
     */
    public function listAction()
    {
        $fs = new FileSystem();
        $rootPath = $this->container->getParameter('claroline.param.root_directory') . '/';
        $danger = array(
            'simple' => array(
                realpath($rootPath . 'vendor/composer/autoload_namespaces.php') => is_writable(realpath($rootPath . 'vendor/composer/autoload_namespaces.php')),
                realpath($rootPath . 'app/config/bundles.ini') => is_writable(realpath($rootPath . 'app/config/bundles.ini')),
                realpath($rootPath . 'vendor') => is_writable(realpath($rootPath . 'vendor')),
                realpath($rootPath . 'app/logs') => is_writable(realpath($rootPath . 'app/logs')),
                realpath($rootPath . 'web/bundles') => is_writable(realpath($rootPath . 'web/bundles'))
            ),
            'recursive' => array(
                realpath($rootPath . 'web/js') => $fs->isWritable(realpath($rootPath . 'web/js'), true),
                realpath($rootPath . 'app/cache') => $fs->isWritable(realpath($rootPath . 'app/cache'), true),
                realpath($rootPath . 'web/themes') =>  $fs->isWritable(realpath($rootPath . 'web/themes'), true),
                realpath($rootPath . 'web/css') =>  $fs->isWritable(realpath($rootPath . 'web/css'), true),
                realpath($rootPath . 'web/vendor') =>  $fs->isWritable(realpath($rootPath . 'web/vendor'), true)
            )
        );

        $refresh = array(
            'simple' => array(
                realpath($rootPath . 'web/bundles') => is_writable(realpath($rootPath . 'web/bundles')),
            ),
            'recursive' => array(
                realpath($rootPath . 'web/js') => $fs->isWritable(realpath($rootPath . 'web/js'), true),
                realpath($rootPath . 'web/themes') =>  $fs->isWritable(realpath($rootPath . 'web/themes'), true),
                realpath($rootPath . 'web/css') =>  $fs->isWritable(realpath($rootPath . 'web/css'), true),
                realpath($rootPath . 'web/vendor') =>  $fs->isWritable(realpath($rootPath . 'web/vendor'), true)
            )
        );

        $coreBundle = $this->bundleManager->getBundle('CoreBundle');
        $coreVersion = $coreBundle->getVersion();
        $api = $this->configHandler->getParameter('repository_api');
        $url = $api . "/version/$coreVersion/tags/last";

        if ($this->configHandler->getParameter('use_repository_test')) {
            $url .= '/test';
        }
        //ask the server wich are the last available packages now.
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $data = curl_exec($ch);
        curl_close($ch);
        $fetched = json_decode($data);
        $installed = $this->bundleManager->getInstalled();
        $plugins = $this->bundleManager->getConfigurablePlugins();

        foreach ($installed as $install) {
            foreach ($plugins as $plugin) {
                if ($plugin->getBundleName() === $install->getName()) {
                    $install->setIsConfigurable(true);
                }
            }
        }

        $uninstalled = $this->bundleManager->getUninstalledFromServer($fetched);

        return array(
            'fetched' => $fetched,
            'installed' => $installed,
            'uninstalled' => $uninstalled,
            'danger' => $danger,
            'refresh' => $refresh,
            'useTestRepo' => $this->configHandler->getParameter('use_repository_test')
        );
    }

    /**
     * @EXT\Route(
     *     "/bundle/{bundle}/install/log/{date}",
     *     name="claro_admin_plugin_install",
     *     options = {"expose"=true}
     * )
     *
     * Install a plugin.
     *
     * @return Response
     */
    public function installFromRemoteAction($bundle, $date)
    {
        $this->bundleManager->installRemoteBundle($bundle, $date);

        return new Response('Done.');
    }

    /**
     * @EXT\Route(
     *     "/install/log/{date}",
     *     name="claro_admin_plugins_log",
     *     options = {"expose"=true}
     * )
     *
     * Install a plugin.
     *
     * @return Response
     */
    public function displayUpdateLog($date)
    {
        $content = @file_get_contents($this->bundleManager->getLogFile() . '-' . $date . '.log');
        if (!$content) $content = '';

        return new Response($content);
    }

    /**
     * @EXT\Route(
     *     "/plugin/parameters/{pluginShortName}",
     *     name="claro_admin_plugin_parameters"
     * )
     */
    public function pluginParametersAction($pluginShortName)
    {
        $eventName = strtolower("plugin_options_{$pluginShortName}");
        $event = $this->eventDispatcher->dispatch($eventName, 'PluginOptions', array());

        return $event->getResponse();
    }

    /**
     * @EXT\Route(
     *     "/package/repository/test",
     *     name="claro_admin_use_test_repository"
     * )
     */
    public function useTestPackageAction()
    {
        $this->configHandler->setParameter('use_repository_test', true);

        return new RedirectResponse($this->generateUrl('claro_admin_plugins'));
    }

    /**
     * @EXT\Route(
     *     "/package/repository/stable",
     *     name="claro_admin_use_stable_repository"
     * )
     */
    public function useStablePackageAction()
    {
        $this->configHandler->setParameter('use_repository_test', false);

        return new RedirectResponse($this->generateUrl('claro_admin_plugins'));
    }

    /**
     * @EXT\Route(
     *     "/refresh/{date}",
     *     name="claro_admin_refresh",
     *     options = {"expose"=true}
     * )
     */
    public function refreshPlatformAction($date)
    {
        $this->bundleManager->refresh($date);

        return new Response('done');
    }

    /**
     * @EXT\Route(
     *     "/refresh/log/{date}",
     *     name="claro_admin_plugins_refresh_log",
     *     options = {"expose"=true}
     * )
     *
     * Install a plugin.
     *
     * @return Response
     */
    public function displayRefreshLog($date)
    {
        $content = @file_get_contents($this->bundleManager->getRefreshLog() . '-' . $date . '.log');
        if (!$content) $content = '';

        return new Response($content);
    }
}
