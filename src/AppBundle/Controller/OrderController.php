<?php

namespace AppBundle\Controller;

use AppBundle\AppBundle;
use AppBundle\Entity\Product;
use AppBundle\Service\StripeClient;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Stripe\Stripe;
use Symfony\Component\HttpFoundation\Request;

class OrderController extends BaseController
{
    const SHOPPING_CART_SERVICE_KEY = 'shopping_cart';
    const APP_STRIPE_CLIENT_SERVICE_KEY = 'app_stripe_client';

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
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function checkoutAction(Request $request)
    {
        $products = $this->get(self::SHOPPING_CART_SERVICE_KEY)->getProducts();

        if ($request->isMethod('POST')) {

            $stripeClient = $this->get('app_stripe_client');

            $token = $request->get('stripeToken');
            Stripe::setApiKey($this->getParameter(AppBundle::STRIPE_SECRET_KEY));

            try {
                $customer = $stripeClient->createCustomer($this->getUser(),$token);
//                $stripeClient->createCharge($this->getUser());
                $stripeClient->createInvoice($this->getUser());
            } catch (NotFoundExceptionInterface $e) {

            } catch (ContainerExceptionInterface $e) {

            }

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
}