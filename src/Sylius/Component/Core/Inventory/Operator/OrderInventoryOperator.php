<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sylius\Component\Core\Inventory\Operator;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\OrderPaymentStates;
use Webmozart\Assert\Assert;

final class OrderInventoryOperator implements OrderInventoryOperatorInterface
{
    public function cancel(OrderInterface $order): void
    {
        if (in_array(
            $order->getPaymentState(),
            [OrderPaymentStates::STATE_PAID, OrderPaymentStates::STATE_REFUNDED],
            true
        )) {
            $this->giveBack($order);

            return;
        }

        $this->release($order);
    }

    public function hold(OrderInterface $order): void
    {
        /** @var OrderItemInterface $orderItem */
        foreach ($order->getItems() as $orderItem) {
            $variant = $orderItem->getVariant();

            if (!$variant->isTracked()) {
                continue;
            }

            $variant->setOnHold($variant->getOnHold() + $orderItem->getQuantity());
        }
    }

    public function sell(OrderInterface $order): void
    {
        /*
                отвечает за ошибку при выстевлении статуса "оплачено" в администативной панели в заказе
                было отключено по просьбе заказчика так как он не мог подтвердить оплату товара, который закончился на складе
            */
        /** @var OrderItemInterface $orderItem */
        foreach ($order->getItems() as $orderItem) {
            $variant = $orderItem->getVariant();

            if (!$variant->isTracked()) {
                continue;
            }

            $onHold = $variant->getOnHold() - $orderItem->getQuantity();
            $variant->setOnHold($onHold);
            if($onHold < 0) {
                $variant->setOnHold(0);
            }

            $onHand = $variant->getOnHand() - $orderItem->getQuantity();
            $variant->setOnHand($onHand);
            if($onHand < 0) {
                $variant->setOnHand(0);
            }
//            Assert::greaterThanEq(
//                ($variant->getOnHold() - $orderItem->getQuantity()),
//                0,
//                sprintf(
//                    'Not enough units to decrease on hold quantity from the inventory of a variant "%s".',
//                    $variant->getName()
//                )
//            );
//
//            Assert::greaterThanEq(
//                ($variant->getOnHand() - $orderItem->getQuantity()),
//                0,
//                sprintf(
//                    'Not enough units to decrease on hand quantity from the inventory of a variant "%s".',
//                    $variant->getName()
//                )
//            );

//            $variant->setOnHold($variant->getOnHold() - $orderItem->getQuantity());
//            $variant->setOnHand($variant->getOnHand() - $orderItem->getQuantity());
        }
    }

    /**
     * @throws \InvalidArgumentException
     */
    private function release(OrderInterface $order): void
    {
        /** @var OrderItemInterface $orderItem */
        foreach ($order->getItems() as $orderItem) {
            $variant = $orderItem->getVariant();

            if (!$variant->isTracked()) {
                continue;
            }

            Assert::greaterThanEq(
                ($variant->getOnHold() - $orderItem->getQuantity()),
                0,
                sprintf(
                    'Not enough units to decrease on hold quantity from the inventory of a variant "%s".',
                    $variant->getName()
                )
            );

            $variant->setOnHold($variant->getOnHold() - $orderItem->getQuantity());
        }
    }

    private function giveBack(OrderInterface $order): void
    {
        /** @var OrderItemInterface $orderItem */
        foreach ($order->getItems() as $orderItem) {
            $variant = $orderItem->getVariant();

            if (!$variant->isTracked()) {
                continue;
            }

            $variant->setOnHand($variant->getOnHand() + $orderItem->getQuantity());
        }
    }
}
