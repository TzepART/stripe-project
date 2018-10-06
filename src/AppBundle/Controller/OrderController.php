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
use Stripe\Error\Card;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class OrderController
 * @package AppBundle\Controller
 */
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
     * @Route("/checkout", name="order_checkout", schemes={"%secure_channel%"})
     * @Security("is_granted('ROLE_USER')")
     * @Method("GET")
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function checkoutAction(Request $request)
    {
        $products = $this->get(self::SHOPPING_CART_SERVICE_KEY)->getProducts();

        return $this->render('order/checkout.html.twig', array(
            'products' => $products,
            'cart' => $this->get(self::SHOPPING_CART_SERVICE_KEY),
            'stripe_public_key' => $this->getParameter(AppBundle::STRIPE_PUBLIC_KEY)
        ));

    }

    /**
     * @Route("/checkout", name="pay_order", schemes={"%secure_channel%"})
     * @Security("is_granted('ROLE_USER')")
     * @Method("POST")
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function payOrderAction(Request $request)
    {
        /** @var StripeClient $stripeClient */
        $stripeClient = $this->get('app_stripe_client');

        $token = $request->get('stripeToken');

        try {
            $customer = $stripeClient->getCustomerByUser($this->getUser(),$token);
//                $stripeClient->createCharge($this->getUser());
            $stripeClient->createInvoice($this->getUser());
            $this->addFlash('success', 'Order Complete! Congratulation!');
        } catch (Card $e) {
            $this->addFlash('error', $e->getMessage());
        } catch (NotFoundExceptionInterface $e) {
            $this->addFlash('error', $e->getMessage());
        } catch (ContainerExceptionInterface $e) {
            $this->addFlash('error', $e->getMessage());
        }

        $this->get(self::SHOPPING_CART_SERVICE_KEY)->emptyCart();

        return $this->redirectToRoute('homepage');

    }
}