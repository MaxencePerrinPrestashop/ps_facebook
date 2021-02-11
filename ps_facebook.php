<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License version 3.0
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

use Dotenv\Dotenv;
use PrestaShop\Module\PrestashopFacebook\Buffer\TemplateBuffer;
use PrestaShop\Module\PrestashopFacebook\Config\Config;
use PrestaShop\Module\PrestashopFacebook\Database\Installer;
use PrestaShop\Module\PrestashopFacebook\Database\Uninstaller;
use PrestaShop\Module\PrestashopFacebook\Dispatcher\EventDispatcher;
use PrestaShop\Module\PrestashopFacebook\Handler\ErrorHandler\ErrorHandler;
use PrestaShop\Module\PrestashopFacebook\Handler\MessengerHandler;
use PrestaShop\Module\PrestashopFacebook\Repository\TabRepository;
use PrestaShop\Module\Ps_facebook\Tracker\Segment;
use PrestaShop\ModuleLibServiceContainer\DependencyInjection\ServiceContainer;

/*
 * 2007-2020 PrestaShop.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 *  @author    PrestaShop SA <contact@prestashop.com>
 *  @copyright 2007-2020 PrestaShop SA
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

class Ps_facebook extends Module
{
    /**
     * @var ServiceContainer
     */
    private $serviceContainer;

    const MODULE_ADMIN_CONTROLLERS = [
        'AdminAjaxPsfacebook',
        'AdminPsfacebookModule',
    ];

    const FRONT_CONTROLLERS = [
        'FrontAjaxFacebookWebhooks',
        'FrontAjaxFacebookAjax',
    ];

    const HOOK_LIST = [
        'displayHeader',
        'actionCustomerAccountAdd',
        'actionObjectContactAddAfter',
        'actionCartSave',
        'actionSearch',
        'displayOrderConfirmation',
        'actionAjaxDieProductControllerDisplayAjaxQuickviewAfter',
        'actionObjectCustomerMessageAddAfter',
        'displayFooter',
        'actionNewsletterRegistrationAfter',
        'actionSubmitAccountBefore',
        'displayPersonalInformationTop',
        'displayBackOfficeHeader',
        'actionFrontControllerSetMedia',
        'actionFacebookCallPixel',
    ];

    const CONFIGURATION_LIST = [
        Config::PS_PIXEL_ID,
        Config::PS_FACEBOOK_USER_ACCESS_TOKEN,
        Config::PS_FACEBOOK_PROFILES,
        Config::PS_FACEBOOK_PAGES,
        Config::PS_FACEBOOK_BUSINESS_MANAGER_ID,
        Config::PS_FACEBOOK_AD_ACCOUNT_ID,
        Config::PS_FACEBOOK_CATALOG_ID,
        Config::PS_FACEBOOK_EXTERNAL_BUSINESS_ID,
        Config::PS_FACEBOOK_PIXEL_ENABLED,
        Config::PS_FACEBOOK_PRODUCT_SYNC_FIRST_START,
        Config::PS_FACEBOOK_PRODUCT_SYNC_ON,
    ];

    /**
     * @var string
     */
    public $controllerAdmin;

    /**
     * @var bool
     */
    public $psVersionIs17;

    /**
     * @var string
     */
    public $css_path;

    /**
     * @var string
     */
    public $docs_path;

    /**
     * @var string
     */
    public $js_path;

    /**
     * @var EventDispatcher
     */
    public $eventDispatcher;

    /**
     * @var TemplateBuffer
     */
    public $templateBuffer;

    public $front_controller = null;

    public function __construct()
    {
        $this->name = 'ps_facebook';
        $this->tab = 'advertising_marketing';
        $this->version = '1.4.5';
        $this->author = 'PrestaShop';
        $this->need_instance = 0;
        $this->module_key = '860395eb54512ec72d98615805274591';
        $this->controllerAdmin = 'AdminAjaxPsfacebook';
        $this->bootstrap = false;

        parent::__construct();

        $this->displayName = $this->l('PS Facebook');
        $this->description = $this->l('PS Facebook gives you all the tools you need to successfully sell and market across Facebook and Instagram. Discover new opportunities to help you scale and grow your business, and manage all your Facebook accounts and products from one place.');
        $this->psVersionIs17 = (bool) version_compare(_PS_VERSION_, '1.7', '>=');
        $this->css_path = $this->_path . 'views/css/';
        $this->js_path = $this->_path . 'views/js/';
        $this->docs_path = $this->_path . 'docs/';
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall this module?');
        $this->ps_versions_compliancy = ['min' => '1.7.0.0', 'max' => _PS_VERSION_];
        $this->front_controller = $this->context->link->getModuleLink(
            $this->name,
            'FrontAjaxPixel',
            [],
            true
        );

        if ($this->serviceContainer === null) {
            $this->serviceContainer = new ServiceContainer($this->name, $this->getLocalPath());
            $this->templateBuffer = $this->getService(TemplateBuffer::class);
        }

        $this->loadEnv();
    }

    private function loadEnv()
    {
        if (file_exists(_PS_MODULE_DIR_ . 'ps_facebook/.env')) {
            $dotenv = Dotenv::create(_PS_MODULE_DIR_ . 'ps_facebook/');
            $dotenv->load();
        }
    }

    /**
     * @param string $serviceName
     *
     * @return mixed
     */
    public function getService($serviceName)
    {
        return $this->serviceContainer->getService($serviceName);
    }

    /**
     * This method is trigger at the installation of the module
     * - install all module tables
     * - set some configuration value
     * - register hook used by the module.
     *
     * @return bool
     */
    public function install()
    {
        // We can't init the Uninstaller in CLI, as it has been declared in the admin container and PrestaShop
        // does not have the _PS_ADMIN_DIR_ in this environment.
        // prestashop/module-lib-service-container:1.3.1 is known as incompatible
        // $installer = $this->getService(Installer::class);
        if (!parent::install()) {
            $this->_errors[] = $this->l('Unable to install module');

            return false;
        }

        if (!(new PrestaShop\AccountsAuth\Installer\Install())->installPsAccounts()) {
            $this->_errors[] = $this->l('Unable to install ps accounts');

            return false;
        }

        $installer = new Installer(
            $this,
            $this->getService(Segment::class),
            $this->getService(ErrorHandler::class)
        );

        if (!$installer->install()) {
            $this->_errors = $installer->getErrors();

            return false;
        }

        return true;
    }

    /**
     * Triggered at the uninstall of the module
     * - erase tables
     * - erase configuration value
     * - unregister hook.
     *
     * @return bool
     */
    public function uninstall()
    {
        // We can't init the Uninstaller in CLI, as it has been declared in the admin container and PrestaShop
        // does not have the _PS_ADMIN_DIR_ in this environment.
        // prestashop/module-lib-service-container:1.3.1 is known as incompatible
        // $uninstaller = $this->getService(Uninstaller::class);

        $uninstaller = new Uninstaller(
            $this,
            $this->getService(TabRepository::class),
            $this->getService(Segment::class),
            $this->getService(ErrorHandler::class)
        );

        return $uninstaller->uninstall() &&
            parent::uninstall();
    }

    /**
     * Activate current module.
     *
     * @param bool $force_all If true, enable module for all shop
     *
     * @return bool
     *
     * @throws PrestaShopException
     */
    public function enable($force_all = false)
    {
        /** @var \PrestaShop\Module\Ps_facebook\Tracker\Segment $segment */
        $segment = $this->getService(\PrestaShop\Module\Ps_facebook\Tracker\Segment::class);
        $segment->setMessage('Enable module');
        $segment->track();

        return parent::enable($force_all);
    }

    /**
     * Activate current module.
     *
     * @param bool $force_all If true, enable module for all shop
     *
     * @return bool
     *
     * @throws PrestaShopException
     */
    public function disable($force_all = false)
    {
        /** @var \PrestaShop\Module\Ps_facebook\Tracker\Segment $segment */
        $segment = $this->getService(\PrestaShop\Module\Ps_facebook\Tracker\Segment::class);
        $segment->setMessage('Disable module');
        $segment->track();

        return parent::disable($force_all);
    }

    public function getContent()
    {
        // With the version prestashop/prestashop-accounts-auth:2.1.9, a successful login will redirect
        // to the module configuration page with extra parameters.
        // We filter the default parameters so the extra ones remain present on the controller we redirect to.
        unset($_GET['controller'], $_GET['configure'], $_GET['token'], $_GET['controllerUri']);

        Tools::redirectAdmin($this->context->link->getAdminLink('AdminPsfacebookModule') . '&' . http_build_query($_GET));
    }

    /**
     * return __FILE__
     *
     * @return string
     */
    public function getFilePath()
    {
        return __FILE__;
    }

    public function hookDisplayBackOfficeHeader()
    {
        $this->context->controller->addCSS($this->getPathUri() . 'views/css/admin/menu.css');
    }

    public function hookActionFrontControllerSetMedia()
    {
        Media::addJsDef([
            'prestashopFacebookAjaxController' => $this->context->link->getModuleLink($this->name, 'Ajax', [], true),
        ]);

        $this->context->controller->addJS("{$this->_path}views/js/front/conversion-api.js");
    }

    public function hookActionCustomerAccountAdd(array $params)
    {
        /** @var EventDispatcher $eventDispatcher */
        $eventDispatcher = $this->getService(EventDispatcher::class);
        $eventDispatcher->dispatch(__FUNCTION__, $params);
    }

    public function hookDisplayHeader(array $params)
    {
        /** @var EventDispatcher $eventDispatcher */
        $eventDispatcher = $this->getService(EventDispatcher::class);
        $eventDispatcher->dispatch(__FUNCTION__, $params);

        return $this->templateBuffer->flush();
    }

    // Handle QuickView (ViewContent)
    public function hookActionAjaxDieProductControllerDisplayAjaxQuickviewAfter($params)
    {
        /** @var EventDispatcher $eventDispatcher */
        $eventDispatcher = $this->getService(EventDispatcher::class);
        $eventDispatcher->dispatch(__FUNCTION__, $params);
    }

    public function hookActionSearch(array $params)
    {
        if (true === $this->context->controller->ajax) {
            return;
        }

        /** @var EventDispatcher $eventDispatcher */
        $eventDispatcher = $this->getService(EventDispatcher::class);
        $eventDispatcher->dispatch(__FUNCTION__, $params);
    }

    public function hookActionCartSave(array $params)
    {
        /** @var EventDispatcher $eventDispatcher */
        $eventDispatcher = $this->getService(EventDispatcher::class);
        $eventDispatcher->dispatch(__FUNCTION__, $params);
    }

    public function hookActionObjectCustomerMessageAddAfter(array $params)
    {
        /** @var EventDispatcher $eventDispatcher */
        $eventDispatcher = $this->getService(EventDispatcher::class);
        $eventDispatcher->dispatch(__FUNCTION__, $params);
    }

    public function hookDisplayOrderConfirmation(array $params)
    {
        /** @var EventDispatcher $eventDispatcher */
        $eventDispatcher = $this->getService(EventDispatcher::class);
        $eventDispatcher->dispatch(__FUNCTION__, $params);

        return $this->templateBuffer->flush();
    }

    public function hookActionNewsletterRegistrationAfter(array $params)
    {
        /** @var EventDispatcher $eventDispatcher */
        $eventDispatcher = $this->getService(EventDispatcher::class);
        $eventDispatcher->dispatch(__FUNCTION__, $params);
    }

    public function hookActionSubmitAccountBefore(array $params)
    {
        /** @var EventDispatcher $eventDispatcher */
        $eventDispatcher = $this->getService(EventDispatcher::class);
        $eventDispatcher->dispatch(__FUNCTION__, $params);
    }

    public function hookDisplayFooter()
    {
        $content = '';
        $messengerHandler = $this->getService(MessengerHandler::class);
        if ($messengerHandler->isReady()) {
            $this->context->smarty->assign($messengerHandler->handle());
            $content .= $this->context->smarty->fetch('module:ps_facebook/views/templates/hook/messenger.tpl');
        }

        return $content . $this->templateBuffer->flush();
    }

    public function hookActionFacebookCallPixel($params)
    {
        /** @var EventDispatcher $eventDispatcher */
        $eventDispatcher = $this->getService(EventDispatcher::class);
        $eventDispatcher->dispatch(__FUNCTION__, $params);
    }

    public function hookDisplayPersonalInformationTop(array $params)
    {
        if (!$this->isFirstCheckoutStep()) {
            return false;
        }

        /** @var EventDispatcher $eventDispatcher */
        $eventDispatcher = $this->getService(EventDispatcher::class);
        $eventDispatcher->dispatch(__FUNCTION__, $params);

        return $this->templateBuffer->flush();
    }

    /**
     * Tells if we are in the Payment step from the order tunnel.
     * We use the ReflectionObject because it only exists from Prestashop 1.7.7
     *
     * @return bool
     */
    private function isFirstCheckoutStep()
    {
        $checkoutSteps = $this->getAllOrderSteps();

        /* Get the checkoutPaymentKey from the $checkoutSteps array */
        foreach ($checkoutSteps as $stepObject) {
            if ($stepObject instanceof CheckoutAddressesStep) {
                return (bool) $stepObject->isCurrent();
            }
        }

        return false;
    }

    /**
     * Get all existing Payment Steps from front office.
     * Use ReflectionObject before Prestashop 1.7.7
     * From Prestashop 1.7.7 object checkoutProcess is now public
     *
     * @return array
     */
    private function getAllOrderSteps()
    {
        $isPrestashop177 = version_compare(_PS_VERSION_, '1.7.7.0', '>=');

        if (true === $isPrestashop177) {
            return $this->context->controller->getCheckoutProcess()->getSteps();
        }

        /* Reflect checkoutProcess object */
        $reflectedObject = (new ReflectionObject($this->context->controller))->getProperty('checkoutProcess');
        $reflectedObject->setAccessible(true);

        /* Get Checkout steps data */
        $checkoutProcessClass = $reflectedObject->getValue($this->context->controller);

        return $checkoutProcessClass->getSteps();
    }
}
