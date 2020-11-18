<?php

namespace PrestaShop\Module\PrestashopFacebook\Event\Conversion;

use FacebookAds\Object\ServerSide\CustomData;
use FacebookAds\Object\ServerSide\Event;

class SearchEvent extends AbstractEvent
{
    public function send($params)
    {
        $user = $this->createSdkUserData($userData);
        $customData = (new CustomData())
            ->setSearchString(pSQL($params['searched_query']))
            ->setItemNumber(pSQL($params['total']));

        $event = (new Event())
            ->setEventName('Search')
            ->setEventTime(time())
            ->setUserData($user)
            ->setCustomData($customData);

        $events = [];
        $events[] = $event;

        $this->sendEvents($events);
    }
}
