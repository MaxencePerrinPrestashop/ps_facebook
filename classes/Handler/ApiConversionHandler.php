<?php

namespace PrestaShop\Module\PrestashopFacebook\Handler;

use Context;
use FacebookAds\Api;
use PrestaShop\Module\PrestashopFacebook\Adapter\ConfigurationAdapter;
use PrestaShop\Module\PrestashopFacebook\Adapter\ToolsAdapter;
use PrestaShop\Module\PrestashopFacebook\Config\Config;
use PrestaShop\Module\PrestashopFacebook\Event\Conversion\AddToCartEvent;
use PrestaShop\Module\PrestashopFacebook\Event\Conversion\CompleteRegistrationEvent;
use PrestaShop\Module\PrestashopFacebook\Event\Conversion\ContactEvent;
use PrestaShop\Module\PrestashopFacebook\Event\Conversion\CustomisationEvent;
use PrestaShop\Module\PrestashopFacebook\Event\Conversion\FirstCheckoutStepEvent;
use PrestaShop\Module\PrestashopFacebook\Event\Conversion\OrderConfirmationEvent;
use PrestaShop\Module\PrestashopFacebook\Event\Conversion\SearchEvent;
use PrestaShop\Module\PrestashopFacebook\Event\Conversion\ShopSubscriptionEvent;
use PrestaShop\Module\PrestashopFacebook\Event\Conversion\ViewContentEvent;
use PrestaShop\Module\PrestashopFacebook\Repository\ProductRepository;

class ApiConversionHandler
{
    /**
     * @var Context
     */
    private $context;

    public function __construct()
    {
        Api::init(
            null, // app_id
            null, // app_secret
            'EAAG8FZCTh0FABAJLPh2gQxbgZBVIAmDRCPq4Ea78jmy10wzPpZAZCL9h4JDP3z1A49IUrphg79lgQ9MtZArXkKTmXhpaPvGjSIOe79msSFsQl9Ngfwl8H26WilAaYjZBfLydyouPemafBLFbY7CZBZCSAsK3HX5RPUMDQDo8TisZAHEbOdVmxJDhM'
//            \Configuration::get(Config::FB_ACCESS_TOKEN) // access_token
        );

        $this->context = Context::getContext();
    }

    public function handleEvent($eventName, $params)
    {
        $pixelId = \Configuration::get(Config::PS_PIXEL_ID);

        switch ($eventName) {
            case 'hookActionSearch':
                (new SearchEvent($this->context, $pixelId))
                    ->send($params);
                break;
            case 'hookActionCartSave':
                (new AddToCartEvent($this->context, $pixelId, new ToolsAdapter(), new ProductRepository()))
                    ->send($params);
                break;
            case 'hookActionNewsletterRegistrationAfter':
                (new ShopSubscriptionEvent($this->context, $pixelId))
                    ->send($params);
                break;
            case 'hookDisplayOrderConfirmation':
                (new OrderConfirmationEvent($this->context, $pixelId, new ToolsAdapter()))
                    ->send($params);
                break;
            case 'hookActionSubmitAccountBefore':
                (new CompleteRegistrationEvent($this->context, $pixelId))
                    ->send($params);
                break;
            case 'hookActionObjectCustomerMessageAddAfter':
                (new ContactEvent($this->context, $pixelId))
                    ->send($params);
                break;
            case 'hookDisplayPersonalInformationTop':
                (new FirstCheckoutStepEvent($this->context, $pixelId, new ToolsAdapter()))
                    ->send($params);
                break;
            case 'hookDisplayHeader':
                (new ViewContentEvent($this->context, $pixelId, new ToolsAdapter(), new ConfigurationAdapter()))
                    ->send($params);
                if (true === \Tools::isSubmit('submitCustomizedData')) {
                    (new CustomisationEvent($this->context, $pixelId, new ProductRepository()))
                        ->send($params);
                }
                break;
            default:
                // unsupported event
                break;
        }
    }
}
