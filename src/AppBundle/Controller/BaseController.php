<?php

namespace AppBundle\Controller;

use AppBundle\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

/**
 * Class BaseController
 * @package AppBundle\Controller
 */
class BaseController extends Controller
{
    /**
     * Overridden to type-hint *our* User class
     *
     * @return User
     */
    public function getUser()
    {
        return parent::getUser();
    }
}
