<?php
/**
 * Created by PhpStorm.
 * User: artem
 * Date: 06/10/2018
 * Time: 01:32
 */

namespace AppBundle\Service;


use AppBundle\Entity\User;
use Stripe\Charge;
use Stripe\Customer;
use Stripe\Invoice;
use Stripe\InvoiceItem;
use Stripe\StripeObject;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class StripeClient
 * @package AppBundle\Service
 */
class StripeClient
{
    const SHOPPING_CART_SERVICE_KEY = 'shopping_cart';

    /**
     * @var ContainerInterface
     */
    private $container;


    /**
     * @var \Doctrine\ORM\EntityManager
     */
    private $em;

    /**
     * StripeClient constructor.
     * @param ContainerInterface $container
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->em = $container->get('doctrine.orm.entity_manager');
    }


    /**
     * @param User $user
     * @param $token
     * @return StripeObject
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function createCustomer(User $user, $token)
    {
        if (!$user->getStripeCustomerId()) {
            $customer = Customer::create(array(
                "email" => $user->getEmail(),
                "source" => $token
            ));

            $user->setStripeCustomerId($customer->id);
            $this->em->persist($user);
            $this->em->flush();
        } else {
            /** @var StripeObject $customer */
            $customer = Customer::retrieve($user->getStripeCustomerId());
            $customer->source = $token;
            $customer->save();
        }

        return $customer;
    }

    /**
     * @param User $user
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function createCharge(User $user): void
    {
        Charge::create(array(
            "amount" => $this->container->get(self::SHOPPING_CART_SERVICE_KEY)->getTotal() * 100,
            "currency" => "usd",
            "customer" => $user->getStripeCustomerId(), // obtained with Stripe.js
        ));
    }

    /**
     * @param User $user
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function createInvoice(User $user): void
    {
        foreach ($this->container->get(self::SHOPPING_CART_SERVICE_KEY)->getProducts() as $index => $product) {
            InvoiceItem::create(array(
                "amount" => $product->getPrice() * 100,
                "currency" => "usd",
                "customer" => $user->getStripeCustomerId(), // obtained with Stripe.js
                "description" => $product->getName()
            ));

        }

        $invoice = Invoice::create([
            'customer' => $user->getStripeCustomerId()
        ]);

        $invoice->pay();

    }

}