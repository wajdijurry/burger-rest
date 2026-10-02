<?php

namespace Tests\Unit;

use App\Domain\Purchasing\Enums\PurchaseOrderStatus;
use Tests\TestCase;

class PurchaseOrderStatusTest extends TestCase
{
    public function test_only_draft_and_sent_can_be_sent(): void
    {
        $this->assertTrue(PurchaseOrderStatus::Draft->canBeSent());
        $this->assertTrue(PurchaseOrderStatus::Sent->canBeSent());
        $this->assertFalse(PurchaseOrderStatus::Received->canBeSent());
        $this->assertFalse(PurchaseOrderStatus::Closed->canBeSent());
    }

    public function test_only_sent_and_received_can_accept_a_delivery(): void
    {
        $this->assertFalse(PurchaseOrderStatus::Draft->canAcceptDelivery());
        $this->assertTrue(PurchaseOrderStatus::Sent->canAcceptDelivery());
        $this->assertTrue(PurchaseOrderStatus::Received->canAcceptDelivery());
        $this->assertFalse(PurchaseOrderStatus::Closed->canAcceptDelivery());
    }

    public function test_only_closed_is_not_open(): void
    {
        $this->assertTrue(PurchaseOrderStatus::Draft->isOpen());
        $this->assertTrue(PurchaseOrderStatus::Sent->isOpen());
        $this->assertTrue(PurchaseOrderStatus::Received->isOpen());
        $this->assertFalse(PurchaseOrderStatus::Closed->isOpen());
    }

    public function test_status_after_receiving(): void
    {
        $this->assertSame(PurchaseOrderStatus::Received, PurchaseOrderStatus::Sent->statusAfterReceiving(false));
        $this->assertSame(PurchaseOrderStatus::Closed, PurchaseOrderStatus::Sent->statusAfterReceiving(true));
        $this->assertSame(PurchaseOrderStatus::Received, PurchaseOrderStatus::Received->statusAfterReceiving(false));
        $this->assertSame(PurchaseOrderStatus::Closed, PurchaseOrderStatus::Received->statusAfterReceiving(true));
    }
}
