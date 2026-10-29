<?php

namespace App\Http\Controllers;

use App\Sites\Billing;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

/**
 * Receives Stripe events and hands the paid-time changes to Billing. Nothing is
 * trusted until the signature verifies against the webhook secret.
 */
class StripeWebhookController extends Controller
{
    public function handle(Request $request, Billing $billing): Response
    {
        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                (string) $request->header('Stripe-Signature'),
                (string) config('services.stripe.webhook_secret'),
            );
        } catch (UnexpectedValueException|SignatureVerificationException) {
            return response('Invalid signature', 400);
        }

        $object = $event->data->object->toArray();

        match ($event->type) {
            'checkout.session.completed' => $billing->handleCheckoutCompleted($object),
            'invoice.paid' => $billing->handleInvoicePaid($object),
            'customer.subscription.deleted' => $billing->handleSubscriptionDeleted($object),
            default => null,
        };

        return response('ok');
    }
}
