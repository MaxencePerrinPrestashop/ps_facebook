<?php

namespace PrestaShop\Module\PrestashopFacebook\Event\Conversion;

use FacebookAds\Object\ServerSide\Event;

class ContactEvent extends AbstractEvent
{
    public function send($params)
    {
        $user = $this->createSdkUserData($userData);

        $event = (new Event())
            ->setEventName('Contact')
            ->setEventTime(time())
            ->setUserData($user);

        $events = [];
        $events[] = $event;

        $this->sendEvents($events);
    }
}
