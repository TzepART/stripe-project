<?php
/**
 * Created by PhpStorm.
 * User: artem
 * Date: 06/10/2018
 * Time: 01:32
 */

namespace AppBundle\Service;


use AppBundle\AppBundle;
use AppBundle\Entity\Product;
use AppBundle\Entity\User;
use Stripe\Charge;
use Stripe\Customer;
use Stripe\Invoice;
use Stripe\InvoiceItem;
use Stripe\Stripe;
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
     * @param string $secretKey
     * @param ContainerInterface $container
     */
    public function __construct(string $secretKey, ContainerInterface $container)
    {
        $this->container = $container;
        $this->em = $container->get('doctrine.orm.entity_manager');
        Stripe::setApiKey($secretKey);
    }


    /**
     * @param User $user
     * @param $token
     * @return StripeObject
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function getCustomerByUser(User $user, $token)
    {
        if (!$user->getStripeCustomerId()) {
            $customer = $this->createCustomer($user, $token);
        } else {
            $customer = $this->updateCustomer($user, $token);
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
     * @return \Stripe\ApiResource
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function createInvoice(User $user)
    {
        foreach ($this->container->get(self::SHOPPING_CART_SERVICE_KEY)->getProducts() as $index => $product) {
            $this->createInvoiceItemByProduct($user, $product);
        }

        $invoice = Invoice::create([
            'customer' => $user->getStripeCustomerId()
        ]);

        $invoice->pay();

        return $invoice;
    }

    /**
     * @param User $user
     * @param $token
     * @return \Stripe\ApiResource
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    protected function createCustomer(User $user, $token): \Stripe\ApiResource
    {
        $customer = Customer::create(array(
            "email" => $user->getEmail(),
            "source" => $token
        ));

        $user->setStripeCustomerId($customer->id);
        $this->em->persist($user);
        $this->em->flush();

        return $customer;
    }

    /**
     * @param User $user
     * @param $token
     * @return StripeObject
     */
    protected function updateCustomer(User $user, $token): StripeObject
    {
        /** @var StripeObject $customer */
        $customer = Customer::retrieve($user->getStripeCustomerId());
        $customer->source = $token;
        $customer->save();

        return $customer;
    }

    /**
     * @param User $user
     * @param $product
     * @return \Stripe\ApiResource
     */
    protected function createInvoiceItemByProduct(User $user, Product $product)
    {
        return InvoiceItem::create([
            "amount" => $product->getPrice() * 100,
            "currency" => "usd",
            "customer" => $user->getStripeCustomerId(), // obtained with Stripe.js
            "description" => $product->getName()
        ]);
    }

}