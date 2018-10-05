<?php

namespace AppBundle\Controller;

use AppBundle\AppBundle;
use AppBundle\Entity\Product;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Stripe\Charge;
use Stripe\Customer;
use Stripe\Stripe;
use Stripe\StripeObject;
use Symfony\Component\HttpFoundation\Request;

class OrderController extends BaseController
{
    const SHOPPING_CART_KEY = 'shopping_cart';

    /**
     * @Route("/cart/product/{slug}", name="order_add_product_to_cart")
     * @Method("POST")
     */
    public function addProductToCartAction(Product $product)
    {
        $this->get(self::SHOPPING_CART_KEY)
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
        $products = $this->get(self::SHOPPING_CART_KEY)->getProducts();

        if ($request->isMethod('POST')) {

            $token = $request->get('stripeToken');
            $customer = $this->createCustomer($token);

            $this->createCharge($customer);

            return $this->redirectToRoute('homepage');
        }

        return $this->render('order/checkout.html.twig', array(
            'products' => $products,
            'cart' => $this->get(self::SHOPPING_CART_KEY),
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
            Stripe::setApiKey("sk_test_68mPncrYfPkg5FGNH1KVPmFR");
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
        Stripe::setApiKey($this->getParameter(AppBundle::STRIPE_SECRET_KEY));
        Charge::create(array(
            "amount" => $this->get(self::SHOPPING_CART_KEY)->getTotal() * 100,
            "currency" => "usd",
            "customer" => $this->getUser()->getStripeCustomerId(), // obtained with Stripe.js
        ));

        $this->get(self::SHOPPING_CART_KEY)->emptyCart();
        $this->addFlash('success', 'Order Complete! Congratulation!');
    }
}