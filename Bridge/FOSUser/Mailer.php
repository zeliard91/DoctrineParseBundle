<?php

namespace Redking\ParseBundle\Bridge\FOSUser;

use FOS\UserBundle\Mailer\Mailer as BaseMailer;

use FOS\UserBundle\Model\UserInterface;

class Mailer extends BaseMailer
{
    /**
     * {@inheritdoc}
     */
    public function sendResettingEmailMessage(UserInterface $user, $route = 'fos_user_resetting_reset')
    {
        $template = $this->parameters['resetting.template'];
        $url = $this->router->generate($route, array('token' => $user->getConfirmationToken()), true);
        $rendered = $this->templating->render($template, array(
            'user' => $user,
            'confirmationUrl' => $url
        ));
        $this->sendEmailMessage($rendered, $this->parameters['from_email']['resetting'], $user->getEmail());
    }
}
