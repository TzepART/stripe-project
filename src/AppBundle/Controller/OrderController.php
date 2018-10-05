<?php

namespace AppBundle\Controller;

use AppBundle\AppBundle;
use AppBundle\Entity\Product;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Stripe\Charge;
use Stripe\Customer;
use Stripe\Invoice;
use Stripe\InvoiceItem;
use Stripe\Stripe;
use Stripe\StripeObject;
use Symfony\Component\HttpFoundation\Request;

class OrderController extends BaseController
{
    const SHOPPING_CART_SERVICE_KEY = 'shopping_cart';

    /**
     * @Route("/cart/product/{slug}", name="order_add_product_to_cart")
     * @Method("POST")
     */
    public function addProductToCartAction(Product $product)
    {
        $this->get(self::SHOPPING_CART_SERVICE_KEY)
            ->addProduct($product);

        $this->addFlash('success', 'Product added!');

        return $this->redirectToRoute('order_checkout');
    }

    /**
     * @Route("/checkout", name="order_checkout")
     * @Security("is_granted('ROLE_USER')")
     */
    public function checkoutAction(Request $request)
    {
        $products = $this->get(self::SHOPPING_CART_SERVICE_KEY)->getProducts();

        if ($request->isMethod('POST')) {

            $token = $request->get('stripeToken');
            Stripe::setApiKey($this->getParameter(AppBundle::STRIPE_SECRET_KEY));

            $customer = $this->createCustomer($token);

//            $this->createCharge($customer);
            $this->createInvoice();

            $this->get(self::SHOPPING_CART_SERVICE_KEY)->emptyCart();
            $this->addFlash('success', 'Order Complete! Congratulation!');

            return $this->redirectToRoute('homepage');
        }

        return $this->render('order/checkout.html.twig', array(
            'products' => $products,
            'cart' => $this->get(self::SHOPPING_CART_SERVICE_KEY),
            'stripe_public_key' => $this->getParameter(AppBundle::STRIPE_PUBLIC_KEY)
        ));

    }

    /**
     * @param $token
     * @return StripeObject
     */
    protected function createCustomer($token)
    {
        $user = $this->getUser();
        if (!$user->getStripeCustomerId()) {
            $customer = Customer::create(array(
                "email" => $user->getEmail(),
                "source" => $token
            ));

            $user->setStripeCustomerId($customer->id);
            $this->getDoctrine()->getManager()->persist($user);
            $this->getDoctrine()->getManager()->flush();
        } else {
            /** @var StripeObject $customer */
            $customer = Customer::retrieve($user->getStripeCustomerId());
            $customer->source = $token;
            $customer->save();
        }

        return $customer;
    }

    /**
     */
    protected function createCharge(): void
    {
        Charge::create(array(
            "amount" => $this->get(self::SHOPPING_CART_SERVICE_KEY)->getTotal() * 100,
            "currency" => "usd",
            "customer" => $this->getUser()->getStripeCustomerId(), // obtained with Stripe.js
        ));
    }

    /**
     */
    protected function createInvoice(): void
    {
        foreach ($this->get(self::SHOPPING_CART_SERVICE_KEY)->getProducts() as $index => $product) {
            InvoiceItem::create(array(
                "amount" => $product->getPrice() * 100,
                "currency" => "usd",
                "customer" => $this->getUser()->getStripeCustomerId(), // obtained with Stripe.js
                "description" => $product->getName()
            ));

        }

        $invoice = Invoice::create([
           'customer' => $this->getUser()->getStripeCustomerId()
        ]);

        $invoice->pay();

    }
}