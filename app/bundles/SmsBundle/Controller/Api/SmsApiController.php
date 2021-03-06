<?php
/**
 * @copyright   2016 Mautic Contributors. All rights reserved.
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
namespace Mautic\SmsBundle\Controller\Api;

use Mautic\ApiBundle\Controller\CommonApiController;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class SmsApiController.
 */
class SmsApiController extends CommonApiController
{
    /**
     * Obtains a list of emails.
     *
     * @return Response
     */
    public function receiveAction()
    {
        $body = $this->request->get('Body');
        $from = $this->request->get('From');

        if ($body === 'STOP' && $this->factory->getHelper('sms')->unsubscribe($from)) {
            return new Response('<Response><Sms>You have been unsubscribed.</Sms></Response>', 200, ['Content-Type' => 'text/xml; charset=utf-8']);
        }

        // Return an empty response
        return new Response();
    }
}
