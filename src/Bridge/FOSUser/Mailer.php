<?php

namespace Redking\ParseBundle\Bridge\FOSUser;

use FOS\UserBundle\Mailer\TwigSwiftMailer as BaseMailer;
use FOS\UserBundle\Model\UserInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class Mailer extends BaseMailer
{
    /**
     * {@inheritdoc}
     */
    public function sendResettingEmailMessage(UserInterface $user, $route = 'fos_user_resetting_reset')
    {
        $template = $this->parameters['template']['resetting'];
        $url = $this->router->generate($route, array('token' => $user->getConfirmationToken()), UrlGeneratorInterface::ABSOLUTE_URL);
        $context = array(
            'user' => $user,
            'confirmationUrl' => $url
        );
        $this->sendMessage($template, $context, $this->parameters['from_email']['resetting'], $user->getEmail());
    }
}
